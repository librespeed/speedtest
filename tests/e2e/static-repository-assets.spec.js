const { test, expect } = require("@playwright/test");

const staticRepositoryUrl = "http://127.0.0.1:18184";

const expectedAssets = [
  "/index-modern.html",
  "/settings.json",
  "/server-list.json",
  "/frontend/styling/index.css",
  "/frontend/javascript/index.js",
  "/frontend/images/logo.svg",
  "/frontend/images/favicon.svg",
  "/frontend/images/close-button.svg",
  "/frontend/images/chevron.svg",
  "/frontend/fonts/Inter-latin.woff2",
  "/frontend/fonts/Inter-latin-ext.woff2",
  "/frontend/images/background.jpeg",
  "/speedtest.js",
  "/speedtest_worker.js",
  "/design-switch.js",
  "/config.json",
  "/images/icon-192.png"
];

test.describe("Unmodified repository static assets", () => {
  test("serves every modern frontend dependency and returns 404 for a missing path", async ({ request }) => {
    for (const path of expectedAssets) {
      const response = await request.get(`${staticRepositoryUrl}${path}`);
      expect(response.ok(), `${path} should return 2xx`).toBeTruthy();
    }

    const missing = await request.get(`${staticRepositoryUrl}/does-not-exist`);
    expect(missing.status()).toBe(404);
  });
});
