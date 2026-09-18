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

<div id="pds-seating-planner-app">
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
					<div class="paddler-item" draggable="true" data-user-id="<?php echo esc_attr( $user->ID ); ?>" data-gender="<?php echo esc_attr( isset( $user->gender ) ? $user->gender : '' ); ?>">
						<?php echo esc_html( $user->display_name ); ?>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="no-paddlers">No confirmed paddlers found for this event.</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="dragon-boat-layout" style="background: #f9f9f9; padding: 20px; border-radius: 8px;">
		<div class="seating-scale-controls">
			<label for="seating-scale">Scale</label>
			<input type="range" id="seating-scale" min="25" max="100" step="5" value="100"
				autocomplete="off" />
			<span id="seating-scale-value">100%</span>
			<button type="button" id="seating-scale-reset" class="button button-small">Reset</button>
		</div>

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

	<!--
		Coaching notes: private, free-form notes for session leads / coaches.
		Saved to the _pds_coaching_notes post meta on the normal post update and
		never rendered on the front end (the public shortcode only reads
		_pds_seating_plan).
	-->
	<div class="coaching-notes" style="margin-top: 20px;">
		<h4 style="margin-bottom: 6px;">Coaching Notes</h4>
		<p class="description" style="margin: 0 0 8px;">Private notes for coaches and session leads. Never shown on the public seating plan. Saved with either <strong>Save Seating Plan</strong> or the post <strong>Update</strong> button.</p>
		<textarea name="pds_coaching_notes" id="pds-coaching-notes" rows="6" style="width: 100%; box-sizing: border-box;" placeholder="e.g. rotation plan, technique focus, paddlers to watch…"><?php echo esc_textarea( $coaching_notes ); ?></textarea>
	</div>

	<!-- Action buttons -->
	<div class="action-buttons" style="margin-top: 20px; text-align: center; padding-top: 15px; border-top: 1px solid #ddd;">
		<div id="global-controls">
			<!-- Global controls will be shown/hidden based on boat count -->
		</div>
		<button type="button" id="boat-load" class="button button-secondary" style="margin-right: 10px;">
			<span class="dashicons dashicons-groups" style="margin-right: 5px;"></span>
			Boat Load
		</button>
		<button type="button" id="toggle-draft-mode" class="button button-secondary" style="margin-right: 10px;">
			<span class="dashicons dashicons-hidden" style="margin-right: 5px;"></span>
			<span id="draft-button-label">Set as Draft</span>
		</button>
		<button type="button" id="save-seating-plan" class="button button-primary" style="margin: 0;">
			<span class="dashicons dashicons-saved" style="margin-right: 5px;"></span>
			Save Seating Plan
		</button>
		<div id="draft-status" style="margin-top: 5px; font-size: 13px; color: #888;"></div>
		<div id="save-status" style="margin-top: 10px; font-size: 14px;"></div>
	</div>

	<!-- Hidden input to store seating data -->
	<input type="hidden" name="seating_plan_data" id="seating-plan-data" value="<?php echo esc_attr( $seating_data ); ?>" />

	<?php
	// A complete userId => gender map for every confirmed paddler, so Boat Load
	// can split by gender even for paddlers already seated (whose available-list
	// item, and thus its data-gender attribute, isn't present in the DOM).
	$paddler_genders = array();
	foreach ( $event_users as $u ) {
		$paddler_genders[ $u->ID ] = isset( $u->gender ) ? $u->gender : '';
	}
	?>
	<input type="hidden" id="paddler-genders" value="<?php echo esc_attr( wp_json_encode( (object) $paddler_genders ) ); ?>" />

	<!-- Boat template -->
	<template id="boat-template">
		<div class="boat-container" data-boat="">
			<div class="boat-header">
				<h5 class="boat-title"></h5>
				<div class="boat-controls">
					<button type="button" class="button button-small empty-single-boat" data-boat="">
						<span class="dashicons dashicons-dismiss"></span> Empty
					</button>
				</div>
			</div>
			<div class="drummer-section">
				<div class="position drummer-position" data-position="drummer" data-boat="">
					<span class="position-label">Drummer</span>
				</div>
			</div>
			<div class="paddlers-section">
				<?php for ( $row = 1; $row <= 10; $row++ ) : ?>
				<div class="paddler-row">
					<div class="position paddler-position" data-position="left-<?php echo $row; ?>" data-boat="">
						<span class="position-label">L<?php echo $row; ?></span>
					</div>
					<div class="row-number"><?php echo $row; ?></div>
					<div class="position paddler-position" data-position="right-<?php echo $row; ?>" data-boat="">
						<span class="position-label">R<?php echo $row; ?></span>
					</div>
				</div>
				<?php endfor; ?>
			</div>
			<div class="steersperson-section">
				<div class="position steersperson-position" data-position="steersperson" data-boat="">
					<span class="position-label">Steerer</span>
				</div>
			</div>
		</div>
	</template>

	<!--
		Global controls (Empty Boat / Add boat / Remove last boat) are built
		dynamically in JS by setupGlobalControls() based on the current boat count.
	-->
</div>
