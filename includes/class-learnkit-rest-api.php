<?php
/**
 * REST API endpoints for LearnKit
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

/**
 * REST API endpoints for LearnKit.
 *
 * Registers all REST API controllers and routes.
 * API-first architecture: all admin UI functionality is built on these endpoints.
 *
 * @since      0.1.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_REST_API {

	/**
	 * The controllers for REST API endpoints.
	 *
	 * @since    0.2.13
	 * @access   private
	 * @var      array    $controllers    Array of controller instances.
	 */
	private $controllers = array();

	/**
	 * Initialize the class and set up REST API hooks.
	 *
	 * @since    0.2.13
	 */
	public function __construct() {
		// Load controller classes.
		require_once plugin_dir_path( __FILE__ ) . 'rest-controllers/class-learnkit-courses-controller.php';
		require_once plugin_dir_path( __FILE__ ) . 'rest-controllers/class-learnkit-modules-controller.php';
		require_once plugin_dir_path( __FILE__ ) . 'rest-controllers/class-learnkit-lessons-controller.php';

		// Instantiate controllers.
		$this->controllers['courses'] = new LearnKit_Courses_Controller();
		$this->controllers['modules'] = new LearnKit_Modules_Controller();
		$this->controllers['lessons'] = new LearnKit_Lessons_Controller();
	}

	/**
	 * Register REST API routes.
	 *
	 * Delegates route registration to individual controllers.
	 *
	 * @since    0.1.0
	 */
	public function register_routes() {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
