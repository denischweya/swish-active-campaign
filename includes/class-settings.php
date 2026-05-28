<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Swish_AC_Settings {

	const PAGE_SLUG  = 'swish-active-campaign';
	const GROUP      = 'swish_ac_settings_group';
	const NONCE_TEST = 'swish_ac_test_connection';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_swish_ac_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	public function register_menu() {
		add_options_page(
			__( 'Swish Active Campaign', 'swish-active-campaign' ),
			__( 'Swish AC', 'swish-active-campaign' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			self::GROUP,
			SWISH_AC_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize( $input ) {
		$out = array();
		$fields_text = array(
			'ac_api_url', 'ac_api_key',
			'default_list_id', 'save_trip_list_id',
			'save_trip_base_tag', 'save_trip_tag_pattern',
			'save_trip_heading', 'save_trip_submit_label',
		);
		foreach ( $fields_text as $f ) {
			$out[ $f ] = isset( $input[ $f ] ) ? sanitize_text_field( $input[ $f ] ) : '';
		}
		$fields_textarea = array( 'save_trip_description', 'save_trip_success' );
		foreach ( $fields_textarea as $f ) {
			$out[ $f ] = isset( $input[ $f ] ) ? sanitize_textarea_field( $input[ $f ] ) : '';
		}
		if ( ! empty( $out['ac_api_url'] ) ) {
			$out['ac_api_url'] = esc_url_raw( $out['ac_api_url'] );
		}
		return $out;
	}

	public function enqueue( $hook ) {
		if ( $hook !== 'settings_page_' . self::PAGE_SLUG ) {
			return;
		}
		wp_enqueue_script(
			'swish-ac-settings',
			SWISH_AC_URL . 'assets/js/settings.js',
			array(),
			SWISH_AC_VERSION,
			true
		);
		wp_localize_script( 'swish-ac-settings', 'swishAcSettings', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_TEST ),
			'i18n'    => array(
				'testing' => __( 'Testing…', 'swish-active-campaign' ),
				'ok'      => __( 'Connection successful.', 'swish-active-campaign' ),
				'fail'    => __( 'Connection failed:', 'swish-active-campaign' ),
			),
		) );
	}

	public function ajax_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( self::NONCE_TEST, 'nonce' );

		$client = Swish_AC_Plugin::client();
		if ( ! $client->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Save your API URL and key first.', 'swish-active-campaign' ) ) );
		}

		$result = $client->test_connection();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$email = isset( $result['user']['email'] ) ? $result['user']['email'] : '';
		wp_send_json_success( array(
			'message' => $email
				? sprintf( __( 'Connected as %s', 'swish-active-campaign' ), $email )
				: __( 'Connection successful.', 'swish-active-campaign' ),
		) );
	}

	/**
	 * Render a <select> of AC lists. Falls back to a text input when the AC API
	 * isn't configured or no lists could be fetched, so the field is still editable.
	 *
	 * @param string $id              The element id.
	 * @param string $name            The form name (e.g. swish_ac_settings[default_list_id]).
	 * @param string $current         Currently saved list id.
	 * @param array  $lists           [ ['id' => '...', 'name' => '...'], ... ]
	 * @param bool   $allow_empty     If true, prepend a "Use default" empty option.
	 */
	private function render_list_select( $id, $name, $current, $lists, $allow_empty ) {
		if ( empty( $lists ) ) {
			?>
			<input type="text" id="<?php echo esc_attr( $id ); ?>" class="small-text"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $current ); ?>"
				placeholder="<?php esc_attr_e( 'List ID', 'swish-active-campaign' ); ?>">
			<?php
			return;
		}
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<?php if ( $allow_empty ) : ?>
				<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( '— Use default —', 'swish-active-campaign' ); ?></option>
			<?php endif; ?>
			<?php foreach ( $lists as $list ) : ?>
				<option value="<?php echo esc_attr( $list['id'] ); ?>" <?php selected( $current, $list['id'] ); ?>>
					<?php echo esc_html( $list['name'] . ' (#' . $list['id'] . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = Swish_AC_Plugin::get_settings();
		$lists = Swish_AC_Rest_Lists::get_cached_lists();
		$list_error = is_wp_error( $lists ) ? $lists->get_error_message() : '';
		if ( is_wp_error( $lists ) ) {
			$lists = array();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Swish Active Campaign', 'swish-active-campaign' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'ActiveCampaign API', 'swish-active-campaign' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="swish-ac-api-url"><?php esc_html_e( 'API URL', 'swish-active-campaign' ); ?></label></th>
						<td>
							<input type="url" id="swish-ac-api-url" class="regular-text"
								name="<?php echo esc_attr( SWISH_AC_OPTION ); ?>[ac_api_url]"
								value="<?php echo esc_attr( $s['ac_api_url'] ); ?>"
								placeholder="https://youraccount.api-us1.com">
							<p class="description"><?php esc_html_e( 'Found in ActiveCampaign → Settings → Developer.', 'swish-active-campaign' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swish-ac-api-key"><?php esc_html_e( 'API Key', 'swish-active-campaign' ); ?></label></th>
						<td>
							<input type="password" id="swish-ac-api-key" class="regular-text"
								name="<?php echo esc_attr( SWISH_AC_OPTION ); ?>[ac_api_key]"
								value="<?php echo esc_attr( $s['ac_api_key'] ); ?>"
								autocomplete="new-password">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swish-ac-default-list"><?php esc_html_e( 'Default List', 'swish-active-campaign' ); ?></label></th>
						<td>
							<?php $this->render_list_select( 'swish-ac-default-list', SWISH_AC_OPTION . '[default_list_id]', $s['default_list_id'], $lists, false ); ?>
							<?php if ( $list_error ) : ?>
								<p class="description" style="color:#b32d2e;"><?php echo esc_html( $list_error ); ?></p>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Used for popups that do not specify their own list.', 'swish-active-campaign' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test Connection', 'swish-active-campaign' ); ?></th>
						<td>
							<button type="button" class="button" id="swish-ac-test-connection">
								<?php esc_html_e( 'Test Connection', 'swish-active-campaign' ); ?>
							</button>
							<span id="swish-ac-test-result" style="margin-left:10px;"></span>
							<p class="description"><?php esc_html_e( 'Save your settings before testing.', 'swish-active-campaign' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Save Trip', 'swish-active-campaign' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="swish-ac-save-trip-list"><?php esc_html_e( 'List (optional override)', 'swish-active-campaign' ); ?></label></th>
						<td>
							<?php $this->render_list_select( 'swish-ac-save-trip-list', SWISH_AC_OPTION . '[save_trip_list_id]', $s['save_trip_list_id'], $lists, true ); ?>
							<p class="description"><?php esc_html_e( 'Leave on "Use default" to fall back to the default list above.', 'swish-active-campaign' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swish-ac-save-trip-base-tag"><?php esc_html_e( 'Base Tag', 'swish-active-campaign' ); ?></label></th>
						<td>
							<input type="text" id="swish-ac-save-trip-base-tag" class="regular-text"
								name="<?php echo esc_attr( SWISH_AC_OPTION ); ?>[save_trip_base_tag]"
								value="<?php echo esc_attr( $s['save_trip_base_tag'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swish-ac-save-trip-tag-pattern"><?php esc_html_e( 'Per-Trip Tag Pattern', 'swish-active-campaign' ); ?></label></th>
						<td>
							<input type="text" id="swish-ac-save-trip-tag-pattern" class="regular-text"
								name="<?php echo esc_attr( SWISH_AC_OPTION ); ?>[save_trip_tag_pattern]"
								value="<?php echo esc_attr( $s['save_trip_tag_pattern'] ); ?>">
							<p class="description"><?php esc_html_e( 'Use {slug} for the product slug.', 'swish-active-campaign' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swish-ac-save-trip-heading"><?php esc_html_e( 'Popup Heading', 'swish-active-campaign' ); ?></label></th>
						<td>
							<input type="text" id="swish-ac-save-trip-heading" class="regular-text"
								name="<?php echo esc_attr( SWISH_AC_OPTION ); ?>[save_trip_heading]"
								value="<?php echo esc_attr( $s['save_trip_heading'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swish-ac-save-trip-description"><?php esc_html_e( 'Popup Description', 'swish-active-campaign' ); ?></label></th>
						<td>
							<textarea id="swish-ac-save-trip-description" class="large-text" rows="2"
								name="<?php echo esc_attr( SWISH_AC_OPTION ); ?>[save_trip_description]"><?php
								echo esc_textarea( $s['save_trip_description'] );
							?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swish-ac-save-trip-submit-label"><?php esc_html_e( 'Submit Button Label', 'swish-active-campaign' ); ?></label></th>
						<td>
							<input type="text" id="swish-ac-save-trip-submit-label" class="regular-text"
								name="<?php echo esc_attr( SWISH_AC_OPTION ); ?>[save_trip_submit_label]"
								value="<?php echo esc_attr( $s['save_trip_submit_label'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="swish-ac-save-trip-success"><?php esc_html_e( 'Success Message', 'swish-active-campaign' ); ?></label></th>
						<td>
							<textarea id="swish-ac-save-trip-success" class="large-text" rows="2"
								name="<?php echo esc_attr( SWISH_AC_OPTION ); ?>[save_trip_success]"><?php
								echo esc_textarea( $s['save_trip_success'] );
							?></textarea>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
