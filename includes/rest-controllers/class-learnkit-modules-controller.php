<?php
/**
 * REST API: Modules Controller
 *
 * @link       https://jameswelbes.com
 * @since      0.2.13
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 */

/**
 * Modules REST API controller.
 *
 * Handles all module-related endpoints.
 *
 * @since      0.2.13
 * @package    LearnKit
 * @subpackage LearnKit/includes/rest-controllers
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Modules_Controller {

	/**
	 * The namespace for our REST API.
	 *
	 * @since    0.2.13
	 * @access   private
	 * @var      string    $namespace    The namespace for REST API routes.
	 */
	private $namespace = 'learnkit/v1';

	/**
	 * Register module routes.
	 *
	 * @since    0.2.13
	 */
	public function register_routes() {
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

		register_rest_route(
			$this->namespace,
			'/courses/(?P<id>\d+)/reorder-modules',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'reorder_modules' ),
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
	 * Get all modules.
	 *
	 * @since    0.2.13
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
	 * Get single module.
	 *
	 * @since    0.2.13
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
	 * Create module.
	 *
	 * @since    0.2.13
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

		if ( ! empty( $request['course_id'] ) ) {
			update_post_meta( $module_id, '_lk_course_id', (int) $request['course_id'] );
		}

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
	 * Update module.
	 *
	 * @since    0.2.13
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

		if ( ! empty( $request['course_id'] ) ) {
			update_post_meta( $module_id, '_lk_course_id', (int) $request['course_id'] );
		}

		return new WP_REST_Response(
			array( 'message' => __( 'Module updated successfully', 'learnkit' ) ),
			200
		);
	}

	/**
	 * Delete module.
	 *
	 * @since    0.2.13
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
	 * Reorder modules.
	 *
	 * @since    0.2.13
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function reorder_modules( $request ) {
		$order     = $request['order'];
		$course_id = (int) $request['id'];

		foreach ( $order as $index => $module_id ) {
			$module_id = (int) $module_id;
			$module    = get_post( $module_id );
			if ( ! $module || 'lk_module' !== $module->post_type ) {
				continue;
			}
			// Verify it belongs to the course in the URL.
			if ( (int) get_post_meta( $module_id, '_lk_course_id', true ) !== $course_id ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $module_id ) ) {
				continue;
			}
			wp_update_post(
				array(
					'ID'         => $module_id,
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
	 * Prepare module data for API response.
	 *
	 * @since    0.2.13
	 * @param    WP_Post $module Module post object.
	 * @return   array Module data.
	 */
	private function prepare_module_response( $module ) {
		return array(
			'id'             => $module->ID,
			'title'          => $module->post_title,
			'content'        => $module->post_content,
			'excerpt'        => $module->post_excerpt,
			'status'         => $module->post_status,
			'author'         => $module->post_author,
			'date_created'   => $module->post_date,
			'date_modified'  => $module->post_modified,
			'menu_order'     => $module->menu_order,
			'course_id'      => get_post_meta( $module->ID, '_lk_course_id', true ),
			'permalink'      => get_permalink( $module->ID ),
			'edit_link'      => get_edit_post_link( $module->ID, 'raw' ),
		);
	}

	/**
	 * Get endpoint arguments for module creation/update.
	 *
	 * @since    0.2.13
	 * @return   array Endpoint arguments.
	 */
	private function get_module_args() {
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
			'course_id' => array(
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
	 * @param    WP_REST_Request $request Full request data.
	 * @return   bool True if user can write.
	 */
	public function check_write_permission( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id ) {
			return current_user_can( 'edit_post', $id );
		}
		return current_user_can( 'edit_posts' );
	}
}
