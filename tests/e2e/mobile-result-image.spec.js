const { test, expect } = require("@playwright/test");
const { baseUrls } = require("./helpers/env");

test.use({ viewport: { width: 360, height: 800 } });

async function openResultImage(page, width, height) {
  await page.goto(`${baseUrls.standaloneNew}/index-modern.html`);

  await page.locator("#results").evaluate(
    (image, dimensions) => {
      image.src =
        "data:image/svg+xml," +
        encodeURIComponent(
          `<svg xmlns="http://www.w3.org/2000/svg" width="${dimensions.width}" height="${dimensions.height}"><rect width="100%" height="100%" fill="black"/></svg>`
        );
    },
    { width, height }
  );
  await page.locator("#share").evaluate(dialog => dialog.showModal());

  const image = page.locator("#results");
  await expect(image).toBeVisible();
  await expect(image).toHaveJSProperty("complete", true);

  return image.evaluate(element => ({
    width: element.getBoundingClientRect().width,
    height: element.getBoundingClientRect().height,
    dialogWidth: element.closest("dialog").clientWidth,
    dialogHeight: element.closest("dialog").clientHeight
  }));
}

test.describe("Mobile result-image sharing", () => {
  test("keeps the landscape result image within the share dialog width", async ({ page }) => {
    const dimensions = await openResultImage(page, 800, 480);

    expect(dimensions.width).toBeLessThanOrEqual(dimensions.dialogWidth);
  });

  test("keeps a tall result image within the share dialog height", async ({ page }) => {
    const dimensions = await openResultImage(page, 480, 1600);

    expect(dimensions.height).toBeLessThanOrEqual(dimensions.dialogHeight);
  });
});
