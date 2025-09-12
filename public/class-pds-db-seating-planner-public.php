<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://pacificdragons.com.au
 * @since      1.0.0
 *
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/public
 * @author     Simon Douglas <si@simondouglas.com>
 */
class Pds_Db_Seating_Planner_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Pds_Db_Seating_Planner_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Pds_Db_Seating_Planner_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pds-db-seating-planner-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Pds_Db_Seating_Planner_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Pds_Db_Seating_Planner_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pds-db-seating-planner-public.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Register shortcode for displaying dragon boat seating planner
	 *
	 * @since    1.0.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'db_seating_planner', array( $this, 'db_seating_planner_shortcode' ) );
	}

	/**
	 * Shortcode callback for displaying dragon boat seating planner
	 *
	 * @since    1.0.0
	 * @param    array     $atts    Shortcode attributes
	 */
	public function db_seating_planner_shortcode( $atts ) {
		
		// Parse shortcode attributes
		$atts = shortcode_atts( array(
			'event_id' => get_the_ID(), // Default to current post ID
		), $atts, 'db_seating_planner' );

		// Get seating data for the event
		$seating_data = '';
		if ( $atts['event_id'] ) {
			$seating_data = get_post_meta( $atts['event_id'], '_pds_seating_plan', true );
		}

		// Debug output (remove in production)
		if ( WP_DEBUG ) {
			error_log( 'DB Seating Planner Debug:' );
			error_log( 'Event ID: ' . $atts['event_id'] );
			error_log( 'Seating data: ' . $seating_data );
		}

		// Start output buffering
		ob_start();

		// Include the public display template
		include plugin_dir_path( __FILE__ ) . 'partials/pds-db-seating-planner-public-display.php';

		// Return the buffered content
		return ob_get_clean();
	}

}
