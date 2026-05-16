# LibreSpeed frontend

This directory contains the modern LibreSpeed UI assets.

## Deployment

For installation and deployment, follow the top-level [README.md](../README.md)
and [DESIGN_SWITCH.md](../DESIGN_SWITCH.md). In non-Docker deployments, the
contents of this directory must be copied so `styling/`, `javascript/`,
`images/`, and `fonts/` sit next to the root HTML files.

## Configuration

- `server-list.json` contains the default server list used by the modern UI.
- `settings.json` overrides selected `speedtest_worker.js` settings.
- `index.html` and `index-modern.html` show how the frontend is wired up.

## Notes

- The modern frontend expects modern browser features and does not support old
  browsers.
- This directory does not contain the backend or results-sharing files.
