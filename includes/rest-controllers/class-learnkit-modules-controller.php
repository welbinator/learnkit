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
class LearnKit_Modules_Controller extends LearnKit_Base_Controller {

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
					'args'                => array(
						'course_id' => array(
							'type'              => 'integer',
							'description'       => __( 'Filter modules by course ID.', 'learnkit' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_module' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array_merge(
						$this->get_module_args(),
						array(
							'title' => array(
								'required'          => true,
								'type'              => 'string',
								'description'       => __( 'Module title.', 'learnkit' ),
								'sanitize_callback' => 'sanitize_text_field',
							),
						)
					),
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
			'/modules/(?P<id>\d+)/assign-course',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'assign_course' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(
						'course_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Course ID to assign this module to.', 'learnkit' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_course' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(
						'course_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Course ID to remove this module from.', 'learnkit' ),
							'sanitize_callback' => 'absint',
						),
					),
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
			$args['meta_key']   = '_lk_course_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional meta query; indexed by plugin on activation.
			$args['meta_value'] = (int) $request['course_id']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
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
	 * Accepts either `course_id` (single integer, backwards compat) or
	 * `course_ids` (array of integers) to assign the new module to one or
	 * more courses at creation time.
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

		// Resolve course IDs — accept course_ids array or fall back to single course_id.
		$course_ids = $this->resolve_course_ids( $request );

		if ( ! empty( $course_ids ) ) {
			// Delete any existing rows first (clean slate), then add each.
			delete_post_meta( $module_id, '_lk_course_id' );
			foreach ( $course_ids as $course_id ) {
				add_post_meta( $module_id, '_lk_course_id', $course_id, false ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional many-to-many; multiple rows supported.
			}
		}

		// Place at end of each assigned course's module list.
		if ( isset( $request['menu_order'] ) ) {
			$new_order = (int) $request['menu_order'];
		} elseif ( ! empty( $course_ids ) ) {
			$course_id        = $course_ids[0];
			$existing_modules = get_posts( array(
				'post_type'      => 'lk_module',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_lk_course_id',
						'value'   => $course_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
				'fields'         => 'ids',
			) );
			$max_order = 0;
			foreach ( $existing_modules as $mid ) {
				if ( (int) $mid === $module_id ) {
					continue;
				}
				$order = (int) get_post_field( 'menu_order', $mid );
				if ( $order > $max_order ) {
					$max_order = $order;
				}
			}
			$new_order = $max_order + 1;
		} else {
			$new_order = 0;
		}
		wp_update_post( array( 'ID' => $module_id, 'menu_order' => $new_order ) );

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
	 * Accepts either `course_id` (single integer, backwards compat) or
	 * `course_ids` (array of integers).  When provided, existing course
	 * assignments are replaced atomically (delete all, re-add).
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

		// Replace course assignments only when the caller explicitly provides them.
		$course_ids = $this->resolve_course_ids( $request );

		if ( null !== $course_ids ) {
			// Atomic replacement: delete all rows, then re-add.
			delete_post_meta( $module_id, '_lk_course_id' );
			foreach ( $course_ids as $course_id ) {
				add_post_meta( $module_id, '_lk_course_id', $course_id, false ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional many-to-many; multiple rows supported.
			}
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
	 * Add a single course assignment to a module (additive).
	 *
	 * POST /modules/{id}/assign-course
	 *
	 * @since    0.6.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function assign_course( $request ) {
		$module_id = (int) $request['id'];
		$module    = get_post( $module_id );

		if ( ! $module || 'lk_module' !== $module->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Module not found', 'learnkit' ) ),
				404
			);
		}

		$course_id      = (int) $request['course_id'];
		$existing_ids   = array_map( 'intval', get_post_meta( $module_id, '_lk_course_id', false ) );

		if ( in_array( $course_id, $existing_ids, true ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Module is already assigned to this course', 'learnkit' ) ),
				200
			);
		}

		add_post_meta( $module_id, '_lk_course_id', $course_id, false ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional many-to-many; multiple rows supported.

		// Place at end of course's module list.
		$existing_modules = get_posts( array(
			'post_type'      => 'lk_module',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_lk_course_id',
					'value'   => $course_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
			),
			'fields'         => 'ids',
		) );
		$max_order = 0;
		foreach ( $existing_modules as $mid ) {
			$order = (int) get_post_field( 'menu_order', $mid );
			if ( $order > $max_order ) {
				$max_order = $order;
			}
		}
		wp_update_post( array( 'ID' => $module_id, 'menu_order' => $max_order + 1 ) );

		return new WP_REST_Response(
			array(
				'message' => __( 'Course assigned successfully', 'learnkit' ),
				'module'  => $this->prepare_module_response( get_post( $module_id ) ),
			),
			200
		);
	}

	/**
	 * Remove a single course assignment from a module.
	 *
	 * DELETE /modules/{id}/assign-course
	 *
	 * @since    0.6.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function remove_course( $request ) {
		$module_id = (int) $request['id'];
		$module    = get_post( $module_id );

		if ( ! $module || 'lk_module' !== $module->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Module not found', 'learnkit' ) ),
				404
			);
		}

		$course_id = (int) $request['course_id'];
		delete_post_meta( $module_id, '_lk_course_id', $course_id );

		return new WP_REST_Response(
			array(
				'message' => __( 'Course removed successfully', 'learnkit' ),
				'module'  => $this->prepare_module_response( get_post( $module_id ) ),
			),
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
			// Verify it belongs to the course in the URL (any of its assigned courses).
			$assigned = array_map( 'intval', get_post_meta( $module_id, '_lk_course_id', false ) );
			if ( ! in_array( $course_id, $assigned, true ) ) {
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
	 * Returns `course_ids` (array) for many-to-many support.
	 * `course_id` (single value, backwards compat) is intentionally removed.
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
			'course_ids'     => array_map( 'intval', get_post_meta( $module->ID, '_lk_course_id', false ) ),
			'permalink'      => get_permalink( $module->ID ),
			'edit_link'      => get_edit_post_link( $module->ID, 'raw' ),
		);
	}

	/**
	 * Resolve course IDs from a request, supporting both `course_ids` (array)
	 * and the legacy `course_id` (single integer) parameter.
	 *
	 * Returns an array of sanitised integer course IDs, or null when neither
	 * parameter is present (so callers can distinguish "not provided" from
	 * "empty array").
	 *
	 * @since    0.6.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   int[]|null Array of course IDs, or null if not provided.
	 */
	private function resolve_course_ids( $request ) {
		if ( isset( $request['course_ids'] ) && is_array( $request['course_ids'] ) ) {
			return array_map( 'absint', array_filter( $request['course_ids'] ) );
		}

		if ( ! empty( $request['course_id'] ) ) {
			return array( absint( $request['course_id'] ) );
		}

		return null;
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
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content' => array(
				'sanitize_callback' => 'wp_kses_post',
			),
			'excerpt' => array(
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'course_id' => array(
				'type'              => 'integer',
				'description'       => __( 'Assign module to a single course (backwards compat). Prefer course_ids.', 'learnkit' ),
				'sanitize_callback' => 'absint',
			),
			'course_ids' => array(
				'type'              => 'array',
				'description'       => __( 'Assign module to one or more courses (many-to-many).', 'learnkit' ),
				'items'             => array(
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				),
			),
			'menu_order' => array(
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
			),
		);
	}
}
