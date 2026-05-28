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

		return $this->push_to_ac( $email, null, $list_id, $tags, array(
			'message' => $settings['save_trip_success'],
		) );
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

		return $this->push_to_ac( $email, $name ?: null, $list_id, $tags );
	}

	private function push_to_ac( $email, $first_name, $list_id, $tags, $extra = array() ) {
		$client  = Swish_AC_Plugin::client();
		if ( ! $client->is_configured() ) {
			return $this->error( 'ac_not_configured' );
		}

		$contact = $client->upsert_contact( $email, $first_name );
		if ( is_wp_error( $contact ) ) {
			return $this->wp_error_response( $contact );
		}

		$list_result = $client->add_contact_to_list( $contact['id'], $list_id );
		if ( is_wp_error( $list_result ) ) {
			return $this->wp_error_response( $list_result );
		}

		foreach ( $tags as $tag_name ) {
			$tag_name = trim( (string) $tag_name );
			if ( $tag_name === '' ) {
				continue;
			}
			$tag_id = $client->find_or_create_tag( $tag_name );
			if ( is_wp_error( $tag_id ) ) {
				continue; // Soft fail on tag errors — contact is already saved.
			}
			$client->add_tag_to_contact( $contact['id'], $tag_id );
		}

		return new WP_REST_Response( array_merge( array( 'ok' => true ), $extra ), 200 );
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
