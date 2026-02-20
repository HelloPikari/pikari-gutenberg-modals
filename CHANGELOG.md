# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0]

### Added

- External URL support: external URLs now load in a sandboxed iframe inside the modal dialog
- `contentSource: 'url'` context property identifies external URL triggers on the frontend

### Fixed

- External URLs no longer blocked by default domain allowlist (allowlist now defaults to empty, permitting all domains)
- External URL triggers now render correctly instead of failing with REST API 404 errors

### Changed

- Domain allowlist defaults to empty array (all domains allowed); blocklist and private IP checks still apply
- Prefetch skipped for external URL triggers (cross-origin iframe content cannot be prefetched)

## [1.2.0]

### Added

- Modal Trigger block — dedicated clickable card wrapper with three content source modes (detected link, custom URL, page content)
- Bidirectional transforms between Modal Trigger block and core/group
- SVG icon indicator for inline triggers in the editor

### Changed

- Renamed "Modal Link" to "Modal Trigger" for consistent terminology across all trigger types
- Unified block icons across all modal blocks
- Improved modal-content REST endpoint with schema and argument validation
- Dynamic REST URL for plain permalink compatibility

### Removed

- Unused search REST endpoint

## [1.1.0]

### Added

- Hybrid theme support: modals render via `do_blocks()` fallback when `block_template_part()` is unavailable
- Theme file overrides for hybrid themes (`parts/modal.html`, `parts/modal-{slug}.html`)
- `pikari_gutenberg_modals_fallback_template` filter for programmatic template customization

### Fixed

- Close button uses absolute positioning to overlap content instead of taking a flex row
- Close button alignment (right/center/left) maps to position offsets
- SVG close icon for consistent rendering across theme fonts
- Dialog content scrolls when taller than the viewport (flex chain fix)
- Block CSS files now copied to build directory by webpack
- Default padding persists correctly (block supports override workaround)

## [1.0.0]

### Added

- Three trigger types: inline modal links, button block modals, clickable group cards
- Template part system with per-trigger assignment
- Overlay styling: solid colors, gradients, images with alpha transparency
- Dialog styling: background, border, padding, box shadow via block supports
- Four sizes: Default, Small, Large, Fullscreen
- Hover prefetch, dynamic block style loading, HTTP caching
- WCAG accessibility: focus trap, keyboard navigation, ARIA, inert background
- Progressive enhancement: triggers are real links without JavaScript
- CSS custom properties for theming
- Domain allowlist/blocklist for external URLs
- 12 developer filters

[Unreleased]: https://github.com/HelloPikari/pikari-gutenberg-modals/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/HelloPikari/pikari-gutenberg-modals/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/HelloPikari/pikari-gutenberg-modals/releases/tag/v1.0.0
