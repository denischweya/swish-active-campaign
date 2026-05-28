<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Swish_AC_Frontend_Save_Trip {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_button' ) );
	}

	public function is_active() {
		return function_exists( 'is_product' ) && is_product();
	}

	public function enqueue() {
		if ( ! $this->is_active() ) {
			return;
		}

		wp_enqueue_style(
			'swish-ac-save-trip',
			SWISH_AC_URL . 'assets/css/save-trip.css',
			array(),
			SWISH_AC_VERSION
		);

		wp_enqueue_script(
			'swish-ac-save-trip',
			SWISH_AC_URL . 'assets/js/save-trip.js',
			array(),
			SWISH_AC_VERSION,
			true
		);

		$settings = Swish_AC_Plugin::get_settings();
		$user     = wp_get_current_user();
		$prefill  = ( $user && $user->ID ) ? $user->user_email : '';

		wp_localize_script( 'swish-ac-save-trip', 'swishAcSaveTrip', array(
			'restUrl'   => esc_url_raw( rest_url( SWISH_AC_REST_NS . '/submit' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'prefill'   => $prefill,
			'trigger'   => array(
				'type'    => $settings['save_trip_show_trigger'],
				'seconds' => (int) $settings['save_trip_show_seconds'],
				'percent' => (int) $settings['save_trip_show_scroll'],
			),
			'copy'      => array(
				'heading'     => $settings['save_trip_heading'],
				'description' => $settings['save_trip_description'],
				'submit'      => $settings['save_trip_submit_label'],
				'success'     => $settings['save_trip_success'],
				'close'       => __( 'Close', 'swish-active-campaign' ),
				'emailLabel'  => __( 'Email address', 'swish-active-campaign' ),
				'emailPh'     => __( 'you@example.com', 'swish-active-campaign' ),
				'tooltip'     => __( 'Save Trip', 'swish-active-campaign' ),
				'errorGeneric'=> __( 'Something went wrong. Please try again.', 'swish-active-campaign' ),
				'errorEmail'  => __( 'Please enter a valid email.', 'swish-active-campaign' ),
			),
		) );
	}

	public function render_button() {
		if ( ! $this->is_active() ) {
			return;
		}
		global $product;
		if ( ! $product ) {
			$product = wc_get_product( get_queried_object_id() );
		}
		if ( ! $product ) {
			return;
		}

		$settings = Swish_AC_Plugin::get_settings();
		$position = $settings['save_trip_position'];
		$show_immediate = $settings['save_trip_show_trigger'] === 'immediate';

		$classes = 'swish-save-trip swish-save-trip--position-' . sanitize_html_class( $position );
		if ( ! $show_immediate ) {
			$classes .= ' swish-save-trip--hidden';
		}
		?>
		<button type="button" class="<?php echo esc_attr( $classes ); ?>"
			data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
			data-product-slug="<?php echo esc_attr( $product->get_slug() ); ?>"
			data-product-name="<?php echo esc_attr( $product->get_name() ); ?>"
			aria-label="<?php esc_attr_e( 'Save Trip', 'swish-active-campaign' ); ?>">
			<span class="swish-save-trip__tooltip" aria-hidden="true"><?php esc_html_e( 'Save Trip', 'swish-active-campaign' ); ?></span>
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" aria-hidden="true" focusable="false">
				<path d="M 12.8125 2 C 12.335938 2.089844 11.992188 2.511719 12 3 L 12 47 C 11.996094 47.359375 12.1875 47.691406 12.496094 47.871094 C 12.804688 48.054688 13.1875 48.054688 13.5 47.875 L 25 41.15625 L 36.5 47.875 C 36.8125 48.054688 37.195313 48.054688 37.503906 47.871094 C 37.8125 47.691406 38.003906 47.359375 38 47 L 38 3 C 38 2.449219 37.550781 2 37 2 L 13 2 C 12.96875 2 12.9375 2 12.90625 2 C 12.875 2 12.84375 2 12.8125 2 Z M 14 4 L 36 4 L 36 45.25 L 25.5 39.125 C 25.191406 38.945313 24.808594 38.945313 24.5 39.125 L 14 45.25 Z"></path>
			</svg>
		</button>
		<?php
	}
}
