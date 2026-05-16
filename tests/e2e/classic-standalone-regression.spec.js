const { test, expect } = require('@playwright/test');
const { baseUrls } = require('./helpers/env');
const { classicStartButton } = require('./helpers/ui');

test.describe('Classic standalone regression coverage', () => {
  test('standalone classic does not get stuck on "No servers available"', async ({ page }) => {
    await page.goto(`${baseUrls.standalone}/index-classic.html`);

    // In standalone mode, classic UI should show the test wrapper directly.
    await expect(page.locator('#testWrapper')).toHaveClass(/visible/, { timeout: 10_000 });
    await expect(page.locator('#loading')).toHaveClass(/hidden/, { timeout: 10_000 });

    await expect(page.locator('#message')).not.toContainText(/No servers available/i);
    await expect(classicStartButton(page)).toBeVisible();
  });
});
