<?php
/**
 * WP-Cron jobs for LearnKit email processing
 *
 * @link       https://jameswelbes.com
 * @since      0.5.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

/**
 * LearnKit Cron class.
 *
 * Registers and handles two recurring WP-Cron events:
 *  - learnkit_process_email_queue  (hourly)  — dispatches pending emails
 *  - learnkit_check_inactive_students (daily) — queues reminder emails
 *
 * @since      0.5.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Cron {

	/**
	 * Maximum emails to process per queue run.
	 *
	 * @since 0.5.0
	 * @var   int
	 */
	const MAX_PER_RUN = 50;

	/**
	 * Register action hooks for cron callbacks.
	 *
	 * @since 0.5.0
	 */
	public static function init() {
		add_action( 'learnkit_process_email_queue', array( __CLASS__, 'process_email_queue' ) );
		add_action( 'learnkit_check_inactive_students', array( __CLASS__, 'check_inactive_students' ) );
	}

	/**
	 * Schedule cron events on plugin activation.
	 *
	 * @since 0.5.0
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( 'learnkit_process_email_queue' ) ) {
			wp_schedule_event( time(), 'hourly', 'learnkit_process_email_queue' );
		}
		if ( ! wp_next_scheduled( 'learnkit_check_inactive_students' ) ) {
			wp_schedule_event( time(), 'daily', 'learnkit_check_inactive_students' );
		}
	}

	/**
	 * Clear cron events on plugin deactivation.
	 *
	 * @since 0.5.0
	 */
	public static function deactivate() {
		$queue_timestamp    = wp_next_scheduled( 'learnkit_process_email_queue' );
		$inactive_timestamp = wp_next_scheduled( 'learnkit_check_inactive_students' );

		if ( $queue_timestamp ) {
			wp_unschedule_event( $queue_timestamp, 'learnkit_process_email_queue' );
		}
		if ( $inactive_timestamp ) {
			wp_unschedule_event( $inactive_timestamp, 'learnkit_check_inactive_students' );
		}
	}

	// -------------------------------------------------------------------------
	// Cron callbacks
	// -------------------------------------------------------------------------

	/**
	 * Process up to MAX_PER_RUN pending queue items.
	 *
	 * @since 0.5.0
	 */
	public static function process_email_queue() {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_email_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
				"SELECT * FROM $table WHERE status = 'pending' AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d",
				current_time( 'mysql', true ),
				self::MAX_PER_RUN
			)
		);

		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			try {
				$success = LearnKit_Emails::send( $item );

				if ( $success ) {
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$table,
						array(
							'status'  => 'sent',
							'sent_at' => current_time( 'mysql', true ),
						),
						array( 'id' => (int) $item->id ),
						array( '%s', '%s' ),
						array( '%d' )
					);
				} else {
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$table,
						array( 'status' => 'failed' ),
						array( 'id' => (int) $item->id ),
						array( '%s' ),
						array( '%d' )
					);
				}
			} catch ( Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[LearnKit] Email send failed for queue item ' . $item->id . ': ' . $e->getMessage() );
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$table,
					array( 'status' => 'failed' ),
					array( 'id' => (int) $item->id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Find inactive students and queue reminder emails.
	 *
	 * "Inactive" = enrolled longer than the configured reminder_delay (default 7 days)
	 * with no progress in the same period.
	 *
	 * @since 0.5.0
	 */
	public static function check_inactive_students() {
		global $wpdb;

		$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';
		$progress_table    = $wpdb->prefix . 'learnkit_progress';
		$queue_table       = $wpdb->prefix . 'learnkit_email_queue';

		$settings   = get_option( 'learnkit_email_settings', array() );
		$delay_days = isset( $settings['reminder_delay'] ) ? max( 1, (int) $settings['reminder_delay'] ) : 7;
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$delay_days} days" ) );

		// Get enrollments older than $delay_days days where user has made no progress recently.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$inactive = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.user_id, e.course_id
				FROM $enrollments_table e
				WHERE e.status = 'active'
				AND e.enrolled_at <= %s
				AND NOT EXISTS (
					SELECT 1 FROM $progress_table p
					INNER JOIN {$wpdb->posts} l ON p.lesson_id = l.ID
					INNER JOIN {$wpdb->postmeta} pm ON l.ID = pm.post_id
						AND pm.meta_key = '_lk_module_id'
					INNER JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = CAST(pm.meta_value AS UNSIGNED)
						AND pm2.meta_key = '_lk_course_id'
						AND pm2.meta_value = e.course_id
					WHERE p.user_id = e.user_id
					AND p.completed_at >= %s
				)",
				$cutoff,
				$cutoff
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $inactive ) ) {
			return;
		}

		foreach ( $inactive as $row ) {
			$user_id   = (int) $row->user_id;
			$course_id = (int) $row->course_id;

			// Skip if a reminder was already queued in the last $delay_days days.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$recent_reminder = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
					"SELECT COUNT(*) FROM $queue_table
					WHERE user_id = %d AND course_id = %d AND email_type = 'reminder'
					AND scheduled_at >= %s",
					$user_id,
					$course_id,
					$cutoff
				)
			);

			if ( $recent_reminder > 0 ) {
				continue;
			}

			LearnKit_Emails::queue_email(
				$user_id,
				$course_id,
				'reminder',
				current_time( 'mysql', true ),
				array()
			);
		}
	}
}
