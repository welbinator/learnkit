<?php
/**
 * REST API tests.
 *
 * @package LearnKit
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test REST API endpoints.
 */
class Test_REST_API extends TestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Test course ID.
	 *
	 * @var int
	 */
	protected static $course_id;

	/**
	 * Set up before class.
	 *
	 * @param object $factory Factory object.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id = $factory->user->create( [ 'role' => 'administrator' ] );
		self::$course_id = $factory->post->create( [
			'post_type' => 'lk_course',
			'post_title' => 'Test Course',
			'post_status' => 'publish',
		] );
	}

	/**
	 * Test courses endpoint returns 200.
	 */
	public function test_courses_endpoint_returns_200() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/courses' );
		$response = rest_do_request( $request );
		
		$this->assertEquals( 200, $response->get_status(), 'Courses endpoint should return 200' );
	}

	/**
	 * Test courses endpoint returns courses.
	 */
	public function test_courses_endpoint_returns_courses() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/courses' );
		$response = rest_do_request( $request );
		$data = $response->get_data();
		
		$this->assertIsArray( $data, 'Response should be an array' );
		$this->assertGreaterThan( 0, count( $data ), 'Should return at least one course' );
	}

	/**
	 * Test POST requires authentication.
	 */
	public function test_post_requires_authentication() {
		$request = new WP_REST_Request( 'POST', '/wp/v2/courses' );
		$request->set_param( 'title', 'New Course' );
		$request->set_param( 'status', 'publish' );
		
		$response = rest_do_request( $request );
		
		$this->assertEquals( 401, $response->get_status(), 'POST without auth should return 401' );
	}

	/**
	 * Test authenticated POST creates course.
	 */
	public function test_authenticated_post_creates_course() {
		wp_set_current_user( self::$admin_id );
		
		$request = new WP_REST_Request( 'POST', '/wp/v2/courses' );
		$request->set_param( 'title', 'New Test Course' );
		$request->set_param( 'status', 'publish' );
		
		$response = rest_do_request( $request );
		
		$this->assertEquals( 201, $response->get_status(), 'Authenticated POST should return 201' );
		
		$data = $response->get_data();
		$this->assertEquals( 'New Test Course', $data['title']['rendered'], 'Course title should match' );
	}

	/**
	 * Test course retrieval.
	 */
	public function test_course_retrieval() {
		$request = new WP_REST_Request( 'GET', '/wp/v2/courses/' . self::$course_id );
		$response = rest_do_request( $request );
		
		$this->assertEquals( 200, $response->get_status(), 'GET course should return 200' );
		
		$data = $response->get_data();
		$this->assertEquals( self::$course_id, $data['id'], 'Course ID should match' );
		$this->assertEquals( 'Test Course', $data['title']['rendered'], 'Course title should match' );
	}

	/**
	 * Test DELETE requires authentication.
	 */
	public function test_delete_requires_authentication() {
		$request = new WP_REST_Request( 'DELETE', '/wp/v2/courses/' . self::$course_id );
		$response = rest_do_request( $request );
		
		$this->assertEquals( 401, $response->get_status(), 'DELETE without auth should return 401' );
	}

	/**
	 * Test enrollment endpoint exists.
	 */
	public function test_enrollment_endpoint_exists() {
		$request = new WP_REST_Request( 'GET', '/learnkit/v1/enrollments' );
		$response = rest_do_request( $request );
		
		// Should return 401 (requires auth) not 404 (doesn't exist)
		$this->assertNotEquals( 404, $response->get_status(), 'Enrollments endpoint should exist' );
	}

	/**
	 * Test progress endpoint exists.
	 */
	public function test_progress_endpoint_exists() {
		$request = new WP_REST_Request( 'GET', '/learnkit/v1/progress' );
		$response = rest_do_request( $request );
		
		// Should return 401 (requires auth) not 404 (doesn't exist)
		$this->assertNotEquals( 404, $response->get_status(), 'Progress endpoint should exist' );
	}
}
