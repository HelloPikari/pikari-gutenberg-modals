# Changelog

All notable changes to Pikari Gutenberg Modals will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/yourusername/pikari-gutenberg-modals/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/yourusername/pikari-gutenberg-modals/compare/v0.1.0-alpha...v0.2.0
[0.1.0-alpha]: https://github.com/yourusername/pikari-gutenberg-modals/releases/tag/v0.1.0-alpha