<?php
/**
 * Test harness: call a pure (WordPress-free) static method on the Boats class
 * and echo the JSON result, so the Playwright suite can assert the layout /
 * type-resolution logic.
 *
 * Usage: php boats.php '<method>' '<json-args-array>'
 * Only methods with no WordPress dependency are callable this way (layout,
 * positions, position_labels, js_config, resolve_type, plan_has_paddler,
 * normalise_type) — category_type/category_types need wp_get_post_terms.
 */

require __DIR__ . '/../../includes/class-pds-db-seating-planner-boats.php';

$method = isset( $argv[1] ) ? $argv[1] : '';
$args   = isset( $argv[2] ) ? json_decode( $argv[2], true ) : array();
if ( ! is_array( $args ) ) {
	$args = array();
}

$result = call_user_func_array( array( 'Pds_Db_Seating_Planner_Boats', $method ), $args );
echo json_encode( $result );
