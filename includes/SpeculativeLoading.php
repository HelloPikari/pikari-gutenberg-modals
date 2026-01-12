<?php
/**
 * Speculative Loading Support
 *
 * Implements WordPress 6.8+ Speculation Rules API to prefetch modal content
 * REST API endpoints when users hover over modal triggers.
 *
 * @package PikariGutenbergModals
 * @since 1.0.0
 */

namespace Pikari\GutenbergModals;

/**
 * SpeculativeLoading class handles prefetching of modal content.
 *
 * Uses the WordPress Speculation Rules API (WP 6.8+) to hint browsers
 * to prefetch modal content before users click, resulting in near-instant
 * modal loading.
 */
class SpeculativeLoading
{
    /**
     * Collection of modal post IDs found during page rendering.
     *
     * @var array<int>
     */
    private static array $modal_post_ids = [];

    /**
     * Constructor - registers prefetch hints for modal content.
     *
     * Uses <link rel="prefetch"> for REST API content, which works in all browsers.
     * WordPress 6.8's default Speculation Rules will handle prefetching the
     * page permalinks (the anchor href), while we prefetch the REST API content
     * that the modal actually loads.
     */
    public function __construct()
    {
        // Add prefetch hints via link rel="prefetch" in the document head
        // This works in all browsers and prefetches the REST API content
        add_action('wp_head', [$this, 'add_prefetch_link_hints'], 99);
    }

    /**
     * Register a modal post ID for prefetching.
     *
     * Called by BlockSupport when processing modal triggers during rendering.
     *
     * @param int $post_id The post ID to prefetch.
     */
    public static function register_modal_post_id( int $post_id ): void
    {
        if ( $post_id > 0 && ! in_array($post_id, self::$modal_post_ids, true) ) {
            self::$modal_post_ids[] = $post_id;
        }
    }

    /**
     * Get all registered modal post IDs.
     *
     * @return array<int> Array of post IDs.
     */
    public static function get_modal_post_ids(): array
    {
        return self::$modal_post_ids;
    }

    /**
     * Clear registered modal post IDs (useful for testing).
     */
    public static function clear_modal_post_ids(): void
    {
        self::$modal_post_ids = [];
    }

    /**
     * Add prefetch link hints for modal REST API content.
     *
     * Outputs <link rel="prefetch"> elements in the document head for each
     * modal content REST API URL. Works in all modern browsers.
     *
     * Note: WordPress 6.8's default Speculation Rules will also prefetch the
     * page permalinks (anchor href) when users hover over modal trigger links.
     *
     * TODO - Switch to using the Speculation Rules API directly with the wp_load_speculation_rules filter
     */
    public function add_prefetch_link_hints(): void
    {
        $post_ids = self::get_modal_post_ids();

        if ( empty($post_ids) ) {
            return;
        }

        $urls = $this->get_modal_api_urls($post_ids);

        foreach ( $urls as $url ) {
            printf(
                '<link rel="prefetch" href="%s" as="fetch" crossorigin="anonymous">%s',
                esc_url($url),
                "\n"
            );
        }
    }

    /**
     * Generate REST API URLs for modal content.
     *
     * @param array<int> $post_ids Array of post IDs.
     * @return array<string> Array of REST API URLs.
     */
    private function get_modal_api_urls( array $post_ids ): array
    {
        $urls     = [];
        $rest_url = rest_url('pikari-gutenberg-modals/v1/modal-content/');

        foreach ( $post_ids as $post_id ) {
            // Verify post exists and is published before adding URL
            $post = get_post($post_id);
            if ( $post && $post->post_status === 'publish' ) {
                $urls[] = $rest_url . $post_id;
            }
        }

        /**
         * Filter the modal content REST API URLs to prefetch.
         *
         * @param array $urls     Array of REST API URLs.
         * @param array $post_ids Array of post IDs.
         */
        return apply_filters('pikari_gutenberg_modals_prefetch_urls', $urls, $post_ids);
    }
}
