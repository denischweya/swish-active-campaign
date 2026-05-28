<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the swish_popup CPT and all post meta needed by the sidebar panel.
 */
class Swish_AC_CPT_Popup {

	const POST_TYPE = 'swish_popup';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'force_block_editor' ), 101, 2 );
	}

	public function force_block_editor( $use, $post_type ) {
		return $post_type === self::POST_TYPE ? true : $use;
	}

	public function register_cpt() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'               => __( 'Popups', 'swish-active-campaign' ),
				'singular_name'      => __( 'Popup', 'swish-active-campaign' ),
				'add_new'            => __( 'Add New', 'swish-active-campaign' ),
				'add_new_item'       => __( 'Add New Popup', 'swish-active-campaign' ),
				'edit_item'          => __( 'Edit Popup', 'swish-active-campaign' ),
				'new_item'           => __( 'New Popup', 'swish-active-campaign' ),
				'view_item'          => __( 'View Popup', 'swish-active-campaign' ),
				'search_items'       => __( 'Search Popups', 'swish-active-campaign' ),
				'menu_name'          => __( 'Swish Popups', 'swish-active-campaign' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_rest'        => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-megaphone',
			'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'template'            => array(
				array( 'swish/popup', array(), array(
					array( 'core/heading', array( 'level' => 2, 'content' => 'Get our newsletter' ) ),
					array( 'core/paragraph', array( 'content' => 'Sign up to hear about new trips.' ) ),
					array( 'swish/ac-form' ),
				) ),
			),
			'template_lock'       => false,
		) );
	}

	public function register_meta() {
		$string = array( 'string' => array( 'type' => 'string' ) );

		register_post_meta( self::POST_TYPE, '_swish_targeting_mode', array(
			'type'          => 'string',
			'single'        => true,
			'default'       => 'all',
			'show_in_rest'  => true,
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_targeting_urls', array(
			'type'         => 'array',
			'single'       => true,
			'default'      => array(),
			'show_in_rest' => array(
				'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_targeting_post_types', array(
			'type'         => 'array',
			'single'       => true,
			'default'      => array(),
			'show_in_rest' => array(
				'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_targeting_auth', array(
			'type'          => 'string',
			'single'        => true,
			'default'       => 'any',
			'show_in_rest'  => true,
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_trigger_type', array(
			'type'          => 'string',
			'single'        => true,
			'default'       => 'time',
			'show_in_rest'  => true,
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_trigger_time_seconds', array(
			'type'          => 'integer',
			'single'        => true,
			'default'       => 5,
			'show_in_rest'  => true,
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_trigger_scroll_percent', array(
			'type'          => 'integer',
			'single'        => true,
			'default'       => 50,
			'show_in_rest'  => true,
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_trigger_click_selector', array(
			'type'          => 'string',
			'single'        => true,
			'default'       => '',
			'show_in_rest'  => true,
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_freq_dismiss_days', array(
			'type'          => 'integer',
			'single'        => true,
			'default'       => 7,
			'show_in_rest'  => true,
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_freq_hide_after_submit', array(
			'type'          => 'boolean',
			'single'        => true,
			'default'       => true,
			'show_in_rest'  => true,
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_ac_list_id', array(
			'type'          => 'string',
			'single'        => true,
			'default'       => '',
			'show_in_rest'  => true,
			'auth_callback' => array( $this, 'can_edit' ),
		) );

		register_post_meta( self::POST_TYPE, '_swish_ac_tags', array(
			'type'         => 'array',
			'single'       => true,
			'default'      => array(),
			'show_in_rest' => array(
				'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'auth_callback' => array( $this, 'can_edit' ),
		) );
	}

	public function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	public function enqueue_editor_assets() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== self::POST_TYPE ) {
			return;
		}

		$post_types = array(
			array( 'value' => 'home', 'label' => __( 'Homepage', 'swish-active-campaign' ) ),
		);
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( $pt->name === self::POST_TYPE ) {
				continue;
			}
			$post_types[] = array( 'value' => $pt->name, 'label' => $pt->label );
		}

		$data = array(
			'postTypes' => $post_types,
			'tagsUrl'   => esc_url_raw( rest_url( SWISH_AC_REST_NS . '/ac-tags' ) ),
		);

		// Inline data before the block's editor script runs.
		wp_add_inline_script(
			'swish-popup-editor-script',
			'window.swishAcEditor = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}
}
