<?php
/**
 * REST API endpoints for LearnKit
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

/**
 * REST API endpoints for LearnKit.
 *
 * Defines REST API routes for courses, modules, lessons, enrollments, and progress.
 * API-first architecture: all admin UI functionality is built on these endpoints.
 *
 * @since      0.1.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_REST_API {

	/**
	 * The namespace for our REST API.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $namespace    The namespace for REST API routes.
	 */
	private $namespace = 'learnkit/v1';

	/**
	 * Register REST API routes.
	 *
	 * @since    0.1.0
	 */
	public function register_routes() {
		// Course endpoints.
		register_rest_route(
			$this->namespace,
			'/courses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_course' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_course_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_course' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_course' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_course_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_course' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
				),
			)
		);

		// Module endpoints.
		register_rest_route(
			$this->namespace,
			'/modules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_modules' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
			)
		);

		// Lesson endpoints.
		register_rest_route(
			$this->namespace,
			'/lessons',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_lessons' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
			)
		);

		// Enrollment endpoints (Sprint 3).
		register_rest_route(
			$this->namespace,
			'/enrollments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_enrollments' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Progress endpoints (Sprint 3).
		register_rest_route(
			$this->namespace,
			'/progress',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_progress' ),
					'permission_callback' => '__return_true', // Logged-in users only (checked in callback).
				),
			)
		);
	}

	/**
	 * Get all courses.
	 *
	 * @since    0.1.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_courses( $request ) {
		$args = array(
			'post_type'      => 'lk_course',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		$courses = get_posts( $args );
		$data    = array();

		foreach ( $courses as $course ) {
			$data[] = $this->prepare_course_response( $course );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get single course by ID.
	 *
	 * @since    0.1.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_course( $request ) {
		$course_id = (int) $request['id'];
		$course    = get_post( $course_id );

		if ( ! $course || 'lk_course' !== $course->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Course not found', 'learnkit' ) ),
				404
			);
		}

		return new WP_REST_Response( $this->prepare_course_response( $course ), 200 );
	}

	/**
	 * Create new course.
	 *
	 * @since    0.1.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function create_course( $request ) {
		$course_data = array(
			'post_title'   => sanitize_text_field( $request['title'] ),
			'post_content' => wp_kses_post( $request['content'] ),
			'post_excerpt' => sanitize_textarea_field( $request['excerpt'] ),
			'post_status'  => 'publish',
			'post_type'    => 'lk_course',
			'post_author'  => get_current_user_id(),
		);

		$course_id = wp_insert_post( $course_data );

		if ( is_wp_error( $course_id ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to create course', 'learnkit' ) ),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'message'   => __( 'Course created successfully', 'learnkit' ),
				'course_id' => $course_id,
			),
			201
		);
	}

	/**
	 * Update existing course.
	 *
	 * @since    0.1.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function update_course( $request ) {
		$course_id = (int) $request['id'];
		$course    = get_post( $course_id );

		if ( ! $course || 'lk_course' !== $course->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Course not found', 'learnkit' ) ),
				404
			);
		}

		$course_data = array(
			'ID'           => $course_id,
			'post_title'   => sanitize_text_field( $request['title'] ),
			'post_content' => wp_kses_post( $request['content'] ),
			'post_excerpt' => sanitize_textarea_field( $request['excerpt'] ),
		);

		$updated = wp_update_post( $course_data );

		if ( is_wp_error( $updated ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to update course', 'learnkit' ) ),
				500
			);
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Course updated successfully', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Delete course.
	 *
	 * @since    0.1.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function delete_course( $request ) {
		$course_id = (int) $request['id'];
		$course    = get_post( $course_id );

		if ( ! $course || 'lk_course' !== $course->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Course not found', 'learnkit' ) ),
				404
			);
		}

		$deleted = wp_delete_post( $course_id, true );

		if ( ! $deleted ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to delete course', 'learnkit' ) ),
				500
			);
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Course deleted successfully', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Get modules (stub for Sprint 2).
	 *
	 * @since    0.1.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_modules( $request ) {
		return new WP_REST_Response(
			array( 'message' => __( 'Module endpoints coming in Sprint 2', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Get lessons (stub for Sprint 2).
	 *
	 * @since    0.1.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_lessons( $request ) {
		return new WP_REST_Response(
			array( 'message' => __( 'Lesson endpoints coming in Sprint 2', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Get enrollments (stub for Sprint 3).
	 *
	 * @since    0.1.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_enrollments( $request ) {
		return new WP_REST_Response(
			array( 'message' => __( 'Enrollment endpoints coming in Sprint 3', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Get progress (stub for Sprint 3).
	 *
	 * @since    0.1.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_progress( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Authentication required', 'learnkit' ) ),
				401
			);
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Progress endpoints coming in Sprint 3', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Prepare course data for API response.
	 *
	 * @since    0.1.0
	 * @param    WP_Post $course Course post object.
	 * @return   array Course data.
	 */
	private function prepare_course_response( $course ) {
		return array(
			'id'            => $course->ID,
			'title'         => $course->post_title,
			'content'       => $course->post_content,
			'excerpt'       => $course->post_excerpt,
			'status'        => $course->post_status,
			'author'        => $course->post_author,
			'date_created'  => $course->post_date,
			'date_modified' => $course->post_modified,
			'permalink'     => get_permalink( $course->ID ),
			'featured_image' => get_the_post_thumbnail_url( $course->ID, 'large' ),
		);
	}

	/**
	 * Get endpoint arguments for course creation/update.
	 *
	 * @since    0.1.0
	 * @return   array Endpoint arguments.
	 */
	private function get_course_args() {
		return array(
			'title'   => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content' => array(
				'required'          => false,
				'sanitize_callback' => 'wp_kses_post',
			),
			'excerpt' => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}

	/**
	 * Check if user can read courses.
	 *
	 * @since    0.1.0
	 * @return   bool Whether user has permission.
	 */
	public function check_read_permission() {
		return current_user_can( 'read' );
	}

	/**
	 * Check if user can create/edit courses.
	 *
	 * @since    0.1.0
	 * @return   bool Whether user has permission.
	 */
	public function check_write_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if user is administrator.
	 *
	 * @since    0.1.0
	 * @return   bool Whether user has permission.
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}
}
