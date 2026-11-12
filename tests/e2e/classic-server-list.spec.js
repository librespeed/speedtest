const { test, expect } = require("@playwright/test");
const { baseUrls } = require("./helpers/env");

test.describe("Classic design with mounted server list", () => {
  test("frontend classic page loads the mounted server-list.json", async ({ page, request }) => {
    const html = await (await request.get(`${baseUrls.frontend}/index-classic.html`)).text();
    expect(html).toMatch(/var SPEEDTEST_SERVERS = "server-list\.json";/);

    const pageErrors = [];
    page.on("pageerror", error => pageErrors.push(error.message));

    // This fixture hostname only resolves inside Docker. Keep server selection
    // deterministic without mocking the mounted server-list.json response.
    await page.route("http://backend-testpoint:8080/empty.php*", route =>
      route.fulfill({ status: 200, headers: { "Access-Control-Allow-Origin": "*" }, body: "" })
    );

    const listResponse = page.waitForResponse(response => response.url().endsWith("/server-list.json"));
    await page.goto(`${baseUrls.frontend}/index-classic.html`);

    const response = await listResponse;
    expect(response.ok()).toBeTruthy();

    // Verify the fetched config was applied to the classic server selector.
    await expect(page.locator("#server option")).toHaveText(["Backend testpoint"], { timeout: 10_000 });
    await expect(page.locator("#startStopBtn")).toBeVisible();
    expect(pageErrors).toEqual([]);
  });
});
