// @ts-check
const { test, expect } = require('@playwright/test');

// OC6 layout: one line of 6 seats (seat-6 steers). Fixture seeds seat-1 = Alice
// (100) and seat-6 = Bob (200); available = [10, 11, 12]. No weights, no Boat Load.
//
// These tests exercise the boat-type plumbing in the admin JS: the injected
// #pds-boat-config drives seat capacity, the position => label map, and — the
// bit that bit us — the weights overlay gate.

const snap = (page) => page.evaluate(() => window.SPH.snap());

async function waitReady(page) {
  await page.waitForFunction(() =>
    window.jQuery &&
    window.jQuery.support && window.jQuery.support.touch === true &&
    document.querySelectorAll('.position.position-filled').length === 2 &&
    !!window.SPH
  );
}

// Open the OC6 fixture. `weightsPref` seeds the SHARED pdsSeatingShowWeights
// localStorage key (as a Dragon Boat board would) BEFORE the app boots, so we
// can prove it still never shows on OC6.
async function openOc6(page, weightsPref) {
  if (weightsPref) {
    await page.addInitScript(() => {
      try { window.localStorage.setItem('pdsSeatingShowWeights', '1'); } catch (e) {}
    });
  }
  await page.goto('/tests/fixture-oc6.html');
  await waitReady(page);
}

test.describe('OC6 layout', () => {
  test('renders six seats with the steerer last', async ({ page }) => {
    await openOc6(page);
    const positions = await page.$$eval('.position', (els) =>
      els.map((e) => e.getAttribute('data-position'))
    );
    expect(positions).toEqual(['seat-1', 'seat-2', 'seat-3', 'seat-4', 'seat-5', 'seat-6']);
    // seat-6 is the steerer slot (dark steersperson styling), not a plain seat.
    expect(await page.locator('.steersperson-position[data-position="seat-6"]').count()).toBe(1);
    // No Dragon-Boat-only chrome.
    expect(await page.locator('#toggle-weights').count()).toBe(0);
    expect(await page.locator('#boat-load').count()).toBe(0);
  });

  test('seat-to-seat swap works (seat-1 <-> seat-6)', async ({ page }) => {
    await openOc6(page);
    await page.evaluate(() => window.SPH.swap('seat-1', 'seat-6', 'body'));
    const s = await snap(page);
    expect(s.seats['seat-1']).toBe('200');
    expect(s.seats['seat-6']).toBe('100');
    expect(s.avail.sort()).toEqual(['10', '11', '12']); // nobody dumped to available
    expect(s.model).toEqual({ 'seat-1': '200', 'seat-6': '100' });
  });

  test('removing a paddler restores the OC6 label, not a "SEAT6" fallback', async ({ page }) => {
    await openOc6(page);
    await page.evaluate(() => window.SPH.tapRemove('seat-6'));
    // Label comes from the injected positionLabels map: seat-6 => "Steerer".
    await expect(
      page.locator('.position[data-position="seat-6"] .position-label')
    ).toHaveText('Steerer');
    const s = await snap(page);
    expect(s.seats['seat-6']).toBeUndefined();
    expect(s.avail).toContain('200');
  });

  test('assign an available paddler onto an empty seat', async ({ page }) => {
    await openOc6(page);
    await page.evaluate(() => window.SPH.assign('11', 'seat-3'));
    const s = await snap(page);
    expect(s.seats['seat-3']).toBe('11');
    expect(s.model['seat-3']).toBe('11');
    expect(s.avail).not.toContain('11');
  });

  test('a fresh plan opens one canoe per 6 paddlers (18 => 3 boats)', async ({ page }) => {
    await page.goto('/tests/fixture-oc6-empty.html');
    await page.waitForFunction(() =>
      window.jQuery &&
      window.jQuery.support && window.jQuery.support.touch === true &&
      !!window.SPH &&
      document.querySelectorAll('.boat-container').length > 0
    );
    expect(await page.locator('.boat-container').count()).toBe(3);
    // Each canoe has its 6 seats wired with the right boat index.
    expect(await page.locator('.position[data-boat="0"]').count()).toBe(6);
    expect(await page.locator('.position[data-boat="2"]').count()).toBe(6);
  });

  test('weights overlay never leaks onto OC6, even left on for Dragon Boat', async ({ page }) => {
    await openOc6(page, /* weightsPref */ true);
    // Force an updateBalance() pass by mutating the board.
    await page.evaluate(() => window.SPH.assign('10', 'seat-2'));
    expect(await page.locator('.sp-no-weight').count()).toBe(0);
    expect(await page.locator('.sp-fix-weight').count()).toBe(0);
    expect(await page.locator('.pds-row-delta').count()).toBe(0);
    expect(await page.locator('.pds-boat-balance').count()).toBe(0);
    expect(await page.locator('#pds-seating-planner-app.sp-show-weights').count()).toBe(0);
  });
});

test.describe('Dragon Boat default boat count', () => {
  // Same ceil(paddlers / capacity) rule as OC6, but Dragon Boat capacity is 21
  // (1 drummer + 20 paddlers; the steerer is a coach, not a booked paddler). A
  // full crew of 21 must stay in ONE boat — the pre-1.8.0 logic (>20 ? 2 : 1)
  // wrongly opened a second boat at 21.
  test('a full 21-paddler crew opens a single boat', async ({ page }) => {
    await page.goto('/tests/fixture-dragon-empty.html');
    await page.waitForFunction(() =>
      window.jQuery &&
      window.jQuery.support && window.jQuery.support.touch === true &&
      !!window.SPH &&
      document.querySelectorAll('.boat-container').length > 0
    );
    expect(await page.locator('.boat-container').count()).toBe(1);
  });
});
