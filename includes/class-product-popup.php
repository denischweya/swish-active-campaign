<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-product popup override.
 *
 * Adds a Swish Popup tab to the WooCommerce product data box. The selected
 * popup is shown on that product's page in place of whatever popups would
 * normally match. The chosen popup's own auth filter, trigger, and
 * frequency cap still apply — only the targeting rule is replaced.
 *
 * Hooks are registered unconditionally; they no-op when WooCommerce is
 * inactive (the filters/actions simply never fire).
 */
class Swish_AC_Product_Popup {

	const META_KEY = '_swish_product_popup_id';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'woocommerce_product_data_tabs',   array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ) );
	}

	public function add_tab( $tabs ) {
		$tabs['swish_ac'] = array(
			'label'    => __( 'Swish Popup', 'swish-active-campaign' ),
			'target'   => 'swish_ac_data',
			'priority' => 70,
		);
		return $tabs;
	}

	public function render_panel() {
		$popups = get_posts( array(
			'post_type'      => Swish_AC_CPT_Popup::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$options = array( '' => __( '— None (use normal targeting) —', 'swish-active-campaign' ) );
		foreach ( $popups as $p ) {
			$options[ $p->ID ] = $p->post_title !== '' ? $p->post_title : sprintf( __( '(no title) #%d', 'swish-active-campaign' ), $p->ID );
		}

		?>
		<div id="swish_ac_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_select( array(
					'id'          => self::META_KEY,
					'label'       => __( 'Override popup', 'swish-active-campaign' ),
					'desc_tip'    => true,
					'description' => __( 'When set, this popup is shown on this product page instead of whatever popups would normally match. The popup\'s own trigger and frequency cap still apply.', 'swish-active-campaign' ),
					'options'     => $options,
				) );
				?>
			</div>
		</div>
		<?php
	}

	public function save( $post_id ) {
		// WooCommerce verifies the nonce around woocommerce_process_product_meta.
		if ( isset( $_POST[ self::META_KEY ] ) ) {
			update_post_meta( $post_id, self::META_KEY, absint( $_POST[ self::META_KEY ] ) );
		}
	}
}
