const { test, expect } = require("@playwright/test");
const { modernStartButton } = require("./helpers/ui");

test.use({ viewport: { width: 390, height: 560 } });

test.describe("Mobile gauge visibility", () => {
  test("keeps the active gauge visible after pressing Start", async ({ page }) => {
    await page.route("**/speedtest.js", async (route) => {
      await route.fulfill({
        contentType: "text/javascript; charset=utf-8",
        body: `
          class Speedtest {
            constructor() {
              this.selectedServer = null;
              this.onupdate = null;
              this.onend = null;
            }

            start() {
              this.onupdate?.({
                dlProgress: 0.1,
                ulProgress: 0,
                dlStatus: "123.45",
                ulStatus: "0",
                pingStatus: "10.5",
                jitterStatus: "0.9",
                testState: 1
              });
            }

            abort() {
              this.onend?.(true);
            }

            setParameter() {}
            addTestPoints() {}
            selectServer(callback) {
              callback(this.selectedServer);
            }
            setSelectedServer(server) {
              this.selectedServer = server;
            }
            getSelectedServer() {
              return this.selectedServer;
            }
          }

          window.Speedtest = Speedtest;
        `,
      });
    });

    await page.route("**/settings.json", async (route) => {
      await route.fulfill({
        contentType: "application/json; charset=utf-8",
        body: JSON.stringify({ telemetry_level: "off" }),
      });
    });

    await page.route("**/server-list.json", async (route) => {
      await route.fulfill({
        contentType: "application/json; charset=utf-8",
        body: JSON.stringify([
          {
            name: "Test Server",
            server: "http://127.0.0.1:18184/backend/",
          },
        ]),
      });
    });

    await page.goto("http://127.0.0.1:18184/index-modern.html");

    const startButton = modernStartButton(page);
    const downloadGauge = page.locator("#download-gauge");

    await expect(startButton).toHaveText("Let's start", { timeout: 10_000 });

    await page.evaluate(() => window.scrollTo(0, 0));

    const gaugeStartsBelowFold = await downloadGauge.evaluate((element) => {
      const { top, bottom } = element.getBoundingClientRect();
      return bottom > window.innerHeight || top < 0;
    });
    expect(gaugeStartsBelowFold).toBe(true);

    await startButton.click();
    await expect(startButton).toHaveText("Abort");

    await expect
      .poll(() =>
        downloadGauge.evaluate((element) => {
          const { top, bottom } = element.getBoundingClientRect();
          return top >= 0 && bottom <= window.innerHeight;
        })
      )
      .toBe(true);
  });
});
