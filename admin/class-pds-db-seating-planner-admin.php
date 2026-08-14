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
		// Only enqueue on post.php pages
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/pds-db-seating-planner-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		// Only enqueue on post.php pages
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' ) {
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/pds-db-seating-planner-admin.js', array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ), $this->version, false );

		// Enqueue jQuery UI Touch Punch for mobile support
		wp_enqueue_script( 'jquery-ui-touch-punch', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js', array( 'jquery-ui-core' ), '0.2.3', false );
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
			if ( $category->slug === 'dragon-boat' ) {
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
		$original_seating_data = get_post_meta( $post->ID, '_pds_seating_plan', true );

		// Validate and clean seating data before rendering
		$seating_data = $this->validate_seating_data( $original_seating_data, $event_users );

		// If data was cleaned, update the post meta and show notification
		$booking_validation_message = '';
		if ( $seating_data !== $original_seating_data && current_user_can( 'edit_post', $post->ID ) ) {
			update_post_meta( $post->ID, '_pds_seating_plan', $seating_data );
			$booking_validation_message = 'Some paddlers were removed from the seating plan due to cancelled bookings.';
		}

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
	 * Validate seating data and remove users with cancelled bookings
	 */
	public function validate_seating_data( $seating_data, $active_users ) {
		if ( empty( $seating_data ) ) {
			return $seating_data;
		}

		// Create array of active user IDs for quick lookup
		$active_user_ids = array();
		foreach ( $active_users as $user ) {
			$active_user_ids[] = (int) $user->ID;
		}

		// Parse seating data
		$decoded_data = json_decode( $seating_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! $decoded_data ) {
			return $seating_data;
		}

		$data_updated = false;

		// Handle different data formats (backward compatibility)
		if ( isset( $decoded_data['boats'] ) && is_array( $decoded_data['boats'] ) ) {
			// New format with boats array and metadata
			foreach ( $decoded_data['boats'] as $boat_index => &$boat_data ) {
				if ( ! is_array( $boat_data ) ) {
					continue;
				}

				foreach ( $boat_data as $position => $user_data ) {
					if ( isset( $user_data['userId'] ) ) {
						$user_id = (int) $user_data['userId'];
						if ( ! in_array( $user_id, $active_user_ids ) ) {
							// User no longer has active booking - remove from seating plan
							unset( $decoded_data['boats'][$boat_index][$position] );
							$data_updated = true;
						}
					}
				}
			}
		} elseif ( is_array( $decoded_data ) && ! isset( $decoded_data['boats'] ) ) {
			// Handle old array format or single boat format
			if ( isset( $decoded_data[0] ) && is_array( $decoded_data[0] ) ) {
				// Array of boats format
				foreach ( $decoded_data as $boat_index => &$boat_data ) {
					foreach ( $boat_data as $position => $user_data ) {
						if ( isset( $user_data['userId'] ) ) {
							$user_id = (int) $user_data['userId'];
							if ( ! in_array( $user_id, $active_user_ids ) ) {
								unset( $decoded_data[$boat_index][$position] );
								$data_updated = true;
							}
						}
					}
				}
			} else {
				// Single boat format
				foreach ( $decoded_data as $position => $user_data ) {
					if ( isset( $user_data['userId'] ) ) {
						$user_id = (int) $user_data['userId'];
						if ( ! in_array( $user_id, $active_user_ids ) ) {
							unset( $decoded_data[$position] );
							$data_updated = true;
						}
					}
				}
			}
		}

		// If data was updated, save it back to the post meta and return the cleaned version
		if ( $data_updated ) {
			$cleaned_data = wp_json_encode( $decoded_data );
			// We can't update post meta here directly as we don't have post_id in this context
			// Instead, return the cleaned data and let the calling function handle the update
			return $cleaned_data;
		}

		return $seating_data;
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

		// Get and validate post ID
		$post_id = intval( $_POST['post_id'] );
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Get seating data (don't sanitize yet as it's JSON)
		$seating_data = wp_unslash( $_POST['seating_data'] );

		// Validate JSON first
		$decoded_data = json_decode( $seating_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( 'Invalid seating data format: ' . json_last_error_msg() );
		}

		// Since we've already validated the JSON structure, we can safely store it
		// Using wp_json_encode to ensure consistent formatting
		$seating_data = wp_json_encode( $decoded_data );

		// Save the data
		$result = update_post_meta( $post_id, '_pds_seating_plan', $seating_data );

		// Debug logging
		error_log( 'DEBUG: post_id=' . $post_id );
		error_log( 'DEBUG: seating_data=' . $seating_data );
		error_log( 'DEBUG: update_post_meta result=' . var_export( $result, true ) );

		// Check if the save was successful by verifying the data exists
		$saved_data = get_post_meta( $post_id, '_pds_seating_plan', true );
		error_log( 'DEBUG: saved_data=' . var_export( $saved_data, true ) );
		
		// Decode both and compare the actual data structure instead of string comparison
		$saved_decoded = json_decode( $saved_data, true );
		$sent_decoded = json_decode( $seating_data, true );
		
		if ( $saved_decoded !== null && $saved_decoded == $sent_decoded ) {
			wp_send_json_success( 'Seating plan saved successfully' );
		} else {
			wp_send_json_error( 'Failed to save seating plan: data mismatch' );
		}
	}

}
