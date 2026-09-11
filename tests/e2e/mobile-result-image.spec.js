const { test, expect } = require("@playwright/test");
const { baseUrls } = require("./helpers/env");

test.use({ viewport: { width: 360, height: 800 } });

test.describe("Mobile result-image sharing", () => {
  test("keeps the result image within the share dialog", async ({ page }) => {
    await page.goto(`${baseUrls.standaloneNew}/index-modern.html`);

    await page.locator("#results").evaluate(image => {
      image.src =
        "data:image/svg+xml," +
        encodeURIComponent(
          '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="480"><rect width="800" height="480" fill="black"/></svg>'
        );
    });
    await page.locator("#share").evaluate(dialog => dialog.showModal());

    const image = page.locator("#results");
    await expect(image).toBeVisible();
    await expect(image).toHaveJSProperty("complete", true);

    const dimensions = await image.evaluate(element => ({
      width: element.getBoundingClientRect().width,
      dialogWidth: element.closest("dialog").clientWidth
    }));

    expect(dimensions.width).toBeLessThanOrEqual(dimensions.dialogWidth);
  });
});
