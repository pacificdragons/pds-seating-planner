// @ts-check
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const path = require('path');

// Pure (WordPress-free) layout / type-resolution logic from the Boats class,
// driven via `php`. Locks the rules that only had live verification before:
// a populated plan keeps its stored type, an empty plan follows the category,
// and the OC6/Dragon position maps.

const HARNESS = path.join(__dirname, 'php', 'boats.php');

/** Call a static Boats method with an args array; returns the parsed result. */
function boats(method, args) {
  const out = execFileSync('php', [HARNESS, method, JSON.stringify(args)], {
    encoding: 'utf8',
  });
  return JSON.parse(out);
}

const withPaddler = { boats: [{ 'seat-1': { userName: 'X' } }], metadata: { type: 'dragon' } };
const emptyPlan = { boats: [{}], metadata: {} };

test.describe('Boats::resolve_type', () => {
  test('a populated plan keeps its stored type, ignoring the category', () => {
    // Stored "dragon", category says oc6 -> stored wins (re-tagging never orphans seats).
    expect(boats('resolve_type', [withPaddler, 'oc6'])).toBe('dragon');
  });

  test('an empty plan follows the event category', () => {
    expect(boats('resolve_type', [emptyPlan, 'oc6'])).toBe('oc6');
    expect(boats('resolve_type', [emptyPlan, 'dragon'])).toBe('dragon');
  });

  test('falls back to dragon when there is no category and no stored type', () => {
    expect(boats('resolve_type', [emptyPlan, null])).toBe('dragon');
  });

  test('a garbage stored type normalises to dragon', () => {
    const bad = { boats: [{ 'seat-1': { userName: 'X' } }], metadata: { type: 'canoe-9000' } };
    expect(boats('resolve_type', [bad, null])).toBe('dragon');
  });
});

test.describe('Boats::plan_has_paddler', () => {
  test('true only when a seat has a non-empty userName', () => {
    expect(boats('plan_has_paddler', [withPaddler])).toBe(true);
    expect(boats('plan_has_paddler', [emptyPlan])).toBe(false);
    expect(boats('plan_has_paddler', [{ boats: [{ 'seat-1': { userName: '' } }] }])).toBe(false);
  });
});

test.describe('Boats layout maps', () => {
  test('OC6 is 6 seats with seat-6 as the steerer', () => {
    const positions = boats('positions', ['oc6']);
    expect(positions.map((p) => p.key)).toEqual(['seat-1', 'seat-2', 'seat-3', 'seat-4', 'seat-5', 'seat-6']);
    expect(positions[5]).toMatchObject({ key: 'seat-6', label: 'Steerer', role: 'steerer' });
  });

  test('Dragon Boat is drummer + 10 rows + steersperson (22 positions)', () => {
    const positions = boats('positions', ['dragon']);
    expect(positions).toHaveLength(22);
    expect(positions[0].key).toBe('drummer');
    expect(positions[positions.length - 1].key).toBe('steersperson');
  });

  test('js_config carries capacity + flags per type', () => {
    expect(boats('js_config', ['oc6'])).toMatchObject({ type: 'oc6', boatCapacity: 6, showWeights: false, showBoatLoad: false });
    expect(boats('js_config', ['dragon'])).toMatchObject({ type: 'dragon', boatCapacity: 21, showWeights: true, showBoatLoad: true });
  });
});
