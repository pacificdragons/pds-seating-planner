// @ts-check
const { test, expect } = require('@playwright/test');

// Default boat-count rule and plan metadata stamping, driven by the parametric
// fixture (?type=&n=). One boat opens per boatCapacity paddlers, rounded up:
// Dragon Boat capacity 21 (drummer + 20; steerer is a coach), OC6 capacity 6.

async function open(page, type, n) {
  await page.goto(`/tests/fixture-count.html?type=${type}&n=${n}`);
  await page.waitForFunction(
    () =>
      window.jQuery &&
      window.jQuery.support && window.jQuery.support.touch === true &&
      !!window.SPH &&
      document.querySelectorAll('.boat-container').length > 0
  );
}

const boatCount = (page) => page.locator('.boat-container').count();
const meta = (page) =>
  page.evaluate(() => {
    try {
      return JSON.parse(document.getElementById('seating-plan-data').value).metadata;
    } catch (e) {
      return { parseError: String(e) };
    }
  });

test.describe('default boat count', () => {
  // [type, paddlers, expected boats]. The pairs either side of a capacity
  // multiple pin the capacity exactly (e.g. dragon 21->1 but 22->2 means 21).
  const cases = [
    ['dragon', 0, 1],
    ['dragon', 1, 1],
    ['dragon', 21, 1],
    ['dragon', 22, 2],
    ['dragon', 42, 2],
    ['dragon', 43, 3],
    ['oc6', 0, 1],
    ['oc6', 6, 1],
    ['oc6', 7, 2],
    ['oc6', 12, 2],
    ['oc6', 13, 3],
    ['oc6', 18, 3],
  ];

  for (const [type, n, expected] of cases) {
    test(`${type}: ${n} paddlers => ${expected} boat(s)`, async ({ page }) => {
      await open(page, type, n);
      expect(await boatCount(page)).toBe(expected);
    });
  }
});

test.describe('plan metadata stamping', () => {
  test('a fresh plan stamps its type and boat count into the hidden field', async ({ page }) => {
    // Matters because the WordPress "Update" button saves the hidden field via
    // save_post without ever touching the "Save Seating Plan" AJAX path.
    await open(page, 'oc6', 18);
    const m = await meta(page);
    expect(m.type).toBe('oc6');
    expect(m.boatCount).toBe(3);
  });

  test('Dragon Boat stamps type "dragon"', async ({ page }) => {
    await open(page, 'dragon', 10);
    const m = await meta(page);
    expect(m.type).toBe('dragon');
    expect(m.boatCount).toBe(1);
  });
});
