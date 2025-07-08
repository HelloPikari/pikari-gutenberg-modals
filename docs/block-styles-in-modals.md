# Block Styles in Modals

## Overview

This document explains how the Pikari Gutenberg Modals plugin captures and preserves WordPress block support styles (gaps, spacing, typography, etc.) when content is loaded dynamically via the REST API.

## The Challenge

WordPress dynamically generates CSS for block supports during page rendering. These styles include:
- Layout styles (flexbox, grid)
- Gap/spacing values using theme presets
- Padding and margin values
- Typography settings

When content is loaded via REST API for modal display, these dynamically generated styles are not included by default, causing layout issues.

## Solution Architecture

### 1. Style Capture Using WordPress Core Functions

The `Block_Support` class includes a modified version of WordPress's `wp_render_layout_support_flag()` function that:
- Uses WordPress's built-in `wp_get_layout_style()` function to generate proper CSS
- Returns styles inline instead of enqueuing them to the footer
- Maintains compatibility with WordPress core updates
- Handles all block layout types (default, constrained, flex, grid)

### 2. Theme Value Preservation

The plugin correctly converts WordPress preset values to CSS custom properties:
- Input: `var:preset|spacing|xl`
- Output: `var(--wp--preset--spacing--xl)`

This ensures theme-defined spacing values are used instead of generic fallbacks.

### 3. REST API Integration

The modal content endpoint (`/wp-json/pikari-gutenberg-modals/v1/modal-content/{id}`) returns:
```json
{
  "id": 123,
  "title": "Post Title",
  "content": "<div class=\"wp-container-xxx\">...</div>",
  "styles": ".wp-container-xxx { display: flex; gap: var(--wp--preset--spacing--lg); }",
  "type": "post"
}
```

### 4. Frontend Implementation

The frontend JavaScript inlines the styles within the modal content:
```javascript
modalBody.innerHTML = `
    ${data.styles ? `<style>${data.styles}</style>` : ''}
    <article class="modal-post-content">
        <header class="modal-post-header">
            <h2>${data.title}</h2>
        </header>
        <div class="modal-post-body">
            ${data.content}
        </div>
    </article>
`;
```

## Technical Details

### Style Generation Method

The `render_layout_support_inline()` method:
- **Leverages WordPress Core**: Uses `wp_get_layout_style()` to ensure accurate style generation
- **Handles Block Context**: Properly processes block attributes and layout settings
- **Returns Inline Styles**: Captures styles that would normally be enqueued to the footer
- **Maintains Compatibility**: Follows WordPress core patterns for future-proof implementation

### Block-Specific Styles

Some block styles (like columns vertical alignment) are provided by block library CSS files. The plugin includes these essential styles in the modal CSS:
- Columns vertical alignment (`are-vertically-aligned-top/center/bottom`)
- Column self-alignment (`is-vertically-aligned-top/center/bottom/stretch`)

### Supported Layout Types

The implementation supports all WordPress layout types:
- **Default (Flow)**: Standard block spacing and alignment
- **Constrained**: Content width constraints with wide/full alignments
- **Flex**: Flexible layouts with gap, alignment, and wrap settings
- **Grid**: CSS Grid layouts (when available)

### CSS Custom Property Mapping

WordPress preset format:
```
var:preset|category|slug
```

Converts to CSS custom property:
```
var(--wp--preset--category--slug)
```

Examples:
- `var:preset|spacing|xs` → `var(--wp--preset--spacing--xs)`
- `var:preset|color|primary` → `var(--wp--preset--color--primary)`

## Troubleshooting

### Styles Not Appearing

1. Check that the post content contains blocks with layout support
2. Verify the REST endpoint is returning styles in the response
3. Ensure frontend code is properly injecting the `<style>` tag
4. Confirm WordPress core functions (`wp_get_layout_style`, `wp_get_layout_definitions`) are available

### Column Alignment Issues

If columns are not aligning properly:
- The plugin includes fallback CSS for vertical alignment classes
- Check that the modal CSS file is loaded and includes `.are-vertically-aligned-*` styles
- Verify the columns block has the correct classes in the HTML

### Missing Container Styles

Some blocks may not generate layout styles. This typically happens with:
- Simple text blocks without layout settings
- Blocks that don't support layout features
- Classic content without block markup

## Implementation Notes

### Why Not Use WordPress's Original Function?

WordPress's `wp_render_layout_support_flag()` function enqueues styles to the page footer, which doesn't work for content loaded via AJAX/REST API. Our modified version:
- Returns styles inline for immediate use
- Maintains the same logic as WordPress core
- Ensures compatibility with block editor updates

### Maintainability

By using WordPress core functions like `wp_get_layout_style()`, the plugin:
- Stays synchronized with WordPress updates
- Supports new layout features automatically
- Reduces maintenance burden
- Ensures consistent behavior with the block editor