const { test, expect } = require("@playwright/test");
const { baseUrls } = require("./helpers/env");

test.describe("Localization", () => {
  test("loads Swedish only for an explicit supported language", async ({ page }) => {
    await page.goto(`${baseUrls.standalone}/index-modern.html?lang=sv`);

    await expect(page.locator("html")).toHaveAttribute("lang", "sv");
    await expect(page.locator("main > h1")).toHaveText("Fritt hastighetstest med öppen källkod.");
    await expect(page.locator('a[href*="stability.html"]')).toHaveAttribute("href", /lang=sv/);
  });

  test("falls back to the source document for an unsupported language", async ({ page }) => {
    await page.goto(`${baseUrls.standaloneNew}/index-modern.html?lang=not-a-language`);

    await expect(page.locator("html")).toHaveAttribute("lang", "en");
    await expect(page).toHaveTitle('Grüße "Tempo" \'Österreich\' - Free and Open Source Speedtest');
    await expect(page.locator("main > p.tagline")).toHaveText('No "Flash", <No Java>, No Websockets & No Bullsh*t');
  });
});
