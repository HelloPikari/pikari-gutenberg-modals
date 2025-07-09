<?php
/**
 * Block Support Manager
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class Block_Support
{
    /**
     * List of blocks that support modal links
     *
     * @var array
     */
    private array $supported_blocks;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->supported_blocks = $this->get_supported_blocks();
        $this->register_block_filters();
        
        // Add single modal container to footer
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
     * Register block filters
     */
    private function register_block_filters(): void
    {
        foreach ($this->supported_blocks as $block_name) {
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
     * @param array $block The block data array
     * @return string Modified block content with modal triggers
     */
    public function filter_block(string $block_content, array $block): string
    {
        // Early return if no modal links detected
        if (!str_contains($block_content, 'data-modal-link')) {
            return $block_content;
        }

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
     * Process a single modal span match.
     *
     * @param array $matches Regex matches array
     * @return string Processed span HTML
     */
    private function process_modal_span(array $matches): string
    {
        $full_tag = $matches[0];
        $inner_html = $matches[1];

        // Extract modal configuration from data attributes
        $modal_config = $this->extract_modal_config($full_tag);

        if (!$modal_config) {
            // Return unchanged if configuration is invalid
            return $full_tag;
        }

        // Return interactive trigger span with content data
        return $this->create_trigger_span(
            $modal_config['content_type'],
            $modal_config['content_id'],
            $inner_html
        );
    }

    /**
     * Extract modal configuration from span tag attributes.
     *
     * @param string $tag_html The full span tag HTML
     * @return array|null Modal configuration or null if invalid
     */
    private function extract_modal_config(string $tag_html): ?array
    {
        // Extract each required attribute
        preg_match('/data-modal-link="([^"]*)"/', $tag_html, $link_match);
        preg_match('/data-modal-content-type="([^"]*)"/', $tag_html, $type_match);
        preg_match('/data-modal-content-id="([^"]*)"/', $tag_html, $id_match);

        // Validate all required attributes exist
        if (!$link_match || !$type_match || !$id_match) {
            return null;
        }

        // Decode JSON link data (handles HTML entities)
        $link_data = json_decode(html_entity_decode($link_match[1]), true) ?: [];

        return [
            'link_data' => $link_data,
            'content_type' => $type_match[1],
            'content_id' => $id_match[1],
        ];
    }

    /**
     * Create the trigger span element.
     *
     * @param string $content_type Content type (post/page/url)
     * @param string $content_id Content ID or URL
     * @param string $inner_html Original span content
     * @return string Trigger span HTML
     */
    private function create_trigger_span(string $content_type, string $content_id, string $inner_html): string
    {
        return sprintf(
            '<span class="has-modal-link modal-link-trigger" data-modal-content-type="%s" data-modal-content-id="%s" role="button" tabindex="0" style="cursor: pointer; text-decoration: underline; text-decoration-style: dashed;">%s</span>',
            esc_attr($content_type),
            esc_attr($content_id),
            $inner_html
        );
    }


    /**
     * Get modal content based on type and ID.
     *
     * Handles three scenarios:
     * 1. External URLs - Rendered in an iframe
     * 2. WordPress posts/pages - Full content with title
     * 3. Invalid content - Returns null with error logging
     *
     * @param string $content_type The content type (url/post/page/custom)
     * @param string $content_id The content ID (post ID) or URL
     * @return string|null The content HTML or null on failure
     */
    private function get_modal_content(string $content_type, string $content_id): ?string
    {
        // Handle external URLs
        if ($content_type === 'url') {
            return $this->get_url_content($content_id);
        }

        // Handle WordPress content (posts, pages, custom post types)
        return $this->get_post_content($content_id);
    }

    /**
     * Get content for external URLs.
     *
     * @param string $url The external URL
     * @return string Iframe HTML
     */
    private function get_url_content(string $url): string
    {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log('Modal Toolbar Button: Invalid URL provided: ' . $url);
            return '<p>' . esc_html__('Invalid URL provided.', 'modal-toolbar-button') . '</p>';
        }

        /**
         * Filter to customize URL modal content.
         *
         * @param string $content The default iframe HTML
         * @param string $url The URL being displayed
         */
        $content = sprintf(
            '<iframe src="%s" style="width: 100%%; height: 80vh; border: none;" title="%s"></iframe>',
            esc_url($url),
            esc_attr__('External content', 'modal-toolbar-button')
        );

        return apply_filters('modal_toolbar_url_content', $content, $url);
    }

    /**
     * Get content for WordPress posts.
     *
     * @param string $post_id The post ID
     * @return string|null Post content HTML or null if not found
     */
    private function get_post_content(string $post_id): ?string
    {
        // Get the post
        $post = get_post((int) $post_id);

        if (!$post || $post->post_status !== 'publish') {
            error_log('Pikari Gutenberg Modals: Post not found or not published: ' . $post_id);
            return null;
        }

        // Get content with captured styles
        $content_with_styles = $this->get_post_content_with_styles($post);

        // Build CSS classes for the article element
        $article_classes = array(
            'modal-entry',
            'type-' . $post->post_type,
            'post-' . $post->ID
        );
        
        // Build the complete modal content
        $modal_content = sprintf(
            '<article class="%s">
                <header class="modal-entry-header">
                    <h2>%s</h2>
                </header>
                <div class="modal-entry-content">
                    %s
                    %s
                </div>
            </article>',
            esc_attr(implode(' ', $article_classes)),
            esc_html(get_the_title($post)),
            $content_with_styles['styles'], // Include captured styles
            $content_with_styles['content']
        );

        /**
         * Filter the modal post content.
         *
         * @param string $modal_content The complete modal content HTML
         * @param WP_Post $post The post object
         */
        return apply_filters('modal_toolbar_post_content', $modal_content, $post);
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
     * @param array $block The block data
     * @return array Array with 'content' and 'styles' keys
     */
    private function render_layout_support_inline(string $block_content, array $block): array
    {
        $block_type = \WP_Block_Type_Registry::get_instance()->get_registered($block['blockName']);
        $block_supports_layout = block_has_support($block_type, 'layout', false) || block_has_support($block_type, '__experimentalLayout', false);
        
        if (!$block_supports_layout) {
            return ['content' => $block_content, 'styles' => ''];
        }
        
        $default_layout = isset($block_type->supports['layout']['default'])
            ? $block_type->supports['layout']['default']
            : array();
            
        if (empty($default_layout)) {
            $default_layout = isset($block_type->supports['__experimentalLayout']['default'])
                ? $block_type->supports['__experimentalLayout']['default']
                : array();
        }
        
        $used_layout = isset($block['attrs']['layout']) ? $block['attrs']['layout'] : $default_layout;
        
        // Set the correct layout type for blocks using legacy content width
        if (isset($used_layout['inherit']) && $used_layout['inherit'] || isset($used_layout['contentSize']) && $used_layout['contentSize']) {
            $used_layout['type'] = 'constrained';
        }
        
        $class_names = [];
        $layout_definitions = wp_get_layout_definitions();
        
        // Get layout type classname
        if (isset($used_layout['type'])) {
            $layout_classname = isset($layout_definitions[$used_layout['type']]['className'])
                ? $layout_definitions[$used_layout['type']]['className']
                : '';
        } else {
            $layout_classname = isset($layout_definitions['default']['className'])
                ? $layout_definitions['default']['className']
                : '';
        }
        
        if ($layout_classname && is_string($layout_classname)) {
            $class_names[] = sanitize_title($layout_classname);
        }
        
        // Add orientation class
        if (!empty($block['attrs']['layout']['orientation'])) {
            $class_names[] = 'is-' . sanitize_title($block['attrs']['layout']['orientation']);
        }
        
        // Add content justification class
        if (!empty($block['attrs']['layout']['justifyContent'])) {
            $class_names[] = 'is-content-justification-' . sanitize_title($block['attrs']['layout']['justifyContent']);
        }
        
        // Add nowrap class
        if (!empty($block['attrs']['layout']['flexWrap']) && 'nowrap' === $block['attrs']['layout']['flexWrap']) {
            $class_names[] = 'is-nowrap';
        }
        
        // Handle vertical alignment for columns block
        if ('core/columns' === $block['blockName'] && isset($block['attrs']['verticalAlignment'])) {
            $class_names[] = 'are-vertically-aligned-' . $block['attrs']['verticalAlignment'];
        }
        
        $gap_value = isset($block['attrs']['style']['spacing']['blockGap'])
            ? $block['attrs']['style']['spacing']['blockGap']
            : null;
            
        // Skip if gap value contains unsupported characters
        if (is_array($gap_value)) {
            foreach ($gap_value as $key => $value) {
                $gap_value[$key] = $value && preg_match('%[\\\(&=}]|/\*%', $value) ? null : $value;
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
        if (!empty($layout_styles)) {
            $class_names[] = $container_class;
        }
        
        // Add combined layout and block classname
        $block_name = explode('/', $block['blockName']);
        $class_names[] = 'wp-block-' . end($block_name) . '-' . $layout_classname;
        
        // Apply classes to block content
        if (!empty($class_names)) {
            $processor = new \WP_HTML_Tag_Processor($block_content);
            if ($processor->next_tag()) {
                foreach ($class_names as $class_name) {
                    $processor->add_class($class_name);
                }
                $block_content = $processor->get_updated_html();
            }
        }
        
        return [
            'content' => $block_content,
            'styles' => $layout_styles
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
    public function get_post_content_with_styles(\WP_Post $post_object): array
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
        $style_capture_filter = function ($block_content, $parsed_block) use (&$captured_styles) {
            // Use our modified layout support function that returns styles inline
            $result = $this->render_layout_support_inline($block_content, $parsed_block);
            
            // Capture any generated styles
            if (!empty($result['styles'])) {
                $captured_styles[] = $result['styles'];
            }
            
            // Return the modified content
            return $result['content'];
        };
        add_filter('render_block', $style_capture_filter, 10, 2);
        
        // Add block context filter to provide post data to dynamic blocks
        $filter_block_context = function ($context) use ($post_object) {
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
        if (!empty($captured_styles)) {
            $captured_styles = array_filter(array_unique($captured_styles));
            $all_styles = sprintf(
                '<style id="modal-%s-block-supports">%s</style>',
                esc_attr($post_object->ID),
                implode("\n", $captured_styles)
            );
        }
        
        return [
            'content' => $content,
            'styles' => $all_styles
        ];
    }
    /**
     * Render a single modal container in the footer.
     *
     * This container will be populated dynamically via AJAX when triggers are clicked.
     */
    public function render_single_modal_container(): void
    {
        ?>
        <div 
            id="pikari-modal"
            class="modal-overlay"
            x-data="pikariModal"
            x-show="open"
            x-on:open-modal.window="openModal($event.detail)"
            x-on:click.self="closeModal"
            x-on:keydown.escape.window="closeModal"
            style="display: none;"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div class="modal-content" x-on:click.stop>
                <button 
                    class="modal-close" 
                    x-on:click="closeModal"
                    aria-label="<?php esc_attr_e('Close modal', 'pikari-gutenberg-modals'); ?>"
                >
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="modal-body" x-html="content">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('pikariModal', () => ({
                open: false,
                content: '',
                loading: false,
                
                async openModal(detail) {
                    if (!detail.contentType || !detail.contentId) {
                        console.error('Missing content type or ID', detail);
                        return;
                    }
                    
                    this.loading = true;
                    this.open = true;
                    this.content = '<div class="modal-loading">Loading...</div>';
                    
                    try {
                        const apiUrl = window.pikariModalsData?.apiUrl || '/wp-json/pikari-gutenberg-modals/v1/modal-content/';
                        const response = await fetch(`${apiUrl}${detail.contentId}`);
                        const data = await response.json();
                        
                        // Build article CSS classes
                        const articleClasses = [
                            'modal-entry',
                            `type-${data.type}`,
                            `post-${data.id}`
                        ].join(' ');
                        
                        // Set content with inline styles
                        this.content = `
                            ${data.styles ? `<style>${data.styles}</style>` : ''}
                            <article class="${articleClasses}">
                                <header class="modal-entry-header">
                                    <h2>${data.title}</h2>
                                </header>
                                <div class="modal-entry-content">
                                    ${data.content}
                                </div>
                            </article>
                        `;
                    } catch (error) {
                        console.error('Error loading modal content:', error);
                        this.content = '<div class="modal-error">Error loading content. Please try again.</div>';
                    } finally {
                        this.loading = false;
                    }
                },
                
                closeModal() {
                    this.open = false;
                    this.content = '';
                }
            }));
        });
        </script>
        <?php
    }
}