const fs = require("node:fs");
const path = require("node:path");
const { test, expect } = require("@playwright/test");
const { baseUrls } = require("./helpers/env");

const staticRepositoryUrl = "http://127.0.0.1:18184";
const pageUrl = `${staticRepositoryUrl}/index-modern.html`;
const languages = ["en", "sv", "pt", "es"];
const root = path.resolve(__dirname, "../..");

const singleServerList = [
  {
    name: "Test server",
    server: "//127.0.0.1:18184/",
    dlURL: "garbage.php",
    ulURL: "empty.php",
    pingURL: "empty.php",
    getIpURL: "getIP.php",
    sponsorName: "Test sponsor",
    sponsorURL: "https://example.com/"
  }
];

// A single server is shown without reachability checks, so the page reaches the READY state offline
async function openPage(page) {
  await page.route("**/server-list.json", route => route.fulfill({ json: singleServerList }));
  await page.goto(pageUrl);
  await expect(page.locator("#start-button")).not.toHaveText("Loading...");
}

test.describe("Localization of the modern page", () => {
  test.describe("English browser", () => {
    test.use({ locale: "en-US" });

    test("shows the English text of the page and lists the four languages", async ({ page }) => {
      await openPage(page);
      await expect(page.locator("main > h1")).toHaveText("Free and Open Source Speedtest.");
      await expect(page.locator("#start-button")).toHaveText("Let's start");
      await expect(page.locator("html")).toHaveAttribute("lang", "en");
      await expect(page.locator("[data-language-select] option")).toHaveText([
        "English",
        "Svenska",
        "Português",
        "Español"
      ]);
    });

    test("changes language from the selector, keeps it after a reload and goes back to English", async ({ page }) => {
      await openPage(page);
      const select = page.locator("[data-language-select]");

      await select.selectOption("pt");
      await expect(page.locator("main > h1")).toHaveText("Teste de velocidade livre e de código aberto.");
      await expect(page.locator("html")).toHaveAttribute("lang", "pt");
      await expect(page).toHaveTitle(/código aberto/);
      await expect(page.locator("#start-button")).toHaveText("Vamos começar");
      await expect(page.locator("#sponsor")).toHaveText("Patrocinador: Test sponsor");
      await expect(page.locator("#sponsor a")).toHaveAttribute("href", "https://example.com/");
      await expect(page.locator("#selected-server")).toHaveText("Test server");

      await page.reload();
      await expect(page.locator("main > h1")).toHaveText("Teste de velocidade livre e de código aberto.");
      await expect(select).toHaveValue("pt");

      await select.selectOption("en");
      await expect(page.locator("main > h1")).toHaveText("Free and Open Source Speedtest.");
      await expect(page.locator("#start-button")).toHaveText("Let's start");
      await expect(page.locator("#sponsor")).toHaveText("Sponsor: Test sponsor");
    });

    test("translates the privacy policy, the alt texts and the label of the selector", async ({ page }) => {
      await openPage(page);
      await page.locator("[data-language-select]").selectOption("es");
      await expect(page.locator("#privacy h1")).toHaveText("Política de Privacidad");
      await expect(page.locator("#close-privacy")).toHaveText("Cerrar");
      await expect(page.locator("#privacy a[href^='mailto:']")).toHaveText("TO BE FILLED BY DEVELOPER");
      await expect(page.locator("#share .close-dialog img")).toHaveAttribute("alt", "Cerrar");
      await expect(page.locator("[data-language-select]")).toHaveAttribute("aria-label", "Idioma");
    });

    test("keeps the page in English when the translation cannot be loaded", async ({ page }) => {
      await page.route("**/frontend/locales/sv.json", route => route.abort());
      await openPage(page);
      await page.locator("[data-language-select]").selectOption("sv");
      await expect(page.locator("main > h1")).toHaveText("Free and Open Source Speedtest.");
      await expect(page.locator("#start-button")).toHaveText("Let's start");
    });
  });

  test.describe("English browser, catalogs that misbehave", () => {
    test.use({ locale: "en-US" });

    test("the latest choice wins when the catalogs arrive out of order", async ({ page }) => {
      await page.route("**/frontend/locales/sv.json", async route => {
        await new Promise(resolve => setTimeout(resolve, 1000));
        await route.continue();
      });
      await openPage(page);
      const select = page.locator("[data-language-select]");
      const swedishArrived = page.waitForResponse("**/frontend/locales/sv.json");
      await select.selectOption("sv");
      await select.selectOption("pt");
      await expect(page.locator("main > h1")).toHaveText("Teste de velocidade livre e de código aberto.");
      await swedishArrived;
      await page.waitForTimeout(200);
      await expect(page.locator("main > h1")).toHaveText("Teste de velocidade livre e de código aberto.");
      await expect(select).toHaveValue("pt");
      expect(await page.evaluate(() => localStorage.getItem("librespeed-language"))).toBe("pt");
    });

    test("the selector goes back to the language shown when a change fails", async ({ page }) => {
      await openPage(page);
      const select = page.locator("[data-language-select]");
      await select.selectOption("pt");
      await expect(page.locator("main > h1")).toHaveText("Teste de velocidade livre e de código aberto.");

      await page.route("**/frontend/locales/en.json", route => route.abort());
      await select.selectOption("en");
      await expect(select).toHaveValue("pt");
      await expect(page.locator("main > h1")).toHaveText("Teste de velocidade livre e de código aberto.");
    });

    test("a key missing from a catalog falls back to the English text, not to the previous language", async ({
      page
    }) => {
      await page.route("**/frontend/locales/sv.json", route =>
        route.fulfill({ json: { "brand.title": "Svensk rubrik" } })
      );
      await openPage(page);
      const select = page.locator("[data-language-select]");
      await select.selectOption("pt");
      await expect(page.locator("main > p.tagline")).toContainText("Sem Flash");
      await select.selectOption("sv");
      await expect(page.locator("main > h1")).toHaveText("Svensk rubrik");
      await expect(page.locator("main > p.tagline")).toHaveText("No Flash, No Java, No Websockets, No Bullsh*t");
      await expect(page.locator("#start-button")).toHaveText("Let's start");
    });
  });

  test.describe("Swedish browser", () => {
    test.use({ locale: "sv-SE" });

    test("uses the language of the browser, without a flash of English", async ({ page }) => {
      await openPage(page);
      await expect(page.locator("main > h1")).toHaveText("Gratis hastighetstest med öppen källkod.");
      await expect(page.locator("html")).toHaveAttribute("lang", "sv");
      await expect(page.locator("html")).not.toHaveAttribute("data-i18n-pending", "");
      await expect(page.locator("#start-button")).toHaveText("Låt oss börja");
      await expect(page.locator("[data-language-select]")).toHaveValue("sv");
    });

    test("shows the page in English when the catalog stalls, and translates it when the catalog arrives", async ({
      page
    }) => {
      await page.route("**/frontend/locales/sv.json", async route => {
        await new Promise(resolve => setTimeout(resolve, 4500));
        await route.continue();
      });
      await openPage(page);
      await expect(page.locator("main > h1")).toBeVisible();
      await expect(page.locator("main > h1")).toHaveText("Free and Open Source Speedtest.");
      await expect(page.locator("main > h1")).toHaveText("Gratis hastighetstest med öppen källkod.");
    });

    test("translates the alert when no server can be reached", async ({ page }) => {
      const dialogs = [];
      page.on("dialog", dialog => {
        dialogs.push(dialog.message());
        dialog.dismiss();
      });
      // Two servers that cannot be reached
      await page.route("**/server-list.json", route =>
        route.fulfill({ json: [singleServerList[0], { ...singleServerList[0], name: "Other server" }] })
      );
      // The servers fail only once the Swedish catalog is in, as they would after the seconds a real ping takes
      await page.route("**/empty.php*", async route => {
        await page.waitForFunction(() => document.documentElement.lang === "sv");
        await route.abort();
      });
      await page.goto(pageUrl);
      await expect.poll(() => dialogs.length).toBeGreaterThan(0);
      expect(dialogs[0]).toContain("Det går inte att nå någon av hastighetstestets servrar");
    });
  });

  test.describe("Browser in a language without a catalog", () => {
    test.use({ locale: "de-DE" });

    test("falls back to English", async ({ page }) => {
      await openPage(page);
      await expect(page.locator("main > h1")).toHaveText("Free and Open Source Speedtest.");
      await expect(page.locator("html")).toHaveAttribute("lang", "en");
      await expect(page.locator("[data-language-select]")).toHaveValue("en");
    });
  });

  test.describe("Docker image with the default TITLE and TAGLINE", () => {
    test.use({ locale: "pt-BR" });

    test("translates the title, the heading and the tagline", async ({ page }) => {
      await page.goto(`${baseUrls.standalone}/index-modern.html`);
      await expect(page.locator("main > h1")).toHaveText("Teste de velocidade livre e de código aberto.");
      await expect(page.locator("main > p.tagline")).toHaveText("Sem Flash, sem Java, sem Websockets, sem enrolação");
      await expect(page).toHaveTitle(/código aberto/);
    });
  });

  test("every catalog has the keys of the English one, and the pages only use keys that exist", () => {
    const catalog = id => JSON.parse(fs.readFileSync(path.join(root, `frontend/locales/${id}.json`), "utf8"));
    const english = Object.keys(catalog("en"));

    for (const id of languages) {
      expect(Object.keys(catalog(id)), `${id}.json`).toEqual(english);
    }

    const sources = ["index-modern.html", "frontend/index.html", "frontend/javascript/index.js"]
      .map(file => fs.readFileSync(path.join(root, file), "utf8"))
      .join("\n");
    const used = [
      ...[...sources.matchAll(/data-i18n="([^"]+)"/g)].map(match => match[1]),
      ...[...sources.matchAll(/data-i18n-attr="([^"]+)"/g)].flatMap(match =>
        match[1].split(",").map(binding => binding.split(":")[1])
      ),
      ...[...sources.matchAll(/\bt\(\s*"([\w.-]+)"/g)].map(match => match[1])
    ];
    expect(used.length).toBeGreaterThan(40);
    for (const key of used) {
      expect(english, key).toContain(key);
    }
  });
});
