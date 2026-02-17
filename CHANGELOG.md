# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
