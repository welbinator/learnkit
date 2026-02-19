<?php
/**
 * REST API: Courses Controller
 *
 * @link       https://jameswelbes.com
 * @since      0.2.13
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 */

/**
 * Courses REST API controller.
 *
 * Handles all course-related endpoints.
 *
 * @since      0.2.13
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Courses_Controller {

	/**
	 * The namespace for our REST API.
	 *
	 * @since    0.2.13
	 * @access   private
	 * @var      string    $namespace    The namespace for REST API routes.
	 */
	private $namespace = 'learnkit/v1';

	/**
	 * Register course routes.
	 *
	 * @since    0.2.13
	 */
	public function register_routes() {
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

		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/structure',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_course_structure' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
			)
		);
	}

	/**
	 * Get all courses.
	 *
	 * @since    0.2.13
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_courses( $request ) {
		$args = array(
			'post_type'      => 'lk_course',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
		);

		$courses = get_posts( $args );
		$data    = array();

		foreach ( $courses as $course ) {
			$data[] = $this->prepare_course_response( $course );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get single course.
	 *
	 * @since    0.2.13
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
	 * Create course.
	 *
	 * @since    0.2.13
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function create_course( $request ) {
		$course_data = array(
			'post_type'   => 'lk_course',
			'post_title'  => sanitize_text_field( $request['title'] ),
			'post_status' => 'draft',
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
				'message' => __( 'Course created successfully', 'learnkit' ),
				'status'  => 'success',
				'id'      => $course_id,
			),
			201
		);
	}

	/**
	 * Update course.
	 *
	 * @since    0.2.13
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
			'ID' => $course_id,
		);

		if ( isset( $request['title'] ) ) {
			$course_data['post_title'] = sanitize_text_field( $request['title'] );
		}

		if ( isset( $request['content'] ) ) {
			$course_data['post_content'] = wp_kses_post( $request['content'] );
		}

		if ( isset( $request['excerpt'] ) ) {
			$course_data['post_excerpt'] = sanitize_textarea_field( $request['excerpt'] );
		}

		$updated = wp_update_post( $course_data );

		if ( is_wp_error( $updated ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to update course', 'learnkit' ) ),
				500
			);
		}

		// Handle featured image if provided.
		if ( isset( $request['featured_image_url'] ) ) {
			$image_url = $request['featured_image_url'];

			if ( empty( $image_url ) ) {
				// Remove featured image.
				delete_post_thumbnail( $course_id );
			} else {
				// Get attachment ID from URL.
				$attachment_id = attachment_url_to_postid( $image_url );
				if ( $attachment_id ) {
					set_post_thumbnail( $course_id, $attachment_id );
				}
			}
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Course updated successfully', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Delete course.
	 *
	 * @since    0.2.13
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
	 * Get course structure (modules and lessons).
	 *
	 * @since    0.2.13
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_course_structure( $request ) {
		$course_id = (int) $request['id'];
		$course    = get_post( $course_id );

		if ( ! $course || 'lk_course' !== $course->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Course not found', 'learnkit' ) ),
				404
			);
		}

		// Get all modules for this course.
		$modules_query = new WP_Query(
			array(
				'post_type'      => 'lk_module',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => 'learnkit_course_id',
				'meta_value'     => $course_id,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$modules = array();

		foreach ( $modules_query->posts as $module ) {
			// Get lessons for this module.
			$lessons_query = new WP_Query(
				array(
					'post_type'      => 'lk_lesson',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_key'       => 'learnkit_module_id',
					'meta_value'     => $module->ID,
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
				)
			);

			$lessons = array();
			foreach ( $lessons_query->posts as $lesson ) {
				$lessons[] = array(
					'id'    => $lesson->ID,
					'title' => $lesson->post_title,
					'order' => $lesson->menu_order,
				);
			}

			$modules[] = array(
				'id'      => $module->ID,
				'title'   => $module->post_title,
				'order'   => $module->menu_order,
				'lessons' => $lessons,
			);
		}

		return new WP_REST_Response(
			array(
				'id'      => $course_id,
				'title'   => $course->post_title,
				'modules' => $modules,
			),
			200
		);
	}

	/**
	 * Prepare course data for API response.
	 *
	 * @since    0.2.13
	 * @param    WP_Post $course Course post object.
	 * @return   array Course data.
	 */
	private function prepare_course_response( $course ) {
		return array(
			'id'             => $course->ID,
			'title'          => $course->post_title,
			'content'        => $course->post_content,
			'excerpt'        => $course->post_excerpt,
			'status'         => $course->post_status,
			'author'         => $course->post_author,
			'date_created'   => $course->post_date,
			'date_modified'  => $course->post_modified,
			'permalink'      => get_permalink( $course->ID ),
			'featured_image' => get_the_post_thumbnail_url( $course->ID, 'large' ),
			'edit_link'      => get_edit_post_link( $course->ID, 'raw' ),
		);
	}

	/**
	 * Get endpoint arguments for course creation/update.
	 *
	 * @since    0.2.13
	 * @return   array Endpoint arguments.
	 */
	private function get_course_args() {
		return array(
			'title'   => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content' => array(
				'sanitize_callback' => 'wp_kses_post',
			),
			'excerpt' => array(
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'featured_image_url' => array(
				'sanitize_callback' => 'esc_url_raw',
			),
		);
	}

	/**
	 * Check read permission.
	 *
	 * @since    0.2.13
	 * @return   bool True if user can read.
	 */
	public function check_read_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check write permission.
	 *
	 * @since    0.2.13
	 * @return   bool True if user can write.
	 */
	public function check_write_permission() {
		return current_user_can( 'edit_posts' );
	}
}
