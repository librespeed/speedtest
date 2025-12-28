# Design Feature Switch

LibreSpeed now supports switching between the classic design and the new modern design.

## Default Behavior

By default, LibreSpeed uses the **classic design** (located in `index.html`).

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

- **Old Design**: Root `index.html` file
- **New Design**: `frontend/index.html` and associated files in the `frontend/` directory

## Technical Details

The feature switch is implemented in `design-switch.js`, which is loaded early in the page lifecycle. It checks:

1. First, URL parameters (`?design=new` or `?design=old`)
2. Then, the `config.json` configuration file
3. Defaults to the old design if neither is specified or if there's an error

The script performs a client-side redirect to `frontend/index.html` when the new design is enabled.
