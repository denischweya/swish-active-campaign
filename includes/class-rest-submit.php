<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single REST endpoint that handles popup and Save Trip submissions.
 *
 *   POST /wp-json/swish-ac/v1/submit
 *
 * Body (JSON or form-encoded):
 *   source: "popup" | "save_trip"
 *   email:  string (required)
 *   name:   string (popup only, optional)
 *   popup_id: int (popup only)
 *   product_id, product_slug, product_name (save_trip only)
 */
class Swish_AC_Rest_Submit {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( SWISH_AC_REST_NS, '/submit', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'source' => array( 'required' => true, 'type' => 'string' ),
				'email'  => array( 'required' => true, 'type' => 'string' ),
			),
		) );
	}

	public function handle( WP_REST_Request $request ) {
		$source = sanitize_key( $request->get_param( 'source' ) );
		$email  = sanitize_email( $request->get_param( 'email' ) );

		if ( ! is_email( $email ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'invalid_email' ), 400 );
		}

		switch ( $source ) {
			case 'save_trip':
				return $this->handle_save_trip( $request, $email );
			case 'popup':
				return $this->handle_popup( $request, $email );
			default:
				return new WP_REST_Response( array( 'ok' => false, 'error' => 'invalid_source' ), 400 );
		}
	}

	private function handle_save_trip( WP_REST_Request $request, $email ) {
		$settings = Swish_AC_Plugin::get_settings();
		$list_id  = $settings['save_trip_list_id'] !== '' ? $settings['save_trip_list_id'] : $settings['default_list_id'];
		if ( $list_id === '' ) {
			return $this->error( 'no_list_configured' );
		}

		$product_id   = absint( $request->get_param( 'product_id' ) );
		$product_slug = sanitize_title( (string) $request->get_param( 'product_slug' ) );
		$product_name = sanitize_text_field( (string) $request->get_param( 'product_name' ) );

		if ( ! $product_id ) {
			return $this->error( 'missing_product' );
		}

		$tags = array();
		if ( $settings['save_trip_base_tag'] !== '' ) {
			$tags[] = $settings['save_trip_base_tag'];
		}
		if ( $product_slug !== '' && strpos( $settings['save_trip_tag_pattern'], '{slug}' ) !== false ) {
			$tags[] = str_replace( '{slug}', $product_slug, $settings['save_trip_tag_pattern'] );
		}

		$result = $this->do_ac_subscribe( $email, null, $list_id, $tags );
		if ( is_wp_error( $result ) ) {
			return $this->wp_error_response( $result );
		}
		$contact = $result;

		// Append the product title to a custom field, if one is configured.
		if ( ! empty( $settings['save_trip_field_id'] ) && $product_name !== '' ) {
			$this->append_field_value(
				Swish_AC_Plugin::client(),
				$contact['id'],
				$settings['save_trip_field_id'],
				$product_name
			);
		}

		return new WP_REST_Response( array(
			'ok'      => true,
			'message' => $settings['save_trip_success'],
		), 200 );
	}

	private function handle_popup( WP_REST_Request $request, $email ) {
		$popup_id = absint( $request->get_param( 'popup_id' ) );
		if ( ! $popup_id || get_post_type( $popup_id ) !== 'swish_popup' || get_post_status( $popup_id ) !== 'publish' ) {
			return $this->error( 'invalid_popup' );
		}

		$settings = Swish_AC_Plugin::get_settings();
		$meta_list = get_post_meta( $popup_id, '_swish_ac_list_id', true );
		$list_id   = $meta_list !== '' ? $meta_list : $settings['default_list_id'];
		if ( $list_id === '' ) {
			return $this->error( 'no_list_configured' );
		}

		$tags = get_post_meta( $popup_id, '_swish_ac_tags', true );
		$tags = is_array( $tags ) ? $tags : array();

		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );

		$result = $this->do_ac_subscribe( $email, $name ?: null, $list_id, $tags );
		if ( is_wp_error( $result ) ) {
			return $this->wp_error_response( $result );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Upsert contact, subscribe to list, apply tags.
	 * Returns the AC contact array on success or WP_Error.
	 */
	private function do_ac_subscribe( $email, $first_name, $list_id, $tags ) {
		$client = Swish_AC_Plugin::client();
		if ( ! $client->is_configured() ) {
			return new WP_Error( 'ac_not_configured', __( 'ActiveCampaign API URL and key are not set.', 'swish-active-campaign' ) );
		}

		$contact = $client->upsert_contact( $email, $first_name );
		if ( is_wp_error( $contact ) ) {
			return $contact;
		}

		$list_result = $client->add_contact_to_list( $contact['id'], $list_id );
		if ( is_wp_error( $list_result ) ) {
			return $list_result;
		}

		foreach ( $tags as $tag_name ) {
			$tag_name = trim( (string) $tag_name );
			if ( $tag_name === '' ) {
				continue;
			}
			$tag_id = $client->find_or_create_tag( $tag_name );
			if ( is_wp_error( $tag_id ) ) {
				continue; // Soft fail — contact is already saved.
			}
			$client->add_tag_to_contact( $contact['id'], $tag_id );
		}

		return $contact;
	}

	/**
	 * Read the current contact field value, append $value if not already present,
	 * and write it back as a comma-separated string. Failures are silent —
	 * the contact + list + tag work has already succeeded.
	 */
	private function append_field_value( $client, $contact_id, $field_id, $value ) {
		$existing = $client->get_field_value( $contact_id, $field_id );
		if ( is_wp_error( $existing ) ) {
			return;
		}

		$existing_value = ( is_array( $existing ) && isset( $existing['value'] ) ) ? (string) $existing['value'] : '';
		$items = array_values( array_filter( array_map( 'trim', explode( ',', $existing_value ) ), 'strlen' ) );

		if ( ! in_array( $value, $items, true ) ) {
			$items[] = $value;
		}

		$client->set_field_value( $contact_id, $field_id, implode( ', ', $items ) );
	}

	private function error( $code, $status = 400 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => $code ), $status );
	}

	private function wp_error_response( WP_Error $err ) {
		return new WP_REST_Response( array(
			'ok'    => false,
			'error' => $err->get_error_code(),
			'message' => $err->get_error_message(),
		), 502 );
	}
}
