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
		$this->set_locale();
		$this->register_post_types();
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
	 * - LearnKit_i18n. Defines internationalization functionality.
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
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-i18n.php';

		/**
		 * The class responsible for defining custom post types.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-post-types.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'admin/class-learnkit-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'public/class-learnkit-public.php';

		/**
		 * The class responsible for defining REST API endpoints.
		 */
		require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-rest-api.php';

		$this->loader = new LearnKit_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the LearnKit_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new LearnKit_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register custom post types.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function register_post_types() {
		$post_types = new LearnKit_Post_Types();

		$this->loader->add_action( 'init', $post_types, 'register_course_post_type' );
		$this->loader->add_action( 'init', $post_types, 'register_module_post_type' );
		$this->loader->add_action( 'init', $post_types, 'register_lesson_post_type' );
		$this->loader->add_action( 'init', $post_types, 'register_post_meta_fields' );
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
