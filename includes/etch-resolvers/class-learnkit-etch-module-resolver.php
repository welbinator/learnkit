<?php
/**
 * Etch dynamic data resolver for lk_module posts.
 *
 * Injects a `lessons` array and a `course` object into the Etch dynamic
 * data payload for module post types.
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes/etch-resolvers
 * @since      0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LearnKit_Etch_Module_Resolver
 *
 * Handles Etch dynamic data enrichment for lk_module post types.
 *
 * @since 0.1.0
 */
class LearnKit_Etch_Module_Resolver {

	/**
	 * Inject module-specific relational data into the Etch dynamic data array.
	 *
	 * Adds two keys to the data array:
	 *
	 *   `lessons` — Ordered array of lesson objects belonging to this module.
	 *     Each entry contains:
	 *       - id         (int)    Post ID of the lesson.
	 *       - title      (string) Post title of the lesson.
	 *       - permalink  (string) Frontend URL of the lesson.
	 *       - menu_order (int)    The lesson's menu_order value.
	 *
	 *   `course` — The parent course object, or null if none found.
	 *     Contains:
	 *       - id         (int)    Post ID of the course.
	 *       - title      (string) Post title of the course.
	 *       - permalink  (string) Frontend URL of the course.
	 *
	 * @since  0.1.0
	 *
	 * @param  array<string, mixed> $data    The existing Etch dynamic data array.
	 * @param  int                  $post_id The ID of the current lk_module post.
	 * @return array<string, mixed>          The enriched dynamic data array.
	 */
	public function resolve( array $data, int $post_id ): array {
		$data['lessons'] = $this->get_lessons( $post_id );
		$data['course']  = $this->get_course( $post_id );

		return $data;
	}

	/**
	 * Retrieve an ordered list of lessons belonging to this module.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  int $module_id The post ID of the lk_module.
	 * @return array<int, array<string, mixed>> Ordered array of lesson data arrays.
	 */
	private function get_lessons( int $module_id ): array {
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

	/**
	 * Retrieve the parent course for this module.
	 *
	 * Reads the `_lk_course_id` post meta from the module to find the
	 * associated lk_course post.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  int $module_id The post ID of the lk_module.
	 * @return array<string, mixed>|null Course data array, or null if not found.
	 */
	private function get_course( int $module_id ): ?array {
		$course_id = (int) get_post_meta( $module_id, '_lk_course_id', true );

		if ( ! $course_id ) {
			return null;
		}

		$course = get_post( $course_id );

		if ( ! $course instanceof WP_Post ) {
			return null;
		}

		return array(
			'id'        => $course->ID,
			'title'     => $course->post_title,
			'permalink' => get_permalink( $course->ID ),
		);
	}
}
