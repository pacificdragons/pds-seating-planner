// @ts-check
const { test, expect } = require('@playwright/test');

// Each test starts from a fresh fixture: boat 0 has left-1 = Alice (100) and
// right-1 = Bob (200) filled; available = [10, 11, 12].
test.beforeEach(async ({ page }) => {
  await page.goto('/tests/fixture.html');
  // Wait until the plugin has rendered the two seeded seats AND touch-punch has
  // patched jQuery UI (otherwise touch drags would be no-ops).
  await page.waitForFunction(() =>
    window.jQuery &&
    window.jQuery.support && window.jQuery.support.touch === true &&
    document.querySelectorAll('.position.position-filled').length === 2 &&
    !!window.SPH
  );
});

const snap = (page) => page.evaluate(() => window.SPH.snap());
const count = (arr, v) => arr.filter((x) => x === v).length;

test.describe('seat-to-seat swap', () => {
  test('grabbing the × does NOT dump the dragged paddler into available (bug #1)', async ({ page }) => {
    await page.evaluate(() => window.SPH.swap('left-1', 'right-1', 'x'));
    const s = await snap(page);
    expect(s.seats['left-1']).toBe('200');
    expect(s.seats['right-1']).toBe('100');
    expect(s.avail.sort()).toEqual(['10', '11', '12']); // no phantom 100
    expect(s.model).toEqual({ 'left-1': '200', 'right-1': '100' });
  });

  test('grabbing the body swaps cleanly (control)', async ({ page }) => {
    await page.evaluate(() => window.SPH.swap('left-1', 'right-1', 'body'));
    const s = await snap(page);
    expect(s.seats['left-1']).toBe('200');
    expect(s.seats['right-1']).toBe('100');
    expect(s.avail.sort()).toEqual(['10', '11', '12']);
  });
});

test.describe('remove via ×', () => {
  test('tapping × removes the paddler exactly once (bug #2)', async ({ page }) => {
    await page.evaluate(() => window.SPH.tapRemove('left-1'));
    const s = await snap(page);
    expect(s.seats['left-1']).toBeUndefined();
    expect(count(s.avail, '100')).toBe(1); // not twice
    expect(s.avail.length).toBe(4);
    expect(s.model['left-1']).toBeUndefined();
  });

  test('tapping two different ×s removes each once (dedup not over-broad)', async ({ page }) => {
    await page.evaluate(() => window.SPH.tapRemove('left-1'));
    await page.evaluate(() => window.SPH.tapRemove('right-1'));
    const s = await snap(page);
    expect(s.seats['left-1']).toBeUndefined();
    expect(s.seats['right-1']).toBeUndefined();
    expect(count(s.avail, '100')).toBe(1);
    expect(count(s.avail, '200')).toBe(1);
    expect(s.avail.length).toBe(5);
  });

  test('desktop mouse-click × removes once (desktop path intact)', async ({ page }) => {
    await page.evaluate(() => window.SPH.clickRemove('left-1'));
    const s = await snap(page);
    expect(s.seats['left-1']).toBeUndefined();
    expect(count(s.avail, '100')).toBe(1);
  });
});

test.describe('assignment from available', () => {
  test('available paddler onto an empty seat', async ({ page }) => {
    await page.evaluate(() => window.SPH.assign('10', 'left-2'));
    const s = await snap(page);
    expect(s.seats['left-2']).toBe('10');
    expect(s.avail).not.toContain('10');
    expect(s.model['left-2']).toBe('10');
  });

  test('available paddler onto a filled seat swaps without duplicating (1.6.1 case)', async ({ page }) => {
    // Drop available 10 onto right-1 (Bob/200): 10 seats, 200 returns to available, once.
    await page.evaluate(() => window.SPH.assign('10', 'right-1'));
    const s = await snap(page);
    expect(s.seats['right-1']).toBe('10');
    expect(s.seats['left-1']).toBe('100');
    expect(count(s.avail, '10')).toBe(0); // dropped paddler left available
    expect(count(s.avail, '200')).toBe(1); // displaced paddler back once, not duplicated
    expect(s.avail.sort()).toEqual(['11', '12', '200']);
  });
});

test.describe('unseat by drag', () => {
  test('dragging a seated paddler to the available list returns it once', async ({ page }) => {
    await page.evaluate(() => window.SPH.unseat('left-1'));
    const s = await snap(page);
    expect(s.seats['left-1']).toBeUndefined();
    expect(count(s.avail, '100')).toBe(1);
    expect(s.model['left-1']).toBeUndefined();
  });
});
