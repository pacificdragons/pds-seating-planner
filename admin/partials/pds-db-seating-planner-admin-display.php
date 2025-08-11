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
		<h4>Dragon Boat Seating Layout</h4>
		<div id="boats-container" class="boats-container">
			<!-- Boats will be generated dynamically by JavaScript -->
		</div>

		<div style="margin-top: 20px; text-align: center; color: #666; font-size: 12px;">
			<p><strong>Instructions:</strong></p>
			<ul style="text-align: left; display: inline-block; margin: 0;">
				<li>Drag paddlers from the available list above to assign them to boat positions</li>
				<li>Drag paddlers between positions to swap them</li>
				<li>Drag assigned paddlers back to the available list to remove them</li>
				<li>Click the × button on assigned paddlers to remove them</li>
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

<script type="text/javascript">
jQuery(document).ready(function($) {
	var seatingData = {
		boats: [],
		metadata: {
			boat2Visible: null // null = auto (based on paddler count), true/false = user preference
		}
	};
	var totalPaddlers = $('.paddler-item').length;
	var defaultBoatCount = totalPaddlers > 20 ? 2 : 1;
	var actualBoatCount = defaultBoatCount;
	
	// Load existing seating data first
	var existingData = $('#seating-plan-data').val();
	if (existingData) {
		try {
			var loadedData = JSON.parse(existingData);
			// Handle backward compatibility - convert old format to new format
			if (loadedData && Array.isArray(loadedData)) {
				// Old array format - convert to new structure
				seatingData.boats = loadedData;
				seatingData.metadata.boat2Visible = null; // Use default behavior
			} else if (loadedData && !Array.isArray(loadedData) && !loadedData.boats) {
				// Very old single boat format - convert to new structure
				seatingData.boats = [loadedData];
				seatingData.metadata.boat2Visible = null;
			} else if (loadedData && loadedData.boats) {
				// New format - use as is
				seatingData = loadedData;
			}
		} catch (e) {
			console.log('Error parsing existing seating data');
		}
	}
	
	// Determine actual boat count based on user preference or default
	if (seatingData.metadata.boat2Visible === false) {
		actualBoatCount = 1; // User explicitly hid boat 2
	} else if (seatingData.metadata.boat2Visible === true) {
		actualBoatCount = 2; // User explicitly wants boat 2
	} else {
		actualBoatCount = defaultBoatCount; // Use default based on paddler count
	}
	
	// Initialize boats
	initializeBoats(actualBoatCount);
	
	// Setup global controls based on boat count
	setupGlobalControls(actualBoatCount);
	
	// Ensure we have the right number of boat objects
	while (seatingData.boats.length < actualBoatCount) {
		seatingData.boats.push({});
	}
	
	// Load seating arrangement
	if (existingData) {
		loadSeatingArrangement();
	}
	
	// Update global reference for other functions
	boatCount = actualBoatCount;
	
	// Make paddler items draggable with touch support
	$('.paddler-item').draggable({
		revert: 'invalid',
		helper: 'clone',
		zIndex: 1000,
		scroll: false,
		distance: 5, // Minimum distance to start drag (helpful for touch)
		delay: 100   // Short delay to prevent accidental drags
	});
	
	function initializeBoats(count) {
		var boatsContainer = $('#boats-container');
		boatsContainer.empty();
		
		for (var i = 0; i < count; i++) {
			var boatHtml = createBoatHTML(i, count);
			boatsContainer.append(boatHtml);
		}
		
		// Re-initialize drag/drop after creating boats
		initializeDragDrop();
	}
	
	function createBoatHTML(boatIndex, totalBoats) {
		var boatTitle = totalBoats > 1 ? 'Boat ' + (boatIndex + 1) : '';
		var boatHtml = '<div class="boat-container" data-boat="' + boatIndex + '">';
		
		if (boatTitle) {
			boatHtml += '<div class="boat-header">';
			boatHtml += '<h5 class="boat-title">' + boatTitle + '</h5>';
			boatHtml += '<div class="boat-controls">';
			boatHtml += '<button type="button" class="button button-small empty-single-boat" data-boat="' + boatIndex + '">';
			boatHtml += '<span class="dashicons dashicons-dismiss"></span> Empty';
			boatHtml += '</button>';
			boatHtml += '</div>';
			boatHtml += '</div>';
		}
		
		// Drummer position
		boatHtml += '<div class="drummer-section">' +
			'<div class="position drummer-position" data-position="drummer" data-boat="' + boatIndex + '">' +
			'<span class="position-label">Drummer</span>' +
			'</div>' +
			'</div>';
		
		// Paddler positions
		boatHtml += '<div class="paddlers-section">';
		for (var row = 1; row <= 10; row++) {
			boatHtml += '<div class="paddler-row">' +
				'<div class="position paddler-position" data-position="left-' + row + '" data-boat="' + boatIndex + '">' +
				'<span class="position-label">L' + row + '</span>' +
				'</div>' +
				'<div class="row-number">' + row + '</div>' +
				'<div class="position paddler-position" data-position="right-' + row + '" data-boat="' + boatIndex + '">' +
				'<span class="position-label">R' + row + '</span>' +
				'</div>' +
				'</div>';
		}
		boatHtml += '</div>';
		
		// Steersperson position
		boatHtml += '<div class="steersperson-section">' +
			'<div class="position steersperson-position" data-position="steersperson" data-boat="' + boatIndex + '">' +
			'<span class="position-label">Steerer</span>' +
			'</div>' +
			'</div>';
		
		boatHtml += '</div>';
		return boatHtml;
	}
	
	function setupGlobalControls(count) {
		var globalControls = $('#global-controls');
		globalControls.empty();
		
		// Only show global empty button for single boat
		if (count === 1) {
			globalControls.html('<button type="button" id="empty-boat" class="button button-secondary" style="margin: 5px;"><span class="dashicons dashicons-dismiss" style="margin-right: 5px;"></span>Empty Boat</button><button type="button" id="add-remove-boat-2" class="button button-secondary" style="margin: 5px;"><span class="dashicons dashicons-plus-alt" style="margin-right: 5px;"></span>Add Boat 2</button>');
		}
		// For multiple boats, show option to remove boat 2
		else {
			globalControls.html('<button type="button" id="add-remove-boat-2" class="button button-secondary" style="margin-right: 10px;"><span class="dashicons dashicons-minus" style="margin-right: 5px;"></span>Remove Boat 2</button>');
		}
	}
	
	function initializeDragDrop() {
		// Make available paddlers area droppable for returning paddlers
		$('#available-paddlers').droppable({
		accept: '.assigned-paddler',
		hoverClass: 'drop-zone-hover',
		drop: function(event, ui) {
			var userId = ui.draggable.data('user-id');
			var userName = ui.draggable.hasClass('assigned-paddler') ? ui.draggable.find('.paddler-name').text().trim() : ui.draggable.text().trim();
			
			// Find and clear the position this paddler was in
			var positionElement = ui.draggable.closest('.position');
			var position = positionElement.data('position');
			var boatIndex = positionElement.data('boat');
			
			positionElement.html('<span class="position-label">' + getPositionLabel(position) + '</span>');
			positionElement.removeClass('position-filled');
			if (seatingData.boats[boatIndex]) {
				delete seatingData.boats[boatIndex][position];
			}
			
			// Add paddler back to available list
			var paddlerItem = $('<div class="paddler-item" draggable="true" data-user-id="' + userId + '">' + userName + '</div>');
			$(this).append(paddlerItem);
			
			// Make the new item draggable
			paddlerItem.draggable({
				revert: 'invalid',
				helper: 'clone',
				zIndex: 1000,
				scroll: false,
				distance: 5,
				delay: 100
			});
			
			updateSeatingDataInput();
		}
	});
	
		// Make positions droppable
		$('.position').droppable({
			accept: '.paddler-item, .assigned-paddler',
			hoverClass: 'position-hover',
			drop: function(event, ui) {
				var position = $(this).data('position');
				var boatIndex = $(this).data('boat');
				var userId = ui.draggable.data('user-id');
				var userName = ui.draggable.hasClass('assigned-paddler') ? ui.draggable.find('.paddler-name').text().trim() : ui.draggable.text().trim();
				var isAssignedPaddler = ui.draggable.hasClass('assigned-paddler');
				
				// Ensure boat object exists
				if (!seatingData.boats[boatIndex]) {
					seatingData.boats[boatIndex] = {};
				}
				
				// Handle position exchange if this position is already occupied
				if ($(this).hasClass('position-filled')) {
					var currentAssignedPaddler = $(this).find('.assigned-paddler');
					var currentUserId = currentAssignedPaddler.data('user-id');
					var currentUserName = currentAssignedPaddler.find('.paddler-name').text().trim();
					
					if (isAssignedPaddler) {
						// Exchange positions between two assigned paddlers
						var sourcePositionElement = ui.draggable.closest('.position');
						var sourcePosition = sourcePositionElement.data('position');
						var sourceBoatIndex = sourcePositionElement.data('boat');
						
						// Move current paddler to source position
						sourcePositionElement.html('<span class="assigned-paddler draggable" data-user-id="' + currentUserId + '"><span class="paddler-name">' + currentUserName + '</span><span class="remove-paddler"><span class="dashicons dashicons-dismiss"></span></span></span>');
						sourcePositionElement.addClass('position-filled');
						
						// Ensure source boat object exists
						if (!seatingData.boats[sourceBoatIndex]) {
							seatingData.boats[sourceBoatIndex] = {};
						}
						
						// Update seating data for source position
						seatingData.boats[sourceBoatIndex][sourcePosition] = {
							userId: currentUserId,
							userName: currentUserName
						};
					} else {
						// Move current paddler back to available list
						var paddlerItem = $('<div class="paddler-item" draggable="true" data-user-id="' + currentUserId + '">' + currentUserName + '</div>');
						$('#available-paddlers').append(paddlerItem);
						
						// Make the new item draggable
						paddlerItem.draggable({
							revert: 'invalid',
							helper: 'clone',
							zIndex: 1000
						});
					}
				} else if (isAssignedPaddler) {
					// Clear the source position
					var sourcePositionElement = ui.draggable.closest('.position');
					var sourcePosition = sourcePositionElement.data('position');
					var sourceBoatIndex = sourcePositionElement.data('boat');
					
					sourcePositionElement.html('<span class="position-label">' + getPositionLabel(sourcePosition) + '</span>');
					sourcePositionElement.removeClass('position-filled');
					
					if (seatingData.boats[sourceBoatIndex]) {
						delete seatingData.boats[sourceBoatIndex][sourcePosition];
					}
				} else {
					// Clear any existing assignment for this user from other positions
					clearUserFromAllPositions(userId);
					
					// Remove paddler from available list
					ui.draggable.remove();
				}
				
				// Add user to this position
				$(this).html('<span class="assigned-paddler draggable" data-user-id="' + userId + '"><span class="paddler-name">' + userName + '</span><span class="remove-paddler"><span class="dashicons dashicons-dismiss"></span></span></span>');
				$(this).addClass('position-filled');
				
				// Make assigned paddler draggable
				var assignedPaddlerElement = $(this).find('.assigned-paddler');
				assignedPaddlerElement.draggable({
					revert: 'invalid',
					helper: 'clone',
					zIndex: 1000,
					scroll: false,
					distance: 5,
					delay: 100
				});
				
				// Update seating data
				seatingData.boats[boatIndex][position] = {
					userId: userId,
					userName: userName
				};
				
				updateSeatingDataInput();
			}
		});
	}
	
	// Handle click on remove button (×)
	$(document).on('click', '.remove-paddler', function(e) {
		e.stopPropagation();
		var positionElement = $(this).closest('.position');
		var position = positionElement.data('position');
		var boatIndex = positionElement.data('boat');
		var assignedPaddler = $(this).closest('.assigned-paddler');
		var userId = assignedPaddler.data('user-id');
		var userName = assignedPaddler.find('.paddler-name').text().trim();
		
		// Add paddler back to available list
		var paddlerItem = $('<div class="paddler-item" draggable="true" data-user-id="' + userId + '">' + userName + '</div>');
		$('#available-paddlers').append(paddlerItem);
		
		// Make the new item draggable
		paddlerItem.draggable({
			revert: 'invalid',
			helper: 'clone',
			zIndex: 1000
		});
		
		// Clear the position
		positionElement.html('<span class="position-label">' + getPositionLabel(position) + '</span>');
		positionElement.removeClass('position-filled');
		
		if (seatingData.boats[boatIndex]) {
			delete seatingData.boats[boatIndex][position];
		}
		updateSeatingDataInput();
	});
	
	function clearUserFromAllPositions(userId) {
		$('.position').each(function() {
			var assignedPaddler = $(this).find('.assigned-paddler[data-user-id="' + userId + '"]');
			if (assignedPaddler.length) {
				var position = $(this).data('position');
				var boatIndex = $(this).data('boat');
				var userName = assignedPaddler.find('.paddler-name').text().trim();
				
				// Add paddler back to available list
				var paddlerItem = $('<div class="paddler-item" draggable="true" data-user-id="' + userId + '">' + userName + '</div>');
				$('#available-paddlers').append(paddlerItem);
				
				// Make the new item draggable
				paddlerItem.draggable({
					revert: 'invalid',
					helper: 'clone',
					zIndex: 1000,
					scroll: false,
					distance: 5,
					delay: 100
				});
				
				$(this).html('<span class="position-label">' + getPositionLabel(position) + '</span>');
				$(this).removeClass('position-filled');
				
				if (seatingData.boats[boatIndex]) {
					delete seatingData.boats[boatIndex][position];
				}
			}
		});
	}
	
	function getPositionLabel(position) {
		if (position === 'drummer') return 'Drummer';
		if (position === 'steersperson') return 'Steerer';
		return position.replace('-', '').toUpperCase();
	}
	
	// Save button click handler
	$('#save-seating-plan').on('click', function() {
		var button = $(this);
		var statusDiv = $('#save-status');
		
		// Show loading state
		button.prop('disabled', true);
		button.find('.dashicons').removeClass('dashicons-saved').addClass('dashicons-update');
		button.find('span:not(.dashicons)').text('Saving...');
		statusDiv.html('').removeClass('success error');
		
		// Get current post ID
		var postId = $('#post_ID').val() || $('input[name="post_ID"]').val();
		
		// Prepare data for AJAX
		var seatingDataString = JSON.stringify(seatingData);
		var ajaxData = {
			action: 'pds_save_seating_plan',
			post_id: postId,
			seating_data: seatingDataString,
			nonce: $('#pds_seating_planner_nonce_field').val()
		};
		
		// Debug: Log the data being sent
		console.log('Saving seating data:', seatingDataString);
		
		// Send AJAX request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: ajaxData,
			success: function(response) {
				console.log('Server response:', response);
				if (response.success) {
					statusDiv.html('<span style="color: #46b450;">✓ Seating plan saved successfully!</span>').addClass('success');
				} else {
					statusDiv.html('<span style="color: #dc3232;">✗ Error: ' + (response.data || 'Unknown error occurred') + '</span>').addClass('error');
				}
			},
			error: function() {
				statusDiv.html('<span style="color: #dc3232;">✗ Network error occurred. Please try again.</span>').addClass('error');
			},
			complete: function() {
				// Reset button state
				button.prop('disabled', false);
				button.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-saved');
				button.find('span:not(.dashicons)').text('Save Seating Plan');
				
				// Clear status after 3 seconds
				setTimeout(function() {
					statusDiv.fadeOut(300, function() {
						$(this).html('').show();
					});
				}, 3000);
			}
		});
	});
	
	// Global empty boat button click handler (single boat only)
	$(document).on('click', '#empty-boat', function() {
		if (confirm('Are you sure you want to empty the boat? This will move all assigned paddlers back to the available list.')) {
			emptyBoat(0); // Empty the single boat
		}
	});
	
	// Per-boat empty button click handler
	$(document).on('click', '.empty-single-boat', function() {
		var boatIndex = $(this).data('boat');
		var boatName = 'Boat ' + (boatIndex + 1);
		if (confirm('Are you sure you want to empty ' + boatName + '? This will move all assigned paddlers back to the available list.')) {
			emptyBoat(boatIndex);
		}
	});
	
	// Add/Remove boat 2 button click handler
	$(document).on('click', '#add-remove-boat-2', function() {
		if (boatCount === 1) {
			addBoat2();
		} else {
			removeBoat2();
		}
	});
	
	function emptyBoat(boatIndex) {
		// Find all assigned paddlers in the specified boat and move them back to available list
		$('.position-filled[data-boat="' + boatIndex + '"]').each(function() {
			var position = $(this).data('position');
			var assignedPaddler = $(this).find('.assigned-paddler');
			var userId = assignedPaddler.data('user-id');
			var userName = assignedPaddler.find('.paddler-name').text().trim();
			
			// Add paddler back to available list
			var paddlerItem = $('<div class="paddler-item" draggable="true" data-user-id="' + userId + '">' + userName + '</div>');
			$('#available-paddlers').append(paddlerItem);
			
			// Make the new item draggable
			paddlerItem.draggable({
				revert: 'invalid',
				helper: 'clone',
				zIndex: 1000,
				scroll: false,
				distance: 5,
				delay: 100
			});
			
			// Clear the position
			$(this).html('<span class="position-label">' + getPositionLabel(position) + '</span>');
			$(this).removeClass('position-filled');
		});
		
		// Clear seating data for this boat
		if (seatingData.boats[boatIndex]) {
			seatingData.boats[boatIndex] = {};
		}
		
		// Update the hidden input
		updateSeatingDataInput();
		
		// Show feedback
		var boatName = boatCount > 1 ? 'Boat ' + (boatIndex + 1) : 'Boat';
		$('#save-status').html('<span style="color: #46b450;">✓ ' + boatName + ' emptied successfully!</span>').addClass('success');
		setTimeout(function() {
			$('#save-status').fadeOut(300, function() {
				$(this).html('').show();
			});
		}, 2000);
	}
	
	function addBoat2() {
		// Update boat count
		boatCount = 2;
		
		// Set user preference to show boat 2
		seatingData.metadata.boat2Visible = true;
		
		// Ensure we have boat data arrays
		while (seatingData.boats.length < 2) {
			seatingData.boats.push({});
		}
		
		// Recreate both boats with proper titles (since we're going from 1 to 2 boats)
		// Store current seating data to restore after recreation
		var currentSeatingData = JSON.parse(JSON.stringify(seatingData.boats));
		
		// Clear and recreate all boats with titles
		$('#boats-container').empty();
		for (var i = 0; i < 2; i++) {
			var boatHtml = createBoatHTML(i, 2);
			$('#boats-container').append(boatHtml);
		}
		
		// Re-initialize drag/drop for all boats
		initializeDragDrop();
		
		// Restore seating arrangement
		loadSeatingArrangement();
		
		// Update global controls
		setupGlobalControls(boatCount);
		
		updateSeatingDataInput();
		
		// Show feedback
		$('#save-status').html('<span style="color: #46b450;">✓ Boat 2 added successfully!</span>').addClass('success');
		setTimeout(function() {
			$('#save-status').fadeOut(300, function() {
				$(this).html('').show();
			});
		}, 2000);
	}
	
	function removeBoat2() {
		if (confirm('Are you sure you want to remove Boat 2? This will move all assigned paddlers from Boat 2 back to the available list.')) {
			// Move all paddlers from boat 2 back to available list
			$('.position-filled[data-boat="1"]').each(function() {
				var assignedPaddler = $(this).find('.assigned-paddler');
				var userId = assignedPaddler.data('user-id');
				var userName = assignedPaddler.find('.paddler-name').text().trim();
				
				// Add paddler back to available list
				var paddlerItem = $('<div class="paddler-item" draggable="true" data-user-id="' + userId + '">' + userName + '</div>');
				$('#available-paddlers').append(paddlerItem);
				
				// Make the new item draggable
				paddlerItem.draggable({
					revert: 'invalid',
					helper: 'clone',
					zIndex: 1000,
					scroll: false,
					distance: 5,
					delay: 100
				});
			});
			
			// Remove boat 2 from DOM
			$('.boat-container[data-boat="1"]').remove();
			
			// Update boat count
			boatCount = 1;
			
			// Set user preference to hide boat 2
			seatingData.metadata.boat2Visible = false;
			
			// Clear boat 2 seating data
			if (seatingData.boats.length > 1) {
				seatingData.boats.splice(1, 1);
			}
			
			// Update global controls
			setupGlobalControls(boatCount);
			
			updateSeatingDataInput();
			
			// Show feedback
			$('#save-status').html('<span style="color: #46b450;">✓ Boat 2 removed successfully!</span>').addClass('success');
			setTimeout(function() {
				$('#save-status').fadeOut(300, function() {
					$(this).html('').show();
				});
			}, 2000);
		}
	}
	
	function updateSeatingDataInput() {
		$('#seating-plan-data').val(JSON.stringify(seatingData));
	}
	
	function loadSeatingArrangement() {
		// Load seating data for each boat
		for (var boatIndex = 0; boatIndex < seatingData.boats.length; boatIndex++) {
			if (seatingData.boats[boatIndex]) {
				$.each(seatingData.boats[boatIndex], function(position, userData) {
					var positionElement = $('.position[data-position="' + position + '"][data-boat="' + boatIndex + '"]');
					if (positionElement.length && userData) {
						positionElement.html('<span class="assigned-paddler draggable" data-user-id="' + userData.userId + '"><span class="paddler-name">' + userData.userName + '</span><span class="remove-paddler"><span class="dashicons dashicons-dismiss"></span></span></span>');
						positionElement.addClass('position-filled');
						
						// Make assigned paddler draggable
						var assignedPaddlerElement = positionElement.find('.assigned-paddler');
						assignedPaddlerElement.draggable({
							revert: 'invalid',
							helper: 'clone',
							zIndex: 1000,
							scroll: false,
							distance: 5,
							delay: 100
						});
						
						// Remove this paddler from available list
						$('.paddler-item[data-user-id="' + userData.userId + '"]').remove();
					}
				});
			}
		}
	}
});
</script>

<style>
/* Base responsive layout */
#pds-seating-planner {
	max-width: 100%;
	overflow-x: auto;
}

/* Available paddlers section */
#available-paddlers {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-bottom: 20px;
	padding: 15px;
	border: 2px dashed #ccc;
	min-height: 60px;
	border-radius: 4px;
	transition: all 0.3s ease;
}

.paddler-item {
	padding: 8px 12px;
	background: #f0f0f0;
	border: 1px solid #ccc;
	border-radius: 4px;
	cursor: move;
	display: inline-block;
	font-size: 14px;
	white-space: nowrap;
}

.no-paddlers {
	color: #666;
	margin: 0;
	font-style: italic;
}

/* Multiple boats container */
.boats-container {
	display: flex;
	flex-wrap: wrap;
	gap: 30px;
	justify-content: center;
	align-items: flex-start;
}

/* Individual boat container */
.boat-container {
	max-width: 550px;
	flex: 1;
	min-width: 300px;
	padding: 0 10px;
	margin: 0 auto;
}

.boat-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 15px;
	padding-bottom: 8px;
	border-bottom: 2px solid #ddd;
}

.boat-title {
	margin: 0;
	color: #333;
	font-size: 16px;
	font-weight: bold;
	flex: 1;
}

.boat-controls {
	display: flex;
	gap: 8px;
}

.boat-controls .button {
	font-size: 12px;
	padding: 4px 8px;
	height: auto;
	line-height: 1.2;
}

.boat-controls .dashicons {
	font-size: 14px;
	width: 14px;
	height: 14px;
	margin-right: 4px;
	vertical-align: middle;
	line-height: 1;
}

/* Drummer and steersperson sections */
.drummer-section,
.steersperson-section {
	text-align: center;
	margin-bottom: 15px;
}

.steersperson-section {
	margin-bottom: 0;
	margin-top: 15px;
}

.drummer-position,
.steersperson-position {
	min-width: 120px;
	min-height: 35px;
	border: 2px solid #333;
	margin: 0 auto;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 4px;
	font-size: 12px;
}

.drummer-position {
	background: #e8f4f8;
}

.steersperson-position {
	background: #f8e8e8;
}

/* Paddlers section - responsive grid */
.paddlers-section {
	display: grid;
	grid-template-rows: repeat(10, 1fr);
	gap: 6px;
	margin-bottom: 15px;
}

.paddler-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
}

.paddler-position {
	flex: 1;
	max-width: 120px;
	height: 35px;
	border: 2px dashed #666;
	background: #fff;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 11px;
	border-radius: 4px;
	min-height: 35px;
}

.row-number {
	padding: 0 8px;
	color: #666;
	font-weight: bold;
	font-size: 12px;
	flex-shrink: 0;
	min-width: 20px;
	text-align: center;
}

/* Position states */
.position-hover {
	background-color: #d4edda !important;
	border-color: #28a745 !important;
}

.position-filled {
	background-color: #d1ecf1 !important;
	border-color: #007bff !important;
}

.position-filled .position-label {
	display: none;
}

.assigned-paddler {
	position: relative;
	font-weight: bold;
	color: #007bff;
	cursor: move;
	font-size: 10px;
	word-break: break-word;
	line-height: 1.2;
	display: flex;
	align-items: center;
	justify-content: center;
	min-width: 120px;
	min-height: 35px;
}

.assigned-paddler:hover {
	background-color: #b3d9ff !important;
	border-radius: 3px;
}

.remove-paddler {
	position: absolute;
	top: -8px;
	right: -8px;
	width: 18px;
	height: 18px;
	background-color: #dc3545;
	color: white;
	font-weight: bold;
	cursor: pointer;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 10px;
	line-height: 1;
	border: 2px solid white;
	box-shadow: 0 2px 4px rgba(0,0,0,0.2);
	z-index: 10;
}

.remove-paddler:hover {
	background-color: #c82333;
	transform: scale(1.1);
}

.paddler-item:hover {
	background-color: #e9ecef !important;
	transform: scale(1.05);
}

.drop-zone-hover {
	background-color: #fff3cd !important;
	border-color: #ffc107 !important;
}

#available-paddlers.drop-zone-hover {
	background-color: #fff3cd !important;
	border-color: #ffc107 !important;
	border-style: solid !important;
}

.ui-draggable-dragging {
	z-index: 1000 !important;
}

/* Mobile touch improvements */
.paddler-item,
.assigned-paddler {
	touch-action: none;
	-webkit-user-select: none;
	-moz-user-select: none;
	-ms-user-select: none;
	user-select: none;
}

@media (max-width: 768px) {
	.paddler-item {
		padding: 10px 14px; /* Larger touch targets */
		font-size: 14px;
		min-height: 44px; /* Apple's recommended minimum touch target */
	}
	
	.position {
		min-height: 44px; /* Ensure drop targets are large enough */
	}
	
	.assigned-paddler {
		min-width: 100px;
		min-height: 40px;
	}
	
	.remove-paddler {
		width: 20px;
		height: 20px;
		font-size: 11px;
		top: -10px;
		right: -10px;
	}
}

/* Action buttons styling */
.action-buttons {
	margin-top: 20px !important;
	text-align: center;
	padding-top: 15px;
	border-top: 1px solid #ddd;
}

#empty-boat,
#save-seating-plan {
	min-width: 140px;
	margin: 5px;
}

#empty-boat .dashicons,
#save-seating-plan .dashicons {
	margin-right: 5px;
	vertical-align: middle;
}

#save-status {
	margin-top: 10px;
	font-size: 14px;
	min-height: 20px;
}

/* Global controls button alignment */
#global-controls .button .dashicons {
	vertical-align: middle;
	line-height: 1;
}

/* Mobile responsive styles */
@media (max-width: 768px) {
	.dragon-boat-layout {
		padding: 15px 10px !important;
	}
	
	/* Stack boats vertically on mobile */
	.boats-container {
		flex-direction: column;
		gap: 20px;
	}
	
	.boat-container {
		padding: 0 5px;
		min-width: unset;
		max-width: 100%;
	}
	
	.boat-header {
		flex-direction: column;
		gap: 10px;
		align-items: stretch;
	}
	
	.boat-title {
		font-size: 15px;
		text-align: center;
	}
	
	.boat-controls {
		justify-content: center;
		gap: 6px;
	}
	
	.boat-controls .button {
		font-size: 11px;
		padding: 3px 6px;
	}
	
	.paddler-row {
		gap: 4px;
	}
	
	.paddler-position {
		max-width: none;
		min-width: 80px;
		font-size: 10px;
	}
	
	.row-number {
		padding: 0 4px;
		min-width: 16px;
		font-size: 11px;
	}
	
	.drummer-position,
	.steersperson-position {
		min-width: 100px;
		min-height: 40px;
		font-size: 11px;
	}
	
	.assigned-paddler {
		font-size: 9px;
	}
	
	.remove-paddler {
		margin-left: 2px;
		font-size: 10px;
	}
	
	#available-paddlers {
		gap: 6px;
		padding: 10px;
	}
	
	.paddler-item {
		padding: 6px 10px;
		font-size: 13px;
	}
}

@media (max-width: 480px) {
	.paddlers-section {
		gap: 4px;
	}
	
	.paddler-position {
		min-width: 60px;
		height: 32px;
		font-size: 9px;
	}
	
	.row-number {
		padding: 0 3px;
		min-width: 14px;
		font-size: 10px;
	}
	
	.drummer-position,
	.steersperson-position {
		min-width: 80px;
		min-height: 32px;
		font-size: 10px;
	}
	
	.assigned-paddler {
		min-width: 80px;
		min-height: 32px;
		font-size: 8px;
	}
	
	.remove-paddler {
		width: 16px;
		height: 16px;
		font-size: 8px;
		top: -8px;
		right: -8px;
	}
	
	#available-paddlers {
		gap: 4px;
		padding: 8px;
	}
	
	.paddler-item {
		padding: 5px 8px;
		font-size: 12px;
	}
	
	.dragon-boat-layout {
		padding: 10px 5px !important;
	}
	
	/* Stack buttons vertically on very small screens */
	.action-buttons {
		padding: 10px 5px;
	}
	
	#empty-boat,
	#save-seating-plan {
		display: block;
		width: 100%;
		max-width: 200px;
		margin: 5px auto;
	}
}
</style>
