// @ts-check
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

// The public render is server-side PHP, so these specs drive the REAL partial
// via `php` (child_process) and assert on its markup; the CSS checks load that
// markup into a real browser with the real stylesheet inlined. This is what the
// static browser-only harness can't reach on its own.

const ROOT = path.join(__dirname, '..');
const HARNESS = path.join(__dirname, 'php', 'render-public.php');
const PUBLIC_CSS = fs.readFileSync(
  path.join(ROOT, 'public/css/pds-db-seating-planner-public.css'),
  'utf8'
);

/** Render the public partial for a plan object + boat type, return the HTML. */
function renderPublic(plan, boatType) {
  return execFileSync('php', [HARNESS, JSON.stringify(plan), boatType], {
    encoding: 'utf8',
  });
}

/** Load rendered markup into the page with the real public CSS inlined. */
async function styled(page, html) {
  await page.setContent(
    `<!doctype html><html><head><meta charset="utf-8">` +
      `<style>${PUBLIC_CSS}</style></head><body>${html}</body></html>`
  );
}

test.describe('public render — structure', () => {
  test('OC6: single-file seats + steerer + custom boat name, no left/right', () => {
    const plan = {
      boats: [{ 'seat-1': { userId: 5, userName: 'Alice' }, 'seat-6': { userId: 6, userName: 'Bob' } }],
      metadata: { type: 'oc6', boatNames: ['Crew A'] },
    };
    const html = renderPublic(plan, 'oc6');
    expect(html).toContain('boat-container oc6-boat');
    expect(html).toContain('>Crew A<'); // heading = custom name
    expect(html).toContain('Alice');
    expect(html).toContain('Bob');
    expect(html).toContain('steersperson-section'); // seat 6 steers
    expect(html).not.toContain('left-position');
    expect(html).not.toContain('right-position');
    expect(html).not.toContain('drummer');
  });

  test('Dragon Boat: drummer + left/right + steersperson, no oc6', () => {
    const plan = {
      boats: [{ 'left-1': { userName: 'Lefty' }, 'right-1': { userName: 'Righty' }, drummer: { userName: 'Drum' } }],
      metadata: { type: 'dragon' },
    };
    const html = renderPublic(plan, 'dragon');
    expect(html).toContain('drummer-position');
    expect(html).toContain('left-position');
    expect(html).toContain('right-position');
    expect(html).toContain('steersperson-position');
    expect(html).not.toContain('oc6');
  });

  test('boat heading falls back to "Boat N" when unnamed', () => {
    const plan = { boats: [{}, { 'seat-1': { userName: 'X' } }], metadata: { type: 'oc6' } };
    const html = renderPublic(plan, 'oc6');
    expect(html).toContain('>Boat 1<');
    expect(html).toContain('>Boat 2<');
  });

  test('paddler names are HTML-escaped', () => {
    const plan = { boats: [{ 'seat-1': { userName: '<script>evil' } }], metadata: { type: 'oc6' } };
    const html = renderPublic(plan, 'oc6');
    expect(html).toContain('&lt;script&gt;evil');
    expect(html).not.toContain('<script>evil');
  });
});

test.describe('public render — styling (grey, boxes, not pills)', () => {
  test('OC6 filled seat is grey and square-ish, never green or a pill', async ({ page }) => {
    const plan = { boats: [{ 'seat-1': { userName: 'Alice' } }], metadata: { type: 'oc6' } };
    await styled(page, renderPublic(plan, 'oc6'));
    const seat = page.locator('.oc6-seat.paddler-position').first();
    await expect(seat).toHaveCSS('background-color', 'rgb(228, 228, 228)'); // #e4e4e4 grey
    await expect(seat).toHaveCSS('border-radius', '4px'); // box, not a 999px pill
  });

  test('OC6 empty seat is grey, not a green tint', async ({ page }) => {
    const plan = { boats: [{}], metadata: { type: 'oc6' } };
    await styled(page, renderPublic(plan, 'oc6'));
    const seat = page.locator('.oc6-seat.paddler-position').first();
    await expect(seat).toHaveCSS('background-color', 'rgb(246, 246, 246)'); // #f6f6f6 grey
  });

  test('Dragon Boat right-hand seat stays green (unchanged)', async ({ page }) => {
    const plan = { boats: [{ 'right-1': { userName: 'Zed' } }], metadata: { type: 'dragon' } };
    await styled(page, renderPublic(plan, 'dragon'));
    const seat = page.locator('.right-position').first();
    await expect(seat).toHaveCSS('background-color', 'rgb(200, 230, 201)'); // #c8e6c9 green
  });
});
