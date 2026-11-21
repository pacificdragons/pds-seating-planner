<?php

/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin. It is
 * included by the [db_seating_planner] shortcode, which provides:
 *   - $seating_data : the raw _pds_seating_plan JSON string
 *   - $boat_type    : 'dragon' | 'oc6' (resolved layout)
 *
 * Draft plans are gated in the shortcode (hidden from non-coaches), so this
 * template only ever renders a plan that should be shown.
 *
 * @link       https://pacificdragons.com.au
 * @since      1.0.0
 *
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/public/partials
 */

$seating_data_decoded = isset( $seating_data ) ? json_decode( $seating_data, true ) : array();
$boats_array = ( ! empty( $seating_data_decoded ) && isset( $seating_data_decoded['boats'] ) && is_array( $seating_data_decoded['boats'] ) )
	? $seating_data_decoded['boats']
	: array();
if ( empty( $boats_array ) ) {
	$boats_array = array( array() ); // single empty boat
}

$pds_boat_type = isset( $boat_type ) ? $boat_type : 'dragon';

// Boat names (metadata.boatNames). WordPress may have stored the sparse object
// as a JSON array on save, so just read by index with a falsy check.
$pds_boat_names = ( isset( $seating_data_decoded['metadata']['boatNames'] ) && is_array( $seating_data_decoded['metadata']['boatNames'] ) )
	? $seating_data_decoded['metadata']['boatNames']
	: array();
?>

<div id="pds-seating-planner-public" class="<?php echo $pds_boat_type === 'oc6' ? 'is-oc6' : 'is-dragon'; ?>">
	<h6>Session seating plan</h6>
	<div class="dragon-boat-layout">
		<div class="boats-container">
			<?php
			foreach ( $boats_array as $boat_index => $boat_data ) :
				if ( ! is_array( $boat_data ) ) {
					$boat_data = array();
				}
				// Per-boat heading: the custom name, else "Boat N".
				$pds_name = isset( $pds_boat_names[ $boat_index ] ) ? trim( (string) $pds_boat_names[ $boat_index ] ) : '';
				$pds_heading = ( '' !== $pds_name ) ? $pds_name : 'Boat ' . ( $boat_index + 1 );
			?>
			<div class="boat-container <?php echo $pds_boat_type === 'oc6' ? 'oc6-boat' : ''; ?>">
				<h6 class="pds-boat-heading"><?php echo esc_html( $pds_heading ); ?></h6>

				<?php if ( 'oc6' === $pds_boat_type ) : ?>
				<!-- OC6: a single file of 6 seats (seat 6 steers). No left/right. -->
				<div class="paddlers-section oc6-seats">
					<?php for ( $seat = 1; $seat <= 5; $seat++ ) :
						$seat_key = 'seat-' . $seat;
					?>
					<div class="paddler-row oc6-row">
						<div class="row-number"><?php echo $seat; ?></div>
						<div class="position paddler-position oc6-seat">
							<?php if ( isset( $boat_data[ $seat_key ] ) && ! empty( $boat_data[ $seat_key ]['userName'] ) ) : ?>
								<span class="paddler-name"><?php echo esc_html( $boat_data[ $seat_key ]['userName'] ); ?></span>
							<?php else : ?>
								<span class="position-label"><?php echo $seat; ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php endfor; ?>
				</div>
				<div class="steersperson-section">
					<div class="position steersperson-position oc6-seat">
						<?php if ( isset( $boat_data['seat-6'] ) && ! empty( $boat_data['seat-6']['userName'] ) ) : ?>
							<span class="paddler-name"><?php echo esc_html( $boat_data['seat-6']['userName'] ); ?></span>
						<?php else : ?>
							<span class="position-label">Steerer</span>
						<?php endif; ?>
					</div>
				</div>

				<?php else : ?>
				<!-- Dragon Boat: drummer + 10 left/right rows + steersperson. -->
				<div class="drummer-section">
					<div class="position drummer-position">
						<?php if ( isset( $boat_data['drummer'] ) && ! empty( $boat_data['drummer']['userName'] ) ) : ?>
							<span class="paddler-name"><?php echo esc_html( $boat_data['drummer']['userName'] ); ?></span>
						<?php else : ?>
							<span class="position-label">Drummer</span>
						<?php endif; ?>
					</div>
				</div>

				<div class="paddlers-section">
					<?php for ( $row = 1; $row <= 10; $row++ ) : ?>
					<div class="paddler-row">
						<div class="position paddler-position left-position">
							<?php
							$left_key = 'left-' . $row;
							if ( isset( $boat_data[ $left_key ] ) && ! empty( $boat_data[ $left_key ]['userName'] ) ) :
							?>
								<span class="paddler-name"><?php echo esc_html( $boat_data[ $left_key ]['userName'] ); ?></span>
							<?php else : ?>
								<span class="position-label">L<?php echo $row; ?></span>
							<?php endif; ?>
						</div>
						<div class="row-number"><?php echo $row; ?></div>
						<div class="position paddler-position right-position">
							<?php
							$right_key = 'right-' . $row;
							if ( isset( $boat_data[ $right_key ] ) && ! empty( $boat_data[ $right_key ]['userName'] ) ) :
							?>
								<span class="paddler-name"><?php echo esc_html( $boat_data[ $right_key ]['userName'] ); ?></span>
							<?php else : ?>
								<span class="position-label">R<?php echo $row; ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php endfor; ?>
				</div>

				<div class="steersperson-section">
					<div class="position steersperson-position">
						<?php if ( isset( $boat_data['steersperson'] ) && ! empty( $boat_data['steersperson']['userName'] ) ) : ?>
							<span class="paddler-name"><?php echo esc_html( $boat_data['steersperson']['userName'] ); ?></span>
						<?php else : ?>
							<span class="position-label">Steerer</span>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
