# Pikari Gutenberg Modals

Accessible modal dialogs for the WordPress block editor. Display posts, pages, and external content in overlays triggered by inline links, buttons, or clickable cards.

[![WordPress](https://img.shields.io/badge/WordPress-6.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## Description

Adds modal dialogs to the WordPress block editor. Content (posts, pages, custom post types, or external URLs) loads in overlays. Triggers are real links that work without JavaScript (progressive enhancement).

### Trigger Types

- **Inline Modal Links** — Apply the modal format to text in paragraphs, headings, lists, quotes, and more (Cmd/Ctrl+M shortcut)
- **Button Block Modals** — Toggle "Open in Modal" on any core Button block
- **Clickable Group Cards** — Make an entire Group block clickable as a card pattern, auto-detecting the primary link inside

### Features

- **Accessibility** — ARIA attributes, focus trapping, keyboard navigation (Escape to close, Tab cycling), screen reader support, inert background
- **Template Part Customization** — Customize the modal dialog layout via the Site Editor template part system
- **Multiple Modal Templates** — Create different modal designs and assign them per trigger
- **Overlay Styling** — Solid colors, gradients, or images with alpha transparency
- **Dialog Styling** — Background, border, padding, and box shadow via block supports
- **Four Sizes** — Default, Small, Large, Fullscreen (extendable via filter)
- **Hover Prefetch** — 200ms debounced prefetch warms the browser cache before click
- **Dynamic Block Styles** — Loads stylesheets for blocks inside modal content on demand
- **HTTP Caching** — REST API responses include ETag, Last-Modified, and Cache-Control headers
- **Built on the Interactivity API** — Uses WordPress core Interactivity API for state management

### Theme Support

- **Block themes** — Full Site Editor template part support
- **Hybrid themes** — Automatic fallback rendering via `do_blocks()`

## Installation

1. Upload plugin files to `/wp-content/plugins/pikari-gutenberg-modals/`, or install through the WordPress plugins screen
2. Activate through the Plugins screen
3. Use any of the three trigger types in the block editor

### Block Themes

The plugin registers a "Modal" template part area. Customize in **Appearance → Editor → Patterns → Template Parts → Modal**. Create additional template parts in the "Modal" area for different designs.

### Hybrid Themes

Modals work automatically with the plugin's default template. To customize:

1. Create `parts/modal.html` in your theme directory with block markup
2. For additional templates, use `parts/modal-{slug}.html` (e.g., `parts/modal-compact.html`)
3. Or use the `pikari_gutenberg_modals_fallback_template` filter

### Composer

```bash
composer require pikari-inc/pikari-gutenberg-modals
```

## Development

```bash
npm install && composer install   # Install dependencies
npm start                         # Dev build with file watching
npm run build                     # Production build
npm run lint:all                  # Run all linters
npm run lint:fix                  # Auto-fix lint issues
npm test                          # Run tests
```

## Developer Filters

### Content & Display

#### `pikari_gutenberg_modals_supported_blocks`

Customize which block types support the inline modal link format.

```php
add_filter( 'pikari_gutenberg_modals_supported_blocks', function( $blocks ) {
    $blocks[] = 'my-plugin/custom-block';
    return $blocks;
} );
```

Default blocks: `core/paragraph`, `core/heading`, `core/list`, `core/list-item`, `core/quote`, `core/verse`, `core/preformatted`, `core/navigation-link`

#### `pikari_gutenberg_modals_content_response`

Modify the REST API response for modal content.

```php
add_filter( 'pikari_gutenberg_modals_content_response', function( $response_data, $post ) {
    $response_data['custom_field'] = get_post_meta( $post->ID, 'my_custom_field', true );
    return $response_data;
}, 10, 2 );
```

### Cache & Performance

#### `pikari_gutenberg_modals_cache_duration`

Customize the browser cache duration for modal content REST API responses.

```php
add_filter( 'pikari_gutenberg_modals_cache_duration', function( $duration ) {
    return 2 * HOUR_IN_SECONDS;
} );
```

Default: `HOUR_IN_SECONDS` (3600 seconds)

#### `pikari_gutenberg_modals_enable_prefetch_hints`

Enable automatic `<link rel="prefetch">` resource hints for modal content URLs detected on the page.

```php
add_filter( 'pikari_gutenberg_modals_enable_prefetch_hints', '__return_true' );
```

Default: `false` (hover-based prefetch still works regardless)

#### `pikari_gutenberg_modals_prefetch_urls`

Modify the REST API URLs included in prefetch resource hints.

```php
add_filter( 'pikari_gutenberg_modals_prefetch_urls', function( $urls, $post_ids ) {
    return $urls;
}, 10, 2 );
```

### Search

#### `pikari_gutenberg_modals_search_args`

Modify the WP_Query arguments for the editor search endpoint.

```php
add_filter( 'pikari_gutenberg_modals_search_args', function( $args, $search_term ) {
    $args['post_type'] = array( 'post', 'page' );
    return $args;
}, 10, 2 );
```

### Editor

#### `pikari_gutenberg_modals_modal_sizes`

Add or modify modal size options. Each entry needs a `label` and `value` (slug used as the `data-size` attribute). Custom sizes require matching CSS.

```php
add_filter( 'pikari_gutenberg_modals_modal_sizes', function( $sizes ) {
    $sizes[] = array(
        'label' => __( 'Medium', 'my-theme' ),
        'value' => 'medium',
    );
    return $sizes;
} );
```

```css
.modal-overlay[data-size='medium'] .modal-content {
	max-width: 768px;
}
```

Default sizes: Default (empty), Small (500px), Large (1200px), Fullscreen (100%)

### Domain Restrictions

#### `pikari_gutenberg_modals_allowed_domains`

Allowlist for external URL content. When set, only these domains are permitted.

```php
add_filter( 'pikari_gutenberg_modals_allowed_domains', function( $domains ) {
    return array( 'example.com', 'trusted-site.org' );
} );
```

#### `pikari_gutenberg_modals_blocked_domains`

Blocklist for external URL content.

```php
add_filter( 'pikari_gutenberg_modals_blocked_domains', function( $domains ) {
    return array( 'untrusted-site.com' );
} );
```

### Theme Customization

#### `pikari_gutenberg_modals_fallback_template`

Override the modal template markup for hybrid themes. Return block markup to bypass the default theme file and plugin template resolution.

```php
add_filter( 'pikari_gutenberg_modals_fallback_template', function( $content, $slug ) {
    if ( 'compact' === $slug ) {
        return '<!-- wp:pikari-gutenberg-modals/modal-dialog -->...<!-- /wp:pikari-gutenberg-modals/modal-dialog -->';
    }
    return $content;
}, 10, 2 );
```

## CSS Custom Properties

The plugin exposes CSS custom properties on `:root` for theming:

```css
/* Modal appearance */
--modal-overlay-bg: rgba(0, 0, 0, 0.8);
--modal-content-bg: #fff;
--modal-content-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
--modal-border-radius: 20px;

/* Modal widths */
--modal-max-width: 1024px; /* Default size */
--modal-max-width-small: 500px; /* Small size */
--modal-max-width-large: 1200px; /* Large size */

/* Interaction */
--modal-focus-color: #3b82f6;
--modal-transition: all 0.2s ease;
```

Override in your theme's CSS:

```css
:root {
	--modal-border-radius: 8px;
	--modal-max-width: 800px;
	--modal-content-bg: #f9f9f9;
}
```

## Changelog

### 1.0.0

- Three trigger types: inline modal links, button block modals, clickable group cards
- Template part system with per-trigger assignment
- Overlay styling: solid colors, gradients, images with alpha transparency
- Dialog styling: background, border, padding, box shadow via block supports
- Four sizes: Default, Small, Large, Fullscreen
- Hybrid theme support via `do_blocks()` fallback
- Theme file overrides (`parts/modal.html`, `parts/modal-{slug}.html`)
- `pikari_gutenberg_modals_fallback_template` filter
- Hover prefetch, dynamic block style loading, HTTP caching
- WCAG accessibility: focus trap, keyboard navigation, ARIA, inert background
- Progressive enhancement: triggers are real links without JavaScript
- CSS custom properties for theming
- Domain allowlist/blocklist for external URLs
- 12 developer filters

## License

GPL-2.0-or-later - see [LICENSE](LICENSE) for details.

## Author

**Pikari Inc.** — [pikari.io](https://pikari.io)
