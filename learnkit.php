<?php
/**
 * Plugin Name: LearnKit
 * Plugin URI: https://github.com/welbinator/learnkit
 * Description: Modern WordPress LMS plugin for course creators who value simplicity, performance, and fair pricing. Create, deliver, and monetize online courses with a beautiful, intuitive interface.
 * Version: 0.2.15
 * Author: James Welbes
 * Author URI: https://jameswelbes.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learnkit
 * Domain Path: /languages
 * Requires at least: 6.0
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
define( 'LEARNKIT_VERSION', '0.2.15' );

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
 * The code that runs during plugin activation.
 * This action is documented in includes/class-learnkit-activator.php
 */
function activate_learnkit() {
	require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-activator.php';
	LearnKit_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-learnkit-deactivator.php
 */
function deactivate_learnkit() {
	require_once LEARNKIT_PLUGIN_DIR . 'includes/class-learnkit-deactivator.php';
	LearnKit_Deactivator::deactivate();
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
function run_learnkit() {
	$plugin = new LearnKit();
	$plugin->run();
}
run_learnkit();
