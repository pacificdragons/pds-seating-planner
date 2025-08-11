<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://pacificdragons.com.au
 * @since      1.0.0
 *
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/admin/partials
 */
?>

<div id="pds-seating-planner">
	<?php if ( ! empty( $booking_validation_message ) ) : ?>
		<div class="notice notice-warning is-dismissible" style="margin-bottom: 15px;">
			<p><strong>Booking Status Update:</strong> <?php echo esc_html( $booking_validation_message ); ?></p>
		</div>
	<?php endif; ?>

	<div class="seating-controls" style="margin-bottom: 20px;">
		<h4>Available Paddlers</h4>
		<div id="available-paddlers">
			<?php if ( ! empty( $event_users ) ) : ?>
				<?php foreach ( $event_users as $user ) : ?>
					<div class="paddler-item" draggable="true" data-user-id="<?php echo esc_attr( $user->ID ); ?>">
						<?php echo esc_html( $user->display_name ); ?>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="no-paddlers">No confirmed paddlers found for this event.</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="dragon-boat-layout" style="background: #f9f9f9; padding: 20px; border-radius: 8px;">
		<div id="boats-container" class="boats-container">
			<!-- Boats will be generated dynamically by JavaScript -->
		</div>

		<div style="margin-top: 20px; text-align: center; color: #666; font-size: 12px;">
			<p><strong>Instructions:</strong></p>
			<ul style="text-align: left; display: inline-block; margin: 0;">
				<li>Drag paddlers from the available list above to assign them to boat positions</li>
				<li>Drag paddlers between positions to swap them</li>
			</ul>
		</div>
	</div>

	<!-- Action buttons -->
	<div class="action-buttons" style="margin-top: 20px; text-align: center; padding-top: 15px; border-top: 1px solid #ddd;">
		<div id="global-controls">
			<!-- Global controls will be shown/hidden based on boat count -->
		</div>
		<button type="button" id="save-seating-plan" class="button button-primary">
			<span class="dashicons dashicons-saved" style="margin-right: 5px;"></span>
			Save Seating Plan
		</button>
		<div id="save-status" style="margin-top: 10px; font-size: 14px;"></div>
	</div>

	<!-- Hidden input to store seating data -->
	<input type="hidden" name="seating_plan_data" id="seating-plan-data" value="<?php echo esc_attr( $seating_data ); ?>" />
</div>
