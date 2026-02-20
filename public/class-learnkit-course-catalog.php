<?php
/**
 * Course Catalog Shortcode
 *
 * Displays all available courses with enrollment options.
 *
 * @link       https://jameswelbes.com
 * @since      0.3.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/public
 */

/**
 * Course Catalog functionality.
 *
 * @since      0.3.0
 * @package    LearnKit
 * @subpackage LearnKit/public
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Course_Catalog {

	/**
	 * Register shortcode and block.
	 *
	 * @since    0.3.0
	 */
	public function register() {
		add_shortcode( 'learnkit_catalog', array( $this, 'render_catalog' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_learnkit_enroll_course', array( $this, 'handle_enrollment' ) );
	}

	/**
	 * Enqueue catalog styles.
	 *
	 * @since    0.3.0
	 */
	public function enqueue_styles() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'learnkit_catalog' ) || has_block( 'learnkit/catalog', $post ) ) ) {
			wp_enqueue_style(
				'learnkit-course-catalog',
				LEARNKIT_PLUGIN_URL . 'assets/css/course-catalog.css',
				array(),
				LEARNKIT_VERSION,
				'all'
			);
		}
	}

	/**
	 * Enqueue catalog scripts.
	 *
	 * @since    0.3.0
	 */
	public function enqueue_scripts() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'learnkit_catalog' ) || has_block( 'learnkit/catalog', $post ) ) ) {
			wp_enqueue_script(
				'learnkit-course-catalog',
				LEARNKIT_PLUGIN_URL . 'assets/js/course-catalog.js',
				array( 'jquery' ),
				LEARNKIT_VERSION,
				true
			);

			wp_localize_script(
				'learnkit-course-catalog',
				'learnkitCatalog',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'learnkit_enroll' ),
				)
			);
		}
	}

	/**
	 * Render course catalog.
	 *
	 * @since    0.3.0
	 * @param    array $atts Shortcode attributes.
	 * @return   string HTML output.
	 */
	public function render_catalog( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'columns' => 3,
				'order'   => 'DESC',
				'orderby' => 'date',
			),
			$atts,
			'learnkit_catalog'
		);

		// Get all published courses.
		$courses = get_posts(
			array(
				'post_type'      => 'lk_course',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => $atts['orderby'],
				'order'          => $atts['order'],
			)
		);

		if ( empty( $courses ) ) {
			return '<div class="learnkit-catalog learnkit-no-courses">' .
				'<p>' . esc_html__( 'No courses available yet. Check back soon!', 'learnkit' ) . '</p>' .
				'</div>';
		}

		// Get user enrollments if logged in.
		$enrolled_course_ids = array();
		if ( is_user_logged_in() ) {
			global $wpdb;
			$user_id           = get_current_user_id();
			$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$enrolled_course_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT course_id FROM $enrollments_table WHERE user_id = %d AND status = 'active'",
					$user_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		ob_start();
		?>
		<div class="learnkit-catalog" data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">
			<div class="learnkit-catalog-grid">
				<?php foreach ( $courses as $course ) : ?>
					<?php
					$course_id     = (int) $course->ID;
					$is_enrolled   = in_array( $course_id, array_map( 'intval', $enrolled_course_ids ), true );
					$thumbnail_url = get_the_post_thumbnail_url( $course_id, 'medium' );
					if ( ! $thumbnail_url ) {
						$thumbnail_url = LEARNKIT_PLUGIN_URL . 'assets/images/default-course.png';
					}

					// Get module and lesson counts.
					$modules = get_posts(
						array(
							'post_type'      => 'lk_module',
							'posts_per_page' => -1,
							'meta_key'       => '_lk_course_id',
							'meta_value'     => $course_id,
						)
					);

					$lesson_count = 0;
					foreach ( $modules as $module ) {
						$lessons       = get_posts(
							array(
								'post_type'      => 'lk_lesson',
								'posts_per_page' => -1,
								'meta_key'       => '_lk_module_id',
								'meta_value'     => $module->ID,
							)
						);
						$lesson_count += count( $lessons );
					}

					$module_count = count( $modules );

					// Determine access type with backward compatibility.
					$access_type = get_post_meta( $course_id, '_lk_access_type', true );
					if ( empty( $access_type ) ) {
						$access_type = get_post_meta( $course_id, '_lk_self_enrollment', true ) ? 'free' : 'free';
					}
					$self_enroll_enabled = ( 'free' === $access_type );
					?>
					<div class="learnkit-catalog-course <?php echo $is_enrolled ? 'enrolled' : ''; ?>">
						<div class="course-thumbnail">
							<?php if ( $thumbnail_url ) : ?>
								<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $course->post_title ); ?>">
							<?php endif; ?>
							<?php if ( $is_enrolled ) : ?>
								<span class="enrollment-badge"><?php esc_html_e( 'Enrolled', 'learnkit' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="course-info">
							<h3>
								<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>">
									<?php echo esc_html( $course->post_title ); ?>
								</a>
							</h3>
							<?php if ( $course->post_excerpt ) : ?>
								<p class="course-excerpt"><?php echo esc_html( wp_trim_words( $course->post_excerpt, 20 ) ); ?></p>
							<?php endif; ?>
							<div class="course-meta">
								<span class="meta-item">
									<span class="dashicons dashicons-book"></span>
									<?php
									/* translators: %d: number of modules */
									echo esc_html( sprintf( __( '%d Modules', 'learnkit' ), $module_count ) );
									?>
								</span>
								<span class="meta-item">
									<span class="dashicons dashicons-welcome-learn-more"></span>
									<?php
									/* translators: %d: number of lessons */
									echo esc_html( sprintf( __( '%d Lessons', 'learnkit' ), $lesson_count ) );
									?>
								</span>
							</div>
							<div class="course-actions">
								<?php if ( $is_enrolled ) : ?>
									<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="button button-enrolled">
										<?php esc_html_e( 'Continue Learning', 'learnkit' ); ?>
									</a>
								<?php elseif ( is_user_logged_in() && $self_enroll_enabled ) : ?>
									<button class="button button-enroll" data-course-id="<?php echo esc_attr( $course_id ); ?>">
										<?php esc_html_e( 'Enroll Now', 'learnkit' ); ?>
									</button>
								<?php elseif ( is_user_logged_in() ) : ?>
									<?php
									/**
									 * Action: learnkit_course_enrollment_cta
									 *
									 * Fires in the catalog card CTA area for courses that are not free self-enroll.
									 * WooCommerce and other integrations hook here to show Buy Now buttons.
									 *
									 * @since 0.5.0
									 *
									 * @param int  $course_id   The course post ID.
									 * @param int  $user_id     The current user ID (0 if not logged in).
									 * @param bool $is_enrolled Whether the current user is enrolled.
									 */
									do_action( 'learnkit_course_enrollment_cta', $course_id, get_current_user_id(), $is_enrolled );
									?>
								<?php else : ?>
									<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="button button-login">
										<?php esc_html_e( 'Login to Enroll', 'learnkit' ); ?>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle AJAX enrollment request.
	 *
	 * @since    0.3.0
	 */
	public function handle_enrollment() {
		check_ajax_referer( 'learnkit_enroll', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to enroll.', 'learnkit' ) ) );
		}

		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;

		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course ID.', 'learnkit' ) ) );
		}

		// Check if free enrollment is enabled.
		$access_type = get_post_meta( $course_id, '_lk_access_type', true );
		if ( empty( $access_type ) ) {
			$access_type = get_post_meta( $course_id, '_lk_self_enrollment', true ) ? 'free' : 'free';
		}
		if ( 'free' !== $access_type ) {
			wp_send_json_error( array( 'message' => __( 'This course requires purchase to enroll.', 'learnkit' ) ) );
		}

		global $wpdb;
		$user_id           = get_current_user_id();
		$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';

		// Check if already enrolled.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $enrollments_table WHERE user_id = %d AND course_id = %d",
				$user_id,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $existing ) {
			wp_send_json_error( array( 'message' => __( 'You are already enrolled in this course.', 'learnkit' ) ) );
		}

		// Create enrollment.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$enrollments_table,
			array(
				'user_id'     => $user_id,
				'course_id'   => $course_id,
				'enrolled_at' => current_time( 'mysql' ),
				'status'      => 'active',
			),
			array( '%d', '%d', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $inserted ) {
			wp_send_json_success( array( 'message' => __( 'Successfully enrolled!', 'learnkit' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to enroll. Please try again.', 'learnkit' ) ) );
		}
	}

	/**
	 * Register Gutenberg block.
	 *
	 * @since    0.3.0
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register block with inline editor script.
		wp_register_script(
			'learnkit-catalog-block',
			'',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor' ),
			LEARNKIT_VERSION,
			false
		);

		// Add inline script for block editor.
		wp_add_inline_script(
			'learnkit-catalog-block',
			"
			(function(blocks, element, blockEditor) {
				var el = element.createElement;
				var useBlockProps = blockEditor.useBlockProps;
				blocks.registerBlockType('learnkit/catalog', {
					edit: function() {
						return el('div', useBlockProps(), 
							el('div', { style: { padding: '40px', textAlign: 'center', background: '#f9f9f9', border: '2px dashed #dcdcde', borderRadius: '8px' } },
								el('span', { className: 'dashicons dashicons-book', style: { fontSize: '48px', color: '#2271b1', display: 'block', marginBottom: '16px' } }),
								el('h3', { style: { margin: '16px 0 8px' } }, 'Course Catalog'),
								el('p', { style: { color: '#757575', margin: 0 } }, 'Shows all available courses. Preview on frontend.')
							)
						);
					},
					save: function() { return null; }
				});
			})(window.wp.blocks, window.wp.element, window.wp.blockEditor);
			"
		);

		register_block_type(
			LEARNKIT_PLUGIN_DIR . 'blocks/catalog',
			array(
				'render_callback' => array( $this, 'render_catalog' ),
				'editor_script'   => 'learnkit-catalog-block',
			)
		);
	}
}
