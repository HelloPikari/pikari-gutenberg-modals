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
        
        // Generate unique modal ID
        $modal_id = 'modal-' . wp_generate_password(8, false);
        
        // Schedule modal HTML rendering in footer
        $this->schedule_modal_render(
            $modal_id,
            $modal_config['content_type'],
            $modal_config['content_id'],
            $modal_config['link_data']
        );
        
        // Return interactive trigger span
        return $this->create_trigger_span($modal_id, $inner_html);
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
     * Schedule modal HTML to be rendered in the footer.
     * 
     * @param string $modal_id Unique modal identifier
     * @param string $content_type Content type (post/page/url)
     * @param string $content_id Content ID or URL
     * @param array $link_data Additional link configuration
     */
    private function schedule_modal_render(string $modal_id, string $content_type, string $content_id, array $link_data): void {
        add_action('wp_footer', function() use ($modal_id, $content_type, $content_id, $link_data) {
            $this->render_modal_html($modal_id, $content_type, $content_id, $link_data);
        });
    }
    
    /**
     * Create the trigger span element.
     * 
     * @param string $modal_id Modal identifier
     * @param string $inner_html Original span content
     * @return string Trigger span HTML
     */
    private function create_trigger_span(string $modal_id, string $inner_html): string {
        return sprintf(
            '<span class="has-modal-link modal-link-trigger" data-modal-id="%s" role="button" tabindex="0" style="cursor: pointer; text-decoration: underline; text-decoration-style: dashed;">%s</span>',
            esc_attr($modal_id),
            $inner_html
        );
    }
    
    /**
     * Render modal HTML
     * 
     * @param string $modal_id The modal ID
     * @param string $content_type The content type (post/page/url)
     * @param string $content_id The content ID or URL
     * @param array $settings Modal settings
     */
    private function render_modal_html(string $modal_id, string $content_type, string $content_id, array $settings = []): void {
        $content = $this->get_modal_content($content_type, $content_id);
        
        if (!$content) {
            return;
        }
        
        // Escape the modal ID for use in Alpine.js
        $alpine_modal_id = esc_js($modal_id);
        ?>
        <div 
            id="<?php echo esc_attr($modal_id); ?>"
            class="modal-overlay"
            x-data="{ open: false }"
            x-show="open"
            x-on:open-modal.window="if ($event.detail.id === '<?php echo $alpine_modal_id; ?>') open = true"
            x-on:click.self="open = false"
            x-on:keydown.escape.window="open = false"
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
                    x-on:click="open = false"
                    aria-label="<?php esc_attr_e('Close modal', 'modal-toolbar-button'); ?>"
                >
                    <span aria-hidden="true">&times;</span>
                </button>
                <div class="modal-body">
                    <?php echo $content; // Content is already escaped ?>
                </div>
            </div>
        </div>
        <?php
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
            error_log('Modal Toolbar Button: Post not found or not published: ' . $post_id);
            return null;
        }
        
        // Set up post data for proper filter application
        setup_postdata($post);
        
        // Get the content with filters applied
        $content = apply_filters('the_content', $post->post_content);
        
        // Build the complete modal content
        $modal_content = sprintf(
            '<article class="modal-post-content">
                <header class="modal-post-header">
                    <h2>%s</h2>
                </header>
                <div class="modal-post-body">
                    %s
                </div>
            </article>',
                esc_html(get_the_title($post)),
                $content
            );
        
        // Reset post data
        wp_reset_postdata();
        
        /**
         * Filter the modal post content.
         * 
         * @param string $modal_content The complete modal content HTML
         * @param WP_Post $post The post object
         */
        return apply_filters('modal_toolbar_post_content', $modal_content, $post);
    }
}