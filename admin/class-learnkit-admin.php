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
		$asset_file = LEARNKIT_PLUGIN_DIR . 'assets/js/learnkit-admin.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array( 'dependencies' => array(), 'version' => $this->version );

		if ( file_exists( LEARNKIT_PLUGIN_DIR . 'assets/js/learnkit-admin.js' ) ) {
			// Enqueue React styles (main styles).
			wp_enqueue_style(
				$this->plugin_name . '-react',
				LEARNKIT_PLUGIN_URL . 'assets/js/style-index.css',
				array(),
				$asset['version'],
				'all'
			);

			// Enqueue Quiz Builder styles.
			wp_enqueue_style(
				$this->plugin_name . '-quiz',
				LEARNKIT_PLUGIN_URL . 'assets/js/index.css',
				array(),
				$asset['version'],
				'all'
			);

			wp_enqueue_script(
				$this->plugin_name,
				$react_bundle,
				$asset['dependencies'],
				$asset['version'],
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

		// Docs submenu.
		add_submenu_page(
			'learnkit',
			__( 'Docs', 'learnkit' ),
			__( 'Docs', 'learnkit' ),
			'manage_options',
			'learnkit-docs',
			array( $this, 'render_docs_page' )
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
			<!-- React app mounts here (title rendered by React to avoid WP's duplicate spinner) -->
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
	/**
	 * Get the list of ACSS button classes from the ACSS config file.
	 *
	 * @return array
	 */
	private function get_acss_button_classes() {
		if ( ! defined( 'ACSS_PLUGIN_DIR' ) ) {
			return array();
		}
		$config_file = ACSS_PLUGIN_DIR . 'config/classes.json';
		if ( ! file_exists( $config_file ) ) {
			return array();
		}
		$decoded = json_decode( file_get_contents( $config_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		// Config may be a flat array or a dict with a 'classes' key.
		$all_classes = isset( $decoded['classes'] ) ? $decoded['classes'] : $decoded;
		if ( ! is_array( $all_classes ) ) {
			return array();
		}
		return array_values(
			array_filter(
				$all_classes,
				function( $class ) {
					return is_string( $class ) && strpos( $class, 'btn--' ) === 0 && 'btn--outline' !== $class;
				}
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since    0.1.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$acss_is_active = defined( 'ACSS_PLUGIN_FILE' );

		// Define all frontend buttons with human-readable labels and their option keys.
		$buttons = array(
			'enroll_button'          => __( 'Enroll Button', 'learnkit' ),
			'start_course_button'    => __( 'Start Course Button', 'learnkit' ),
			'continue_learning_button' => __( 'Continue Learning Button', 'learnkit' ),
			'next_lesson_button'          => __( 'Next Lesson Button', 'learnkit' ),
			'next_lesson_button_disabled' => __( 'Next Lesson Button (Disabled)', 'learnkit' ),
			'prev_lesson_button'          => __( 'Previous Lesson Button', 'learnkit' ),
			'prev_lesson_button_disabled' => __( 'Previous Lesson Button (Disabled)', 'learnkit' ),
			'mark_complete_button'   => __( 'Mark Complete Button', 'learnkit' ),
			'take_quiz_button'       => __( 'Take Quiz Button', 'learnkit' ),
			'start_quiz_button'      => __( 'Start Quiz Button', 'learnkit' ),
			'submit_quiz_button'     => __( 'Submit Quiz Button', 'learnkit' ),
			'retake_quiz_button'     => __( 'Retake Quiz Button', 'learnkit' ),
			'back_to_lesson_button'  => __( 'Back to Lesson Button', 'learnkit' ),
			'back_to_course_button'  => __( 'Back to Course Button', 'learnkit' ),
			'login_button'           => __( 'Log In to Take Quiz Button', 'learnkit' ),
		);

		// Handle "Create All Pages" action.
		if ( isset( $_POST['learnkit_create_pages_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['learnkit_create_pages_nonce'] ) ), 'learnkit_create_pages' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'learnkit' ) );
			}

			$pages_to_create = array(
				'course' => array(
					'title'     => __( 'Course', 'learnkit' ),
					'shortcode' => '[learnkit_course]',
					'option'    => 'learnkit_course_page',
				),
				'lesson' => array(
					'title'     => __( 'Lesson', 'learnkit' ),
					'shortcode' => '[learnkit_lesson]',
					'option'    => 'learnkit_lesson_page',
				),
				'quiz' => array(
					'title'     => __( 'Quiz', 'learnkit' ),
					'shortcode' => '[learnkit_quiz]',
					'option'    => 'learnkit_quiz_page',
				),
			);

			$created = array();
			foreach ( $pages_to_create as $key => $page_def ) {
				// Skip if the option is already set to an existing page.
				$existing = get_option( $page_def['option'] );
				if ( $existing && get_post( $existing ) ) {
					continue;
				}

				$page_id = wp_insert_post( array(
					'post_title'   => $page_def['title'],
					'post_content' => $page_def['shortcode'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				) );

				if ( $page_id && ! is_wp_error( $page_id ) ) {
					update_option( $page_def['option'], $page_id );
					$created[] = $page_def['title'];
				}
			}

			flush_rewrite_rules();

			if ( ! empty( $created ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' .
					sprintf(
						/* translators: %s: comma-separated list of page titles */
						esc_html__( 'Created pages: %s. Rewrite rules flushed.', 'learnkit' ),
						esc_html( implode( ', ', $created ) )
					) .
					'</p></div>';
			} else {
				echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'All template pages already exist — no new pages were created.', 'learnkit' ) . '</p></div>';
			}
		}

		// Save settings.
		if ( isset( $_POST['learnkit_settings_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['learnkit_settings_nonce'] ) ), 'learnkit_settings' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'learnkit' ) );
			}

			if ( $acss_is_active ) {
				$acss_settings = array();
				foreach ( array_keys( $buttons ) as $key ) {
					$acss_settings[ $key . '_class' ]   = sanitize_html_class( wp_unslash( $_POST[ 'learnkit_acss_' . $key . '_class' ] ?? '' ) );
					$acss_settings[ $key . '_outline' ] = isset( $_POST[ 'learnkit_acss_' . $key . '_outline' ] ) ? 1 : 0;
				}
				update_option( 'learnkit_acss_settings', $acss_settings );
			}

			// Save Template Pages settings.
			$course_page = isset( $_POST['learnkit_course_page'] ) ? absint( $_POST['learnkit_course_page'] ) : 0;
			$lesson_page = isset( $_POST['learnkit_lesson_page'] ) ? absint( $_POST['learnkit_lesson_page'] ) : 0;
			$quiz_page   = isset( $_POST['learnkit_quiz_page'] ) ? absint( $_POST['learnkit_quiz_page'] ) : 0;

			$old_course_page = get_option( 'learnkit_course_page' );
			$old_lesson_page = get_option( 'learnkit_lesson_page' );
			$old_quiz_page   = get_option( 'learnkit_quiz_page' );

			update_option( 'learnkit_course_page', $course_page );
			update_option( 'learnkit_lesson_page', $lesson_page );
			update_option( 'learnkit_quiz_page', $quiz_page );

			// Flush rewrite rules whenever any template page setting changes.
			if ( $course_page !== (int) $old_course_page || $lesson_page !== (int) $old_lesson_page || $quiz_page !== (int) $old_quiz_page ) {
				flush_rewrite_rules();
			}

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'learnkit' ) . '</p></div>';
		}

		$acss_settings  = get_option( 'learnkit_acss_settings', array(
			'enroll_button_class'            => '',
			'start_course_button_class'      => '',
			'continue_learning_button_class' => '',
			'next_lesson_button_class'          => '',
			'next_lesson_button_disabled_class' => '',
			'prev_lesson_button_class'          => '',
			'prev_lesson_button_disabled_class' => '',
			'mark_complete_button_class'     => '',
			'take_quiz_button_class'         => '',
			'start_quiz_button_class'        => '',
			'submit_quiz_button_class'       => '',
			'retake_quiz_button_class'       => '',
			'back_to_lesson_button_class'    => '',
			'back_to_course_button_class'    => '',
			'login_button_class'             => '',
		) );
		$btn_classes    = $acss_is_active ? $this->get_acss_button_classes() : array();

		// Read saved template page IDs. If we just POSTed, use the POST values directly
		// to bypass object cache on hosts that cache get_option() aggressively (e.g. Redis).
		if ( isset( $_POST['learnkit_settings_nonce'] ) ) {
			$saved_course_page = isset( $_POST['learnkit_course_page'] ) ? absint( $_POST['learnkit_course_page'] ) : 0;
			$saved_lesson_page = isset( $_POST['learnkit_lesson_page'] ) ? absint( $_POST['learnkit_lesson_page'] ) : 0;
			$saved_quiz_page   = isset( $_POST['learnkit_quiz_page'] ) ? absint( $_POST['learnkit_quiz_page'] ) : 0;
		} else {
			$saved_course_page = (int) get_option( 'learnkit_course_page', 0 );
			$saved_lesson_page = (int) get_option( 'learnkit_lesson_page', 0 );
			$saved_quiz_page   = (int) get_option( 'learnkit_quiz_page', 0 );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php /* Standalone form for "Create All Pages" — kept outside the main settings form
			         to avoid invalid nested <form> elements which break submission in all browsers. */ ?>
			<form method="post" action="" id="learnkit-create-pages-form">
				<?php wp_nonce_field( 'learnkit_create_pages', 'learnkit_create_pages_nonce' ); ?>
			</form>

			<form method="post" action="">
				<?php wp_nonce_field( 'learnkit_settings', 'learnkit_settings_nonce' ); ?>

				<?php if ( $acss_is_active ) : ?>
				<h2><?php esc_html_e( 'Automatic CSS Settings', 'learnkit' ); ?></h2>
				<p><?php esc_html_e( 'Automatic CSS is active. Choose an ACSS button class for each LearnKit button. Check "Outline" to also add the btn--outline modifier.', 'learnkit' ); ?></p>
				<h3><?php esc_html_e( 'Button Classes', 'learnkit' ); ?></h3>
				<table class="form-table" role="presentation">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Button', 'learnkit' ); ?></th>
							<th><?php esc_html_e( 'ACSS Class', 'learnkit' ); ?></th>
							<th><?php esc_html_e( 'Outline', 'learnkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $buttons as $key => $label ) :
							$saved_class   = $acss_settings[ $key . '_class' ] ?? '';
							$saved_outline = ! empty( $acss_settings[ $key . '_outline' ] );
							?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<select name="learnkit_acss_<?php echo esc_attr( $key ); ?>_class" id="learnkit_acss_<?php echo esc_attr( $key ); ?>_class">
									<option value=""><?php esc_html_e( '— None —', 'learnkit' ); ?></option>
									<?php foreach ( $btn_classes as $class ) : ?>
										<option value="<?php echo esc_attr( $class ); ?>" <?php selected( $saved_class, $class ); ?>>
											<?php echo esc_html( $class ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<label>
									<input
										type="checkbox"
										name="learnkit_acss_<?php echo esc_attr( $key ); ?>_outline"
										value="1"
										<?php checked( $saved_outline ); ?>
									/>
									<?php esc_html_e( 'Outline', 'learnkit' ); ?>
								</label>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Template Pages', 'learnkit' ); ?></h2>
				<p><?php esc_html_e( 'Choose WordPress pages to use as wrappers for LearnKit content. The shortcode placed in each page\'s content will render the corresponding course, lesson, or quiz. Leave blank to use the default plugin templates.', 'learnkit' ); ?></p>

				<div style="margin-bottom: 12px;">
					<button type="submit" name="learnkit_create_pages" value="1" class="button button-secondary" form="learnkit-create-pages-form">
						<?php esc_html_e( '⚡ Create All Pages', 'learnkit' ); ?>
					</button>
					<p class="description" style="margin-top: 6px;">
						<?php esc_html_e( 'Automatically creates Course, Lesson, and Quiz pages with the correct shortcodes and assigns them below. Skips any that are already assigned.', 'learnkit' ); ?>
					</p>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="learnkit_course_page"><?php esc_html_e( 'Course Page', 'learnkit' ); ?></label>
							</th>
							<td>
								<select name="learnkit_course_page" id="learnkit_course_page">
									<option value="0"><?php esc_html_e( '— Select a page —', 'learnkit' ); ?></option>
									<?php foreach ( get_pages() as $page ) : ?>
										<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $saved_course_page, $page->ID ); ?>>
											<?php echo esc_html( $page->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Add [learnkit_course] to this page\'s content.', 'learnkit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="learnkit_lesson_page"><?php esc_html_e( 'Lesson Page', 'learnkit' ); ?></label>
							</th>
							<td>
								<select name="learnkit_lesson_page" id="learnkit_lesson_page">
									<option value="0"><?php esc_html_e( '— Select a page —', 'learnkit' ); ?></option>
									<?php foreach ( get_pages() as $page ) : ?>
										<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $saved_lesson_page, $page->ID ); ?>>
											<?php echo esc_html( $page->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Add [learnkit_lesson] to this page\'s content.', 'learnkit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="learnkit_quiz_page"><?php esc_html_e( 'Quiz Page', 'learnkit' ); ?></label>
							</th>
							<td>
								<select name="learnkit_quiz_page" id="learnkit_quiz_page">
									<option value="0"><?php esc_html_e( '— Select a page —', 'learnkit' ); ?></option>
									<?php foreach ( get_pages() as $page ) : ?>
										<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $saved_quiz_page, $page->ID ); ?>>
											<?php echo esc_html( $page->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Add [learnkit_quiz] to this page\'s content.', 'learnkit' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
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

	/**
	 * Render the Docs admin page.
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public function render_docs_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LearnKit Docs', 'learnkit' ); ?></h1>
			<p><?php esc_html_e( 'Developer reference for extending LearnKit with hooks and filters.', 'learnkit' ); ?></p>

			<!-- Table of Contents -->
			<div style="background:#f6f7f7; border:1px solid #c3c4c7; border-radius:4px; padding:16px 24px; margin-bottom:28px; max-width:900px;">
				<strong style="display:block; margin-bottom:8px;"><?php esc_html_e( 'Table of Contents', 'learnkit' ); ?></strong>
				<ol style="margin:0; padding-left:20px; line-height:2;">
					<li><a href="#creating-a-course"><?php esc_html_e( 'Creating a Course', 'learnkit' ); ?></a>
						<ol style="margin:4px 0; padding-left:20px; line-height:2;">
							<li><a href="#course-step-1"><?php esc_html_e( 'Step 1 — Create the course', 'learnkit' ); ?></a></li>
							<li><a href="#course-step-2"><?php esc_html_e( 'Step 2 — Add modules', 'learnkit' ); ?></a></li>
							<li><a href="#course-step-3"><?php esc_html_e( 'Step 3 — Add lessons', 'learnkit' ); ?></a></li>
							<li><a href="#course-step-4"><?php esc_html_e( 'Step 4 — Add quizzes (optional)', 'learnkit' ); ?></a></li>
							<li><a href="#course-step-5"><?php esc_html_e( 'Step 5 — Publish', 'learnkit' ); ?></a></li>
						</ol>
					</li>
					<li><a href="#hooks-filters"><?php esc_html_e( 'Hooks &amp; Filters', 'learnkit' ); ?></a>
						<ol style="margin:4px 0; padding-left:20px; line-height:2;">
							<li><a href="#filter-button-classes"><code>learnkit_button_classes</code></a></li>
						</ol>
					</li>
					<li><a href="#template-pages"><?php esc_html_e( 'Template Pages', 'learnkit' ); ?></a>
						<ol style="margin:4px 0; padding-left:20px; line-height:2;">
							<li><a href="#template-pages-setup"><?php esc_html_e( 'Setup', 'learnkit' ); ?></a></li>
							<li><a href="#template-pages-create"><?php esc_html_e( 'Create All Pages button', 'learnkit' ); ?></a></li>
						</ol>
					</li>
					<li><a href="#woocommerce-integration"><?php esc_html_e( 'WooCommerce Integration', 'learnkit' ); ?></a>
						<ol style="margin:4px 0; padding-left:20px; line-height:2;">
							<li><a href="#wc-step-1"><?php esc_html_e( 'Step 1 — Create a WooCommerce product', 'learnkit' ); ?></a></li>
							<li><a href="#wc-step-2"><?php esc_html_e( 'Step 2 — Link the product to a course', 'learnkit' ); ?></a></li>
							<li><a href="#wc-step-3"><?php esc_html_e( 'Step 3 — Set the course access type', 'learnkit' ); ?></a></li>
							<li><a href="#wc-how-it-works"><?php esc_html_e( 'How it works', 'learnkit' ); ?></a></li>
							<li><a href="#hook-enrollment-cta"><code>learnkit_course_enrollment_cta</code></a></li>
						</ol>
					</li>
				</ol>
			</div>

			<hr>

			<h2 id="creating-a-course"><?php esc_html_e( 'Creating a Course', 'learnkit' ); ?></h2>
			<p><?php esc_html_e( 'Courses in LearnKit are made up of modules, and modules are made up of lessons. Quizzes are optional and can be attached to any lesson. Follow these steps to build your first course.', 'learnkit' ); ?></p>

			<!-- Course Step 1 -->
			<div id="course-step-1" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Step 1 — Create the course', 'learnkit' ); ?></h3>
				<p><?php esc_html_e( 'Go to LearnKit → Course Builder and click "New Course". Give it a title, description, and featured image. You\'ll also choose an Access Type:', 'learnkit' ); ?></p>
				<table class="widefat striped" style="max-width:600px; margin-bottom:16px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Access Type', 'learnkit' ); ?></th>
							<th><?php esc_html_e( 'Description', 'learnkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Free', 'learnkit' ); ?></strong></td>
							<td><?php esc_html_e( 'Students can enroll directly from the course page at no cost. No WooCommerce required.', 'learnkit' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Paid', 'learnkit' ); ?></strong></td>
							<td><?php esc_html_e( 'Students must purchase the course through WooCommerce before gaining access. Requires WooCommerce to be installed and a product linked to the course (see WooCommerce Integration below).', 'learnkit' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Course Step 2 -->
			<div id="course-step-2" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Step 2 — Add modules', 'learnkit' ); ?></h3>
				<p><?php esc_html_e( 'Modules are the top-level sections of your course (e.g. "Introduction", "Module 1: HTML Basics"). Inside the Course Builder, click "Add Module" to create one inline. You can drag modules to reorder them.', 'learnkit' ); ?></p>
			</div>

			<!-- Course Step 3 -->
			<div id="course-step-3" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Step 3 — Add lessons', 'learnkit' ); ?></h3>
				<p><?php esc_html_e( 'Click the edit button on a module to open the module editor. From there you can:', 'learnkit' ); ?></p>
				<ul style="list-style:disc; padding-left:24px;">
					<li><strong><?php esc_html_e( 'Create a new lesson', 'learnkit' ); ?></strong> — <?php esc_html_e( 'Type a title in the "Create new lesson" field and hit Enter. The lesson is created and added to the module immediately.', 'learnkit' ); ?></li>
					<li><strong><?php esc_html_e( 'Add an existing lesson', 'learnkit' ); ?></strong> — <?php esc_html_e( 'Use the "Add existing lesson" dropdown to pick a lesson that\'s already been created and assign it to this module.', 'learnkit' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Each lesson in the list shows three actions:', 'learnkit' ); ?></p>
				<ul style="list-style:disc; padding-left:24px;">
					<li><strong><?php esc_html_e( 'Edit Content', 'learnkit' ); ?></strong> — <?php esc_html_e( 'Opens the lesson in the WordPress block editor so you can write and format the lesson content.', 'learnkit' ); ?></li>
					<li><strong><?php esc_html_e( 'Quiz', 'learnkit' ); ?></strong> — <?php esc_html_e( 'Create or manage a quiz attached to this lesson (see Step 4).', 'learnkit' ); ?></li>
					<li><strong><?php esc_html_e( 'Delete', 'learnkit' ); ?></strong> — <?php esc_html_e( 'Remove the lesson from the module.', 'learnkit' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Each lesson belongs to exactly one module. Drag the handle (⠿) next to a lesson to reorder it. Lesson order determines the Previous / Next navigation students see on the frontend.', 'learnkit' ); ?></p>
			</div>

			<!-- Course Step 4 -->
			<div id="course-step-4" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Step 4 — Add quizzes (optional)', 'learnkit' ); ?></h3>
				<p><?php esc_html_e( 'Quizzes can be attached to any lesson. Inside the module editor, click the "Quiz" link next to a lesson to create or manage its quiz. Each quiz can have multiple questions, each with four answer options and one correct answer.', 'learnkit' ); ?></p>
				<p><?php esc_html_e( 'If a lesson has a quiz attached, students must pass it before the "Next Lesson" button is enabled. You can set a passing score percentage per quiz.', 'learnkit' ); ?></p>
			</div>

			<!-- Course Step 5 -->
			<div id="course-step-5" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Step 5 — Publish', 'learnkit' ); ?></h3>
				<p><?php esc_html_e( 'Once your modules and lessons are set up, publish the course post. Students can browse your course catalog, enroll (free or paid), and begin learning immediately.', 'learnkit' ); ?></p>
				<p><?php esc_html_e( 'When a student completes all lessons and passes all quizzes, a "Download Certificate" button appears on the final lesson.', 'learnkit' ); ?></p>
			</div>

			<hr style="margin: 32px 0;">

			<h2 id="hooks-filters"><?php esc_html_e( 'Hooks &amp; Filters', 'learnkit' ); ?></h2>

			<!-- learnkit_button_classes -->
			<div id="filter-button-classes" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0; font-family:monospace; font-size:1.1rem;">learnkit_button_classes</h3>
				<p><?php esc_html_e( 'Filter the CSS classes applied to any LearnKit frontend button. Use this to add, remove, or replace classes on a per-button basis without modifying template files.', 'learnkit' ); ?></p>

				<table class="widefat striped" style="max-width:600px; margin-bottom:20px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Parameter', 'learnkit' ); ?></th>
							<th><?php esc_html_e( 'Type', 'learnkit' ); ?></th>
							<th><?php esc_html_e( 'Description', 'learnkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>$classes</code></td>
							<td><?php esc_html_e( 'string', 'learnkit' ); ?></td>
							<td><?php esc_html_e( 'Space-separated class string to be output on the element.', 'learnkit' ); ?></td>
						</tr>
						<tr>
							<td><code>$button_key</code></td>
							<td><?php esc_html_e( 'string', 'learnkit' ); ?></td>
							<td><?php esc_html_e( 'Identifier for the button (see keys below).', 'learnkit' ); ?></td>
						</tr>
						<tr>
							<td><code>$base_classes</code></td>
							<td><?php esc_html_e( 'string', 'learnkit' ); ?></td>
							<td><?php esc_html_e( 'The original base classes passed by the template.', 'learnkit' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Available button keys', 'learnkit' ); ?></h4>
				<table class="widefat striped" style="max-width:600px; margin-bottom:20px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Key', 'learnkit' ); ?></th>
							<th><?php esc_html_e( 'Button', 'learnkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$button_keys = array(
							'enroll_button'               => __( 'Enroll Button', 'learnkit' ),
							'start_course_button'         => __( 'Start Course Button', 'learnkit' ),
							'continue_learning_button'    => __( 'Continue Learning Button', 'learnkit' ),
							'next_lesson_button'          => __( 'Next Lesson Button', 'learnkit' ),
							'next_lesson_button_disabled' => __( 'Next Lesson Button (Disabled)', 'learnkit' ),
							'prev_lesson_button'          => __( 'Previous Lesson Button', 'learnkit' ),
							'prev_lesson_button_disabled' => __( 'Previous Lesson Button (Disabled)', 'learnkit' ),
							'mark_complete_button'        => __( 'Mark Complete Button', 'learnkit' ),
							'take_quiz_button'            => __( 'Take Quiz Button', 'learnkit' ),
							'start_quiz_button'           => __( 'Start Quiz Button', 'learnkit' ),
							'submit_quiz_button'          => __( 'Submit Quiz Button', 'learnkit' ),
							'retake_quiz_button'          => __( 'Retake Quiz Button', 'learnkit' ),
							'back_to_lesson_button'       => __( 'Back to Lesson Button', 'learnkit' ),
							'back_to_course_button'       => __( 'Back to Course Button', 'learnkit' ),
							'login_button'                => __( 'Log In to Take Quiz Button', 'learnkit' ),
						);
						foreach ( $button_keys as $key => $label ) :
							?>
						<tr>
							<td><code><?php echo esc_html( $key ); ?></code></td>
							<td><?php echo esc_html( $label ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Examples', 'learnkit' ); ?></h4>

				<p><strong><?php esc_html_e( 'Add a custom class to every button:', 'learnkit' ); ?></strong></p>
				<pre style="background:#1e1e1e; color:#d4d4d4; padding:16px 20px; border-radius:4px; overflow-x:auto; font-size:13px; line-height:1.6;"><code>add_filter( 'learnkit_button_classes', function( $classes, $button_key, $base_classes ) {
    return $classes . ' my-custom-class';
}, 10, 3 );</code></pre>

				<p><strong><?php esc_html_e( 'Add a class only to the Enroll button:', 'learnkit' ); ?></strong></p>
				<pre style="background:#1e1e1e; color:#d4d4d4; padding:16px 20px; border-radius:4px; overflow-x:auto; font-size:13px; line-height:1.6;"><code>add_filter( 'learnkit_button_classes', function( $classes, $button_key, $base_classes ) {
    if ( 'enroll_button' === $button_key ) {
        $classes .= ' btn--xl btn--rounded';
    }
    return $classes;
}, 10, 3 );</code></pre>

				<p><strong><?php esc_html_e( 'Replace all classes on the Mark Complete button:', 'learnkit' ); ?></strong></p>
				<pre style="background:#1e1e1e; color:#d4d4d4; padding:16px 20px; border-radius:4px; overflow-x:auto; font-size:13px; line-height:1.6;"><code>add_filter( 'learnkit_button_classes', function( $classes, $button_key, $base_classes ) {
    if ( 'mark_complete_button' === $button_key ) {
        return 'btn--lk-mark-complete my-complete-btn';
    }
    return $classes;
}, 10, 3 );</code></pre>
			</div>

			<hr style="margin: 32px 0;">
			<h2 id="template-pages"><?php esc_html_e( 'Template Pages', 'learnkit' ); ?></h2>
			<p><?php esc_html_e( 'By default, LearnKit uses its own PHP templates to display courses, lessons, and quizzes. This works out of the box but bypasses your theme\'s header, footer, and any custom page templates you\'ve built with your page builder.', 'learnkit' ); ?></p>
			<p><?php esc_html_e( 'The Template Pages system lets you use normal WordPress pages as wrappers for LearnKit content. Your page builder controls the layout (header, footer, sidebar, container widths, etc.) and a shortcode injects the LearnKit content into the page.', 'learnkit' ); ?></p>

			<div id="template-pages-setup" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'How it works', 'learnkit' ); ?></h3>
				<p><?php esc_html_e( 'When a template page is configured for a content type, LearnKit registers a rewrite rule that maps URLs like:', 'learnkit' ); ?></p>
				<ul style="list-style:disc; padding-left:24px; margin-bottom:12px;">
					<li><code>/course/my-course-slug/</code> <?php esc_html_e( '→ your Course page', 'learnkit' ); ?></li>
					<li><code>/lesson/my-lesson-slug/</code> <?php esc_html_e( '→ your Lesson page', 'learnkit' ); ?></li>
					<li><code>/quiz/my-quiz-slug/</code> <?php esc_html_e( '→ your Quiz page', 'learnkit' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'The slug in the URL tells the shortcode which piece of content to render. Your theme (or page builder template) wraps the entire page, so your custom header, footer, and layout are fully in control.', 'learnkit' ); ?></p>
				<p><?php esc_html_e( 'If a template page is not configured for a content type, LearnKit falls back to its built-in default templates — so you can adopt the system incrementally or not at all.', 'learnkit' ); ?></p>

				<h3><?php esc_html_e( 'Manual setup', 'learnkit' ); ?></h3>
				<ol style="padding-left:24px;">
					<li><?php esc_html_e( 'Create a new WordPress page (e.g. "Lesson").', 'learnkit' ); ?></li>
					<li><?php printf( esc_html__( 'Add the shortcode %s to the page\'s content area (in a text/HTML block).', 'learnkit' ), '<code>[learnkit_lesson]</code>' ); ?></li>
					<li><?php esc_html_e( 'In your page builder, assign whatever template or layout you want to this page — this is the template every lesson will use.', 'learnkit' ); ?></li>
					<li><?php printf( esc_html__( 'Go to %s and select the page you just created under "Lesson Page". Save settings.', 'learnkit' ), '<strong>' . esc_html__( 'LearnKit → Settings → Template Pages', 'learnkit' ) . '</strong>' ); ?></li>
				</ol>
				<p><?php esc_html_e( 'Repeat for Course and Quiz pages as needed.', 'learnkit' ); ?></p>
			</div>

			<div id="template-pages-create" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Create All Pages button', 'learnkit' ); ?></h3>
				<p><?php printf( esc_html__( 'On the %s page, clicking %s will automatically:', 'learnkit' ), '<strong>' . esc_html__( 'LearnKit → Settings', 'learnkit' ) . '</strong>', '<strong>' . esc_html__( '⚡ Create All Pages', 'learnkit' ) . '</strong>' ); ?></p>
				<ul style="list-style:disc; padding-left:24px;">
					<li><?php esc_html_e( 'Create published pages named "Course", "Lesson", and "Quiz"', 'learnkit' ); ?></li>
					<li><?php esc_html_e( 'Add the correct shortcode to each page\'s content automatically', 'learnkit' ); ?></li>
					<li><?php esc_html_e( 'Assign each page to its respective Template Page setting', 'learnkit' ); ?></li>
					<li><?php esc_html_e( 'Flush rewrite rules so the new URL patterns take effect immediately', 'learnkit' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'Pages that are already assigned are skipped. After creation, open each page in your page builder and apply your desired template.', 'learnkit' ); ?></p>
			</div>

			<hr style="margin: 32px 0;">
			<h2 id="woocommerce-integration"><?php esc_html_e( 'WooCommerce Integration', 'learnkit' ); ?></h2>
			<p><?php esc_html_e( 'LearnKit integrates with WooCommerce to gate course access behind a product purchase. No extra plugin required — just follow the steps below.', 'learnkit' ); ?></p>

			<!-- Step 1 -->
			<div id="wc-step-1" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;">
					<?php esc_html_e( 'Step 1 — Create a WooCommerce product', 'learnkit' ); ?>
				</h3>
				<p><?php esc_html_e( 'Create a Simple product in WooCommerce (Products → Add New). Set the price and publish it. Note the product ID — you\'ll need it in the next step.', 'learnkit' ); ?></p>
			</div>

			<!-- Step 2 -->
			<div id="wc-step-2" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;">
					<?php esc_html_e( 'Step 2 — Link the product to a course', 'learnkit' ); ?>
				</h3>
				<p><?php esc_html_e( 'On the WooCommerce product edit screen, find the "Product data" panel and click the "LearnKit Courses" tab. You\'ll see a list of all published courses — select one or more to link them to this product. Students will be enrolled in every selected course when an order containing this product is completed.', 'learnkit' ); ?></p>
				<p><?php esc_html_e( 'You can also set an "Access duration (days)" value. Leave it at 0 for lifetime access, or enter a positive number to automatically expire enrollment after that many days.', 'learnkit' ); ?></p>
			</div>

			<!-- Step 3 -->
			<div id="wc-step-3" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;">
					<?php esc_html_e( 'Step 3 — Set the course access type', 'learnkit' ); ?>
				</h3>
				<p><?php esc_html_e( 'In the course settings (Course Builder → course settings or the post meta), make sure the course is NOT set to self-enrollment. Paid courses should have self-enrollment disabled so only purchasers gain access.', 'learnkit' ); ?></p>
			</div>

			<!-- How it works -->
			<div id="wc-how-it-works" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0;">
					<?php esc_html_e( 'How it works', 'learnkit' ); ?>
				</h3>
				<p><?php esc_html_e( 'When a customer completes a WooCommerce order containing a linked product, LearnKit automatically enrolls them in the associated course(s). No manual enrollment needed.', 'learnkit' ); ?></p>
				<p><?php esc_html_e( 'On the course page and catalog, visitors who are not enrolled see an "Enroll — $X.XX" button that links to:', 'learnkit' ); ?></p>
				<ul style="list-style:disc; padding-left:24px;">
					<li><?php esc_html_e( 'The product page — if only one product is linked to the course', 'learnkit' ); ?></li>
					<li><?php esc_html_e( 'The WooCommerce shop archive filtered to that course\'s products — if multiple products are linked', 'learnkit' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'The "Login to Enroll" button is only shown on free (self-enrollment) courses. Paid courses always show the purchase CTA instead.', 'learnkit' ); ?></p>
			</div>

			<!-- Hook reference -->
			<div id="hook-enrollment-cta" style="background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:24px 28px; margin-bottom:24px; max-width:900px;">
				<h3 style="margin-top:0; font-family:monospace; font-size:1.1rem;">learnkit_course_enrollment_cta</h3>
				<p><?php esc_html_e( 'Action hook that fires in place of the enroll button for paid courses. The built-in WooCommerce integration uses this hook to render the purchase button. You can also use it to add your own payment gateway or custom enrollment logic.', 'learnkit' ); ?></p>
				<table class="widefat striped" style="max-width:600px; margin-bottom:20px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Parameter', 'learnkit' ); ?></th>
							<th><?php esc_html_e( 'Type', 'learnkit' ); ?></th>
							<th><?php esc_html_e( 'Description', 'learnkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>$course_id</code></td>
							<td><?php esc_html_e( 'int', 'learnkit' ); ?></td>
							<td><?php esc_html_e( 'The course post ID.', 'learnkit' ); ?></td>
						</tr>
						<tr>
							<td><code>$user_id</code></td>
							<td><?php esc_html_e( 'int', 'learnkit' ); ?></td>
							<td><?php esc_html_e( 'The current user ID. 0 if not logged in.', 'learnkit' ); ?></td>
						</tr>
						<tr>
							<td><code>$is_enrolled</code></td>
							<td><?php esc_html_e( 'bool', 'learnkit' ); ?></td>
							<td><?php esc_html_e( 'Whether the current user is already enrolled.', 'learnkit' ); ?></td>
						</tr>
					</tbody>
				</table>
				<h4><?php esc_html_e( 'Example — custom payment gateway button', 'learnkit' ); ?></h4>
				<pre style="background:#1e1e1e; color:#d4d4d4; padding:16px 20px; border-radius:4px; overflow-x:auto; font-size:13px; line-height:1.6;"><code>add_action( 'learnkit_course_enrollment_cta', function( $course_id, $user_id, $is_enrolled ) {
    if ( $is_enrolled ) {
        return;
    }
    $checkout_url = 'https://example.com/checkout?course=' . $course_id;
    echo '&lt;a href="' . esc_url( $checkout_url ) . '" class="btn--lk-enroll"&gt;Buy Now&lt;/a&gt;';
}, 10, 3 );</code></pre>
			</div>

		</div>
		<?php
	}
}
