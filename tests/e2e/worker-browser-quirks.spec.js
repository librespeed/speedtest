const fs = require("node:fs");
const path = require("node:path");
const { test, expect } = require("@playwright/test");

const workerSource = fs.readFileSync(path.join(__dirname, "..", "..", "speedtest_worker.js"), "utf8");

const userAgents = {
  safariMac:
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15",
  safariIos:
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1",
  chromeIos:
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/123.0.6312.52 Mobile/15E148 Safari/604.1",
  firefoxIos:
    "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/124.0 Mobile/15E148 Safari/605.1.15",
  chromeAndroid:
    "Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Mobile Safari/537.36",
  edge16:
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36 Edge/16.16299",
  playstation4: "Mozilla/5.0 (PlayStation 4 5.55) AppleWebKit/601.2 (KHTML, like Gecko)"
};

/*
  Loads speedtest_worker.js into a page that reports the given user agent, feeds it a "start"
  command and reports back the settings the worker resolved. The "_" test step is a no-op delay,
  so the worker settles its settings without performing any network I/O.
*/
async function resolveSettings(browser, userAgent, customSettings) {
  const context = await browser.newContext({ userAgent });
  try {
    const page = await context.newPage();
    await page.goto("about:blank");
    await page.addScriptTag({ content: workerSource });
    return await page.evaluate(custom => {
      window.dispatchEvent(new MessageEvent("message", { data: "start " + JSON.stringify(custom) }));
      return {
        userAgent: navigator.userAgent,
        forceIE11Workaround: settings.forceIE11Workaround,
        xhr_ul_blob_megabytes: settings.xhr_ul_blob_megabytes
      };
    }, Object.assign({ test_order: "_" }, customSettings));
  } finally {
    await context.close();
  }
}

test.describe("speedtest_worker browser quirks", () => {
  test("Safari uses the accurate upload test instead of the IE11 workaround", async ({ browser }) => {
    for (const ua of [userAgents.safariMac, userAgents.safariIos]) {
      const resolved = await resolveSettings(browser, ua);
      expect(resolved.userAgent, "context user agent should be applied").toBe(ua);
      expect(resolved.forceIE11Workaround, `${ua} should not force the IE11 workaround`).toBe(false);
      expect(resolved.xhr_ul_blob_megabytes).toBe(20);
    }
  });

  test("Safari, Chrome and Firefox on iOS resolve to the same upload path", async ({ browser }) => {
    const safari = await resolveSettings(browser, userAgents.safariIos);
    const chrome = await resolveSettings(browser, userAgents.chromeIos);
    const firefox = await resolveSettings(browser, userAgents.firefoxIos);
    expect(safari.forceIE11Workaround).toBe(chrome.forceIE11Workaround);
    expect(safari.forceIE11Workaround).toBe(firefox.forceIE11Workaround);
  });

  test("Safari in MPOT mode also uses the accurate upload test", async ({ browser }) => {
    const resolved = await resolveSettings(browser, userAgents.safariMac, { mpot: true });
    expect(resolved.forceIE11Workaround).toBe(false);
  });

  test("Chrome mobile still caps the upload blob at 4 megabytes", async ({ browser }) => {
    const resolved = await resolveSettings(browser, userAgents.chromeAndroid);
    expect(resolved.xhr_ul_blob_megabytes).toBe(4);
    expect(resolved.forceIE11Workaround).toBe(false);
  });

  test("Edge and the PlayStation 4 browser keep the IE11 workaround by default", async ({ browser }) => {
    for (const ua of [userAgents.edge16, userAgents.playstation4]) {
      const resolved = await resolveSettings(browser, ua);
      expect(resolved.forceIE11Workaround, `${ua} should keep the IE11 workaround`).toBe(true);
    }
  });

  test("an explicitly passed forceIE11Workaround is not overwritten by the quirks", async ({ browser }) => {
    for (const ua of [userAgents.edge16, userAgents.playstation4]) {
      const off = await resolveSettings(browser, ua, { forceIE11Workaround: false });
      expect(off.forceIE11Workaround, `${ua} should honour an explicit false`).toBe(false);
    }
    const on = await resolveSettings(browser, userAgents.safariMac, { forceIE11Workaround: true });
    expect(on.forceIE11Workaround, "an explicit true should still force the workaround").toBe(true);
  });
});
