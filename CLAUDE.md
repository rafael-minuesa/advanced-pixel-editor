# Advanced Image Editor - Development Notes

## Project Overview
WordPress plugin for professional image editing with contrast adjustment and unsharp masking. Requires ImageMagick PHP extension.

## Current Version
2.6 (January 21, 2025)

## File Structure
```
advanced-image-editor/
├── advanced-image-editor.php      # Main plugin file
├── editor-page.php                # Editor UI template
├── includes/
│   ├── class-advanced-image-editor.php    # Main plugin class
│   ├── class-advaimg-ajax-handler.php     # AJAX handlers
│   └── advaimg-functions.php              # Utility functions
├── assets/
│   ├── css/admin.css              # Editor styles
│   └── js/editor.js               # Editor JavaScript
├── languages/                     # Translation files
├── .wordpress-org/                # WP.org assets (banners, screenshots)
├── dev-tools/                     # Development utilities
├── readme.txt                     # WordPress.org readme
├── README.md                      # GitHub readme
└── build-wp-org-zip.sh           # Build script for submission
```

## Naming Conventions
- **Prefix**: `advaimg` (7 characters) - required by WordPress.org (4+ chars)
- **Constants**: `ADVAIMG_VERSION`, `ADVAIMG_PLUGIN_DIR`, `ADVAIMG_PLUGIN_URL`, `ADVAIMG_PLUGIN_BASENAME`
- **Classes**: `ADVAIMG_Ajax_Handler`, `Advanced_Image_Editor`
- **AJAX actions**: `advaimg_preview`, `advaimg_save`, `advaimg_get_original`
- **Nonce**: `advaimg_nonce`
- **Transients**: `advaimg_rate_limit_*`
- **JS object**: `ADVAIMG_AJAX`
- **Script handles**: `advaimg-admin-css`, `advaimg-editor-js`
- **HTML IDs**: Use `aie-*` prefix (not restricted by WP.org rules)

## Build Process
Run `./build-wp-org-zip.sh` to create submission ZIP. Excludes:
- `.git/`, `.wordpress-org/`, `dev-tools/`
- `README.md`, `CHANGELOG.md`, `CLAUDE.md`
- Build scripts, workspace files, node_modules

ZIP output: `../advanced-image-editor.zip`

## Key Implementation Details

### Comparison Slider
- Original image: `#aie-original-preview` (z-index: 1, behind)
- Edited image: `#aie-preview` (z-index: 2, on top)
- Clip-path applied to EDITED image to reveal original behind

### Image Data Sanitization
Base64 image data uses custom `sanitize_base64_image_data()` method in AJAX handler:
- Validates data URI format with strict regex
- Whitelists allowed MIME types (jpeg, png, gif, webp)
- Decodes and verifies actual image content
- Checks MIME type matches declared type

### Rate Limiting
Uses transients with key `advaimg_rate_limit_{hash}` for preview/save operations.

## WordPress.org Submission
- Plugin URI: https://github.com/rafael-minuesa/advanced-image-editor/
- After approval, upload `.wordpress-org/` assets via SVN to plugin assets folder

## Git Repository
https://github.com/rafael-minuesa/advanced-image-editor.git
