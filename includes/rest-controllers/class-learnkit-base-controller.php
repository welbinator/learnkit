<?php
/**
 * Base REST controller for LearnKit.
 *
 * @package LearnKit
 */

/**
 * Class LearnKit_Base_Controller
 *
 * Shared namespace, permission callbacks, and helpers for all LearnKit REST controllers.
 */
class LearnKit_Base_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = LEARNKIT_REST_NAMESPACE;

	/**
	 * Check permission for read operations (admin only).
	 *
	 * @return bool
	 */
	public function check_read_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check permission for write operations (ownership-aware).
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return bool
	 */
	public function check_write_permission( $request ) {
		$id = isset( $request['id'] ) ? (int) $request['id'] : 0;
		if ( $id ) {
			return current_user_can( 'edit_post', $id );
		}
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check permission for admin-only operations.
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}
}
