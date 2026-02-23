<?php
/**
 * REST API: Enrollments Controller
 *
 * @link       https://jameswelbes.com
 * @since      0.2.14
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 */

/**
 * Enrollments REST API controller.
 *
 * Handles all enrollment endpoints (admin manual enrollment).
 *
 * @since      0.2.14
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Enrollments_Controller {

	/**
	 * The namespace for our REST API.
	 *
	 * @since    0.2.14
	 * @access   private
	 * @var      string    $namespace    The namespace for REST API routes.
	 */
	private $namespace = 'learnkit/v1';

	/**
	 * Register enrollment routes.
	 *
	 * @since    0.2.14
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/enrollments',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_enrollment' ),
					'permission_callback' => array( $this, 'check_enrollment_permission' ),
					'args'                => array(
						'user_id'   => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
						'course_id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/enrollments/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_enrollment' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/enrollments/course/(?P<course_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_course_enrollments' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/enrollments/user/(?P<user_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_user_enrollments' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);
	}

	/**
	 * Create enrollment.
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function create_enrollment( $request ) {
		$user_id   = (int) $request['user_id'];
		$course_id = (int) $request['course_id'];

		$validation_error = $this->validate_enrollment_request( $user_id, $course_id );
		if ( $validation_error instanceof WP_REST_Response ) {
			return $validation_error;
		}

		return $this->insert_enrollment( $user_id, $course_id );
	}

	/**
	 * Validate an enrollment request — checks user, course, and access permissions.
	 *
	 * @since    0.2.14
	 * @param    int $user_id   User ID to enroll.
	 * @param    int $course_id Course ID to enroll into.
	 * @return   WP_REST_Response|true  Error response on failure, true on success.
	 */
	private function validate_enrollment_request( $user_id, $course_id ) {
		// Verify user exists.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_REST_Response(
				array( 'message' => __( 'User not found', 'learnkit' ) ),
				404
			);
		}

		// Verify course exists.
		$course = get_post( $course_id );
		if ( ! $course || 'lk_course' !== $course->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Course not found', 'learnkit' ) ),
				404
			);
		}

		// Non-admins can only enroll themselves in free courses.
		$current_user_id = get_current_user_id();
		if ( ! current_user_can( 'edit_posts' ) ) {
			if ( $current_user_id !== $user_id ) {
				return new WP_REST_Response(
					array( 'message' => __( 'You can only enroll yourself.', 'learnkit' ) ),
					403
				);
			}
			$access_type = get_post_meta( $course_id, '_lk_access_type', true );
			if ( empty( $access_type ) ) {
				$self_enrollment = get_post_meta( $course_id, '_lk_self_enrollment', true );
				$access_type     = $self_enrollment ? 'free' : 'free';
			}
			if ( 'free' !== $access_type ) {
				return new WP_REST_Response(
					array( 'message' => __( 'This course requires purchase to enroll.', 'learnkit' ) ),
					403
				);
			}
		}

		return true;
	}

	/**
	 * Insert a new enrollment record, checking for duplicates first.
	 *
	 * @since    0.2.14
	 * @param    int $user_id   User ID.
	 * @param    int $course_id Course ID.
	 * @return   WP_REST_Response Response object.
	 */
	private function insert_enrollment( $user_id, $course_id ) {
		$result = learnkit_enroll_user( $user_id, $course_id, 'manual' );
		if ( ! $result ) {
			return new WP_REST_Response( array( 'message' => 'Enrollment failed.' ), 500 );
		}
		return new WP_REST_Response( array( 'message' => 'Enrolled successfully.' ), 201 );
	}

	/**
	 * Delete enrollment.
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function delete_enrollment( $request ) {
		global $wpdb;

		$enrollment_id = (int) $request['id'];
		$table         = $wpdb->prefix . 'learnkit_enrollments';

		$deleted = $wpdb->delete(
			$table,
			array( 'id' => $enrollment_id ),
			array( '%d' )
		);

		if ( false === $deleted || 0 === $deleted ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to delete enrollment', 'learnkit' ) ),
				500
			);
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Enrollment deleted successfully', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Get all enrollments for a course.
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_course_enrollments( $request ) {
		global $wpdb;

		$course_id = (int) $request['course_id'];
		$table     = $wpdb->prefix . 'learnkit_enrollments';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$enrollments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE course_id = %d ORDER BY enrolled_at DESC",
				$course_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Enrich with user data.
		foreach ( $enrollments as &$enrollment ) {
			$user = get_user_by( 'id', $enrollment['user_id'] );
			if ( $user ) {
				$enrollment['user_name']  = $user->display_name;
				$enrollment['user_email'] = $user->user_email;
			}
		}

		return new WP_REST_Response( $enrollments, 200 );
	}

	/**
	 * Get all enrollments for a user.
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_user_enrollments( $request ) {
		global $wpdb;

		$user_id = (int) $request['user_id'];
		$table   = $wpdb->prefix . 'learnkit_enrollments';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$enrollments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d ORDER BY enrolled_at DESC",
				$user_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Enrich with course data.
		foreach ( $enrollments as &$enrollment ) {
			$course = get_post( $enrollment['course_id'] );
			if ( $course ) {
				$enrollment['course_title'] = $course->post_title;
				$enrollment['course_url']   = get_permalink( $course->ID );
			}
		}

		return new WP_REST_Response( $enrollments, 200 );
	}

	/**
	 * Check enrollment permission — logged-in users can attempt self-enrollment.
	 *
	 * @since    0.3.3
	 * @return   bool True if user is logged in.
	 */
	public function check_enrollment_permission() {
		return is_user_logged_in();
	}

	/**
	 * Check admin permission.
	 *
	 * @since    0.2.14
	 * @return   bool True if user can edit posts.
	 */
	public function check_admin_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check user permission (self or admin).
	 *
	 * @since    0.2.14
	 * @param    WP_REST_Request $request Full request data.
	 * @return   bool True if user can access.
	 */
	public function check_user_permission( $request ) {
		$user_id         = (int) $request['user_id'];
		$current_user_id = get_current_user_id();

		return $current_user_id === $user_id || current_user_can( 'edit_posts' );
	}
}
