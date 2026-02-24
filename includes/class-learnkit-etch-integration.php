<?php
/**
 * Etch integration — LearnKit dynamic data orchestrator.
 *
 * Hooks into the `etch/dynamic_data/post` filter and enriches the data
 * payload with nested relational data (modules, lessons, courses, quizzes)
 * for LearnKit post types.
 *
 * Only active when the Etch plugin is loaded.
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @since      0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LearnKit_Etch_Integration
 *
 * Orchestrates Etch dynamic data injection for LearnKit post types.
 *
 * Detects the post type of the current context post and delegates to the
 * appropriate resolver:
 *   - lk_course  → LearnKit_Etch_Course_Resolver
 *   - lk_module  → LearnKit_Etch_Module_Resolver
 *   - lk_lesson  → LearnKit_Etch_Lesson_Resolver
 *
 * @since 0.1.0
 */
class LearnKit_Etch_Integration {

	/**
	 * Resolver instance for lk_course posts.
	 *
	 * @since  0.1.0
	 * @access private
	 * @var    LearnKit_Etch_Course_Resolver
	 */
	private $course_resolver;

	/**
	 * Resolver instance for lk_module posts.
	 *
	 * @since  0.1.0
	 * @access private
	 * @var    LearnKit_Etch_Module_Resolver
	 */
	private $module_resolver;

	/**
	 * Resolver instance for lk_lesson posts.
	 *
	 * @since  0.1.0
	 * @access private
	 * @var    LearnKit_Etch_Lesson_Resolver
	 */
	private $lesson_resolver;

	/**
	 * Constructor. Instantiates all resolver dependencies.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->course_resolver = new LearnKit_Etch_Course_Resolver();
		$this->module_resolver = new LearnKit_Etch_Module_Resolver();
		$this->lesson_resolver = new LearnKit_Etch_Lesson_Resolver();
	}

	/**
	 * Filter callback for `etch/dynamic_data/post`.
	 *
	 * Inspects the post type of `$post_id` and dispatches to the correct
	 * resolver. Post types not managed by LearnKit are returned unchanged.
	 *
	 * @since  0.1.0
	 *
	 * @param  array<string, mixed> $data    The Etch dynamic data array for the post.
	 * @param  int                  $post_id The ID of the post being resolved.
	 * @return array<string, mixed>          The (potentially enriched) dynamic data array.
	 */
	public function inject( array $data, int $post_id ): array {
		$post_type = get_post_type( $post_id );

		switch ( $post_type ) {
			case 'lk_course':
				return $this->course_resolver->resolve( $data, $post_id );

			case 'lk_module':
				return $this->module_resolver->resolve( $data, $post_id );

			case 'lk_lesson':
				return $this->lesson_resolver->resolve( $data, $post_id );

			default:
				return $data;
		}
	}
}
