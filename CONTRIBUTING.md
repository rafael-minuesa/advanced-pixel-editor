# Contributing to Advanced Pixel Editor

Thank you for your interest in contributing to Advanced Pixel Editor! This document provides guidelines for contributing to the project.

## Getting Started

1. Fork the repository on [GitHub](https://github.com/rafael-minuesa/advanced-pixel-editor)
2. Clone your fork locally
3. Create a new branch for your feature or fix
4. Make your changes
5. Submit a pull request

## Prerequisites

- WordPress 5.6+
- PHP 7.4+
- ImageMagick PHP extension (Imagick)
- A local WordPress development environment

## Development Setup

1. Clone the repository into your WordPress `wp-content/plugins/` directory (or symlink it):

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/rafael-minuesa/advanced-pixel-editor.git
```

2. Activate the plugin in WordPress admin under Plugins.

3. Navigate to Media > Advanced Pixel Editor to verify it loads correctly.

## Project Structure

```
advanced-pixel-editor/
├── advanced-pixel-editor.php          # Main plugin entry point
├── editor-page.php                    # Editor UI template
├── includes/
│   ├── class-advanced-pixel-editor.php    # Main plugin class
│   ├── class-advaimg-ajax-handler.php     # AJAX request handlers
│   └── advaimg-functions.php              # Helper functions
├── assets/
│   ├── css/admin.css                  # Editor styles
│   └── js/
│       ├── editor.js                  # Editor JavaScript
│       └── media-library.js           # Media Library integration
├── languages/                         # Translation files
└── readme.txt                         # WordPress.org readme
```

## Naming Conventions

- **PHP prefix**: `advaimg` for constants, classes, AJAX actions, transients
- **HTML IDs**: `aie-*` prefix
- **Script handles**: `advaimg-*`
- **JS objects**: `ADVAIMG_AJAX`, `ADVAIMG_MEDIA`

## Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use proper escaping (`esc_html`, `esc_attr`, `esc_url`) for all output
- Use nonces and capability checks for all AJAX handlers
- Use `__()` and `esc_html_e()` for translatable strings with the `advanced-pixel-editor` text domain
- Keep JavaScript in separate files, not inline

## Security

Security is a priority. All contributions must:

- Validate and sanitize all input
- Escape all output
- Use nonces for form submissions and AJAX requests
- Check user capabilities before performing actions
- Avoid direct file access (include `if (!defined('ABSPATH')) { exit; }`)

## Submitting Changes

1. Create a descriptive branch name (e.g., `add-brightness-filter`, `fix-preview-resize`)
2. Keep commits focused and atomic
3. Write clear commit messages describing what changed and why
4. Submit a pull request against the `main` branch
5. Describe what your PR does and how to test it

## Reporting Bugs

- Use [GitHub Issues](https://github.com/rafael-minuesa/advanced-pixel-editor/issues) to report bugs
- Include your WordPress version, PHP version, and browser
- Describe the steps to reproduce the issue
- Include screenshots if applicable

## Feature Requests

Feature requests are welcome via [GitHub Issues](https://github.com/rafael-minuesa/advanced-pixel-editor/issues). Please describe the use case and expected behavior.

## License

By contributing, you agree that your contributions will be licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
