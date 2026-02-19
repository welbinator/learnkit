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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_module' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_module_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/modules/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_module' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_module' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_module_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_module' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_lesson' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_lesson_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/lessons/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_lesson' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_lesson' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_lesson_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_lesson' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
				),
			)
		);

		// Course structure endpoint (tree view).
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/structure',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_course_structure' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
			)
		);

		// Reorder modules within a course.
		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/reorder-modules',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'reorder_modules' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(
						'order' => array(
							'required'          => true,
							'type'              => 'array',
							'description'       => __( 'Array of module IDs in desired order', 'learnkit' ),
						),
					),
				),
			)
		);

		// Reorder lessons within a module.
		register_rest_route(
			$this->namespace,
			'/modules/(?P<id>\d+)/reorder-lessons',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'reorder_lessons' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(
						'order' => array(
							'required'          => true,
							'type'              => 'array',
							'description'       => __( 'Array of lesson IDs in desired order', 'learnkit' ),
						),
					),
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
	 * Get all modules.
	 *
	 * Supports filtering by course_id query parameter.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_modules( $request ) {
		$args = array(
			'post_type'      => 'lk_module',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		// Filter by course ID if provided.
		if ( ! empty( $request['course_id'] ) ) {
			$args['meta_key']   = '_lk_course_id';
			$args['meta_value'] = (int) $request['course_id'];
		}

		$modules = get_posts( $args );
		$data    = array();

		foreach ( $modules as $module ) {
			$data[] = $this->prepare_module_response( $module );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get single module by ID.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_module( $request ) {
		$module_id = (int) $request['id'];
		$module    = get_post( $module_id );

		if ( ! $module || 'lk_module' !== $module->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Module not found', 'learnkit' ) ),
				404
			);
		}

		return new WP_REST_Response( $this->prepare_module_response( $module ), 200 );
	}

	/**
	 * Create new module.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function create_module( $request ) {
		$module_data = array(
			'post_title'   => sanitize_text_field( $request['title'] ),
			'post_content' => wp_kses_post( $request['content'] ?? '' ),
			'post_excerpt' => sanitize_textarea_field( $request['excerpt'] ?? '' ),
			'post_status'  => 'publish',
			'post_type'    => 'lk_module',
			'post_author'  => get_current_user_id(),
		);

		$module_id = wp_insert_post( $module_data );

		if ( is_wp_error( $module_id ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to create module', 'learnkit' ) ),
				500
			);
		}

		// Set course relationship.
		if ( ! empty( $request['course_id'] ) ) {
			update_post_meta( $module_id, '_lk_course_id', (int) $request['course_id'] );
		}

		// Set menu order if provided.
		if ( isset( $request['menu_order'] ) ) {
			wp_update_post(
				array(
					'ID'         => $module_id,
					'menu_order' => (int) $request['menu_order'],
				)
			);
		}

		return new WP_REST_Response(
			array(
				'message'   => __( 'Module created successfully', 'learnkit' ),
				'module_id' => $module_id,
				'module'    => $this->prepare_module_response( get_post( $module_id ) ),
			),
			201
		);
	}

	/**
	 * Update existing module.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function update_module( $request ) {
		$module_id = (int) $request['id'];
		$module    = get_post( $module_id );

		if ( ! $module || 'lk_module' !== $module->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Module not found', 'learnkit' ) ),
				404
			);
		}

		$module_data = array(
			'ID' => $module_id,
		);

		if ( isset( $request['title'] ) ) {
			$module_data['post_title'] = sanitize_text_field( $request['title'] );
		}

		if ( isset( $request['content'] ) ) {
			$module_data['post_content'] = wp_kses_post( $request['content'] );
		}

		if ( isset( $request['excerpt'] ) ) {
			$module_data['post_excerpt'] = sanitize_textarea_field( $request['excerpt'] );
		}

		if ( isset( $request['menu_order'] ) ) {
			$module_data['menu_order'] = (int) $request['menu_order'];
		}

		$updated = wp_update_post( $module_data );

		if ( is_wp_error( $updated ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to update module', 'learnkit' ) ),
				500
			);
		}

		// Update course relationship if provided.
		if ( isset( $request['course_id'] ) ) {
			update_post_meta( $module_id, '_lk_course_id', (int) $request['course_id'] );
		}

		return new WP_REST_Response(
			array(
				'message' => __( 'Module updated successfully', 'learnkit' ),
				'module'  => $this->prepare_module_response( get_post( $module_id ) ),
			),
			200
		);
	}

	/**
	 * Delete module.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function delete_module( $request ) {
		$module_id = (int) $request['id'];
		$module    = get_post( $module_id );

		if ( ! $module || 'lk_module' !== $module->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Module not found', 'learnkit' ) ),
				404
			);
		}

		$deleted = wp_delete_post( $module_id, true );

		if ( ! $deleted ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to delete module', 'learnkit' ) ),
				500
			);
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Module deleted successfully', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Get all lessons.
	 *
	 * Supports filtering by module_id query parameter.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_lessons( $request ) {
		$args = array(
			'post_type'      => 'lk_lesson',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		// Filter by module ID if provided.
		if ( ! empty( $request['module_id'] ) ) {
			$args['meta_key']   = '_lk_module_id';
			$args['meta_value'] = (int) $request['module_id'];
		}

		$lessons = get_posts( $args );
		$data    = array();

		foreach ( $lessons as $lesson ) {
			$data[] = $this->prepare_lesson_response( $lesson );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get single lesson by ID.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function get_lesson( $request ) {
		$lesson_id = (int) $request['id'];
		$lesson    = get_post( $lesson_id );

		if ( ! $lesson || 'lk_lesson' !== $lesson->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Lesson not found', 'learnkit' ) ),
				404
			);
		}

		return new WP_REST_Response( $this->prepare_lesson_response( $lesson ), 200 );
	}

	/**
	 * Create new lesson.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function create_lesson( $request ) {
		$lesson_data = array(
			'post_title'   => sanitize_text_field( $request['title'] ),
			'post_content' => wp_kses_post( $request['content'] ?? '' ),
			'post_excerpt' => sanitize_textarea_field( $request['excerpt'] ?? '' ),
			'post_status'  => 'publish',
			'post_type'    => 'lk_lesson',
			'post_author'  => get_current_user_id(),
		);

		$lesson_id = wp_insert_post( $lesson_data );

		if ( is_wp_error( $lesson_id ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to create lesson', 'learnkit' ) ),
				500
			);
		}

		// Set module relationship.
		if ( ! empty( $request['module_id'] ) ) {
			update_post_meta( $lesson_id, '_lk_module_id', (int) $request['module_id'] );
		}

		// Set menu order if provided.
		if ( isset( $request['menu_order'] ) ) {
			wp_update_post(
				array(
					'ID'         => $lesson_id,
					'menu_order' => (int) $request['menu_order'],
				)
			);
		}

		return new WP_REST_Response(
			array(
				'message'   => __( 'Lesson created successfully', 'learnkit' ),
				'lesson_id' => $lesson_id,
				'lesson'    => $this->prepare_lesson_response( get_post( $lesson_id ) ),
			),
			201
		);
	}

	/**
	 * Update existing lesson.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function update_lesson( $request ) {
		$lesson_id = (int) $request['id'];
		$lesson    = get_post( $lesson_id );

		if ( ! $lesson || 'lk_lesson' !== $lesson->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Lesson not found', 'learnkit' ) ),
				404
			);
		}

		$lesson_data = array(
			'ID' => $lesson_id,
		);

		if ( isset( $request['title'] ) ) {
			$lesson_data['post_title'] = sanitize_text_field( $request['title'] );
		}

		if ( isset( $request['content'] ) ) {
			$lesson_data['post_content'] = wp_kses_post( $request['content'] );
		}

		if ( isset( $request['excerpt'] ) ) {
			$lesson_data['post_excerpt'] = sanitize_textarea_field( $request['excerpt'] );
		}

		if ( isset( $request['menu_order'] ) ) {
			$lesson_data['menu_order'] = (int) $request['menu_order'];
		}

		$updated = wp_update_post( $lesson_data );

		if ( is_wp_error( $updated ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to update lesson', 'learnkit' ) ),
				500
			);
		}

		// Update module relationship if provided.
		if ( isset( $request['module_id'] ) ) {
			update_post_meta( $lesson_id, '_lk_module_id', (int) $request['module_id'] );
		}

		return new WP_REST_Response(
			array(
				'message' => __( 'Lesson updated successfully', 'learnkit' ),
				'lesson'  => $this->prepare_lesson_response( get_post( $lesson_id ) ),
			),
			200
		);
	}

	/**
	 * Delete lesson.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function delete_lesson( $request ) {
		$lesson_id = (int) $request['id'];
		$lesson    = get_post( $lesson_id );

		if ( ! $lesson || 'lk_lesson' !== $lesson->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Lesson not found', 'learnkit' ) ),
				404
			);
		}

		$deleted = wp_delete_post( $lesson_id, true );

		if ( ! $deleted ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Failed to delete lesson', 'learnkit' ) ),
				500
			);
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Lesson deleted successfully', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Get complete course structure with modules and lessons.
	 *
	 * Returns hierarchical tree: course -> modules -> lessons.
	 *
	 * @since    0.2.0
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
		$modules = get_posts(
			array(
				'post_type'      => 'lk_module',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'meta_key'       => '_lk_course_id',
				'meta_value'     => $course_id,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$structure = array(
			'course'  => $this->prepare_course_response( $course ),
			'modules' => array(),
		);

		foreach ( $modules as $module ) {
			// Get all lessons for this module.
			$lessons = get_posts(
				array(
					'post_type'      => 'lk_lesson',
					'posts_per_page' => -1,
					'post_status'    => array( 'publish', 'draft' ),
					'meta_key'       => '_lk_module_id',
					'meta_value'     => $module->ID,
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
				)
			);

			$module_data = $this->prepare_module_response( $module );
			$module_data['lessons'] = array();

			foreach ( $lessons as $lesson ) {
				$module_data['lessons'][] = $this->prepare_lesson_response( $lesson );
			}

			$structure['modules'][] = $module_data;
		}

		return new WP_REST_Response( $structure, 200 );
	}

	/**
	 * Reorder modules within a course.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function reorder_modules( $request ) {
		$course_id = (int) $request['id'];
		$order     = $request['order'];

		if ( ! is_array( $order ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Order must be an array', 'learnkit' ) ),
				400
			);
		}

		foreach ( $order as $index => $module_id ) {
			wp_update_post(
				array(
					'ID'         => (int) $module_id,
					'menu_order' => $index,
				)
			);
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Modules reordered successfully', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Reorder lessons within a module.
	 *
	 * @since    0.2.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function reorder_lessons( $request ) {
		$module_id = (int) $request['id'];
		$order     = $request['order'];

		if ( ! is_array( $order ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Order must be an array', 'learnkit' ) ),
				400
			);
		}

		foreach ( $order as $index => $lesson_id ) {
			wp_update_post(
				array(
					'ID'         => (int) $lesson_id,
					'menu_order' => $index,
				)
			);
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Lessons reordered successfully', 'learnkit' ) ),
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
			'edit_link'     => get_edit_post_link( $course->ID, 'raw' ),
		);
	}

	/**
	 * Prepare module data for API response.
	 *
	 * @since    0.2.0
	 * @param    WP_Post $module Module post object.
	 * @return   array Module data.
	 */
	private function prepare_module_response( $module ) {
		return array(
			'id'            => $module->ID,
			'title'         => $module->post_title,
			'content'       => $module->post_content,
			'excerpt'       => $module->post_excerpt,
			'status'        => $module->post_status,
			'author'        => $module->post_author,
			'date_created'  => $module->post_date,
			'date_modified' => $module->post_modified,
			'menu_order'    => $module->menu_order,
			'course_id'     => get_post_meta( $module->ID, '_lk_course_id', true ),
			'permalink'     => get_permalink( $module->ID ),
			'edit_link'     => get_edit_post_link( $module->ID, 'raw' ),
		);
	}

	/**
	 * Prepare lesson data for API response.
	 *
	 * @since    0.2.0
	 * @param    WP_Post $lesson Lesson post object.
	 * @return   array Lesson data.
	 */
	private function prepare_lesson_response( $lesson ) {
		return array(
			'id'            => $lesson->ID,
			'title'         => $lesson->post_title,
			'content'       => $lesson->post_content,
			'excerpt'       => $lesson->post_excerpt,
			'status'        => $lesson->post_status,
			'author'        => $lesson->post_author,
			'date_created'  => $lesson->post_date,
			'date_modified' => $lesson->post_modified,
			'menu_order'    => $lesson->menu_order,
			'module_id'     => get_post_meta( $lesson->ID, '_lk_module_id', true ),
			'permalink'     => get_permalink( $lesson->ID ),
			'edit_link'     => get_edit_post_link( $lesson->ID, 'raw' ),
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
	 * Get endpoint arguments for module creation/update.
	 *
	 * @since    0.2.0
	 * @return   array Endpoint arguments.
	 */
	private function get_module_args() {
		return array(
			'title'      => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'    => array(
				'required'          => false,
				'sanitize_callback' => 'wp_kses_post',
			),
			'excerpt'    => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'course_id'  => array(
				'required'          => false,
				'sanitize_callback' => 'absint',
			),
			'menu_order' => array(
				'required'          => false,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get endpoint arguments for lesson creation/update.
	 *
	 * @since    0.2.0
	 * @return   array Endpoint arguments.
	 */
	private function get_lesson_args() {
		return array(
			'title'      => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'    => array(
				'required'          => false,
				'sanitize_callback' => 'wp_kses_post',
			),
			'excerpt'    => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'module_id'  => array(
				'required'          => false,
				'sanitize_callback' => 'absint',
			),
			'menu_order' => array(
				'required'          => false,
				'sanitize_callback' => 'absint',
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
