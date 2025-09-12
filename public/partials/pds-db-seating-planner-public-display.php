<?php

/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       https://pacificdragons.com.au
 * @since      1.0.0
 *
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/public/partials
 */
?>

<div id="pds-seating-planner-public">
	<h6>Session seating plan</h6>
	<div class="dragon-boat-layout">
		<div class="boats-container">
			<?php
			// Debug output
			if ( WP_DEBUG ) {
				echo '<!-- Debug: Raw seating_data = ' . esc_html($seating_data) . ' -->';
			}
			
			// Parse seating data if available
			$seating_data_decoded = isset($seating_data) ? json_decode($seating_data, true) : array();
			$boats_array = !empty($seating_data_decoded) && isset($seating_data_decoded['boats']) ? $seating_data_decoded['boats'] : array();
			$boat_count = !empty($boats_array) ? count($boats_array) : 1;
			
			// Debug output
			if ( WP_DEBUG ) {
				echo '<!-- Debug: Decoded data = ' . esc_html(print_r($seating_data_decoded, true)) . ' -->';
				echo '<!-- Debug: Boats array = ' . esc_html(print_r($boats_array, true)) . ' -->';
			}
			
			// Display boats - if no data, show empty boat
			if (empty($boats_array)) {
				$boats_array = array(array()); // Single empty boat
			}
			
			foreach ($boats_array as $boat_index => $boat_data) :
				$boat_num = $boat_index + 1;
				
				// Debug output for each boat
				if ( WP_DEBUG ) {
					echo '<!-- Debug: Boat ' . $boat_num . ' data = ' . esc_html(print_r($boat_data, true)) . ' -->';
				}
			?>
			<div class="boat-container">
				<!-- Drummer Section -->
				<div class="drummer-section">
					<div class="position drummer-position">
						<?php if (isset($boat_data['drummer']) && !empty($boat_data['drummer']['userName'])): ?>
							<span class="paddler-name"><?php echo esc_html($boat_data['drummer']['userName']); ?></span>
						<?php else: ?>
							<span class="position-label">Drummer</span>
						<?php endif; ?>
					</div>
				</div>
				
				<!-- Paddlers Section -->
				<div class="paddlers-section">
					<?php for ($row = 1; $row <= 10; $row++) : ?>
					<div class="paddler-row">
						<div class="position paddler-position left-position">
							<?php 
							$left_key = 'left-' . $row;
							if (isset($boat_data[$left_key]) && !empty($boat_data[$left_key]['userName'])): 
							?>
								<span class="paddler-name"><?php echo esc_html($boat_data[$left_key]['userName']); ?></span>
							<?php else: ?>
								<span class="position-label">L<?php echo $row; ?></span>
							<?php endif; ?>
						</div>
						<div class="row-number"><?php echo $row; ?></div>
						<div class="position paddler-position right-position">
							<?php 
							$right_key = 'right-' . $row;
							if (isset($boat_data[$right_key]) && !empty($boat_data[$right_key]['userName'])): 
							?>
								<span class="paddler-name"><?php echo esc_html($boat_data[$right_key]['userName']); ?></span>
							<?php else: ?>
								<span class="position-label">R<?php echo $row; ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php endfor; ?>
				</div>
				
				<!-- Steersperson Section -->
				<div class="steersperson-section">
					<div class="position steersperson-position">
						<?php if (isset($boat_data['steersperson']) && !empty($boat_data['steersperson']['userName'])): ?>
							<span class="paddler-name"><?php echo esc_html($boat_data['steersperson']['userName']); ?></span>
						<?php else: ?>
							<span class="position-label">Steerer</span>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
