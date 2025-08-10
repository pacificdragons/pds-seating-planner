<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://pacificdragons.com.au
 * @since      1.0.0
 *
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/includes
 * @author     Simon Douglas <si@simondouglas.com>
 */
class Pds_Db_Seating_Planner_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'pds-db-seating-planner',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
