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
          '<svg xmlns="http://www.w3.org/2000/svg" width="480" height="1600"><rect width="480" height="1600" fill="black"/></svg>'
        );
    });
    await page.locator("#share").evaluate(dialog => dialog.showModal());

    const image = page.locator("#results");
    await expect(image).toBeVisible();
    await expect(image).toHaveJSProperty("complete", true);

    const dimensions = await image.evaluate(element => ({
      width: element.getBoundingClientRect().width,
      height: element.getBoundingClientRect().height,
      dialogWidth: element.closest("dialog").clientWidth,
      dialogHeight: element.closest("dialog").clientHeight
    }));

    expect(dimensions.width).toBeLessThanOrEqual(dimensions.dialogWidth);
    expect(dimensions.height).toBeLessThanOrEqual(dimensions.dialogHeight);
  });
});
