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

  test('classic UI displays ISP and approximate IP-derived location metadata', async ({ page }) => {
    await page.addInitScript(() => {
      window.__workers = [];
      window.Worker = class FakeWorker {
        constructor(url) {
          this.url = url;
          this.onmessage = null;
          window.__workers.push(this);
        }
        postMessage() {}
        terminate() {}
        emit(data) {
          this.onmessage({ data: JSON.stringify(data) });
        }
      };
    });

    await page.goto('http://127.0.0.1:18184/index-classic.html');
    await expect(page.locator('#testWrapper')).toHaveClass(/visible/, { timeout: 10_000 });
    await classicStartButton(page).click();

    await page.evaluate(() => {
      window.__workers[0].emit({
        testState: 3,
        clientIp: '128.94.2.134 - Example Networks, US',
        rawIspInfo: {
          org: 'AS00001 Example Networks',
          country: 'US'
        },
        location: {
          city: 'Indianapolis',
          region: 'Indiana',
          country: 'US',
          countryCode: 'US',
          latitude: 39.7684,
          longitude: -86.158,
          approximate: true,
          source: 'ipinfo_api'
        }
      });
    });

    await expect(page.locator('#ip')).toContainText('128.94.2.134');
    await expect(page.locator('#ipIsp')).toHaveText('ISP: Example Networks');
    await expect(page.locator('#ipLocation')).toHaveText(
      'Approximate IP-derived location: Indianapolis, Indiana, US'
    );
    await expect(page.locator('#ipCoordinates')).toHaveText(
      'Lat/Long: 39.7684, -86.158'
    );
  });

  test('modern UI displays ISP and approximate IP-derived location metadata', async ({ page }) => {
    await page.addInitScript(() => {
      window.__workers = [];
      window.Worker = class FakeWorker {
        constructor(url) {
          this.url = url;
          this.onmessage = null;
          window.__workers.push(this);
        }
        postMessage() {}
        terminate() {}
        emit(data) {
          this.onmessage({ data: JSON.stringify(data) });
        }
      };
    });

    await page.goto('http://127.0.0.1:18184/index-modern.html');
    await expect(page.locator('#start-button')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#start-button')).not.toHaveClass(/disabled/, { timeout: 10_000 });
    await expect(page.locator('#start-button')).toHaveText('Let\'s start', { timeout: 10_000 });
    await page.locator('#start-button').click();

    await page.evaluate(() => {
      window.__workers[0].emit({
        testState: 3,
        clientIp: '128.94.2.134 - Example Networks, US',
        rawIspInfo: {
          org: 'AS00001 Example Networks',
          country: 'US'
        },
        location: {
          city: 'Indianapolis',
          region: 'Indiana',
          country: 'US',
          countryCode: 'US',
          latitude: 39.7684,
          longitude: -86.158,
          approximate: true,
          source: 'ipinfo_api'
        }
      });
    });

    await expect(page.locator('#privacy-warning')).toContainText(
      '128.94.2.134 - Example Networks, US'
    );
    await expect(page.locator('#privacy-warning')).toContainText(
      'ISP: Example Networks'
    );
    await expect(page.locator('#privacy-warning')).toContainText(
      'Approximate IP-derived location: Indianapolis, Indiana, US'
    );
    await expect(page.locator('#privacy-warning')).toContainText(
      'Lat/Long: 39.7684, -86.158'
    );
  });
});
