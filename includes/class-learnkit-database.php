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
			source varchar(50) NOT NULL DEFAULT 'manual',
			expires_at datetime DEFAULT NULL,
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

		// Quiz attempts table.
		$quiz_attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';
		$quiz_attempts_sql   = "CREATE TABLE $quiz_attempts_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			quiz_id bigint(20) unsigned NOT NULL,
			score int(11) NOT NULL,
			max_score int(11) NOT NULL,
			passed tinyint(1) NOT NULL DEFAULT 0,
			started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			answers longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY quiz_id (quiz_id),
			KEY passed (passed)
		) $charset_collate;";

		// Email queue table.
		$email_queue_table = $wpdb->prefix . 'learnkit_email_queue';
		$email_queue_sql   = "CREATE TABLE $email_queue_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			email_type varchar(50) NOT NULL,
			scheduled_at datetime NOT NULL,
			sent_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			payload longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) $charset_collate;";

		// Email preferences table.
		$email_prefs_table = $wpdb->prefix . 'learnkit_email_preferences';
		$email_prefs_sql   = "CREATE TABLE $email_prefs_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			email_type varchar(50) NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY user_email_type (user_id, email_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $enrollments_sql );
		dbDelta( $progress_sql );
		dbDelta( $quiz_attempts_sql );
		dbDelta( $email_queue_sql );
		dbDelta( $email_prefs_sql );

		// Store database version.
		update_option( 'learnkit_db_version', '1.3' );
	}

	/**
	 * Drop database tables (for uninstall).
	 *
	 * @since    0.2.14
	 */
	public static function drop_tables() {
		global $wpdb;

		$enrollments_table   = $wpdb->prefix . 'learnkit_enrollments';
		$progress_table      = $wpdb->prefix . 'learnkit_progress';
		$quiz_attempts_table = $wpdb->prefix . 'learnkit_quiz_attempts';
		$email_queue_table   = $wpdb->prefix . 'learnkit_email_queue';
		$email_prefs_table   = $wpdb->prefix . 'learnkit_email_preferences';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS $email_prefs_table" );
		$wpdb->query( "DROP TABLE IF EXISTS $email_queue_table" );
		$wpdb->query( "DROP TABLE IF EXISTS $quiz_attempts_table" );
		$wpdb->query( "DROP TABLE IF EXISTS $progress_table" );
		$wpdb->query( "DROP TABLE IF EXISTS $enrollments_table" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		delete_option( 'learnkit_db_version' );
	}
}
