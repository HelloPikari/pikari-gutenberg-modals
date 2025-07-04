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

### 1. Style Capture During Rendering

The `Block_Support` class includes a `get_post_content_with_styles()` method that:
- Hooks into the `render_block` filter during content processing
- Captures container IDs (e.g., `wp-container-core-group-xxx`)
- Generates appropriate CSS for each container based on block attributes

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

The `generate_layout_styles()` method handles:
- **Layout detection**: Identifies flex/grid layouts
- **Gap calculation**: Converts block gap attributes to CSS
- **Alignment**: Maps WordPress alignment values to flexbox properties
- **Spacing**: Processes padding/margin with theme presets

### Supported Block Attributes

The following block attributes are processed:
- `layout.type` (flex, grid, default)
- `layout.flexWrap`
- `layout.verticalAlignment`
- `style.spacing.blockGap` (string or array)
- `style.spacing.padding` (directional values)

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

### Generic Fallback Values

If you see values like `var(--wp--style--block-gap, 1.5rem)`, the theme preset conversion may not be working. Check:
- Block attributes contain preset values
- The `convert_value_to_css()` method is properly detecting preset format

### Missing Container Styles

Some blocks may not have container IDs. This typically happens with:
- Simple text blocks without layout settings
- Blocks that don't support layout features
- Classic content without block markup

## Future Improvements

Potential enhancements:
- Cache generated styles for performance
- Support for additional block supports (colors, typography)
- Integration with block style variations
- Support for responsive spacing values