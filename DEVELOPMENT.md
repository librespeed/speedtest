# Development Guide

This document describes how to use the package manager (npm) for development tasks.

## Prerequisites

- Node.js 14.0.0 or higher
- npm (comes with Node.js)

## Getting Started

Install development dependencies:

```bash
npm install
```

## Available Scripts

### Linting

Check code for potential issues:

```bash
npm run lint
```

Automatically fix linting issues where possible:

```bash
npm run lint:fix
```

### Code Formatting

Check code formatting (JavaScript files only):

```bash
npm run format:check
```

Format JavaScript files:

```bash
npm run format
```

> **Note**: The existing codebase uses its original formatting style. Prettier and ESLint are provided as optional tools for new code or improvements.

### Validation

Run all checks (formatting and linting):

```bash
npm run validate
```

### Testing

Currently, there are no automated tests configured:

```bash
npm run test
```

### Docker

Build Docker images:

```bash
npm run docker:build          # Standard Debian-based image
npm run docker:build-alpine   # Alpine-based image
```

## Development Tools

The package manager setup includes:

- **ESLint**: JavaScript linting tool to catch common errors
- **Prettier**: Code formatting tool to maintain consistent style

These tools are configured but non-intrusive to the existing codebase.

## Project Structure

```
.
├── speedtest.js              # Main speedtest library
├── speedtest_worker.js       # Web Worker for speed testing
├── index.html                # Default UI
├── backend/                  # PHP backend files
├── examples/                 # Example implementations
├── results/                  # Results/telemetry handling
├── docker/                   # Docker-related files
└── package.json              # npm package configuration
```

## Why Use a Package Manager?

The package manager provides several benefits:

1. **Standardized tooling**: Common commands across different environments
2. **Development dependencies**: Easy installation of linting and formatting tools
3. **Project metadata**: Version, description, and licensing information
4. **Future extensibility**: Foundation for adding build tools, tests, or bundlers if needed
5. **npm distribution**: Makes the library easy to use in other Node.js projects

## Contributing

When making changes:

1. Run `npm run lint` to check for potential issues
2. Consider running `npm run format` on new files for consistency
3. Test your changes manually in a browser
4. For PHP backend changes, test with the appropriate server setup

## Notes

- The core library has **no runtime dependencies** - it's pure vanilla JavaScript
- Development dependencies (ESLint, Prettier) are only needed for development
- The library can still be used standalone without npm (just include the JS files)
- npm setup is completely optional and doesn't change how the library is deployed
