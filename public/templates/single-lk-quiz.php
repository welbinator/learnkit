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
	$table_name  = $wpdb->prefix . 'learnkit_enrollments';
	$enrollment  = $wpdb->get_row(
		$wpdb->prepare(
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
	.submit-button {
		background: #2271b1;
		color: #fff;
		border: none;
		padding: 15px 40px;
		font-size: 18px;
		font-weight: 600;
		border-radius: 6px;
		cursor: pointer;
		transition: background 0.2s;
	}
	.submit-button:hover {
		background: #135e96;
	}
	.submit-button:disabled {
		background: #ccc;
		cursor: not-allowed;
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
	if ( isset( $_GET['quiz_result'] ) && 'submitted' === $_GET['quiz_result'] ) :
		$result_score  = isset( $_GET['score'] ) ? (int) $_GET['score'] : 0;
		$result_passed = isset( $_GET['passed'] ) && '1' === $_GET['passed'];
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
				<?php printf( esc_html__( 'Your Score: %d%%', 'learnkit' ), $result_score ); ?>
			</p>
			<p>
				<?php
				if ( $result_passed ) {
					printf( esc_html__( 'You need %d%% to pass. Great job!', 'learnkit' ), $passing_score );
				} else {
					printf( esc_html__( 'You need %d%% to pass. Try again!', 'learnkit' ), $passing_score );
				}
				?>
			</p>
			<?php if ( ! $result_passed && ( $attempts_allowed === 0 || $attempts_used < $attempts_allowed ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $quiz_id ) ); ?>" class="submit-button">
					<?php esc_html_e( 'Retake Quiz', 'learnkit' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $lesson_id ) : ?>
				<a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="submit-button" style="margin-left: 10px;">
					<?php esc_html_e( 'Back to Lesson', 'learnkit' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! $user_id ) : ?>
		<div class="not-enrolled">
			<h3><?php esc_html_e( 'Login Required', 'learnkit' ); ?></h3>
			<p><?php esc_html_e( 'You must be logged in to take this quiz.', 'learnkit' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="submit-button">
				<?php esc_html_e( 'Log In', 'learnkit' ); ?>
			</a>
		</div>
	<?php elseif ( ! $is_enrolled ) : ?>
		<div class="not-enrolled">
			<h3><?php esc_html_e( 'Enrollment Required', 'learnkit' ); ?></h3>
			<p><?php esc_html_e( 'You must be enrolled in this course to take this quiz.', 'learnkit' ); ?></p>
			<?php if ( $course_id ) : ?>
				<a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="submit-button">
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
				<p style="font-size: 20px; margin: 10px 0;"><?php printf( esc_html__( 'Your best score: %d%%', 'learnkit' ), (int) $best_attempt->score ); ?></p>
				<p style="margin: 15px 0 0 0; font-size: 14px; opacity: 0.9;">
					<?php
					if ( $attempts_allowed === 0 || $attempts_used < $attempts_allowed ) {
						esc_html_e( 'You\'ve already passed, but you can retake to improve your score if you wish.', 'learnkit' );
					} else {
						esc_html_e( 'Congratulations! You\'ve completed this quiz successfully.', 'learnkit' );
					}
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $attempts_allowed > 0 && $attempts_used >= $attempts_allowed && ! $has_passed ) : ?>
			<div class="not-enrolled">
				<h3><?php esc_html_e( 'No Attempts Remaining', 'learnkit' ); ?></h3>
				<p><?php printf( esc_html__( 'You have used all %d attempts for this quiz.', 'learnkit' ), $attempts_allowed ); ?></p>
				<?php if ( $best_attempt ) : ?>
					<p><?php printf( esc_html__( 'Your best score: %d%%', 'learnkit' ), (int) $best_attempt->score ); ?></p>
				<?php endif; ?>
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

			<form id="learnkit-quiz-form" method="post">
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
								<?php echo esc_html( $question['points'] ); ?> <?php echo esc_html( $question['points'] === 1 ? __( 'pt', 'learnkit' ) : __( 'pts', 'learnkit' ) ); ?>
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
					<button type="submit" class="submit-button">
						<?php esc_html_e( 'Submit Quiz', 'learnkit' ); ?>
					</button>
				</div>
			</form>

			<?php if ( $time_limit > 0 ) : ?>
				<script>
					(function() {
						const timeLimit = <?php echo (int) $time_limit; ?> * 60; // Convert to seconds
						let timeRemaining = timeLimit;
						const timerDisplay = document.getElementById('time-remaining');
						const form = document.getElementById('learnkit-quiz-form');

						const countdown = setInterval(function() {
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

						// Clear timer on submit
						form.addEventListener('submit', function() {
							clearInterval(countdown);
						});
					})();
				</script>
			<?php endif; ?>

		<?php endif; ?>

	<?php endif; ?>
</div>

<?php
get_footer();
