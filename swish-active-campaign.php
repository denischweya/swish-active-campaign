<?php
/**
 * Plugin Name: Swish Active Campaign
 * Description: ActiveCampaign integration: lead-magnet popups (CPT + custom block) and a Save Trip button for WooCommerce products.
 * Version: 0.1.0
 * Author: Denis Bosire
 * Text Domain: swish-active-campaign
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SWISH_AC_VERSION', '0.1.0' );
define( 'SWISH_AC_FILE', __FILE__ );
define( 'SWISH_AC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWISH_AC_URL', plugin_dir_url( __FILE__ ) );
define( 'SWISH_AC_OPTION', 'swish_ac_settings' );
define( 'SWISH_AC_REST_NS', 'swish-ac/v1' );

require_once SWISH_AC_DIR . 'includes/class-activecampaign-client.php';
require_once SWISH_AC_DIR . 'includes/class-settings.php';
require_once SWISH_AC_DIR . 'includes/class-rest-submit.php';
require_once SWISH_AC_DIR . 'includes/class-rest-tags.php';
require_once SWISH_AC_DIR . 'includes/class-rest-lists.php';
require_once SWISH_AC_DIR . 'includes/class-cpt-popup.php';
require_once SWISH_AC_DIR . 'includes/class-blocks.php';
require_once SWISH_AC_DIR . 'includes/class-frontend-save-trip.php';
require_once SWISH_AC_DIR . 'includes/class-frontend-popup.php';
require_once SWISH_AC_DIR . 'includes/class-plugin.php';

Swish_AC_Plugin::instance();
