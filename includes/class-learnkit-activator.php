<?php
/**
 * Fired during plugin activation
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.1.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    0.1.0
	 */
	public static function activate() {
		// Load database class.
		require_once plugin_dir_path( __FILE__ ) . 'class-learnkit-database.php';

		// Create custom database tables.
		LearnKit_Database::create_tables();

		// Migrate existing data from post_parent to meta fields.
		self::migrate_relationships();

		// Flush rewrite rules so custom post types work immediately.
		flush_rewrite_rules();

		// Set default options.
		self::set_default_options();
	}

	/**
	 * Migrate post_parent relationships to meta fields.
	 *
	 * Moves hierarchical post_parent data to flat meta-based relationships
	 * to prevent WordPress URL conflicts across CPT boundaries.
	 *
	 * @since    0.1.0
	 */
	private static function migrate_relationships() {
		// Migrate modules: post_parent → _lk_course_id.
		$modules = get_posts(
			array(
				'post_type'           => 'lk_module',
				'posts_per_page'      => -1,
				'post_status'         => 'any',
				'post_parent__not_in' => array( 0 ),
			)
		);

		foreach ( $modules as $module ) {
			if ( $module->post_parent > 0 ) {
				$parent_post_type = get_post_type( $module->post_parent );
				if ( 'lk_course' === $parent_post_type ) {
					update_post_meta( $module->ID, '_lk_course_id', $module->post_parent );
					wp_update_post(
						array(
							'ID'          => $module->ID,
							'post_parent' => 0,
						)
					);
				}
			}
		}

		// Migrate lessons: post_parent → _lk_module_id.
		$lessons = get_posts(
			array(
				'post_type'           => 'lk_lesson',
				'posts_per_page'      => -1,
				'post_status'         => 'any',
				'post_parent__not_in' => array( 0 ),
			)
		);

		foreach ( $lessons as $lesson ) {
			if ( $lesson->post_parent > 0 ) {
				$parent_post_type = get_post_type( $lesson->post_parent );
				if ( 'lk_module' === $parent_post_type ) {
					update_post_meta( $lesson->ID, '_lk_module_id', $lesson->post_parent );
					wp_update_post(
						array(
							'ID'          => $lesson->ID,
							'post_parent' => 0,
						)
					);
				}
			}
		}
	}

	/**
	 * Set default plugin options.
	 *
	 * @since    0.1.0
	 */
	private static function set_default_options() {
		// Store activation timestamp.
		add_option( 'learnkit_activated', current_time( 'timestamp' ) );

		// Default settings (can be modified via settings page later).
		add_option(
			'learnkit_settings',
			array(
				'enable_certificates' => true,
				'enable_email_notifications' => false,
				'course_catalog_page' => '',
			)
		);
	}
}
