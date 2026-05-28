<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns AC custom contact fields for the settings page dropdown.
 *
 *   GET /wp-json/swish-ac/v1/ac-fields
 *
 * Response: { ok, fields: [{ id, name }], cached }
 */
class Swish_AC_Rest_Fields {

	const TRANSIENT = 'swish_ac_field_cache';
	const TTL       = 5 * MINUTE_IN_SECONDS;

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
		register_rest_route( SWISH_AC_REST_NS, '/ac-fields', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );
	}

	public function handle( WP_REST_Request $request ) {
		$refresh = (bool) $request->get_param( 'refresh' );
		$fields  = self::get_cached_fields( $refresh );

		if ( is_wp_error( $fields ) ) {
			return new WP_REST_Response( array(
				'ok'      => false,
				'error'   => $fields->get_error_code(),
				'message' => $fields->get_error_message(),
			), 502 );
		}

		return new WP_REST_Response( array(
			'ok'     => true,
			'fields' => $fields,
			'cached' => ! $refresh && (bool) get_transient( self::TRANSIENT ),
		), 200 );
	}

	/**
	 * Fetch contact fields with transient caching.
	 * Returns [{ id, name }] (mapping AC's 'title' to 'name') or WP_Error.
	 */
	public static function get_cached_fields( $refresh = false ) {
		if ( ! $refresh ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$client = Swish_AC_Plugin::client();
		if ( ! $client->is_configured() ) {
			return new WP_Error( 'ac_not_configured', __( 'ActiveCampaign API URL and key are not set.', 'swish-active-campaign' ) );
		}

		$result = $client->list_fields( 100 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fields = array();
		if ( ! empty( $result['fields'] ) && is_array( $result['fields'] ) ) {
			foreach ( $result['fields'] as $f ) {
				if ( isset( $f['id'], $f['title'] ) ) {
					$fields[] = array( 'id' => (string) $f['id'], 'name' => (string) $f['title'] );
				}
			}
		}
		usort( $fields, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );

		set_transient( self::TRANSIENT, $fields, self::TTL );

		return $fields;
	}
}
