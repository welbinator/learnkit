<?php
/**
 * Template for displaying a single quiz (student-facing).
 *
 * @package LearnKit
 * @since   0.4.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-scoped variables, not true PHP globals.

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
	$is_enrolled = learnkit_is_enrolled( $user_id, (int) $course_id );
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
	$attempts       = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API equivalent.
		$wpdb->prepare(
			'SELECT * FROM %i WHERE user_id = %d AND quiz_id = %d ORDER BY completed_at DESC',
			$attempts_table,
			$user_id,
			$quiz_id
		)
	);
}

$attempts_used = count( $attempts );
$best_attempt  = ! empty( $attempts ) ? $attempts[0] : null;
$has_passed    = $best_attempt && $best_attempt->passed;

?>

<div class="learnkit-quiz-container">
	<?php
	// Show results if quiz was just submitted.
	if ( isset( $_GET['quiz_result'] ) && 'submitted' === $_GET['quiz_result'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result_data  = array();
		$result_token = isset( $_GET['result_token'] ) ? sanitize_text_field( wp_unslash( $_GET['result_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $result_token && is_user_logged_in() ) {
			$transient_key = 'lk_quiz_result_' . get_current_user_id() . '_' . $quiz_id . '_' . $result_token;
			$result_data   = get_transient( $transient_key );
			if ( $result_data ) {
				delete_transient( $transient_key ); // One-time use.
			}
		}
		$result_score  = isset( $result_data['score'] ) ? (int) $result_data['score'] : 0;
		$result_passed = isset( $result_data['passed'] ) ? (bool) $result_data['passed'] : false;

		// Load the most recent attempt for answer review (Option C).
		$latest_attempt   = null;
		$attempt_answers  = array();
		$question_results = array();

		if ( $user_id ) {
			global $wpdb;
			$attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$latest_attempt = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE user_id = %d AND quiz_id = %d ORDER BY completed_at DESC LIMIT 1',
					$attempts_table,
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
					<a href="<?php echo esc_url( get_permalink( $quiz_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'retake_quiz_button', 'lk-button-submit lk-button-submit' ) ); ?>">
						<?php esc_html_e( 'Retake Quiz', 'learnkit' ); ?>
					</a>
				<?php elseif ( true === $result_passed && ( 0 === $attempts_allowed || $attempts_used < $attempts_allowed ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $quiz_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'retake_quiz_button', 'lk-button-submit lk-button-submit' ) ); ?>">
						<?php esc_html_e( 'Retake Quiz', 'learnkit' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $lesson_id && ! empty( $lesson_id ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'back_to_lesson_button', 'lk-button-submit lk-button-submit' ) ); ?>">
						<?php esc_html_e( 'Back to Lesson', 'learnkit' ); ?>
					</a>
				<?php elseif ( ( $module_id && ! empty( $module_id ) ) || ( $course_id && ! empty( $course_id ) ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $course_id ? $course_id : $module_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'back_to_course_button', 'lk-button-submit lk-button-submit' ) ); ?>">
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
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'login_button', 'lk-button-submit lk-button-submit' ) ); ?>">
				<?php esc_html_e( 'Log In', 'learnkit' ); ?>
			</a>
		</div>
	<?php elseif ( ! $is_enrolled ) : ?>
		<div class="not-enrolled">
			<h3><?php esc_html_e( 'Enrollment Required', 'learnkit' ); ?></h3>
			<p><?php esc_html_e( 'You must be enrolled in this course to take this quiz.', 'learnkit' ); ?></p>
			<?php if ( $course_id ) : ?>
				<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'back_to_course_button', 'lk-button-submit lk-button-submit' ) ); ?>">
					<?php esc_html_e( 'View Course', 'learnkit' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<div class="quiz-header">
			<h1 class="quiz-title"><?php the_title(); ?></h1>
			<div class="quiz-meta">
				<div class="quiz-meta-item">
					<span class="lk-icon"><svg aria-hidden="true" focusable="false" width="1em" height="1em" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><g><path d="M154.8,424.7h202.4c12.8,0,23.2-10.4,23.2-23.2V139.3c0-12.8-10.4-23.2-23.2-23.2h-29.8V94.8c0-4.1-3.4-7.5-7.5-7.5s-7.5,3.4-7.5,7.5v21.3h-48.9V94.8c0-4.1-3.4-7.5-7.5-7.5s-7.5,3.4-7.5,7.5v21.3h-48.9V94.8c0-4.1-3.4-7.5-7.5-7.5s-7.5,3.4-7.5,7.5v21.3h-29.8c-12.8,0-23.2,10.4-23.2,23.2v262.1C131.6,414.2,142,424.7,154.8,424.7z M146.6,139.3c0-4.5,3.7-8.2,8.2-8.2h29.8v21.5c0,4.1,3.4,7.5,7.5,7.5s7.5-3.4,7.5-7.5v-21.5h48.9v21.5c0,4.1,3.4,7.5,7.5,7.5s7.5-3.4,7.5-7.5v-21.5h48.9v21.5c0,4.1,3.4,7.5,7.5,7.5s7.5-3.4,7.5-7.5v-21.5h29.8c4.5,0,8.2,3.7,8.2,8.2v262.1c0,4.5-3.7,8.2-8.2,8.2H154.8c-4.5,0-8.2-3.7-8.2-8.2V139.3z"/><path d="M181.9,219.4c1.5,1.5,3.4,2.2,5.3,2.2c1.9,0,3.8-0.7,5.3-2.2l26.5-26.5c2.9-2.9,2.9-7.7,0-10.6c-2.9-2.9-7.7-2.9-10.6,0l-21.2,21.2l-7.7-7.7c-2.9-2.9-7.7-2.9-10.6,0c-2.9,2.9-2.9,7.7,0,10.6L181.9,219.4z"/><path d="M238.1,208.3h99.7c4.1,0,7.5-3.4,7.5-7.5s-3.4-7.5-7.5-7.5h-99.7c-4.1,0-7.5,3.4-7.5,7.5S234,208.3,238.1,208.3z"/><path d="M181.9,289c1.5,1.5,3.4,2.2,5.3,2.2c1.9,0,3.8-0.7,5.3-2.2l26.5-26.5c2.9-2.9,2.9-7.7,0-10.6s-7.7-2.9-10.6,0L187.2,273l-7.7-7.7c-2.9-2.9-7.7-2.9-10.6,0c-2.9,2.9-2.9,7.7,0,10.6L181.9,289z"/><path d="M238.1,277.9h99.7c4.1,0,7.5-3.4,7.5-7.5s-3.4-7.5-7.5-7.5h-99.7c-4.1,0-7.5,3.4-7.5,7.5S234,277.9,238.1,277.9z"/><path d="M181.9,358.5c1.5,1.5,3.4,2.2,5.3,2.2c1.9,0,3.8-0.7,5.3-2.2l26.5-26.5c2.9-2.9,2.9-7.7,0-10.6s-7.7-2.9-10.6,0l-21.2,21.2l-7.7-7.7c-2.9-2.9-7.7-2.9-10.6,0c-2.9,2.9-2.9,7.7,0,10.6L181.9,358.5z"/><path d="M238.1,347.5h99.7c4.1,0,7.5-3.4,7.5-7.5s-3.4-7.5-7.5-7.5h-99.7c-4.1,0-7.5,3.4-7.5,7.5S234,347.5,238.1,347.5z"/></g></svg></span>
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
						<a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'back_to_lesson_button', 'lk-button-submit lk-button-submit' ) ); ?>">
							<?php esc_html_e( 'Back to Lesson', 'learnkit' ); ?>
						</a>
					<?php elseif ( ( $module_id && ! empty( $module_id ) ) || ( $course_id && ! empty( $course_id ) ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $course_id ? $course_id : $module_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'back_to_course_button', 'lk-button-submit lk-button-submit' ) ); ?>">
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
						<a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'back_to_lesson_button', 'lk-button-submit lk-button-submit' ) ); ?>">
							<?php esc_html_e( 'Back to Lesson', 'learnkit' ); ?>
						</a>
					<?php elseif ( ( $module_id && ! empty( $module_id ) ) || ( $course_id && ! empty( $course_id ) ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $course_id ? $course_id : $module_id ) ); ?>" class="<?php echo esc_attr( learnkit_button_classes( 'back_to_course_button', 'lk-button-submit lk-button-submit' ) ); ?>">
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
					<button type="button" id="start-quiz-button" class="<?php echo esc_attr( learnkit_button_classes( 'start_quiz_button', 'lk-button-submit lk-button-submit' ) ); ?>">
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
					<button type="submit" class="<?php echo esc_attr( learnkit_button_classes( 'submit_quiz_button', 'lk-button-submit lk-button-submit' ) ); ?>">
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
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
get_footer();
