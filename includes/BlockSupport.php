<?php
/**
 * Block Support Manager
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class BlockSupport
{
    /**
     * List of blocks that support modal links
     *
     * @var array
     */
    private array $supported_blocks;

    /**
     * Whether modal triggers were found during block rendering
     *
     * @var bool
     */
    private static bool $has_modal_triggers = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->supported_blocks = $this->get_supported_blocks();
        $this->register_block_filters();

        // Add filter for button blocks with modal attribute
        add_filter('render_block_core/button', [$this, 'filter_button_block'], 10, 2);

        // Add single modal container to footer (only renders if triggers were found)
        add_action('wp_footer', [$this, 'render_single_modal_container'], 999);
    }

    /**
     * Get list of supported blocks
     *
     * @return array
     */
    private function get_supported_blocks(): array
    {
        $default_blocks = [
            'core/paragraph',
            'core/heading',
            // 'core/button', // Excluded: Creates nested interactive elements (span with role="button" inside button/anchor)
            'core/list',
            'core/list-item',
            'core/quote',
            'core/verse',
            'core/preformatted',
            'core/navigation-link',
        ];

        /**
         * Filter the list of blocks that support modal links
         *
         * Note: Button blocks are excluded by default because they render as
         * interactive elements (button or anchor tags). Adding modal format
         * would create nested interactive elements, which violates HTML standards
         * and causes accessibility issues.
         *
         * For modal-triggering buttons, consider:
         * - Using a regular button block with a custom URL
         * - Creating a dedicated "Modal Button" block variation
         * - Using a paragraph block styled as a button
         *
         * @param array $default_blocks Default list of supported blocks
         */
        return apply_filters('pikari_gutenberg_modals_supported_blocks', $default_blocks);
    }

    /**
     * Get supported blocks for JavaScript
     *
     * @return array
     */
    public function get_supported_blocks_for_js(): array
    {
        return $this->supported_blocks;
    }

    /**
     * Mark that modal triggers exist on this page and enqueue all modal assets.
     *
     * Centralizes asset enqueuing so every trigger type (inline links, buttons,
     * group blocks) loads the same set of styles and scripts. Block styles for
     * the template part blocks (close-button, content-area) are enqueued here
     * because they render late in wp_footer and WordPress won't auto-enqueue them.
     */
    public static function set_has_modal_triggers(): void
    {
        if ( self::$has_modal_triggers ) {
            return;
        }

        self::$has_modal_triggers = true;

        wp_enqueue_script_module( 'pikari-gutenberg-modals-frontend' );
        wp_enqueue_style( 'pikari-gutenberg-modals-frontend' );

        // Enqueue block styles for blocks rendered inside the modal template part.
        // These render in wp_footer after WordPress's normal block style enqueuing.
        wp_enqueue_style( 'pikari-gutenberg-modals-modal-dialog-style' );
        wp_enqueue_style( 'pikari-gutenberg-modals-close-button-style' );
        wp_enqueue_style( 'pikari-gutenberg-modals-content-area-style' );
    }

    /**
     * Register block filters
     */
    private function register_block_filters(): void
    {
        foreach ( $this->supported_blocks as $block_name ) {
            add_filter("render_block_{$block_name}", [$this, 'filter_block'], 10, 2);
        }
    }

    /**
     * Filter block content to convert modal link format spans into interactive triggers.
     *
     * This method:
     * 1. Finds spans with modal link data attributes
     * 2. Extracts the modal configuration
     * 3. Schedules modal HTML rendering in the footer
     * 4. Replaces the span with an interactive trigger element
     *
     * @param string $block_content The block content HTML
     * @param array  $block The block data array
     * @return string Modified block content with modal triggers
     */
    public function filter_block( string $block_content, array $block ): string
    {
        // Early return if no modal links detected
        if ( ! str_contains($block_content, 'data-modal-link') ) {
            return $block_content;
        }

        // Enqueue frontend assets when modal trigger is detected
        self::set_has_modal_triggers();

        // Process modal links using regex with callback
        // Pattern breakdown:
        // - <span[^>]* - Match opening span tag
        // - class="[^"]*modal-link-trigger[^"]*" - Must have modal-link-trigger class
        // - [^>]* - Any other attributes
        // - >(.*?)</span> - Capture inner content until closing tag
        // - /s flag allows . to match newlines
        $block_content = preg_replace_callback(
            '/<span[^>]*class="[^"]*modal-link-trigger[^"]*"[^>]*>(.*?)<\/span>/s',
            [$this, 'process_modal_span'],
            $block_content
        );

        return $block_content;
    }

    /**
     * Filter button block to add modal trigger functionality.
     *
     * When a button has the pikariOpenInModal attribute set to true,
     * this method transforms the button's anchor tag to work with the
     * Interactivity API modal system.
     *
     * @param string $block_content The block content HTML.
     * @param array  $block         The block data array.
     * @return string Modified block content.
     */
    public function filter_button_block( string $block_content, array $block ): string
    {
        // Check if modal is enabled for this button
        $open_in_modal = $block['attrs']['pikariOpenInModal'] ?? false;

        if ( ! $open_in_modal ) {
            return $block_content;
        }

        // Check content source: 'inline' for page content, 'link' (default) for URL
        $content_source = $block['attrs']['pikariModalContentSource'] ?? 'link';
        $inline_anchor  = $block['attrs']['pikariModalInlineAnchor'] ?? '';

        if ( $content_source === 'inline' ) {
            return $this->filter_button_block_inline( $block_content, $block, $inline_anchor );
        }

        // Get the URL - first try block attributes, then extract from HTML
        $url = $block['attrs']['url'] ?? '';

        // If URL not in attributes, extract from the anchor href
        if ( empty( $url ) ) {
            $processor = new \WP_HTML_Tag_Processor( $block_content );
            if ( $processor->next_tag( 'a' ) ) {
                $url = $processor->get_attribute( 'href' ) ?? '';
            }
        }

        if ( empty( $url ) ) {
            return $block_content;
        }

        // Mark that we have modal triggers on this page
        self::set_has_modal_triggers();

        // Determine content type and ID
        $content_type = 'url';
        $content_id   = $url;

        // Check if URL is internal WordPress content
        $post_id = url_to_postid( $url );
        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $content_type = $post->post_type;
                $content_id   = (string) $post_id;

                // Register for speculative loading
                SpeculativeLoading::register_modal_post_id( $post_id );
            }
        }

        // Get modal size setting
        $modal_size = $block['attrs']['pikariModalSize'] ?? '';

        // Generate unique trigger ID
        $trigger_id = 'modal-trigger-' . wp_unique_id();

        // Use WP_HTML_Tag_Processor to modify the anchor tag
        $processor = new \WP_HTML_Tag_Processor( $block_content );

        // Build context data
        $context = [
            'postId'  => $content_id,
            'modalId' => $content_type . '-' . $content_id,
        ];

        if ( ! empty( $modal_size ) ) {
            $context['size'] = $modal_size;
        }

        // Find the anchor tag (button link)
        if ( $processor->next_tag( 'a' ) ) {
            $processor->set_attribute( 'id', $trigger_id );
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute(
                'data-wp-context',
                wp_json_encode( $context )
            );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleTriggerClick' );
            $processor->set_attribute( 'data-wp-on--mouseenter', 'actions.handlePrefetchHover' );
            $processor->set_attribute( 'data-wp-on--mouseleave', 'actions.handlePrefetchLeave' );
            $processor->set_attribute( 'aria-haspopup', 'dialog' );
            $processor->set_attribute( 'aria-expanded', 'false' );
            $processor->set_attribute( 'data-wp-bind--aria-expanded', 'state.isOpen' );
            $processor->add_class( 'has-pikari-modal' );
        }

        return $processor->get_updated_html();
    }

    /**
     * Handle button block with inline content source.
     *
     * When the button is configured to show inline page content (Modal Content block),
     * we set up the trigger to reference the inline content anchor instead of a URL.
     *
     * @param string $block_content The block content HTML.
     * @param array  $block         The block data array.
     * @param string $inline_anchor The anchor of the Modal Content block.
     * @return string Modified block content.
     */
    private function filter_button_block_inline( string $block_content, array $block, string $inline_anchor ): string
    {
        if ( empty( $inline_anchor ) ) {
            return $block_content;
        }

        // Mark that we have modal triggers on this page
        self::set_has_modal_triggers();

        $modal_size = $block['attrs']['pikariModalSize'] ?? '';
        $trigger_id = 'modal-trigger-' . wp_unique_id();

        // Build context data for inline content
        $context = [
            'contentSource' => 'inline',
            'inlineAnchor'  => $inline_anchor,
            'modalId'       => 'inline-' . $inline_anchor,
        ];

        if ( ! empty( $modal_size ) ) {
            $context['size'] = $modal_size;
        }

        $processor = new \WP_HTML_Tag_Processor( $block_content );

        // Find the anchor tag (button link)
        if ( $processor->next_tag( 'a' ) ) {
            $processor->set_attribute( 'id', $trigger_id );
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute(
                'data-wp-context',
                wp_json_encode( $context )
            );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleTriggerClick' );
            $processor->set_attribute( 'aria-haspopup', 'dialog' );
            $processor->set_attribute( 'aria-expanded', 'false' );
            $processor->set_attribute( 'data-wp-bind--aria-expanded', 'state.isOpen' );
            $processor->set_attribute( 'href', '#' . $inline_anchor );
            $processor->add_class( 'has-pikari-modal' );
        }

        return $processor->get_updated_html();
    }

    /**
     * Process a single modal span match.
     *
     * @param array $matches Regex matches array
     * @return string Processed span HTML
     */
    private function process_modal_span( array $matches ): string
    {
        $full_tag = $matches[0];
        $inner_html = $matches[1];

        // Extract modal configuration from data attributes
        $modal_config = $this->extract_modal_config($full_tag);

        if ( ! $modal_config ) {
            // Return unchanged if configuration is invalid
            return $full_tag;
        }

        // Register post ID for speculative loading (prefetch) — skip for inline content
        $content_id = $modal_config['content_id'];
        if ( $modal_config['content_type'] !== 'inline' && is_numeric( $content_id ) ) {
            SpeculativeLoading::register_modal_post_id( (int) $content_id );
        }

        // Return interactive trigger link with content data
        return $this->create_trigger_link(
            $modal_config['content_type'],
            $modal_config['content_id'],
            $inner_html,
            $modal_config['size'] ?? ''
        );
    }

    /**
     * Extract modal configuration from span tag attributes.
     *
     * @param string $tag_html The full span tag HTML
     * @return array|null Modal configuration or null if invalid
     */
    private function extract_modal_config( string $tag_html ): ?array
    {
        // Extract each required attribute
        preg_match('/data-modal-link="([^"]*)"/', $tag_html, $link_match);
        preg_match('/data-modal-content-type="([^"]*)"/', $tag_html, $type_match);
        preg_match('/data-modal-content-id="([^"]*)"/', $tag_html, $id_match);

        // Validate all required attributes exist
        if ( ! $link_match || ! $type_match || ! $id_match ) {
            return null;
        }

        // Decode JSON link data (handles HTML entities)
        $link_data = json_decode(html_entity_decode($link_match[1]), true) ?: [];

        // Extract optional size attribute
        preg_match('/data-modal-size="([^"]*)"/', $tag_html, $size_match);
        $size = $size_match[1] ?? '';

        return [
            'link_data'    => $link_data,
            'content_type' => $type_match[1],
            'content_id'   => $id_match[1],
            'size'         => $size,
        ];
    }

    /**
     * Create the trigger anchor element.
     *
     * Uses a real anchor tag for progressive enhancement:
     * - With JS: Opens modal via Interactivity API
     * - Without JS: Navigates to the actual content
     * - Enables native browser prefetching via Speculation Rules API
     *
     * @param string $content_type Content type (post/page/url)
     * @param string $content_id Content ID or URL
     * @param string $inner_html Original span content
     * @param string $size       Modal size (small/large/fullscreen, empty for default)
     * @return string Trigger anchor HTML
     */
    private function create_trigger_link( string $content_type, string $content_id, string $inner_html, string $size = '' ): string
    {
        // Generate unique ID for focus management
        $trigger_id = 'modal-trigger-' . wp_unique_id();

        // Handle inline content (Modal Content block on the page)
        if ( $content_type === 'inline' ) {
            $context = [
                'contentSource' => 'inline',
                'inlineAnchor'  => $content_id,
                'modalId'       => 'inline-' . $content_id,
            ];

            if ( ! empty( $size ) ) {
                $context['size'] = $size;
            }

            return sprintf(
                '<a
                    id="%s"
                    href="%s"
                    class="has-modal-link modal-link-trigger"
                    data-wp-interactive="pikari-modal"
                    data-wp-context=\'%s\'
                    data-wp-on--click="actions.handleTriggerClick"
                    aria-haspopup="dialog"
                >%s</a>',
                esc_attr( $trigger_id ),
                esc_attr( '#' . $content_id ),
                wp_json_encode( $context ),
                $inner_html
            );
        }

        // Determine the href based on content type
        if ( $content_type === 'url' ) {
            // External URL - use as-is
            $href = $content_id;
        } else {
            // Post/page - get the permalink for progressive enhancement
            $href = get_permalink( (int) $content_id );
            if ( ! $href ) {
                // Fallback to # if permalink not found
                $href = '#';
            }
        }

        // Build context data
        $context = [
            'postId'  => $content_id,
            'modalId' => $content_type . '-' . $content_id,
        ];

        if ( ! empty( $size ) ) {
            $context['size'] = $size;
        }

        return sprintf(
            '<a
                id="%s"
                href="%s"
                class="has-modal-link modal-link-trigger"
                data-wp-interactive="pikari-modal"
                data-wp-context=\'%s\'
                data-wp-on--click="actions.handleTriggerClick"
                data-wp-on--mouseenter="actions.handlePrefetchHover"
                data-wp-on--mouseleave="actions.handlePrefetchLeave"
                aria-haspopup="dialog"
            >%s</a>',
            esc_attr( $trigger_id ),
            esc_url( $href ),
            wp_json_encode( $context ),
            $inner_html
        );
    }

    /**
     * Modified version of WordPress's wp_render_layout_support_flag function.
     *
     * This captures and returns layout styles inline instead of enqueuing them
     * to the footer, which is necessary for content loaded via REST API/AJAX.
     *
     * Based on WordPress core's wp_render_layout_support_flag from
     * wp-includes/block-supports/layout.php
     *
     * @param string $block_content The block content
     * @param array  $block The block data
     * @return array Array with 'content' and 'styles' keys
     */
    private function render_layout_support_inline( string $block_content, array $block ): array
    {
        $block_type = \WP_Block_Type_Registry::get_instance()->get_registered($block['blockName']);
        $block_supports_layout = block_has_support($block_type, 'layout', false) || block_has_support($block_type, '__experimentalLayout', false);

        if ( ! $block_supports_layout ) {
            return [
                'content' => $block_content,
                'styles' => '',
            ];
        }

        $default_layout = isset($block_type->supports['layout']['default'])
        ? $block_type->supports['layout']['default']
        : array();

        if ( empty($default_layout) ) {
            $default_layout = isset($block_type->supports['__experimentalLayout']['default'])
            ? $block_type->supports['__experimentalLayout']['default']
            : array();
        }

        $used_layout = isset($block['attrs']['layout']) ? $block['attrs']['layout'] : $default_layout;

        // Set the correct layout type for blocks using legacy content width
        if ( isset($used_layout['inherit']) && $used_layout['inherit'] || isset($used_layout['contentSize']) && $used_layout['contentSize'] ) {
            $used_layout['type'] = 'constrained';
        }

        $class_names = [];
        $layout_definitions = wp_get_layout_definitions();

        // Get layout type classname
        if ( isset($used_layout['type']) ) {
            $layout_classname = isset($layout_definitions[ $used_layout['type'] ]['className'])
            ? $layout_definitions[ $used_layout['type'] ]['className']
            : '';
        } else {
            $layout_classname = isset($layout_definitions['default']['className'])
            ? $layout_definitions['default']['className']
            : '';
        }

        if ( $layout_classname && is_string($layout_classname) ) {
            $class_names[] = sanitize_title($layout_classname);
        }

        // Add orientation class
        if ( ! empty($block['attrs']['layout']['orientation']) ) {
            $class_names[] = 'is-' . sanitize_title($block['attrs']['layout']['orientation']);
        }

        // Add content justification class
        if ( ! empty($block['attrs']['layout']['justifyContent']) ) {
            $class_names[] = 'is-content-justification-' . sanitize_title($block['attrs']['layout']['justifyContent']);
        }

        // Add nowrap class
        if ( ! empty($block['attrs']['layout']['flexWrap']) && 'nowrap' === $block['attrs']['layout']['flexWrap'] ) {
            $class_names[] = 'is-nowrap';
        }

        // Handle vertical alignment for columns block
        if ( 'core/columns' === $block['blockName'] && isset($block['attrs']['verticalAlignment']) ) {
            $class_names[] = 'are-vertically-aligned-' . $block['attrs']['verticalAlignment'];
        }

        $gap_value = isset($block['attrs']['style']['spacing']['blockGap'])
        ? $block['attrs']['style']['spacing']['blockGap']
        : null;

        // Skip if gap value contains unsupported characters
        if ( is_array($gap_value) ) {
            foreach ( $gap_value as $key => $value ) {
                $gap_value[ $key ] = $value && preg_match('%[\\\(&=}]|/\*%', $value) ? null : $value;
            }
        } else {
            $gap_value = $gap_value && preg_match('%[\\\(&=}]|/\*%', $gap_value) ? null : $gap_value;
        }

        $should_skip_gap_serialization = wp_should_skip_block_supports_serialization($block_type, 'spacing', 'blockGap');
        $block_spacing = isset($block['attrs']['style']['spacing']) ? $block['attrs']['style']['spacing'] : null;

        $fallback_gap_value = isset($block_type->supports['spacing']['blockGap']['__experimentalDefault'])
        ? $block_type->supports['spacing']['blockGap']['__experimentalDefault']
        : '0.5em';

        $has_block_gap_support = isset(wp_get_global_settings()['spacing']['blockGap']);

        // Generate unique container class
        $unique_id = wp_unique_id('is-layout-');
        $container_class = 'wp-container-' . sanitize_title($block['blockName']) . '-' . $unique_id;

        // Get layout styles using WordPress core function
        $layout_styles = wp_get_layout_style(
            ".$container_class",
            $used_layout,
            $has_block_gap_support,
            $gap_value,
            $should_skip_gap_serialization,
            $fallback_gap_value,
            $block_spacing
        );

        // Only add container class if we have styles
        if ( ! empty($layout_styles) ) {
            $class_names[] = $container_class;
        }

        // Add combined layout and block classname
        $block_name = explode('/', $block['blockName']);
        $class_names[] = 'wp-block-' . end($block_name) . '-' . $layout_classname;

        // Apply classes to block content
        if ( ! empty($class_names) ) {
            $processor = new \WP_HTML_Tag_Processor($block_content);
            if ( $processor->next_tag() ) {
                foreach ( $class_names as $class_name ) {
                    $processor->add_class($class_name);
                }
                $block_content = $processor->get_updated_html();
            }
        }

        return [
            'content' => $block_content,
            'styles' => $layout_styles,
        ];
    }

    /**
     * Get post content with captured block support styles.
     *
     * This method captures the dynamically generated CSS for block supports
     * (gaps, spacing, typography, etc.) that are normally injected via
     * wp_add_inline_style() during block rendering.
     *
     * @param WP_Post $post_object The post object
     * @return array Array with 'content' and 'styles' keys
     */
    public function get_post_content_with_styles( \WP_Post $post_object ): array
    {
        // Store current global post
        global $post;
        $original_post = $post;

        // Set up post data - this sets the global $post variable
        $post = $post_object;
        setup_postdata($post);

        // Array to collect all styles
        $captured_styles = [];

        // Hook into render_block to use our modified layout support function
        $style_capture_filter = function ( $block_content, $parsed_block ) use ( &$captured_styles ) {
            // Use our modified layout support function that returns styles inline
            $result = $this->render_layout_support_inline($block_content, $parsed_block);

            // Capture any generated styles
            if ( ! empty($result['styles']) ) {
                $captured_styles[] = $result['styles'];
            }

            // Return the modified content
            return $result['content'];
        };
        add_filter('render_block', $style_capture_filter, 10, 2);

        // Add block context filter to provide post data to dynamic blocks
        $filter_block_context = function ( $context ) use ( $post_object ) {
            $context['postId'] = $post_object->ID;
            $context['postType'] = $post_object->post_type;
            return $context;
        };
        add_filter('render_block_context', $filter_block_context, 1);

        // Process blocks
        $content = do_blocks($post_object->post_content);

        // Apply content filters
        $content = apply_filters('the_content', $content);

        // Remove our filters
        remove_filter('render_block', $style_capture_filter, 10);
        remove_filter('render_block_context', $filter_block_context, 1);

        // Reset post data
        wp_reset_postdata();

        // Restore original post
        $post = $original_post;

        // Combine all captured styles
        $all_styles = '';
        if ( ! empty($captured_styles) ) {
            $captured_styles = array_filter(array_unique($captured_styles));
            $all_styles = sprintf(
                '<style id="modal-%s-block-supports">%s</style>',
                esc_attr($post_object->ID),
                implode("\n", $captured_styles)
            );
        }

        return [
            'content' => $content,
            'styles' => $all_styles,
        ];
    }
    /**
     * Render a single modal container in the footer.
     *
     * Uses the modal template part for block themes, falling back to
     * hardcoded HTML for classic themes without template part support.
     *
     * Only renders if modal triggers were found during block rendering.
     */
    public function render_single_modal_container(): void
    {
        // Only render if modal triggers were found on this page
        if ( ! self::$has_modal_triggers ) {
            return;
        }

        // The outer structural wrapper handles overlay positioning, ARIA, and Interactivity API scope.
        // The inner content (modal-dialog block) owns the dialog chrome and overlay appearance.
        $inner_content = ModalTemplatePart::render();
        ?>
        <div
            id="pikari-modal"
            class="modal-overlay"
            data-wp-interactive="pikari-modal"
            data-wp-on--click="actions.closeModalOnBackdrop"
            data-wp-on-window--keydown="actions.handleKeydown"
            style="display: none;"
            role="dialog"
            aria-modal="true"
            aria-label="<?php esc_attr_e( 'Modal dialog', 'pikari-gutenberg-modals' ); ?>"
            aria-labelledby="modal-title"
            aria-describedby="modal-content"
        >
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template part content is processed by do_blocks().
        echo $inner_content;
        ?>
        </div>
        <?php
    }
}