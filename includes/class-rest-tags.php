<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor-only endpoint that returns AC tags for the sidebar picker.
 * Cached in a transient for 5 minutes; ?refresh=1 forces a re-fetch.
 *
 *   GET /wp-json/swish-ac/v1/ac-tags
 */
class Swish_AC_Rest_Tags {

	const TRANSIENT = 'swish_ac_tag_cache';
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
		register_rest_route( SWISH_AC_REST_NS, '/ac-tags', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle' ),
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	public function handle( WP_REST_Request $request ) {
		$refresh = (bool) $request->get_param( 'refresh' );

		if ( ! $refresh ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return new WP_REST_Response( array( 'ok' => true, 'tags' => $cached, 'cached' => true ), 200 );
			}
		}

		$client = Swish_AC_Plugin::client();
		if ( ! $client->is_configured() ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'ac_not_configured' ), 400 );
		}

		$result = $client->list_tags( 100 );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array(
				'ok'      => false,
				'error'   => $result->get_error_code(),
				'message' => $result->get_error_message(),
			), 502 );
		}

		$tags = array();
		if ( ! empty( $result['tags'] ) && is_array( $result['tags'] ) ) {
			foreach ( $result['tags'] as $t ) {
				if ( isset( $t['tag'] ) ) {
					$tags[] = $t['tag'];
				}
			}
		}
		sort( $tags );

		set_transient( self::TRANSIENT, $tags, self::TTL );

		return new WP_REST_Response( array( 'ok' => true, 'tags' => $tags, 'cached' => false ), 200 );
	}
}
