<?php
/**
 * Test harness: render the public seating partial with fixture data.
 *
 * Usage: php render-public.php '<seating_json>' '<boat_type>'
 * Stubs the one WordPress dependency (esc_html) and includes the real partial,
 * so the Playwright suite can assert on the actual server-rendered markup.
 */

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

$seating_data = isset( $argv[1] ) ? $argv[1] : '';
$boat_type    = isset( $argv[2] ) ? $argv[2] : 'dragon';

include __DIR__ . '/../../public/partials/pds-db-seating-planner-public-display.php';
