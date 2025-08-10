<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://pacificdragons.com.au
 * @since             1.0.0
 * @package           Pds_Db_Seating_Planner
 *
 * @wordpress-plugin
 * Plugin Name:       PD DB Seating Planner
 * Plugin URI:        https://git@github.com:pacificdragons/pds-seating-planner.git
 * Description:       Allows Session Leads to arrange the seating of paddlers.
 * Version:           1.0.0
 * Author:            Simon Douglas
 * Author URI:        https://pacificdragons.com.au/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pds-db-seating-planner
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PDS_DB_SEATING_PLANNER_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-pds-db-seating-planner-activator.php
 */
function activate_pds_db_seating_planner() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pds-db-seating-planner-activator.php';
	Pds_Db_Seating_Planner_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-pds-db-seating-planner-deactivator.php
 */
function deactivate_pds_db_seating_planner() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pds-db-seating-planner-deactivator.php';
	Pds_Db_Seating_Planner_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_pds_db_seating_planner' );
register_deactivation_hook( __FILE__, 'deactivate_pds_db_seating_planner' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-pds-db-seating-planner.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_pds_db_seating_planner() {

	$plugin = new Pds_Db_Seating_Planner();
	$plugin->run();

}
run_pds_db_seating_planner();
