<?php
/**
 * Register custom post types for LearnKit
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

/**
 * Register custom post types for LearnKit.
 *
 * Defines custom post types for courses, modules, and lessons.
 * Uses WordPress CPTs to leverage core functionality while maintaining
 * performance-optimized custom tables for enrollment and progress data.
 *
 * @since      0.1.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Post_Types {

	/**
	 * Register Course post type.
	 *
	 * Hierarchical structure allows for course organization.
	 * Supports title, editor, thumbnail, and excerpt.
	 *
	 * @since    0.1.0
	 */
	public function register_course_post_type() {
		$labels = array(
			'name'                  => _x( 'Courses', 'Post Type General Name', 'learnkit' ),
			'singular_name'         => _x( 'Course', 'Post Type Singular Name', 'learnkit' ),
			'menu_name'             => __( 'Courses', 'learnkit' ),
			'name_admin_bar'        => __( 'Course', 'learnkit' ),
			'archives'              => __( 'Course Archives', 'learnkit' ),
			'attributes'            => __( 'Course Attributes', 'learnkit' ),
			'parent_item_colon'     => __( 'Parent Course:', 'learnkit' ),
			'all_items'             => __( 'All Courses', 'learnkit' ),
			'add_new_item'          => __( 'Add New Course', 'learnkit' ),
			'add_new'               => __( 'Add New', 'learnkit' ),
			'new_item'              => __( 'New Course', 'learnkit' ),
			'edit_item'             => __( 'Edit Course', 'learnkit' ),
			'update_item'           => __( 'Update Course', 'learnkit' ),
			'view_item'             => __( 'View Course', 'learnkit' ),
			'view_items'            => __( 'View Courses', 'learnkit' ),
			'search_items'          => __( 'Search Course', 'learnkit' ),
			'not_found'             => __( 'Not found', 'learnkit' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'learnkit' ),
			'featured_image'        => __( 'Course Image', 'learnkit' ),
			'set_featured_image'    => __( 'Set course image', 'learnkit' ),
			'remove_featured_image' => __( 'Remove course image', 'learnkit' ),
			'use_featured_image'    => __( 'Use as course image', 'learnkit' ),
			'insert_into_item'      => __( 'Insert into course', 'learnkit' ),
			'uploaded_to_this_item' => __( 'Uploaded to this course', 'learnkit' ),
			'items_list'            => __( 'Courses list', 'learnkit' ),
			'items_list_navigation' => __( 'Courses list navigation', 'learnkit' ),
			'filter_items_list'     => __( 'Filter courses list', 'learnkit' ),
		);

		$args = array(
			'label'               => __( 'Course', 'learnkit' ),
			'description'         => __( 'LearnKit Courses', 'learnkit' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
			'hierarchical'        => true,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // We'll add custom menu.
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-welcome-learn-more',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'show_in_rest'        => true, // Enable Gutenberg editor.
			'rest_base'           => 'lk-courses',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);

		register_post_type( 'lk_course', $args );
	}

	/**
	 * Register Module post type.
	 *
	 * Modules are organizational units within courses.
	 * Uses post_parent to link to parent course.
	 *
	 * @since    0.1.0
	 */
	public function register_module_post_type() {
		$labels = array(
			'name'                  => _x( 'Modules', 'Post Type General Name', 'learnkit' ),
			'singular_name'         => _x( 'Module', 'Post Type Singular Name', 'learnkit' ),
			'menu_name'             => __( 'Modules', 'learnkit' ),
			'name_admin_bar'        => __( 'Module', 'learnkit' ),
			'archives'              => __( 'Module Archives', 'learnkit' ),
			'attributes'            => __( 'Module Attributes', 'learnkit' ),
			'parent_item_colon'     => __( 'Parent Course:', 'learnkit' ),
			'all_items'             => __( 'All Modules', 'learnkit' ),
			'add_new_item'          => __( 'Add New Module', 'learnkit' ),
			'add_new'               => __( 'Add New', 'learnkit' ),
			'new_item'              => __( 'New Module', 'learnkit' ),
			'edit_item'             => __( 'Edit Module', 'learnkit' ),
			'update_item'           => __( 'Update Module', 'learnkit' ),
			'view_item'             => __( 'View Module', 'learnkit' ),
			'view_items'            => __( 'View Modules', 'learnkit' ),
			'search_items'          => __( 'Search Module', 'learnkit' ),
			'not_found'             => __( 'Not found', 'learnkit' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'learnkit' ),
			'featured_image'        => __( 'Module Image', 'learnkit' ),
			'set_featured_image'    => __( 'Set module image', 'learnkit' ),
			'remove_featured_image' => __( 'Remove module image', 'learnkit' ),
			'use_featured_image'    => __( 'Use as module image', 'learnkit' ),
			'insert_into_item'      => __( 'Insert into module', 'learnkit' ),
			'uploaded_to_this_item' => __( 'Uploaded to this module', 'learnkit' ),
			'items_list'            => __( 'Modules list', 'learnkit' ),
			'items_list_navigation' => __( 'Modules list navigation', 'learnkit' ),
			'filter_items_list'     => __( 'Filter modules list', 'learnkit' ),
		);

		$args = array(
			'label'               => __( 'Module', 'learnkit' ),
			'description'         => __( 'Course Modules', 'learnkit' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			'hierarchical'        => true,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-list-view',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'show_in_rest'        => true,
			'rest_base'           => 'lk-modules',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);

		register_post_type( 'lk_module', $args );
	}

	/**
	 * Register Lesson post type.
	 *
	 * Lessons contain the actual course content.
	 * Uses WordPress block editor for content creation.
	 * Links to parent module via post_parent.
	 *
	 * @since    0.1.0
	 */
	public function register_lesson_post_type() {
		$labels = array(
			'name'                  => _x( 'Lessons', 'Post Type General Name', 'learnkit' ),
			'singular_name'         => _x( 'Lesson', 'Post Type Singular Name', 'learnkit' ),
			'menu_name'             => __( 'Lessons', 'learnkit' ),
			'name_admin_bar'        => __( 'Lesson', 'learnkit' ),
			'archives'              => __( 'Lesson Archives', 'learnkit' ),
			'attributes'            => __( 'Lesson Attributes', 'learnkit' ),
			'parent_item_colon'     => __( 'Parent Lesson:', 'learnkit' ),
			'all_items'             => __( 'All Lessons', 'learnkit' ),
			'add_new_item'          => __( 'Add New Lesson', 'learnkit' ),
			'add_new'               => __( 'Add New', 'learnkit' ),
			'new_item'              => __( 'New Lesson', 'learnkit' ),
			'edit_item'             => __( 'Edit Lesson', 'learnkit' ),
			'update_item'           => __( 'Update Lesson', 'learnkit' ),
			'view_item'             => __( 'View Lesson', 'learnkit' ),
			'view_items'            => __( 'View Lessons', 'learnkit' ),
			'search_items'          => __( 'Search Lesson', 'learnkit' ),
			'not_found'             => __( 'Not found', 'learnkit' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'learnkit' ),
			'featured_image'        => __( 'Lesson Image', 'learnkit' ),
			'set_featured_image'    => __( 'Set lesson image', 'learnkit' ),
			'remove_featured_image' => __( 'Remove lesson image', 'learnkit' ),
			'use_featured_image'    => __( 'Use as lesson image', 'learnkit' ),
			'insert_into_item'      => __( 'Insert into lesson', 'learnkit' ),
			'uploaded_to_this_item' => __( 'Uploaded to this lesson', 'learnkit' ),
			'items_list'            => __( 'Lessons list', 'learnkit' ),
			'items_list_navigation' => __( 'Lessons list navigation', 'learnkit' ),
			'filter_items_list'     => __( 'Filter lessons list', 'learnkit' ),
		);

		$args = array(
			'label'               => __( 'Lesson', 'learnkit' ),
			'description'         => __( 'Course Lessons', 'learnkit' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'page-attributes' ),
			'hierarchical'        => true,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'menu_position'       => 22,
			'menu_icon'           => 'dashicons-media-document',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'show_in_rest'        => true, // Enable Gutenberg editor for lesson content.
			'rest_base'           => 'lk-lessons',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);

		register_post_type( 'lk_lesson', $args );
	}
}
