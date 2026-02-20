<?php
/**
 * Public Enrollment API functions
 *
 * Provides a clean, hookable API for enrolling/unenrolling users in courses.
 * Third-party integrations (WooCommerce, membership plugins, etc.) should use
 * these functions instead of writing directly to the enrollments table.
 *
 * @link       https://jameswelbes.com
 * @since      0.4.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Enroll a user in a course.
 *
 * Inserts a row into wp_learnkit_enrollments (or re-activates an inactive one)
 * and fires the `learnkit_user_enrolled` action.
 *
 * @since 0.4.0
 *
 * @param int    $user_id   WordPress user ID.
 * @param int    $course_id Post ID of the lk_course.
 * @param string $source    Optional. Origin of the enrollment. Default 'manual'.
 * @param string $expires_at Optional. MySQL datetime string for expiry or empty string for lifetime.
 * @return int|false The enrollment row ID on success, false on failure.
 */
function learnkit_enroll_user( $user_id, $course_id, $source = 'manual', $expires_at = '' ) {
	global $wpdb;

	$user_id   = (int) $user_id;
	$course_id = (int) $course_id;

	if ( ! $user_id || ! $course_id ) {
		return false;
	}

	$table = $wpdb->prefix . 'learnkit_enrollments';

	// Check whether a row already exists (active or inactive).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$existing = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely prefixed.
			"SELECT id, status FROM $table WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course_id
		)
	);

	if ( $existing ) {
		if ( 'active' === $existing->status ) {
			// Already enrolled; nothing to do.
			return (int) $existing->id;
		}

		// Re-activate.
		$data   = array(
			'status'      => 'active',
			'source'      => sanitize_text_field( $source ),
			'expires_at'  => ! empty( $expires_at ) ? $expires_at : null,
			'enrolled_at' => current_time( 'mysql' ),
		);
		$format = array( '%s', '%s', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$table,
			$data,
			array(
				'id' => (int) $existing->id,
			),
			$format,
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		/**
		 * Fires after a user is enrolled in a course.
		 *
		 * @since 0.4.0
		 *
		 * @param int $user_id   The enrolled user ID.
		 * @param int $course_id The course post ID.
		 */
		do_action( 'learnkit_user_enrolled', $user_id, $course_id );

		return (int) $existing->id;
	}

	// Insert fresh enrollment.
	$insert_data   = array(
		'user_id'     => $user_id,
		'course_id'   => $course_id,
		'enrolled_at' => current_time( 'mysql' ),
		'status'      => 'active',
		'source'      => sanitize_text_field( $source ),
	);
	$insert_format = array( '%d', '%d', '%s', '%s', '%s' );

	if ( ! empty( $expires_at ) ) {
		$insert_data['expires_at'] = $expires_at;
		$insert_format[]           = '%s';
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$inserted = $wpdb->insert( $table, $insert_data, $insert_format );

	if ( false === $inserted ) {
		return false;
	}

	$enrollment_id = (int) $wpdb->insert_id;

	/** This action is documented in includes/learnkit-enrollment-api.php */
	do_action( 'learnkit_user_enrolled', $user_id, $course_id );

	return $enrollment_id;
}

/**
 * Unenroll (deactivate) a user from a course.
 *
 * Sets the enrollment status to 'inactive' and fires the
 * `learnkit_user_unenrolled` action.
 *
 * @since 0.4.0
 *
 * @param int $user_id   WordPress user ID.
 * @param int $course_id Post ID of the lk_course.
 * @return bool True on success, false on failure.
 */
function learnkit_unenroll_user( $user_id, $course_id ) {
	global $wpdb;

	$user_id   = (int) $user_id;
	$course_id = (int) $course_id;

	if ( ! $user_id || ! $course_id ) {
		return false;
	}

	$table = $wpdb->prefix . 'learnkit_enrollments';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$updated = $wpdb->update(
		$table,
		array( 'status' => 'inactive' ),
		array(
			'user_id'   => $user_id,
			'course_id' => $course_id,
		),
		array( '%s' ),
		array( '%d', '%d' )
	);

	if ( false === $updated ) {
		return false;
	}

	/**
	 * Fires after a user is unenrolled from a course.
	 *
	 * @since 0.4.0
	 *
	 * @param int $user_id   The unenrolled user ID.
	 * @param int $course_id The course post ID.
	 */
	do_action( 'learnkit_user_unenrolled', $user_id, $course_id );

	return true;
}

/**
 * Check whether a user is actively enrolled in a course.
 *
 * Passes the result through the `learnkit_user_can_access_course` filter
 * so membership plugins can grant or restrict access without direct DB writes.
 *
 * @since 0.4.0
 *
 * @param int $user_id   WordPress user ID.
 * @param int $course_id Post ID of the lk_course.
 * @return bool True if the user has active access, false otherwise.
 */
function learnkit_is_enrolled( $user_id, $course_id ) {
	global $wpdb;

	$user_id   = (int) $user_id;
	$course_id = (int) $course_id;

	if ( ! $user_id || ! $course_id ) {
		return false;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$is_enrolled = (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}learnkit_enrollments WHERE user_id = %d AND course_id = %d AND status = 'active'",
			$user_id,
			$course_id
		)
	);

	/**
	 * Filter whether a user can access a course.
	 *
	 * Membership plugins and other integrations can hook into this filter to
	 * grant or restrict access independently of the enrollments table.
	 *
	 * @since 0.4.0
	 *
	 * @param bool $is_enrolled Whether the user has an active enrollment record.
	 * @param int  $user_id     The user being checked.
	 * @param int  $course_id   The course being checked.
	 */
	return (bool) apply_filters( 'learnkit_user_can_access_course', $is_enrolled, $user_id, $course_id );
}
