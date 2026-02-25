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
class LearnKit_Lessons_Controller extends LearnKit_Base_Controller {

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
					'args'                => array_merge(
						$this->get_lesson_args(),
						array(
							'title' => array(
								'required'          => true,
								'type'              => 'string',
								'description'       => __( 'Lesson title.', 'learnkit' ),
								'sanitize_callback' => 'sanitize_text_field',
							),
						)
					),
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
			'/lessons/(?P<id>\d+)/assign-module',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'assign_module' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(
						'module_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Module ID to assign this lesson to.', 'learnkit' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_module' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(
						'module_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Module ID to remove this lesson from.', 'learnkit' ),
							'sanitize_callback' => 'absint',
						),
					),
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
			$args['meta_key']   = '_lk_module_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Intentional meta query; indexed by plugin on activation.
			$args['meta_value'] = (int) $request['module_id']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
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
			'post_content' => isset( $request['content'] ) ? wp_kses_post( $request['content'] ) : '',
			'post_excerpt' => isset( $request['excerpt'] ) ? sanitize_textarea_field( $request['excerpt'] ) : '',
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

		// Assign to module (one-to-one).
		update_post_meta( $lesson_id, '_lk_module_id', $module_id ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		// Place at end of module's lesson list.
		if ( isset( $request['menu_order'] ) ) {
			$new_order = (int) $request['menu_order'];
		} else {
			$existing_lessons = get_posts( array(
				'post_type'      => 'lk_lesson',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_lk_module_id',
						'value'   => $module_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
				'fields'         => 'ids',
			) );
			$max_order = 0;
			foreach ( $existing_lessons as $lid ) {
				if ( (int) $lid === $lesson_id ) {
					continue;
				}
				$order = (int) get_post_field( 'menu_order', $lid );
				if ( $order > $max_order ) {
					$max_order = $order;
				}
			}
			$new_order = $max_order + 1;
		}
		wp_update_post( array( 'ID' => $lesson_id, 'menu_order' => $new_order ) );

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
	 * Accepts either `module_id` (single integer, backwards compat) or
	 * `module_ids` (array of integers).  When provided, existing module
	 * assignments are replaced atomically (delete all, re-add).
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
		// Resolve module IDs — accept module_ids array or fall back to single module_id.
		$module_ids = $this->resolve_module_ids( $request );

		if ( null !== $module_ids ) {
			// One-to-one: store only the first (or only) module ID.
			$single_module_id = ! empty( $module_ids ) ? reset( $module_ids ) : 0;
			if ( $single_module_id ) {
				update_post_meta( $lesson_id, '_lk_module_id', $single_module_id ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			} else {
				delete_post_meta( $lesson_id, '_lk_module_id' );
			}
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
	 * Add a single module assignment to a lesson (additive).
	 *
	 * POST /lessons/{id}/assign-module
	 *
	 * @since    0.6.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function assign_module( $request ) {
		$lesson_id = (int) $request['id'];
		$lesson    = get_post( $lesson_id );

		if ( ! $lesson || 'lk_lesson' !== $lesson->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Lesson not found', 'learnkit' ) ),
				404
			);
		}

		$module_id    = (int) $request['module_id'];
		$existing_ids = array_map( 'intval', get_post_meta( $lesson_id, '_lk_module_id', false ) );

		if ( in_array( $module_id, $existing_ids, true ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Lesson is already assigned to this module', 'learnkit' ) ),
				200
			);
		}

		// One-to-one: replaces any existing module assignment.
		update_post_meta( $lesson_id, '_lk_module_id', $module_id ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		// Place at end of module's lesson list.
		$existing_lessons = get_posts( array(
			'post_type'      => 'lk_lesson',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_lk_module_id',
					'value'   => $module_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
			),
			'fields'         => 'ids',
		) );
		$max_order = 0;
		foreach ( $existing_lessons as $lid ) {
			$order = (int) get_post_field( 'menu_order', $lid );
			if ( $order > $max_order ) {
				$max_order = $order;
			}
		}
		wp_update_post( array( 'ID' => $lesson_id, 'menu_order' => $max_order + 1 ) );

		return new WP_REST_Response(
			array(
				'message' => __( 'Module assigned successfully', 'learnkit' ),
				'lesson'  => $this->prepare_lesson_response( get_post( $lesson_id ) ),
			),
			200
		);
	}

	/**
	 * Remove a single module assignment from a lesson.
	 *
	 * DELETE /lessons/{id}/assign-module
	 *
	 * @since    0.6.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   WP_REST_Response Response object.
	 */
	public function remove_module( $request ) {
		$lesson_id = (int) $request['id'];
		$lesson    = get_post( $lesson_id );

		if ( ! $lesson || 'lk_lesson' !== $lesson->post_type ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Lesson not found', 'learnkit' ) ),
				404
			);
		}

		$module_id = (int) $request['module_id'];
		delete_post_meta( $lesson_id, '_lk_module_id', $module_id );

		return new WP_REST_Response(
			array(
				'message' => __( 'Module removed successfully', 'learnkit' ),
				'lesson'  => $this->prepare_lesson_response( get_post( $lesson_id ) ),
			),
			200
		);
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
		$order     = $request['order'];
		$module_id = (int) $request['id'];

		foreach ( $order as $index => $lesson_id ) {
			$lesson_id = (int) $lesson_id;
			$lesson    = get_post( $lesson_id );
			if ( ! $lesson || 'lk_lesson' !== $lesson->post_type ) {
				continue;
			}
			// Verify it belongs to this module.
			$assigned_module = (int) get_post_meta( $lesson_id, '_lk_module_id', true );
			if ( $assigned_module !== $module_id ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $lesson_id ) ) {
				continue;
			}
			wp_update_post(
				array(
					'ID'         => $lesson_id,
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
	 * Returns `module_id` (int) for one-to-one module assignment.
	 * Also returns `module_ids` array for backwards compatibility.
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
			'module_id'      => (int) get_post_meta( $lesson->ID, '_lk_module_id', true ),
			'module_ids'     => array( (int) get_post_meta( $lesson->ID, '_lk_module_id', true ) ), // backwards compat
			'permalink'      => learnkit_lesson_url( $lesson->ID ),
			'edit_link'      => get_edit_post_link( $lesson->ID, 'raw' ),
			'release_type'   => get_post_meta( $lesson->ID, '_lk_release_type', true ) ? get_post_meta( $lesson->ID, '_lk_release_type', true ) : 'immediate',
			'release_days'   => (int) get_post_meta( $lesson->ID, '_lk_release_days', true ),
			'release_date'   => get_post_meta( $lesson->ID, '_lk_release_date', true ),
		);
	}

	/**
	 * Resolve module IDs from a request, supporting both `module_ids` (array)
	 * and the legacy `module_id` (single integer) parameter.
	 *
	 * Returns an array of sanitised integer module IDs, or null when neither
	 * parameter is present (so callers can distinguish "not provided" from
	 * "empty array").
	 *
	 * @since    0.6.0
	 * @param    WP_REST_Request $request Full request data.
	 * @return   int[]|null Array of module IDs, or null if not provided.
	 */
	private function resolve_module_ids( $request ) {
		if ( isset( $request['module_ids'] ) && is_array( $request['module_ids'] ) ) {
			return array_map( 'absint', array_filter( $request['module_ids'] ) );
		}

		if ( ! empty( $request['module_id'] ) ) {
			return array( absint( $request['module_id'] ) );
		}

		return null;
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
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content' => array(
				'sanitize_callback' => 'wp_kses_post',
			),
			'excerpt' => array(
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'module_id' => array(
				'type'              => 'integer',
				'description'       => __( 'Assign lesson to a single module (backwards compat). Prefer module_ids.', 'learnkit' ),
				'sanitize_callback' => 'absint',
			),
			'module_ids' => array(
				'type'              => 'array',
				'description'       => __( 'Assign lesson to one or more modules (many-to-many).', 'learnkit' ),
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
			'release_type' => array(
				'type'              => 'string',
				'enum'              => array( 'immediate', 'drip_days', 'drip_date' ),
				'description'       => __( 'Lesson release type.', 'learnkit' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'release_days' => array(
				'type'              => 'integer',
				'minimum'           => 0,
				'description'       => __( 'Days after enrollment to release lesson.', 'learnkit' ),
				'sanitize_callback' => 'absint',
			),
			'release_date' => array(
				'type'              => 'string',
				'format'            => 'date',
				'description'       => __( 'Specific date to release lesson (YYYY-MM-DD).', 'learnkit' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					return false !== \DateTime::createFromFormat( 'Y-m-d', $value );
				},
			),
		);
	}
}
