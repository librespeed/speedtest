const { test, expect } = require('@playwright/test');
const { baseUrls } = require('./helpers/env');

const specialTitle = 'Grüße "Tempo" \'Österreich\'';

test.describe('TITLE special characters', () => {
  test('modern page title supports umlauts and quotes', async ({ page }) => {
    await page.goto(`${baseUrls.standaloneNew}/index-modern.html`);
    await expect(page).toHaveTitle(`${specialTitle} - Free and Open Source Speedtest`);
  });

  test('classic heading supports umlauts and quotes', async ({ page }) => {
    await page.goto(`${baseUrls.standaloneNew}/index-classic.html`);
    await expect(page.locator('h1').first()).toHaveText(specialTitle);
  });
});
