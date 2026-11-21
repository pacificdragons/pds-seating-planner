// @ts-check
const { test, expect } = require('@playwright/test');

// Boat naming (both boat types): an editable name field per boat, shown only
// when there are 2+ boats, persisted into metadata.boatNames on every keystroke.

async function waitBoats(page, n) {
  await page.waitForFunction(
    (count) =>
      window.jQuery &&
      window.jQuery.support && window.jQuery.support.touch === true &&
      !!window.SPH &&
      document.querySelectorAll('.boat-container').length === count,
    n
  );
}

// Read metadata.boatNames straight off the hidden field the form submits.
const boatNames = (page) =>
  page.evaluate(() => {
    try {
      return JSON.parse(document.getElementById('seating-plan-data').value).metadata.boatNames;
    } catch (e) {
      return { parseError: String(e) };
    }
  });

test.describe('boat naming', () => {
  test('names in WordPress array form load into the inputs', async ({ page }) => {
    // boatNames saved as ["Alpha","","Charlie"] (what wp_json_encode makes of a
    // sparse object) must land on the right boats; the blank keeps its placeholder.
    await page.goto('/tests/fixture-naming.html');
    await waitBoats(page, 3);
    const inputs = page.locator('.boat-name-input');
    await expect(inputs.nth(0)).toHaveValue('Alpha');
    await expect(inputs.nth(1)).toHaveValue('');
    await expect(inputs.nth(1)).toHaveAttribute('placeholder', 'Boat 2');
    await expect(inputs.nth(2)).toHaveValue('Charlie');
  });

  test('typing a name persists into metadata.boatNames (survives the Update path)', async ({ page }) => {
    await page.goto('/tests/fixture-oc6-empty.html'); // 18 paddlers => 3 empty boats
    await waitBoats(page, 3);
    await page.locator('.boat-name-input').nth(1).fill('Women’s A');
    const names = await boatNames(page);
    expect(names['1']).toBe('Women’s A');
    // Clearing the field removes the key rather than storing "".
    await page.locator('.boat-name-input').nth(1).fill('');
    expect((await boatNames(page))['1']).toBeUndefined();
  });

  test('Enter in the name field does not submit the page', async ({ page }) => {
    await page.goto('/tests/fixture-oc6-empty.html');
    await waitBoats(page, 3);
    const input = page.locator('.boat-name-input').nth(0);
    await input.fill('Mixed');
    await input.press('Enter');
    // Still on the fixture, app still alive, value intact.
    await expect(page).toHaveURL(/fixture-oc6-empty\.html$/);
    await expect(input).toHaveValue('Mixed');
    expect((await boatNames(page))['0']).toBe('Mixed');
  });

  test('a single boat has no name field', async ({ page }) => {
    await page.goto('/tests/fixture.html'); // seeded boatCount:1 => one boat
    await waitBoats(page, 1);
    expect(await page.locator('.boat-name-input').count()).toBe(0);
  });
});

test.describe('boat naming — add / remove', () => {
  test('a boat name survives adding another boat (rebuild)', async ({ page }) => {
    await page.goto('/tests/fixture-oc6-empty.html'); // 3 boats
    await waitBoats(page, 3);
    await page.locator('.boat-name-input').nth(0).fill('Alpha');
    await page.locator('#add-boat').click();
    await waitBoats(page, 4);
    await expect(page.locator('.boat-name-input').nth(0)).toHaveValue('Alpha');
    expect((await boatNames(page))['0']).toBe('Alpha');
    // The new 4th boat is blank with its own "Boat 4" placeholder.
    await expect(page.locator('.boat-name-input').nth(3)).toHaveValue('');
    await expect(page.locator('.boat-name-input').nth(3)).toHaveAttribute('placeholder', 'Boat 4');
  });

  test('each boat shows its number badge, which follows add', async ({ page }) => {
    await page.goto('/tests/fixture-oc6-empty.html'); // 3 boats
    await waitBoats(page, 3);
    expect(await page.locator('.boat-number').allTextContents()).toEqual(['1', '2', '3']);
    await page.locator('#add-boat').click();
    await waitBoats(page, 4);
    expect(await page.locator('.boat-number').allTextContents()).toEqual(['1', '2', '3', '4']);
  });

  test('removing the last boat drops only that boat’s name', async ({ page }) => {
    page.on('dialog', (d) => d.accept()); // removeLastBoat asks to confirm
    await page.goto('/tests/fixture-oc6-empty.html'); // 3 boats
    await waitBoats(page, 3);
    await page.locator('.boat-name-input').nth(0).fill('Alpha');
    await page.locator('.boat-name-input').nth(2).fill('Charlie');
    await page.locator('#remove-last-boat').click();
    await waitBoats(page, 2);
    // Charlie (index 2) is gone; Alpha (index 0) is kept.
    expect(await boatNames(page)).toEqual({ '0': 'Alpha' });
  });
});
