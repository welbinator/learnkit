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
		'meta_key'       => '_lk_module_id',
		'meta_value'     => $module_id,
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
if ( ! $next_lesson_id && $course_id && $module_id ) {
	// Get all modules in this course.
	$modules_query = new WP_Query(
		array(
			'post_type'      => 'lk_module',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_key'       => '_lk_course_id',
			'meta_value'     => $course_id,
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

	// If there's a next module, get its first lesson.
	if ( null !== $current_module_idx && isset( $modules[ $current_module_idx + 1 ] ) ) {
		$next_module    = $modules[ $current_module_idx + 1 ];
		$next_mod_query = new WP_Query(
			array(
				'post_type'      => 'lk_lesson',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_key'       => '_lk_module_id',
				'meta_value'     => $next_module->ID,
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
		}
	}
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

					$required_quiz = $wpdb->get_row(
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
							class="learnkit-mark-complete"
							data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
							disabled
							aria-disabled="true"
							style="background: #ccc; cursor: not-allowed; opacity: 0.7;"
						>
							<span class="checkmark">✓</span> Mark as Complete
						</button>
						<p class="learnkit-quiz-gate-notice" style="margin: 8px 0 0; font-size: 0.875rem; color: #d63638; font-weight: 600;">
							<?php esc_html_e( 'Complete the quiz to finish this lesson', 'learnkit' ); ?>
						</p>
					<?php else : ?>
						<button
							class="learnkit-mark-complete"
							data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
						>
							<span class="checkmark">✓</span> Mark as Complete
						</button>
					<?php endif; ?>

					<?php
					// Check if there's any quiz for this lesson (for Take Quiz button).
					$quiz = $wpdb->get_row(
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
						?>
						<a href="<?php echo esc_url( get_permalink( $quiz->ID ) ); ?>" class="learnkit-quiz-button">
							<span class="quiz-icon">📝</span> Take Quiz
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
					<a href="<?php echo esc_url( get_permalink( $prev_lesson_id ) ); ?>" class="learnkit-nav-button prev">
						<span class="arrow">←</span> Previous Lesson
					</a>
				<?php else : ?>
					<span class="learnkit-nav-button prev disabled">
						<span class="arrow">←</span> Previous Lesson
					</span>
				<?php endif; ?>

				<?php if ( $next_lesson_id ) : ?>
					<a href="<?php echo esc_url( get_permalink( $next_lesson_id ) ); ?>" class="learnkit-nav-button next">
						Next Lesson <span class="arrow">→</span>
					</a>
				<?php elseif ( $next_module_first_lesson ) : ?>
					<a href="<?php echo esc_url( get_permalink( $next_module_first_lesson['id'] ) ); ?>" class="learnkit-nav-button next next-module">
						<div style="display: flex; flex-direction: column; align-items: flex-end;">
							<span style="font-size: 12px; opacity: 0.8;">Next Module:</span>
							<span><?php echo esc_html( $next_module_first_lesson['module_name'] ); ?> <span class="arrow">→</span></span>
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

<style>
.learnkit-lesson-viewer {
	display: grid;
	grid-template-columns: 1fr 320px;
	gap: 2rem;
	max-width: 1400px;
	margin: 2rem auto;
	padding: 0 2rem;
}

.learnkit-lesson-container {
	background: #ffffff;
	border-radius: 8px;
	padding: 2rem;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.learnkit-breadcrumb {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	margin-bottom: 1.5rem;
	font-size: 0.875rem;
	color: #757575;
}

.learnkit-breadcrumb a {
	color: #2271b1;
	text-decoration: none;
}

.learnkit-breadcrumb a:hover {
	text-decoration: underline;
}

.learnkit-breadcrumb .separator {
	color: #dcdcde;
}

.learnkit-lesson-header {
	margin-bottom: 2rem;
	border-bottom: 2px solid #f0f0f1;
	padding-bottom: 1rem;
}

.learnkit-lesson-title {
	font-size: 2rem;
	font-weight: 700;
	color: #1e1e1e;
	margin: 0 0 0.5rem 0;
}

.learnkit-lesson-meta {
	color: #757575;
	font-size: 0.875rem;
}

.learnkit-lesson-content {
	font-size: 1rem;
	line-height: 1.7;
	color: #1e1e1e;
	margin-bottom: 3rem;
}

.learnkit-lesson-content p {
	margin-bottom: 1rem;
}

.learnkit-lesson-footer {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding-top: 2rem;
	border-top: 2px solid #f0f0f1;
}

.learnkit-mark-complete {
	background: #00a32a;
	color: #ffffff;
	border: none;
	padding: 0.75rem 1.5rem;
	border-radius: 4px;
	font-size: 1rem;
	font-weight: 600;
	cursor: pointer;
	display: flex;
	align-items: center;
	gap: 0.5rem;
	transition: background 0.2s;
}

.learnkit-mark-complete:hover {
	background: #008a20;
}

.learnkit-quiz-button {
	background: #2271b1;
	color: #ffffff;
	border: none;
	padding: 0.75rem 1.5rem;
	border-radius: 4px;
	font-size: 1rem;
	font-weight: 600;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
	transition: background 0.2s;
	text-decoration: none;
	margin-left: 1rem;
}

.learnkit-quiz-button:hover {
	background: #135e96;
}

.quiz-icon {
	font-size: 1.25rem;
}

.learnkit-login-prompt {
	color: #2271b1;
	text-decoration: none;
	font-size: 0.875rem;
}

.learnkit-login-prompt:hover {
	text-decoration: underline;
}

.learnkit-lesson-navigation {
	display: flex;
	gap: 1rem;
}

.learnkit-nav-button {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	padding: 0.75rem 1.5rem;
	background: #2271b1;
	color: #ffffff;
	text-decoration: none;
	border-radius: 4px;
	font-weight: 600;
	transition: background 0.2s;
}

.learnkit-nav-button:hover {
	background: #1d5d8a;
}

.learnkit-nav-button.next-module {
	background: #00a32a;
}

.learnkit-nav-button.next-module:hover {
	background: #008a20;
}

.learnkit-nav-button.disabled {
	background: #dcdcde;
	color: #757575;
	cursor: not-allowed;
}

.learnkit-lesson-sidebar {
	background: #ffffff;
	border-radius: 8px;
	padding: 1.5rem;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	height: fit-content;
	position: sticky;
	top: 2rem;
}

.learnkit-module-overview {
	margin-bottom: 1.5rem;
	padding-bottom: 1rem;
	border-bottom: 2px solid #f0f0f1;
}

.module-title {
	font-size: 1.125rem;
	font-weight: 700;
	color: #1e1e1e;
	margin: 0 0 1rem 0;
}

.module-progress {
	margin-top: 0.75rem;
}

.progress-bar {
	height: 8px;
	background: #f0f0f1;
	border-radius: 4px;
	overflow: hidden;
	margin-bottom: 0.5rem;
}

.progress-fill {
	height: 100%;
	background: #00a32a;
	transition: width 0.3s;
}

.progress-text {
	font-size: 0.875rem;
	color: #757575;
}

.learnkit-lessons-list {
	list-style: none;
	padding: 0;
	margin: 0;
}

.lesson-item {
	margin-bottom: 0.5rem;
}

.lesson-item a {
	display: flex;
	align-items: center;
	gap: 0.75rem;
	padding: 0.75rem;
	border-radius: 4px;
	text-decoration: none;
	color: #1e1e1e;
	transition: background 0.2s;
}

.lesson-item a:hover {
	background: #f0f0f1;
}

.lesson-item.active a {
	background: #e7f5fe;
	color: #2271b1;
	font-weight: 600;
}

.lesson-number {
	font-weight: 700;
	color: #757575;
	min-width: 2rem;
}

.lesson-item.active .lesson-number {
	color: #2271b1;
}

.lesson-title {
	flex: 1;
}

.status-icon {
	font-size: 1.25rem;
}

.status-icon.incomplete {
	color: #dcdcde;
}

.status-icon.complete {
	color: #00a32a;
}

@media (max-width: 1024px) {
	.learnkit-lesson-viewer {
		grid-template-columns: 1fr;
	}

	.learnkit-lesson-sidebar {
		position: static;
	}
}

@media (max-width: 640px) {
	.learnkit-lesson-footer {
		flex-direction: column;
		gap: 1rem;
	}

	.learnkit-lesson-navigation {
		width: 100%;
		flex-direction: column;
	}

	.learnkit-nav-button {
		width: 100%;
		justify-content: center;
	}
}
</style>

<?php
get_footer();
