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
class LearnKit_Quiz_Controller extends WP_REST_Controller {

	/**
	 * Register routes.
	 *
	 * @since    0.4.0
	 */
	public function register_routes() {
		$namespace = 'learnkit/v1';

		// Get quizzes (with optional lesson_id filter).
		register_rest_route(
			$namespace,
			'/quizzes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_quizzes' ),
				'permission_callback' => array( $this, 'check_permission' ),
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

		$args = array(
			'post_type'      => 'lk_quiz',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		);

		if ( $lesson_id ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_lk_lesson_id',
					'value' => $lesson_id,
				),
			);
		}

		$quizzes = get_posts( $args );
		$result  = array();

		foreach ( $quizzes as $quiz ) {
			$result[] = array(
				'id'    => $quiz->ID,
				'title' => $quiz->post_title,
				'meta'  => array(
					'_lk_lesson_id'          => get_post_meta( $quiz->ID, '_lk_lesson_id', true ),
					'_lk_passing_score'      => get_post_meta( $quiz->ID, '_lk_passing_score', true ),
					'_lk_time_limit'         => get_post_meta( $quiz->ID, '_lk_time_limit', true ),
					'_lk_attempts_allowed'   => get_post_meta( $quiz->ID, '_lk_attempts_allowed', true ),
					'_lk_required_to_complete' => get_post_meta( $quiz->ID, '_lk_required_to_complete', true ),
					'_lk_questions'          => get_post_meta( $quiz->ID, '_lk_questions', true ),
				),
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Check permission for quiz operations.
	 *
	 * @since    0.4.0
	 * @return   bool    Whether user has permission.
	 */
	public function check_permission() {
		return current_user_can( 'edit_posts' );
	}
}
