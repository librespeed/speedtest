# Design Feature Switch

LibreSpeed now supports switching between the classic design and the new modern design.

## Default Behavior

By default, LibreSpeed uses the **classic design** (located in `index-classic.html`).

## How It Works

The implementation uses three HTML files at the root level:
- **`index.html`** - Entry point with design switcher (lightweight redirect page)
- **`index-classic.html`** - Classic design (default)
- **`index-modern.html`** - Modern design with assets in `frontend/` subdirectory

Both designs are at the same level, so relative paths to resources like `results/` work correctly for both.

## Browser Compatibility

The feature switch uses modern JavaScript features (URLSearchParams, fetch API). It is compatible with all modern browsers. The new design itself requires modern browser features and has no backwards compatibility with older browsers (see `frontend/README.md`).

## Enabling the New Design

There are two ways to enable the new design:

### Method 1: Configuration File (Persistent)

Edit the `config.json` file in the root directory and set `useNewDesign` to `true`:

```json
{
  "useNewDesign": true
}
```

This will make the new design the default for all users visiting your site.

### Method 2: URL Parameter (Temporary Override)

You can override the configuration by adding a URL parameter:

- To use the new design: `http://yoursite.com/?design=new`
- To use the old design: `http://yoursite.com/?design=old`

URL parameters take precedence over the configuration file, making them useful for testing or allowing users to choose their preferred design.

## Design Locations

- **Entry Point**: Root `index.html` file (lightweight redirect page)
- **Old Design**: `index-classic.html` at root
- **New Design**: `index-modern.html` at root (references assets in `frontend/` directory)
- **Assets**: Frontend assets (CSS, JS, images, fonts) remain in `frontend/` subdirectory

Both designs are at the same directory level, ensuring that relative paths to shared resources like `backend/` and `results/` work correctly for both.

## Technical Details

The feature switch is implemented in `design-switch.js`, which is loaded by the root `index.html`. It checks:

1. First, URL parameters (`?design=new` or `?design=old`)
2. Then, the `config.json` configuration file
3. Redirects to either `index-classic.html` or `index-modern.html`

Both design HTML files are at the root level, eliminating path issues. The modern design references assets from the `frontend/` subdirectory (e.g., `frontend/styling/index.css`), while both designs can access shared resources like `backend/` and `results/` using the same relative paths.
