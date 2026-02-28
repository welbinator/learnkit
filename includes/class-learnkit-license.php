<?php
/**
 * LearnKit License Manager
 *
 * Handles license key storage, validation via the remote API,
 * WordPress update injection, and the "View Details" modal.
 *
 * License keys are UUID strings stored in wp_options as `learnkit_license_key`.
 * The license only gates plugin updates — the plugin always functions regardless
 * of license status.
 *
 * @package LearnKit
 * @since   0.9.2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class LearnKit_License
 */
class LearnKit_License {

	/**
	 * Remote API base URL.
	 */
	const API_BASE = 'https://yffbkjrvslvmnnbdxhur.supabase.co/functions/v1/';

	/**
	 * WordPress option key for the license key.
	 */
	const OPTION_KEY = 'learnkit_license_key';

	/**
	 * WordPress option key for cached license status data.
	 */
	const STATUS_OPTION = 'learnkit_license_status';

	/**
	 * Plugin slug used in WordPress update checks.
	 */
	const PLUGIN_SLUG = 'learnkit';

	/**
	 * Register all hooks.
	 */
	public function register() {
		// Admin settings page under Settings → LearnKit.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// AJAX handler for the "Activate License" button.
		add_action( 'wp_ajax_learnkit_activate_license', array( $this, 'ajax_activate_license' ) );

		// AJAX handler for the "Deactivate License" button.
		add_action( 'wp_ajax_learnkit_deactivate_license', array( $this, 'ajax_deactivate_license' ) );

		// Hook into WordPress update system.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );

		// Hook into the "View Details" plugin modal.
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );

		// Admin notice if no license key is set.
		add_action( 'admin_notices', array( $this, 'maybe_show_license_notice' ) );
	}

	// -------------------------------------------------------------------------
	// Settings Page
	// -------------------------------------------------------------------------

	/**
	 * Register the Settings → LearnKit submenu page.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'LearnKit Settings', 'learnkit' ),
			__( 'LearnKit', 'learnkit' ),
			'manage_options',
			'learnkit-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register the license_key setting.
	 */
	public function register_settings() {
		register_setting(
			'learnkit_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
	}

	/**
	 * Render the Settings → LearnKit page.
	 */
	public function render_settings_page() {
		$license_key    = get_option( self::OPTION_KEY, '' );
		$status_data    = get_option( self::STATUS_OPTION, array() );
		$status_label   = $this->get_status_label( $status_data );
		$status_class   = $this->get_status_class( $status_data );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LearnKit Settings', 'learnkit' ); ?></h1>

			<h2><?php esc_html_e( 'License', 'learnkit' ); ?></h2>
			<p><?php esc_html_e( 'Enter your LearnKit license key to receive plugin updates.', 'learnkit' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="learnkit_license_key"><?php esc_html_e( 'License Key', 'learnkit' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="learnkit_license_key"
							name="learnkit_license_key_input"
							value="<?php echo esc_attr( $license_key ); ?>"
							class="regular-text"
							placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
						/>
						<button
							type="button"
							id="learnkit-activate-license"
							class="button button-primary"
							style="margin-left: 8px;"
						>
							<?php esc_html_e( 'Activate License', 'learnkit' ); ?>
						</button>
						<?php if ( ! empty( $license_key ) ) : ?>
						<button
							type="button"
							id="learnkit-deactivate-license"
							class="button button-secondary"
							style="margin-left: 8px;"
						>
							<?php esc_html_e( 'Deactivate License', 'learnkit' ); ?>
						</button>
						<?php endif; ?>
						<span id="learnkit-license-spinner" class="spinner" style="float:none; margin-top:0; vertical-align:middle;"></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'License Status', 'learnkit' ); ?></th>
					<td>
						<span id="learnkit-license-status" class="learnkit-license-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
						<?php if ( ! empty( $status_data['plan'] ) ) : ?>
							<span style="margin-left: 8px; color: #666;">
								(<?php echo esc_html( ucfirst( $status_data['plan'] ) ); ?>)
							</span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<style>
				.learnkit-license-status { font-weight: 600; }
				.learnkit-status-active   { color: #00a32a; }
				.learnkit-status-inactive { color: #666; }
				.learnkit-status-invalid  { color: #d63638; }
				.learnkit-status-expired  { color: #dba617; }
			</style>

			<script>
			(function($) {
				$('#learnkit-activate-license').on('click', function() {
					var key = $('#learnkit_license_key').val().trim();
					if (!key) {
						alert('<?php echo esc_js( __( 'Please enter a license key.', 'learnkit' ) ); ?>');
						return;
					}
					var $btn     = $(this);
					var $spinner = $('#learnkit-license-spinner');
					var $status  = $('#learnkit-license-status');

					$btn.prop('disabled', true);
					$spinner.addClass('is-active');

					$.post(ajaxurl, {
						action:      'learnkit_activate_license',
						license_key: key,
						nonce:       '<?php echo esc_js( wp_create_nonce( 'learnkit_activate_license' ) ); ?>'
					}, function(response) {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');

						if (response.success) {
							$status
								.text(response.data.label)
								.removeClass('learnkit-status-active learnkit-status-inactive learnkit-status-invalid learnkit-status-expired')
								.addClass('learnkit-status-' + response.data.css_class);
							// Show deactivate button now that a key is active.
							if (!$('#learnkit-deactivate-license').length) {
								$btn.after('<button type="button" id="learnkit-deactivate-license" class="button button-secondary" style="margin-left:8px;"><?php echo esc_js( __( 'Deactivate License', 'learnkit' ) ); ?></button>');
							}
						} else {
							$status
								.text(response.data.message || '<?php echo esc_js( __( 'Error contacting license server.', 'learnkit' ) ); ?>')
								.removeClass('learnkit-status-active learnkit-status-inactive learnkit-status-invalid learnkit-status-expired')
								.addClass('learnkit-status-invalid');
						}
					}).fail(function() {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
						$status
							.text('<?php echo esc_js( __( 'Error contacting license server.', 'learnkit' ) ); ?>')
							.addClass('learnkit-status-invalid');
					});
				});

				$(document).on('click', '#learnkit-deactivate-license', function() {
					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to deactivate this license key?', 'learnkit' ) ); ?>')) {
						return;
					}
					var $btn     = $(this);
					var $spinner = $('#learnkit-license-spinner');
					var $status  = $('#learnkit-license-status');

					$btn.prop('disabled', true);
					$spinner.addClass('is-active');

					$.post(ajaxurl, {
						action: 'learnkit_deactivate_license',
						nonce:  '<?php echo esc_js( wp_create_nonce( 'learnkit_deactivate_license' ) ); ?>'
					}, function(response) {
						$spinner.removeClass('is-active');
						if (response.success) {
							$('#learnkit_license_key').val('');
							$status
								.text(response.data.label)
								.removeClass('learnkit-status-active learnkit-status-inactive learnkit-status-invalid learnkit-status-expired')
								.addClass('learnkit-status-inactive');
							$btn.remove();
						} else {
							$btn.prop('disabled', false);
							alert(response.data.message || '<?php echo esc_js( __( 'Error deactivating license.', 'learnkit' ) ); ?>');
						}
					}).fail(function() {
						$btn.prop('disabled', false);
						$spinner.removeClass('is-active');
						alert('<?php echo esc_js( __( 'Error deactivating license.', 'learnkit' ) ); ?>');
					});
				});
			})(jQuery);
			</script>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX: Activate License
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler — validate the entered license key and persist it.
	 */
	public function ajax_activate_license() {
		check_ajax_referer( 'learnkit_activate_license', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learnkit' ) ) );
		}

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

		if ( empty( $license_key ) ) {
			wp_send_json_error( array( 'message' => __( 'License key is required.', 'learnkit' ) ) );
		}

		$result = $this->call_check_update( $license_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Persist the key and status.
		update_option( self::OPTION_KEY, $license_key );
		update_option( self::STATUS_OPTION, $result );

		$label     = $this->get_status_label( $result );
		$css_class = $this->get_status_class( $result );

		wp_send_json_success(
			array(
				'label'     => $label,
				'css_class' => $css_class,
				'plan'      => isset( $result['plan'] ) ? $result['plan'] : '',
			)
		);
	}

	// -------------------------------------------------------------------------
	// AJAX: Deactivate License
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler — remove the stored license key and status from the DB.
	 */
	public function ajax_deactivate_license() {
		check_ajax_referer( 'learnkit_deactivate_license', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learnkit' ) ) );
		}

		delete_option( self::OPTION_KEY );
		delete_option( self::STATUS_OPTION );

		wp_send_json_success( array( 'label' => __( 'Not activated', 'learnkit' ) ) );
	}

	// -------------------------------------------------------------------------
	// Update Checker
	// -------------------------------------------------------------------------

	/**
	 * Hook into the WordPress plugin update transient.
	 *
	 * @param  object $transient The update_plugins transient.
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$plugin_file = self::PLUGIN_SLUG . '/' . self::PLUGIN_SLUG . '.php';
		$license_key = get_option( self::OPTION_KEY, '' );

		if ( empty( $license_key ) ) {
			// No license key — ensure no stale update entry lingers.
			unset( $transient->response[ $plugin_file ] );
			error_log( '[LearnKit License] No license key set — cleared any stale update entry.' );
			return $transient;
		}

		$result = $this->call_check_update( $license_key );

		if ( is_wp_error( $result ) ) {
			error_log( '[LearnKit License] API call failed: ' . $result->get_error_message() );
			unset( $transient->response[ $plugin_file ] );
			return $transient;
		}

		if ( empty( $result['valid'] ) ) {
			// Invalid/expired license — remove any stale update entry and clear cached status.
			unset( $transient->response[ $plugin_file ] );
			delete_option( self::STATUS_OPTION );
			error_log( '[LearnKit License] Invalid/expired license — cleared update entry. API response: ' . wp_json_encode( $result ) );
			return $transient;
		}

		// Cache the latest status.
		update_option( self::STATUS_OPTION, $result );

		if ( ! empty( $result['update_available'] ) && ! empty( $result['new_version'] ) ) {
			$transient->response[ $plugin_file ] = (object) array(
				'id'          => 'learnkitwp.com/' . self::PLUGIN_SLUG,
				'slug'        => self::PLUGIN_SLUG,
				'plugin'      => $plugin_file,
				'new_version' => sanitize_text_field( $result['new_version'] ),
				'url'         => 'https://learnkitwp.com',
				'package'     => esc_url_raw( $result['download_url'] ),
			);
		} else {
			// Valid license but no update available — remove any stale entry.
			unset( $transient->response[ $plugin_file ] );
		}

		return $transient;
	}

	// -------------------------------------------------------------------------
	// Plugin Info Modal
	// -------------------------------------------------------------------------

	/**
	 * Populate the "View Details" plugin info modal.
	 *
	 * @param  false|object|array $result The result object or array.
	 * @param  string             $action The API action.
	 * @param  object             $args   Request arguments.
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$status_data = get_option( self::STATUS_OPTION, array() );
		$changelog   = ! empty( $status_data['changelog'] ) ? $status_data['changelog'] : '';
		$new_version = ! empty( $status_data['new_version'] ) ? $status_data['new_version'] : LEARNKIT_VERSION;

		$info = (object) array(
			'name'          => 'LearnKit',
			'slug'          => self::PLUGIN_SLUG,
			'version'       => $new_version,
			'author'        => '<a href="https://jameswelbes.com">James Welbes</a>',
			'homepage'      => 'https://learnkitwp.com',
			'requires'      => '6.2',
			'tested'        => '6.7',
			'requires_php'  => '7.4',
			'sections'      => array(
				'description' => 'Modern WordPress LMS plugin for course creators who value simplicity, performance, and fair pricing.',
				'changelog'   => $changelog ? '<p>' . esc_html( $changelog ) . '</p>' : '<p>See <a href="https://learnkitwp.com/changelog">learnkitwp.com/changelog</a>.</p>',
			),
			'download_link' => ! empty( $status_data['download_url'] ) ? esc_url_raw( $status_data['download_url'] ) : '',
		);

		return $info;
	}

	// -------------------------------------------------------------------------
	// API Call
	// -------------------------------------------------------------------------

	/**
	 * Call the check-update endpoint.
	 *
	 * @param  string $license_key The license key to validate.
	 * @return array|WP_Error      Parsed response body or WP_Error on failure.
	 */
	private function call_check_update( $license_key ) {
		$body = wp_json_encode(
			array(
				'license_key'     => $license_key,
				'site_url'        => site_url(),
				'current_version' => LEARNKIT_VERSION,
			)
		);

		$response = wp_remote_post(
			self::API_BASE . 'check-update',
			array(
				'timeout'     => 15,
				'redirection' => 5,
				'headers'     => array(
					'Content-Type' => 'application/json',
				),
				'body'        => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$http_code    = wp_remote_retrieve_response_code( $response );
		$raw_body     = wp_remote_retrieve_body( $response );
		$decoded      = json_decode( $raw_body, true );

		error_log( '[LearnKit License] check-update API response — HTTP ' . $http_code . ' — body: ' . $raw_body );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'learnkit_license_parse_error', __( 'Invalid response from license server.', 'learnkit' ) );
		}

		// 403 = invalid/expired — still a valid response, not a WP_Error.
		if ( 403 === $http_code || empty( $decoded['valid'] ) ) {
			$decoded['valid'] = false;
		}

		return $decoded;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get a human-readable status label from cached status data.
	 *
	 * @param  array $status_data Cached status data from wp_options.
	 * @return string
	 */
	private function get_status_label( $status_data ) {
		if ( empty( $status_data ) ) {
			return __( 'Not activated', 'learnkit' );
		}

		if ( empty( $status_data['valid'] ) ) {
			$error = ! empty( $status_data['error'] ) ? $status_data['error'] : '';
			if ( false !== stripos( $error, 'expired' ) ) {
				return __( 'Expired', 'learnkit' );
			}
			return __( 'Invalid', 'learnkit' );
		}

		$plan = ! empty( $status_data['plan'] ) ? ucfirst( $status_data['plan'] ) : 'Pro';
		/* translators: %s: license plan name, e.g. "Pro" or "Agency" */
		return sprintf( __( 'Active (%s)', 'learnkit' ), $plan );
	}

	/**
	 * Get a CSS class suffix for the status label.
	 *
	 * @param  array $status_data Cached status data.
	 * @return string  One of: active, inactive, invalid, expired.
	 */
	private function get_status_class( $status_data ) {
		if ( empty( $status_data ) ) {
			return 'inactive';
		}

		if ( empty( $status_data['valid'] ) ) {
			$error = ! empty( $status_data['error'] ) ? $status_data['error'] : '';
			if ( false !== stripos( $error, 'expired' ) ) {
				return 'expired';
			}
			return 'invalid';
		}

		return 'active';
	}

	/**
	 * Show an admin notice if no license key has been set.
	 */
	public function maybe_show_license_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only show on LearnKit admin pages and the plugins page.
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$relevant_screens = array( 'settings_page_learnkit-settings', 'plugins' );
		if ( ! in_array( $screen->id, $relevant_screens, true ) ) {
			return;
		}

		$license_key = get_option( self::OPTION_KEY, '' );
		if ( ! empty( $license_key ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Enter your LearnKit license key to receive plugin updates.', 'learnkit' ),
			esc_url( admin_url( 'options-general.php?page=learnkit-settings' ) ),
			esc_html__( 'Go to LearnKit Settings →', 'learnkit' )
		);
	}
}
