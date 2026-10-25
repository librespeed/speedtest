const fs = require("node:fs");
const path = require("node:path");
const { test, expect } = require("@playwright/test");
const { baseUrls } = require("./helpers/env");

const staticRepositoryUrl = "http://127.0.0.1:18184";
const root = path.resolve(__dirname, "../..");
const hostTheme = fs.readFileSync(path.join(__dirname, "fixtures/theme-host.css"), "utf8");

const tagline = page => page.locator("main > p.tagline");
const color = locator => locator.evaluate(element => getComputedStyle(element).color);
const rootStyle = page =>
  page.evaluate(() => {
    const style = getComputedStyle(document.documentElement);
    return { image: style.backgroundImage, color: style.backgroundColor };
  });

test.describe("Host theme of the modern design", () => {
  test("ships an empty theme.css that leaves the default look alone", async ({ page, request }) => {
    const response = await request.get(`${staticRepositoryUrl}/frontend/styling/theme.css`);
    expect(response.ok()).toBeTruthy();
    expect(await response.text()).not.toContain("{");

    await page.goto(`${staticRepositoryUrl}/index-modern.html`);
    expect(await color(tagline(page))).toBe("rgb(92, 249, 253)");
    expect((await rootStyle(page)).image).toContain("background.jpeg");
  });

  test("a theme.css of the host changes the colors and the background", async ({ page }) => {
    await page.route("**/frontend/styling/theme.css", route =>
      route.fulfill({ body: hostTheme, contentType: "text/css" })
    );
    await page.goto(`${staticRepositoryUrl}/index-modern.html`);
    expect(await color(tagline(page))).toBe("rgb(255, 138, 0)");
    expect(await rootStyle(page)).toEqual({ image: "none", color: "rgb(16, 24, 32)" });
  });

  test("a relative url in theme.css is relative to the theme.css", async ({ page }) => {
    await page.route("**/frontend/styling/theme.css", route =>
      route.fulfill({
        body: 'html { background-image: url("../images/background-original.jpeg"); }',
        contentType: "text/css"
      })
    );
    const requested = page.waitForRequest("**/frontend/images/background-original.jpeg");
    await page.goto(`${staticRepositoryUrl}/index-modern.html`);
    await requested;
  });

  test("both modern pages load theme.css after index.css", () => {
    const link = (file, folder) => {
      const html = fs.readFileSync(path.join(root, file), "utf8");
      return [html.indexOf(`${folder}index.css`), html.indexOf(`${folder}theme.css`)];
    };
    for (const [file, folder] of [
      ["index-modern.html", "frontend/styling/"],
      ["frontend/index.html", "styling/"]
    ]) {
      const [index, theme] = link(file, folder);
      expect(index, file).toBeGreaterThan(-1);
      expect(theme, file).toBeGreaterThan(index);
    }
  });

  test("a theme.css mounted in the Docker image is the one that is served", async ({ page }) => {
    await page.goto(`${baseUrls.standaloneApostrophe}/index-modern.html`);
    expect(await color(tagline(page))).toBe("rgb(255, 138, 0)");
    expect(await rootStyle(page)).toEqual({ image: "none", color: "rgb(16, 24, 32)" });
  });
});
