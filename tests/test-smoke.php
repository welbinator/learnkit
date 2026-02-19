<?php
/**
 * Basic smoke tests - plugin loads without errors.
 *
 * @package LearnKit
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test basic plugin functionality.
 */
class Test_Smoke extends TestCase {

	/**
	 * Test plugin main class exists.
	 */
	public function test_plugin_class_exists() {
		$this->assertTrue( class_exists( 'LearnKit' ), 'LearnKit main class should exist' );
	}

	/**
	 * Test plugin activator class exists.
	 */
	public function test_activator_class_exists() {
		$this->assertTrue( class_exists( 'LearnKit_Activator' ), 'LearnKit_Activator class should exist' );
	}

	/**
	 * Test plugin deactivator class exists.
	 */
	public function test_deactivator_class_exists() {
		$this->assertTrue( class_exists( 'LearnKit_Deactivator' ), 'LearnKit_Deactivator class should exist' );
	}

	/**
	 * Test REST API class exists.
	 */
	public function test_rest_api_class_exists() {
		$this->assertTrue( class_exists( 'LearnKit_REST_API' ), 'LearnKit_REST_API class should exist' );
	}

	/**
	 * Test Post Types class exists.
	 */
	public function test_post_types_class_exists() {
		$this->assertTrue( class_exists( 'LearnKit_Post_Types' ), 'LearnKit_Post_Types class should exist' );
	}

	/**
	 * Test plugin version constant is defined.
	 */
	public function test_version_constant() {
		$this->assertTrue( defined( 'LEARNKIT_VERSION' ), 'LEARNKIT_VERSION should be defined' );
		$this->assertNotEmpty( LEARNKIT_VERSION, 'LEARNKIT_VERSION should not be empty' );
	}

	/**
	 * Test plugin loads without fatal errors.
	 */
	public function test_plugin_loads_without_errors() {
		// If we got here, the plugin loaded successfully in bootstrap
		$this->assertTrue( true, 'Plugin loaded without fatal errors' );
	}

	/**
	 * Test composer autoload works.
	 */
	public function test_composer_autoload() {
		$this->assertTrue( class_exists( 'Yoast\PHPUnitPolyfills\TestCases\TestCase' ), 'Composer autoload should work' );
	}
}
