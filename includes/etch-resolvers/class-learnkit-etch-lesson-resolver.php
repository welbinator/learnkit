<?php
/**
 * Etch dynamic data resolver for lk_lesson posts.
 *
 * Injects `module`, `course`, and `quiz` objects into the Etch dynamic
 * data payload for lesson post types.
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes/etch-resolvers
 * @since      0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class LearnKit_Etch_Lesson_Resolver
 *
 * Handles Etch dynamic data enrichment for lk_lesson post types.
 *
 * @since 0.1.0
 */
class LearnKit_Etch_Lesson_Resolver {

	/**
	 * Inject lesson-specific relational data into the Etch dynamic data array.
	 *
	 * Adds three keys to the data array:
	 *
	 *   `module` — The parent module object, or null if none found.
	 *     Contains:
	 *       - id         (int)    Post ID of the module.
	 *       - title      (string) Post title of the module.
	 *       - permalink  (string) Frontend URL of the module.
	 *
	 *   `course` — The parent course object (resolved through the module), or null.
	 *     Contains:
	 *       - id         (int)    Post ID of the course.
	 *       - title      (string) Post title of the course.
	 *       - permalink  (string) Frontend URL of the course.
	 *
	 *   `quiz` — The quiz associated with this lesson, or null if none exists.
	 *     Contains:
	 *       - id         (int)    Post ID of the quiz.
	 *       - title      (string) Post title of the quiz.
	 *       - permalink  (string) Frontend URL of the quiz.
	 *
	 * @since  0.1.0
	 *
	 * @param  array<string, mixed> $data    The existing Etch dynamic data array.
	 * @param  int                  $post_id The ID of the current lk_lesson post.
	 * @return array<string, mixed>          The enriched dynamic data array.
	 */
	public function resolve( array $data, int $post_id ): array {
		$module = $this->get_module( $post_id );

		$data['module'] = $module;
		$data['course'] = $module ? $this->get_course_for_module( (int) $module['id'] ) : null;
		$data['quiz']   = $this->get_quiz( $post_id );

		return $data;
	}

	/**
	 * Retrieve the parent module for this lesson.
	 *
	 * Reads the `_lk_module_id` post meta from the lesson to locate the
	 * associated lk_module post.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  int $lesson_id The post ID of the lk_lesson.
	 * @return array<string, mixed>|null Module data array, or null if not found.
	 */
	private function get_module( int $lesson_id ): ?array {
		$module_id = (int) get_post_meta( $lesson_id, '_lk_module_id', true );

		if ( ! $module_id ) {
			return null;
		}

		$module = get_post( $module_id );

		if ( ! $module instanceof WP_Post ) {
			return null;
		}

		return array(
			'id'        => $module->ID,
			'title'     => $module->post_title,
			'permalink' => get_permalink( $module->ID ),
		);
	}

	/**
	 * Retrieve the parent course for a given module.
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
	private function get_course_for_module( int $module_id ): ?array {
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

	/**
	 * Retrieve the quiz associated with this lesson, if one exists.
	 *
	 * Queries for an lk_quiz post whose `_lk_lesson_id` meta matches
	 * the given lesson ID.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  int $lesson_id The post ID of the lk_lesson.
	 * @return array<string, mixed>|null Quiz data array, or null if no quiz found.
	 */
	private function get_quiz( int $lesson_id ): ?array {
		$quizzes = get_posts(
			array(
				'post_type'      => 'lk_quiz',
				'meta_key'       => '_lk_lesson_id',
				'meta_value'     => $lesson_id,
				'posts_per_page' => 1,
			)
		);

		$quiz = $quizzes[0] ?? null;

		if ( ! $quiz instanceof WP_Post ) {
			return null;
		}

		return array(
			'id'        => $quiz->ID,
			'title'     => $quiz->post_title,
			'permalink' => get_permalink( $quiz->ID ),
		);
	}
}
