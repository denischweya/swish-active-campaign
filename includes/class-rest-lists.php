<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns AC lists for the block sidebar + settings page dropdowns.
 *
 *   GET /wp-json/swish-ac/v1/ac-lists
 *
 * Response: { ok, lists: [{ id, name }], cached }
 */
class Swish_AC_Rest_Lists {

	const TRANSIENT = 'swish_ac_list_cache';
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
		register_rest_route( SWISH_AC_REST_NS, '/ac-lists', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	public function handle( WP_REST_Request $request ) {
		$refresh = (bool) $request->get_param( 'refresh' );
		$lists   = self::get_cached_lists( $refresh );

		if ( is_wp_error( $lists ) ) {
			return new WP_REST_Response( array(
				'ok'      => false,
				'error'   => $lists->get_error_code(),
				'message' => $lists->get_error_message(),
			), 502 );
		}

		return new WP_REST_Response( array(
			'ok'     => true,
			'lists'  => $lists,
			'cached' => ! $refresh && (bool) get_transient( self::TRANSIENT ),
		), 200 );
	}

	/**
	 * Fetch lists with transient caching. Returns [{id, name}] or WP_Error.
	 */
	public static function get_cached_lists( $refresh = false ) {
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

		$result = $client->list_lists( 100 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$lists = array();
		if ( ! empty( $result['lists'] ) && is_array( $result['lists'] ) ) {
			foreach ( $result['lists'] as $l ) {
				if ( isset( $l['id'], $l['name'] ) ) {
					$lists[] = array( 'id' => (string) $l['id'], 'name' => (string) $l['name'] );
				}
			}
		}
		usort( $lists, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );

		set_transient( self::TRANSIENT, $lists, self::TTL );

		return $lists;
	}
}
