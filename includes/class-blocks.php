<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Swish_AC_Blocks {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	public function register_blocks() {
		register_block_type( SWISH_AC_DIR . 'blocks/popup' );
		register_block_type( SWISH_AC_DIR . 'blocks/ac-form' );
	}
}
