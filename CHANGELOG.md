# Changelog

All notable changes to **Advanced Pixel Editor** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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