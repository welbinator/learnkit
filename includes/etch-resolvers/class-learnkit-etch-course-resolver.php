<?php
/**
 * Etch dynamic data resolver for lk_course posts.
 *
 * Injects a `modules` array into the Etch dynamic data payload.
 * Each module entry includes its ordered `lessons` array and permalink.
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes/etch-resolvers
 * @since      0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LearnKit_Etch_Course_Resolver
 *
 * Handles Etch dynamic data enrichment for lk_course post types.
 *
 * @since 0.1.0
 */
class LearnKit_Etch_Course_Resolver {

	/**
	 * Inject course-specific relational data into the Etch dynamic data array.
	 *
	 * Adds a `modules` key containing an ordered array of module objects.
	 * Each module object includes:
	 *   - id         (int)    Post ID of the module.
	 *   - title      (string) Post title of the module.
	 *   - permalink  (string) Frontend URL of the module.
	 *   - lessons    (array)  Ordered array of lesson objects belonging to this module.
	 *
	 * Each lesson object within modules includes:
	 *   - id         (int)    Post ID of the lesson.
	 *   - title      (string) Post title of the lesson.
	 *   - permalink  (string) Frontend URL of the lesson.
	 *   - menu_order (int)    The lesson's menu_order value.
	 *
	 * @since  0.1.0
	 *
	 * @param  array<string, mixed> $data    The existing Etch dynamic data array.
	 * @param  int                  $post_id The ID of the current lk_course post.
	 * @return array<string, mixed>          The enriched dynamic data array.
	 */
	public function resolve( array $data, int $post_id ): array {
		$modules = get_posts(
			array(
				'post_type'      => 'lk_module',
				'meta_key'       => '_lk_course_id',
				'meta_value'     => $post_id,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			)
		);

		$data['modules'] = array();

		foreach ( $modules as $module ) {
			$data['modules'][] = array(
				'id'        => $module->ID,
				'title'     => $module->post_title,
				'permalink' => get_permalink( $module->ID ),
				'lessons'   => $this->get_lessons_for_module( $module->ID ),
			);
		}

		return $data;
	}

	/**
	 * Retrieve an ordered list of lessons belonging to a given module.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  int $module_id The post ID of the lk_module.
	 * @return array<int, array<string, mixed>> Ordered array of lesson data arrays.
	 */
	private function get_lessons_for_module( int $module_id ): array {
		$lessons = get_posts(
			array(
				'post_type'      => 'lk_lesson',
				'meta_key'       => '_lk_module_id',
				'meta_value'     => $module_id,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			)
		);

		$result = array();

		foreach ( $lessons as $lesson ) {
			$result[] = array(
				'id'         => $lesson->ID,
				'title'      => $lesson->post_title,
				'permalink'  => get_permalink( $lesson->ID ),
				'menu_order' => (int) $lesson->menu_order,
			);
		}

		return $result;
	}
}
