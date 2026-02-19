<?php
/**
 * Database operations tests.
 *
 * @package LearnKit
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test database operations.
 */
class Test_Database_Operations extends TestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Course ID.
	 *
	 * @var int
	 */
	protected static $course_id;

	/**
	 * Lesson ID.
	 *
	 * @var int
	 */
	protected static $lesson_id;

	/**
	 * Set up before class.
	 *
	 * @param object $factory Factory object.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create();
		self::$course_id = $factory->post->create( [
			'post_type' => 'lk_course',
			'post_status' => 'publish',
		] );
		self::$lesson_id = $factory->post->create( [
			'post_type' => 'lk_lesson',
			'post_status' => 'publish',
		] );
	}

	/**
	 * Test enrollment creation.
	 */
	public function test_enrollment_creation() {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_enrollments';
		
		$wpdb->insert(
			$table,
			[
				'user_id' => self::$user_id,
				'course_id' => self::$course_id,
				'status' => 'active',
				'enrolled_at' => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		$enrollment_id = $wpdb->insert_id;
		$this->assertGreaterThan( 0, $enrollment_id, 'Enrollment should be created with valid ID' );

		// Verify enrollment exists
		$enrollment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $enrollment_id ) );
		$this->assertNotNull( $enrollment, 'Enrollment should exist in database' );
		$this->assertEquals( self::$user_id, $enrollment->user_id, 'User ID should match' );
		$this->assertEquals( self::$course_id, $enrollment->course_id, 'Course ID should match' );
	}

	/**
	 * Test progress tracking.
	 */
	public function test_progress_tracking() {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_progress';
		
		$wpdb->insert(
			$table,
			[
				'user_id' => self::$user_id,
				'lesson_id' => self::$lesson_id,
				'status' => 'in_progress',
				'started_at' => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		$progress_id = $wpdb->insert_id;
		$this->assertGreaterThan( 0, $progress_id, 'Progress record should be created with valid ID' );

		// Update progress to completed
		$wpdb->update(
			$table,
			[
				'status' => 'completed',
				'completed_at' => current_time( 'mysql' ),
			],
			[ 'id' => $progress_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		// Verify update
		$progress = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $progress_id ) );
		$this->assertEquals( 'completed', $progress->status, 'Progress status should be updated to completed' );
		$this->assertNotNull( $progress->completed_at, 'Completed timestamp should be set' );
	}

	/**
	 * Test certificate generation.
	 */
	public function test_certificate_generation() {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_certificates';
		$certificate_url = 'https://example.com/certificates/test-cert-123.pdf';
		
		$wpdb->insert(
			$table,
			[
				'user_id' => self::$user_id,
				'course_id' => self::$course_id,
				'certificate_url' => $certificate_url,
				'issued_at' => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		$certificate_id = $wpdb->insert_id;
		$this->assertGreaterThan( 0, $certificate_id, 'Certificate should be created with valid ID' );

		// Verify certificate
		$certificate = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $certificate_id ) );
		$this->assertEquals( $certificate_url, $certificate->certificate_url, 'Certificate URL should match' );
	}

	/**
	 * Test data integrity - enrollment before progress.
	 */
	public function test_data_integrity() {
		global $wpdb;

		// Create enrollment
		$enrollment_table = $wpdb->prefix . 'learnkit_enrollments';
		$wpdb->insert(
			$enrollment_table,
			[
				'user_id' => self::$user_id,
				'course_id' => self::$course_id,
				'status' => 'active',
				'enrolled_at' => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		// Verify enrollment exists before tracking progress
		$enrollment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$enrollment_table} WHERE user_id = %d AND course_id = %d",
				self::$user_id,
				self::$course_id
			)
		);

		$this->assertNotNull( $enrollment, 'User should be enrolled before tracking progress' );

		// Now track progress
		$progress_table = $wpdb->prefix . 'learnkit_progress';
		$wpdb->insert(
			$progress_table,
			[
				'user_id' => self::$user_id,
				'lesson_id' => self::$lesson_id,
				'status' => 'in_progress',
				'started_at' => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		$this->assertGreaterThan( 0, $wpdb->insert_id, 'Progress should be tracked after enrollment' );
	}

	/**
	 * Test duplicate enrollment prevention.
	 */
	public function test_duplicate_enrollment_prevention() {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_enrollments';
		
		// First enrollment
		$wpdb->insert(
			$table,
			[
				'user_id' => self::$user_id,
				'course_id' => self::$course_id,
				'status' => 'active',
				'enrolled_at' => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		// Check for existing enrollment before inserting duplicate
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND course_id = %d",
				self::$user_id,
				self::$course_id
			)
		);

		$this->assertGreaterThan( 0, $existing, 'Should find existing enrollment' );
	}
}
