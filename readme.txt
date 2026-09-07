=== Pikari Gutenberg Modals ===
Contributors: pikari
Tags: modal, popup, dialog, gutenberg, block, accessible
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.4
Stable tag: trunk
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accessible modal dialogs for the WordPress block editor. Display posts, pages, and external content in overlays triggered by inline links, buttons, or clickable cards.

== Description ==

Pikari Gutenberg Modals adds accessible modal dialogs to the WordPress block editor. Content — posts, pages, custom post types, or external URLs — is displayed in overlays with smooth animations. Triggers are real links that work without JavaScript (progressive enhancement).

**Trigger Types:**

* **Inline Modal Triggers** — Apply the modal format to any text in paragraphs, headings, lists, quotes, and more (Cmd/Ctrl+M shortcut)
* **Modal Trigger Block** — Dedicated clickable card wrapper with auto-detected link, custom URL, or inline content modes
* **Close Triggers** — Modal Trigger block and inline triggers support a "Close modal" action for fully customizable close buttons

**Key Features:**

* **Full Accessibility** — ARIA attributes, focus trapping, keyboard navigation (Escape to close, Tab cycling), screen reader support
* **Template Part Customization** — Customize the modal dialog layout (close button, content area) via the Site Editor template part system
* **Multiple Modal Templates** — Create different modal designs and assign them per trigger
* **Overlay Styling** — Solid colors, gradients, or images with alpha transparency on the overlay backdrop
* **Dialog Chrome** — Background color, border, padding, and box shadow on the dialog container via block supports
* **Four Size Options** — Default, Small, Large, and Fullscreen (extendable via filter)
* **Smooth Animations** — CSS transitions with reduced-motion support
* **WordPress Interactivity API** — Built on the core Interactivity API for optimal performance
* **Progressive Enhancement** — Triggers render as real `<a>` tags that navigate without JavaScript
* **Hover Prefetch** — 200ms debounced prefetch warms the browser cache before click
* **Dynamic Block Styles** — Automatically loads stylesheets for blocks inside modal content
* **HTTP Caching** — REST API responses include ETag, Last-Modified, and Cache-Control headers

**Works With:**

* Block themes (full Site Editor support)
* Hybrid themes (file-based template rendering)

== Installation ==

1. Download the latest release or clone this repository
2. Upload the plugin files to `/wp-content/plugins/pikari-gutenberg-modals/`, or install through the WordPress plugins screen
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Use any of the three trigger types in the block editor to create modal triggers

= Block Themes =

The plugin registers a "Modal" template part area. Customize it in **Appearance → Editor → Patterns → Template Parts → Modal**. Create additional modal template parts in the "Modal" area for different designs.

= Hybrid Themes =

Modals work automatically with the plugin's default template. To customize:

1. Create `parts/modal.html` in your theme directory with block markup
2. For additional templates, use `parts/modal-{slug}.html` (e.g., `parts/modal-compact.html`)
3. Or use the `pikari_gutenberg_modals_fallback_template` filter for programmatic customization

= Composer =

`composer require pikari-inc/pikari-gutenberg-modals`

== Frequently Asked Questions ==

= Is this plugin fully accessible? =

Yes. The plugin follows WCAG guidelines with proper ARIA attributes, focus trapping, keyboard navigation (Escape to close, Tab to cycle through focusable elements), and screen reader support. Background content is marked inert when a modal is open.

= Does it work with hybrid themes? =

Yes. Block themes get full Site Editor template part support. Hybrid themes (classic PHP templates with block editor for content) render the modal dialog from theme files via `do_blocks()`. The Site Editor template part system is not used for hybrid themes. Theme authors can customize by placing `parts/modal.html` in their theme directory.

= How do I create different modal designs? =

**Block themes:** Create additional template parts in the "Modal" area via the Site Editor. Assign them to triggers using the "Modal Template" dropdown in the block inspector.

**Hybrid themes:** Create `parts/modal-{slug}.html` files in your theme. For example, `parts/modal-compact.html` appears as "Compact" in the template selector.

= What are the trigger types? =

1. **Inline Modal Triggers** — Select text, press Cmd/Ctrl+M (or use the toolbar button), and search for content to link
2. **Modal Trigger Block** — Add a Modal Trigger block, place any content inside, and the plugin detects the primary link (from buttons, images, headings, etc.) to make the whole card clickable. Also supports custom URL and inline content modes.
3. **Close Triggers** — Set the Modal Trigger block or inline trigger to "Close modal" action. Use inside modal template parts to create custom close buttons with full design flexibility.

= Does it work without JavaScript? =

Yes. All triggers render as standard `<a href="...">` links. Without JavaScript, clicking navigates to the linked post/page. With JavaScript, the content loads in a modal overlay instead.

= Can I load external URLs in modals? =

Yes. External URLs are loaded in a sandboxed iframe inside the modal. Set the trigger URL to an external page and it will display within the modal dialog. Note that some sites block being embedded in iframes via X-Frame-Options or Content-Security-Policy headers — those sites will show a blank frame. Use the domain allowlist/blocklist filters to control which external domains are permitted.

== Developer Filters ==

= Content & Display =

**pikari_gutenberg_modals_supported_blocks**
Customize which block types support the inline modal trigger format.

`add_filter( 'pikari_gutenberg_modals_supported_blocks', function( $blocks ) {
    $blocks[] = 'my-plugin/custom-block';
    return $blocks;
} );`

Default blocks: `core/paragraph`, `core/heading`, `core/list`, `core/list-item`, `core/quote`, `core/verse`, `core/preformatted`, `core/navigation-link`

**pikari_gutenberg_modals_content_response**
Modify the REST API response for modal content.

`add_filter( 'pikari_gutenberg_modals_content_response', function( $response_data, $post ) {
    $response_data['custom_field'] = get_post_meta( $post->ID, 'my_custom_field', true );
    return $response_data;
}, 10, 2 );`

= Cache & Performance =

**pikari_gutenberg_modals_cache_duration**
Customize the browser cache duration for modal content REST API responses.

`add_filter( 'pikari_gutenberg_modals_cache_duration', function( $duration ) {
    return 2 * HOUR_IN_SECONDS; // Cache for 2 hours instead of 1
} );`

Default: `HOUR_IN_SECONDS` (3600 seconds)

**pikari_gutenberg_modals_enable_prefetch_hints**
Enable automatic `<link rel="prefetch">` resource hints in the document head for modal content URLs detected on the page.

`add_filter( 'pikari_gutenberg_modals_enable_prefetch_hints', '__return_true' );`

Default: `false` (hover-based prefetch still works regardless)

**pikari_gutenberg_modals_prefetch_urls**
Modify the REST API URLs included in prefetch resource hints.

`add_filter( 'pikari_gutenberg_modals_prefetch_urls', function( $urls, $post_ids ) {
    // Add additional URLs or filter existing ones
    return $urls;
}, 10, 2 );`

= Editor =

**pikari_gutenberg_modals_modal_sizes**
Add or modify the modal size options available in the editor dropdown. Each entry needs a `label` (display text) and `value` (slug used as the `data-size` attribute). Custom sizes require matching CSS.

`add_filter( 'pikari_gutenberg_modals_modal_sizes', function( $sizes ) {
    $sizes[] = array(
        'label' => __( 'Medium', 'my-theme' ),
        'value' => 'medium',
    );
    return $sizes;
} );`

Then add the CSS for your custom size:

`.modal-overlay[data-size="medium"] .modal-content {
    max-width: 768px;
}`

Default sizes: Default (empty), Small (500px), Large (1200px), Fullscreen (100%)

= Domain Restrictions =

**pikari_gutenberg_modals_allowed_domains**
Restrict which domains are allowed for external URL content (allowlist). By default, all domains are allowed (the blocklist and private IP checks still apply). When this filter returns a non-empty array, only those domains are permitted.

`add_filter( 'pikari_gutenberg_modals_allowed_domains', function( $domains ) {
    return array( 'example.com', 'trusted-site.org' );
} );`

**pikari_gutenberg_modals_blocked_domains**
Specify domains blocked from external URL content (blocklist).

`add_filter( 'pikari_gutenberg_modals_blocked_domains', function( $domains ) {
    return array( 'untrusted-site.com' );
} );`

= Theme Customization =

**pikari_gutenberg_modals_fallback_template**
Override the modal template markup for hybrid (non-block) themes. Return block markup to bypass the default theme file and plugin template resolution.

`add_filter( 'pikari_gutenberg_modals_fallback_template', function( $content, $slug ) {
    if ( 'compact' === $slug ) {
        return '<!-- wp:pikari-gutenberg-modals/modal-dialog -->...<!-- /wp:pikari-gutenberg-modals/modal-dialog -->';
    }
    return $content;
}, 10, 2 );`

== CSS Custom Properties ==

The plugin exposes CSS custom properties on `:root` for easy theming without filters or PHP:

`/* Modal appearance */
--modal-overlay-bg: rgba(0, 0, 0, 0.8);
--modal-content-bg: #fff;
--modal-content-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
--modal-border-radius: 20px;

/* Modal widths */
--modal-max-width: 1024px;          /* Default size */
--modal-max-width-small: 500px;     /* Small size */
--modal-max-width-large: 1200px;    /* Large size */

/* Interaction */
--modal-focus-color: #3b82f6;
--modal-transition: all 0.2s ease;`

Override any of these in your theme's CSS to customize the modal appearance:

`/* In your theme's style.css or editor styles */
:root {
    --modal-border-radius: 8px;
    --modal-max-width: 800px;
    --modal-content-bg: #f9f9f9;
}`

== Screenshots ==

1. Inline modal trigger in the editor with link picker
2. Button block with "Open in Modal" toggle
3. Clickable group card pattern
4. Live modal with smooth animations
5. Template part customization in the Site Editor

== Changelog ==

= Unreleased =
* Overlay opacity control on the Modal Dialog block, set independently of the overlay colour so a theme that disables custom colours can still produce a translucent backdrop
* Fixed prefers-reduced-motion having no effect: the override named class names the modal never applies, so animations still ran for users who had asked for reduced motion

= 1.3.0 =
* Close-mode triggers: Modal Trigger block and inline triggers now support a "Close modal" action
* Modal Trigger block close mode with whole-wrapper and targeted child element options
* Inline close triggers inside modal template parts
* Automatic sr-only fallback close button when no close trigger detected in modal dialog
* Block context restrictions: Close Button and Content Area restricted to Modal Dialog; Modal Content and Modal Trigger hidden in Site Editor
* Video URLs from YouTube and Vimeo now hold a 16:9 box in the modal instead of stretching to the dialog height
* Modal Trigger URL mode: optional Accessible label field, overriding the generic "Open modal dialog"
* Default modal template now uses Modal Trigger block (close mode) instead of the close-button block
* Close Button block deprecated (hidden from inserter, replaced by Modal Trigger close mode)
* Fixed theme per-block styles and layout CSS missing from modal content
* Fixed close-mode element selection not persisting across page refreshes
* Fixed buttons with URLs not appearing in close trigger element dropdown
* Fixed hybrid themes seeing phantom modal template parts in the admin

= 1.2.2 =
* Fixed hybrid themes seeing a non-functional modal template part in the admin

= 1.2.1 =
* External URL support: external URLs now load in a sandboxed iframe inside the modal dialog
* Fixed external URLs being blocked by default domain allowlist (now defaults to allowing all domains)
* Fixed external URL triggers failing with REST API 404 errors
* Prefetch skipped for external URL triggers (cross-origin iframe content cannot be prefetched)

= 1.2.0 =
* New Modal Trigger block — dedicated clickable card wrapper with three content source modes (detected link, custom URL, page content)
* Bidirectional transforms between Modal Trigger block and core/group
* Renamed "Modal Link" to "Modal Trigger" for consistent terminology across all trigger types
* Unified block icons across all modal blocks
* SVG icon indicator replaces Unicode character for inline triggers in the editor
* Removed unused search REST endpoint
* Improved modal-content REST endpoint with schema and argument validation
* Fixed dynamic REST URL for plain permalink compatibility

= 1.1.0 =
* Hybrid theme support: modals render via `do_blocks()` fallback when `block_template_part()` is unavailable
* Theme file overrides for hybrid themes (`parts/modal.html`, `parts/modal-{slug}.html`)
* `pikari_gutenberg_modals_fallback_template` filter for programmatic template customization
* Close button uses absolute positioning to overlap content instead of taking a flex row
* Close button alignment (right/center/left) now maps to position offsets
* SVG close icon for consistent rendering across theme fonts
* Dialog content scrolls when taller than the viewport (flex chain fix)
* Webpack fix: block CSS files now copied to build directory
* Default padding persists correctly (block supports override workaround)

= 1.0.0 =
* Three trigger types: inline modal triggers, button block modals, clickable group cards
* Template part system for customizable modal dialog layout
* Multiple modal template support with per-trigger assignment
* Overlay styling: solid colors, gradients, and images with alpha transparency
* Dialog chrome: background, border, padding, and box shadow via block supports
* Four built-in sizes: Default, Small, Large, Fullscreen
* Hybrid theme support with automatic `do_blocks()` fallback rendering
* Theme file overrides for hybrid themes (`parts/modal.html`, `parts/modal-{slug}.html`)
* `pikari_gutenberg_modals_fallback_template` filter for programmatic template customization
* Hover-based content prefetch with 200ms debounce
* Dynamic block stylesheet loading for modal content
* REST API with HTTP caching (ETag, Last-Modified, Cache-Control)
* Full WCAG accessibility: focus trap, keyboard navigation, ARIA attributes, inert background
* Progressive enhancement: triggers are real links that work without JavaScript
* CSS custom properties for easy theming
* Domain allowlist/blocklist for external URL content
* 12 developer filters for deep customization

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Pikari Gutenberg Modals plugin.
