<?php
/**
 * Group Modal Trigger Support
 *
 * Handles server-side rendering for modal trigger functionality on core/group
 * blocks, reading the unified pikariModal* attributes (see trigger-blocks.js).
 * This is the current mechanism for group-based modal triggers, replacing
 * the retired Modal Trigger wrapper block.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class GroupModalTriggerSupport
{
    /**
     * Post-link block types that can link to the current post in Query Loop.
     *
     * @var array
     */
    private const POST_LINK_BLOCKS = [
        'core/post-title',
        'core/post-featured-image',
        'core/post-date',
        'core/read-more',
        'core/post-excerpt',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('render_block_core/group', [$this, 'filter_group_block'], 10, 2);

        // Register post-link block filters for Phase 1 marking
        foreach ( self::POST_LINK_BLOCKS as $block_name ) {
            add_filter( "render_block_{$block_name}", [ $this, 'filter_post_link_block' ], 10, 3 );
        }
    }

    /**
     * Phase 1: Mark post-link blocks with temporary attributes.
     *
     * When a post-link block renders inside a Query Loop context, this marks
     * its anchor tag with temporary attributes that Phase 2 will use to identify it.
     *
     * @param string    $block_content  The block content HTML.
     * @param array     $parsed_block   The parsed block data.
     * @param \WP_Block $block_instance The block instance with context.
     * @return string Modified block content.
     */
    public function filter_post_link_block(
        string $block_content,
        array $parsed_block,
        \WP_Block $block_instance
    ): string {
        // Get post ID from block context (provided by Query Loop)
        $post_id = $block_instance->context['postId'] ?? null;

        if ( ! $post_id ) {
            return $block_content;
        }

        // Mark the first anchor tag with temporary attributes
        $processor = new \WP_HTML_Tag_Processor( $block_content );

        if ( $processor->next_tag( 'a' ) ) {
            $processor->add_class( 'pikari-post-link-candidate' );
            $processor->set_attribute( 'data-pikari-post-id', (string) $post_id );
            $processor->set_attribute( 'data-pikari-block-name', $parsed_block['blockName'] );
        }

        return $processor->get_updated_html();
    }

    /**
     * Filter group block to add modal trigger functionality.
     *
     * When a group has pikariModalAction set to 'open' and a valid primary
     * link selected, this method:
     * 1. Adds modal trigger CSS class to the group wrapper
     * 2. Finds the primary link anchor and adds Interactivity API attributes
     *
     * @param string $block_content The block content HTML.
     * @param array  $block         The block data array.
     * @return string Modified block content.
     */
    public function filter_group_block( string $block_content, array $block ): string
    {
        // Check if the group is configured to open a modal
        $is_open_trigger = ( $block['attrs']['pikariModalAction'] ?? '' ) === 'open';

        if ( ! $is_open_trigger ) {
            // Don't clean up markers here - a parent group may need them.
            // Markers will be cleaned up by the parent group that uses them,
            // or left in place (harmless) if no parent group needs them.
            return $block_content;
        }

        // Check content source: 'inline' for page content, 'url' for a custom
        // URL, 'none' for a modal whose template part is the content, 'link'
        // (default) for a link detected inside the group.
        $content_source = $block['attrs']['pikariModalContentSource'] ?? 'link';
        $template_part  = $block['attrs']['pikariModalTemplatePart'] ?? '';

        if ( $content_source === 'none' ) {
            return $this->handle_template_only( $block_content, $block, $template_part );
        }

        if ( $content_source === 'inline' ) {
            return $this->handle_inline_content( $block_content, $block, $template_part );
        }

        if ( $content_source === 'url' ) {
            return $this->handle_direct_url( $block_content, $block, $template_part );
        }

        // Get the selected primary link identifier JSON
        $primary_link_json = $block['attrs']['pikariModalPrimaryLinkId'] ?? '';

        if ( empty( $primary_link_json ) ) {
            return self::cleanup_post_link_markers( $block_content );
        }

        // Parse the JSON identifier
        $link_identifier = json_decode( $primary_link_json, true );

        if ( ! $link_identifier ) {
            return self::cleanup_post_link_markers( $block_content );
        }

        // Determine matching strategy based on link type
        $is_post_link = isset( $link_identifier['linkType'] ) && $link_identifier['linkType'] === 'post-link';

        if ( $is_post_link ) {
            return $this->handle_post_link_block( $block_content, $block, $link_identifier, $template_part );
        }

        // URL-based matching (existing behavior)
        return $this->handle_url_based_link( $block_content, $block, $link_identifier, $template_part );
    }

    /**
     * Handle URL-based link matching (original implementation).
     *
     * @param string $block_content   The block content HTML.
     * @param array  $block           The block data array.
     * @param array  $link_identifier The link identifier from block attributes.
     * @param string $template_part   Template part slug (empty for default 'modal').
     * @return string Modified block content.
     */
    private function handle_url_based_link( string $block_content, array $block, array $link_identifier, string $template_part = '' ): string
    {
        if ( empty( $link_identifier['linkUrl'] ) ) {
            return self::cleanup_post_link_markers( $block_content );
        }

        // Sanitize the URL from block attributes
        $target_url = esc_url_raw( $link_identifier['linkUrl'] );

        // Find the anchor with the matching href using WP_HTML_Tag_Processor
        $processor          = new \WP_HTML_Tag_Processor( $block_content );
        $found_primary_link = false;
        $trigger_id         = '';
        $modal_id           = '';
        $content_id         = '';

        while ( $processor->next_tag( 'a' ) ) {
            $href = $processor->get_attribute( 'href' );

            if ( $href === $target_url ) {
                // Found the primary link - add modal trigger classes and attributes
                $found_primary_link = true;

                // Mark that we have modal triggers on this page (tells BlockSupport to render container)
                $slug = ! empty( $template_part ) ? $template_part : 'modal';
                BlockSupport::set_has_modal_triggers( $slug );

                // Determine content type and ID
                $content_type = 'url';
                $content_id   = $target_url;

                // Check if URL is internal WordPress content
                $post_id = url_to_postid( $target_url );
                if ( $post_id > 0 ) {
                    $post = get_post( $post_id );
                    if ( $post ) {
                        $content_type = $post->post_type;
                        $content_id   = (string) $post_id;

                        // Register for speculative loading
                        SpeculativeLoading::register_modal_post_id( $post_id );
                    }
                }

                // Generate unique trigger ID and modal ID
                $trigger_id = 'modal-trigger-link-' . wp_unique_id();
                $modal_id   = $content_type . '-' . $content_id;

                // Add attributes to the primary link (for accessibility and keyboard navigation)
                $processor->set_attribute( 'id', $trigger_id );
                $processor->set_attribute( 'aria-haspopup', 'dialog' );
                $processor->add_class( 'is-primary-link' );
                $processor->add_class( 'has-pikari-modal' );

                break; // Only modify the first matching link
            }
        }

        // If we didn't find the primary link, return unchanged
        if ( ! $found_primary_link ) {
            return self::cleanup_post_link_markers( $block_content );
        }

        // Get the updated content with the modified anchor
        $block_content = $processor->get_updated_html();

        // Clean up any post-link markers
        $block_content = self::cleanup_post_link_markers( $block_content );

        // Build context data
        $base = [
            'postId'  => $content_id,
            'modalId' => $modal_id,
        ];

        if ( $content_type === 'url' ) {
            $base['contentSource'] = 'url';
        }

        $context = TriggerContext::build( $block['attrs'], $base, $template_part );

        // Now add the modal trigger class, click handler, and accessibility attributes to the group wrapper
        $processor = new \WP_HTML_Tag_Processor( $block_content );

        // The first tag in a group block is the wrapper div
        if ( $processor->next_tag() ) {
            $processor->add_class( 'has-pikari-modal-trigger' );

            // Add Interactivity API attributes for click delegation
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute(
                'data-wp-context',
                wp_json_encode( $context )
            );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleGroupTriggerClick' );
            $processor->set_attribute( 'data-wp-on--mouseenter', 'actions.handlePrefetchHover' );
            $processor->set_attribute( 'data-wp-on--mouseleave', 'actions.handlePrefetchLeave' );

            // Add role="group" and aria-labelledby for screen reader context
            $processor->set_attribute( 'role', 'group' );
            $processor->set_attribute( 'aria-labelledby', $trigger_id );
        }

        return $processor->get_updated_html();
    }

    /**
     * Phase 2: Handle post-link block matching.
     *
     * Finds the marked post-link anchor that matches the selected block type
     * and applies modal attributes.
     *
     * @param string $block_content   The block content HTML.
     * @param array  $block           The block data array.
     * @param array  $link_identifier The link identifier from block attributes.
     * @param string $template_part   Template part slug (empty for default 'modal').
     * @return string Modified block content.
     */
    private function handle_post_link_block( string $block_content, array $block, array $link_identifier, string $template_part = '' ): string
    {
        $target_block_name = $link_identifier['blockName'] ?? '';

        if ( empty( $target_block_name ) ) {
            return self::cleanup_post_link_markers( $block_content );
        }

        $processor          = new \WP_HTML_Tag_Processor( $block_content );
        $found_primary_link = false;
        $trigger_id         = '';
        $post_id            = null;

        // Find the marked anchor matching our target block type
        while ( $processor->next_tag( 'a' ) ) {
            if ( ! $processor->has_class( 'pikari-post-link-candidate' ) ) {
                continue;
            }

            $block_name = $processor->get_attribute( 'data-pikari-block-name' );
            if ( $block_name !== $target_block_name ) {
                continue;
            }

            // Found our target post-link
            $found_primary_link = true;
            $post_id            = $processor->get_attribute( 'data-pikari-post-id' );
            $trigger_id         = 'modal-trigger-link-' . wp_unique_id();
            $modal_id           = 'post-' . $post_id;

            // Clean up temporary attributes
            $processor->remove_class( 'pikari-post-link-candidate' );
            $processor->remove_attribute( 'data-pikari-post-id' );
            $processor->remove_attribute( 'data-pikari-block-name' );

            // Add modal attributes
            $processor->set_attribute( 'id', $trigger_id );
            $processor->set_attribute( 'aria-haspopup', 'dialog' );
            $processor->add_class( 'is-primary-link' );
            $processor->add_class( 'has-pikari-modal' );

            // Enqueue assets and register for speculative loading
            $slug = ! empty( $template_part ) ? $template_part : 'modal';
            BlockSupport::set_has_modal_triggers( $slug );
            SpeculativeLoading::register_modal_post_id( (int) $post_id );

            break;
        }

        if ( ! $found_primary_link ) {
            // Clean up any remaining markers that weren't used
            return self::cleanup_post_link_markers( $block_content );
        }

        $block_content = $processor->get_updated_html();

        // Clean up other markers not used as primary link
        $block_content = self::cleanup_post_link_markers( $block_content );

        // Build context data
        $base = [
            'postId'  => $post_id,
            'modalId' => 'post-' . $post_id,
        ];

        $context = TriggerContext::build( $block['attrs'], $base, $template_part );

        // Add group wrapper attributes (same as URL-based)
        $processor = new \WP_HTML_Tag_Processor( $block_content );
        if ( $processor->next_tag() ) {
            $processor->add_class( 'has-pikari-modal-trigger' );
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute(
                'data-wp-context',
                wp_json_encode( $context )
            );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleGroupTriggerClick' );
            $processor->set_attribute( 'data-wp-on--mouseenter', 'actions.handlePrefetchHover' );
            $processor->set_attribute( 'data-wp-on--mouseleave', 'actions.handlePrefetchLeave' );
            $processor->set_attribute( 'role', 'group' );
            $processor->set_attribute( 'aria-labelledby', $trigger_id );
        }

        return $processor->get_updated_html();
    }

    /**
     * Handle group trigger with inline content source.
     *
     * When the group is configured to show inline page content (Modal Content block),
     * we set up the group as a trigger without needing a primary link.
     *
     * @param string $block_content The block content HTML.
     * @param array  $block         The block data array.
     * @param string $template_part Template part slug (empty for default 'modal').
     * @return string Modified block content.
     */
    private function handle_inline_content( string $block_content, array $block, string $template_part = '' ): string
    {
        $inline_anchor = $block['attrs']['pikariModalInlineAnchor'] ?? '';

        if ( empty( $inline_anchor ) ) {
            return self::cleanup_post_link_markers( $block_content );
        }

        // Mark that we have modal triggers on this page
        $slug = ! empty( $template_part ) ? $template_part : 'modal';
        BlockSupport::set_has_modal_triggers( $slug );

        // Clean up any post-link markers
        $block_content = self::cleanup_post_link_markers( $block_content );

        // Build context data for inline content
        $base = [
            'contentSource' => 'inline',
            'inlineAnchor'  => $inline_anchor,
            'modalId'       => 'inline-' . $inline_anchor,
        ];

        $context = TriggerContext::build( $block['attrs'], $base, $template_part );

        // An author-supplied label wins over the generic default.
        $aria_label   = __( 'Open modal dialog', 'pikari-gutenberg-modals' );
        $custom_label = trim( $block['attrs']['pikariModalAccessibleLabel'] ?? '' );
        if ( '' !== $custom_label ) {
            $aria_label = $custom_label;
        }

        // Add group wrapper attributes
        $processor = new \WP_HTML_Tag_Processor( $block_content );
        if ( $processor->next_tag() ) {
            $processor->add_class( 'has-pikari-modal-trigger' );

            // Preserve an author-set HTML anchor (core's `anchor` block
            // support writes it to this same wrapper's id) rather than
            // overwriting it — it serves the same focus-restore purpose a
            // generated id would.
            if ( ! $processor->get_attribute( 'id' ) ) {
                $processor->set_attribute( 'id', 'modal-trigger-' . wp_unique_id() );
            }
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute(
                'data-wp-context',
                wp_json_encode( $context )
            );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleGroupTriggerClick' );
            $processor->set_attribute( 'data-wp-on--keydown', 'actions.handleTriggerKeydown' );
            $processor->set_attribute( 'aria-haspopup', 'dialog' );
            $processor->set_attribute( 'aria-expanded', 'false' );
            $processor->set_attribute( 'data-wp-bind--aria-expanded', 'state.isExpanded' );
            $processor->set_attribute( 'role', 'button' );
            $processor->set_attribute( 'tabindex', '0' );
            $processor->set_attribute( 'aria-label', $aria_label );
        }

        return $processor->get_updated_html();
    }

    /**
     * Handle a group trigger whose content is the modal template part itself.
     *
     * There is nothing to fetch and nothing on the page to clone: the
     * template part already holds the content, which is the point — a global
     * modal (a booking panel, a newsletter sign-up) stops needing a
     * stand-in page to be maintained alongside it.
     *
     * Structurally this is handle_inline_content() without the anchor. The
     * whole group becomes the trigger, as an ARIA button, because there is
     * no link inside it to hand the job to.
     *
     * @param string $block_content The block content HTML.
     * @param array  $block         The block data array.
     * @param string $template_part Template part slug (empty for default 'modal').
     * @return string Modified block content.
     */
    private function handle_template_only( string $block_content, array $block, string $template_part = '' ): string
    {
        $slug = ! empty( $template_part ) ? $template_part : 'modal';
        BlockSupport::set_has_modal_triggers( $slug );

        $block_content = self::cleanup_post_link_markers( $block_content );

        $base = [
            'contentSource' => 'none',
            'modalId'       => 'template-' . $slug,
        ];

        $context = TriggerContext::build( $block['attrs'], $base, $template_part );

        // An author-supplied label wins over the generic default.
        $aria_label   = __( 'Open modal dialog', 'pikari-gutenberg-modals' );
        $custom_label = trim( $block['attrs']['pikariModalAccessibleLabel'] ?? '' );
        if ( '' !== $custom_label ) {
            $aria_label = $custom_label;
        }

        $processor = new \WP_HTML_Tag_Processor( $block_content );
        if ( $processor->next_tag() ) {
            $processor->add_class( 'has-pikari-modal-trigger' );

            // Preserve an author-set HTML anchor — see handle_inline_content().
            if ( ! $processor->get_attribute( 'id' ) ) {
                $processor->set_attribute( 'id', 'modal-trigger-' . wp_unique_id() );
            }
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute(
                'data-wp-context',
                wp_json_encode( $context )
            );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleGroupTriggerClick' );
            $processor->set_attribute( 'data-wp-on--keydown', 'actions.handleTriggerKeydown' );
            $processor->set_attribute( 'aria-haspopup', 'dialog' );
            $processor->set_attribute( 'aria-expanded', 'false' );
            $processor->set_attribute( 'data-wp-bind--aria-expanded', 'state.isExpanded' );
            $processor->set_attribute( 'role', 'button' );
            $processor->set_attribute( 'tabindex', '0' );
            $processor->set_attribute( 'aria-label', $aria_label );
        }

        return $processor->get_updated_html();
    }

    /**
     * Handle group trigger with a direct/custom URL content source.
     *
     * Unlike handle_url_based_link(), this does not search the group's inner
     * blocks for a matching anchor — the group has no link of its own to
     * find, the URL is typed directly into the panel. The whole group
     * becomes the trigger, mirroring handle_inline_content().
     *
     * @param string $block_content The block content HTML.
     * @param array  $block         The block data array.
     * @param string $template_part Template part slug (empty for default 'modal').
     * @return string Modified block content.
     */
    private function handle_direct_url( string $block_content, array $block, string $template_part = '' ): string
    {
        $target_url = esc_url_raw( $block['attrs']['pikariModalDirectUrl'] ?? '' );

        // Validated against the domain allow/block lists — this is
        // author-typed input, unlike the links matched in
        // handle_url_based_link(), which already exist in page content.
        if ( ! ModalHandler::validate_url( $target_url ) ) {
            return self::cleanup_post_link_markers( $block_content );
        }

        // Mark that we have modal triggers on this page
        $slug = ! empty( $template_part ) ? $template_part : 'modal';
        BlockSupport::set_has_modal_triggers( $slug );

        // Clean up any post-link markers
        $block_content = self::cleanup_post_link_markers( $block_content );

        // Determine content type and ID
        $content_type = 'url';
        $content_id   = $target_url;
        $aria_label   = __( 'Open modal dialog', 'pikari-gutenberg-modals' );

        // Check if URL is internal WordPress content
        $post_id = url_to_postid( $target_url );
        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $content_type = $post->post_type;
                $content_id   = (string) $post_id;

                // Register for speculative loading
                SpeculativeLoading::register_modal_post_id( $post_id );

                $post_title = get_the_title( $post );
                if ( ! empty( $post_title ) ) {
                    /* translators: %s: content title */
                    $aria_label = sprintf( __( 'Open %s in modal dialog', 'pikari-gutenberg-modals' ), $post_title );
                }
            }
        }

        // An author-supplied label wins over both the generic string and
        // the title derived from an internal URL above.
        $custom_label = trim( $block['attrs']['pikariModalAccessibleLabel'] ?? '' );
        if ( '' !== $custom_label ) {
            $aria_label = $custom_label;
        }

        // Build context data
        $base = [
            'postId'  => $content_id,
            'modalId' => $content_type . '-' . $content_id,
        ];

        if ( $content_type === 'url' ) {
            $base['contentSource'] = 'url';
        }

        $context = TriggerContext::build( $block['attrs'], $base, $template_part );

        // Add group wrapper attributes
        $processor = new \WP_HTML_Tag_Processor( $block_content );
        if ( $processor->next_tag() ) {
            $processor->add_class( 'has-pikari-modal-trigger' );

            // Preserve an author-set HTML anchor (core's `anchor` block
            // support writes it to this same wrapper's id) rather than
            // overwriting it — it serves the same focus-restore purpose a
            // generated id would.
            if ( ! $processor->get_attribute( 'id' ) ) {
                $processor->set_attribute( 'id', 'modal-trigger-' . wp_unique_id() );
            }
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute(
                'data-wp-context',
                wp_json_encode( $context )
            );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleGroupTriggerClick' );
            $processor->set_attribute( 'data-wp-on--keydown', 'actions.handleTriggerKeydown' );
            $processor->set_attribute( 'data-wp-on--mouseenter', 'actions.handlePrefetchHover' );
            $processor->set_attribute( 'data-wp-on--mouseleave', 'actions.handlePrefetchLeave' );
            $processor->set_attribute( 'aria-haspopup', 'dialog' );
            $processor->set_attribute( 'aria-expanded', 'false' );
            $processor->set_attribute( 'data-wp-bind--aria-expanded', 'state.isExpanded' );
            $processor->set_attribute( 'role', 'button' );
            $processor->set_attribute( 'tabindex', '0' );
            $processor->set_attribute( 'aria-label', $aria_label );
        }

        return $processor->get_updated_html();
    }

    /**
     * Clean up post-link marker attributes from content.
     *
     * Removes the temporary classes and data attributes added in Phase 1
     * from any anchor tags that weren't selected as the primary link.
     *
     * @param string $content The HTML content to clean.
     * @return string Cleaned HTML content.
     */
    public static function cleanup_post_link_markers( string $content ): string
    {
        $processor = new \WP_HTML_Tag_Processor( $content );

        while ( $processor->next_tag( 'a' ) ) {
            if ( $processor->has_class( 'pikari-post-link-candidate' ) ) {
                $processor->remove_class( 'pikari-post-link-candidate' );
                $processor->remove_attribute( 'data-pikari-post-id' );
                $processor->remove_attribute( 'data-pikari-block-name' );
            }
        }

        return $processor->get_updated_html();
    }
}
