=== Pikari Gutenberg Modals ===
Contributors: pikari
Tags: modal, popup, dialog, gutenberg, block, accessible
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: trunk
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful modal windows for the WordPress block editor. Create engaging content with smooth animations and accessible modal dialogs.

== Description ==

Create beautiful, accessible modal windows with this comprehensive WordPress Gutenberg block plugin. Perfect for showcasing content, images, forms, and improving user engagement.

**Key Features:**

* **Full Accessibility Support** - Complete ARIA attributes, keyboard navigation, and semantic HTML
* **Smooth Animations** - Elegant CSS transitions for modal open/close effects
* **WordPress Interactivity API** - Built on WordPress core Interactivity API for optimal performance
* **WordPress Integration** - Full support for core layout features (spacing, colors, borders)
* **Block Editor Native** - Seamlessly integrates with Gutenberg block editor
* **Progressive Enhancement** - Works with JavaScript disabled (fallback behavior)
* **Responsive Design** - Mobile-friendly modal windows

**Perfect for:**
* Image galleries and lightboxes
* Contact forms and lead generation
* Product showcases
* Video content
* Call-to-action overlays
* Any popup content needs

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/pikari-gutenberg-modals/`, or install through the WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. In the block editor, add the "Modal" block
4. Add content inside the Modal block
5. Customize settings in the block inspector

== Frequently Asked Questions ==

= Is this plugin fully accessible? =

Yes! We follow WCAG guidelines with proper ARIA attributes, keyboard navigation support, and semantic HTML structure. The plugin is compatible with screen readers and other assistive technologies.

= Does it work with my theme? =

Yes! The plugin uses WordPress core layout features and follows coding standards for broad theme compatibility. It respects your theme's typography and color schemes.

= What is the WordPress Interactivity API? =

The WordPress Interactivity API is a new framework introduced in WordPress 6.8 that provides a standard way to add interactive behavior to blocks. Our plugin uses this API for optimal performance and compatibility.

= Does it work without JavaScript? =

Yes! The plugin uses progressive enhancement, so it will provide fallback behavior when JavaScript is disabled, ensuring content remains accessible.

= Can I customize the modal appearance? =

Yes! The plugin respects WordPress core layout features including spacing, colors, and borders. You can customize the appearance through the block inspector.

== Developer Filters ==

The plugin provides several filters for developers to customize behavior:

= Cache & Performance =

**pikari_gutenberg_modals_cache_duration**
Customize the browser cache duration for modal content REST API responses.

`add_filter( 'pikari_gutenberg_modals_cache_duration', function( $duration ) {
    return 2 * HOUR_IN_SECONDS; // Cache for 2 hours instead of 1
} );`

Default: `HOUR_IN_SECONDS` (3600 seconds)

**pikari_gutenberg_modals_prefetch_urls**
Modify the REST API URLs to prefetch for modal content.

`add_filter( 'pikari_gutenberg_modals_prefetch_urls', function( $urls, $post_ids ) {
    // Add additional URLs or filter existing ones
    return $urls;
}, 10, 2 );`

= Content & Display =

**pikari_gutenberg_modals_supported_blocks**
Customize which block types support the modal link format.

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

**pikari_gutenberg_modals_post_content**
Filter the rendered post content before it's returned in the modal.

`add_filter( 'pikari_gutenberg_modals_post_content', function( $content, $post ) {
    return $content . '<p>Additional content</p>';
}, 10, 2 );`

**pikari_gutenberg_modals_url_content**
Filter content loaded from external URLs.

`add_filter( 'pikari_gutenberg_modals_url_content', function( $content, $url ) {
    // Modify external URL content
    return $content;
}, 10, 2 );`

= Search =

**pikari_gutenberg_modals_search_args**
Modify the WP_Query arguments for the modal content search endpoint.

`add_filter( 'pikari_gutenberg_modals_search_args', function( $args, $search_term ) {
    $args['post_type'] = array( 'post', 'page' ); // Limit to specific post types
    return $args;
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
Specify domains allowed for external URL content (allowlist).

`add_filter( 'pikari_gutenberg_modals_allowed_domains', function( $domains ) {
    return array( 'example.com', 'trusted-site.org' );
} );`

**pikari_gutenberg_modals_blocked_domains**
Specify domains blocked from external URL content (blocklist).

`add_filter( 'pikari_gutenberg_modals_blocked_domains', function( $domains ) {
    return array( 'untrusted-site.com' );
} );`

**pikari_gutenberg_modals_allowed_domains_list**
Filter the final computed allowed domains list.

**pikari_gutenberg_modals_blocked_domains_list**
Filter the final computed blocked domains list.

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

Override any of these in your theme's CSS to customize the modal appearance.

== Screenshots ==

1. Modal block in the editor with content
2. Live modal with smooth animations
3. Block settings panel with customization options
4. Accessible keyboard navigation in action

== Changelog ==

= 1.0.0 =
* Initial release
* Full modal functionality with accessibility features
* WordPress Interactivity API integration
* Smooth animation support
* Progressive enhancement with JavaScript fallback
* Block Editor native integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Pikari Gutenberg Modals plugin.