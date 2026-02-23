<?php
/**
 * REST API Controller for Quizzes
 *
 * @link       https://jameswelbes.com
 * @since      0.4.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 */

/**
 * LearnKit Quiz REST Controller.
 *
 * Handles CRUD operations for quizzes via REST API.
 *
 * @since      0.4.0
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Quiz_Controller extends LearnKit_Base_Controller {

	/**
	 * Register routes.
	 *
	 * @since    0.4.0
	 */
	public function register_routes() {
		$namespace = $this->namespace;

		// Get quizzes (with optional lesson_id filter).
		register_rest_route(
			$namespace,
			'/quizzes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_quizzes' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);

		// Submit quiz.
		register_rest_route(
			$namespace,
			'/quizzes/(?P<id>\d+)/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_quiz' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
				'args'                => array(
					'answers' => array(
						'required'    => true,
						'type'        => 'object',
						'description' => __( 'Map of question IDs to answer values.', 'learnkit' ),
					),
				),
			)
		);

		// Get quiz attempts.
		register_rest_route(
			$namespace,
			'/quizzes/(?P<id>\d+)/attempts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_attempts' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'args'                => array(
					'user_id' => array(
						'type'              => 'integer',
						'description'       => __( 'Filter attempts by user ID.', 'learnkit' ),
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Get quizzes.
	 *
	 * @since    0.4.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response            Response object.
	 */
	public function get_quizzes( $request ) {
		$lesson_id = $request->get_param( 'lesson_id' );
		$module_id = $request->get_param( 'module_id' );
		$course_id = $request->get_param( 'course_id' );

		$args = array(
			'post_type'      => 'lk_quiz',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		);

		// Build meta query based on which ID is provided.
		$meta_query = array();

		if ( $lesson_id ) {
			$meta_query[] = array(
				'key'   => '_lk_lesson_id',
				'value' => $lesson_id,
			);
		}

		if ( $module_id ) {
			$meta_query[] = array(
				'key'   => '_lk_module_id',
				'value' => $module_id,
			);
		}

		if ( $course_id ) {
			$meta_query[] = array(
				'key'   => '_lk_course_id',
				'value' => $course_id,
			);
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		$quizzes = get_posts( $args );
		$result  = array();

		foreach ( $quizzes as $quiz ) {
			$result[] = array(
				'id'    => $quiz->ID,
				'title' => $quiz->post_title,
				'meta'  => array(
					'_lk_lesson_id'            => get_post_meta( $quiz->ID, '_lk_lesson_id', true ),
					'_lk_module_id'            => get_post_meta( $quiz->ID, '_lk_module_id', true ),
					'_lk_course_id'            => get_post_meta( $quiz->ID, '_lk_course_id', true ),
					'_lk_passing_score'        => get_post_meta( $quiz->ID, '_lk_passing_score', true ),
					'_lk_time_limit'           => get_post_meta( $quiz->ID, '_lk_time_limit', true ),
					'_lk_attempts_allowed'     => get_post_meta( $quiz->ID, '_lk_attempts_allowed', true ),
					'_lk_required_to_complete' => get_post_meta( $quiz->ID, '_lk_required_to_complete', true ),
					'_lk_questions'            => get_post_meta( $quiz->ID, '_lk_questions', true ),
				),
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Submit quiz answers and grade.
	 *
	 * @since    0.4.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response            Response object.
	 */
	public function submit_quiz( $request ) {
		$quiz_id = $request->get_param( 'id' );
		$answers = $request->get_param( 'answers' );
		$user_id = get_current_user_id();

		// Enforce attempt limits server-side.
		$limit_error = $this->check_attempt_limit( $quiz_id, $user_id );
		if ( $limit_error instanceof WP_REST_Response ) {
			return $limit_error;
		}

		// Grade and save.
		$grade_data = $this->grade_quiz( $quiz_id, $answers );
		$this->save_quiz_attempt( $quiz_id, $user_id, $grade_data, $answers );

		return rest_ensure_response(
			array(
				'score'           => $grade_data['score'],
				'max_score'       => $grade_data['max_score'],
				'percentage'      => $grade_data['percentage'],
				'passed'          => $grade_data['passed'],
				'correct_count'   => $grade_data['correct_count'],
				'total_questions' => $grade_data['total_questions'],
				'questions'       => $grade_data['questions'],
			)
		);
	}

	/**
	 * Check whether the current user has exceeded the attempt limit for a quiz.
	 *
	 * @since    0.4.0
	 * @param    int $quiz_id  Quiz post ID.
	 * @param    int $user_id  WordPress user ID.
	 * @return   WP_REST_Response|null  Error response if limit reached, null otherwise.
	 */
	private function check_attempt_limit( $quiz_id, $user_id ) {
		global $wpdb;

		$attempts_allowed = (int) get_post_meta( $quiz_id, '_lk_attempts_allowed', true );
		if ( $attempts_allowed <= 0 ) {
			return null;
		}

		$attempt_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table, no WP API equivalent.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
				"SELECT COUNT(*) FROM {$wpdb->prefix}learnkit_quiz_attempts WHERE user_id = %d AND quiz_id = %d",
				$user_id,
				$quiz_id
			)
		);

		if ( $attempt_count >= $attempts_allowed ) {
			return new WP_REST_Response(
				array( 'message' => __( 'You have reached the maximum number of attempts for this quiz.', 'learnkit' ) ),
				403
			);
		}

		return null;
	}

	/**
	 * Grade a quiz submission and return score data.
	 *
	 * @since    0.4.0
	 * @param    int   $quiz_id  Quiz post ID.
	 * @param    array $answers  Map of question index (string) to selected option index (int).
	 * @return   array {
	 *     @type int   $score           Raw score earned.
	 *     @type int   $max_score       Maximum possible score.
	 *     @type int   $percentage      Score as a rounded percentage.
	 *     @type bool  $passed          Whether the passing threshold was met.
	 *     @type int   $correct_count   Number of correctly answered questions.
	 *     @type int   $total_questions Total number of questions.
	 *     @type array $questions       Per-question result objects.
	 * }
	 */
	private function grade_quiz( $quiz_id, $answers ) {
		$questions_json = get_post_meta( $quiz_id, '_lk_questions', true );
		$questions      = json_decode( $questions_json, true );
		$passing_score  = get_post_meta( $quiz_id, '_lk_passing_score', true );
		if ( ! $passing_score ) {
			$passing_score = 70;
		}

		$score            = 0;
		$max_score        = 0;
		$correct_count    = 0;
		$question_results = array();

		foreach ( $questions as $index => $question ) {
			$max_score += (int) $question['points'];

			$user_answer    = isset( $answers[ (string) $index ] ) ? (int) $answers[ (string) $index ] : -1;
			$correct_answer = (int) $question['correctAnswer'];
			$is_correct     = $user_answer === $correct_answer;

			if ( $is_correct ) {
				$score += (int) $question['points'];
				$correct_count++;
			}

			$question_results[] = array(
				'question'       => $question['question'],
				'user_answer'    => $user_answer >= 0 ? $question['options'][ $user_answer ] : 'No answer',
				'correct_answer' => $question['options'][ $correct_answer ],
				'is_correct'     => $is_correct,
			);
		}

		$percentage = $max_score > 0 ? round( ( $score / $max_score ) * 100 ) : 0;

		return array(
			'score'           => $score,
			'max_score'       => $max_score,
			'percentage'      => $percentage,
			'passed'          => $percentage >= $passing_score,
			'correct_count'   => $correct_count,
			'total_questions' => count( $questions ),
			'questions'       => $question_results,
		);
	}

	/**
	 * Persist a quiz attempt row to the database.
	 *
	 * @since    0.4.0
	 * @param    int   $quiz_id    Quiz post ID.
	 * @param    int   $user_id    WordPress user ID.
	 * @param    array $grade_data Grade data array as returned by grade_quiz().
	 * @param    array $answers    Raw answers map submitted by the user.
	 */
	private function save_quiz_attempt( $quiz_id, $user_id, $grade_data, $answers = array() ) {
		global $wpdb;

		$attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$attempts_table,
			array(
				'user_id'      => $user_id,
				'quiz_id'      => $quiz_id,
				'score'        => $grade_data['score'],
				'max_score'    => $grade_data['max_score'],
				'passed'       => $grade_data['passed'] ? 1 : 0,
				'answers'      => wp_json_encode( $answers ),
				'completed_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get quiz attempts for a quiz.
	 *
	 * @since    0.4.0
	 * @param    WP_REST_Request $request    Request object.
	 * @return   WP_REST_Response            Response object.
	 */
	public function get_attempts( $request ) {
		$quiz_id = $request->get_param( 'id' );
		$user_id = $request->get_param( 'user_id' );

		global $wpdb;

		if ( $user_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$attempts = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
					"SELECT * FROM {$wpdb->prefix}learnkit_quiz_attempts WHERE quiz_id = %d AND user_id = %d ORDER BY completed_at DESC",
					$quiz_id,
					$user_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$attempts = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
					"SELECT * FROM {$wpdb->prefix}learnkit_quiz_attempts WHERE quiz_id = %d ORDER BY completed_at DESC",
					$quiz_id
				)
			);
		}

		return rest_ensure_response( $attempts );
	}

	/**
	 * Check permission for user operations (logged in).
	 *
	 * @since    0.4.0
	 * @return   bool    Whether user is logged in.
	 */
	public function check_user_permission() {
		return is_user_logged_in();
	}
}
