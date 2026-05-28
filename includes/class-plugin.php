<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Swish_AC_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		Swish_AC_Settings::instance();
		Swish_AC_Rest_Submit::instance();
		Swish_AC_Rest_Tags::instance();
		Swish_AC_Rest_Lists::instance();
		Swish_AC_Rest_Fields::instance();
		Swish_AC_CPT_Popup::instance();
		Swish_AC_Blocks::instance();
		Swish_AC_Frontend_Save_Trip::instance();
		Swish_AC_Frontend_Popup::instance();
	}

	public static function get_settings() {
		$defaults = array(
			'ac_api_url'              => '',
			'ac_api_key'              => '',
			'default_list_id'         => '',
			'save_trip_list_id'       => '',
			'save_trip_base_tag'      => 'Saved Trip',
			'save_trip_tag_pattern'   => 'trip:{slug}',
			'save_trip_heading'       => 'Trip Saved',
			'save_trip_description'   => 'You will receive updates about this trip.',
			'save_trip_submit_label'  => 'Notify Me',
			'save_trip_success'       => 'Thanks! Check your inbox for updates.',
			'save_trip_position'      => 'middle-right',
			'save_trip_show_trigger'  => 'immediate',
			'save_trip_show_seconds'  => 0,
			'save_trip_show_scroll'   => 0,
			'save_trip_field_id'      => '',
		);
		$saved = get_option( SWISH_AC_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public static function client() {
		$s = self::get_settings();
		return new Swish_AC_Client( $s['ac_api_url'], $s['ac_api_key'] );
	}
}
