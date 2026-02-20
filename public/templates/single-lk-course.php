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

get_header();

$course_id = get_the_ID();
$user_id   = get_current_user_id();

// Get course modules.
$modules = get_posts(
	array(
		'post_type'      => 'lk_module',
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'   => '_lk_course_id',
				'value' => $course_id,
			),
		),
		'orderby'        => 'meta_value_num',
		'meta_key'       => '_lk_order',
		'order'          => 'ASC',
	)
);

// Check enrollment status.
$is_enrolled = false;
$progress    = array(
	'completed'  => 0,
	'total'      => 0,
	'percentage' => 0,
);

if ( $user_id ) {
	global $wpdb;
	$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$is_enrolled = (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}learnkit_enrollments WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course_id
		)
	);

	if ( $is_enrolled ) {
		// Calculate progress.
		$all_lessons = array();
		foreach ( $modules as $module ) {
			$module_lessons = get_posts(
				array(
					'post_type'      => 'lk_lesson',
					'posts_per_page' => -1,
					'meta_query'     => array(
						array(
							'key'   => '_lk_module_id',
							'value' => $module->ID,
						),
					),
				)
			);
			$all_lessons    = array_merge( $all_lessons, wp_list_pluck( $module_lessons, 'ID' ) );
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
	}
}

// Check if self-enrollment is enabled.
$self_enrollment = get_post_meta( $course_id, '_lk_self_enrollment', true );
?>

<style>
	.lk-course-hero {
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		color: #fff;
		padding: 60px 20px;
		margin-bottom: 40px;
	}

	.lk-course-hero-content {
		max-width: 1200px;
		margin: 0 auto;
		display: grid;
		grid-template-columns: 2fr 1fr;
		gap: 40px;
		align-items: center;
	}

	.lk-course-title {
		font-size: 48px;
		font-weight: 700;
		margin: 0 0 20px 0;
		line-height: 1.2;
	}

	.lk-course-excerpt {
		font-size: 20px;
		line-height: 1.6;
		opacity: 0.95;
		margin-bottom: 30px;
	}

	.lk-course-featured-image {
		border-radius: 12px;
		overflow: hidden;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
	}

	.lk-course-featured-image img {
		width: 100%;
		height: auto;
		display: block;
	}

	.lk-course-content {
		max-width: 1200px;
		margin: 0 auto;
		padding: 0 20px 60px;
	}

	.lk-progress-section {
		background: #f0f0f1;
		padding: 30px;
		border-radius: 12px;
		margin-bottom: 40px;
	}

	.lk-progress-bar-container {
		background: #fff;
		height: 24px;
		border-radius: 12px;
		overflow: hidden;
		margin: 15px 0;
	}

	.lk-progress-bar {
		height: 100%;
		background: linear-gradient(90deg, #2271b1 0%, #135e96 100%);
		transition: width 0.3s ease;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #fff;
		font-size: 14px;
		font-weight: 600;
	}

	.lk-modules-section {
		margin-top: 40px;
	}

	.lk-section-title {
		font-size: 32px;
		font-weight: 700;
		margin-bottom: 30px;
		color: #1d2327;
	}

	.lk-module {
		background: #fff;
		border: 1px solid #dcdcde;
		border-radius: 12px;
		margin-bottom: 20px;
		overflow: hidden;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
	}

	.lk-module-header {
		padding: 20px 24px;
		background: #f6f7f7;
		cursor: pointer;
		display: flex;
		justify-content: space-between;
		align-items: center;
		transition: background 0.2s;
	}

	.lk-module-header:hover {
		background: #e8e8e9;
	}

	.lk-module-title {
		font-size: 20px;
		font-weight: 600;
		margin: 0;
		color: #1d2327;
	}

	.lk-module-meta {
		font-size: 14px;
		color: #757575;
		margin-top: 5px;
	}

	.lk-module-toggle {
		font-size: 24px;
		color: #757575;
		transition: transform 0.3s;
	}

	.lk-module.open .lk-module-toggle {
		transform: rotate(180deg);
	}

	.lk-lessons-list {
		max-height: 0;
		overflow: hidden;
		transition: max-height 0.3s ease;
	}

	.lk-module.open .lk-lessons-list {
		max-height: 1000px;
	}

	.lk-lesson-item {
		padding: 16px 24px;
		border-top: 1px solid #dcdcde;
		display: flex;
		justify-content: space-between;
		align-items: center;
		transition: background 0.2s;
	}

	.lk-lesson-item:hover {
		background: #f6f7f7;
	}

	.lk-lesson-title {
		font-size: 16px;
		color: #1d2327;
		text-decoration: none;
		display: flex;
		align-items: center;
		gap: 10px;
	}

	.lk-lesson-title:hover {
		color: #2271b1;
	}

	.lk-lesson-locked {
		color: #999;
		cursor: default;
		font-style: italic;
	}

	.lk-lesson-status {
		font-size: 20px;
	}

	:where(.lk-enroll-button, .lk-start-button) {
		background: var(--btn-background, #2271b1);
		color: var(--btn-text-color, #fff);
		padding-block: var(--btn-padding-block);
		padding-inline: var(--btn-padding-inline);
		inline-size: var(--btn-width, auto);
		min-inline-size: var(--btn-min-width);
		line-height: var(--btn-line-height);
		font-family: var(--btn-font-family);
		font-size: var(--btn-font-size, var(--text-m));
		font-weight: var(--btn-font-weight);
		font-style: var(--btn-font-style);
		text-transform: var(--btn-text-transform);
		letter-spacing: var(--btn-letter-spacing);
		border-width: var(--btn-border-width);
		border-style: var(--btn-border-style);
		border-radius: var(--btn-border-radius);
		border-color: var(--btn-border-color);
		transition: var(--btn-transition, var(--transition));
		justify-content: var(--btn-justify-content, center);
		align-items: var(--btn-align-items, center);
		text-align: var(--btn-text-align, center);
		display: var(--btn-display, inline-flex);
		cursor: pointer;
		text-decoration: none;
	}

	:where(.lk-enroll-button, .lk-start-button):hover {
		background: var(--btn-background-hover, #135e96);
		color: var(--btn-text-color, #fff);
	}

	.lk-login-prompt {
		background: #f0f0f1;
		padding: 20px;
		border-radius: 6px;
		text-align: center;
	}

	.lk-login-prompt a {
		color: #2271b1;
		font-weight: 600;
	}

	@media (max-width: 768px) {
		.lk-course-hero-content {
			grid-template-columns: 1fr;
		}

		.lk-course-title {
			font-size: 32px;
		}

		.lk-course-excerpt {
			font-size: 16px;
		}
	}
</style>

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
					<a href="<?php echo esc_url( get_permalink( $modules[0]->ID ?? 0 ) ); ?>" class="lk-start-button btn--primary">
						Continue Learning →
					</a>
				<?php elseif ( $user_id && $self_enrollment ) : ?>
					<button class="lk-enroll-button btn--primary" data-course-id="<?php echo esc_attr( $course_id ); ?>">
						Enroll Now
					</button>
				<?php elseif ( ! $user_id ) : ?>
					<div class="lk-login-prompt">
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Login to Enroll</a>
					</div>
				<?php endif; ?>
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

				foreach ( $modules as $index => $module ) :
					$lessons = get_posts(
						array(
							'post_type'      => 'lk_lesson',
							'posts_per_page' => -1,
							'meta_query'     => array(
								array(
									'key'   => '_lk_module_id',
									'value' => $module->ID,
								),
							),
							'orderby'        => 'meta_value_num',
							'meta_key'       => '_lk_order',
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
								// Check for lesson quiz.
								// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$lesson_quiz = $wpdb->get_row(
									$wpdb->prepare(
										"SELECT p.ID, p.post_title FROM {$wpdb->posts} p 
										INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
										WHERE p.post_type = 'lk_quiz' 
										AND pm.meta_key = '_lk_lesson_id' 
										AND pm.meta_value = %d 
										LIMIT 1",
										$lesson->ID
									)
								);
								if ( $lesson_quiz ) :
									// Check if user has taken this quiz.
									$quiz_attempt = null;
									if ( $is_enrolled && $user_id ) {
										// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$quiz_attempt = $wpdb->get_row(
											$wpdb->prepare(
												"SELECT * FROM {$wpdb->prefix}learnkit_quiz_attempts 
												WHERE user_id = %d AND quiz_id = %d 
												ORDER BY score DESC, completed_at DESC 
												LIMIT 1",
												$user_id,
												$lesson_quiz->ID
											)
										);
									}
									?>
									<div class="lk-lesson-item" style="padding-left: 48px; background: #f9f9f9;">
										<?php if ( $is_enrolled ) : ?>
											<a href="<?php echo esc_url( get_permalink( $lesson_quiz->ID ) ); ?>" class="lk-lesson-title" style="color: #2271b1;">
												📝 <?php echo esc_html( $lesson_quiz->post_title ); ?>
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

							<?php
							// Check for module quiz.
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$module_quiz = $wpdb->get_row(
								$wpdb->prepare(
									"SELECT p.ID, p.post_title FROM {$wpdb->posts} p 
									INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
									WHERE p.post_type = 'lk_quiz' 
									AND pm.meta_key = '_lk_module_id' 
									AND pm.meta_value = %d 
									AND NOT EXISTS (
										SELECT 1 FROM {$wpdb->postmeta} pm2 
										WHERE pm2.post_id = p.ID 
										AND pm2.meta_key = '_lk_lesson_id'
									)
									LIMIT 1",
									$module->ID
								)
							);
							if ( $module_quiz ) :
								// Check if user has taken this quiz.
								$module_quiz_attempt = null;
								if ( $is_enrolled && $user_id ) {
									// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$module_quiz_attempt = $wpdb->get_row(
										$wpdb->prepare(
											"SELECT * FROM {$wpdb->prefix}learnkit_quiz_attempts 
											WHERE user_id = %d AND quiz_id = %d 
											ORDER BY score DESC, completed_at DESC 
											LIMIT 1",
											$user_id,
											$module_quiz->ID
										)
									);
								}
								?>
								<div class="lk-lesson-item" style="background: #fff3cd; border-top: 2px solid #ffc107;">
									<?php if ( $is_enrolled ) : ?>
										<a href="<?php echo esc_url( get_permalink( $module_quiz->ID ) ); ?>" class="lk-lesson-title" style="color: #856404; font-weight: 600;">
											🎯 Module Quiz: <?php echo esc_html( $module_quiz->post_title ); ?>
											<?php if ( $module_quiz_attempt ) : ?>
												<span style="font-size: 13px; margin-left: 8px; color: <?php echo $module_quiz_attempt->passed ? '#00a32a' : '#d63638'; ?>;">
													(<?php echo esc_html( $module_quiz_attempt->score ); ?>% - <?php echo $module_quiz_attempt->passed ? 'Passed' : 'Failed'; ?>)
												</span>
											<?php endif; ?>
										</a>
										<?php if ( $module_quiz_attempt ) : ?>
											<span class="lk-lesson-status" style="color: <?php echo $module_quiz_attempt->passed ? '#00a32a' : '#d63638'; ?>;">
												<?php echo $module_quiz_attempt->passed ? '✓' : '✗'; ?>
											</span>
										<?php endif; ?>
									<?php else : ?>
										<span class="lk-lesson-title lk-lesson-locked" style="color: #999;">
											🔒 Module Quiz: <?php echo esc_html( $module_quiz->post_title ); ?>
										</span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>

				<?php
				// Check for course-level quizzes.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
									// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
	const enrollButton = document.querySelector('.lk-enroll-button');
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
get_footer();
