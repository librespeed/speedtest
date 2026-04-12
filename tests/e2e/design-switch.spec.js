const { test, expect } = require('@playwright/test');
const { baseUrls } = require('./helpers/env');
const { classicStartButton, modernStartButton } = require('./helpers/ui');

test.describe('Design switch behavior', () => {
  test('index.html defaults to classic when useNewDesign=false', async ({ page }) => {
    await page.goto(`${baseUrls.standalone}/index.html`);
    await expect(page).toHaveURL(/index-classic\.html/);
    await expect(classicStartButton(page)).toBeVisible();
  });

  test('index.html defaults to modern when useNewDesign=true', async ({ page }) => {
    await page.goto(`${baseUrls.standaloneNew}/index.html`);
    await expect(page).toHaveURL(/index-modern\.html/);
    await expect(modernStartButton(page)).toBeVisible();
  });

  test('query override design=new forces modern', async ({ page }) => {
    await page.goto(`${baseUrls.standalone}/index.html?design=new`);
    await expect(page).toHaveURL(/index-modern\.html\?design=new/);
    await expect(modernStartButton(page)).toBeVisible();
  });

  test('query override design=old forces classic', async ({ page }) => {
    await page.goto(`${baseUrls.standaloneNew}/index.html?design=old`);
    await expect(page).toHaveURL(/index-classic\.html\?design=old/);
    await expect(classicStartButton(page)).toBeVisible();
  });
});
