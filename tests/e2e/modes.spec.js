const { test, expect } = require('@playwright/test');
const { baseUrls } = require('./helpers/env');
const { modernStartButton } = require('./helpers/ui');

test.describe('Runtime mode smoke coverage', () => {
  test('standalone exposes UI and local backend endpoints', async ({ page, request }) => {
    const root = await request.get(`${baseUrls.standalone}/`);
    expect(root.ok()).toBeTruthy();

    const index = await request.get(`${baseUrls.standalone}/index.html`);
    expect(index.ok()).toBeTruthy();
    await expect(await index.text()).toContain('design-switch.js');

    for (const endpoint of ['/backend/empty.php', '/backend/garbage.php', '/backend/getIP.php']) {
      const response = await request.get(`${baseUrls.standalone}${endpoint}`);
      expect(response.ok()).toBeTruthy();
    }

    await page.goto(`${baseUrls.standalone}/index-modern.html`);
    await expect(modernStartButton(page)).toBeVisible();
  });

  test('backend exposes only local backend contract endpoints', async ({ request }) => {
    for (const endpoint of ['/empty.php', '/garbage.php', '/getIP.php']) {
      const response = await request.get(`${baseUrls.backend}${endpoint}`);
      expect(response.ok()).toBeTruthy();
    }
  });

  test('frontend serves UI and server list without local backend contract', async ({ page, request }) => {
    const serverList = await request.get(`${baseUrls.frontend}/server-list.json`);
    expect(serverList.ok()).toBeTruthy();
    await expect(await serverList.text()).toContain('Backend testpoint');

    const localBackendEndpoint = await request.get(`${baseUrls.frontend}/backend/empty.php`);
    expect(localBackendEndpoint.status()).toBe(404);

    await page.goto(`${baseUrls.frontend}/index-modern.html`);
    await expect(modernStartButton(page)).toBeVisible();
    await expect(page.locator('#selected-server')).not.toHaveText(/searching nearest server/i);
  });

  test('dual combines frontend and local backend availability', async ({ page, request }) => {
    const serverList = await request.get(`${baseUrls.dual}/server-list.json`);
    expect(serverList.ok()).toBeTruthy();
    await expect(await serverList.text()).toContain('Local dual backend');

    for (const endpoint of ['/backend/empty.php', '/backend/garbage.php', '/backend/getIP.php']) {
      const response = await request.get(`${baseUrls.dual}${endpoint}`);
      expect(response.ok()).toBeTruthy();
    }

    await page.goto(`${baseUrls.dual}/index-modern.html`);
    await expect(modernStartButton(page)).toBeVisible();
  });
});
