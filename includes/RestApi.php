<?php
/**
 * REST API functionality for Pikari Gutenberg Modals
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

/**
 * RestApi class handles all REST API endpoints for the plugin
 */
class RestApi
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes()
    {
        // Register modal content endpoint
        register_rest_route(
            'pikari-gutenberg-modals/v1',
            '/modal-content/(?P<id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => [$this, 'get_modal_content'],
                'permission_callback' => '__return_true', // Public endpoint for frontend use
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => function ( $param ) {
                            return is_numeric($param);
                        },
                        'description'       => __('Post ID to retrieve content for.', 'pikari-gutenberg-modals'),
                    ),
                ),
            )
        );
    }

    /**
     * Get modal content with styles via REST API.
     *
     * Returns both the rendered content and associated block support styles
     * for proper display in modal windows. Implements HTTP caching with
     * ETag and Last-Modified headers for browser cache optimization.
     *
     * @param \WP_REST_Request $request The REST request object.
     * @return \WP_REST_Response|\WP_Error The modal content or error.
     */
    public function get_modal_content( $request )
    {
        $post_id = $request->get_param('id');

        // Get the post
        $post = get_post($post_id);

        if ( ! $post || $post->post_status !== 'publish' ) {
            return new \WP_Error(
                'post_not_found',
                __('Post not found or not published.', 'pikari-gutenberg-modals'),
                array( 'status' => 404 )
            );
        }

        // Generate ETag based on post content and modification time
        $etag          = $this->generate_etag($post);
        $last_modified = strtotime($post->post_modified_gmt);

        // Check for conditional request (If-None-Match or If-Modified-Since)
        $cached_response = $this->check_conditional_request($request, $etag, $last_modified);
        if ( $cached_response !== null ) {
            return $cached_response;
        }

        // Use the Block_Support class method to get content with properly captured styles
        $block_support = new BlockSupport();

        // Get content and styles using the working method
        $content_data = $block_support->get_post_content_with_styles($post);

        // Extract CSS from style tag if present
        $styles = '';
        if ( ! empty($content_data['styles']) ) {
            // Extract content between style tags
            if ( preg_match('/<style[^>]*>(.*?)<\/style>/s', $content_data['styles'], $matches) ) {
                $styles = $matches[1];
            }
        }

        // Get block stylesheet URLs for dynamic loading
        $block_style_collector = new BlockStyleCollector();
        $block_styles          = $block_style_collector->get_block_styles_for_content($post->post_content);

        // Prepare response data
        $response_data = array(
            'id'          => $post->ID,
            'title'       => get_the_title($post),
            'content'     => $content_data['content'],
            'styles'      => $styles,
            'blockStyles' => $block_styles,
            'type'        => $post->post_type,
        );

        /**
         * Filter the modal content response.
         *
         * @param array $response_data The response data
         * @param \WP_Post $post The post object
         */
        $response_data = apply_filters('pikari_gutenberg_modals_content_response', $response_data, $post);

        // Prepare response with cache headers
        $response = rest_ensure_response($response_data);
        $this->add_cache_headers($response, $etag, $last_modified);

        return $response;
    }

    /**
     * Generate an ETag for a post based on content and modification time.
     *
     * @param \WP_Post $post The post object.
     * @return string The ETag value (quoted string).
     */
    private function generate_etag( $post )
    {
        // Create hash from post ID, modification time, and content hash
        $hash_data = $post->ID . '-' . $post->post_modified_gmt . '-' . md5($post->post_content);
        return '"' . md5($hash_data) . '"';
    }

    /**
     * Check for conditional request headers and return 304 if content unchanged.
     *
     * @param \WP_REST_Request $request       The REST request object.
     * @param string           $etag          The current ETag value.
     * @param int              $last_modified The last modified timestamp.
     * @return \WP_REST_Response|null Response with 304 status or null to continue.
     */
    private function check_conditional_request( $request, $etag, $last_modified )
    {
        // Check If-None-Match header (ETag validation)
        $if_none_match = $request->get_header('If-None-Match');
        if ( $if_none_match !== null ) {
            // Handle multiple ETags (comma-separated)
            $client_etags = array_map('trim', explode(',', $if_none_match));
            if ( in_array($etag, $client_etags, true) || in_array('*', $client_etags, true) ) {
                return $this->create_304_response($etag, $last_modified);
            }
        }

        // Check If-Modified-Since header (date validation)
        $if_modified_since = $request->get_header('If-Modified-Since');
        if ( $if_modified_since !== null && $if_none_match === null ) {
            $client_time = strtotime($if_modified_since);
            if ( $client_time !== false && $last_modified <= $client_time ) {
                return $this->create_304_response($etag, $last_modified);
            }
        }

        return null;
    }

    /**
     * Create a 304 Not Modified response with cache headers.
     *
     * @param string $etag          The ETag value.
     * @param int    $last_modified The last modified timestamp.
     * @return \WP_REST_Response The 304 response.
     */
    private function create_304_response( $etag, $last_modified )
    {
        $response = new \WP_REST_Response(null, 304);
        $response->header('ETag', $etag);
        $response->header('Last-Modified', gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
        return $response;
    }

    /**
     * Add cache headers to the response.
     *
     * @param \WP_REST_Response $response      The response object.
     * @param string            $etag          The ETag value.
     * @param int               $last_modified The last modified timestamp.
     */
    private function add_cache_headers( $response, $etag, $last_modified )
    {
        /**
         * Filter the cache duration for modal content REST API responses.
         *
         * @param int $duration Cache duration in seconds. Default 3600 (1 hour).
         */
        $cache_duration = apply_filters('pikari_gutenberg_modals_cache_duration', HOUR_IN_SECONDS);

        // Cache-Control header: public cache, revalidate after max-age
        $response->header('Cache-Control', 'public, max-age=' . $cache_duration . ', must-revalidate');

        // ETag for cache validation
        $response->header('ETag', $etag);

        // Last-Modified for date-based validation
        $response->header('Last-Modified', gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');

        // Vary header to ensure proper caching with different Accept headers
        $response->header('Vary', 'Accept, Accept-Encoding');
    }
}
