<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://pacificdragons.com.au
 * @since      1.0.0
 *
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/admin
 * @author     Simon Douglas <si@simondouglas.com>
 */
class Pds_Db_Seating_Planner_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pds-db-seating-planner-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pds-db-seating-planner-admin.js', array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ), $this->version, false );

	}

	/**
	 * Add metabox for dragon boat seating planner
	 */
	public function add_seating_planner_metabox() {
		global $post;
		
		if ( ! $post || $post->post_type !== 'event' ) {
			return;
		}

		$event_categories = wp_get_post_terms( $post->ID, 'event-categories' );
		$is_dragon_boat = false;
		
		foreach ( $event_categories as $category ) {
			if ( $category->term_id == 61 ) {
				$is_dragon_boat = true;
				break;
			}
		}

		if ( $is_dragon_boat ) {
			add_meta_box(
				'pds-seating-planner',
				'Dragon Boat Seating Planner',
				array( $this, 'render_seating_planner_metabox' ),
				'event',
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the seating planner metabox
	 */
	public function render_seating_planner_metabox( $post ) {
		wp_nonce_field( 'pds_seating_planner_nonce', 'pds_seating_planner_nonce_field' );
		
		$event_users = $this->get_event_users( $post->ID );
		$seating_data = get_post_meta( $post->ID, '_pds_seating_plan', true );
		
		include plugin_dir_path( __FILE__ ) . 'partials/pds-db-seating-planner-admin-display.php';
	}

	/**
	 * Get users associated with this event who have confirmed bookings
	 */
	public function get_event_users( $post_id ) {
		global $wpdb;

		$event_id = get_post_meta( $post_id, '_event_id', true );
		
		if ( ! $event_id ) {
			return array();
		}

		$query = $wpdb->prepare("
			SELECT DISTINCT u.ID, u.display_name, u.user_email, b.booking_id
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->prefix}em_bookings b ON u.ID = b.person_id
			WHERE b.event_id = %d 
			AND b.booking_status = 1
			ORDER BY u.display_name ASC
		", $event_id);

		return $wpdb->get_results( $query );
	}

	/**
	 * Save seating plan data
	 */
	public function save_seating_planner_data( $post_id ) {
		if ( ! isset( $_POST['pds_seating_planner_nonce_field'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['pds_seating_planner_nonce_field'], 'pds_seating_planner_nonce' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['seating_plan_data'] ) ) {
			$seating_data = sanitize_text_field( $_POST['seating_plan_data'] );
			update_post_meta( $post_id, '_pds_seating_plan', $seating_data );
		}
	}

	/**
	 * AJAX handler for saving seating plan data
	 */
	public function ajax_save_seating_plan() {
		// Set JSON header
		header( 'Content-Type: application/json' );
		
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pds_seating_planner_nonce' ) ) {
			wp_send_json_error( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Get and validate post ID
		$post_id = intval( $_POST['post_id'] );
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		// Get seating data (don't sanitize yet as it's JSON)
		$seating_data = wp_unslash( $_POST['seating_data'] );
		
		// Validate JSON first
		$decoded_data = json_decode( $seating_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( 'Invalid seating data format: ' . json_last_error_msg() );
		}
		
		// Now sanitize the JSON string after validation
		$seating_data = sanitize_textarea_field( $seating_data );

		// Save the data
		$result = update_post_meta( $post_id, '_pds_seating_plan', $seating_data );

		if ( $result !== false ) {
			wp_send_json_success( 'Seating plan saved successfully' );
		} else {
			wp_send_json_error( 'Failed to save seating plan' );
		}
	}

}
