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

		$seating_decoded = ! empty( $seating_data ) ? json_decode( $seating_data, true ) : array();

		$is_draft = ! empty( $seating_decoded['metadata']['isDraft'] );

		// Draft plans are hidden from paddlers. Dragon boat coaches (the
		// can_coach capability) get a preview so they can review a plan on the
		// front end before it is published — rendered dimmed and clearly
		// labelled as a draft (see wrap_draft_preview() below).
		if ( $is_draft && ! current_user_can( 'can_coach' ) ) {
			return '';
		}

		// An empty boat is noise on the front end — require at least one
		// assigned paddler before rendering anything, draft preview included.
		if ( ! $this->has_assigned_paddler( $seating_decoded ) ) {
			return '';
		}

		// Start output buffering
		ob_start();

		// Include the public display template
		include plugin_dir_path( __FILE__ ) . 'partials/pds-db-seating-planner-public-display.php';

		// Return the buffered content, wrapping a coach draft preview so it
		// reads clearly as work-in-progress.
		$output = ob_get_clean();

		if ( $is_draft ) {
			$output = $this->wrap_draft_preview( $output );
		}

		return $output;
	}

	/**
	 * Wrap a rendered seating plan as a coach-only draft preview: a dimmed
	 * container introduced by a notice that the plan is not yet published.
	 *
	 * @since    1.4.2
	 * @param    string    $html    The rendered seating plan markup.
	 * @return   string             The wrapped draft-preview markup.
	 */
	private function wrap_draft_preview( $html ) {
		$notice = '<p class="pds-seating-planner-draft-notice">'
			. esc_html__( 'Draft — visible to coaches only. This seating plan has not been published to paddlers yet.', 'pds-db-seating-planner' )
			. '</p>';

		return '<div class="pds-seating-planner-draft">' . $notice . $html . '</div>';
	}

	/**
	 * Determine whether a decoded seating plan has at least one seat filled.
	 *
	 * The template treats a position as assigned when its userName is non-empty
	 * (see partials/pds-db-seating-planner-public-display.php). We mirror that
	 * here so a plan with only empty positions renders nothing.
	 *
	 * @since    1.0.0
	 * @param    array    $seating_decoded    Decoded _pds_seating_plan meta.
	 * @return   bool                         True if any boat has an assigned paddler.
	 */
	private function has_assigned_paddler( $seating_decoded ) {
		if ( empty( $seating_decoded['boats'] ) || ! is_array( $seating_decoded['boats'] ) ) {
			return false;
		}

		foreach ( $seating_decoded['boats'] as $boat_data ) {
			if ( ! is_array( $boat_data ) ) {
				continue;
			}
			foreach ( $boat_data as $position ) {
				if ( is_array( $position ) && ! empty( $position['userName'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

}
