<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Swish_AC_Frontend_Popup {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue() {
		if ( is_admin() || is_feed() ) {
			return;
		}

		$popups = $this->collect_active_popups();
		if ( empty( $popups ) ) {
			return;
		}

		wp_enqueue_style(
			'swish-ac-popup',
			SWISH_AC_URL . 'assets/css/popup.css',
			array(),
			SWISH_AC_VERSION
		);

		wp_enqueue_script(
			'swish-ac-popup-loader',
			SWISH_AC_URL . 'assets/js/popup-loader.js',
			array(),
			SWISH_AC_VERSION,
			true
		);

		wp_localize_script( 'swish-ac-popup-loader', 'swishAcPopups', array(
			'restUrl' => esc_url_raw( rest_url( SWISH_AC_REST_NS . '/submit' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'popups'  => $popups,
		) );
	}

	private function collect_active_popups() {
		// Product override: if a product page has a specific popup selected,
		// only show that one; suppress everything else.
		$override = $this->product_override_popups();
		if ( $override !== null ) {
			return $override;
		}

		$query = new WP_Query( array(
			'post_type'      => Swish_AC_CPT_Popup::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'no_found_rows'  => true,
		) );

		$this->debug_log( 'found ' . count( $query->posts ) . ' published popups' );

		$out = array();
		foreach ( $query->posts as $post ) {
			if ( ! $this->matches_current_request( $post->ID ) ) {
				$this->debug_log( 'popup ' . $post->ID . ' (' . $post->post_title . ') did not match targeting for ' . ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) );
				continue;
			}

			$payload = $this->build_popup_payload( $post );
			if ( $payload === null ) {
				continue;
			}

			$this->debug_log( 'popup ' . $post->ID . ' matched and rendered' );
			$out[] = $payload;
		}
		return $out;
	}

	/**
	 * If we're on a single Woo product with a popup override set, return an
	 * array with just that popup (or an empty array if the override popup is
	 * blocked by its own auth filter). Returns null when no override applies
	 * so the normal matching flow runs.
	 */
	private function product_override_popups() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return null;
		}
		$product_id  = get_queried_object_id();
		$override_id = (int) get_post_meta( $product_id, Swish_AC_Product_Popup::META_KEY, true );
		if ( ! $override_id ) {
			return null;
		}

		$popup = get_post( $override_id );
		if ( ! $popup || $popup->post_type !== Swish_AC_CPT_Popup::POST_TYPE || $popup->post_status !== 'publish' ) {
			$this->debug_log( 'product override popup ' . $override_id . ' missing or not published; falling back to normal targeting' );
			return null;
		}

		// Still respect the popup's own logged-in/out filter.
		$auth = get_post_meta( $popup->ID, '_swish_targeting_auth', true ) ?: 'any';
		if ( $auth === 'logged_in'  && ! is_user_logged_in() ) {
			$this->debug_log( 'product override popup ' . $popup->ID . ' suppressed (logged-in only)' );
			return array();
		}
		if ( $auth === 'logged_out' && is_user_logged_in() ) {
			$this->debug_log( 'product override popup ' . $popup->ID . ' suppressed (logged-out only)' );
			return array();
		}

		$payload = $this->build_popup_payload( $popup );
		if ( $payload === null ) {
			return array();
		}

		$this->debug_log( 'product override popup ' . $popup->ID . ' active on product ' . $product_id );
		return array( $payload );
	}

	private function build_popup_payload( WP_Post $post ) {
		$html = $this->render_popup_body( $post );
		if ( $html === '' ) {
			$this->debug_log( 'popup ' . $post->ID . ' rendered to empty HTML (no swish/popup block found in content)' );
			return null;
		}
		return array(
			'id'      => $post->ID,
			'html'    => $html,
			'trigger' => array(
				'type'     => get_post_meta( $post->ID, '_swish_trigger_type', true ) ?: 'time',
				'seconds'  => (int) get_post_meta( $post->ID, '_swish_trigger_time_seconds', true ),
				'percent'  => (int) get_post_meta( $post->ID, '_swish_trigger_scroll_percent', true ),
				'selector' => (string) get_post_meta( $post->ID, '_swish_trigger_click_selector', true ),
			),
			'freq' => array(
				'days'            => (int) get_post_meta( $post->ID, '_swish_freq_dismiss_days', true ),
				'hideAfterSubmit' => (bool) get_post_meta( $post->ID, '_swish_freq_hide_after_submit', true ),
			),
		);
	}

	private function matches_current_request( $popup_id ) {
		$mode = get_post_meta( $popup_id, '_swish_targeting_mode', true ) ?: 'all';
		$auth = get_post_meta( $popup_id, '_swish_targeting_auth', true ) ?: 'any';

		if ( $auth === 'logged_in' && ! is_user_logged_in() ) return false;
		if ( $auth === 'logged_out' && is_user_logged_in() ) return false;

		switch ( $mode ) {
			case 'all':
				return true;
			case 'post_types':
				$pts = get_post_meta( $popup_id, '_swish_targeting_post_types', true );
				$pts = is_array( $pts ) ? $pts : array();
				if ( empty( $pts ) ) return false;
				$current = $this->current_post_types();
				$this->debug_log( 'targeting: current types are [' . implode( ', ', $current ) . '], popup wants [' . implode( ', ', $pts ) . ']' );
				return (bool) array_intersect( $pts, $current );
			case 'urls':
				$patterns = get_post_meta( $popup_id, '_swish_targeting_urls', true );
				return $this->any_pattern_matches( is_array( $patterns ) ? $patterns : array() );
			case 'exclude':
				$patterns = get_post_meta( $popup_id, '_swish_targeting_urls', true );
				return ! $this->any_pattern_matches( is_array( $patterns ) ? $patterns : array() );
		}
		return false;
	}

	/**
	 * The set of post-type values the current request matches. A static front
	 * page matches both 'home' AND its underlying post type ('page'), so users
	 * targeting either get the popup. Posts-as-front matches just 'home'.
	 */
	private function current_post_types() {
		$types = array();
		if ( is_front_page() || is_home() ) {
			$types[] = 'home';
		}
		if ( is_singular() ) {
			$pt = get_post_type( get_queried_object_id() );
			if ( $pt ) {
				$types[] = $pt;
			}
		}
		return array_values( array_unique( $types ) );
	}

	private function any_pattern_matches( array $patterns ) {
		if ( empty( $patterns ) ) return false;
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '/';
		$path = $path ?: '/';
		foreach ( $patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( $pattern === '' ) continue;
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
			if ( preg_match( $regex, $path ) ) return true;
		}
		return false;
	}

	private function debug_log( $msg ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[swish-ac] ' . $msg );
		}
	}

	private function render_popup_body( WP_Post $post ) {
		$blocks = parse_blocks( $post->post_content );
		$html = '';
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'swish/popup' ) {
				$html .= render_block( $block );
			}
		}
		return $html;
	}
}
