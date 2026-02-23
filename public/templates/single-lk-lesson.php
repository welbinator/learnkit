<?php
/**
 * Template for displaying single lessons
 *
 * @link       https://jameswelbes.com
 * @since      0.2.13
 *
 * @package    LearnKit
 * @subpackage LearnKit/public/templates
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-scoped variables, not true PHP globals.

get_header();

// Get current lesson.
$lesson_id = get_the_ID();
$lesson    = get_post( $lesson_id );
$module_id = get_post_meta( $lesson_id, '_lk_module_id', true );
$course_id = $module_id ? get_post_meta( $module_id, '_lk_course_id', true ) : 0;

// Get module and course.
$module = $module_id ? get_post( $module_id ) : null;
$course = $course_id ? get_post( $course_id ) : null;

// Get all lessons in this module for navigation.
$lessons_query = new WP_Query(
	array(
		'post_type'      => 'lk_lesson',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'no_found_rows'  => true,
		'meta_key'       => '_lk_module_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'     => $module_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	)
);

$lessons        = $lessons_query->posts;
$current_index  = 0;
$prev_lesson_id = null;
$next_lesson_id = null;

// Find current lesson index and prev/next.
foreach ( $lessons as $index => $l ) {
	if ( $l->ID === $lesson_id ) {
		$current_index = $index;
		if ( $index > 0 ) {
			$prev_lesson_id = $lessons[ $index - 1 ]->ID;
		}
		if ( $index < count( $lessons ) - 1 ) {
			$next_lesson_id = $lessons[ $index + 1 ]->ID;
		}
		break;
	}
}

// Check if we're on last lesson of module - find next module's first lesson.
$next_module_first_lesson = null;
$next_module_quiz         = null;
if ( ! $next_lesson_id && $course_id && $module_id ) {
	// Get all modules in this course.
	$modules_query = new WP_Query(
		array(
			'post_type'      => 'lk_module',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'no_found_rows'  => true,
			'meta_key'       => '_lk_course_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		)
	);

	$modules            = $modules_query->posts;
	$current_module_idx = null;

	// Find current module index.
	foreach ( $modules as $idx => $mod ) {
		if ( (int) $mod->ID === (int) $module_id ) {
			$current_module_idx = $idx;
			break;
		}
	}

	// If there's a next module, get its first lesson — or its quiz if it has no lessons.
	if ( null !== $current_module_idx && isset( $modules[ $current_module_idx + 1 ] ) ) {
		$next_module    = $modules[ $current_module_idx + 1 ];
		$next_mod_query = new WP_Query(
			array(
				'post_type'      => 'lk_lesson',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'meta_key'       => '_lk_module_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $next_module->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		if ( $next_mod_query->have_posts() ) {
			$next_module_first_lesson = array(
				'id'          => $next_mod_query->posts[0]->ID,
				'title'       => $next_mod_query->posts[0]->post_title,
				'module_name' => $next_module->post_title,
			);
		} else {
			// No lessons in next module — check for a quiz instead.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$module_quiz = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE p.post_type = 'lk_quiz'
					AND p.post_status = 'publish'
					AND pm.meta_key = '_lk_module_id'
					AND pm.meta_value = %d
					LIMIT 1",
					$next_module->ID
				)
			);
			if ( $module_quiz ) {
				$next_module_quiz = array(
					'id'          => $module_quiz->ID,
					'title'       => $module_quiz->post_title,
					'module_name' => $next_module->post_title,
				);
			}
		}
	}
}

// Check enrollment — gate lesson access.
$user_id     = get_current_user_id();
$is_enrolled = false;
if ( $course_id && $user_id ) {
	$is_enrolled = learnkit_is_enrolled( $user_id, (int) $course_id );
}

if ( ! $is_enrolled ) {
	?>
	<div style="max-width: 680px; margin: 80px auto; padding: 0 20px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
		<div style="font-size: 48px; margin-bottom: 20px;">🔒</div>
		<h2 style="font-size: 28px; font-weight: 700; margin-bottom: 16px; color: #1a1a1a;">Enrollment Required</h2>
		<p style="font-size: 16px; color: #555; margin-bottom: 32px; line-height: 1.6;">
			<?php esc_html_e( 'You need to be enrolled in this course to access lessons.', 'learnkit' ); ?>
		</p>
		<?php if ( $course_id ) : ?>
			<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>"
				style="display: inline-block; background: #2271b1; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 600;">
				<?php esc_html_e( 'View Course', 'learnkit' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
	get_footer();
	return;
}

// Check drip availability.
$is_available = LearnKit_Drip::is_lesson_available( $lesson_id, $user_id );
if ( ! $is_available ) {
	$unlock_date = LearnKit_Drip::get_unlock_date( $lesson_id, $user_id );
	?>
	<div class="lk-lesson-locked-drip" style="max-width: 680px; margin: 80px auto; padding: 0 20px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
		<div style="font-size: 48px; margin-bottom: 20px;">🔓</div>
		<h2 style="font-size: 28px; font-weight: 700; margin-bottom: 16px; color: #1a1a1a;">
			<?php esc_html_e( 'This lesson is not yet available', 'learnkit' ); ?>
		</h2>
		<?php if ( $unlock_date ) : ?>
			<p style="font-size: 16px; color: #555; margin-bottom: 32px; line-height: 1.6;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: unlock date */
						__( 'Available on: %s', 'learnkit' ),
						$unlock_date->format( get_option( 'date_format' ) )
					)
				);
				?>
			</p>
		<?php else : ?>
			<p style="font-size: 16px; color: #555; margin-bottom: 32px; line-height: 1.6;">
				<?php esc_html_e( 'This lesson will become available soon.', 'learnkit' ); ?>
			</p>
		<?php endif; ?>
		<?php if ( $course_id ) : ?>
			<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>"
				style="display: inline-block; background: #2271b1; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 600;">
				<?php esc_html_e( 'Back to Course', 'learnkit' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
	get_footer();
	return;
}
?>

<div class="learnkit-lesson-viewer">
	<div class="learnkit-lesson-container">
		<!-- Breadcrumb -->
		<div class="learnkit-breadcrumb">
			<?php if ( $course ) : ?>
				<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>">
					<?php echo esc_html( $course->post_title ); ?>
				</a>
				<span class="separator">/</span>
			<?php endif; ?>
			<?php if ( $module ) : ?>
				<span class="module-name"><?php echo esc_html( $module->post_title ); ?></span>
				<span class="separator">/</span>
			<?php endif; ?>
			<span class="current-lesson"><?php echo esc_html( $lesson->post_title ); ?></span>
		</div>

		<!-- Lesson Header -->
		<div class="learnkit-lesson-header">
			<h1 class="learnkit-lesson-title"><?php echo esc_html( $lesson->post_title ); ?></h1>
			<div class="learnkit-lesson-meta">
				<span class="lesson-number">
					Lesson <?php echo esc_html( $current_index + 1 ); ?> of <?php echo esc_html( count( $lessons ) ); ?>
				</span>
			</div>
		</div>

		<!-- Lesson Content -->
		<div class="learnkit-lesson-content">
			<?php echo wp_kses_post( $lesson->post_content ); ?>
		</div>

		<!-- Progress & Navigation -->
		<div class="learnkit-lesson-footer">
			<div class="learnkit-lesson-progress">
				<?php if ( is_user_logged_in() ) : ?>
					<?php
					// Check if there's a required quiz that must be passed before completing.
					global $wpdb;

					$required_quiz = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API equivalent.
						$wpdb->prepare(
							"SELECT p.ID FROM {$wpdb->posts} p
							INNER JOIN {$wpdb->postmeta} pm_lesson ON p.ID = pm_lesson.post_id
								AND pm_lesson.meta_key = '_lk_lesson_id'
								AND pm_lesson.meta_value = %d
							INNER JOIN {$wpdb->postmeta} pm_required ON p.ID = pm_required.post_id
								AND pm_required.meta_key = '_lk_required_to_complete'
								AND pm_required.meta_value = '1'
							WHERE p.post_type = 'lk_quiz'
							AND p.post_status = 'publish'
							LIMIT 1",
							$lesson_id
						)
					);

					$quiz_gate_active = false;

					if ( $required_quiz ) {
						// Check whether the current user has a passing attempt for this quiz.
						$attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$passing_attempt = $wpdb->get_var(
							$wpdb->prepare(
								// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
								"SELECT COUNT(*) FROM $attempts_table WHERE user_id = %d AND quiz_id = %d AND passed = 1",
								get_current_user_id(),
								(int) $required_quiz->ID
							)
						);

						$quiz_gate_active = empty( $passing_attempt ) || 0 === (int) $passing_attempt;
					}

					if ( $quiz_gate_active ) :
						?>
						<button
							class="learnkit-mark-complete btn--primary"
							data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
							disabled
							aria-disabled="true"
							style="cursor: not-allowed; opacity: 0.5;"
						>
							<span class="checkmark">✓</span> Mark as Complete
						</button>
						<p class="learnkit-quiz-gate-notice" style="margin: 8px 0 0; font-size: 0.875rem; color: #d63638; font-weight: 600;">
							<?php esc_html_e( 'Complete the quiz to finish this lesson', 'learnkit' ); ?>
						</p>
					<?php else : ?>
						<button
							class="learnkit-mark-complete btn--primary"
							data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
						>
							<span class="checkmark">✓</span> Mark as Complete
						</button>
					<?php endif; ?>

					<?php
					// Check if there's any quiz for this lesson (for Take Quiz button).
					$quiz = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API equivalent.
						$wpdb->prepare(
							"SELECT ID FROM {$wpdb->posts} p
							INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
							WHERE p.post_type = 'lk_quiz'
							AND pm.meta_key = '_lk_lesson_id'
							AND pm.meta_value = %d
							LIMIT 1",
							$lesson_id
						)
					);

					if ( $quiz ) :
						// Check if user has already attempted this quiz.
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$quiz_attempt = $wpdb->get_row(
							$wpdb->prepare(
								"SELECT score, passed FROM {$wpdb->prefix}learnkit_quiz_attempts
								WHERE user_id = %d AND quiz_id = %d
								ORDER BY completed_at DESC LIMIT 1",
								$user_id,
								$quiz->ID
							)
						);
						?>
						<a href="<?php echo esc_url( get_permalink( $quiz->ID ) ); ?>" class="learnkit-quiz-button btn--primary">
							<span class="quiz-icon">📝</span>
							<?php if ( $quiz_attempt ) : ?>
								<?php esc_html_e( 'Retake Quiz', 'learnkit' ); ?>
								<span style="font-size: 12px; opacity: 0.85; margin-left: 6px;">
									(<?php echo esc_html( $quiz_attempt->score ); ?>% — <?php echo $quiz_attempt->passed ? esc_html__( 'Passed', 'learnkit' ) : esc_html__( 'Failed', 'learnkit' ); ?>)
								</span>
							<?php else : ?>
								<?php esc_html_e( 'Take Quiz', 'learnkit' ); ?>
							<?php endif; ?>
						</a>
					<?php endif; ?>
				<?php else : ?>
					<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="learnkit-login-prompt">
						Log in to track your progress
					</a>
				<?php endif; ?>
			</div>

			<div class="learnkit-lesson-navigation">
				<?php if ( $prev_lesson_id ) : ?>
					<a href="<?php echo esc_url( get_permalink( $prev_lesson_id ) ); ?>" class="learnkit-nav-button prev btn--primary">
						<span class="arrow">←</span> Previous Lesson
					</a>
				<?php else : ?>
					<span class="learnkit-nav-button prev disabled">
						<span class="arrow">←</span> Previous Lesson
					</span>
				<?php endif; ?>

				<?php if ( $next_lesson_id ) : ?>
					<a href="<?php echo esc_url( get_permalink( $next_lesson_id ) ); ?>" class="learnkit-nav-button next btn--primary">
						Next Lesson <span class="arrow">→</span>
					</a>
				<?php elseif ( $next_module_first_lesson ) : ?>
					<a href="<?php echo esc_url( get_permalink( $next_module_first_lesson['id'] ) ); ?>" class="learnkit-nav-button next next-module btn--primary">
						<div style="display: flex; flex-direction: column; align-items: flex-end;">
							<span style="font-size: 12px; opacity: 0.8;">Next Module:</span>
							<span><?php echo esc_html( $next_module_first_lesson['module_name'] ); ?> <span class="arrow">→</span></span>
						</div>
					</a>
				<?php elseif ( $next_module_quiz ) : ?>
					<a href="<?php echo esc_url( get_permalink( $next_module_quiz['id'] ) ); ?>" class="learnkit-nav-button next next-module btn--primary">
						<div style="display: flex; flex-direction: column; align-items: flex-end;">
							<span style="font-size: 12px; opacity: 0.8;">Next: <?php echo esc_html( $next_module_quiz['module_name'] ); ?></span>
							<span>📝 Take Module Quiz <span class="arrow">→</span></span>
						</div>
					</a>
				<?php else : ?>
					<span class="learnkit-nav-button next disabled">
						Next Lesson <span class="arrow">→</span>
					</span>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Sidebar: Module Lessons List -->
	<aside class="learnkit-lesson-sidebar" data-module-id="<?php echo esc_attr( $module_id ); ?>">
		<div class="learnkit-module-overview">
			<h3 class="module-title"><?php echo esc_html( $module->post_title ); ?></h3>
			<div class="module-progress">
				<div class="progress-bar">
					<div class="progress-fill" style="width: 0%;"></div>
				</div>
				<span class="progress-text">0% Complete</span>
			</div>
		</div>

		<ul class="learnkit-lessons-list">
			<?php foreach ( $lessons as $index => $l ) : ?>
				<li class="lesson-item <?php echo $l->ID === $lesson_id ? 'active' : ''; ?>">
					<a href="<?php echo esc_url( get_permalink( $l->ID ) ); ?>">
						<span class="lesson-number"><?php echo esc_html( $index + 1 ); ?>.</span>
						<span class="lesson-title"><?php echo esc_html( $l->post_title ); ?></span>
						<span class="lesson-status">
							<span class="status-icon incomplete">○</span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</aside>
</div>

<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
get_footer();
