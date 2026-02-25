<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.1.0
 * @package    LearnKit
 * @subpackage LearnKit/includes
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    0.1.0
	 * @access   protected
	 * @var      LearnKit_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    0.1.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    0.1.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    0.1.0
	 */
	public function __construct() {
		if ( defined( 'LEARNKIT_VERSION' ) ) {
			$this->version = LEARNKIT_VERSION;
		} else {
			$this->version = '0.1.0';
		}
		$this->plugin_name = 'learnkit';

		$this->load_dependencies();
		$this->register_post_types();
		$this->register_meta_boxes();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->register_rest_api();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - LearnKit_Loader. Orchestrates the hooks of the plugin.
	 * - LearnKit_Admin. Defines all hooks for the admin area.
	 * - LearnKit_Public. Defines all hooks for the public side of the site.
	 * - LearnKit_Post_Types. Registers custom post types.
	 * - LearnKit_REST_API. Registers REST API endpoints.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-loader.php';

		/**
		 * Template helper functions (e.g. learnkit_button_classes).
		 * Guard against double-load since learnkit.php now requires this earlier.
		 */
		if ( ! function_exists( 'learnkit_button_classes' ) ) {
			require_once LEARNKIT_PLUGIN_DIR . 'includes/learnkit-template-helpers.php';
		}

		/**
		 * The class responsible for defining custom post types.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-post-types.php';

		/**
		 * The class responsible for quiz custom post type.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-quiz-cpt.php';

		/**
		 * The class responsible for defining meta boxes for relationships.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-meta-boxes.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'admin/class-learnkit-admin.php';

		/**
		 * The class responsible for quiz reports.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'admin/class-learnkit-quiz-reports.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'public/class-learnkit-public.php';

		/**
		 * The class responsible for student dashboard functionality.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'public/class-learnkit-student-dashboard.php';

		/**
		 * The class responsible for course catalog functionality.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'public/class-learnkit-course-catalog.php';

		/**
		 * The class responsible for certificate generation.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'public/class-learnkit-certificate-generator.php';

		/**
		 * The class responsible for defining REST API endpoints.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-rest-api.php';

		/**
		 * Etch integration — injects nested relational data into Etch's dynamic
		 * data payload for LearnKit post types. Only loaded when Etch is active.
		 */
		if ( class_exists( 'Etch\Plugin' ) ) {
			require_once LEARNKIT_PLUGIN_DIR . 'includes/etch-resolvers/class-learnkit-etch-course-resolver.php';
			require_once LEARNKIT_PLUGIN_DIR . 'includes/etch-resolvers/class-learnkit-etch-module-resolver.php';
			require_once LEARNKIT_PLUGIN_DIR . 'includes/etch-resolvers/class-learnkit-etch-lesson-resolver.php';
			require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-etch-integration.php';
		}

		$this->loader = new LearnKit_Loader();
	}

	/**
	 * Register custom post types.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function register_post_types() {
		$post_types = new LearnKit_Post_Types();
		$quiz_cpt   = new LearnKit_Quiz_CPT();

		$this->loader->add_action( 'init', $post_types, 'register_course_post_type' );
		$this->loader->add_action( 'init', $post_types, 'register_module_post_type' );
		$this->loader->add_action( 'init', $post_types, 'register_lesson_post_type' );
		$this->loader->add_action( 'init', $post_types, 'register_post_meta_fields' );
		$this->loader->add_action( 'init', $quiz_cpt, 'register' );
	}

	/**
	 * Register meta boxes for course/module relationships.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function register_meta_boxes() {
		$meta_boxes = new LearnKit_Meta_Boxes();

		$this->loader->add_action( 'add_meta_boxes', $meta_boxes, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post_lk_module', $meta_boxes, 'save_module_meta' );
		$this->loader->add_action( 'save_post_lk_lesson', $meta_boxes, 'save_lesson_meta' );

		// Admin columns.
		$this->loader->add_filter( 'manage_lk_module_posts_columns', $meta_boxes, 'add_module_columns' );
		$this->loader->add_action( 'manage_lk_module_posts_custom_column', $meta_boxes, 'populate_module_columns', 10, 2 );
		$this->loader->add_filter( 'manage_lk_lesson_posts_columns', $meta_boxes, 'add_lesson_columns' );
		$this->loader->add_action( 'manage_lk_lesson_posts_custom_column', $meta_boxes, 'populate_lesson_columns', 10, 2 );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new LearnKit_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );

		// Quiz reports.
		$quiz_reports = new LearnKit_Quiz_Reports();
		$this->loader->add_action( 'admin_menu', $quiz_reports, 'add_menu_page' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new LearnKit_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Filter post_type_link so that get_permalink() for our CPTs returns the
		// template page URL when one is configured. This covers the admin "View"
		// link, the REST API permalink field, and any direct get_permalink() calls.
		add_filter(
			'post_type_link',
			function ( $url, $post ) {
				// Use post_name directly to avoid re-calling get_permalink() which
				// would trigger this filter again causing infinite recursion.
				$slug = $post->post_name;
				if ( ! $slug ) {
					return $url;
				}

				if ( 'lk_course' === $post->post_type ) {
					static $course_base = null;
					if ( null === $course_base ) {
						$course_base = get_option( 'learnkit_course_page' )
							? ( LearnKit_Rewrite::get_base( 'learnkit_course_page' ) ?? '' )
							: '';
					}
					if ( $course_base ) {
						return home_url( $course_base . '/' . $slug . '/' );
					}
				} elseif ( 'lk_lesson' === $post->post_type ) {
					static $lesson_base = null;
					if ( null === $lesson_base ) {
						$lesson_base = get_option( 'learnkit_lesson_page' )
							? ( LearnKit_Rewrite::get_base( 'learnkit_lesson_page' ) ?? '' )
							: '';
					}
					if ( $lesson_base ) {
						return home_url( $lesson_base . '/' . $slug . '/' );
					}
				} elseif ( 'lk_quiz' === $post->post_type ) {
					static $quiz_base = null;
					if ( null === $quiz_base ) {
						$quiz_base = get_option( 'learnkit_quiz_page' )
							? ( LearnKit_Rewrite::get_base( 'learnkit_quiz_page' ) ?? '' )
							: '';
					}
					if ( $quiz_base ) {
						return home_url( $quiz_base . '/' . $slug . '/' );
					}
				}

				// No template page configured — return the original CPT URL unchanged.
				return $url;
			},
			10,
			2
		);

		// Register student dashboard.
		$student_dashboard = new LearnKit_Student_Dashboard();
		$student_dashboard->register();

		// Register course catalog.
		$course_catalog = new LearnKit_Course_Catalog();
		$course_catalog->register();

		// Register certificate generator.
		$certificate_generator = new LearnKit_Certificate_Generator();
		$certificate_generator->register();

		// Register Etch integration — only when Etch is active.
		if ( class_exists( 'Etch\Plugin' ) ) {
			$etch_integration = new LearnKit_Etch_Integration();
			$this->loader->add_filter( 'etch/dynamic_data/post', $etch_integration, 'inject', 10, 2 );
		}
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function register_rest_api() {
		$rest_api = new LearnKit_REST_API();

		$this->loader->add_action( 'rest_api_init', $rest_api, 'register_routes' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    0.1.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     0.1.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     0.1.0
	 * @return    LearnKit_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     0.1.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
