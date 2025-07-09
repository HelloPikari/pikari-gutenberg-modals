# Developer Notes - Pikari Gutenberg Modals

## WordPress Block Editor Iframe Compatibility

As of WordPress 5.8+, the block editor uses an iframe-based approach for better style isolation. This requires special handling for custom format styles.

### Key Requirements for Iframe Editor Styles:

1. **Use `.editor-styles-wrapper` selectors**: The iframe editor wraps content in this class
   ```scss
   // Standard selector
   span.modal-link-trigger { }
   
   // Iframe-compatible selector
   .editor-styles-wrapper span.modal-link-trigger { }
   ```

2. **Use `!important` declarations**: Required to override default editor styles within the iframe
   ```scss
   background-color: rgba(139, 92, 246, 0.1) !important;
   ```

3. **Style enqueueing**: Styles are automatically included via webpack imports in the editor script
   ```javascript
   // src/editor/index.js
   import './style.scss';
   ```

### Testing

Always test format styles in:
- Classic editor mode
- Iframe editor mode (default in WordPress 5.8+)
- Different themes that may have their own editor styles

### Reference

- [WordPress Make: Blocks in an iframed (template) editor](https://make.wordpress.org/core/2021/06/29/blocks-in-an-iframed-template-editor/)

## Button Block Limitations

The modal toolbar button intentionally does not support the core/button block due to HTML and accessibility constraints.

### Why Button Blocks Are Excluded

Button blocks render as either `<button>` or `<a>` elements. Our modal format creates a `<span>` with `role="button"`, which would result in:

```html
<!-- Invalid nested interactive elements -->
<button class="wp-block-button__link">
    <span class="modal-link-trigger" role="button" tabindex="0">
        Button Text
    </span>
</button>
```

This creates:
- **Invalid HTML**: Interactive elements cannot contain other interactive elements
- **Accessibility Issues**: Screen readers and keyboard navigation become confused
- **Unpredictable Behavior**: Click handlers may conflict

### Alternative Approaches for Modal Buttons

If you need buttons that trigger modals, consider these approaches:

#### 1. Custom Block Variation
Create a button variation that triggers modals:

```javascript
wp.blocks.registerBlockVariation('core/button', {
    name: 'modal-button',
    title: 'Modal Button',
    attributes: {
        className: 'is-modal-trigger',
        rel: 'modal'
    }
});
```

#### 2. Custom CSS Class Approach
Use a paragraph block with button styling:

```css
.button-style-paragraph {
    display: inline-block;
    padding: 0.5em 1em;
    background: var(--wp-admin-theme-color);
    color: white;
    text-decoration: none;
    border-radius: 4px;
}
```

#### 3. Filter to Add Support (Not Recommended)
While you could add button support via filter, this creates invalid HTML:

```php
// NOT RECOMMENDED - Creates accessibility issues
add_filter('pikari_gutenberg_modals_supported_blocks', function($blocks) {
    $blocks[] = 'core/button';
    return $blocks;
});
```

#### 4. Future Enhancement: Modal Button Block
Consider creating a dedicated block that properly handles modal triggering:

```php
register_block_type('pikari-gutenberg-modals/modal-button', [
    'render_callback' => function($attributes, $content) {
        return sprintf(
            '<button class="wp-block-button__link modal-trigger" data-modal-id="%s">%s</button>',
            esc_attr($attributes['modalId']),
            esc_html($content)
        );
    }
]);
```

### Best Practices

1. **Maintain Semantic HTML**: Don't nest interactive elements
2. **Preserve Accessibility**: Ensure keyboard navigation works properly
3. **Follow ARIA Guidelines**: Use appropriate roles and attributes
4. **Test Thoroughly**: Verify with screen readers and keyboard-only navigation