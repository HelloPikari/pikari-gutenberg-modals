# Sticky Column Implementation for Modals

## Overview
This document outlines various approaches for implementing a sticky/fixed left column (image) in modal layouts, where only the right column content scrolls.

## Problem Analysis

### Current Limitations

1. **WordPress Position Support**:
   - Only Group blocks natively support sticky positioning (not columns/column blocks)
   - Sticky position only works at root level (not nested blocks)
   - Position:sticky adheres to parent element, which in modals is the scrolling container

2. **Modal Structure Constraints**:
   - `.modal-body` is the scrolling container with `overflow-y: auto`
   - Content is wrapped in multiple containers (article, modal-entry-content)
   - Standard sticky positioning won't work as expected within this structure

3. **Block Editor Constraints**:
   - Column blocks don't have position support in their block.json
   - Nested Group blocks don't show sticky option in WordPress 6.2+

## Implementation Options

### Option 1: CSS-Based Solution (Simplest)

Add custom CSS classes that users can apply to achieve the sticky effect.

#### Implementation:

```scss
// Add to src/frontend/style.scss
.modal-body {
  // Sticky left column pattern
  .wp-block-columns.has-sticky-left-column {
    align-items: flex-start;
    min-height: 100%;
    
    // First column (image) stays sticky
    .wp-block-column:first-child {
      position: sticky;
      top: 0;
      align-self: flex-start;
      
      // Ensure image doesn't overflow
      img {
        max-height: calc(100vh - 4rem); // Account for modal padding
        object-fit: contain;
      }
    }
    
    // Second column scrolls normally
    .wp-block-column:nth-child(2) {
      // Ensure content can scroll
      min-height: 100%;
    }
  }
  
  // Mobile: disable sticky on small screens
  @media (max-width: 768px) {
    .wp-block-columns.has-sticky-left-column {
      .wp-block-column:first-child {
        position: static;
        
        img {
          max-height: none;
        }
      }
    }
  }
}
```

#### Usage:
1. User adds a Columns block
2. In Block settings → Advanced → Additional CSS Classes
3. Add: `has-sticky-left-column`

#### Pros:
- Simple implementation
- No JavaScript required
- Works with existing blocks
- Mobile responsive

#### Cons:
- Requires manual class addition
- Users need to know the class name
- Not discoverable in UI

### Option 2: Custom Block Pattern

Create a pre-configured block pattern for modal profile layouts.

#### Implementation:

```php
// Add to plugin.php or separate patterns file
function register_modal_profile_pattern() {
    register_block_pattern(
        'pikari-gutenberg-modals/modal-profile-layout',
        array(
            'title'       => __('Modal Profile Layout', 'pikari-gutenberg-modals'),
            'description' => __('A two-column layout with sticky image on left for modal displays', 'pikari-gutenberg-modals'),
            'categories'  => array('layout'),
            'content'     => '<!-- wp:columns {"className":"has-sticky-left-column","verticalAlignment":"top"} -->
<div class="wp-block-columns are-vertically-aligned-top has-sticky-left-column">
    <!-- wp:column {"width":"240px","verticalAlignment":"top"} -->
    <div class="wp-block-column is-vertically-aligned-top" style="flex-basis:240px">
        <!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
        <figure class="wp-block-image size-full">
            <img src="" alt=""/>
        </figure>
        <!-- /wp:image -->
    </div>
    <!-- /wp:column -->
    
    <!-- wp:column {"verticalAlignment":"top"} -->
    <div class="wp-block-column is-vertically-aligned-top">
        <!-- wp:paragraph {"placeholder":"Add your content here..."} -->
        <p></p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
</div>
<!-- /wp:columns -->',
        )
    );
}
add_action('init', 'register_modal_profile_pattern');
```

#### Pros:
- One-click insertion
- Pre-configured with correct classes
- Discoverable in pattern library
- Consistent structure

#### Cons:
- Still requires CSS from Option 1
- Pattern needs maintenance
- May not fit all use cases

### Option 3: Alternative Layout Structure

Use Group blocks instead of Columns for more control.

#### Block Structure:
```
Group (flex, horizontal, custom class: modal-profile-layout)
├── Group (sticky wrapper, width: 240px)
│   └── Image
└── Group (content wrapper, flex: 1)
    └── Content blocks
```

#### CSS Implementation:

```scss
.modal-body {
  // Custom profile layout using groups
  .wp-block-group.modal-profile-layout {
    display: flex;
    gap: var(--wp--preset--spacing--lg, 2rem);
    align-items: flex-start;
    min-height: 100%;
    
    // First group (image container)
    > .wp-block-group:first-child {
      position: sticky;
      top: 0;
      flex-shrink: 0;
      width: 240px;
      align-self: flex-start;
      
      img {
        width: 100%;
        height: auto;
        max-height: calc(100vh - 4rem);
        object-fit: contain;
      }
    }
    
    // Second group (content)
    > .wp-block-group:nth-child(2) {
      flex: 1;
      min-width: 0; // Prevent flex item overflow
    }
  }
}
```

#### Pros:
- More flexible than columns
- Better control over spacing
- Can use Group block's native features
- Works with theme spacing presets

#### Cons:
- More complex block structure
- Requires understanding of flex layouts
- Not as intuitive as columns

### Option 4: Automatic JavaScript Enhancement

Detect specific patterns and apply sticky behavior automatically.

#### Implementation:

```javascript
// Add to src/frontend/index.js
function enhanceModalLayouts() {
  // Auto-detect profile-style layouts
  const detectProfileLayout = (modalContent) => {
    const columns = modalContent.querySelector('.wp-block-columns');
    if (!columns) return;
    
    const firstColumn = columns.querySelector('.wp-block-column:first-child');
    const hasImage = firstColumn?.querySelector('img, .wp-block-post-featured-image');
    
    // If first column has only an image, make it sticky
    if (hasImage && firstColumn.children.length === 1) {
      columns.classList.add('auto-sticky-left');
    }
  };
  
  // Apply to modals when they open
  document.addEventListener('modal-content-loaded', (event) => {
    detectProfileLayout(event.detail.container);
  });
}

// CSS for auto-applied class
```

```scss
.modal-body {
  .wp-block-columns.auto-sticky-left {
    // Same styles as has-sticky-left-column
  }
}
```

#### Pros:
- No user action required
- Works automatically
- Smart detection
- Backward compatible

#### Cons:
- May apply unexpectedly
- Harder to debug
- Requires JavaScript
- Could conflict with user intentions

## Recommended Implementation

### Phase 1: CSS Support (Option 1)
1. Add CSS classes for sticky columns
2. Document in plugin readme
3. Test across browsers and devices

### Phase 2: Block Pattern (Option 2)
1. Create "Modal Profile Layout" pattern
2. Include in pattern library
3. Add to documentation

### Phase 3: Consider Automation (Option 4)
1. Monitor usage patterns
2. If commonly used, add auto-detection
3. Provide option to disable

## Additional Considerations

### Mobile Responsiveness
```scss
@media (max-width: 768px) {
  // Stack columns vertically on mobile
  .has-sticky-left-column {
    flex-direction: column !important;
    
    .wp-block-column:first-child {
      position: static !important;
      width: 100% !important;
      max-width: 100% !important;
    }
  }
}
```

### Accessibility
- Ensure keyboard navigation works properly
- Test with screen readers
- Maintain proper heading hierarchy
- Consider focus management

### Performance
- Use `will-change: transform` for smooth scrolling
- Consider `contain: layout` for performance
- Test with large images
- Monitor repaints during scroll

### Browser Support
- `position: sticky` works in all modern browsers
- IE11 doesn't support sticky (fallback to static)
- Test in Safari for any quirks
- Consider polyfill if needed

## Testing Checklist

- [ ] Test with various image sizes
- [ ] Test with short content (less than viewport)
- [ ] Test with long content (multiple viewports)
- [ ] Test on mobile devices
- [ ] Test with different modal sizes
- [ ] Test keyboard navigation
- [ ] Test with screen readers
- [ ] Test in all major browsers
- [ ] Test with RTL languages

## Future Enhancements

1. **Animation Options**
   - Fade in sticky image
   - Parallax effects
   - Smooth transitions

2. **Layout Variations**
   - Right-side sticky
   - Multiple sticky columns
   - Sticky header + image

3. **User Controls**
   - Toggle sticky on/off
   - Adjust sticky offset
   - Custom breakpoints

4. **Integration**
   - Block editor preview
   - Custom block controls
   - Theme.json support