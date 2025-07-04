# Pikari Gutenberg Modals

✨ Beautiful modal windows for the WordPress block editor. Create engaging content with smooth animations and accessible modal dialogs that captivate your audience.

## Features

- 🎨 **Visual Format in Editor** - Modal links are clearly highlighted with purple styling and a modal icon
- 🔗 **Multiple Content Types** - Link to posts, pages, custom post types, or external URLs
- ⚡ **Alpine.js Integration** - Smooth animations with fallback for non-Alpine sites
- 📱 **Responsive Design** - Works seamlessly on all devices
- ♿ **Accessible** - Full keyboard navigation and screen reader support
- 🎯 **Block Support** - Works with paragraphs, headings, lists, buttons, and more
- 🔍 **Smart Search** - Built-in content search with REST API
- 🛡️ **Security First** - URL validation and domain allowlisting

## Installation

1. Upload the `pikari-gutenberg-modals` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The modal button will appear in the block editor toolbar

## Usage

1. Select text in any supported block
2. Click the modal link button (⧉) in the toolbar
3. Search for content or enter a URL
4. The selected text becomes a modal trigger

## Supported Blocks

By default, modal links work in:
- Paragraphs
- Headings
- Lists
- Quotes
- Navigation links

### Important Note: Button Blocks

The plugin does **not** support the core/button block to maintain HTML standards and accessibility. Button blocks render as `<button>` or `<a>` elements, and adding modal format would create nested interactive elements, which is invalid HTML.

**Alternatives for modal-triggering buttons:**
1. Use a regular button block and set the link URL directly
2. Use a paragraph block with custom CSS to style it as a button
3. Consider creating a custom "Modal Button" block (see Developer Documentation)

## Developer Documentation

### Hooks & Filters

#### Customize Supported Blocks
```php
add_filter('pikari_gutenberg_modals_supported_blocks', function($blocks) {
    $blocks[] = 'my-plugin/custom-block';
    return $blocks;
});
```

#### Process Modal Content
```php
add_filter('pikari_gutenberg_modals_content', function($content, $type, $id) {
    // Modify content before display
    return $content;
}, 10, 3);
```

#### Security Filters
```php
// Allow specific domains
add_filter('pikari_gutenberg_modals_allowed_domains', function($domains) {
    $domains[] = 'trusted-site.com';
    return $domains;
});

// Block domains
add_filter('pikari_gutenberg_modals_blocked_domains', function($domains) {
    $domains[] = 'untrusted-site.com';
    return $domains;
});
```

#### Customize Search
```php
add_filter('pikari_gutenberg_modals_search_args', function($args, $search) {
    // Modify WP_Query arguments
    $args['post_type'] = ['post', 'page'];
    return $args;
}, 10, 2);
```

### REST API

#### Search Endpoint
```
GET /wp-json/pikari-gutenberg-modals/v1/search
```

Parameters:
- `search` (required) - Search term
- `per_page` - Results per page (default: 20)
- `page` - Page number for pagination

### JavaScript Events

#### Alpine.js Integration
```javascript
// Open a modal programmatically
window.dispatchEvent(new CustomEvent('open-modal', {
    detail: { id: 'modal-id' }
}));
```

### CSS Custom Properties

Customize modal appearance:
```css
:root {
    --modal-overlay-bg: rgba(0, 0, 0, 0.8);
    --modal-content-bg: #ffffff;
    --modal-max-width: 800px;
    --modal-border-radius: 8px;
}
```

### Constants

- `PIKARI_GUTENBERG_MODALS_CACHE_DURATION` - Set cache duration for external content (seconds)

## Iframe Editor Compatibility

WordPress 5.8+ uses an iframe-based editor. This plugin includes special handling:
- Styles use `.editor-styles-wrapper` selectors
- `!important` declarations ensure visibility
- See `DEVELOPER-NOTES.md` for details

## Requirements

- WordPress 6.8+
- PHP 8.3+
- Modern browser with JavaScript enabled

## Changelog

### 1.0.0
- Initial release
- Core modal functionality
- Alpine.js integration
- REST API search
- Security features

## License

GPL v2 or later

## Credits

Developed by Pikari Inc.