<?php
/**
 * Block Support Manager
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class Block_Support {
    /**
     * List of blocks that support modal links
     *
     * @var array
     */
    private array $supported_blocks;

    /**
     * Constructor
     */
    public function __construct() {
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
    private function get_supported_blocks(): array {
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
    public function get_supported_blocks_for_js(): array {
        return $this->supported_blocks;
    }

    /**
     * Register block filters
     */
    private function register_block_filters(): void {
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
    public function filter_block(string $block_content, array $block): string {
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
    private function process_modal_span(array $matches): string {
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
    private function extract_modal_config(string $tag_html): ?array {
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
    private function create_trigger_span(string $content_type, string $content_id, string $inner_html): string {
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
    private function get_modal_content(string $content_type, string $content_id): ?string {
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
    private function get_url_content(string $url): string {
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
    private function get_post_content(string $post_id): ?string {
        // Get the post
        $post = get_post((int) $post_id);

        if (!$post || $post->post_status !== 'publish') {
            error_log('Pikari Gutenberg Modals: Post not found or not published: ' . $post_id);
            return null;
        }

        // Get content with captured styles
        $content_with_styles = $this->get_post_content_with_styles($post);

        // Build the complete modal content
        $modal_content = sprintf(
            '<article class="modal-post-content">
                <header class="modal-post-header">
                    <h2>%s</h2>
                </header>
                <div class="modal-post-body">
                    %s
                    %s
                </div>
            </article>',
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
     * Get post content with captured block support styles.
     *
     * This method captures the dynamically generated CSS for block supports
     * (gaps, spacing, typography, etc.) that are normally injected via
     * wp_add_inline_style() during block rendering.
     *
     * @param WP_Post $post The post object
     * @return array Array with 'content' and 'styles' keys
     */
    public function get_post_content_with_styles(\WP_Post $post): array {
        // Set up post data
        setup_postdata($post);
        
        // Array to collect all styles
        $captured_styles = [];
        
        // Hook into render_block to capture dynamically generated styles
        add_filter('render_block', function($block_content, $parsed_block) use (&$captured_styles) {
            // Check if block has layout support
            if (!empty($parsed_block['attrs']['style']) || strpos($block_content, 'wp-container-') !== false) {
                // Extract container ID from rendered content
                if (preg_match('/class="[^"]*?(wp-container-[^"\s]+)/', $block_content, $matches)) {
                    $container_id = $matches[1];
                    
                    // Get layout styles for this specific block
                    $layout_styles = $this->generate_layout_styles($container_id, $parsed_block);
                    if ($layout_styles) {
                        $captured_styles[] = $layout_styles;
                    }
                }
            }
            return $block_content;
        }, 10, 2);
        
        // Process blocks
        $content = do_blocks($post->post_content);
        
        // Apply content filters
        $content = apply_filters('the_content', $content);
        
        // Remove our filter
        remove_filter('render_block', '__return_false', 10);
        
        // Reset post data
        wp_reset_postdata();
        
        // Combine all captured styles
        $all_styles = '';
        if (!empty($captured_styles)) {
            $captured_styles = array_filter(array_unique($captured_styles));
            $all_styles = sprintf(
                '<style id="modal-%s-block-supports">%s</style>',
                esc_attr($post->ID),
                implode("\n", $captured_styles)
            );
        }
        
        return [
            'content' => $content,
            'styles' => $all_styles
        ];
    }
    
    /**
     * Generate layout styles for a specific container.
     *
     * @param string $container_id The container ID (e.g., wp-container-xxx)
     * @param array $parsed_block The parsed block data
     * @return string Generated CSS styles
     */
    private function generate_layout_styles(string $container_id, array $parsed_block): string {
        $styles = [];
        
        // Check if this is a flex or grid layout
        $is_flex = isset($parsed_block['attrs']['layout']['type']) && $parsed_block['attrs']['layout']['type'] === 'flex';
        $is_default = !isset($parsed_block['attrs']['layout']['type']) || $parsed_block['attrs']['layout']['type'] === 'default';
        
        // For columns and group blocks with flex layout
        if ($is_flex || strpos($parsed_block['blockName'], 'core/columns') !== false) {
            $styles[] = ".{$container_id} { display: flex; }";
            
            // Handle flex wrap
            if (!empty($parsed_block['attrs']['layout']['flexWrap'])) {
                $styles[] = ".{$container_id} { flex-wrap: {$parsed_block['attrs']['layout']['flexWrap']}; }";
            } else {
                $styles[] = ".{$container_id} { flex-wrap: wrap; }";
            }
            
            // Handle vertical alignment
            if (!empty($parsed_block['attrs']['layout']['verticalAlignment'])) {
                $align_map = [
                    'top' => 'flex-start',
                    'center' => 'center',
                    'bottom' => 'flex-end',
                ];
                $align_value = $align_map[$parsed_block['attrs']['layout']['verticalAlignment']] ?? 'center';
                $styles[] = ".{$container_id} { align-items: {$align_value}; }";
            } else {
                $styles[] = ".{$container_id} { align-items: center; }";
            }
        }
        
        // Handle gap/spacing
        if (!empty($parsed_block['attrs']['style']['spacing']['blockGap'])) {
            $gap = $parsed_block['attrs']['style']['spacing']['blockGap'];
            
            // Handle different gap formats
            if (is_string($gap)) {
                $gap_value = $this->convert_value_to_css($gap);
                $styles[] = ".{$container_id} { gap: {$gap_value}; }";
            } elseif (is_array($gap)) {
                // Handle directional gaps
                if (isset($gap['top']) || isset($gap['left'])) {
                    $row_gap = isset($gap['top']) ? $this->convert_value_to_css($gap['top']) : '0';
                    $column_gap = isset($gap['left']) ? $this->convert_value_to_css($gap['left']) : '0';
                    $styles[] = ".{$container_id} { row-gap: {$row_gap}; column-gap: {$column_gap}; }";
                }
            }
        } elseif ($is_flex || strpos($parsed_block['blockName'], 'core/columns') !== false) {
            // Default gap for flex layouts
            $styles[] = ".{$container_id} { gap: var(--wp--style--block-gap, 2em); }";
        }
        
        // Handle padding
        if (!empty($parsed_block['attrs']['style']['spacing']['padding'])) {
            $padding = $parsed_block['attrs']['style']['spacing']['padding'];
            $padding_styles = [];
            
            foreach (['top', 'right', 'bottom', 'left'] as $side) {
                if (!empty($padding[$side])) {
                    $value = $this->convert_value_to_css($padding[$side]);
                    $padding_styles[] = "padding-{$side}: {$value}";
                }
            }
            
            if (!empty($padding_styles)) {
                $styles[] = ".{$container_id} { " . implode('; ', $padding_styles) . "; }";
            }
        }
        
        return implode("\n", $styles);
    }
    
    /**
     * Convert WordPress preset values to CSS.
     *
     * @param string $value The value to convert
     * @return string CSS value
     */
    private function convert_value_to_css(string $value): string {
        // Handle preset values (e.g., var:preset|spacing|xl)
        if (strpos($value, 'var:') === 0) {
            $preset_path = substr($value, 4);
            $css_var_path = str_replace('|', '--', $preset_path);
            return "var(--wp--{$css_var_path})";
        }
        
        return $value;
    }
    
    /**
     * Render a single modal container in the footer.
     * 
     * This container will be populated dynamically via AJAX when triggers are clicked.
     */
    public function render_single_modal_container(): void {
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
                        
                        // Set content with inline styles
                        this.content = `
                            ${data.styles ? `<style>${data.styles}</style>` : ''}
                            <article class="modal-post-content">
                                <header class="modal-post-header">
                                    <h2>${data.title}</h2>
                                </header>
                                <div class="modal-post-body">
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