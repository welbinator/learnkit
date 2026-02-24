<?php
/**
 * Template for displaying single course
 *
 * @link       https://jameswelbes.com
 * @since      0.4.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/public/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-scoped variables, not true PHP globals.

get_header();

$course_id = get_the_ID();
$user_id   = get_current_user_id();

// Get course modules.
$modules = get_posts(
	array(
		'post_type'      => 'lk_module',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => '_lk_course_id',
				'value' => $course_id,
			),
		),
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	)
);

// Check enrollment status.
$is_enrolled        = false;
$continue_lesson_id = 0;
$progress           = array(
	'completed'  => 0,
	'total'      => 0,
	'percentage' => 0,
);

if ( $user_id ) {
	$is_enrolled = learnkit_is_enrolled( $user_id, $course_id );

	if ( $is_enrolled ) {
		// Calculate progress.
		$all_lessons = array();
		foreach ( $modules as $module ) {
			$module_lessons = get_posts(
				array(
					'post_type'              => 'lk_lesson',
					'posts_per_page'         => -1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'fields'                 => 'ids',
					'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_lk_module_id',
							'value' => $module->ID,
						),
					),
				)
			);
			$all_lessons    = array_merge( $all_lessons, $module_lessons );
		}

		$progress['total'] = count( $all_lessons );

		if ( $progress['total'] > 0 ) {
			$placeholders = implode( ',', array_fill( 0, count( $all_lessons ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$completed_lessons      = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT lesson_id FROM {$wpdb->prefix}learnkit_progress WHERE user_id = %d AND lesson_id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					array_merge( array( $user_id ), $all_lessons )
				)
			);
			$progress['completed']  = count( $completed_lessons );
			$progress['percentage'] = round( ( $progress['completed'] / $progress['total'] ) * 100 );
		}

		// Find the next lesson to continue: first uncompleted lesson in order.
		$continue_lesson_id = 0;
		foreach ( $modules as $module ) {
			$ordered_lessons = get_posts(
				array(
					'post_type'              => 'lk_lesson',
					'posts_per_page'         => -1,
					'orderby'                => 'menu_order',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'fields'                 => 'ids',
					'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_lk_module_id',
							'value' => $module->ID,
						),
					),
				)
			);
			foreach ( $ordered_lessons as $lesson_id ) {
				if ( ! in_array( $lesson_id, $completed_lessons, true ) ) {
					$continue_lesson_id = $lesson_id;
					break 2;
				}
			}
		}
		// If all lessons completed, link to the first lesson.
		if ( 0 === $continue_lesson_id && ! empty( $all_lessons ) ) {
			$continue_lesson_id = $all_lessons[0];
		}
	}
}

// Determine access type with backward compatibility.
$access_type = get_post_meta( $course_id, '_lk_access_type', true );
if ( empty( $access_type ) ) {
	$access_type = get_post_meta( $course_id, '_lk_self_enrollment', true ) ? 'free' : 'free';
}
$self_enrollment = ( 'free' === $access_type ); // Keep $self_enrollment var for template compat.
?>

<div class="lk-course-single">
	<!-- Hero Section -->
	<div class="lk-course-hero">
		<div class="lk-course-hero-content">
			<div>
				<h1 class="lk-course-title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<div class="lk-course-excerpt"><?php the_excerpt(); ?></div>
				<?php endif; ?>

				<?php if ( $is_enrolled ) : ?>
					<a href="<?php echo esc_url( get_permalink( $continue_lesson_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'continue_learning_button', 'btn--lk-continue' ) ); ?>">
						Continue Learning →
					</a>
				<?php elseif ( $user_id && $self_enrollment ) : ?>
					<button class="<?php echo esc_attr( learnkit_button_classes( 'enroll_button', 'btn--lk-enroll' ) ); ?>" data-course-id="<?php echo esc_attr( $course_id ); ?>">
						Enroll Now
					</button>
				<?php elseif ( ! $user_id ) : ?>
					<div class="lk-login-prompt">
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Login to Enroll</a>
					</div>
				<?php endif; ?>

				<?php
				/**
				 * Action: learnkit_course_enrollment_cta
				 *
				 * Fires after the standard enrollment controls on the course page.
				 * WooCommerce and other integrations hook here to show Buy Now buttons
				 * or membership prompts.
				 *
				 * @since 0.4.0
				 *
				 * @param int  $course_id   The course post ID.
				 * @param int  $user_id     The current user ID (0 if not logged in).
				 * @param bool $is_enrolled Whether the current user is enrolled.
				 */
				do_action( 'learnkit_course_enrollment_cta', $course_id, $user_id, $is_enrolled );
				?>
			</div>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="lk-course-featured-image">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Content Section -->
	<div class="lk-course-content">
		<?php if ( $is_enrolled ) : ?>
			<!-- Progress Section -->
			<div class="lk-progress-section">
				<h2 style="margin: 0 0 10px 0; font-size: 24px;">Your Progress</h2>
				<p style="margin: 0 0 15px 0; color: #757575;">
					<?php echo esc_html( $progress['completed'] ); ?> of <?php echo esc_html( $progress['total'] ); ?> lessons completed
				</p>
				<div class="lk-progress-bar-container">
					<div class="lk-progress-bar" style="width: <?php echo esc_attr( $progress['percentage'] ); ?>%;">
						<?php echo esc_html( $progress['percentage'] ); ?>%
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Course Description -->
		<?php
		$content = get_the_content();
		if ( ! empty( $content ) ) :
			?>
			<div class="lk-course-description" style="margin-bottom: 40px; line-height: 1.8; font-size: 16px;">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<!-- Modules & Lessons -->
		<?php if ( ! empty( $modules ) ) : ?>
			<div class="lk-modules-section">
				<h2 class="lk-section-title">Course Content</h2>

				<?php
				$completed_lesson_ids = array();
				if ( $is_enrolled && $user_id ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$completed_lesson_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT lesson_id FROM {$wpdb->prefix}learnkit_progress WHERE user_id = %d",
							$user_id
						)
					);
				}

				// Batch-fetch all lesson IDs for all modules in one pass.
				$all_lesson_ids = array();
				foreach ( $modules as $module ) {
					$lesson_ids_for_module = get_posts(
						array(
							'post_type'              => 'lk_lesson',
							'posts_per_page'         => -1,
							'meta_key'               => '_lk_module_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value'             => $module->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							'fields'                 => 'ids',
							'no_found_rows'          => true,
							'update_post_meta_cache' => false,
							'update_post_term_cache' => false,
						)
					);
					$all_lesson_ids = array_merge( $all_lesson_ids, $lesson_ids_for_module );
				}

				// Batch-fetch quiz IDs for all lessons (one query).
				$quiz_id_by_lesson = array();
				if ( ! empty( $all_lesson_ids ) ) {
					global $wpdb;
					$placeholders = implode( ',', array_fill( 0, count( $all_lesson_ids ), '%d' ) );
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					$quiz_rows = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT p.ID as quiz_id, pm.meta_value as lesson_id
							 FROM {$wpdb->posts} p
							 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_lk_lesson_id'
							 WHERE p.post_type = 'lk_quiz' AND p.post_status = 'publish'
							 AND pm.meta_value IN ({$placeholders})",
							$all_lesson_ids
						)
					);
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					foreach ( $quiz_rows as $row ) {
						$quiz_id_by_lesson[ (int) $row->lesson_id ] = (int) $row->quiz_id;
					}
				}

				// Batch-fetch quiz attempts for the current user (one query).
				$attempt_by_quiz = array();
				$batch_quiz_ids  = array_values( $quiz_id_by_lesson );
				if ( ! empty( $batch_quiz_ids ) && is_user_logged_in() ) {
					$placeholders2 = implode( ',', array_fill( 0, count( $batch_quiz_ids ), '%d' ) );
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$attempts = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT quiz_id, score, passed
							 FROM {$wpdb->prefix}learnkit_quiz_attempts
							 WHERE user_id = %d AND quiz_id IN ({$placeholders2})
							 ORDER BY completed_at DESC",
							array_merge( array( get_current_user_id() ), $batch_quiz_ids )
						)
					);
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					foreach ( $attempts as $attempt ) {
						if ( ! isset( $attempt_by_quiz[ (int) $attempt->quiz_id ] ) ) {
							$attempt_by_quiz[ (int) $attempt->quiz_id ] = $attempt;
						}
					}
				}

				foreach ( $modules as $index => $module ) :
					$lessons = get_posts(
						array(
							'post_type'      => 'lk_lesson',
							'posts_per_page' => -1,
							'no_found_rows'  => true,
							'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
								array(
									'key'   => '_lk_module_id',
									'value' => $module->ID,
								),
							),
							'orderby'        => 'menu_order',
							'order'          => 'ASC',
						)
					);
					?>
					<div class="lk-module <?php echo 0 === $index ? 'open' : ''; ?>">
						<div class="lk-module-header">
							<div>
								<h3 class="lk-module-title"><?php echo esc_html( $module->post_title ); ?></h3>
								<div class="lk-module-meta">
									<?php echo esc_html( count( $lessons ) ); ?> <?php echo count( $lessons ) === 1 ? 'lesson' : 'lessons'; ?>
								</div>
							</div>
							<span class="lk-module-toggle">▼</span>
						</div>

						<div class="lk-lessons-list">
							<?php foreach ( $lessons as $lesson ) : ?>
								<div class="lk-lesson-item">
									<?php if ( $is_enrolled ) : ?>
										<a href="<?php echo esc_url( get_permalink( $lesson->ID ) ); ?>" class="lk-lesson-title">
											📖 <?php echo esc_html( $lesson->post_title ); ?>
										</a>
										<span class="lk-lesson-status">
											<?php echo in_array( (int) $lesson->ID, array_map( 'intval', $completed_lesson_ids ), true ) ? '✓' : '○'; ?>
										</span>
									<?php else : ?>
										<span class="lk-lesson-title lk-lesson-locked">
											🔒 <?php echo esc_html( $lesson->post_title ); ?>
										</span>
									<?php endif; ?>
								</div>

								<?php
								// Use batch-fetched quiz/attempt data instead of per-lesson queries.
								$lq_id       = isset( $quiz_id_by_lesson[ $lesson->ID ] ) ? $quiz_id_by_lesson[ $lesson->ID ] : 0;
								$lesson_quiz = $lq_id ? get_post( $lq_id ) : null;
								if ( $lesson_quiz ) :
									$quiz_attempt = $lq_id && isset( $attempt_by_quiz[ $lq_id ] ) ? $attempt_by_quiz[ $lq_id ] : null;
									if ( ! $is_enrolled || ! $user_id ) {
										$quiz_attempt = null;
									}
									?>
									<div class="lk-lesson-item" style="padding-left: 48px; background: #f9f9f9;">
										<?php if ( $is_enrolled ) : ?>
											<a href="<?php echo esc_url( get_permalink( $lesson_quiz->ID ) ); ?>" class="lk-lesson-title" style="color: #2271b1;">
												<span class="lk-icon"><svg aria-hidden="true" focusable="false" width="1.5em" height="1.5em" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" fill="var(--base, #757575)"><g><path d="M154.8,424.7h202.4c12.8,0,23.2-10.4,23.2-23.2V139.3c0-12.8-10.4-23.2-23.2-23.2h-29.8V94.8c0-4.1-3.4-7.5-7.5-7.5s-7.5,3.4-7.5,7.5v21.3h-48.9V94.8c0-4.1-3.4-7.5-7.5-7.5s-7.5,3.4-7.5,7.5v21.3h-48.9V94.8c0-4.1-3.4-7.5-7.5-7.5s-7.5,3.4-7.5,7.5v21.3h-29.8c-12.8,0-23.2,10.4-23.2,23.2v262.1C131.6,414.2,142,424.7,154.8,424.7z M146.6,139.3c0-4.5,3.7-8.2,8.2-8.2h29.8v21.5c0,4.1,3.4,7.5,7.5,7.5s7.5-3.4,7.5-7.5v-21.5h48.9v21.5c0,4.1,3.4,7.5,7.5,7.5s7.5-3.4,7.5-7.5v-21.5h48.9v21.5c0,4.1,3.4,7.5,7.5,7.5s7.5-3.4,7.5-7.5v-21.5h29.8c4.5,0,8.2,3.7,8.2,8.2v262.1c0,4.5-3.7,8.2-8.2,8.2H154.8c-4.5,0-8.2-3.7-8.2-8.2V139.3z"/><path d="M181.9,219.4c1.5,1.5,3.4,2.2,5.3,2.2c1.9,0,3.8-0.7,5.3-2.2l26.5-26.5c2.9-2.9,2.9-7.7,0-10.6c-2.9-2.9-7.7-2.9-10.6,0l-21.2,21.2l-7.7-7.7c-2.9-2.9-7.7-2.9-10.6,0c-2.9,2.9-2.9,7.7,0,10.6L181.9,219.4z"/><path d="M238.1,208.3h99.7c4.1,0,7.5-3.4,7.5-7.5s-3.4-7.5-7.5-7.5h-99.7c-4.1,0-7.5,3.4-7.5,7.5S234,208.3,238.1,208.3z"/><path d="M181.9,289c1.5,1.5,3.4,2.2,5.3,2.2c1.9,0,3.8-0.7,5.3-2.2l26.5-26.5c2.9-2.9,2.9-7.7,0-10.6s-7.7-2.9-10.6,0L187.2,273l-7.7-7.7c-2.9-2.9-7.7-2.9-10.6,0c-2.9,2.9-2.9,7.7,0,10.6L181.9,289z"/><path d="M238.1,277.9h99.7c4.1,0,7.5-3.4,7.5-7.5s-3.4-7.5-7.5-7.5h-99.7c-4.1,0-7.5,3.4-7.5,7.5S234,277.9,238.1,277.9z"/><path d="M181.9,358.5c1.5,1.5,3.4,2.2,5.3,2.2c1.9,0,3.8-0.7,5.3-2.2l26.5-26.5c2.9-2.9,2.9-7.7,0-10.6s-7.7-2.9-10.6,0l-21.2,21.2l-7.7-7.7c-2.9-2.9-7.7-2.9-10.6,0c-2.9,2.9-2.9,7.7,0,10.6L181.9,358.5z"/><path d="M238.1,347.5h99.7c4.1,0,7.5-3.4,7.5-7.5s-3.4-7.5-7.5-7.5h-99.7c-4.1,0-7.5,3.4-7.5,7.5S234,347.5,238.1,347.5z"/></g></svg></span> <?php echo esc_html( $lesson_quiz->post_title ); ?>
												<?php if ( $quiz_attempt ) : ?>
													<span style="font-size: 13px; margin-left: 8px; color: <?php echo $quiz_attempt->passed ? '#00a32a' : '#d63638'; ?>;">
														(<?php echo esc_html( $quiz_attempt->score ); ?>% - <?php echo $quiz_attempt->passed ? 'Passed' : 'Failed'; ?>)
													</span>
												<?php endif; ?>
											</a>
											<?php if ( $quiz_attempt ) : ?>
												<span class="lk-lesson-status" style="color: <?php echo $quiz_attempt->passed ? '#00a32a' : '#d63638'; ?>;">
													<?php echo $quiz_attempt->passed ? '✓' : '✗'; ?>
												</span>
											<?php endif; ?>
										<?php else : ?>
											<span class="lk-lesson-title lk-lesson-locked" style="color: #999;">
												🔒 <?php echo esc_html( $lesson_quiz->post_title ); ?>
											</span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>

						</div>
					</div>
				<?php endforeach; ?>

				<?php
				// Check for course-level quizzes.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$course_quizzes = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT p.ID, p.post_title FROM {$wpdb->posts} p 
						INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
						WHERE p.post_type = 'lk_quiz' 
						AND pm.meta_key = '_lk_course_id' 
						AND pm.meta_value = %d 
						AND NOT EXISTS (
							SELECT 1 FROM {$wpdb->postmeta} pm2 
							WHERE pm2.post_id = p.ID 
							AND pm2.meta_key IN ('_lk_lesson_id', '_lk_module_id')
						)
						ORDER BY p.post_title ASC",
						$course_id
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				if ( ! empty( $course_quizzes ) ) :
					?>
					<div class="lk-module open" style="border: 2px solid #00a32a; background: #f0f6fc;">
						<div class="lk-module-header" style="background: #e7f5ec;">
							<div>
								<h3 class="lk-module-title" style="color: #00a32a;">🏆 Final Course Assessment</h3>
								<div class="lk-module-meta">
									<?php echo esc_html( count( $course_quizzes ) ); ?> <?php echo 1 === count( $course_quizzes ) ? 'quiz' : 'quizzes'; ?>
								</div>
							</div>
							<span class="lk-module-toggle">▼</span>
						</div>
						<div class="lk-lessons-list">
							<?php foreach ( $course_quizzes as $course_quiz ) : ?>
								<?php
								// Check if user has taken this quiz.
								$course_quiz_attempt = null;
								if ( $is_enrolled && $user_id ) {
									// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$course_quiz_attempt = $wpdb->get_row(
										$wpdb->prepare(
											"SELECT * FROM {$wpdb->prefix}learnkit_quiz_attempts 
											WHERE user_id = %d AND quiz_id = %d 
											ORDER BY score DESC, completed_at DESC 
											LIMIT 1",
											$user_id,
											$course_quiz->ID
										)
									);
								}
								// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								?>
								<div class="lk-lesson-item" style="background: #fff;">
									<?php if ( $is_enrolled ) : ?>
										<a href="<?php echo esc_url( get_permalink( $course_quiz->ID ) ); ?>" class="lk-lesson-title" style="color: #00a32a; font-weight: 600;">
											🎓 <?php echo esc_html( $course_quiz->post_title ); ?>
											<?php if ( $course_quiz_attempt ) : ?>
												<span style="font-size: 13px; margin-left: 8px; color: <?php echo $course_quiz_attempt->passed ? '#00a32a' : '#d63638'; ?>;">
													(<?php echo esc_html( $course_quiz_attempt->score ); ?>% - <?php echo $course_quiz_attempt->passed ? 'Passed' : 'Failed'; ?>)
												</span>
											<?php endif; ?>
										</a>
										<?php if ( $course_quiz_attempt ) : ?>
											<span class="lk-lesson-status" style="color: <?php echo $course_quiz_attempt->passed ? '#00a32a' : '#d63638'; ?>;">
												<?php echo $course_quiz_attempt->passed ? '✓' : '✗'; ?>
											</span>
										<?php endif; ?>
									<?php else : ?>
										<span class="lk-lesson-title lk-lesson-locked" style="color: #999;">
											🔒 <?php echo esc_html( $course_quiz->post_title ); ?>
										</span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<script>
	// Module accordion toggle
	document.querySelectorAll('.lk-module-header').forEach(header => {
		header.addEventListener('click', () => {
			header.closest('.lk-module').classList.toggle('open');
		});
	});

	// Enroll button handler
	const enrollButton = document.querySelector('.btn--lk-enroll');
	if (enrollButton) {
		enrollButton.addEventListener('click', async () => {
			const courseId = enrollButton.dataset.courseId;
			enrollButton.disabled = true;
			enrollButton.textContent = 'Enrolling...';

			try {
				const response = await fetch('<?php echo esc_url( rest_url( 'learnkit/v1/enrollments' ) ); ?>', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
					},
					body: JSON.stringify({
						user_id: <?php echo (int) $user_id; ?>,
						course_id: parseInt(courseId)
					})
				});

				if (response.ok) {
					location.reload();
				} else {
					throw new Error('Enrollment failed');
				}
			} catch (error) {
				enrollButton.disabled = false;
				enrollButton.textContent = 'Enroll Now';
				alert('Enrollment failed. Please try again.');
			}
		});
	}
</script>

<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
get_footer();
