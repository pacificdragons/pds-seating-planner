<?php

/**
 * Boat-type definitions for the Seat Planner.
 *
 * Single source of truth for the two boat layouts the planner supports and for
 * resolving which layout a given event/plan uses. The admin metabox, the admin
 * JS (via an injected config blob) and the public shortcode all read from here
 * so the seat geometry is defined in exactly one place.
 *
 * Layouts:
 *  - dragon : Dragon Boat — drummer + 10 left/right rows + steersperson (20+).
 *             Weights overlay and Boat Load are Dragon-Boat only.
 *  - oc6    : OC6 outrigger canoe — 6 single-file seats, seat 6 steers. Weight
 *             is irrelevant for OC6, so the weights overlay and Boat Load are off.
 *
 * @package    Pds_Db_Seating_Planner
 * @subpackage Pds_Db_Seating_Planner/includes
 */

class Pds_Db_Seating_Planner_Boats {

	/**
	 * Map of event-categories term slug => boat layout type. An event tagged with
	 * one of these gets the planner. If an event somehow carries both, the first
	 * listed wins (Dragon Boat), which keeps every existing Dragon-Boat event on
	 * its current layout. (As of the OC6 rollout no event is tagged both.)
	 */
	const CATEGORY_TYPE_MAP = array(
		'dragon-boat' => 'dragon',
		'oc6'         => 'oc6',
	);

	/**
	 * All boat layout definitions, keyed by type.
	 *
	 * - label         : human name used in the metabox title.
	 * - boat_capacity : seats per boat; drives the admin JS "show a 2nd boat by
	 *                   default" threshold.
	 * - show_weights  : whether the admin weight/lateral-balance overlay applies.
	 * - show_boat_load: whether the gender-split Boat Load auto-fill applies.
	 *
	 * @return array
	 */
	public static function layouts() {
		return array(
			'dragon' => array(
				// Capacity counts seats filled from the confirmed-paddler pool:
				// 1 drummer + 20 paddlers = 21. The steerer/sweep is a coach, not
				// a booked paddler, so it is not counted — a full crew of 21 opens
				// a single boat; the 22nd paddler triggers a second.
				'label'          => 'Dragon Boat',
				'boat_capacity'  => 21,
				'show_weights'   => true,
				'show_boat_load' => true,
			),
			'oc6' => array(
				'label'          => 'OC6',
				'boat_capacity'  => 6,
				'show_weights'   => false,
				'show_boat_load' => false,
			),
		);
	}

	/**
	 * Layout definition for a type, falling back to Dragon Boat for anything
	 * unrecognised (e.g. a plan with a missing/garbage type).
	 *
	 * @param  string $type
	 * @return array
	 */
	public static function layout( $type ) {
		$layouts = self::layouts();
		return isset( $layouts[ $type ] ) ? $layouts[ $type ] : $layouts['dragon'];
	}

	/**
	 * Every boat type implied by an event's categories, in precedence order
	 * (first = the winner). Normally one entry; two only when an event is tagged
	 * both Dragon Boat and Outrigger — see category_type() / the metabox notice.
	 *
	 * @param  int $post_id
	 * @return array  e.g. ['dragon'], ['oc6'], ['dragon','oc6'] or []
	 */
	public static function category_types( $post_id ) {
		$terms = wp_get_post_terms( $post_id, 'event-categories' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$slugs = array();
		foreach ( $terms as $term ) {
			$slugs[ $term->slug ] = true;
		}

		// Iterate the map (not the post's terms) so precedence is deterministic:
		// the order of CATEGORY_TYPE_MAP decides the winner (Dragon Boat first).
		$types = array();
		foreach ( self::CATEGORY_TYPE_MAP as $slug => $type ) {
			if ( isset( $slugs[ $slug ] ) ) {
				$types[] = $type;
			}
		}
		return $types;
	}

	/**
	 * The boat type implied by an event's categories, or null when the event
	 * carries neither planner category (so no metabox is shown). When an event
	 * is tagged with both, the first in precedence order (Dragon Boat) wins.
	 *
	 * @param  int $post_id
	 * @return string|null  'dragon' | 'oc6' | null
	 */
	public static function category_type( $post_id ) {
		$types = self::category_types( $post_id );
		return empty( $types ) ? null : $types[0];
	}

	/**
	 * Resolve the layout type to actually render for a plan.
	 *
	 * A plan that already has a paddler seated keeps its stored type (missing =>
	 * dragon), so re-tagging a populated event's category never orphans its saved
	 * seats under a layout whose position keys don't match. An empty plan follows
	 * the event category, falling back to the stored type and finally Dragon Boat.
	 *
	 * @param  array|null  $decoded        Decoded _pds_seating_plan, or null/[].
	 * @param  string|null $category_type  Type implied by the event category.
	 * @return string                      'dragon' | 'oc6'
	 */
	public static function resolve_type( $decoded, $category_type ) {
		$stored = isset( $decoded['metadata']['type'] ) ? $decoded['metadata']['type'] : null;

		if ( self::plan_has_paddler( $decoded ) ) {
			return self::normalise_type( $stored );
		}

		if ( null !== $category_type ) {
			return $category_type;
		}

		return self::normalise_type( $stored );
	}

	/**
	 * Clamp an arbitrary stored type to a known layout, defaulting to dragon.
	 *
	 * @param  string|null $type
	 * @return string
	 */
	public static function normalise_type( $type ) {
		$layouts = self::layouts();
		return ( is_string( $type ) && isset( $layouts[ $type ] ) ) ? $type : 'dragon';
	}

	/**
	 * Whether a decoded plan has at least one seat with an assigned paddler.
	 * Position-key agnostic, so it works for every layout.
	 *
	 * @param  array|null $decoded
	 * @return bool
	 */
	public static function plan_has_paddler( $decoded ) {
		if ( empty( $decoded['boats'] ) || ! is_array( $decoded['boats'] ) ) {
			return false;
		}
		foreach ( $decoded['boats'] as $boat ) {
			if ( ! is_array( $boat ) ) {
				continue;
			}
			foreach ( $boat as $seat ) {
				if ( is_array( $seat ) && ! empty( $seat['userName'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Ordered list of seat positions for a layout, each as
	 * [ 'key' => ..., 'label' => ..., 'role' => 'drummer'|'left'|'right'|'seat'|'steerer' ].
	 *
	 * This is the one definition the templates (admin + public) and the JS label
	 * map are all built from.
	 *
	 * @param  string $type
	 * @return array
	 */
	public static function positions( $type ) {
		if ( 'oc6' === $type ) {
			$positions = array();
			// Seats 1-5 are plain paddling seats; seat 6 steers.
			for ( $n = 1; $n <= 5; $n++ ) {
				$positions[] = array( 'key' => 'seat-' . $n, 'label' => (string) $n, 'role' => 'seat' );
			}
			$positions[] = array( 'key' => 'seat-6', 'label' => 'Steerer', 'role' => 'steerer' );
			return $positions;
		}

		// Dragon Boat: drummer, 10 left/right rows, steersperson.
		$positions = array(
			array( 'key' => 'drummer', 'label' => 'Drummer', 'role' => 'drummer' ),
		);
		for ( $row = 1; $row <= 10; $row++ ) {
			$positions[] = array( 'key' => 'left-' . $row, 'label' => 'L' . $row, 'role' => 'left', 'row' => $row );
			$positions[] = array( 'key' => 'right-' . $row, 'label' => 'R' . $row, 'role' => 'right', 'row' => $row );
		}
		$positions[] = array( 'key' => 'steersperson', 'label' => 'Steerer', 'role' => 'steerer' );
		return $positions;
	}

	/**
	 * position key => label map, used to seed the admin JS so a cleared seat can
	 * restore its own label without the JS hard-coding any geometry.
	 *
	 * @param  string $type
	 * @return array
	 */
	public static function position_labels( $type ) {
		$labels = array();
		foreach ( self::positions( $type ) as $position ) {
			$labels[ $position['key'] ] = $position['label'];
		}
		return $labels;
	}

	/**
	 * The config blob injected into the admin page for the JS to read.
	 *
	 * @param  string $type
	 * @return array
	 */
	public static function js_config( $type ) {
		$layout = self::layout( $type );
		return array(
			'type'           => $type,
			'boatCapacity'   => $layout['boat_capacity'],
			'showWeights'    => (bool) $layout['show_weights'],
			'showBoatLoad'   => (bool) $layout['show_boat_load'],
			'positionLabels' => self::position_labels( $type ),
		);
	}
}
