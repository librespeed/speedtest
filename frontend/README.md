# LibreSpeed frontend

This directory contains the modern LibreSpeed UI assets.

## Deployment

For installation and deployment, follow the top-level [README.md](../README.md)
and [DESIGN_SWITCH.md](../DESIGN_SWITCH.md). This directory is copied as a
whole: `index-modern.html` loads its assets from `frontend/`, so the layout
here is the layout that gets served.

## Configuration

The two configuration files live at the top level rather than here, because the
page fetches them relative to itself:

- `../server-list.json` contains the default server list used by the modern UI.
- `../settings.json` overrides selected `speedtest_worker.js` settings.
- `index.html` and `../index-modern.html` show how the frontend is wired up.

## Localization

The modern page ships with English, Swedish, Portuguese and Spanish. The language is the saved choice, then the first
supported language of the browser, then English; the selector in the footer changes it and saves the choice in the
browser.

- `javascript/i18n.js` loads `locales/<language>.json` (flat JSON, dotted keys) and fills the elements marked with
  `data-i18n="key"` (text) or `data-i18n-attr="attribute:key"` (attributes). The English text stays in the HTML as the
  fallback.
- Texts written by `javascript/index.js` go through `LibreSpeedI18n.t(key, fallback, { name: value })`.
- To add a language, copy `locales/en.json` to `locales/<id>.json`, translate it and add `{ id, name }` to `LANGUAGES` in
  `javascript/i18n.js`. `tests/e2e/i18n.spec.js` checks that every catalog has the same keys as the English one.
- In the Docker image, a `TITLE` or `TAGLINE` other than the default replaces the translated title, heading and tagline with that text; the defaults keep them translated.

## Notes

- The modern frontend expects modern browser features and does not support old
  browsers.
- This directory does not contain the backend or results-sharing files.
