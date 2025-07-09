# WordPress Plugin Debugging Issue

## Issue Summary
**Plugin Name:** Pikari Gutenberg Modals  
**Repository:** https://github.com/HelloPikari/pikari-gutenberg-modals  
**Branch/Commit:** main (v0.1.0-alpha)  
**Issue Type:** Feature Not Working  
**Severity:** High  
**First Noticed:** July 4, 2025

**Brief Description:**
The content that gets loaded into the modal window from a REST endpoint doesn't include the gutenberg core-block-supports-inline-css. This means that styling is missing such as block gaps. The REST API returns HTML with namespaced classes but without the corresponding dynamically generated CSS rules.

## Environment Details

### WordPress Environment
- **WordPress Version:** 6.8.1
- **PHP Version:** 8.4

### Plugin Information
- **Plugin Version:** v0.1.0-alpha
- **Plugin Folder Name:** pikari-gutenberg-modals
- **Main Plugin File:** pikari-gutenberg-modals.php
- **GitHub Repository:** https://github.com/HelloPikari/pikari-gutenberg-modals

## Issue Details

### Expected Behavior
When content is loaded into the modal window, all Gutenberg block styling should be preserved, including:
- Block gap spacing (defined by gap support) with proper namespaced CSS
- Each block instance should have its unique classes (e.g., `.wp-container-core-columns-is-layout-5b614432`)
- Corresponding CSS rules for those classes should be injected
- Padding and margin from spacing supports
- Typography settings
- Color settings
- Any other core block support styles

The modal should display content identically to how it appears in the main content area, with all dynamically generated styles intact.

### Actual Behavior
Content loaded via REST endpoint into the modal window is missing styles from `core-block-supports-inline-css`. These styles are usually injected when a block is used and are namespaced to the block (e.g., `.wp-container-core-columns-is-layout-5b614432`), resulting in:
- Missing block gaps between elements
- Incorrect spacing
- Potentially missing other block support styles (padding, margin, typography)
- Namespaced CSS classes exist on blocks but corresponding styles are not injected
- REST response contains HTML with classes but no accompanying CSS
- Content appears differently in modal vs. main content area

### Steps to Reproduce
1. Create a post/page with Gutenberg blocks that use block supports (e.g., Group blocks with gap settings)
2. Set specific gap values or other block support styles
3. Inspect the block in the editor/frontend to note the namespaced classes (e.g., `wp-container-core-columns-is-layout-5b614432`)
4. Check the REST endpoint response directly (e.g., `/wp-json/pikari/v1/modal-content/{id}`)
   - Verify HTML contains namespaced classes
   - Confirm no inline CSS is included in response
5. Trigger the modal to display this content
6. Inspect the modal content to verify:
   - Namespaced classes are present on blocks
   - Corresponding CSS rules for those classes are missing
7. Compare the same content outside the modal to see the styling difference

#### Browser Inspector Checks
- Look for missing `<style id="core-block-supports-inline-css">` element in modal
- Check if namespaced classes like `.wp-container-core-columns-is-layout-*` exist on blocks
- Verify if corresponding CSS rules for those namespaced classes are present
- Compare class names on blocks in modal vs. main content
- Check if style tag exists but is empty or missing modal-specific rules

## Gutenberg/Block Editor Specific Details

### Block Registration
- **Modal Block Name:** pikari/modal
- **Affected Core Blocks Inside Modal:** 
  - core/group (gap, padding, layout)
  - core/columns (gap between columns)
  - core/buttons (gap between buttons)
  - core/navigation (spacing)
  - Any block using layout/spacing supports
- **Missing Style Pattern:** `.wp-container-core-[block]-is-layout-[hash]`
- **Theme.json Impact:** Custom spacing scale values not applying

### Modal Behavior
- **Modal Trigger:** [How the modal is triggered - button click, automatic, etc.]
- **Modal Type:** [Inline/Overlay/Sidebar]
- **Content Loading Method:** REST API endpoint
- **REST Endpoint:** [e.g., /wp-json/pikari/v1/modal-content]
- **Style Loading:** Missing core-block-supports-inline-css
- **Z-index Issues:** [Yes/No - Common with modals]
- **Focus Trap Working:** [Yes/No]
- **Escape Key Handling:** [Yes/No]

### React/JavaScript Details
- **React DevTools Installed:** [Yes/No]
- **Component State at Error:** [If applicable]
- **REST API Call Method:** [fetch/wp.apiFetch/axios]
- **Response Handling:** Content displayed but styles missing
- **Style Injection Method:** [How are returned styles applied, if any]

## Relevant Code

### Plugin File Structure
```
pikari-gutenberg-modals/
├── pikari-gutenberg-modals.php
├── build/
│   ├── index.js
│   ├── index.asset.php
│   └── style.css
├── src/
│   ├── index.js
│   ├── editor.scss
│   ├── style.scss
│   └── components/
│       └── modal/
├── includes/
│   └── [PHP files]
├── assets/
│   ├── css/
│   └── js/
├── package.json
└── webpack.config.js
```

### Problematic Code Section
**File:** [Likely in REST endpoint registration or callback function]  
**Line Numbers:** [Where REST endpoint prepares modal content]  
**GitHub Reference:** [Link to specific file/line in repo, e.g., https://github.com/HelloPikari/pikari-gutenberg-modals/blob/main/includes/rest-api.php]

```php
// Example areas to investigate in REST endpoint:
// 1. How post content is retrieved and processed
// 2. Whether content goes through block rendering
// 3. If using get_post()->post_content vs. apply_filters('the_content')
// 4. REST response structure and fields
// 5. Any style collection mechanisms

// Look for code patterns like:
// register_rest_route('pikari/v1', '/modal-content/(?P<id>\d+)', array(
//     'callback' => function($request) {
//         $post = get_post($request['id']);
//         return $post->post_content; // Missing block rendering!
//     }
// ));
```

### Related Database Tables
**Table Name:** wp_posts (for content retrieval via REST)
- REST endpoint fetches post_content
- Content includes block markup with attributes
- Note: Block support styles are NOT stored in DB, they're generated at render time
- REST endpoint must process blocks to generate styles dynamically

## Debugging Attempts

### What Has Been Tried
1. [Checked if styles are present in page source but not in modal]
2. [Verified if manual inclusion of block CSS files helps]
3. [Tested with different themes to rule out theme conflicts]
4. [Checked existing GitHub issues: https://github.com/HelloPikari/pikari-gutenberg-modals/issues]

### Debug Mode Status
- **WP_DEBUG:** [Enabled/Disabled]
- **WP_DEBUG_LOG:** [Enabled/Disabled]
- **WP_DEBUG_DISPLAY:** [Enabled/Disabled]
- **SCRIPT_DEBUG:** [Enabled/Disabled]

## Additional Context

### Technical Background
The `core-block-supports-inline-css` is dynamically generated by WordPress to handle block support features. Key characteristics:
- **Dynamic Generation**: Styles are injected only when specific blocks are rendered
- **Namespaced Classes**: Each block instance gets unique classes (e.g., `.wp-container-core-columns-is-layout-5b614432`)
- **Block-Specific**: CSS is generated based on individual block attributes and settings
- **Runtime Injection**: Styles are added during the block rendering process

This CSS handles:
- Gap (block spacing) with custom values
- Padding/Margin per block instance
- Typography settings
- Color configurations
- Layout (flex, grid, etc.)

When blocks are rendered normally, WordPress:
1. Processes block attributes
2. Generates unique class names
3. Creates corresponding CSS rules
4. Injects styles via `wp_add_inline_style()`

**REST API Context**: When content is served via REST endpoints, the block rendering pipeline may be bypassed, resulting in HTML with classes but without the corresponding dynamically generated CSS rules.

### Possible Root Causes
1. Modal content loaded from REST endpoint returns raw block HTML without processed styles
2. REST API response doesn't include dynamically generated block support CSS
3. Block support style generation not occurring in REST context
4. Namespaced CSS classes applied to blocks but corresponding styles not collected in API response
5. Missing render_block filters when content is prepared for REST endpoint
6. REST endpoint may be using raw post_content instead of rendered blocks
7. No mechanism to capture and return inline styles alongside REST response

## Specific Questions for Debugging

1. How is the REST endpoint preparing content (using `get_post_field('post_content')` or `apply_filters('the_content')`)?
2. Is the REST endpoint using `do_blocks()` or `render_block()` on the content?
3. What does the REST response structure look like (just HTML or includes styles)?
4. Are the namespaced block classes (e.g., `wp-container-*`) present in the REST response?
5. Could the endpoint collect and return generated styles alongside content?
6. Would using the REST API's `context=view` parameter with proper block rendering help?
7. Is the endpoint custom or extending core REST API functionality?

## Desired Outcome

### Primary Goal
Enable full Gutenberg block support styling within modal windows, ensuring visual consistency between modal and regular content display.

### Success Criteria
- [ ] REST endpoint returns both content HTML and associated block support CSS
- [ ] Block-specific namespaced styles (e.g., `.wp-container-*`) load correctly in modals
- [ ] All dynamically generated block support CSS is captured and included in API response
- [ ] Block gaps display with correct spacing values
- [ ] All block support features (spacing, typography, colors) work in modals
- [ ] No visual differences between content in modal vs. main area
- [ ] Solution works with REST-loaded content without duplicate styles

### Constraints
- Must maintain modal performance
- Should not duplicate styles unnecessarily
- Must work with all Gutenberg core blocks
- Should be compatible with custom blocks using block supports

---

## Instructions for Claude

Please analyze this WordPress plugin issue and provide:

1. **Root Cause Analysis**: Why REST endpoints don't include dynamically generated block support styles
2. **Solution Approach**: How to modify the REST endpoint to include block support CSS
3. **Code Fix**: Provide specific code for the REST endpoint that:
   - Properly renders blocks with `do_blocks()` or `apply_filters('the_content')`
   - Captures generated inline styles
   - Returns both content and styles in the response
4. **Implementation Guide**: Step-by-step instructions for implementing the fix
5. **Testing Plan**: How to verify all block supports work correctly via REST endpoint

### REST Endpoint Specific Considerations:
- How to capture styles generated during block rendering
- Methods to extract inline CSS from the rendering process
- Structuring REST response to include both content and styles
- Using output buffering to capture styles if needed
- Ensuring proper WordPress environment setup in REST context

### Gutenberg-Specific Considerations:
- How WordPress generates namespaced styles during block rendering
- The render_block filter pipeline and style generation
- Methods to capture dynamically generated inline CSS in REST responses
- Ensuring blocks are processed through proper rendering context in REST endpoints
- Collecting and returning block-specific namespaced styles via API
- Alternative approaches:
  - Using `apply_filters('the_content')` in REST endpoint
  - Implementing custom style collection during block rendering
  - Returning styles as separate field in REST response
  - Using WP_REST_Response to include inline styles

### Repository Reference:
- Review the source code at: https://github.com/HelloPikari/pikari-gutenberg-modals
- Check REST endpoint registration and callback implementation
- Look for how content is prepared in the API response
- Verify if any block rendering is currently happening
- Check if styles are being handled in any way

If you need any additional information or code sections to properly diagnose the issue, please specify what would be helpful.