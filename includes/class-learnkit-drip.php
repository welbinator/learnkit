<?php
/**
 * Drip content scheduling for LearnKit
 *
 * @link       https://jameswelbes.com
 * @since      0.5.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

/**
 * LearnKit Drip class.
 *
 * Controls when lessons become available based on post meta.
 *
 * Meta keys on lk_lesson:
 *  - _lk_release_type  : immediate (default) | days_after_enrollment | specific_date
 *  - _lk_release_days  : int — days after enrollment date
 *  - _lk_release_date  : datetime string — specific unlock date
 *
 * @since      0.5.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Drip {

	/**
	 * Determine whether a lesson is currently available for a user.
	 *
	 * Always returns true for non-enrolled users (the enrollment gate handles
	 * that separately) and for lessons with release_type = immediate.
	 *
	 * @since  0.5.0
	 * @param  int $lesson_id Lesson post ID.
	 * @param  int $user_id   WordPress user ID.
	 * @return bool
	 */
	public static function is_lesson_available( $lesson_id, $user_id ) {
		$release_type = get_post_meta( $lesson_id, '_lk_release_type', true );

		// Default / no drip = always available.
		if ( ! $release_type || 'immediate' === $release_type ) {
			return true;
		}

		if ( 'specific_date' === $release_type ) {
			$release_date_str = get_post_meta( $lesson_id, '_lk_release_date', true );
			if ( ! $release_date_str ) {
				return true;
			}
			$release_dt = date_create( $release_date_str );
			if ( ! $release_dt ) {
				return true;
			}
			return $release_dt <= new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		}

		if ( 'days_after_enrollment' === $release_type ) {
			$release_days = (int) get_post_meta( $lesson_id, '_lk_release_days', true );
			if ( $release_days <= 0 ) {
				return true;
			}

			$enrolled_at = self::get_enrollment_date( $lesson_id, $user_id );
			if ( ! $enrolled_at ) {
				// Not enrolled — available by default (enrollment gate handles access).
				return true;
			}

			$unlock_dt = clone $enrolled_at;
			$unlock_dt->modify( '+' . $release_days . ' days' );

			return $unlock_dt <= new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		}

		return true;
	}

	/**
	 * Return the DateTime when a lesson unlocks, or null.
	 *
	 * @since  0.5.0
	 * @param  int $lesson_id Lesson post ID.
	 * @param  int $user_id   WordPress user ID.
	 * @return DateTime|null
	 */
	public static function get_unlock_date( $lesson_id, $user_id ) {
		$release_type = get_post_meta( $lesson_id, '_lk_release_type', true );

		if ( ! $release_type || 'immediate' === $release_type ) {
			return null;
		}

		if ( 'specific_date' === $release_type ) {
			$release_date_str = get_post_meta( $lesson_id, '_lk_release_date', true );
			if ( ! $release_date_str ) {
				return null;
			}
			$dt = date_create( $release_date_str );
			return $dt ? $dt : null;
		}

		if ( 'days_after_enrollment' === $release_type ) {
			$release_days = (int) get_post_meta( $lesson_id, '_lk_release_days', true );
			if ( $release_days <= 0 ) {
				return null;
			}

			$enrolled_at = self::get_enrollment_date( $lesson_id, $user_id );
			if ( ! $enrolled_at ) {
				return null;
			}

			$unlock_dt = clone $enrolled_at;
			$unlock_dt->modify( '+' . $release_days . ' days' );
			return $unlock_dt;
		}

		return null;
	}

	/**
	 * Queue a lesson_unlock email for the given unlock datetime.
	 *
	 * @since  0.5.0
	 * @param  int      $lesson_id        Lesson post ID.
	 * @param  int      $user_id          WordPress user ID.
	 * @param  DateTime $unlock_datetime   When the lesson unlocks.
	 */
	public static function schedule_lesson_unlock_email( $lesson_id, $user_id, $unlock_datetime ) {
		$module_id = get_post_meta( $lesson_id, '_lk_module_id', true );
		$course_id = $module_id ? (int) get_post_meta( $module_id, '_lk_course_id', true ) : 0;

		if ( ! $course_id ) {
			return;
		}

		LearnKit_Emails::queue_email(
			$user_id,
			$course_id,
			'lesson_unlock',
			$unlock_datetime->format( 'Y-m-d H:i:s' ),
			array( 'lesson_id' => (int) $lesson_id )
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the enrollment DateTime for the user/lesson combination.
	 *
	 * Looks up the course via the lesson → module → course chain.
	 *
	 * @since  0.5.0
	 * @param  int $lesson_id Lesson post ID.
	 * @param  int $user_id   WordPress user ID.
	 * @return DateTime|null
	 */
	private static function get_enrollment_date( $lesson_id, $user_id ) {
		global $wpdb;

		$module_id = get_post_meta( $lesson_id, '_lk_module_id', true );
		if ( ! $module_id ) {
			return null;
		}
		$course_id = get_post_meta( (int) $module_id, '_lk_course_id', true );
		if ( ! $course_id ) {
			return null;
		}

		$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$enrolled_at = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT enrolled_at FROM %i WHERE user_id = %d AND course_id = %d LIMIT 1',
				$enrollments_table,
				(int) $user_id,
				(int) $course_id
			)
		);

		if ( ! $enrolled_at ) {
			return null;
		}

		$dt = date_create( $enrolled_at, new DateTimeZone( 'UTC' ) );
		return $dt ? $dt : null;
	}
}
