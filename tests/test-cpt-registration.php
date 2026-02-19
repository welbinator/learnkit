<?php
/**
 * Custom Post Type registration tests.
 *
 * @package LearnKit
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test CPT registration.
 */
class Test_CPT_Registration extends TestCase {

	/**
	 * Test lk_course CPT is registered.
	 */
	public function test_course_cpt_registered() {
		$this->assertTrue( post_type_exists( 'lk_course' ), 'lk_course post type should be registered' );
	}

	/**
	 * Test lk_module CPT is registered.
	 */
	public function test_module_cpt_registered() {
		$this->assertTrue( post_type_exists( 'lk_module' ), 'lk_module post type should be registered' );
	}

	/**
	 * Test lk_lesson CPT is registered.
	 */
	public function test_lesson_cpt_registered() {
		$this->assertTrue( post_type_exists( 'lk_lesson' ), 'lk_lesson post type should be registered' );
	}

	/**
	 * Test course CPT has REST API enabled.
	 */
	public function test_course_rest_api_enabled() {
		$post_type = get_post_type_object( 'lk_course' );
		$this->assertTrue( $post_type->show_in_rest, 'lk_course should have REST API enabled' );
		$this->assertEquals( 'courses', $post_type->rest_base, 'lk_course REST base should be "courses"' );
	}

	/**
	 * Test module CPT has REST API enabled.
	 */
	public function test_module_rest_api_enabled() {
		$post_type = get_post_type_object( 'lk_module' );
		$this->assertTrue( $post_type->show_in_rest, 'lk_module should have REST API enabled' );
		$this->assertEquals( 'modules', $post_type->rest_base, 'lk_module REST base should be "modules"' );
	}

	/**
	 * Test lesson CPT has REST API enabled.
	 */
	public function test_lesson_rest_api_enabled() {
		$post_type = get_post_type_object( 'lk_lesson' );
		$this->assertTrue( $post_type->show_in_rest, 'lk_lesson should have REST API enabled' );
		$this->assertEquals( 'lessons', $post_type->rest_base, 'lk_lesson REST base should be "lessons"' );
	}

	/**
	 * Test course CPT supports expected features.
	 */
	public function test_course_supports() {
		$post_type = get_post_type_object( 'lk_course' );
		$supports = get_all_post_type_supports( 'lk_course' );
		
		$this->assertArrayHasKey( 'title', $supports, 'lk_course should support title' );
		$this->assertArrayHasKey( 'editor', $supports, 'lk_course should support editor' );
		$this->assertArrayHasKey( 'thumbnail', $supports, 'lk_course should support thumbnail' );
	}
}
