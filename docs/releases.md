# Release Process

This document describes the automated release process for Pikari Gutenberg Modals.

## Overview

Releases are automatically created by GitHub Actions when you push a tag starting with `v`. The automation handles building, packaging, and publishing the release to GitHub.

## Prerequisites

- All code changes merged to main branch
- Clean working directory (no uncommitted changes)
- Node.js and Composer dependencies installed locally for testing

## Step-by-Step Release Process

### 1. Update Version Numbers

Update the version in three locations:

#### `pikari-gutenberg-modals.php`
```php
 * Version: 0.2.0
```

#### `package.json`
```json
{
  "version": "0.2.0",
```

#### `CHANGELOG.md`
- Move items from `[Unreleased]` section to a new version section
- Add the release date
- Update comparison links at the bottom

Example:
```markdown
## [0.2.0] - 2024-01-09

### Added
- Automated release process via GitHub Actions
- Comprehensive documentation structure

### Changed
- Reorganized project structure following WordPress best practices
```

### 2. Run Pre-Release Checks

```bash
# Check for uncommitted changes
git status

# Run linting
npm run lint:js
npm run lint:css
composer lint

# Build assets locally to verify
npm run build

# Run tests if available
npm test
```

### 3. Commit Version Updates

```bash
git add -A
git commit -m "Prepare v0.2.0 release"
git push origin main  # or your feature branch
```

### 4. Create and Push Tag

```bash
# Create annotated tag
git tag -a v0.2.0 -m "Release version 0.2.0"

# Push the tag to trigger automation
git push origin v0.2.0
```

### 5. Monitor the Release

1. Go to the [Actions tab](https://github.com/your-org/pikari-gutenberg-modals/actions) on GitHub
2. Watch the "CI" workflow run
3. Once complete, check the [Releases page](https://github.com/your-org/pikari-gutenberg-modals/releases)
4. The new release should appear with:
   - Release notes extracted from CHANGELOG.md
   - ZIP file attached for download
   - Marked as pre-release if version contains "alpha" or "beta"

## What the Automation Does

When you push a tag starting with `v`, GitHub Actions will:

1. **Run Quality Checks**
   - JavaScript linting
   - CSS linting
   - PHP code standards check
   - Run tests

2. **Build Release Package**
   - Install production dependencies only
   - Build optimized assets
   - Create ZIP file excluding development files

3. **Create GitHub Release**
   - Extract release notes for the specific version from CHANGELOG.md
   - Upload the ZIP file as a release asset
   - Auto-detect pre-release status based on version string

## Version Naming Convention

Follow [Semantic Versioning](https://semver.org/):

- `MAJOR.MINOR.PATCH` for stable releases (e.g., `1.0.0`)
- `MAJOR.MINOR.PATCH-alpha.N` for alpha releases (e.g., `0.2.0-alpha.1`)
- `MAJOR.MINOR.PATCH-beta.N` for beta releases (e.g., `0.2.0-beta.1`)
- `MAJOR.MINOR.PATCH-rc.N` for release candidates (e.g., `1.0.0-rc.1`)

## Troubleshooting

### Release Not Created

1. Ensure the tag starts with `v` (e.g., `v0.2.0` not `0.2.0`)
2. Check GitHub Actions for any failed steps
3. Verify all version numbers were updated correctly

### Build Failures

1. Run `npm ci` and `composer install` locally
2. Ensure all linting passes
3. Check for any missing dependencies

### Manual Release (Fallback)

If automation fails, you can create a release manually:

```bash
# Create release ZIP locally
./bin/create-release.sh

# Upload to GitHub releases manually
```

## Composer Installation

Users can install the plugin via Composer from any tagged release:

```bash
composer require pikari/gutenberg-modals:^0.2
```

The `.gitattributes` file ensures only production files are included in Composer installs.