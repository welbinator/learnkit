<?php
/**
 * Plugin Name: LearnKit
 * Plugin URI: https://github.com/welbinator/learnkit
 * Description: Modern WordPress LMS plugin for course creators who value simplicity, performance, and fair pricing. Create, deliver, and monetize online courses with a beautiful, intuitive interface.
 * Version: 0.5.2
 * Author: James Welbes
 * Author URI: https://jameswelbes.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learnkit
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 *
 * @package LearnKit
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 0.1.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'LEARNKIT_VERSION', '0.5.0' );

/**
 * Plugin directory path.
 */
define( 'LEARNKIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'LEARNKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'LEARNKIT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * REST API namespace.
 */
define( 'LEARNKIT_REST_NAMESPACE', 'learnkit/v1' );

/**
 * Load Composer autoloader for dependencies (FPDF for certificates).
 */
require_once LEARNKIT_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Public Enrollment API — must be loaded before any code that calls
 * learnkit_enroll_user(), learnkit_unenroll_user(), or learnkit_is_enrolled().
 */
require_once LEARNKIT_PLUGIN_DIR . 'includes/learnkit-enrollment-api.php';

/**
 * Email notifications system.
 */
require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-emails.php';
LearnKit_Emails::init();

/**
 * Drip content system.
 */
require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-drip.php';

/**
 * Cron jobs for email processing.
 */
require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-cron.php';
LearnKit_Cron::init();

// Hook enrollment → welcome email.
add_action( 'learnkit_user_enrolled', array( 'LearnKit_Emails', 'schedule_welcome_email' ), 10, 2 );

// Hook course completion → completion email.
add_action( 'learnkit_user_completed_course', array( 'LearnKit_Emails', 'schedule_completion_email' ), 10, 2 );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-learnkit-activator.php
 */
function activate_learnkit() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Standard WP plugin activation hook pattern.
	require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-activator.php';
	require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-cron.php';
	LearnKit_Activator::activate();
	LearnKit_Cron::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-learnkit-deactivator.php
 */
function deactivate_learnkit() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Standard WP plugin deactivation hook pattern.
	require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-deactivator.php';
	require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-cron.php';
	LearnKit_Deactivator::deactivate();
	LearnKit_Cron::deactivate();
}

register_activation_hook( __FILE__, 'activate_learnkit' );
register_deactivation_hook( __FILE__, 'deactivate_learnkit' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 0.1.0
 */
function run_learnkit() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Standard WP plugin bootstrap pattern.
	$plugin = new LearnKit();
	$plugin->run();
}
run_learnkit();

/**
 * Load WooCommerce integration when WooCommerce is active.
 *
 * We hook into `plugins_loaded` so WooCommerce is guaranteed to have
 * registered its classes before we try to use them.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'WooCommerce' ) ) {
			require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-woocommerce.php';

			$learnkit_woo = new LearnKit_WooCommerce();
			$learnkit_woo->register();

			// Hook the Buy Now / Enrolled badge CTA into the course template action.
			add_action(
				'learnkit_course_enrollment_cta',
				array( 'LearnKit_WooCommerce', 'render_course_cta' ),
				10,
				3
			);
		}
	}
);
