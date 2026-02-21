<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueuing
 * the admin-specific stylesheet and JavaScript.
 *
 * @package    LearnKit
 * @subpackage LearnKit/admin
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 * @param    string $plugin_name    The name of this plugin.
	 * @param    string $version        The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    0.1.0
	 * @param    string $hook    The current admin page.
	 */
	public function enqueue_styles( $hook ) {
		// Only load on LearnKit admin pages.
		if ( strpos( $hook, 'learnkit' ) === false ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			LEARNKIT_PLUGIN_URL . 'assets/css/learnkit-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    0.1.0
	 * @param    string $hook    The current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on LearnKit admin pages.
		if ( strpos( $hook, 'learnkit' ) === false ) {
			return;
		}

		// Enqueue WordPress media library.
		wp_enqueue_media();

		// Enqueue React bundle (built from admin/react/).
		$react_bundle = LEARNKIT_PLUGIN_URL . 'assets/js/learnkit-admin.js';

		// Check if React build exists, otherwise use development warning.
		if ( file_exists( LEARNKIT_PLUGIN_DIR . 'assets/js/learnkit-admin.js' ) ) {
			// Enqueue React styles (main styles).
			wp_enqueue_style(
				$this->plugin_name . '-react',
				LEARNKIT_PLUGIN_URL . 'assets/js/style-index.css',
				array(),
				$this->version,
				'all'
			);

			// Enqueue Quiz Builder styles.
			wp_enqueue_style(
				$this->plugin_name . '-quiz',
				LEARNKIT_PLUGIN_URL . 'assets/js/index.css',
				array(),
				$this->version,
				'all'
			);

			wp_enqueue_script(
				$this->plugin_name,
				$react_bundle,
				array(), // React has no jQuery dependency.
				$this->version,
				true
			);

			// Pass data to React app via wp_localize_script.
			wp_localize_script(
				$this->plugin_name,
				'learnkitAdmin',
				array(
					'apiUrl'      => rest_url( 'learnkit/v1' ),
					'wpApiUrl'    => rest_url(),
					'nonce'       => wp_create_nonce( 'wp_rest' ),
					'currentUser' => wp_get_current_user()->ID,
					'pluginUrl'   => LEARNKIT_PLUGIN_URL,
				)
			);
		} else {
			// Development mode: show warning if React bundle doesn't exist yet.
			wp_enqueue_script(
				$this->plugin_name . '-dev-warning',
				LEARNKIT_PLUGIN_URL . 'assets/js/learnkit-admin-dev.js',
				array(),
				$this->version,
				true
			);
		}
	}

	/**
	 * Add LearnKit admin menu.
	 *
	 * Creates main menu item and submenu pages for course management.
	 *
	 * @since    0.1.0
	 */
	public function add_admin_menu() {
		// Main menu item.
		add_menu_page(
			__( 'LearnKit', 'learnkit' ),           // Page title.
			__( 'LearnKit', 'learnkit' ),           // Menu title.
			'manage_options',                        // Capability.
			'learnkit',                              // Menu slug.
			array( $this, 'render_admin_page' ),    // Callback.
			'dashicons-welcome-learn-more',          // Icon.
			20                                       // Position.
		);

		// Course Builder submenu (renders React app).
		add_submenu_page(
			'learnkit',
			__( 'Course Builder', 'learnkit' ),
			__( 'Course Builder', 'learnkit' ),
			'manage_options',
			'learnkit',
			array( $this, 'render_admin_page' )
		);

		// All Courses submenu (links to CPT list).
		add_submenu_page(
			'learnkit',
			__( 'All Courses', 'learnkit' ),
			__( 'All Courses', 'learnkit' ),
			'edit_posts',
			'edit.php?post_type=lk_course'
		);

		// All Modules submenu.
		add_submenu_page(
			'learnkit',
			__( 'All Modules', 'learnkit' ),
			__( 'All Modules', 'learnkit' ),
			'edit_posts',
			'edit.php?post_type=lk_module'
		);

		// All Lessons submenu.
		add_submenu_page(
			'learnkit',
			__( 'All Lessons', 'learnkit' ),
			__( 'All Lessons', 'learnkit' ),
			'edit_posts',
			'edit.php?post_type=lk_lesson'
		);

		// Settings submenu (placeholder for future).
		add_submenu_page(
			'learnkit',
			__( 'Settings', 'learnkit' ),
			__( 'Settings', 'learnkit' ),
			'manage_options',
			'learnkit-settings',
			array( $this, 'render_settings_page' )
		);

		// Email Settings submenu.
		add_submenu_page(
			'learnkit',
			__( 'Email Settings', 'learnkit' ),
			__( 'Email Settings', 'learnkit' ),
			'manage_options',
			'learnkit-email-settings',
			array( $this, 'render_email_settings_page' )
		);
	}

	/**
	 * Render main LearnKit admin page.
	 *
	 * This page contains the React app mount point.
	 *
	 * @since    0.1.0
	 */
	public function render_admin_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<!-- React app mounts here -->
			<div id="learnkit-admin-root"></div>
			<noscript>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'JavaScript must be enabled to use the LearnKit Course Builder.', 'learnkit' ); ?></p>
				</div>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * Placeholder for future settings implementation.
	 *
	 * @since    0.1.0
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'Settings page coming in Sprint 5+. For now, all defaults are active.', 'learnkit' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Quiz Reports page.
	 *
	 * @since    0.4.0
	 */
	public function render_quiz_reports_page() {
		require_once plugin_dir_path( __FILE__ ) . 'quiz-reports.php';
	}

	/**
	 * Render the Email Settings page.
	 *
	 * @since    0.5.0
	 */
	public function render_email_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save handler.
		if ( isset( $_POST['learnkit_email_settings_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['learnkit_email_settings_nonce'] ) ), 'learnkit_email_settings' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'learnkit' ) );
			}

			$settings = array(
				'from_name'        => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? get_bloginfo( 'name' ) ) ),
				'from_email'       => sanitize_email( wp_unslash( $_POST['from_email'] ?? get_option( 'admin_email' ) ) ),
				'welcome_enabled'  => ! empty( $_POST['welcome_enabled'] ),
				'reminder_enabled' => ! empty( $_POST['reminder_enabled'] ),
				'reminder_delay'   => max( 1, absint( wp_unslash( $_POST['reminder_delay'] ?? '7' ) ) ),
			);

			update_option( 'learnkit_email_settings', $settings );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Email settings saved.', 'learnkit' ) . '</p></div>';
		}

		$settings = get_option(
			'learnkit_email_settings',
			array(
				'from_name'        => get_bloginfo( 'name' ),
				'from_email'       => get_option( 'admin_email' ),
				'welcome_enabled'  => true,
				'reminder_enabled' => true,
				'reminder_delay'   => 7,
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Email Settings', 'learnkit' ); ?></h1>
			<form method="post" action="">
				<?php wp_nonce_field( 'learnkit_email_settings', 'learnkit_email_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="from_name"><?php esc_html_e( 'From Name', 'learnkit' ); ?></label>
						</th>
						<td>
							<input type="text" id="from_name" name="from_name"
								value="<?php echo esc_attr( $settings['from_name'] ); ?>"
								class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="from_email"><?php esc_html_e( 'From Email', 'learnkit' ); ?></label>
						</th>
						<td>
							<input type="email" id="from_email" name="from_email"
								value="<?php echo esc_attr( $settings['from_email'] ); ?>"
								class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Welcome Email', 'learnkit' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="welcome_enabled" value="1"
									<?php checked( $settings['welcome_enabled'] ); ?> />
								<?php esc_html_e( 'Send welcome email on enrollment', 'learnkit' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reminder Email', 'learnkit' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="reminder_enabled" value="1"
									<?php checked( $settings['reminder_enabled'] ); ?> />
								<?php esc_html_e( 'Send reminder email for inactive students', 'learnkit' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="reminder_delay"><?php esc_html_e( 'Reminder Delay (days)', 'learnkit' ); ?></label>
						</th>
						<td>
							<input type="number" id="reminder_delay" name="reminder_delay"
								value="<?php echo esc_attr( $settings['reminder_delay'] ); ?>"
								min="1" max="365" class="small-text" />
							<p class="description">
								<?php esc_html_e( 'Days of inactivity before sending a reminder.', 'learnkit' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Email Settings', 'learnkit' ) ); ?>
			</form>
		</div>
		<?php
	}
}
