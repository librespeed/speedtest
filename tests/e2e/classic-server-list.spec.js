const { test, expect } = require('@playwright/test');
const { baseUrls } = require('./helpers/env');

test.describe('Classic design with mounted server list', () => {
  test('frontend classic page loads the mounted server-list.json', async ({ page, request }) => {
    const html = await (await request.get(`${baseUrls.frontend}/index-classic.html`)).text();
    expect(html).toMatch(/var SPEEDTEST_SERVERS = "server-list\.json";/);

    const listRequest = page.waitForRequest((req) => req.url().endsWith('/server-list.json'));
    await page.goto(`${baseUrls.frontend}/index-classic.html`);
    await listRequest;

    // Not treated as a standalone install: the server selector is not hidden.
    await expect(page.locator('#serverArea')).not.toHaveCSS('display', 'none');
  });
});
