<?php
/**
 * Template for displaying single quiz
 *
 * @link       https://jameswelbes.com
 * @since      0.4.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/public/templates
 */

get_header();

$quiz_id = get_the_ID();
$user_id = get_current_user_id();

// Get quiz settings.
$lesson_id           = get_post_meta( $quiz_id, '_lk_lesson_id', true );
$passing_score       = get_post_meta( $quiz_id, '_lk_passing_score', true ) ? get_post_meta( $quiz_id, '_lk_passing_score', true ) : 70;
$time_limit          = get_post_meta( $quiz_id, '_lk_time_limit', true ) ? get_post_meta( $quiz_id, '_lk_time_limit', true ) : 0;
$attempts_allowed    = get_post_meta( $quiz_id, '_lk_attempts_allowed', true ) ? get_post_meta( $quiz_id, '_lk_attempts_allowed', true ) : 0;
$required            = get_post_meta( $quiz_id, '_lk_required_to_complete', true );
$questions_json      = get_post_meta( $quiz_id, '_lk_questions', true ) ? get_post_meta( $quiz_id, '_lk_questions', true ) : '[]';
$questions           = json_decode( $questions_json, true );

// Get lesson info.
$lesson = $lesson_id ? get_post( $lesson_id ) : null;

// Check previous attempts.
$attempts      = array();
$attempts_used = 0;
$best_score    = 0;
$has_passed    = false;

if ( $user_id ) {
	global $wpdb;
	$attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$attempts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}learnkit_quiz_attempts WHERE user_id = %d AND quiz_id = %d ORDER BY completed_at DESC",
			$user_id,
			$quiz_id
		)
	);

	$attempts_used = count( $attempts );

	if ( ! empty( $attempts ) ) {
		foreach ( $attempts as $attempt ) {
			$score_percentage = ( $attempt->score / $attempt->max_score ) * 100;
			if ( $score_percentage > $best_score ) {
				$best_score = $score_percentage;
			}
			if ( $attempt->passed ) {
				$has_passed = true;
			}
		}
	}
}

// Check if user can take quiz.
$can_take_quiz = true;
$lock_message  = '';

if ( ! $user_id ) {
	$can_take_quiz = false;
	$lock_message  = 'You must be logged in to take this quiz.';
} elseif ( $attempts_allowed > 0 && $attempts_used >= $attempts_allowed ) {
	$can_take_quiz = false;
	$lock_message  = sprintf( 'You have used all %d attempts for this quiz.', $attempts_allowed );
}

?>

<style>
	.lk-quiz-container {
		max-width: 900px;
		margin: 40px auto;
		padding: 0 20px;
	}

	.lk-quiz-header {
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		color: #fff;
		padding: 40px;
		border-radius: 12px;
		margin-bottom: 30px;
	}

	.lk-quiz-title {
		font-size: 32px;
		font-weight: 700;
		margin: 0 0 10px 0;
	}

	.lk-quiz-lesson-link {
		color: rgba(255, 255, 255, 0.9);
		text-decoration: none;
		font-size: 16px;
	}

	.lk-quiz-lesson-link:hover {
		text-decoration: underline;
	}

	.lk-quiz-info {
		background: #f6f7f7;
		padding: 24px;
		border-radius: 12px;
		margin-bottom: 30px;
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 20px;
	}

	.lk-info-item {
		display: flex;
		flex-direction: column;
	}

	.lk-info-label {
		font-size: 13px;
		color: #757575;
		font-weight: 600;
		text-transform: uppercase;
		margin-bottom: 5px;
	}

	.lk-info-value {
		font-size: 20px;
		font-weight: 700;
		color: #1d2327;
	}

	.lk-attempts-info {
		background: #fff;
		padding: 20px;
		border-radius: 12px;
		border: 2px solid #dcdcde;
		margin-bottom: 30px;
	}

	.lk-attempts-info h3 {
		margin: 0 0 15px 0;
		font-size: 18px;
	}

	.lk-attempt-row {
		padding: 10px 0;
		border-bottom: 1px solid #f0f0f1;
		display: flex;
		justify-content: space-between;
	}

	.lk-attempt-row:last-child {
		border-bottom: none;
	}

	.lk-lock-message {
		background: #fff3cd;
		border: 2px solid #ffc107;
		padding: 20px;
		border-radius: 12px;
		text-align: center;
		margin: 30px 0;
	}

	.lk-quiz-form {
		background: #fff;
		padding: 32px;
		border-radius: 12px;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
	}

	.lk-question {
		margin-bottom: 40px;
		padding-bottom: 40px;
		border-bottom: 1px solid #dcdcde;
	}

	.lk-question:last-child {
		border-bottom: none;
		margin-bottom: 0;
		padding-bottom: 0;
	}

	.lk-question-header {
		margin-bottom: 20px;
	}

	.lk-question-number {
		font-size: 14px;
		font-weight: 600;
		color: #2271b1;
		text-transform: uppercase;
		margin-bottom: 8px;
	}

	.lk-question-text {
		font-size: 18px;
		font-weight: 600;
		color: #1d2327;
		line-height: 1.5;
	}

	.lk-question-points {
		font-size: 14px;
		color: #757575;
		margin-top: 5px;
	}

	.lk-answer-options {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	.lk-answer-option {
		display: flex;
		align-items: center;
		padding: 16px;
		border: 2px solid #dcdcde;
		border-radius: 8px;
		cursor: pointer;
		transition: all 0.2s;
	}

	.lk-answer-option:hover {
		border-color: #2271b1;
		background: #f6f7f7;
	}

	.lk-answer-option input[type="radio"] {
		width: 20px;
		height: 20px;
		margin-right: 12px;
		cursor: pointer;
	}

	.lk-answer-option label {
		font-size: 16px;
		cursor: pointer;
		flex: 1;
	}

	.lk-timer {
		position: fixed;
		top: 100px;
		right: 20px;
		background: #fff;
		padding: 20px;
		border-radius: 12px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
		text-align: center;
		z-index: 1000;
	}

	.lk-timer-label {
		font-size: 12px;
		color: #757575;
		font-weight: 600;
		text-transform: uppercase;
		margin-bottom: 8px;
	}

	.lk-timer-value {
		font-size: 32px;
		font-weight: 700;
		color: #1d2327;
	}

	.lk-timer.warning {
		background: #fff3cd;
		border: 2px solid #ffc107;
	}

	.lk-timer.warning .lk-timer-value {
		color: #ff6b6b;
	}

	.lk-submit-section {
		margin-top: 40px;
		text-align: center;
	}

	.lk-submit-button {
		display: inline-block;
		padding: 16px 48px;
		background: #2271b1;
		color: #fff;
		text-decoration: none;
		border-radius: 6px;
		font-size: 18px;
		font-weight: 600;
		border: none;
		cursor: pointer;
		transition: background 0.2s;
	}

	.lk-submit-button:hover {
		background: #135e96;
		color: #fff;
	}

	.lk-submit-button:disabled {
		background: #dcdcde;
		cursor: not-allowed;
	}

	.lk-results {
		background: #fff;
		padding: 32px;
		border-radius: 12px;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
	}

	.lk-result-header {
		text-align: center;
		padding: 40px;
		border-radius: 12px;
		margin-bottom: 30px;
	}

	.lk-result-header.passed {
		background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
		color: #fff;
	}

	.lk-result-header.failed {
		background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
		color: #fff;
	}

	.lk-result-score {
		font-size: 72px;
		font-weight: 700;
		margin: 0;
	}

	.lk-result-message {
		font-size: 24px;
		margin: 10px 0 0 0;
	}

	.lk-result-details {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
		gap: 20px;
		margin-bottom: 30px;
	}

	.lk-result-stat {
		background: #f6f7f7;
		padding: 20px;
		border-radius: 8px;
		text-align: center;
	}

	.lk-result-stat-value {
		font-size: 32px;
		font-weight: 700;
		color: #1d2327;
		margin-bottom: 5px;
	}

	.lk-result-stat-label {
		font-size: 14px;
		color: #757575;
	}

	.lk-question-result {
		margin-bottom: 30px;
		padding: 20px;
		border-radius: 8px;
	}

	.lk-question-result.correct {
		background: #d4edda;
		border: 2px solid #28a745;
	}

	.lk-question-result.incorrect {
		background: #f8d7da;
		border: 2px solid #dc3545;
	}

	.lk-result-actions {
		display: flex;
		gap: 16px;
		justify-content: center;
		margin-top: 30px;
	}

	@media (max-width: 768px) {
		.lk-timer {
			position: static;
			margin-bottom: 20px;
		}
	}
</style>

<div class="lk-quiz-container">
	<!-- Header -->
	<div class="lk-quiz-header">
		<h1 class="lk-quiz-title"><?php the_title(); ?></h1>
		<?php if ( $lesson ) : ?>
			<a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="lk-quiz-lesson-link">
				← Back to: <?php echo esc_html( $lesson->post_title ); ?>
			</a>
		<?php endif; ?>
	</div>

	<!-- Quiz Info -->
	<div class="lk-quiz-info">
		<div class="lk-info-item">
			<span class="lk-info-label">Questions</span>
			<span class="lk-info-value"><?php echo count( $questions ); ?></span>
		</div>
		<div class="lk-info-item">
			<span class="lk-info-label">Passing Score</span>
			<span class="lk-info-value"><?php echo esc_html( $passing_score ); ?>%</span>
		</div>
		<?php if ( $time_limit > 0 ) : ?>
			<div class="lk-info-item">
				<span class="lk-info-label">Time Limit</span>
				<span class="lk-info-value"><?php echo esc_html( $time_limit ); ?> min</span>
			</div>
		<?php endif; ?>
		<?php if ( $attempts_allowed > 0 ) : ?>
			<div class="lk-info-item">
				<span class="lk-info-label">Attempts</span>
				<span class="lk-info-value"><?php echo esc_html( $attempts_used ); ?> / <?php echo esc_html( $attempts_allowed ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<!-- Previous Attempts -->
	<?php if ( ! empty( $attempts ) ) : ?>
		<div class="lk-attempts-info">
			<h3>Previous Attempts</h3>
			<p>Best Score: <strong><?php echo esc_html( round( $best_score ) ); ?>%</strong></p>
			<?php foreach ( array_slice( $attempts, 0, 5 ) as $attempt ) : ?>
				<?php
				$attempt_percentage = ( $attempt->score / $attempt->max_score ) * 100;
				?>
				<div class="lk-attempt-row">
					<span><?php echo esc_html( wp_date( 'F j, Y g:i a', strtotime( $attempt->completed_at ) ) ); ?></span>
					<span>
						<strong><?php echo esc_html( round( $attempt_percentage ) ); ?>%</strong>
						<?php echo $attempt->passed ? '✅ Passed' : '❌ Failed'; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<!-- Lock Message -->
	<?php if ( ! $can_take_quiz ) : ?>
		<div class="lk-lock-message">
			<h2>Quiz Unavailable</h2>
			<p><?php echo esc_html( $lock_message ); ?></p>
			<?php if ( ! $user_id ) : ?>
				<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="lk-submit-button">Login</a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<!-- Quiz Form -->
		<form id="lk-quiz-form" class="lk-quiz-form">
			<?php wp_nonce_field( 'lk_submit_quiz', 'lk_quiz_nonce' ); ?>
			<input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">

			<?php foreach ( $questions as $index => $question ) : ?>
				<div class="lk-question">
					<div class="lk-question-header">
						<div class="lk-question-number">Question <?php echo esc_html( $index + 1 ); ?></div>
						<div class="lk-question-text"><?php echo esc_html( $question['question'] ); ?></div>
						<div class="lk-question-points"><?php echo esc_html( $question['points'] ); ?> <?php echo 1 === (int) $question['points'] ? 'point' : 'points'; ?></div>
					</div>

					<div class="lk-answer-options">
						<?php foreach ( $question['options'] as $opt_index => $option ) : ?>
							<div class="lk-answer-option">
								<input
									type="radio"
									id="q<?php echo esc_attr( $index ); ?>_opt<?php echo esc_attr( $opt_index ); ?>"
									name="answer_<?php echo esc_attr( $index ); ?>"
									value="<?php echo esc_attr( $opt_index ); ?>"
									required
								>
								<label for="q<?php echo esc_attr( $index ); ?>_opt<?php echo esc_attr( $opt_index ); ?>">
									<?php echo esc_html( $option ); ?>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<div class="lk-submit-section">
				<button type="submit" class="lk-submit-button">Submit Quiz</button>
			</div>
		</form>

		<!-- Timer (if time limit set) -->
		<?php if ( $time_limit > 0 ) : ?>
			<div id="lk-timer" class="lk-timer">
				<div class="lk-timer-label">Time Remaining</div>
				<div class="lk-timer-value" id="lk-timer-value"><?php echo esc_html( $time_limit ); ?>:00</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<!-- Results Container (hidden initially) -->
	<div id="lk-results" class="lk-results" style="display: none;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
	const form = document.getElementById('lk-quiz-form');
	const resultsContainer = document.getElementById('lk-results');
	const timerElement = document.getElementById('lk-timer');
	const timerValue = document.getElementById('lk-timer-value');

	// Timer countdown
	<?php if ( $time_limit > 0 ) : ?>
		let timeRemaining = <?php echo (int) $time_limit; ?> * 60; // seconds

		const timerInterval = setInterval(() => {
			timeRemaining--;

			const minutes = Math.floor(timeRemaining / 60);
			const seconds = timeRemaining % 60;
			timerValue.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

			if (timeRemaining <= 60) {
				timerElement.classList.add('warning');
			}

			if (timeRemaining <= 0) {
				clearInterval(timerInterval);
				alert('Time is up! Submitting quiz...');
				form.dispatchEvent(new Event('submit'));
			}
		}, 1000);
	<?php endif; ?>

	// Form submission
	if (form) {
		form.addEventListener('submit', async (e) => {
			e.preventDefault();

			const formData = new FormData(form);
			const answers = {};

			// Collect answers
			<?php foreach ( $questions as $index => $question ) : ?>
				const answer_<?php echo (int) $index; ?> = formData.get('answer_<?php echo (int) $index; ?>');
				if (answer_<?php echo (int) $index; ?> !== null) {
					answers['<?php echo (int) $index; ?>'] = parseInt(answer_<?php echo (int) $index; ?>);
				}
			<?php endforeach; ?>

			// Submit to API
			try {
				const response = await fetch('<?php echo esc_url( rest_url( 'learnkit/v1/quizzes/' . $quiz_id . '/submit' ) ); ?>', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
					},
					body: JSON.stringify({
						quiz_id: <?php echo (int) $quiz_id; ?>,
						answers: answers
					})
				});

				if (response.ok) {
					const result = await response.json();
					displayResults(result);
					form.style.display = 'none';
					if (timerElement) {
						timerElement.style.display = 'none';
					}
				} else {
					alert('Failed to submit quiz. Please try again.');
				}
			} catch (error) {
				console.error('Quiz submission error:', error);
				alert('An error occurred. Please try again.');
			}
		});
	}

	function displayResults(result) {
		const passedClass = result.passed ? 'passed' : 'failed';
		const passedText = result.passed ? 'You Passed!' : 'You Did Not Pass';

		let questionsHtml = '';
		result.questions.forEach((q, index) => {
			const correctClass = q.is_correct ? 'correct' : 'incorrect';
			const icon = q.is_correct ? '✅' : '❌';
			questionsHtml += `
				<div class="lk-question-result ${correctClass}">
					<strong>${icon} Question ${index + 1}:</strong> ${q.question}<br>
					<strong>Your answer:</strong> ${q.user_answer}<br>
					${!q.is_correct ? `<strong>Correct answer:</strong> ${q.correct_answer}` : ''}
				</div>
			`;
		});

		resultsContainer.innerHTML = `
			<div class="lk-result-header ${passedClass}">
				<div class="lk-result-score">${result.percentage}%</div>
				<div class="lk-result-message">${passedText}</div>
			</div>

			<div class="lk-result-details">
				<div class="lk-result-stat">
					<div class="lk-result-stat-value">${result.score} / ${result.max_score}</div>
					<div class="lk-result-stat-label">Points</div>
				</div>
				<div class="lk-result-stat">
					<div class="lk-result-stat-value">${result.correct_count} / ${result.total_questions}</div>
					<div class="lk-result-stat-label">Correct Answers</div>
				</div>
				<div class="lk-result-stat">
					<div class="lk-result-stat-value">${result.percentage >= <?php echo (int) $passing_score; ?> ? 'Pass' : 'Fail'}</div>
					<div class="lk-result-stat-label">Result</div>
				</div>
			</div>

			<h3>Question Review</h3>
			${questionsHtml}

			<div class="lk-result-actions">
				<?php if ( $lesson ) : ?>
					<a href="<?php echo esc_url( get_permalink( $lesson_id ) ); ?>" class="lk-submit-button">Back to Lesson</a>
				<?php endif; ?>
				<?php if ( 0 === $attempts_allowed || $attempts_used < $attempts_allowed ) : ?>
					<button onclick="location.reload()" class="lk-submit-button">Try Again</button>
				<?php endif; ?>
			</div>
		`;

		resultsContainer.style.display = 'block';
		resultsContainer.scrollIntoView({ behavior: 'smooth' });
	}
});
</script>

<?php
get_footer();
