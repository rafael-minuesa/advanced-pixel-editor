# Release Process

This document outlines the process for releasing new versions of the Advanced Pixel Editor plugin.

## Version Numbering

This project follows [Semantic Versioning](https://semver.org/):

- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

## Automated Version Bumping

Use the provided script to automatically bump versions:

```bash
# Bump patch version
./version-bump.sh patch "Fixed bug description"

# Bump minor version
./version-bump.sh minor "Added new feature"

# Bump major version
./version-bump.sh major "Breaking change description"
```

The script will:
- Update version numbers in `advanced-pixel-editor.php` and `readme.txt`
- Add a new section to `CHANGELOG.md`
- Update the changelog in `readme.txt`

## Manual Release Process

If you prefer manual updates:

1. **Update Version Numbers**
   - `advanced-pixel-editor.php` (header comment and ADVAIMG_VERSION constant)
   - `readme.txt` (Stable tag)

2. **Update Changelog**
   - Add new version section to `CHANGELOG.md`
   - Update changelog section in `readme.txt`

3. **Commit Changes**
   ```bash
   git add .
   git commit -m "Release vX.Y.Z"
   ```

4. **Create Git Tag**
   ```bash
   git tag vX.Y.Z
   ```

5. **Push to Repository**
   ```bash
   git push origin main
   git push origin --tags
   ```

6. **Create GitHub Release** (optional)
   - Go to GitHub repository
   - Create new release from the tag
   - Copy changelog entries as release notes

## Changelog Format

Follow [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format:

```markdown
## [Unreleased]

### Added
- New feature description

### Changed
- Changed functionality description

### Fixed
- Bug fix description

## [1.2.3] - 2024-01-15

### Added
- Another new feature
```

## Pre-release Checklist

- [ ] All tests pass
- [ ] Code follows WordPress coding standards
- [ ] Version numbers updated in all files
- [ ] Changelog updated
- [ ] Readme.txt updated
- [ ] No sensitive information in commits
- [ ] Plugin tested on target WordPress versions
- [ ] Accessibility features verified

## WordPress Plugin Directory

When submitting to WordPress Plugin Directory:

1. Ensure `readme.txt` follows WordPress format
2. Test plugin in clean WordPress installation
3. Verify all assets are properly licensed
4. Check that all external links work