# Changelog

All notable changes to **Advanced Pixel Editor** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.3] - 2025-01-16

### Added
- Preview toggle checkbox to show/hide image preview
- Before/after comparison slider with draggable handle
- Original image loading for comparison functionality
- Redesigned layout with preview at top and compact controls sidebar
- Responsive design improvements for mobile devices

### Changed
- Moved image preview from bottom to top of editor interface
- Made control sections more compact to fit everything on screen
- Updated button sizes and spacing for better space utilization
- Improved CSS layout with flexbox for better organization

### Fixed
- Better handling of image loading states
- Improved accessibility with proper ARIA labels for new controls

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