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
                    'id'       => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'description'       => __( 'Post ID to retrieve content for.', 'pikari-gutenberg-modals' ),
                    ),
                    'modal_id' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => __( 'Unique modal identifier for HTTP cache differentiation.', 'pikari-gutenberg-modals' ),
                    ),
                ),
                'schema'              => [ $this, 'get_item_schema' ],
            )
        );
    }

    /**
     * Get the REST schema for the modal-content endpoint.
     *
     * Enables schema discovery via OPTIONS requests per WP REST API best practices.
     *
     * @return array The JSON Schema for a modal content response.
     */
    public function get_item_schema()
    {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'pikari-modal-content',
            'type'       => 'object',
            'properties' => array(
                'id'          => array(
                    'type'        => 'integer',
                    'description' => __( 'The post ID.', 'pikari-gutenberg-modals' ),
                    'context'     => array( 'view' ),
                    'readonly'    => true,
                ),
                'title'       => array(
                    'type'        => 'string',
                    'description' => __( 'The post title (plain text, no HTML).', 'pikari-gutenberg-modals' ),
                    'context'     => array( 'view' ),
                    'readonly'    => true,
                ),
                'content'     => array(
                    'type'        => 'string',
                    'description' => __( 'The rendered post content HTML.', 'pikari-gutenberg-modals' ),
                    'context'     => array( 'view' ),
                    'readonly'    => true,
                ),
                'styles'      => array(
                    'type'        => 'string',
                    'description' => __( 'Block support CSS for the rendered content.', 'pikari-gutenberg-modals' ),
                    'context'     => array( 'view' ),
                    'readonly'    => true,
                ),
                'blockStyles' => array(
                    'type'        => 'object',
                    'description' => __( 'Block stylesheet URLs for dynamic loading.', 'pikari-gutenberg-modals' ),
                    'context'     => array( 'view' ),
                    'readonly'    => true,
                    'properties'  => array(
                        'urls' => array(
                            'type'  => 'array',
                            'items' => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'scripts'     => array(
                    'type'        => 'array',
                    'description' => __( 'Classic scripts the content needs, dependencies first.', 'pikari-gutenberg-modals' ),
                    'context'     => array( 'view' ),
                    'readonly'    => true,
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'handle' => array( 'type' => 'string' ),
                            'src'    => array( 'type' => array( 'string', 'null' ) ),
                            'data'   => array( 'type' => 'string' ),
                            'before' => array( 'type' => 'string' ),
                            'after'  => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'type'        => array(
                    'type'        => 'string',
                    'description' => __( 'The post type slug.', 'pikari-gutenberg-modals' ),
                    'context'     => array( 'view' ),
                    'readonly'    => true,
                ),
            ),
        );
    }

    /**
     * Whether this public endpoint may return a post's content.
     *
     * Only what a logged-out visitor could already see on the frontend: a
     * public status on a viewable post type, and no password. Checking the
     * status alone served password-protected posts in full, and published
     * posts of non-public types — WPForms form definitions, navigation menus,
     * global styles — to anyone who asked by ID.
     *
     * @internal Public only so the behaviour can be tested directly.
     *
     * @param \WP_Post|object $post The post.
     * @return bool True when the content may be returned.
     */
    public function is_content_viewable( $post ): bool
    {
        return is_post_publicly_viewable( $post ) && '' === (string) $post->post_password;
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

        // The same 404 for a hidden post as for a missing one, so the
        // endpoint never confirms that a post it will not show exists.
        if ( ! $post || ! $this->is_content_viewable( $post ) ) {
            return new \WP_Error(
                'post_not_found',
                __('Post not found or not published.', 'pikari-gutenberg-modals'),
                array( 'status' => 404 )
            );
        }

        // Generate ETag based on post content, modification time and plugin
        // version — see generate_etag() for why the version belongs in it.
        // A request carrying the REST nonce runs as the logged-in viewer, and
        // their render (nonces included) must never validate as anyone else's.
        $user_id       = get_current_user_id();
        $simulate      = self::should_simulate_frontend();
        $etag          = $this->generate_etag($post, PIKARI_GUTENBERG_MODALS_VERSION, $simulate, $user_id);
        $last_modified = strtotime($post->post_modified_gmt);

        // Check for conditional request (If-None-Match or If-Modified-Since).
        // Never for a logged-in viewer: their response is never stored, so a
        // 304 cannot be right for them, and the If-Modified-Since check ignores
        // the ETag, so it would tell them to keep an anonymous body.
        if ( 0 === $user_id ) {
            $cached_response = $this->check_conditional_request($request, $etag, $last_modified);
            if ( $cached_response !== null ) {
                return $cached_response;
            }
        }

        // Instantiating BlockSupport here registers render_block filters that
        // affect only the do_blocks() call below.
        $block_support = new BlockSupport();

        // A REST request runs neither wp_enqueue_scripts nor wp_footer, and
        // whole classes of stylesheet only reach the queue inside them.
        //
        // block-style-variation-styles takes both halves: core calls
        // wp_enqueue_style() on it during wp_enqueue_scripts, before it is
        // registered, so WP_Dependencies parks it in queued_before_register;
        // registration then happens during do_blocks() via render_block_data
        // and promotes it into the queue. Skip the header and it is never
        // parked, so it never lands. Plugins such as WPForms enqueue in
        // wp_footer instead, once they know what the page rendered.
        if ( $simulate ) {
            $this->simulate_enqueue_scripts( $post );
        }

        // Snapshot AFTER the simulated header. Taking it before would count
        // every handle the theme and WordPress itself enqueue on any page —
        // global-styles among them — as newly added by this render, and
        // duplicate their inline CSS into the response.
        $before_queue   = wp_styles()->queue;
        $before_scripts = wp_scripts()->queue;

        // Anything building a URL from REQUEST_URI during the render would
        // otherwise point at this REST route — WPForms' form action among them.
        $content_data = $this->with_request_uri(
            $this->request_uri_for_post( $post ),
            function () use ( $block_support, $post ) {
                return $block_support->get_post_content_with_styles( $post );
            }
        );

        if ( $simulate ) {
            $this->simulate_footer();
        }

        // Extract raw CSS from the <style> tag returned by get_post_content_with_styles().
        // preg_match is safe here because the input is always a single <style> tag generated
        // by BlockSupport (not arbitrary HTML), so the regex reliably captures the CSS content.
        $styles = '';
        if ( ! empty( $content_data['styles'] ) ) {
            if ( preg_match( '/<style[^>]*>(.*?)<\/style>/s', $content_data['styles'], $matches ) ) {
                $styles = $matches[1];
            }
        }

        // Collect block styles from two sources:
        // 1. Registry-based: block type style_handles (core block stylesheets)
        // 2. Render-enqueued: theme per-block styles added during do_blocks()
        $block_style_collector = new BlockStyleCollector();
        $block_styles          = $block_style_collector->get_block_styles_for_content( $post->post_content );
        $render_styles         = $block_style_collector->collect_render_enqueued_styles( $before_queue );

        // Scripts the render enqueued, with what the client needs to run them.
        $block_script_collector = new BlockScriptCollector();
        $scripts                = $block_script_collector->collect_render_enqueued_scripts( $before_scripts );

        // Merge render-enqueued URLs with registry-based URLs (deduplicated).
        $merged_urls = array_merge( $block_styles['urls'], $render_styles['urls'] );
        $all_urls    = array_values( array_unique( $merged_urls ) );

        // Append render-enqueued inline CSS (path-based theme styles) to block support styles.
        if ( ! empty( $render_styles['css'] ) ) {
            $styles .= $render_styles['css'];
        }

        // Prepare response data
        $response_data = array(
            'id'          => $post->ID,
            'title'       => wp_strip_all_tags( get_the_title( $post ) ),
            'content'     => $content_data['content'],
            'styles'      => $styles,
            'blockStyles' => array( 'urls' => $all_urls ),
            'scripts'     => $scripts,
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
     * Whether to run the frontend enqueue lifecycle for this request.
     *
     * Firing wp_footer on a public endpoint runs every plugin's footer hook,
     * which is a real cost and a real risk: the output is discarded, but the
     * side effects are not. Sites that hit a misbehaving plugin can turn the
     * simulation off and accept that plugin stylesheets go missing from
     * modal content.
     *
     * @return bool True when the lifecycle should be simulated.
     */
    public static function should_simulate_frontend(): bool
    {
        /**
         * Filter whether the modal-content endpoint simulates the frontend
         * enqueue lifecycle to collect stylesheets.
         *
         * @param bool $simulate Default true.
         */
        return (bool) apply_filters( 'pikari_gutenberg_modals_simulate_frontend', true );
    }

    /**
     * Run wp_enqueue_scripts as a frontend request would.
     *
     * The global post is set first so callbacks that inspect the current post
     * to decide what to enqueue see the post the modal is about to show,
     * rather than nothing at all.
     *
     * @internal Public only so the behaviour can be tested directly.
     *
     * @param \WP_Post $post The post being rendered.
     */
    public function simulate_enqueue_scripts( \WP_Post $post ): void
    {
        if ( did_action( 'wp_enqueue_scripts' ) ) {
            return;
        }

        $original_post   = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $post;

        // Callbacks print as well as enqueue; only the queue is wanted here.
        try {
            ob_start();
            do_action( 'wp_enqueue_scripts' );
        } finally {
            ob_end_clean();
            $GLOBALS['post'] = $original_post;
        }
    }

    /**
     * Run wp_footer as a frontend request would.
     *
     * Plugins that render their own markup — WPForms among them — enqueue
     * their stylesheets here rather than in the header, because they only
     * know which assets are needed once the content has rendered.
     *
     * @internal Public only so the behaviour can be tested directly.
     */
    public function simulate_footer(): void
    {
        if ( did_action( 'wp_footer' ) ) {
            return;
        }

        // Our own container renderer is hooked to wp_footer twice over by
        // now — once from the instance above and once from the one
        // bootstrapped on init — and would render every modal template part
        // into a response that only wants the styles.
        BlockSupport::suspend_container_render( true );

        // wp_maybe_inline_styles() inlines small stylesheets into the page and
        // sets src to false on every handle it takes, because a printed page no
        // longer needs the URL. This response does: BlockStyleCollector reads
        // exactly that src to build blockStyles.urls. Measured on WordPress 7.1
        // — firing wp_footer without this dropped wp-block-paragraph, -heading
        // and -group from the URL list. (The CSS itself still arrived, via the
        // collector's path and after fallbacks, duplicated between them.)
        remove_action( 'wp_footer', 'wp_maybe_inline_styles', 1 );

        // Every hook here belongs to another plugin. A fatal or an exception in
        // one of them must not leave this request with an unbalanced output
        // buffer, a core callback unhooked, or container rendering suspended —
        // the response is still written after this returns.
        try {
            ob_start();
            do_action( 'wp_footer' );
        } finally {
            ob_end_clean();
            add_action( 'wp_footer', 'wp_maybe_inline_styles', 1 );
            BlockSupport::suspend_container_render( false );
        }
    }

    /**
     * The request URI a frontend request for this post would carry.
     *
     * @internal Public only so the behaviour can be tested directly.
     *
     * @param \WP_Post|object $post The post being rendered.
     * @return string Path and query, e.g. "/book-a-conversation/".
     */
    public function request_uri_for_post( $post ): string
    {
        $parts = wp_parse_url( (string) get_permalink( $post ) );
        $uri   = $parts['path'] ?? '/';

        if ( ! empty( $parts['query'] ) ) {
            $uri .= '?' . $parts['query'];
        }

        return $uri;
    }

    /**
     * Run a callback with REQUEST_URI set to another address.
     *
     * Both add_query_arg() and remove_query_arg() fall back to REQUEST_URI
     * when given no URL, which inside this endpoint is the REST route. The original
     * is restored even when the callback throws — the rest of the request
     * still runs after it.
     *
     * @internal Public only so the behaviour can be tested directly.
     *
     * @param string   $uri      The request URI the callback should see.
     * @param callable $callback The work to run.
     * @return mixed The callback's return value.
     */
    public function with_request_uri( string $uri, callable $callback )
    {
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput -- restored verbatim, never output.
        $had_uri      = array_key_exists( 'REQUEST_URI', $_SERVER );
        $original_uri = $_SERVER['REQUEST_URI'] ?? null;

        $_SERVER['REQUEST_URI'] = $uri;

        try {
            return $callback();
        } finally {
            if ( $had_uri ) {
                $_SERVER['REQUEST_URI'] = $original_uri;
            } else {
                unset( $_SERVER['REQUEST_URI'] );
            }
        }
        // phpcs:enable
    }

    /**
     * Generate an ETag for a post based on content, modification time and the
     * plugin version.
     *
     * The version is in the hash because this endpoint's output can change
     * without the post changing: 2.1.0 began collecting plugin and
     * block-style-variation CSS that earlier versions never returned. An ETag
     * derived from the post alone would tell a browser or CDN holding the
     * older body that nothing had changed, and it would keep serving modal
     * content with the missing styles until someone re-saved the post.
     *
     * @internal Public only so the behaviour can be tested directly.
     *
     * @param \WP_Post|object $post     The post object.
     * @param string          $version  The plugin version.
     * @param bool            $simulate Whether the frontend lifecycle is simulated.
     * @param int             $user_id  The viewer the content was rendered for; 0 when logged out.
     * @return string The ETag value (quoted string).
     */
    public function generate_etag( $post, string $version, bool $simulate = true, int $user_id = 0 )
    {
        // Everything that shapes the response, not just everything that shapes
        // the post: the simulate flag decides whether plugin and
        // block-style-variation CSS is collected at all, so a site toggling the
        // filter would otherwise be told 304 against the other shape. A
        // logged-in viewer's render carries their own nonces, so it must never
        // validate against anyone else's body.
        $hash_data = implode(
            '-',
            [
                $post->ID,
                $post->post_modified_gmt,
                md5($post->post_content),
                $version,
                $simulate ? 'sim' : 'nosim',
                $user_id,
            ]
        );

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
        // WP_REST_Response(null, 304) is correct for WP 6.8+: serve_request()
        // checks `null !== $result` and skips body output when data is null.
        $response = new \WP_REST_Response( null, 304 );

        // A 304 only ever answers a logged-out request, and carries the same
        // validators, Cache-Control and Vary as the response it stands for.
        foreach ( $this->cache_headers( $etag, (int) $last_modified, false ) as $name => $value ) {
            $response->header( $name, $value );
        }

        return $response;
    }

    /**
     * The cache headers for a modal-content response.
     *
     * A logged-in viewer's render is theirs alone — WPForms, for one, adds a
     * per-user nonce — so it is never stored. Core sends its own no-cache
     * headers for a logged-in REST request after these, and they win;
     * private, no-store is the backstop for a site that filters
     * rest_send_nocache_headers off. Every response varies on the REST nonce
     * header, because one URL
     * answers both kinds of request and a browser holding the anonymous body
     * would otherwise serve it to the authenticated fetch.
     *
     * @internal Public only so the behaviour can be tested directly.
     *
     * @param string $etag          The ETag value.
     * @param int    $last_modified The last modified timestamp.
     * @param bool   $personal      Whether the content was rendered for a logged-in viewer.
     * @return array<string, string> Header name => value.
     */
    public function cache_headers( string $etag, int $last_modified, bool $personal ): array
    {
        $headers = array(
            'ETag'          => $etag,
            'Last-Modified' => gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT',
            'Vary'          => 'Accept, Accept-Encoding, X-WP-Nonce',
        );

        if ( $personal ) {
            $headers['Cache-Control'] = 'private, no-store';

            return $headers;
        }

        /**
         * Filter the cache duration for modal content REST API responses.
         *
         * Applies to logged-out visitors only; a logged-in viewer's response
         * is never stored.
         *
         * @param int $duration Cache duration in seconds. Default 3600 (1 hour).
         */
        $cache_duration = apply_filters( 'pikari_gutenberg_modals_cache_duration', HOUR_IN_SECONDS );

        // Public cache, revalidate after max-age.
        $headers['Cache-Control'] = 'public, max-age=' . $cache_duration . ', must-revalidate';

        return $headers;
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
        foreach ( $this->cache_headers( $etag, (int) $last_modified, is_user_logged_in() ) as $name => $value ) {
            $response->header( $name, $value );
        }
    }
}
