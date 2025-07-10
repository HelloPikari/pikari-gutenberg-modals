# Changelog

All notable changes to Pikari Gutenberg Modals will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- Resolved fatal error when installing via Composer due to namespace in plugin.php
  - Removed namespace declaration from plugin.php to prevent WordPress function resolution issues
  - Moved REST API functionality to dedicated RestApi class for better separation of concerns
  - WordPress functions now properly resolve in global namespace when loaded via Composer
- Fixed build branch to exclude unnecessary vendor directory
  - Removed plugin.php from composer.json files array to eliminate composer dependency in production
  - Build branch now contains only necessary files for WordPress Playground and direct downloads
  - Plugin works without composer autoloader for production use

## [0.3.1] - 2025-01-10

### Fixed
- PSR-4 autoloading compliance for composer installations
  - Renamed class files from hyphenated to standard format
  - Updated class names to match filenames
  - Fixed composer autoloading warnings
  - No functional changes for end users

## [0.3.0] - 2025-01-10

### Added
- WordPress Playground integration for instant plugin demos
  - Public demo accessible via badge in README
  - Local development support with wp-now
  - Separate blueprint configurations for public and local environments
- GitHub Actions workflow for automated build branch management
  - Automatically builds and deploys compiled assets to build branch
  - Enables WordPress Playground to work with GitHub source
- Interactive demo content showcasing modal functionality
  - Three working examples: internal page, self-referential post, and external URL
  - Step-by-step instructions for creating new modals
- Composer.lock file for consistent development dependencies

### Changed
- Lowered PHP requirement from 8.3 to 8.2 for broader compatibility
- Updated composer package name to pikari/pikari-gutenberg-modals
- WordPress Playground demo now lands on frontend view instead of editor
- Fixed modal span attributes in demo content to match plugin format

### Fixed
- Build workflow permissions and git handling
- Modal frontend functionality in WordPress Playground
- Composer install issues in build workflow

### Developer Notes
- Build files remain gitignored in main branch
- Composer installation from main branch provides source-only distribution
- Build branch contains compiled assets for WordPress Playground compatibility

## [0.2.0] - 2025-01-09

### Added
- Proper test directory structure with unit, integration, e2e, fixtures, and manual subdirectories
- Organized documentation in `docs/` directory with comprehensive guides
- Source code organization with `shared/` and `utils/` directories for better modularity
- Standard project files:
  - `CHANGELOG.md` - Version history following Keep a Changelog format
  - `LICENSE` - GPL-2.0-or-later license
  - `CONTRIBUTING.md` - Contribution guidelines with coding standards
  - `composer.json` - PHP dependency management and autoloading
- Testing infrastructure with PHPUnit and Jest configuration
- GitHub Actions CI/CD workflow for automated testing and releases
- `.gitattributes` for clean Composer distribution
- Automated release process with GitHub Actions
- Release documentation and scripts
- Translation support with pot file generation

### Changed
- Reorganized project structure following WordPress plugin best practices
- Updated `.gitignore` for new directory structure
- Fixed package.json to remove duplicate webpack configuration
- Updated version to stable release (removed alpha designation)
- Enhanced documentation with proper linking and organization

## [0.1.0-alpha] - 2024-07-08

### Added
- Initial pre-release of Pikari Gutenberg Modals
- Block Editor Integration with custom toolbar button (⧉)
- Visual editing experience with purple styling and icon indicators
- Support for posts, pages, custom post types, and external URLs
- Smart search powered by REST API
- Modern frontend animations with Alpine.js (graceful fallback included)
- Full accessibility with keyboard navigation and screen reader support
- Responsive design for all device sizes
- WordPress 6.8+ compatibility
- Security features: URL validation, domain allowlisting, nonce verification
- Developer-friendly hooks, filters, and REST API
- Support for paragraphs, headings, lists, quotes, and navigation blocks
- Customizable modal appearance via CSS custom properties
- Comprehensive developer documentation

### Technical Requirements
- WordPress 6.8+
- PHP 8.3+
- Modern browser with JavaScript enabled

### Known Limitations
- Button blocks not supported (maintains HTML standards and accessibility)
- Alpha release - testing in development environment recommended

[Unreleased]: https://github.com/HelloPikari/pikari-gutenberg-modals/compare/v0.3.1...HEAD
[0.3.1]: https://github.com/HelloPikari/pikari-gutenberg-modals/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/HelloPikari/pikari-gutenberg-modals/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/HelloPikari/pikari-gutenberg-modals/compare/v0.1.0-alpha...v0.2.0
[0.1.0-alpha]: https://github.com/HelloPikari/pikari-gutenberg-modals/releases/tag/v0.1.0-alpha