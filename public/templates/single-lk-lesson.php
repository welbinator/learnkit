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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-scoped variables, not true PHP globals.

if ( empty( $GLOBALS['learnkit_shortcode_context'] ) ) {
	get_header();
}

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

	// If there's a next module, get its first lesson.
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
		}
	}
}

// Check if we're on first lesson of module - find previous module's last lesson.
$prev_module_last_lesson = null;
if ( ! $prev_lesson_id && $course_id && $module_id ) {
	// Get all modules in this course (reuse or re-query).
	$prev_modules_query = new WP_Query(
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

	$all_modules        = $prev_modules_query->posts;
	$current_module_idx = null;

	foreach ( $all_modules as $idx => $mod ) {
		if ( (int) $mod->ID === (int) $module_id ) {
			$current_module_idx = $idx;
			break;
		}
	}

	// If there's a previous module, get its last lesson.
	if ( null !== $current_module_idx && $current_module_idx > 0 ) {
		$prev_module      = $all_modules[ $current_module_idx - 1 ];
		$prev_mod_lessons = new WP_Query(
			array(
				'post_type'      => 'lk_lesson',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'meta_key'       => '_lk_module_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $prev_module->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
			)
		);

		if ( $prev_mod_lessons->have_posts() ) {
			$prev_module_last_lesson = array(
				'id'          => $prev_mod_lessons->posts[0]->ID,
				'title'       => $prev_mod_lessons->posts[0]->post_title,
				'module_name' => $prev_module->post_title,
			);
		}
	}
}


$user_id     = get_current_user_id();
$is_enrolled = false;
if ( $user_id && current_user_can( 'manage_options' ) ) {
	$is_enrolled = true;
} elseif ( $course_id && $user_id ) {
	$is_enrolled = learnkit_is_enrolled( $user_id, (int) $course_id );
}

// Detect if this is the last lesson in the course and whether the user has earned a certificate.
$is_last_lesson     = ! $next_lesson_id && ! $next_module_first_lesson;
$show_certificate   = false;
$certificate_url    = '';

if ( $is_last_lesson && $is_enrolled && $user_id && $course_id ) {
	global $wpdb;

	// Get all module IDs for this course.
	$course_module_ids = get_posts(
		array(
			'post_type'      => 'lk_module',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => '_lk_course_id',
					'value' => $course_id,
				),
			),
		)
	);

	// Get all lesson IDs in those modules.
	$all_course_lesson_ids = array();
	if ( ! empty( $course_module_ids ) ) {
		$all_course_lesson_ids = get_posts(
			array(
				'post_type'      => 'lk_lesson',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => '_lk_module_id',
						'value'   => $course_module_ids,
						'compare' => 'IN',
					),
				),
			)
		);
	}

	// Get completed lesson IDs for this user (presence in table = completed).
	$completed_lesson_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT lesson_id FROM {$wpdb->prefix}learnkit_progress WHERE user_id = %d",
			$user_id
		)
	);

	$all_lessons_done = ! empty( $all_course_lesson_ids ) &&
		count( array_diff( array_map( 'strval', $all_course_lesson_ids ), array_map( 'strval', $completed_lesson_ids ) ) ) === 0;

	// Get all quiz IDs for those modules.
	$all_quiz_ids = array();
	if ( ! empty( $course_module_ids ) ) {
		$all_quiz_ids = get_posts(
			array(
				'post_type'      => 'lk_quiz',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => '_lk_module_id',
						'value'   => $course_module_ids,
						'compare' => 'IN',
					),
				),
			)
		);
	}

	$all_quizzes_passed = true;
	if ( ! empty( $all_quiz_ids ) ) {
		$passed_quiz_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT quiz_id FROM {$wpdb->prefix}learnkit_quiz_attempts WHERE user_id = %d AND passed = 1",
				$user_id
			)
		);
		$all_quizzes_passed = count( array_diff( array_map( 'strval', $all_quiz_ids ), array_map( 'strval', $passed_quiz_ids ) ) ) === 0;
	}

	if ( $all_lessons_done && $all_quizzes_passed ) {
		$show_certificate = true;
		$certificate_url  = add_query_arg(
			array(
				'download_certificate' => $course_id,
				'_wpnonce'             => wp_create_nonce( 'learnkit_certificate_' . $course_id ),
			),
			home_url()
		);
	}
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
			<a href="<?php echo esc_url( learnkit_course_url( $course_id ) ); ?>"
				style="display: inline-block; background: #2271b1; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 600;">
				<?php esc_html_e( 'View Course', 'learnkit' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
	if ( empty( $GLOBALS['learnkit_shortcode_context'] ) ) {
		get_footer();
	}
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
			<a href="<?php echo esc_url( learnkit_course_url( $course_id ) ); ?>"
				style="display: inline-block; background: #2271b1; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 600;">
				<?php esc_html_e( 'Back to Course', 'learnkit' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
	if ( empty( $GLOBALS['learnkit_shortcode_context'] ) ) {
		get_footer();
	}
	return;
}
?>

<div class="learnkit-lesson-viewer">
	<div class="learnkit-lesson-container">
		<!-- Breadcrumb -->
		<div class="learnkit-breadcrumb">
			<?php if ( $course ) : ?>
				<a href="<?php echo esc_url( learnkit_course_url( $course_id ) ); ?>">
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
								'SELECT COUNT(*) FROM %i WHERE user_id = %d AND quiz_id = %d AND passed = 1',
								$attempts_table,
								get_current_user_id(),
								(int) $required_quiz->ID
							)
						);

						$quiz_gate_active = empty( $passing_attempt ) || 0 === (int) $passing_attempt;
					}

					if ( $quiz_gate_active ) :
						?>
						<button
							class="<?php echo esc_attr( learnkit_button_classes( 'mark_complete_button', 'btn--lk-mark-complete' ) ); ?>"
							data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
							disabled
							aria-disabled="true"
							style="cursor: not-allowed; opacity: 0.5;"
						>
							<span class="lk-icon"><svg aria-hidden="true" focusable="false" width="1em" height="1em" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5.341,12.247a1,1,0,0,0,1.317,1.505l4-3.5a1,1,0,0,0,.028-1.48l-9-8.5A1,1,0,0,0,.313,1.727l8.2,7.745Z" transform="translate(19 6.5) rotate(90)" fill="white"/></svg></span> Mark as Complete
						</button>
					<?php else : ?>
						<button
							class="<?php echo esc_attr( learnkit_button_classes( 'mark_complete_button', 'btn--lk-mark-complete' ) ); ?>"
							data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>"
						>
							<span class="lk-icon"><svg aria-hidden="true" focusable="false" width="1em" height="1em" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5.341,12.247a1,1,0,0,0,1.317,1.505l4-3.5a1,1,0,0,0,.028-1.48l-9-8.5A1,1,0,0,0,.313,1.727l8.2,7.745Z" transform="translate(19 6.5) rotate(90)" fill="white"/></svg></span> Mark as Complete
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
						<a href="<?php echo esc_url( learnkit_quiz_url( $quiz->ID ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'take_quiz_button', 'btn--lk-take-quiz' ) ); ?>">
							
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
				<?php
				// Build previous button tooltip.
				if ( $prev_lesson_id ) {
					$prev_tooltip = get_the_title( $prev_lesson_id );
				} elseif ( $prev_module_last_lesson ) {
					$prev_tooltip = $prev_module_last_lesson['title'];
				} else {
					$prev_tooltip = '';
				}

				if ( $prev_lesson_id || $prev_module_last_lesson ) :
					$prev_href = $prev_lesson_id ? learnkit_lesson_url( $prev_lesson_id ) : learnkit_lesson_url( $prev_module_last_lesson["id"] );
				?>
					<a
						href="<?php echo esc_url( $prev_href ); ?>"
						class="<?php echo esc_attr( learnkit_button_classes( 'prev_lesson_button', 'btn--lk-nav prev' ) ); ?>"
						<?php if ( $prev_tooltip ) : ?>title="<?php echo esc_attr( $prev_tooltip ); ?>" data-tooltip="<?php echo esc_attr( $prev_tooltip ); ?>"<?php endif; ?>
					>
						<span class="arrow">←</span> Previous Lesson
					</a>
				<?php else : ?>
					<span class="<?php echo esc_attr( learnkit_button_classes( 'prev_lesson_button_disabled', 'btn--lk-nav prev disabled' ) ); ?>">
						<span class="arrow">←</span> Previous Lesson
					</span>
				<?php endif; ?>

				<?php
				// Build next button tooltip.
				if ( $quiz_gate_active ) {
					$next_tooltip = esc_attr__( 'Complete the quiz to proceed to the next lesson', 'learnkit' );
				} elseif ( $next_lesson_id ) {
					$next_tooltip = get_the_title( $next_lesson_id );
				} elseif ( $next_module_first_lesson ) {
					$next_tooltip = $next_module_first_lesson['title'];
				} else {
					$next_tooltip = '';
				}

				if ( $next_lesson_id || $next_module_first_lesson ) :
					$next_href = $next_lesson_id ? learnkit_lesson_url( $next_lesson_id ) : learnkit_lesson_url( $next_module_first_lesson["id"] );
					if ( $quiz_gate_active ) : ?>
						<span
							class="<?php echo esc_attr( learnkit_button_classes( 'next_lesson_button_disabled', 'btn--lk-nav next disabled' ) ); ?>"
							aria-disabled="true"
							title="<?php echo esc_attr( $next_tooltip ); ?>"
							data-tooltip="<?php echo esc_attr( $next_tooltip ); ?>"
						>
							Next Lesson <span class="arrow">→</span>
						</span>
					<?php else : ?>
						<a
							href="<?php echo esc_url( $next_href ); ?>"
							class="<?php echo esc_attr( learnkit_button_classes( 'next_lesson_button', 'btn--lk-nav next' ) ); ?>"
							<?php if ( $next_tooltip ) : ?>title="<?php echo esc_attr( $next_tooltip ); ?>" data-tooltip="<?php echo esc_attr( $next_tooltip ); ?>"<?php endif; ?>
						>
							Next Lesson <span class="arrow">→</span>
						</a>
					<?php endif; ?>
				<?php else : ?>
					<?php if ( $show_certificate ) : ?>
						<a href="<?php echo esc_url( $certificate_url ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'certificate_button', 'btn--lk-certificate' ) ); ?>">
							🎓 Download Certificate
						</a>
					<?php else : ?>
						<span class="<?php echo esc_attr( learnkit_button_classes( 'next_lesson_button_disabled', 'btn--lk-nav next disabled' ) ); ?>">
							Next Lesson <span class="arrow">→</span>
						</span>
					<?php endif; ?>
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
			<?php foreach ( $lessons as $index => $l ) :
				$is_current   = $l->ID === $lesson_id;
				$is_after     = $index > array_search( $lesson_id, array_column( $lessons, 'ID' ), true );
				$is_gated     = $quiz_gate_active && $is_after;
				$sidebar_tip  = esc_attr__( 'Complete the quiz to proceed to the next lesson', 'learnkit' );
				?>
				<li class="lesson-item <?php echo $is_current ? 'active' : ''; ?> <?php echo $is_gated ? 'gated' : ''; ?>">
					<?php if ( $is_gated ) : ?>
						<span
							class="lk-sidebar-lesson-gated"
							aria-disabled="true"
							title="<?php echo $sidebar_tip; ?>"
							data-tooltip="<?php echo $sidebar_tip; ?>"
						>
							<span class="lesson-number"><?php echo esc_html( $index + 1 ); ?>.</span>
							<span class="lesson-title"><?php echo esc_html( $l->post_title ); ?></span>
							<span class="lesson-status">
								<span class="status-icon incomplete">○</span>
							</span>
						</span>
					<?php else : ?>
						<a href="<?php echo esc_url( learnkit_lesson_url( $l->ID ) ); ?>">
							<span class="lesson-number"><?php echo esc_html( $index + 1 ); ?>.</span>
							<span class="lesson-title"><?php echo esc_html( $l->post_title ); ?></span>
							<span class="lesson-status">
								<span class="status-icon incomplete">○</span>
							</span>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</aside>
</div>

<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( empty( $GLOBALS['learnkit_shortcode_context'] ) ) {
	get_footer();
}
