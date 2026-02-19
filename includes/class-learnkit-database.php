<?php
/**
 * Database schema for LearnKit progress tracking
 *
 * @link       https://jameswelbes.com
 * @since      0.2.14
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

/**
 * LearnKit Database class.
 *
 * Creates and manages custom database tables for enrollments and progress tracking.
 *
 * @since      0.2.14
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Database {

	/**
	 * Create database tables.
	 *
	 * @since    0.2.14
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Enrollments table.
		$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';
		$enrollments_sql   = "CREATE TABLE $enrollments_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			enrolled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) NOT NULL DEFAULT 'active',
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY course_id (course_id),
			KEY status (status)
		) $charset_collate;";

		// Progress table.
		$progress_table = $wpdb->prefix . 'learnkit_progress';
		$progress_sql   = "CREATE TABLE $progress_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			completed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_lesson (user_id, lesson_id),
			KEY lesson_id (lesson_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $enrollments_sql );
		dbDelta( $progress_sql );

		// Store database version.
		update_option( 'learnkit_db_version', '1.0' );
	}

	/**
	 * Drop database tables (for uninstall).
	 *
	 * @since    0.2.14
	 */
	public static function drop_tables() {
		global $wpdb;

		$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';
		$progress_table    = $wpdb->prefix . 'learnkit_progress';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS $progress_table" );
		$wpdb->query( "DROP TABLE IF EXISTS $enrollments_table" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		delete_option( 'learnkit_db_version' );
	}
}
