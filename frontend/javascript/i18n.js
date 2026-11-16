/**
 * Localization for the modern page
 *
 * Flat JSON catalogs with dotted keys in ../locales/<language>.json. The text of the page in English stays in the HTML
 * as the fallback, so a missing key or a failed request leaves the page readable.
 *   data-i18n="key"                      sets the text of the element
 *   data-i18n-attr="attribute:key,..."   sets attributes of the element
 *   LibreSpeedI18n.t(key, fallback, { name: value })   for the text written by scripts, with {name} placeholders
 *
 * The language is the saved choice (localStorage), then the first supported language of the browser, then English.
 * A <select data-language-select> is filled with the languages and changes the language of the open page.
 * "i18nchange" is fired on window after every change of language, for the texts that scripts draw.
 */
(() => {
  const DEFAULT_LANGUAGE = "en";
  const LANGUAGES = [
    { id: "en", name: "English" },
    { id: "sv", name: "Svenska" },
    { id: "pt", name: "Português" },
    { id: "es", name: "Español" }
  ];
  const STORAGE_KEY = "librespeed-language";
  // How long the page stays hidden while the first catalog loads; a late catalog is still applied
  const PENDING_MS = 3000;
  const localesURL = new URL("../locales/", document.currentScript.src);
  let language = DEFAULT_LANGUAGE;
  let messages = {};
  // The latest change of language wins, whatever the order in which the catalogs arrive
  let request = 0;
  // The English text and attributes of the page, kept before the first translation as the fallback of missing keys
  const originals = new WeakMap();

  const supported = id => LANGUAGES.some(item => item.id === id);
  const normalize = value =>
    String(value || "")
      .toLowerCase()
      .split("-")[0];

  function initialLanguage() {
    try {
      const saved = normalize(window.localStorage.getItem(STORAGE_KEY));
      if (supported(saved)) return saved;
    } catch (error) {
      // Storage can be blocked; the browser language is used then
    }
    const browser = navigator.languages || [navigator.language];
    return browser.map(normalize).find(supported) || DEFAULT_LANGUAGE;
  }

  function translate(key, fallback = key, parameters = {}) {
    return (messages[key] || fallback).replace(/\{(\w+)\}/g, (match, name) =>
      Object.prototype.hasOwnProperty.call(parameters, name) ? parameters[name] : match
    );
  }

  function original(element, name, current) {
    const saved = originals.get(element) || {};
    if (!(name in saved)) saved[name] = current;
    originals.set(element, saved);
    return saved[name];
  }

  function showLanguage() {
    document.querySelectorAll("[data-language-select]").forEach(select => {
      select.value = language;
    });
  }

  function translateDocument() {
    document.querySelectorAll("[data-i18n]").forEach(element => {
      element.textContent = translate(element.dataset.i18n, original(element, "", element.textContent.trim()));
    });
    document.querySelectorAll("[data-i18n-attr]").forEach(element => {
      element.dataset.i18nAttr.split(",").forEach(binding => {
        const [attribute, key] = binding.split(":");
        element.setAttribute(attribute, translate(key, original(element, attribute, element.getAttribute(attribute))));
      });
    });
    showLanguage();
  }

  function set(id, save) {
    if (!supported(id)) return Promise.resolve();
    const mine = ++request;
    return fetch(new URL(`${id}.json`, localesURL))
      .then(response => {
        if (!response.ok) throw new Error(`Unable to load the ${id} translations`);
        return response.json();
      })
      .then(catalog => {
        if (mine !== request) return;
        language = id;
        messages = catalog;
        if (save) {
          try {
            window.localStorage.setItem(STORAGE_KEY, id);
          } catch (error) {
            // The choice then only lasts until the page is closed
          }
        }
        document.documentElement.lang = id;
        translateDocument();
        window.dispatchEvent(new Event("i18nchange"));
      })
      .catch(error => {
        console.warn("LibreSpeed localization unavailable:", error);
        // The page stays in the language it shows, and so does the selector
        if (mine === request) showLanguage();
      })
      .finally(() => document.documentElement.removeAttribute("data-i18n-pending"));
  }

  const initial = initialLanguage();
  // The page waits for a catalog other than English, so it does not flash in English first
  if (initial !== DEFAULT_LANGUAGE) {
    document.documentElement.setAttribute("data-i18n-pending", "");
    setTimeout(() => document.documentElement.removeAttribute("data-i18n-pending"), PENDING_MS);
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-language-select]").forEach(select => {
      LANGUAGES.forEach(item => select.add(new Option(item.name, item.id)));
      select.value = language;
      select.addEventListener("change", () => set(select.value, true));
    });
    if (initial !== DEFAULT_LANGUAGE) set(initial, false);
  });

  window.LibreSpeedI18n = {
    get language() {
      return language;
    },
    set,
    t: translate
  };
})();
