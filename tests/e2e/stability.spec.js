const fs = require("node:fs");
const { test, expect } = require("@playwright/test");
const { baseUrls } = require("./helpers/env");
const { stabilityStartButton } = require("./helpers/ui");

async function setShortDuration(page) {
  await page.evaluate(() => {
    const select = document.querySelector("#durationSelect");
    const option = document.createElement("option");
    option.value = "1";
    option.textContent = "1 Second";
    select.insertBefore(option, select.firstChild);
    select.value = "1";
    select.dispatchEvent(new Event("change", { bubbles: true }));
  });
}

async function setAlertThreshold(page, value) {
  await page.evaluate(threshold => {
    const input = document.querySelector("#alertThreshold");
    input.value = String(threshold);
    input.dispatchEvent(new Event("input", { bubbles: true }));
  }, value);
}

async function waitForSamples(page) {
  await expect.poll(() => page.evaluate(() => window.allPingData.length), { timeout: 10_000 }).toBeGreaterThan(0);
}

async function waitForLocalServer(page, serverName) {
  await expect(page.locator("#serverArea")).toBeVisible({ timeout: 10_000 });
  await expect(page.locator("#server option")).toContainText(serverName, { timeout: 10_000 });
}

test.describe("Stability test", () => {
  test("keeps the local start control disabled until server discovery completes", async ({ page }) => {
    let releaseServerProbe;
    const serverProbe = new Promise(resolve => {
      releaseServerProbe = resolve;
    });

    await page.route(/\/backend\/empty\.php\?cors=true/, async route => {
      await serverProbe;
      await route.fulfill({ status: 200, body: "" });
    });

    await page.goto(`${baseUrls.standalone}/stability.html`);

    await expect(stabilityStartButton(page)).toHaveClass(/disabled/);
    await expect(stabilityStartButton(page)).toHaveClass(/finding/);
    await expect(stabilityStartButton(page)).toHaveAttribute("aria-disabled", "true");
    await expect(stabilityStartButton(page)).toHaveAttribute("title", "Finding best server...");
    await expect(page.locator("#server")).toBeDisabled();

    releaseServerProbe();

    await expect(page.locator("#server option")).toContainText("local", { timeout: 10_000 });
    await expect(stabilityStartButton(page)).not.toHaveClass(/disabled/);
    await expect(stabilityStartButton(page)).toHaveAttribute("aria-disabled", "false");
    await expect(stabilityStartButton(page)).toHaveAttribute("title", "");
    await expect(page.locator("#server")).toBeEnabled();
  });

  test("runs a short local measurement and exports CSV data", async ({ page }) => {
    await page.goto(`${baseUrls.standalone}/stability.html`);

    await expect(page).toHaveTitle("LibreSpeed - Stability Test");
    await waitForLocalServer(page, "local");

    await setShortDuration(page);
    await stabilityStartButton(page).click();

    await expect(stabilityStartButton(page)).toHaveClass(/running/);
    await expect(page.locator("#durationSelect")).toBeDisabled();
    await expect(page.locator("#targetSelect")).toBeDisabled();
    await expect(page.locator("#server")).toBeDisabled();

    await waitForSamples(page);
    await expect(page.locator("#statAvg")).not.toHaveText("", { timeout: 5_000 });
    await expect(page.locator("#rating")).not.toHaveText("--", { timeout: 5_000 });

    await expect(stabilityStartButton(page)).not.toHaveClass(/running/, { timeout: 10_000 });
    await expect(page.locator("#durationSelect")).toBeEnabled();

    const [download] = await Promise.all([page.waitForEvent("download"), page.locator("#downloadCsvBtn").click()]);
    expect(download.suggestedFilename()).toMatch(/^stability_test_.*\.csv$/);

    const csvPath = await download.path();
    const csv = fs.readFileSync(csvPath, "utf8");
    expect(csv).toContain("elapsed_s,ping_ms,failed\n");
    expect(csv.trim().split("\n").length).toBeGreaterThan(1);
  });

  test("supports threshold display, abort, and reset controls", async ({ page }) => {
    await page.goto(`${baseUrls.standalone}/stability.html`);
    await waitForLocalServer(page, "local");

    await setAlertThreshold(page, 40);
    await expect(page.locator("#thresholdValue")).toHaveText("40 ms");

    await stabilityStartButton(page).click();
    await expect(stabilityStartButton(page)).toHaveClass(/running/);
    await waitForSamples(page);

    await stabilityStartButton(page).click();
    await expect(stabilityStartButton(page)).not.toHaveClass(/running/);
    await expect(page.locator("#durationSelect")).toBeEnabled();
    await expect(page.locator("#targetSelect")).toBeEnabled();

    await page.waitForTimeout(700);
    await page.locator("#resetBtn").click();

    await expect(page.locator("#rating")).toHaveText("--");
    await expect(page.locator("#statAvg")).toHaveText("");
    await expect.poll(() => page.evaluate(() => window.allPingData.length)).toBe(0);
    await expect.poll(() => page.evaluate(() => window.latestData)).toBeNull();
  });

  test("loads the configured dual-mode server list", async ({ page }) => {
    await page.goto(`${baseUrls.dual}/stability.html`);

    await expect(page.locator("#serverArea")).toBeVisible({ timeout: 10_000 });
    await expect(page.locator("#server option")).toContainText("Local dual backend", { timeout: 10_000 });
  });
});
