# Testing Modal Content with Styles

## Important: Correct Endpoints

The plugin creates its own REST endpoint, NOT the default WordPress REST API endpoint.

### ❌ WRONG Endpoint (Core WordPress API - No style support)
```
https://childrensliteracy.ddev.site/wp-json/wp/v2/team/331
```

### ✅ CORRECT Endpoint (Plugin API - With style support)
```
https://childrensliteracy.ddev.site/wp-json/pikari-gutenberg-modals/v1/modal-content/331
```

## Testing the Fix

1. **Test the new endpoint**:
   ```bash
   curl -X GET "https://childrensliteracy.ddev.site/wp-json/pikari-gutenberg-modals/v1/modal-content/331"
   ```

2. **Expected response structure**:
   ```json
   {
     "id": 331,
     "title": "Team Member Name",
     "content": "<!-- Rendered HTML -->",
     "styles": "/* CSS for block supports */",
     "type": "team"
   }
   ```

3. **Check for styles in the response**:
   - Look for the `styles` field
   - It should contain CSS rules for `.wp-container-*` classes
   - Block gaps, spacing, and other supports should be included

## How Modal Content is Loaded

The plugin renders modal content in two ways:

1. **Server-side rendering** (current implementation):
   - Content is rendered in PHP when the page loads
   - Modal HTML is output in the footer
   - Styles are captured during this server-side rendering

2. **REST API** (for AJAX loading):
   - Use the `/wp-json/pikari-gutenberg-modals/v1/modal-content/{id}` endpoint
   - Returns both content and styles
   - Can be used for dynamic loading if needed

## Debugging

If styles are still missing:

1. **Check if the post has blocks with custom spacing**:
   - Edit post ID 331
   - Add a Group block with custom gap settings
   - Add a Columns block with custom column gap
   - Save and test again

2. **Verify block rendering**:
   - The fix uses `do_blocks()` to ensure proper block processing
   - Captures styles from global `$wp_styles` object
   - Also captures any directly output style tags

3. **Clear caches**:
   - Clear any page caching
   - Clear browser cache
   - Try in incognito/private mode