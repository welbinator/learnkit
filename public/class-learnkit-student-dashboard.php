<?php
/**
 * Student Dashboard Shortcode
 *
 * Displays enrolled courses with progress indicators.
 *
 * @link       https://jameswelbes.com
 * @since      0.3.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/public
 */

/**
 * Student Dashboard functionality.
 *
 * @since      0.3.0
 * @package    LearnKit
 * @subpackage LearnKit/public
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Student_Dashboard {

	/**
	 * Register shortcode and block.
	 *
	 * @since    0.3.0
	 */
	public function register() {
		add_shortcode( 'learnkit_dashboard', array( $this, 'render_dashboard' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue dashboard styles.
	 *
	 * @since    0.3.0
	 */
	public function enqueue_styles() {
		// Only enqueue if shortcode is present or it's a page with the dashboard block.
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'learnkit_dashboard' ) || has_block( 'learnkit/dashboard', $post ) ) ) {
			wp_enqueue_style(
				'learnkit-student-dashboard',
				LEARNKIT_PLUGIN_URL . 'assets/css/student-dashboard.css',
				array(),
				LEARNKIT_VERSION,
				'all'
			);
		}
	}

	/**
	 * Render student dashboard.
	 *
	 * @since    0.3.0
	 * @param    array $atts Shortcode attributes.
	 * @return   string HTML output.
	 */
	public function render_dashboard( $atts = array() ) {
		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return '<div class="learnkit-dashboard learnkit-login-required">' .
				'<p>' . esc_html__( 'Please log in to view your dashboard.', 'learnkit' ) . '</p>' .
				'</div>';
		}

		global $wpdb;
		$user_id = get_current_user_id();
		$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';
		$progress_table    = $wpdb->prefix . 'learnkit_progress';

		// Get enrolled courses.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$enrollments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $enrollments_table WHERE user_id = %d AND status = 'active' ORDER BY enrolled_at DESC",
				$user_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $enrollments ) ) {
			return '<div class="learnkit-dashboard learnkit-no-enrollments">' .
				'<p>' . esc_html__( 'You are not enrolled in any courses yet.', 'learnkit' ) . '</p>' .
				'</div>';
		}

		ob_start();
		?>
		<div class="learnkit-dashboard">
			<h2><?php esc_html_e( 'My Courses', 'learnkit' ); ?></h2>
			<div class="learnkit-dashboard-grid">
				<?php foreach ( $enrollments as $enrollment ) : ?>
					<?php
					$course_id = $enrollment['course_id'];
					$course    = get_post( $course_id );

					if ( ! $course || 'lk_course' !== $course->post_type ) {
						continue;
					}

					// Get course progress.
					$progress_data = $this->get_course_progress( $user_id, $course_id );
					$progress_percent = $progress_data['progress_percent'];
					$completed_lessons = $progress_data['completed_lessons'];
					$total_lessons = $progress_data['total_lessons'];

					// Get course thumbnail.
					$thumbnail_url = get_the_post_thumbnail_url( $course_id, 'medium' );
					if ( ! $thumbnail_url ) {
						$thumbnail_url = LEARNKIT_PLUGIN_URL . 'assets/images/default-course.png';
					}

					// Determine status badge.
					$status_class = 'in-progress';
					$status_text = __( 'In Progress', 'learnkit' );
					if ( 100 === $progress_percent ) {
						$status_class = 'completed';
						$status_text  = __( 'Completed', 'learnkit' );
					} elseif ( 0 === $progress_percent ) {
						$status_class = 'not-started';
						$status_text  = __( 'Not Started', 'learnkit' );
					}
					?>
					<div class="learnkit-dashboard-course">
						<div class="course-thumbnail">
							<?php if ( $thumbnail_url ) : ?>
								<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $course->post_title ); ?>">
							<?php endif; ?>
							<span class="course-status <?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( $status_text ); ?>
							</span>
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
							<div class="course-progress">
								<div class="progress-bar">
									<div class="progress-fill" style="width: <?php echo esc_attr( $progress_percent ); ?>%;"></div>
								</div>
								<div class="progress-stats">
									<span class="progress-percent"><?php echo esc_html( $progress_percent ); ?>%</span>
									<span class="progress-lessons">
										<?php
										/* translators: %1$d: number of completed lessons, %2$d: total number of lessons */
										echo esc_html( sprintf( __( '%1$d of %2$d lessons', 'learnkit' ), $completed_lessons, $total_lessons ) );
										?>
									</span>
								</div>
							</div>
							<div class="course-actions">
								<?php if ( 100 === $progress_percent ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'download_certificate', $course_id, home_url() ) ); ?>" class="button button-certificate">
										<?php esc_html_e( 'Download Certificate', 'learnkit' ); ?>
									</a>
								<?php else : ?>
									<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="button button-continue">
										<?php esc_html_e( 'Continue Learning', 'learnkit' ); ?>
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
	 * Get course progress for a user.
	 *
	 * @since    0.3.0
	 * @param    int $user_id User ID.
	 * @param    int $course_id Course ID.
	 * @return   array Progress data.
	 */
	private function get_course_progress( $user_id, $course_id ) {
		global $wpdb;

		// Get all modules in course.
		$modules = get_posts(
			array(
				'post_type'      => 'lk_module',
				'posts_per_page' => -1,
				'meta_key'       => '_lk_course_id',
				'meta_value'     => $course_id,
			)
		);

		if ( empty( $modules ) ) {
			return array(
				'progress_percent'   => 0,
				'completed_lessons'  => 0,
				'total_lessons'      => 0,
			);
		}

		$module_ids = wp_list_pluck( $modules, 'ID' );

		// Get all lessons in these modules.
		$lessons = get_posts(
			array(
				'post_type'      => 'lk_lesson',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_lk_module_id',
						'value'   => $module_ids,
						'compare' => 'IN',
					),
				),
			)
		);

		$total_lessons = count( $lessons );
		$lesson_ids    = wp_list_pluck( $lessons, 'ID' );

		if ( empty( $lesson_ids ) ) {
			return array(
				'progress_percent'   => 0,
				'completed_lessons'  => 0,
				'total_lessons'      => 0,
			);
		}

		// Get completed lessons.
		$progress_table = $wpdb->prefix . 'learnkit_progress';
		$placeholders   = implode( ',', array_fill( 0, count( $lesson_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$completed = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT lesson_id FROM $progress_table WHERE user_id = %d AND lesson_id IN ($placeholders) AND completed = 1",
				array_merge( array( $user_id ), $lesson_ids )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$completed_count  = count( $completed );
		$progress_percent = $total_lessons > 0 ? round( ( $completed_count / $total_lessons ) * 100 ) : 0;

		return array(
			'progress_percent'   => $progress_percent,
			'completed_lessons'  => $completed_count,
			'total_lessons'      => $total_lessons,
		);
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
			'learnkit-dashboard-block',
			'',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor' ),
			LEARNKIT_VERSION,
			false
		);

		// Add inline script for block editor.
		wp_add_inline_script(
			'learnkit-dashboard-block',
			"
			(function(blocks, element, blockEditor) {
				var el = element.createElement;
				var useBlockProps = blockEditor.useBlockProps;
				blocks.registerBlockType('learnkit/dashboard', {
					edit: function() {
						return el('div', useBlockProps(), 
							el('div', { style: { padding: '40px', textAlign: 'center', background: '#f9f9f9', border: '2px dashed #dcdcde', borderRadius: '8px' } },
								el('span', { className: 'dashicons dashicons-welcome-learn-more', style: { fontSize: '48px', color: '#2271b1', display: 'block', marginBottom: '16px' } }),
								el('h3', { style: { margin: '16px 0 8px' } }, 'Student Dashboard'),
								el('p', { style: { color: '#757575', margin: 0 } }, 'Shows enrolled courses with progress. Preview on frontend.')
							)
						);
					},
					save: function() { return null; }
				});
			})(window.wp.blocks, window.wp.element, window.wp.blockEditor);
			"
		);

		register_block_type(
			LEARNKIT_PLUGIN_DIR . 'blocks/dashboard',
			array(
				'render_callback' => array( $this, 'render_dashboard' ),
				'editor_script'   => 'learnkit-dashboard-block',
			)
		);
	}
}
