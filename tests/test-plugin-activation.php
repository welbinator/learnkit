<?php
/**
 * Plugin activation tests.
 *
 * @package LearnKit
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test plugin activation.
 */
class Test_Plugin_Activation extends TestCase {

	/**
	 * Test plugin activates without errors.
	 */
	public function test_plugin_activates() {
		// Plugin should already be activated via bootstrap
		$this->assertTrue( class_exists( 'LearnKit' ), 'LearnKit main class should exist' );
	}

	/**
	 * Test database tables exist after activation.
	 */
	public function test_database_tables_exist() {
		global $wpdb;

		$tables = [
			$wpdb->prefix . 'learnkit_enrollments',
			$wpdb->prefix . 'learnkit_progress',
			$wpdb->prefix . 'learnkit_certificates',
		];

		foreach ( $tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$this->assertEquals( $table, $exists, "Table {$table} should exist" );
		}
	}

	/**
	 * Test enrollments table structure.
	 */
	public function test_enrollments_table_structure() {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_enrollments';
		$columns = $wpdb->get_col( "DESC {$table}", 0 );

		$expected_columns = [ 'id', 'user_id', 'course_id', 'status', 'enrolled_at', 'completed_at' ];
		
		foreach ( $expected_columns as $column ) {
			$this->assertContains( $column, $columns, "Enrollments table should have {$column} column" );
		}
	}

	/**
	 * Test progress table structure.
	 */
	public function test_progress_table_structure() {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_progress';
		$columns = $wpdb->get_col( "DESC {$table}", 0 );

		$expected_columns = [ 'id', 'user_id', 'lesson_id', 'status', 'started_at', 'completed_at' ];
		
		foreach ( $expected_columns as $column ) {
			$this->assertContains( $column, $columns, "Progress table should have {$column} column" );
		}
	}

	/**
	 * Test certificates table structure.
	 */
	public function test_certificates_table_structure() {
		global $wpdb;

		$table = $wpdb->prefix . 'learnkit_certificates';
		$columns = $wpdb->get_col( "DESC {$table}", 0 );

		$expected_columns = [ 'id', 'user_id', 'course_id', 'certificate_url', 'issued_at' ];
		
		foreach ( $expected_columns as $column ) {
			$this->assertContains( $column, $columns, "Certificates table should have {$column} column" );
		}
	}
}
