<?php
/**
 * REST API: Lessons Controller
 *
 * @link       https://jameswelbes.com
 * @since      0.2.13
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 */

/**
 * Lessons REST API controller.
 *
 * Handles all lesson-related endpoints.
 *
 * @since      0.2.13
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Lessons_Controller {

	/**
	 * The namespace for our REST API.
	 *
	 * @since    0.2.13
	 * @access   private
	 * @var      string    $namespace    The namespace for REST API routes.
	 */
	private $namespace = 'learnkit/v1';

	/**
	 * Register lesson routes.
	 *
	 * @since    0.2.13
	 */
	public function register_routes() {
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

		register_rest_route(
			$this->namespace,
			'/modules/(?P<id>\d+)/lessons',
			array(
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

		register_rest_route(
			$this->namespace,
			'/modules/(?P<id>\d+)/reorder-lessons',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'reorder_lessons' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'args'                => array(
					'order' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Get all lessons.
	 *
	 * @since    0.2.13
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
	 * Get single lesson.
	 *
	 * @since    0.2.13
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
	 * Create lesson.
	 *
	 * @since    0.2.13
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function create_lesson( $request ) {
		$module_id = (int) $request['id'];

		$lesson_data = array(
			'post_title'   => sanitize_text_field( $request['title'] ),
			'post_content' => '',
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

		update_post_meta( $lesson_id, '_lk_module_id', $module_id );

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
	 * Update lesson.
	 *
	 * @since    0.2.13
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

		$lesson_data = array( 'ID' => $lesson_id );

		if ( isset( $request['title'] ) ) {
			$lesson_data['post_title'] = sanitize_text_field( $request['title'] );
		}
		if ( isset( $request['content'] ) ) {
			$lesson_data['post_content'] = wp_kses_post( $request['content'] );
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

		$this->update_lesson_meta( $lesson_id, $request );

		return new WP_REST_Response(
			array( 'message' => __( 'Lesson updated successfully', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Update lesson meta fields from request data.
	 *
	 * @since    0.2.13
	 * @param    int             $lesson_id Lesson post ID.
	 * @param    WP_REST_Request $request   Full request data.
	 */
	private function update_lesson_meta( $lesson_id, $request ) {
		if ( ! empty( $request['module_id'] ) ) {
			update_post_meta( $lesson_id, '_lk_module_id', (int) $request['module_id'] );
		}

		// Drip content meta.
		if ( isset( $request['release_type'] ) ) {
			$allowed_types = array( 'immediate', 'days_after_enrollment', 'specific_date' );
			$release_type  = in_array( $request['release_type'], $allowed_types, true ) ? $request['release_type'] : 'immediate';
			update_post_meta( $lesson_id, '_lk_release_type', $release_type );
		}
		if ( isset( $request['release_days'] ) ) {
			update_post_meta( $lesson_id, '_lk_release_days', max( 0, (int) $request['release_days'] ) );
		}
		if ( isset( $request['release_date'] ) ) {
			update_post_meta( $lesson_id, '_lk_release_date', sanitize_text_field( $request['release_date'] ) );
		}
	}

	/**
	 * Delete lesson.
	 *
	 * @since    0.2.13
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
	 * Reorder lessons.
	 *
	 * @since    0.2.13
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function reorder_lessons( $request ) {
		$order = $request['order'];

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
	 * Prepare lesson data for API response.
	 *
	 * @since    0.2.13
	 * @param    WP_Post $lesson Lesson post object.
	 * @return   array Lesson data.
	 */
	private function prepare_lesson_response( $lesson ) {
		return array(
			'id'             => $lesson->ID,
			'title'          => $lesson->post_title,
			'content'        => $lesson->post_content,
			'status'         => $lesson->post_status,
			'author'         => $lesson->post_author,
			'date_created'   => $lesson->post_date,
			'date_modified'  => $lesson->post_modified,
			'menu_order'     => $lesson->menu_order,
			'module_id'      => get_post_meta( $lesson->ID, '_lk_module_id', true ),
			'permalink'      => get_permalink( $lesson->ID ),
			'edit_link'      => get_edit_post_link( $lesson->ID, 'raw' ),
			'release_type'   => get_post_meta( $lesson->ID, '_lk_release_type', true ) ? get_post_meta( $lesson->ID, '_lk_release_type', true ) : 'immediate',
			'release_days'   => (int) get_post_meta( $lesson->ID, '_lk_release_days', true ),
			'release_date'   => get_post_meta( $lesson->ID, '_lk_release_date', true ),
		);
	}

	/**
	 * Get endpoint arguments for lesson creation/update.
	 *
	 * @since    0.2.13
	 * @return   array Endpoint arguments.
	 */
	private function get_lesson_args() {
		return array(
			'title'   => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content' => array(
				'sanitize_callback' => 'wp_kses_post',
			),
			'module_id' => array(
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
			),
			'menu_order' => array(
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
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
