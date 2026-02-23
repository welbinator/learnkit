<?php
/**
 * REST API: Progress Controller
 *
 * @link       https://jameswelbes.com
 * @since      0.2.14
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 */

/**
 * Progress REST API controller.
 *
 * Handles all progress tracking endpoints.
 *
 * @since      0.2.14
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Progress_Controller {

	/**
	 * The namespace for our REST API.
	 *
	 * @since    0.2.14
	 * @access   private
	 * @var      string    $namespace    The namespace for REST API routes.
	 */
	private $namespace = 'learnkit/v1';

	/**
	 * Register progress routes.
	 *
	 * @since    0.2.14
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/progress/(?P<lesson_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_lesson_complete' ),
					'permission_callback' => array( $this, 'check_user_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'mark_lesson_incomplete' ),
					'permission_callback' => array( $this, 'check_user_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/progress/user/(?P<user_id>\d+)/course/(?P<course_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_course_progress' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/progress/user/(?P<user_id>\d+)/module/(?P<module_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_module_progress' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);
	}

	/**
	 * Mark lesson as complete.
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function mark_lesson_complete( $request ) {
		global $wpdb;

		$lesson_id = (int) $request['lesson_id'];
		$user_id   = get_current_user_id();

		// Verify lesson exists.
		$lesson = get_post( $lesson_id );
		if ( ! $lesson || 'lk_lesson' !== $lesson->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Lesson not found', 'learnkit' ) ),
				404
			);
		}

		// Resolve lesson → module → course chain.
		$module_id = (int) get_post_meta( $lesson_id, '_lk_module_id', true );
		$course_id = $module_id ? (int) get_post_meta( $module_id, '_lk_course_id', true ) : 0;

		// Require enrollment.
		if ( $course_id && ! learnkit_is_enrolled( $user_id, $course_id ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'You are not enrolled in this course.', 'learnkit' ) ),
				403
			);
		}

		// Backend gate: enforce required quiz passage.
		$gate_error = $this->check_quiz_gate( $lesson_id, $user_id );
		if ( $gate_error instanceof WP_REST_Response ) {
			return $gate_error;
		}

		$table = $wpdb->prefix . 'learnkit_progress';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND lesson_id = %d",
				$user_id,
				$lesson_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $existing ) {
			return new WP_REST_Response(
				array(
					'message'   => __( 'Lesson already marked complete', 'learnkit' ),
					'completed' => true,
				),
				200
			);
		}

		// Insert new progress record.
		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'      => $user_id,
				'lesson_id'    => $lesson_id,
				'completed_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to mark lesson complete', 'learnkit' ) ),
				500
			);
		}

		// Check if the entire course is now complete and fire action.
		$this->maybe_fire_course_completed( $lesson_id, $user_id );

		return new WP_REST_Response(
			array(
				'message'   => __( 'Lesson marked complete', 'learnkit' ),
				'completed' => true,
			),
			201
		);
	}

	/**
	 * Check whether a required quiz has been passed before allowing lesson completion.
	 *
	 * @since    0.2.14
	 * @param    int $lesson_id Lesson post ID.
	 * @param    int $user_id   Current user ID.
	 * @return   WP_REST_Response|true  Error response if gate blocks, true if clear.
	 */
	private function check_quiz_gate( $lesson_id, $user_id ) {
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

		if ( ! $required_quiz ) {
			return true;
		}

		$attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$passing_attempt = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
				"SELECT COUNT(*) FROM $attempts_table WHERE user_id = %d AND quiz_id = %d AND passed = 1",
				$user_id,
				(int) $required_quiz->ID
			)
		);

		if ( empty( $passing_attempt ) || 0 === (int) $passing_attempt ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'You must pass the quiz before marking this lesson complete.', 'learnkit' ),
				),
				403
			);
		}

		return true;
	}

	/**
	 * Mark lesson as incomplete.
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function mark_lesson_incomplete( $request ) {
		global $wpdb;

		$lesson_id = (int) $request['lesson_id'];
		$user_id   = get_current_user_id();

		$table = $wpdb->prefix . 'learnkit_progress';

		$deleted = $wpdb->delete(
			$table,
			array(
				'user_id'   => $user_id,
				'lesson_id' => $lesson_id,
			),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to mark lesson incomplete', 'learnkit' ) ),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'message'   => __( 'Lesson marked incomplete', 'learnkit' ),
				'completed' => false,
			),
			200
		);
	}

	/**
	 * Get course progress for a user.
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_course_progress( $request ) {
		$user_id   = (int) $request['user_id'];
		$course_id = (int) $request['course_id'];

		$progress = learnkit_get_course_progress( $user_id, $course_id );

		// Build the completed lesson IDs list for the REST response.
		global $wpdb;

		$module_ids = get_posts(
			array(
				'post_type'              => 'lk_module',
				'posts_per_page'         => -1,
				'meta_key'               => '_lk_course_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$completed_lesson_ids = array();

		if ( ! empty( $module_ids ) ) {
			$lesson_ids = get_posts(
				array(
					'post_type'              => 'lk_lesson',
					'posts_per_page'         => -1,
					'meta_key'               => '_lk_module_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value__in'         => $module_ids,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( ! empty( $lesson_ids ) ) {
				$table        = $wpdb->prefix . 'learnkit_progress';
				$placeholders = implode( ',', array_fill( 0, count( $lesson_ids ), '%d' ) );
				$args         = array_merge( array( $user_id ), $lesson_ids );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$completed_lesson_ids = array_map(
					'intval',
					(array) $wpdb->get_col(
						$wpdb->prepare(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
							"SELECT lesson_id FROM {$table} WHERE user_id = %d AND lesson_id IN ({$placeholders})",
							$args
						)
					)
				);
			}
		}

		return new WP_REST_Response(
			array(
				'total_lessons'        => $progress['total_lessons'],
				'completed_lessons'    => $progress['completed_lessons'],
				'progress_percent'     => $progress['progress_percent'],
				'completed_lesson_ids' => $completed_lesson_ids,
			),
			200
		);
	}

	/**
	 * Get module progress for a user.
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_module_progress( $request ) {
		global $wpdb;

		$user_id   = (int) $request['user_id'];
		$module_id = (int) $request['module_id'];

		// Get all lessons in this module.
		$lessons = get_posts(
			array(
				'post_type'      => 'lk_lesson',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_key'       => '_lk_module_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $module_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$total_lessons = count( $lessons );
		$lesson_ids    = wp_list_pluck( $lessons, 'ID' );

		if ( empty( $lesson_ids ) ) {
			return new WP_REST_Response(
				array(
					'total_lessons'        => 0,
					'completed_lessons'    => 0,
					'progress_percent'     => 0,
					'completed_lesson_ids' => array(),
				),
				200
			);
		}

		// Get completed lessons.
		$table        = $wpdb->prefix . 'learnkit_progress';
		$placeholders = implode( ',', array_fill( 0, count( $lesson_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$completed = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT lesson_id FROM $table WHERE user_id = %d AND lesson_id IN ($placeholders)",
				array_merge( array( $user_id ), $lesson_ids )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$completed_count  = count( $completed );
		$progress_percent = $total_lessons > 0 ? round( ( $completed_count / $total_lessons ) * 100 ) : 0;

		return new WP_REST_Response(
			array(
				'total_lessons'        => $total_lessons,
				'completed_lessons'    => $completed_count,
				'progress_percent'     => $progress_percent,
				'completed_lesson_ids' => array_map( 'intval', $completed ),
			),
			200
		);
	}

	/**
	 * Check user permission.
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   bool True if user is logged in and authorised.
	 */
	public function check_user_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$requested_id = isset( $request['user_id'] ) ? (int) $request['user_id'] : 0;
		// For the mark-complete POST endpoint, no user_id in URL — only check login.
		if ( 0 === $requested_id ) {
			return true;
		}
		return get_current_user_id() === $requested_id || current_user_can( 'manage_options' );
	}

	/**
	 * Fire the learnkit_user_completed_course action if all lessons are done.
	 *
	 * @since    0.5.0
	 * @param    int $lesson_id   The lesson just completed.
	 * @param    int $user_id     The current user.
	 */
	private function maybe_fire_course_completed( $lesson_id, $user_id ) {
		global $wpdb;

		// Get course via lesson → module → course chain.
		$module_id = get_post_meta( $lesson_id, '_lk_module_id', true );
		if ( ! $module_id ) {
			return;
		}
		$course_id = get_post_meta( (int) $module_id, '_lk_course_id', true );
		if ( ! $course_id ) {
			return;
		}

		// Get all lessons in this course.
		$module_ids = get_posts(
			array(
				'post_type'              => 'lk_module',
				'posts_per_page'         => -1,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_lk_course_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => (int) $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( empty( $module_ids ) ) {
			return;
		}

		$lessons = get_posts(
			array(
				'post_type'              => 'lk_lesson',
				'posts_per_page'         => -1,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_lk_module_id',
						'value'   => $module_ids,
						'compare' => 'IN',
					),
				),
			)
		);

		if ( empty( $lessons ) ) {
			return;
		}

		$total        = count( $lessons );
		$progress_tbl = $wpdb->prefix . 'learnkit_progress';
		$placeholders = implode( ',', array_fill( 0, $total, '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$completed_count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $progress_tbl WHERE user_id = %d AND lesson_id IN ($placeholders)",
				array_merge( array( $user_id ), $lessons )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $completed_count >= $total ) {
			do_action( 'learnkit_user_completed_course', $user_id, (int) $course_id );
		}
	}
}
