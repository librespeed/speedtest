(() => {
  const DEFAULT_LANGUAGE = "en";
  const SUPPORTED_LANGUAGES = new Set(["en", "sv"]);
  const script = document.currentScript;
  const localesURL = new URL("../locales/", script.src);
  let messages = {};

  function languageFromRequest() {
    const requested = new URLSearchParams(window.location.search).get("lang");
    if (requested) return requested.toLowerCase().split("-")[0];
    return (navigator.languages || [navigator.language || DEFAULT_LANGUAGE])
      .map((language) => language.toLowerCase().split("-")[0])
      .find((language) => SUPPORTED_LANGUAGES.has(language)) || DEFAULT_LANGUAGE;
  }

  function interpolate(value, parameters) {
    return value.replace(/\{(\w+)\}/g, (match, key) =>
      Object.prototype.hasOwnProperty.call(parameters, key) ? parameters[key] : match
    );
  }

  function translate(key, fallback = key, parameters = {}) {
    return interpolate(messages[key] || fallback, parameters);
  }

  function translateDocument() {
    document.querySelectorAll("[data-i18n]").forEach((element) => {
      const key = element.dataset.i18n;
      element.textContent = translate(key, element.textContent.trim());
    });
    document.querySelectorAll("[data-i18n-attr]").forEach((element) => {
      element.dataset.i18nAttr.split(",").forEach((binding) => {
        const [attribute, key] = binding.split(":");
        if (attribute && key) element.setAttribute(attribute, translate(key, element.getAttribute(attribute) || key));
      });
    });
  }

  const language = languageFromRequest();
  const ready = fetch(new URL(`${language}.json`, localesURL))
    .then((response) => {
      if (!response.ok) throw new Error(`Unable to load ${language} translations`);
      return response.json();
    })
    .then((catalog) => {
      messages = catalog;
      document.documentElement.lang = language;
      translateDocument();
    })
    .catch((error) => {
      console.warn("LibreSpeed localization unavailable:", error);
      document.documentElement.lang = DEFAULT_LANGUAGE;
    });

  window.LibreSpeedI18n = { language, ready, t: translate };
})();
