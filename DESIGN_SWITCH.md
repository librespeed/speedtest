# Design Feature Switch

LibreSpeed now supports switching between the classic design and the new modern design.

## Default Behavior

By default, LibreSpeed uses the **classic design** (located in `index-classic.html`).

## Architecture

### File Structure (Non-Docker)
- **`index.html`** - Entry point (lightweight switcher)
- **`index-classic.html`** - Classic design at root
- **`index-modern.html`** - Modern design at root (references assets in subdirectories)
- **`frontend/`** - Directory containing modern design assets (CSS, JS, images, fonts) - kept for non-Docker deployments

### File Structure (Docker)
In Docker deployments, the frontend assets are flattened to root-level subdirectories:
- **`index.html`** - Entry point (lightweight switcher)
- **`index-classic.html`** - Classic design
- **`index-modern.html`** - Modern design  
- **`styling/`** - CSS files for modern design
- **`javascript/`** - JS files for modern design
- **`images/`** - Images for modern design
- **`fonts/`** - Fonts for modern design
- **No `frontend/` directory** - Assets are copied directly to root subdirectories

### Benefits of Root-Level Design Files
✅ Both designs at same level - no path confusion
✅ `results/` accessible from both designs with same relative path
✅ `backend/` accessible from both designs with same relative path  
✅ No subdirectory nesting issues
✅ Clean separation of concerns
✅ Docker containers have no `frontend/` parent directory

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

### Non-Docker Deployments
- **Entry Point**: Root `index.html` file (lightweight redirect page)
- **Old Design**: `index-classic.html` at root
- **New Design**: `index-modern.html` at root (references assets in `frontend/` subdirectory)
- **Assets**: Frontend assets (CSS, JS, images, fonts) in `frontend/` subdirectory

### Docker Deployments
- **Entry Point**: Root `index.html` file (lightweight redirect page)
- **Old Design**: `index-classic.html` at root
- **New Design**: `index-modern.html` at root (references assets in root subdirectories)
- **Assets**: Frontend assets copied directly to root subdirectories (`styling/`, `javascript/`, `images/`, `fonts/`)
- **No `frontend/` directory** - Assets are flattened to root level

Both designs are at the same directory level, ensuring that relative paths to shared resources like `backend/` and `results/` work correctly for both.

## Technical Details

The feature switch is implemented in `design-switch.js`, which is loaded by the root `index.html`. It checks:

1. First, URL parameters (`?design=new` or `?design=old`)
2. Then, the `config.json` configuration file
3. Redirects to either `index-classic.html` or `index-modern.html`

Both design HTML files are at the root level, eliminating path issues.

### Non-Docker
The modern design references assets from the `frontend/` subdirectory (e.g., `frontend/styling/index.css`), while both designs can access shared resources like `backend/` and `results/` using the same relative paths.

### Docker
In Docker deployments, the `frontend/` directory is flattened during container startup. Assets are copied directly to root-level subdirectories (`styling/`, `javascript/`, `images/`, `fonts/`), and `index-modern.html` references these root-level paths. This eliminates the `frontend/` parent directory in the container.
