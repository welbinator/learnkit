<?php
/**
 * Template for displaying a single quiz (student-facing).
 *
 * @package LearnKit
 * @since   0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// Check if user is enrolled in the course.
$quiz_id   = get_the_ID();
$lesson_id = get_post_meta( $quiz_id, '_lk_lesson_id', true );
$module_id = get_post_meta( $quiz_id, '_lk_module_id', true );
$course_id = get_post_meta( $quiz_id, '_lk_course_id', true );

// Get the course ID from lesson/module if not directly attached.
if ( ! $course_id && $lesson_id ) {
	$course_id = get_post_meta( $lesson_id, '_lk_course_id', true );
}
if ( ! $course_id && $module_id ) {
	$course_id = get_post_meta( $module_id, '_lk_course_id', true );
}

$user_id     = get_current_user_id();
$is_enrolled = false;

if ( $course_id && $user_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'learnkit_enrollments';
	$enrollment = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
			"SELECT * FROM $table_name WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course_id
		)
	);
	$is_enrolled = ! empty( $enrollment );
}

// Get quiz settings.
$passing_score        = (int) get_post_meta( $quiz_id, '_lk_passing_score', true );
$time_limit           = (int) get_post_meta( $quiz_id, '_lk_time_limit', true );
$attempts_allowed     = (int) get_post_meta( $quiz_id, '_lk_attempts_allowed', true );
$required_to_complete = get_post_meta( $quiz_id, '_lk_required_to_complete', true );
$questions            = get_post_meta( $quiz_id, '_lk_questions', true );
$questions            = $questions ? json_decode( $questions, true ) : array();

// Get user's previous attempts.
$attempts = array();
if ( $user_id ) {
	global $wpdb;
	$attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';
	$attempts       = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
			"SELECT * FROM $attempts_table WHERE user_id = %d AND quiz_id = %d ORDER BY completed_at DESC",
			$user_id,
			$quiz_id
		)
	);
}

$attempts_used = count( $attempts );
$best_attempt  = ! empty( $attempts ) ? $attempts[0] : null;
$has_passed    = $best_attempt && $best_attempt->passed;

?>

<style>
	.learnkit-quiz-container {
		max-width: 800px;
		margin: 40px auto;
		padding: 0 20px;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
	}
	.quiz-header {
		text-align: center;
		margin-bottom: 40px;
		padding-bottom: 20px;
		border-bottom: 2px solid #e0e0e0;
	}
	.quiz-title {
		font-size: 32px;
		font-weight: 700;
		color: #1a1a1a;
		margin-bottom: 10px;
	}
	.quiz-meta {
		display: flex;
		justify-content: center;
		gap: 30px;
		flex-wrap: wrap;
		color: #666;
		font-size: 14px;
	}
	.quiz-meta-item {
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.quiz-timer {
		position: sticky;
		top: 20px;
		background: #fff3cd;
		border: 2px solid #ffc107;
		border-radius: 8px;
		padding: 15px;
		text-align: center;
		margin-bottom: 20px;
		font-size: 18px;
		font-weight: 600;
		color: #856404;
	}
	.quiz-question {
		background: #fff;
		border: 1px solid #e0e0e0;
		border-radius: 12px;
		padding: 30px;
		margin-bottom: 20px;
		box-shadow: 0 2px 4px rgba(0,0,0,0.05);
	}
	.question-header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		margin-bottom: 20px;
	}
	.question-text {
		font-size: 18px;
		font-weight: 600;
		color: #1a1a1a;
		flex: 1;
	}
	.question-points {
		background: #2271b1;
		color: #fff;
		padding: 4px 12px;
		border-radius: 12px;
		font-size: 14px;
		white-space: nowrap;
		margin-left: 15px;
	}
	.quiz-options {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}
	.quiz-option {
		display: flex;
		align-items: center;
		padding: 15px;
		border: 2px solid #e0e0e0;
		border-radius: 8px;
		cursor: pointer;
		transition: all 0.2s;
	}
	.quiz-option:hover {
		border-color: #2271b1;
		background: #f0f6fc;
	}
	.quiz-option input[type="radio"] {
		margin-right: 12px;
		width: 20px;
		height: 20px;
		cursor: pointer;
	}
	.quiz-option label {
		cursor: pointer;
		flex: 1;
		font-size: 16px;
	}
	.quiz-submit {
		text-align: center;
		margin-top: 40px;
	}
	.submit-button:disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}
	:where(.lk-submit-button) {
		background: var(--btn-background, #2271b1);
		color: var(--btn-text-color, #fff);
		padding-block: var(--btn-padding-block, 0.75em);
		padding-inline: var(--btn-padding-inline, 1.5em);
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
		border-radius: var(--btn-border-radius, 6px);
		border-color: var(--btn-border-color);
		transition: var(--btn-transition, var(--transition));
		justify-content: var(--btn-justify-content, center);
		align-items: var(--btn-align-items, center);
		text-align: var(--btn-text-align, center);
		display: var(--btn-display, inline-flex);
		cursor: pointer;
		text-decoration: none;
	}
	:where(.lk-submit-button):where(:hover) {
		background: var(--btn-background-hover, #135e96);
		color: var(--btn-text-color, #fff);
	}
	.quiz-attempts-info {
		background: #e7f3ff;
		border-left: 4px solid #2271b1;
		padding: 15px;
		margin-bottom: 20px;
		border-radius: 4px;
	}
	.not-enrolled {
		background: #fff3cd;
		border-left: 4px solid #ffc107;
		padding: 20px;
		margin: 40px 0;
		border-radius: 4px;
	}
	.passed-banner {
		background: #d4edda;
		border-left: 4px solid #28a745;
		padding: 20px;
		margin-bottom: 20px;
		border-radius: 4px;
		text-align: center;
	}
	.passed-banner h3 {
		color: #155724;
		margin: 0 0 10px;
	}
	@media (max-width: 768px) {
		.quiz-title {
			font-size: 24px;
		}
		.quiz-question {
			padding: 20px;
		}
		.question-text {
			font-size: 16px;
		}
		.quiz-meta {
			gap: 15px;
		}
	}
</style>

<div class="learnkit-quiz-container">
	<?php
	// Show results if quiz was just submitted.
	if ( isset( $_GET['quiz_result'] ) && 'submitted' === $_GET['quiz_result'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result_score  = isset( $_GET['score'] ) ? (int) $_GET['score'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result_passed = isset( $_GET['passed'] ) && '1' === $_GET['passed']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Load the most recent attempt for answer review (Option C).
		$latest_attempt   = null;
		$attempt_answers  = array();
		$question_results = array();

		if ( $user_id ) {
			global $wpdb;
			$attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$latest_attempt = $wpdb->get_row(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
					"SELECT * FROM $attempts_table WHERE user_id = %d AND quiz_id = %d ORDER BY completed_at DESC LIMIT 1",
					$user_id,
					$quiz_id
				)
			);
		}

		if ( $latest_attempt && ! empty( $latest_attempt->answers ) ) {
			$attempt_answers = json_decode( $latest_attempt->answers, true );
			if ( ! is_array( $attempt_answers ) ) {
				$attempt_answers = array();
			}
		}

		// Cross-reference answers with questions for the breakdown.
		// The PHP form handler keys answers by question 'id'; the REST endpoint keys by numeric index.
		// Support both formats.
		if ( ! empty( $questions ) && ! empty( $attempt_answers ) ) {
			// Determine which keying scheme is in use.
			// If the first question's 'id' value is a key in attempt_answers, use id-based lookup.
			$first_question = reset( $questions );
			$use_id_key     = isset( $first_question['id'] ) && array_key_exists( (string) $first_question['id'], $attempt_answers );

			foreach ( $questions as $index => $question ) {
				if ( $use_id_key ) {
					$lookup_key = (string) $question['id'];
				} else {
					$lookup_key = (string) $index;
				}

				$user_answer_idx = isset( $attempt_answers[ $lookup_key ] ) ? (int) $attempt_answers[ $lookup_key ] : -1;
				$correct_idx     = isset( $question['correctAnswer'] ) ? (int) $question['correctAnswer'] : (int) $question['correct'];
				$is_correct      = $user_answer_idx === $correct_idx && $user_answer_idx >= 0;

				$question_results[] = array(
					'question'        => $question['question'],
					'options'         => $question['options'],
					'user_answer'     => $user_answer_idx >= 0 ? $question['options'][ $user_answer_idx ] : '',
					'user_answer_idx' => $user_answer_idx,
					'correct_answer'  => $question['options'][ $correct_idx ],
					'correct_idx'     => $correct_idx,
					'is_correct'      => $is_correct,
					'points'          => (int) $question['points'],
				);
			}
		}
		?>
		<div class="<?php echo esc_attr( $result_passed ? 'passed-banner' : 'not-enrolled' ); ?>">
			<h2>
				<?php
				if ( $result_passed ) {
					echo '🎉 ' . esc_html__( 'Congratulations! You Passed!', 'learnkit' );
				} else {
					echo '📝 ' . esc_html__( 'Quiz Complete', 'learnkit' );
				}
				?>
			</h2>
			<p style="font-size: 24px; font-weight: bold;">
				<?php
				/* translators: %d: User's quiz score percentage */
				printf( esc_html__( 'Your Score: %d%%', 'learnkit' ), (int) $result_score );
				?>
			</p>
			<p>
				<?php
				if ( true === $result_passed ) {
					/* translators: %d: Minimum passing score percentage */
					printf( esc_html__( 'You need %d%% to pass. Great job!', 'learnkit' ), (int) $passing_score );
				} else {
					/* translators: %d: Minimum passing score percentage */
					printf( esc_html__( 'You need %d%% to pass. Try again!', 'learnkit' ), (int) $passing_score );
				}
				?>
			</p>
			<div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
				<?php if ( false === $result_passed && ( 0 === $attempts_allowed || $attempts_used < $attempts_allowed ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $quiz_id ) ); ?>" class="submit-button lk-submit-button btn--primary">
						<?php esc_html_e( 'Retake Quiz', 'learnkit' ); ?>
					</a>
				<?php elseif ( true === $result_passed && ( 0 === $attempts_allowed || $attempts_used < $attempts_allowed ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $quiz_id ) ); ?>" class="submit-button lk-submit-button btn--primary">
						<?php esc_html_e( 'Retake Quiz', 'learnkit' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $lesson_id && ! empty( $lesson_id ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="submit-button lk-submit-button btn--secondary">
						<?php esc_html_e( 'Back to Lesson', 'learnkit' ); ?>
					</a>
				<?php elseif ( ( $module_id && ! empty( $module_id ) ) || ( $course_id && ! empty( $course_id ) ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $course_id ? $course_id : $module_id ) ); ?>" class="submit-button lk-submit-button btn--secondary">
						<?php esc_html_e( 'Back to Course', 'learnkit' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! empty( $question_results ) ) : ?>
			<div class="lk-answer-review">
				<h3 class="lk-answer-review__title"><?php esc_html_e( 'Answer Review', 'learnkit' ); ?></h3>
				<?php foreach ( $question_results as $qnum => $qr ) : ?>
					<div class="lk-answer-review__question <?php echo esc_attr( $qr['is_correct'] ? 'lk-answer-review__question--correct' : 'lk-answer-review__question--incorrect' ); ?>">
						<div class="lk-answer-review__question-header">
							<span class="lk-answer-review__question-number">
								<?php
								/* translators: %d: Question number */
								printf( esc_html__( 'Question %d', 'learnkit' ), (int) $qnum + 1 );
								?>
							</span>
							<span class="lk-answer-review__result-badge <?php echo esc_attr( $qr['is_correct'] ? 'lk-answer-review__result-badge--correct' : 'lk-answer-review__result-badge--incorrect' ); ?>">
								<?php
								if ( $qr['is_correct'] ) {
									echo '✓ ' . esc_html__( 'Correct', 'learnkit' );
								} else {
									echo '✗ ' . esc_html__( 'Incorrect', 'learnkit' );
								}
								?>
							</span>
							<span class="lk-answer-review__points">
								<?php
								if ( $qr['is_correct'] ) {
									/* translators: 1: Points earned, 2: Total points for question */
									printf( esc_html__( '%1$d / %2$d pts', 'learnkit' ), (int) $qr['points'], (int) $qr['points'] );
								} else {
									/* translators: 1: Points earned (0), 2: Total points for question */
									printf( esc_html__( '%1$d / %2$d pts', 'learnkit' ), 0, (int) $qr['points'] );
								}
								?>
							</span>
						</div>
						<p class="lk-answer-review__question-text"><?php echo esc_html( $qr['question'] ); ?></p>
						<ul class="lk-answer-review__options">
							<?php foreach ( $qr['options'] as $opt_idx => $opt_text ) : ?>
								<?php
								$is_user_choice    = $opt_idx === $qr['user_answer_idx'];
								$is_correct_choice = $opt_idx === $qr['correct_idx'];
								$option_class      = 'lk-answer-review__option';
								if ( $is_correct_choice ) {
									$option_class .= ' lk-answer-review__option--correct';
								} elseif ( $is_user_choice && ! $is_correct_choice ) {
									$option_class .= ' lk-answer-review__option--wrong';
								}
								?>
								<li class="<?php echo esc_attr( $option_class ); ?>">
									<?php if ( $is_correct_choice ) : ?>
										<span class="lk-answer-review__option-icon">✓</span>
									<?php elseif ( $is_user_choice ) : ?>
										<span class="lk-answer-review__option-icon">✗</span>
									<?php else : ?>
										<span class="lk-answer-review__option-icon lk-answer-review__option-icon--empty">○</span>
									<?php endif; ?>
									<?php echo esc_html( $opt_text ); ?>
									<?php if ( $is_correct_choice && ! $is_user_choice ) : ?>
										<em class="lk-answer-review__correct-label"><?php esc_html_e( '(correct answer)', 'learnkit' ); ?></em>
									<?php endif; ?>
									<?php if ( $is_user_choice ) : ?>
										<em class="lk-answer-review__your-label"><?php esc_html_e( '(your answer)', 'learnkit' ); ?></em>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>

			<style>
				.lk-answer-review {
					margin-top: 40px;
				}
				.lk-answer-review__title {
					font-size: 22px;
					font-weight: 700;
					color: #1a1a1a;
					margin-bottom: 20px;
					padding-bottom: 10px;
					border-bottom: 2px solid #e0e0e0;
				}
				.lk-answer-review__question {
					background: #fff;
					border: 2px solid #e0e0e0;
					border-radius: 10px;
					padding: 20px 24px;
					margin-bottom: 16px;
				}
				.lk-answer-review__question--correct {
					border-color: #28a745;
					background: #f6fff8;
				}
				.lk-answer-review__question--incorrect {
					border-color: #dc3545;
					background: #fff6f6;
				}
				.lk-answer-review__question-header {
					display: flex;
					align-items: center;
					gap: 12px;
					margin-bottom: 10px;
					flex-wrap: wrap;
				}
				.lk-answer-review__question-number {
					font-weight: 700;
					color: #555;
					font-size: 13px;
					text-transform: uppercase;
					letter-spacing: 0.04em;
				}
				.lk-answer-review__result-badge {
					font-size: 13px;
					font-weight: 700;
					padding: 2px 10px;
					border-radius: 20px;
				}
				.lk-answer-review__result-badge--correct {
					background: #d4edda;
					color: #155724;
				}
				.lk-answer-review__result-badge--incorrect {
					background: #f8d7da;
					color: #721c24;
				}
				.lk-answer-review__points {
					margin-left: auto;
					font-size: 13px;
					color: #666;
					font-weight: 600;
				}
				.lk-answer-review__question-text {
					font-size: 16px;
					font-weight: 600;
					color: #1a1a1a;
					margin: 0 0 14px 0;
				}
				.lk-answer-review__options {
					list-style: none;
					padding: 0;
					margin: 0;
					display: flex;
					flex-direction: column;
					gap: 8px;
				}
				.lk-answer-review__option {
					display: flex;
					align-items: center;
					gap: 8px;
					padding: 10px 14px;
					border-radius: 6px;
					border: 1px solid #e0e0e0;
					background: #fafafa;
					font-size: 15px;
					color: #333;
				}
				.lk-answer-review__option--correct {
					border-color: #28a745;
					background: #d4edda;
					color: #155724;
					font-weight: 600;
				}
				.lk-answer-review__option--wrong {
					border-color: #dc3545;
					background: #f8d7da;
					color: #721c24;
					font-weight: 600;
				}
				.lk-answer-review__option-icon {
					font-size: 15px;
					font-weight: 700;
					flex-shrink: 0;
					width: 20px;
					text-align: center;
				}
				.lk-answer-review__option-icon--empty {
					color: #bbb;
					font-weight: 400;
				}
				.lk-answer-review__correct-label,
				.lk-answer-review__your-label {
					margin-left: 6px;
					font-size: 12px;
					opacity: 0.75;
					font-style: italic;
				}
			</style>
		<?php endif; ?>
		<?php
		// Don't show quiz form again after completion, just show navigation.
		get_footer();
		return;
		?>
	<?php endif; ?>

	<?php if ( ! $user_id ) : ?>
		<div class="not-enrolled">
			<h3><?php esc_html_e( 'Login Required', 'learnkit' ); ?></h3>
			<p><?php esc_html_e( 'You must be logged in to take this quiz.', 'learnkit' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="submit-button lk-submit-button btn--primary">
				<?php esc_html_e( 'Log In', 'learnkit' ); ?>
			</a>
		</div>
	<?php elseif ( ! $is_enrolled ) : ?>
		<div class="not-enrolled">
			<h3><?php esc_html_e( 'Enrollment Required', 'learnkit' ); ?></h3>
			<p><?php esc_html_e( 'You must be enrolled in this course to take this quiz.', 'learnkit' ); ?></p>
			<?php if ( $course_id ) : ?>
				<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="submit-button lk-submit-button btn--primary">
					<?php esc_html_e( 'View Course', 'learnkit' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<div class="quiz-header">
			<h1 class="quiz-title"><?php the_title(); ?></h1>
			<div class="quiz-meta">
				<div class="quiz-meta-item">
					<span>📝</span>
					<span><?php echo esc_html( count( $questions ) ); ?> <?php esc_html_e( 'Questions', 'learnkit' ); ?></span>
				</div>
				<?php if ( $time_limit > 0 ) : ?>
					<div class="quiz-meta-item">
						<span>⏱️</span>
						<span><?php echo esc_html( $time_limit ); ?> <?php esc_html_e( 'Minutes', 'learnkit' ); ?></span>
					</div>
				<?php endif; ?>
				<div class="quiz-meta-item">
					<span>🎯</span>
					<span><?php echo esc_html( $passing_score ); ?>% <?php esc_html_e( 'to Pass', 'learnkit' ); ?></span>
				</div>
				<?php if ( $attempts_allowed > 0 ) : ?>
					<div class="quiz-meta-item">
						<span>🔄</span>
						<span><?php echo esc_html( $attempts_used ); ?>/<?php echo esc_html( $attempts_allowed ); ?> <?php esc_html_e( 'Attempts', 'learnkit' ); ?></span>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $has_passed ) : ?>
			<div class="passed-banner">
				<h3>🎉 <?php esc_html_e( 'Quiz Passed!', 'learnkit' ); ?></h3>
				<p style="font-size: 20px; margin: 10px 0;">
				<?php
				/* translators: %d: Best quiz score percentage */
				printf( esc_html__( 'Your best score: %d%%', 'learnkit' ), (int) $best_attempt->score );
				?>
			</p>
				<p style="margin: 15px 0 20px 0; font-size: 14px; opacity: 0.9;">
					<?php
					if ( 0 === $attempts_allowed || $attempts_used < $attempts_allowed ) {
						esc_html_e( 'You\'ve already passed, but you can retake to improve your score if you wish.', 'learnkit' );
					} else {
						esc_html_e( 'Congratulations! You\'ve completed this quiz successfully.', 'learnkit' );
					}
					?>
				</p>
				<div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
					<?php if ( $lesson_id && ! empty( $lesson_id ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="submit-button lk-submit-button btn--secondary">
							<?php esc_html_e( 'Back to Lesson', 'learnkit' ); ?>
						</a>
					<?php elseif ( ( $module_id && ! empty( $module_id ) ) || ( $course_id && ! empty( $course_id ) ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $course_id ? $course_id : $module_id ) ); ?>" class="submit-button lk-submit-button btn--secondary">
							<?php esc_html_e( 'Back to Course', 'learnkit' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $attempts_allowed > 0 && $attempts_used >= $attempts_allowed && ! $has_passed ) : ?>
			<div class="not-enrolled">
				<h3><?php esc_html_e( 'No Attempts Remaining', 'learnkit' ); ?></h3>
				<p>
				<?php
				/* translators: %d: Maximum attempts allowed */
				printf( esc_html__( 'You have used all %d attempts for this quiz.', 'learnkit' ), (int) $attempts_allowed );
				?>
				</p>
				<?php if ( $best_attempt ) : ?>
					<p>
					<?php
					/* translators: %d: Best quiz score percentage */
					printf( esc_html__( 'Your best score: %d%%', 'learnkit' ), (int) $best_attempt->score );
					?>
				</p>
				<?php endif; ?>
				<div style="margin-top: 20px;">
					<?php if ( $lesson_id && ! empty( $lesson_id ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="submit-button lk-submit-button btn--secondary">
							<?php esc_html_e( 'Back to Lesson', 'learnkit' ); ?>
						</a>
					<?php elseif ( ( $module_id && ! empty( $module_id ) ) || ( $course_id && ! empty( $course_id ) ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $course_id ? $course_id : $module_id ) ); ?>" class="submit-button lk-submit-button btn--secondary">
							<?php esc_html_e( 'Back to Course', 'learnkit' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>

			<?php if ( $attempts_used > 0 ) : ?>
				<div class="quiz-attempts-info">
					<strong><?php esc_html_e( 'Previous Attempts:', 'learnkit' ); ?></strong>
					<?php foreach ( array_slice( $attempts, 0, 3 ) as $attempt ) : ?>
						<p>
							<?php
							echo esc_html(
								sprintf(
									'Score: %d%% (%s) - %s',
									(int) $attempt->score,
									$attempt->passed ? __( 'Passed', 'learnkit' ) : __( 'Failed', 'learnkit' ),
									gmdate( 'M j, Y g:i A', strtotime( $attempt->completed_at ) )
								)
							);
							?>
						</p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $time_limit > 0 ) : ?>
				<!-- Start Quiz Button for Timed Quizzes -->
				<div id="quiz-start-screen" style="text-align: center; padding: 40px 20px;">
					<h2><?php esc_html_e( 'Ready to Begin?', 'learnkit' ); ?></h2>
					<p style="font-size: 18px; margin: 20px 0;">
						<?php
						/* translators: 1: Time limit in minutes, 2: Number of questions */
						printf( esc_html__( 'This quiz is timed. You will have %1\$d minutes to complete %2\$d questions.', 'learnkit' ), (int) $time_limit, count( $questions ) );
						?>
					</p>
					<p style="color: #d63638; font-weight: 600; margin-bottom: 30px;">
						<?php esc_html_e( 'The timer will start as soon as you click the button below.', 'learnkit' ); ?>
					</p>
					<button type="button" id="start-quiz-button" class="submit-button lk-submit-button btn--primary">
						<?php esc_html_e( 'Start Quiz', 'learnkit' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<form id="learnkit-quiz-form" method="post" style="<?php echo $time_limit > 0 ? 'display: none;' : ''; ?>">
				<?php wp_nonce_field( 'learnkit_submit_quiz_' . $quiz_id, 'learnkit_quiz_nonce' ); ?>
				<input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
				<input type="hidden" name="start_time" value="<?php echo esc_attr( time() ); ?>">

				<?php if ( $time_limit > 0 ) : ?>
					<div class="quiz-timer" id="quiz-timer">
						<span id="time-remaining"><?php echo esc_html( $time_limit ); ?>:00</span>
					</div>
				<?php endif; ?>

				<?php foreach ( $questions as $index => $question ) : ?>
					<div class="quiz-question">
						<div class="question-header">
							<div class="question-text">
								<strong><?php echo esc_html( $index + 1 ); ?>.</strong>
								<?php echo esc_html( $question['question'] ); ?>
							</div>
							<div class="question-points">
								<?php echo esc_html( $question['points'] ); ?> <?php echo esc_html( 1 === $question['points'] ? __( 'pt', 'learnkit' ) : __( 'pts', 'learnkit' ) ); ?>
							</div>
						</div>
						<div class="quiz-options">
							<?php foreach ( $question['options'] as $opt_index => $option ) : ?>
								<div class="quiz-option">
									<input
										type="radio"
										name="question_<?php echo esc_attr( $question['id'] ); ?>"
										id="q<?php echo esc_attr( $question['id'] ); ?>_opt<?php echo esc_attr( $opt_index ); ?>"
										value="<?php echo esc_attr( $opt_index ); ?>"
										required
									>
									<label for="q<?php echo esc_attr( $question['id'] ); ?>_opt<?php echo esc_attr( $opt_index ); ?>">
										<?php echo esc_html( $option ); ?>
									</label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>

				<div class="quiz-submit">
					<button type="submit" class="submit-button lk-submit-button btn--primary">
						<?php esc_html_e( 'Submit Quiz', 'learnkit' ); ?>
					</button>
				</div>
			</form>

			<?php if ( $time_limit > 0 ) : ?>
				<script>
					(function() {
						const startButton = document.getElementById('start-quiz-button');
						const startScreen = document.getElementById('quiz-start-screen');
						const form = document.getElementById('learnkit-quiz-form');
						const timerDisplay = document.getElementById('time-remaining');
						const timeLimit = <?php echo (int) $time_limit; ?> * 60; // Convert to seconds
						let timeRemaining = timeLimit;
						let countdown;

						// Start quiz button handler
						if (startButton) {
							startButton.addEventListener('click', function() {
								// Hide start screen, show quiz
								startScreen.style.display = 'none';
								form.style.display = 'block';

								// Start countdown
								countdown = setInterval(function() {
									timeRemaining--;
									const minutes = Math.floor(timeRemaining / 60);
									const seconds = timeRemaining % 60;
									timerDisplay.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

									if (timeRemaining <= 0) {
										clearInterval(countdown);
										alert('<?php esc_html_e( 'Time is up! Submitting your quiz...', 'learnkit' ); ?>');
										form.submit();
									}
								}, 1000);
							});
						}

						// Clear timer on submit
						form.addEventListener('submit', function() {
							if (countdown) {
								clearInterval(countdown);
							}
						});
					})();
				</script>
			<?php endif; ?>

		<?php endif; ?>

	<?php endif; ?>
</div>

<?php
get_footer();
