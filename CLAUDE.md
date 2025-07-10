# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Pikari Gutenberg Modals is a WordPress plugin that adds modal window functionality to the block editor. It allows content creators to transform selected text into modal triggers that display content in beautiful, animated popups.

## Development Commands

### Build and Development
```bash
# Install dependencies
npm install

# Start development build with file watching
npm start

# Production build
npm run build

# Create plugin ZIP for distribution
npm run plugin-zip
```

### Code Quality
```bash
# Lint JavaScript
npm run lint:js

# Lint SCSS/CSS
npm run lint:css

# Update WordPress packages to latest versions
npm run packages-update

# PHP code standards check (PSR-2 with spaces)
phpcs
```

## Architecture

### Plugin Structure
- **Entry Point**: `pikari-gutenberg-modals.php` - WordPress plugin header
- **Main Logic**: `includes/plugin.php` - Core initialization and REST API
- **PHP Classes**: PSR-4 autoloaded from `includes/` directory
  - `Block_Support` - Processes modal spans in block content
  - `Editor_Integration` - Enqueues editor assets and toolbar
  - `Frontend_Renderer` - Renders modal HTML in footer
  - `Modal_Handler` - Validates and processes modal content

### JavaScript Architecture
- **Editor Script** (`src/editor/index.js`): Registers format type and toolbar button
  - `modal-format.js` - Format registration with RichText API
  - `modal-link-edit.js` - LinkControl component for URL selection
- **Frontend Script** (`src/frontend/index.js`): Modal display with Alpine.js or vanilla JS fallback

### Build System
- Uses `@wordpress/scripts` (wp-scripts) for Webpack configuration
- Entry points defined in `package.json` under `wp-scripts.entry`
- Outputs to `build/` directory (gitignored)

## Key Implementation Details

### Block Editor Integration
- Adds custom "modal" format to RichText toolbar
- Uses WordPress Format API (`registerFormatType`)
- Supports paragraphs, headings, lists, quotes by default
- **Does NOT support button blocks** (creates invalid nested interactive elements)

### Modal Content Processing
1. Selected text wrapped in `<span class="modal-link-trigger">`
2. Data attributes store modal configuration (URL, type, ID)
3. Block_Support class processes spans via regex on `render_block`
4. Modal HTML rendered in footer via `wp_footer` action

### Security Features
- URL validation with domain allowlisting/blocklisting
- Content sanitization with `wp_kses_post()`
- Nonce verification for AJAX requests
- Local URL detection to prevent SSRF

### REST API Endpoint
- `/wp-json/pikari-gutenberg-modals/v1/search` - Content search
- Returns paginated results with Link headers
- Filterable via `pikari_gutenberg_modals_search_args`

## Extensibility Hooks

### PHP Filters
- `pikari_gutenberg_modals_supported_blocks` - Customize supported blocks
- `pikari_gutenberg_modals_content` - Modify modal content before display
- `pikari_gutenberg_modals_allowed_domains` - Allowlist domains
- `pikari_gutenberg_modals_blocked_domains` - Block domains
- `pikari_gutenberg_modals_search_args` - Customize search query

### JavaScript Events
- `open-modal` - Programmatically open modals (Alpine.js sites)
- Modal triggers use `data-modal-id` attributes

## Important Considerations

### Iframe Editor Compatibility
WordPress 5.8+ uses iframe-based editor requiring special CSS handling:
- Styles must use `.editor-styles-wrapper` prefix
- `!important` declarations needed to override editor defaults
- See `DEVELOPER-NOTES.md` for detailed explanation

### Testing Areas
- Modal creation/editing in block editor
- Search functionality with various post types
- External URL validation and content display
- Keyboard navigation and screen reader support
- Alpine.js integration and vanilla JS fallback
- Mobile responsiveness

### Code Standards
- PHP: PSR-2 with spaces (not tabs) - see `phpcs.xml`
- JavaScript: WordPress coding standards via `@wordpress/scripts`
- Comprehensive JSDoc and PHPDoc documentation required

## Block Styles in Dynamic Content

When content is loaded dynamically via REST API for modals, WordPress block support styles (gaps, spacing, typography) must be captured and preserved. See `/docs/block-styles-in-modals.md` for detailed documentation on how this is implemented.

Key points:
- The `Block_Support::get_post_content_with_styles()` method captures dynamically generated CSS
- Theme preset values (e.g., `var:preset|spacing|xl`) are converted to CSS custom properties
- Styles are returned via REST API and inlined within modal content
- This ensures proper layout and spacing using theme-defined values

## Release Process - Version Updates

When creating a new release, update the version number in these files:

1. **pikari-gutenberg-modals.php** (line ~6)
   - Update the `Version:` header in the plugin file header comment

2. **includes/plugin.php** (line ~16)
   - Update `PIKARI_GUTENBERG_MODALS_VERSION` constant

3. **package.json** (line ~3)
   - Update the `"version"` field

4. **CHANGELOG.md**
   - Add new version section under `## [Unreleased]`
   - Update version comparison links at the bottom

5. **composer.json** (if changing requirements)
   - Update PHP version requirement if changed
   - Currently requires PHP 8.2+

Additional version-related updates:
- If PHP version requirement changes, also update:
  - `pikari-gutenberg-modals.php` (line ~11) - `Requires PHP:` header
  - `includes/plugin.php` (line ~71) - PHP version check in activation hook

## Important Note

This CLAUDE.md file is specific to the Pikari Gutenberg Modals plugin directory. When working in this plugin, use this file for guidance rather than any parent directory CLAUDE.md files.