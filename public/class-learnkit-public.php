<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://jameswelbes.com
 * @since      0.1.0
 *
 * @package    LearnKit
 * @subpackage LearnKit/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueuing
 * the public-facing stylesheet and JavaScript.
 *
 * @package    LearnKit
 * @subpackage LearnKit/public
 * @author     James Welbes <james.welbes@gmail.com>
 */
class LearnKit_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 * @param    string $plugin_name    The name of the plugin.
	 * @param    string $version        The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Hook template loading.
		add_filter( 'single_template', array( $this, 'load_lesson_template' ) );
	}

	/**
	 * Load custom template for single lessons.
	 *
	 * @since    0.2.13
	 * @param    string $template    The path to the template.
	 * @return   string             Modified template path.
	 */
	public function load_lesson_template( $template ) {
		if ( is_singular( 'lk_lesson' ) ) {
			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/single-lk-lesson.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_styles() {
		// Only load on LearnKit pages (courses, lessons).
		if ( ! is_singular( array( 'lk_course', 'lk_module', 'lk_lesson' ) ) ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			LEARNKIT_PLUGIN_URL . 'assets/css/learnkit-public.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_scripts() {
		// Only load on LearnKit pages.
		if ( ! is_singular( array( 'lk_course', 'lk_module', 'lk_lesson' ) ) ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			LEARNKIT_PLUGIN_URL . 'assets/js/learnkit-public.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Pass data to JavaScript for AJAX and API calls.
		wp_localize_script(
			$this->plugin_name,
			'learnkitPublic',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'apiUrl'      => rest_url( 'learnkit/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentUser' => get_current_user_id(),
				'isLoggedIn'  => is_user_logged_in(),
			)
		);
	}
}
