<?php
/**
 * Class Test_Plugin_Activation
 *
 * @package LearnKit
 */

/**
 * Test plugin activation and basic setup.
 */
class Test_Plugin_Activation extends WP_UnitTestCase {

	/**
	 * Test that plugin is loaded.
	 */
	public function test_plugin_loaded() {
		$this->assertTrue( defined( 'LEARNKIT_VERSION' ) );
		$this->assertTrue( defined( 'LEARNKIT_PLUGIN_DIR' ) );
	}

	/**
	 * Test custom post types are registered.
	 */
	public function test_cpt_registered() {
		$this->assertTrue( post_type_exists( 'lk_course' ) );
		$this->assertTrue( post_type_exists( 'lk_module' ) );
		$this->assertTrue( post_type_exists( 'lk_lesson' ) );
	}

	/**
	 * Test that custom tables exist.
	 */
	public function test_custom_tables_exist() {
		global $wpdb;
		
		$enrollments_table = $wpdb->prefix . 'learnkit_enrollments';
		$progress_table    = $wpdb->prefix . 'learnkit_progress';
		
		// Check table exists.
		$enrollments_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $enrollments_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$progress_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $progress_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		
		$this->assertEquals( $enrollments_table, $enrollments_exists );
		$this->assertEquals( $progress_table, $progress_exists );
	}
}
