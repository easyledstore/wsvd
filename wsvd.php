<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://acquistasitoweb.it
 * @since             1.0.0
 * @package           Wsvd
 *
 * @wordpress-plugin
 * Plugin Name:       wsvd
 * Plugin URI:        https://acquistasitoweb.it/wsvd
 * Description:       Questo plugin permette di gestire i codici sconto applicati automaticamente
 * Version:           1.2.0
 * Author:            Acqsuitasitoweb
 * Author URI:        https://acquistasitoweb.it/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wsvd
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.2.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WSVD_VERSION', '1.2.0' );
define( 'WSVD_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSVD_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wsvd-activator.php
 */
function activate_wsvd() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wsvd-activator.php';
	Wsvd_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wsvd-deactivator.php
 */
function deactivate_wsvd() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wsvd-deactivator.php';
	Wsvd_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wsvd' );
register_deactivation_hook( __FILE__, 'deactivate_wsvd' );

/**
 * Load the namespaced plugin bootstrap that orchestrates the module system.
 */
require_once WSVD_PATH . 'includes/class-plugin.php';

/**
 * Legacy compatibility shim for old `Wsvd` references.
 */
require_once WSVD_PATH . 'includes/class-wsvd.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wsvd() {
	$plugin = \WSVD\Plugin::instance();
	$plugin->boot();
}

add_action( 'plugins_loaded', 'run_wsvd', 20 );
