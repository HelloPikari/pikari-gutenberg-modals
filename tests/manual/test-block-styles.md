# Testing Block Support Styles in Modals

## Test Procedure

### 1. Create Test Content with Block Supports

Create a new post/page with the following blocks to test various block supports:

```
<!-- wp:group {"style":{"spacing":{"blockGap":"3rem","padding":{"top":"2rem","bottom":"2rem","left":"1rem","right":"1rem"}}},"backgroundColor":"pale-cyan-blue"} -->
<div class="wp-block-group has-pale-cyan-blue-background-color has-background" style="padding-top:2rem;padding-right:1rem;padding-bottom:2rem;padding-left:1rem">
<!-- wp:heading -->
<h2>Test Heading with Gap</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This group block has custom gap spacing (3rem) and padding.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:columns {"style":{"spacing":{"blockGap":"2rem"}}} -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:paragraph -->
<p>Column 1 with custom gap</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:paragraph -->
<p>Column 2 with custom gap</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->

<!-- wp:buttons {"style":{"spacing":{"blockGap":"1.5rem"}}} -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Button 1</a></div>
<!-- /wp:button -->

<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Button 2</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
```

### 2. Verify Block Support Classes

1. Open the browser developer tools
2. Inspect the blocks on the frontend
3. Look for namespaced classes like:
   - `.wp-container-core-group-is-layout-[hash]`
   - `.wp-container-core-columns-is-layout-[hash]`
   - `.wp-container-core-buttons-is-layout-[hash]`

### 3. Check Modal Content

1. Create a modal link to the test post
2. Open the modal
3. In developer tools, verify:
   - The same namespaced classes exist on blocks in the modal
   - A `<style>` tag with id `modal-[post-id]-block-supports` is present
   - The style tag contains CSS rules for the namespaced classes
   - Block gaps, padding, and margins display correctly

### 4. Test REST Endpoint

Test the REST endpoint directly:

```bash
# Replace {post_id} with your test post ID
curl -X GET "https://your-site.com/wp-json/pikari-gutenberg-modals/v1/modal-content/{post_id}"
```

Expected response structure:
```json
{
  "id": 123,
  "title": "Test Post",
  "content": "<!-- Rendered HTML with blocks -->",
  "styles": "/* CSS for block supports */",
  "type": "post"
}
```

### 5. Common Issues to Check

1. **Missing Styles**: If styles are empty, ensure:
   - The post has blocks with block supports (gap, spacing, etc.)
   - WordPress is properly rendering blocks (not just returning raw HTML)

2. **Style Conflicts**: If modal styles affect the main page:
   - Check that styles are properly scoped to modal content
   - Verify no global CSS rules are being captured

3. **Performance**: For posts with many blocks:
   - Monitor load time of modal content
   - Check if styles are being duplicated unnecessarily

## Expected Results

✅ Block gaps display correctly in modals
✅ Custom spacing (padding/margin) is preserved
✅ Typography settings apply correctly
✅ Color settings work as expected
✅ No visual differences between modal and main content
✅ REST endpoint returns both content and styles