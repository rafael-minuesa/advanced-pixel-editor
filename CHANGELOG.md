# Changelog

All notable changes to **Advanced Pixel Editor** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.1] - 2026-02-08

### Added
- "Advanced Editor" button on the attachment edit page (`post.php?post=ID&action=edit`) via `attachment_submitbox_misc_actions` hook
- Button appears in the publish meta box sidebar for image attachments only

## [3.0] - 2026-02-08

### Added
- Save mode selection: "Save as new image" (default) or "Replace original image"
- Custom filename input when saving as a new image
- Automatic backup when replacing original image using WordPress `_wp_attachment_backup_sizes` meta pattern
- "Restore Original" button to revert replaced images to their original version
- New AJAX endpoint `advaimg_restore` for image restoration
- Backup detection on image load with restore notice UI
- New i18n strings for all save/replace/restore UI elements

### Changed
- Save workflow refactored into `save_as_new()` and `save_replace()` private methods
- `ajax_get_original()` now returns `has_backup` status
- Updated help text to reflect new save options

## [2.9] - 2026-01-28

### Added
- "Advanced Editor" button in Media Library grid view modal, next to the "Edit Image" button
- "Advanced Edit" row action in Media Library list view, positioned after "Edit"
- Direct image pre-loading via `attachment_id` URL parameter for seamless Media Library integration
- New `assets/js/media-library.js` for Media Library Backbone view integration

## [2.8] - 2025-01-23

### Added
- Draggable comparison slider handle directly on the image preview
- Click anywhere on preview wrapper to move comparison slider
- Touch support for comparison slider on mobile devices
- Negative value support for all filter controls

### Changed
- Contrast range extended from 0-1 to -1 to 1
- Sharpness Amount range extended from 0-5 to -5 to 5
- Sharpness Radius range extended from 0-10 to -10 to 10
- Sharpness Threshold range extended from 0-1 to -1 to 1

## [2.4] - 2025-01-16

### Added
- Manual input fields for all filter values (contrast, sharpness amount, radius, threshold)
- Side-by-side layout with image preview on left and controls on right
- Compact image selector with selected image name display

### Changed
- Redesigned UI layout for better space efficiency
- Added number inputs alongside sliders for precise value entry
- Updated help text to reflect new features and workflow
- Shortened plugin description to meet WordPress.org requirements

### Fixed
- Removed development files causing WordPress plugin validation errors
- Fixed short description length issue for WordPress.org
- Restored README.md to root directory
- Improved responsive design for new layout

### Removed
- Development files (version-bump.sh, .gitignore, RELEASE.md) from plugin root

## [2.2] - 2025-01-16

### Added
- Complete plugin rename from "Advanced Image Filters" to "Advanced Pixel Editor"
- Enhanced security with rate limiting, input validation, and capability checks
- Improved accessibility with ARIA support and keyboard navigation
- Performance optimizations with memory management and dimension limits
- Better user interface with loading states and progress indicators
- Comprehensive internationalization support
- WordPress coding standards compliance

### Changed
- Updated plugin architecture for better maintainability
- Improved error handling and user feedback
- Enhanced AJAX request handling with proper security measures

### Fixed
- Resolved potential security vulnerabilities
- Fixed accessibility issues for screen readers
- Improved mobile responsiveness

## [2.1] - 2024-XX-XX

### Added
- Complete plugin rename from "Advanced Image Filters" to "Advanced Pixel Editor"
- Enhanced security with rate limiting, input validation, and capability checks
- Improved accessibility with ARIA support and keyboard navigation
- Performance optimizations with memory management and dimension limits
- Better user interface with loading states and progress indicators
- Comprehensive internationalization support
- WordPress coding standards compliance

## [2.0] - 2024-XX-XX

### Added
- Initial release with basic image editing functionality
- Real-time preview with contrast and sharpening controls
- WordPress media library integration
- Basic accessibility features

---

## Version Numbering

This project uses [Semantic Versioning](https://semver.org/):

- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

## Release Process

To create a new release:

1. Update version numbers in:
   - `advanced-pixel-editor.php` (header and constant)
   - `readme.txt` (Stable tag)
   - `CHANGELOG.md` (add new version section)

2. Update changelog with new features, changes, and fixes

3. Commit changes with message: `Release vX.Y.Z`

4. Create git tag: `git tag vX.Y.Z`

5. Push to repository: `git push && git push --tags`

## Contributing

When contributing to this project:

- Add entries to the `[Unreleased]` section above
- Use the following types:
  - `Added` for new features
  - `Changed` for changes in existing functionality
  - `Deprecated` for soon-to-be removed features
  - `Removed` for now removed features
  - `Fixed` for any bug fixes
  - `Security` for vulnerability fixes