<?php
/**
 * Quiz Post Type Registration
 *
 * Registers the lk_quiz custom post type for quizzes and assessments.
 *
 * @link       https://jameswelbes.com
 * @since      0.4.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

/**
 * Register Quiz custom post type.
 *
 * @since      0.4.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Quiz_CPT {

	/**
	 * Register the quiz custom post type.
	 *
	 * @since    0.4.0
	 */
	public function register() {
		$labels = array(
			'name'                  => _x( 'Quizzes', 'Post Type General Name', 'learnkit' ),
			'singular_name'         => _x( 'Quiz', 'Post Type Singular Name', 'learnkit' ),
			'menu_name'             => __( 'Quizzes', 'learnkit' ),
			'name_admin_bar'        => __( 'Quiz', 'learnkit' ),
			'archives'              => __( 'Quiz Archives', 'learnkit' ),
			'attributes'            => __( 'Quiz Attributes', 'learnkit' ),
			'parent_item_colon'     => __( 'Parent Quiz:', 'learnkit' ),
			'all_items'             => __( 'All Quizzes', 'learnkit' ),
			'add_new_item'          => __( 'Add New Quiz', 'learnkit' ),
			'add_new'               => __( 'Add New', 'learnkit' ),
			'new_item'              => __( 'New Quiz', 'learnkit' ),
			'edit_item'             => __( 'Edit Quiz', 'learnkit' ),
			'update_item'           => __( 'Update Quiz', 'learnkit' ),
			'view_item'             => __( 'View Quiz', 'learnkit' ),
			'view_items'            => __( 'View Quizzes', 'learnkit' ),
			'search_items'          => __( 'Search Quiz', 'learnkit' ),
			'not_found'             => __( 'Not found', 'learnkit' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'learnkit' ),
			'featured_image'        => __( 'Featured Image', 'learnkit' ),
			'set_featured_image'    => __( 'Set featured image', 'learnkit' ),
			'remove_featured_image' => __( 'Remove featured image', 'learnkit' ),
			'use_featured_image'    => __( 'Use as featured image', 'learnkit' ),
			'insert_into_item'      => __( 'Insert into quiz', 'learnkit' ),
			'uploaded_to_this_item' => __( 'Uploaded to this quiz', 'learnkit' ),
			'items_list'            => __( 'Quizzes list', 'learnkit' ),
			'items_list_navigation' => __( 'Quizzes list navigation', 'learnkit' ),
			'filter_items_list'     => __( 'Filter quizzes list', 'learnkit' ),
		);

		$args = array(
			'label'               => __( 'Quiz', 'learnkit' ),
			'description'         => __( 'Quizzes and assessments for courses', 'learnkit' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'revisions' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // Managed via LearnKit admin.
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-clipboard',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'show_in_rest'        => true,
			'rest_base'           => 'quizzes',
			'capability_type'     => 'post',
		);

		register_post_type( 'lk_quiz', $args );

		// Register quiz meta fields.
		$this->register_meta_fields();
	}

	/**
	 * Register quiz meta fields.
	 *
	 * @since    0.4.0
	 */
	private function register_meta_fields() {
		// Lesson ID (quiz belongs to this lesson).
		register_post_meta(
			'lk_quiz',
			'_lk_lesson_id',
			array(
				'type'              => 'integer',
				'description'       => __( 'Lesson this quiz belongs to', 'learnkit' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
			)
		);

		// Module ID (quiz belongs to this module).
		register_post_meta(
			'lk_quiz',
			'_lk_module_id',
			array(
				'type'              => 'integer',
				'description'       => __( 'Module this quiz belongs to', 'learnkit' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
			)
		);

		// Course ID (quiz belongs to this course).
		register_post_meta(
			'lk_quiz',
			'_lk_course_id',
			array(
				'type'              => 'integer',
				'description'       => __( 'Course this quiz belongs to', 'learnkit' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
			)
		);

		// Passing score percentage.
		register_post_meta(
			'lk_quiz',
			'_lk_passing_score',
			array(
				'type'              => 'integer',
				'description'       => __( 'Passing score percentage (0-100)', 'learnkit' ),
				'single'            => true,
				'default'           => 70,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
			)
		);

		// Time limit in minutes (0 = unlimited).
		register_post_meta(
			'lk_quiz',
			'_lk_time_limit',
			array(
				'type'              => 'integer',
				'description'       => __( 'Time limit in minutes (0 for unlimited)', 'learnkit' ),
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
			)
		);

		// Number of attempts allowed (0 = unlimited).
		register_post_meta(
			'lk_quiz',
			'_lk_attempts_allowed',
			array(
				'type'              => 'integer',
				'description'       => __( 'Number of attempts allowed (0 for unlimited)', 'learnkit' ),
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
			)
		);

		// Required to complete lesson.
		register_post_meta(
			'lk_quiz',
			'_lk_required_to_complete',
			array(
				'type'              => 'boolean',
				'description'       => __( 'Must pass quiz to mark lesson complete', 'learnkit' ),
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		// Quiz questions (JSON array).
		register_post_meta(
			'lk_quiz',
			'_lk_questions',
			array(
				'type'              => 'string',
				'description'       => __( 'Quiz questions data (JSON)', 'learnkit' ),
				'single'            => true,
				'default'           => '[]',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);
	}
}
