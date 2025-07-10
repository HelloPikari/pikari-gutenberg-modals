<?php
/**
 * Modal Handler
 *
 * Manages modal content processing, caching, and security validation.
 * Provides extensibility points for developers to customize modal behavior.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class ModalHandler
{
    /**
     * Cache group for modal content.
     *
     * @var string
     */
    private const CACHE_GROUP = 'pikari_gutenberg_modals';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Add hooks for modal content processing
        add_filter('pikari_gutenberg_modals_content', [$this, 'process_modal_content'], 10, 3);
        add_filter('pikari_gutenberg_modals_cache_duration', [$this, 'get_cache_duration']);
        
        // Add security filters
        add_filter('pikari_gutenberg_modals_allowed_domains', [$this, 'get_allowed_domains']);
        add_filter('pikari_gutenberg_modals_blocked_domains', [$this, 'get_blocked_domains']);
    }
    
    /**
     * Process modal content before rendering.
     *
     * This method provides a central processing point for all modal content.
     * Developers can hook into this to modify content before display.
     *
     * Examples of processing:
     * - Strip unwanted HTML tags
     * - Add wrapper elements
     * - Inject custom styles
     * - Transform content based on type
     *
     * @param string $content The content HTML
     * @param string $content_type The content type (post/page/url)
     * @param mixed $content_id The content ID or URL
     * @return string Processed content
     */
    public function process_modal_content(string $content, string $content_type, $content_id): string
    {
        // Add loading indicator for external content
        if ($content_type === 'url') {
            $content = $this->add_loading_indicator($content);
        }
        
        // Add responsive wrapper
        $content = sprintf(
            '<div class="modal-content-wrapper modal-content-type-%s" data-content-id="%s">%s</div>',
            esc_attr($content_type),
            esc_attr($content_id),
            $content
        );
        
        // Allow developers to further process content
        return apply_filters(
            'pikari_gutenberg_modals_processed_content',
            $content,
            $content_type,
            $content_id
        );
    }
    
    /**
     * Add loading indicator for iframe content.
     *
     * @param string $content The iframe HTML
     * @return string Content with loading indicator
     */
    private function add_loading_indicator(string $content): string
    {
        $loading_text = esc_html__('Loading content...', 'pikari-gutenberg-modals');
        
        return sprintf(
            '<div class="modal-loading-wrapper">
                <div class="modal-loading-indicator" aria-hidden="true">%s</div>
                %s
            </div>',
            $loading_text,
            $content
        );
    }
    
    /**
     * Get cache duration for external content.
     *
     * @return int Cache duration in seconds (default: 1 hour)
     */
    public function get_cache_duration(): int
    {
        // Allow customization via constant
        if (defined('PIKARI_GUTENBERG_MODALS_CACHE_DURATION')) {
            return (int) PIKARI_GUTENBERG_MODALS_CACHE_DURATION;
        }
        
        return HOUR_IN_SECONDS; // WordPress constant = 3600
    }
    
    /**
     * Get allowed domains for external content.
     *
     * @return array List of allowed domains
     */
    public function get_allowed_domains(): array
    {
        $allowed = [
            // Add commonly trusted domains
            parse_url(home_url(), PHP_URL_HOST),
        ];
        
        return apply_filters('pikari_gutenberg_modals_allowed_domains_list', $allowed);
    }
    
    /**
     * Get blocked domains for security.
     *
     * @return array List of blocked domains
     */
    public function get_blocked_domains(): array
    {
        $blocked = [
            // Add known malicious domains if needed
        ];
        
        return apply_filters('pikari_gutenberg_modals_blocked_domains_list', $blocked);
    }
    
    /**
     * Validate URL for security.
     *
     * Performs comprehensive URL validation including:
     * - Scheme validation (HTTP/HTTPS only)
     * - Domain allowlist/blocklist checking
     * - Local URL detection
     * - Malformed URL detection
     *
     * @param string $url The URL to validate
     * @return bool Whether the URL is valid and safe
     */
    public static function validate_url(string $url): bool
    {
        // Basic validation
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        // Use WordPress URL validation
        $url = esc_url_raw($url);
        if (empty($url)) {
            return false;
        }
        
        // Parse URL components
        $parsed = wp_parse_url($url);
        
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }
        
        // Only allow HTTP(S) schemes
        if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
            return false;
        }
        
        // Check against blocked domains
        $blocked_domains = apply_filters('pikari_gutenberg_modals_blocked_domains', []);
        foreach ($blocked_domains as $blocked) {
            if (stripos($parsed['host'], $blocked) !== false) {
                return false;
            }
        }
        
        // If allowlist is defined, check against it
        $allowed_domains = apply_filters('pikari_gutenberg_modals_allowed_domains', []);
        if (!empty($allowed_domains)) {
            $domain_allowed = false;
            foreach ($allowed_domains as $allowed) {
                if (stripos($parsed['host'], $allowed) !== false) {
                    $domain_allowed = true;
                    break;
                }
            }
            if (!$domain_allowed) {
                return false;
            }
        }
        
        // Check for local/private IPs (security measure)
        if (self::is_local_url($parsed['host'])) {
            // Only allow local URLs in development environments
            return wp_get_environment_type() === 'local';
        }
        
        return true;
    }
    
    /**
     * Check if URL points to local/private network.
     *
     * @param string $host The hostname to check
     * @return bool Whether the host is local/private
     */
    private static function is_local_url(string $host): bool
    {
        // Check for localhost variations
        $local_hosts = ['localhost', '127.0.0.1', '::1'];
        if (in_array($host, $local_hosts, true)) {
            return true;
        }
        
        // Check for private IP ranges
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        
        return false;
    }
}
