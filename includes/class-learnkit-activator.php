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
		// Create custom database tables.
		self::create_tables();

		// Migrate existing data from post_parent to meta fields.
		self::migrate_relationships();

		// Flush rewrite rules so custom post types work immediately.
		flush_rewrite_rules();

		// Set default options.
		self::set_default_options();
	}

	/**
	 * Create custom database tables for enrollments and progress tracking.
	 *
	 * Uses dbDelta for safe table creation that handles updates gracefully.
	 * Custom tables chosen over post meta for performance at scale.
	 *
	 * @since    0.1.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table names with WordPress prefix.
		$enrollments_table = $wpdb->prefix . 'lk_enrollments';
		$progress_table    = $wpdb->prefix . 'lk_progress';
		$certificates_table = $wpdb->prefix . 'lk_certificates';

		$sql = array();

		// Enrollments table: tracks which users are enrolled in which courses.
		$sql[] = "CREATE TABLE $enrollments_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			enrolled_date datetime NOT NULL,
			completed_date datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY status (status),
			UNIQUE KEY user_course (user_id, course_id)
		) $charset_collate;";

		// Progress table: tracks lesson completion per user.
		$sql[] = "CREATE TABLE $progress_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			completed tinyint(1) NOT NULL DEFAULT 0,
			completed_date datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY lesson_id (lesson_id),
			KEY course_id (course_id),
			KEY completed (completed),
			UNIQUE KEY user_lesson (user_id, lesson_id)
		) $charset_collate;";

		// Certificates table: stores generated certificates.
		$sql[] = "CREATE TABLE $certificates_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			certificate_code varchar(100) NOT NULL,
			issued_date datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			UNIQUE KEY certificate_code (certificate_code),
			UNIQUE KEY user_course_cert (user_id, course_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		// Store database version for future migrations.
		update_option( 'learnkit_db_version', '1.0' );
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
