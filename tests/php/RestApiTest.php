<?php
/**
 * Frontend-lifecycle simulation in the modal-content endpoint.
 *
 * The endpoint renders a post outside the frontend request it would normally
 * be rendered in, and two whole classes of stylesheet only exist inside that
 * request: per-block global styles (the `block-style-variation-styles` handle
 * is registered during wp_enqueue_scripts) and third-party plugin assets
 * (WPForms enqueues on wp_footer). Measured on a real install — neither
 * handle is so much as registered in REST context without firing them.
 *
 * @package Pikari\Tests\GutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\RestApi;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

class RestApiTest extends TestCase
{
    public function test_simulation_is_on_by_default(): void
    {
        Functions\when( 'apply_filters' )->returnArg( 2 );

        $this->assertTrue( RestApi::should_simulate_frontend() );
    }

    public function test_simulation_can_be_switched_off_by_filter(): void
    {
        Filters\expectApplied( 'pikari_gutenberg_modals_simulate_frontend' )
            ->once()
            ->with( true )
            ->andReturn( false );

        $this->assertFalse( RestApi::should_simulate_frontend() );
    }

    /**
     * A truthy non-boolean from a filter must not leak out as itself — the
     * caller branches on this and a string would silently behave as true
     * while failing a strict comparison somewhere else.
     */
    public function test_simulation_flag_is_always_a_boolean(): void
    {
        Filters\expectApplied( 'pikari_gutenberg_modals_simulate_frontend' )
            ->once()
            ->andReturn( 'yes' );

        $this->assertTrue( RestApi::should_simulate_frontend() );
    }

    /**
     * wp_maybe_inline_styles() is hooked to wp_footer at priority 1. It
     * inlines small stylesheets and then sets src to false on each handle it
     * took, because a printed page no longer needs the URL. This response
     * does — BlockStyleCollector reads exactly that src — so the simulated
     * footer must run without it and put it back afterwards.
     *
     * Measured on WordPress 7.1 against a real site: without this, firing
     * wp_footer dropped wp-block-paragraph, wp-block-heading and
     * wp-block-group from blockStyles.urls — 5 URLs became 2. The CSS itself
     * still arrived, through the collector's path and after fallbacks, so the
     * loss is the URLs rather than the bytes.
     */
    public function test_simulated_footer_runs_without_inlining_styles(): void
    {
        // Constructed before the expectations below: the constructor calls
        // add_action() itself, and would otherwise consume one of them.
        $api = new RestApi();

        Functions\when( 'did_action' )->justReturn( 0 );

        Functions\expect( 'remove_action' )
            ->once()
            ->with( 'wp_footer', 'wp_maybe_inline_styles', 1 );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'wp_footer', 'wp_maybe_inline_styles', 1 );

        Functions\expect( 'do_action' )->once()->with( 'wp_footer' );

        $level = ob_get_level();

        $api->simulate_footer();

        // The response is written after this runs, so an unbalanced output
        // buffer here would swallow or corrupt it.
        $this->assertSame( $level, ob_get_level() );
    }

    /**
     * Firing wp_footer a second time inside a request that has already run it
     * would re-run every footer hook on the site.
     */
    public function test_simulated_footer_does_nothing_once_wp_footer_has_run(): void
    {
        $api = new RestApi();

        Functions\when( 'did_action' )->justReturn( 1 );
        Functions\expect( 'do_action' )->never();

        $level = ob_get_level();

        $api->simulate_footer();

        $this->assertSame( $level, ob_get_level() );
    }

    /**
     * Build a post double for ETag hashing.
     *
     * @param string $content Post content.
     * @return object A stand-in for WP_Post.
     */
    private function post_double( string $content = 'Hello' ): object
    {
        $post                    = new \stdClass();
        $post->ID                = 365;
        $post->post_modified_gmt = '2026-09-01 10:00:00';
        $post->post_content      = $content;

        return $post;
    }

    /**
     * The ETag validates a cached response, and what the endpoint returns for
     * an unchanged post changed in 2.1.0 — the simulated frontend lifecycle
     * adds plugin and block-style-variation CSS that was not there before.
     * Without the plugin version in the hash, a browser or CDN holding the
     * older body revalidates, is told 304, and keeps serving content with the
     * missing styles until someone re-saves the post.
     */
    public function test_etag_changes_with_the_plugin_version(): void
    {
        $api  = new RestApi();
        $post = $this->post_double();

        $this->assertNotSame(
            $api->generate_etag( $post, '2.0.0' ),
            $api->generate_etag( $post, '2.1.0' )
        );
    }

    /**
     * The simulate filter is a second input to the response shape — it decides
     * whether plugin and block-style-variation CSS is collected at all — so a
     * site that toggles it hits exactly the stale-304 the version in the hash
     * was added to prevent.
     */
    public function test_etag_changes_with_the_simulate_flag(): void
    {
        $api  = new RestApi();
        $post = $this->post_double();

        $this->assertNotSame(
            $api->generate_etag( $post, '2.1.0', true ),
            $api->generate_etag( $post, '2.1.0', false )
        );
    }

    public function test_etag_is_stable_for_the_same_post_and_version(): void
    {
        $api  = new RestApi();
        $post = $this->post_double();

        $this->assertSame(
            $api->generate_etag( $post, '2.1.0' ),
            $api->generate_etag( $post, '2.1.0' )
        );
    }

    public function test_etag_still_changes_with_the_content(): void
    {
        $api = new RestApi();

        $this->assertNotSame(
            $api->generate_etag( $this->post_double( 'Hello' ), '2.1.0' ),
            $api->generate_etag( $this->post_double( 'Goodbye' ), '2.1.0' )
        );
    }

    /**
     * WPForms — and anything else calling add_query_arg() or
     * remove_query_arg() without a URL — builds links from REQUEST_URI. Inside
     * this endpoint that is the REST route itself, so a form's action pointed
     * at a GET-only endpoint. The render has to see the post's own address.
     */
    public function test_request_uri_is_the_post_address_while_the_callback_runs(): void
    {
        $api                    = new RestApi();
        $_SERVER['REQUEST_URI'] = '/wp-json/pikari-gutenberg-modals/v1/modal-content/365';

        $seen = $api->with_request_uri(
            '/book-a-conversation/',
            function () {
                return $_SERVER['REQUEST_URI'];
            }
        );

        $this->assertSame( '/book-a-conversation/', $seen );
        $this->assertSame( '/wp-json/pikari-gutenberg-modals/v1/modal-content/365', $_SERVER['REQUEST_URI'] );
    }

    /**
     * The render runs every plugin's block filters; one of them throwing must
     * not leave the rest of the request believing it is on another page.
     */
    public function test_request_uri_is_restored_when_the_callback_throws(): void
    {
        $api                    = new RestApi();
        $_SERVER['REQUEST_URI'] = '/wp-json/route';
        $thrown                 = false;

        try {
            $api->with_request_uri(
                '/page/',
                function () {
                    throw new \RuntimeException( 'A plugin failed mid-render.' );
                }
            );
        } catch ( \RuntimeException $e ) {
            $thrown = true;
        }

        $this->assertTrue( $thrown );
        $this->assertSame( '/wp-json/route', $_SERVER['REQUEST_URI'] );
    }

    public function test_an_unset_request_uri_stays_unset(): void
    {
        $api = new RestApi();
        unset( $_SERVER['REQUEST_URI'] );

        $api->with_request_uri(
            '/page/',
            function () {
            }
        );

        $this->assertArrayNotHasKey( 'REQUEST_URI', $_SERVER );
    }

    public function test_post_address_keeps_path_and_query(): void
    {
        Functions\when( 'get_permalink' )->justReturn( 'https://example.com/book-a-conversation/?lang=fr' );
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

        $this->assertSame(
            '/book-a-conversation/?lang=fr',
            ( new RestApi() )->request_uri_for_post( $this->post_double() )
        );
    }

    /**
     * Plain permalinks have no path at all — only ?page_id=.
     */
    public function test_post_address_without_a_path_starts_at_the_root(): void
    {
        Functions\when( 'get_permalink' )->justReturn( 'https://example.com/?page_id=365' );
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

        $this->assertSame(
            '/?page_id=365',
            ( new RestApi() )->request_uri_for_post( $this->post_double() )
        );
    }

    /**
     * Build a post object carrying what visibility is decided from.
     *
     * @param string $password The post password.
     * @return object Post double.
     */
    private function visibility_double( string $password = '' ): object
    {
        $post                = new \stdClass();
        $post->ID            = 365;
        $post->post_type     = 'page';
        $post->post_status   = 'publish';
        $post->post_password = $password;

        return $post;
    }

    /**
     * The endpoint is public, so it may only return what a logged-out visitor
     * could already see on the frontend. It checked post_status alone, which
     * served password-protected posts in full and published posts of
     * non-public types — WPForms form definitions among them.
     */
    public function test_a_publicly_viewable_post_without_a_password_is_served(): void
    {
        $post = $this->visibility_double();

        Functions\expect( 'is_post_publicly_viewable' )->once()->with( $post )->andReturn( true );

        $this->assertTrue( ( new RestApi() )->is_content_viewable( $post ) );
    }

    public function test_a_password_protected_post_is_not_served(): void
    {
        Functions\when( 'is_post_publicly_viewable' )->justReturn( true );

        $this->assertFalse( ( new RestApi() )->is_content_viewable( $this->visibility_double( 'qa-secret' ) ) );
    }

    public function test_a_post_that_is_not_publicly_viewable_is_not_served(): void
    {
        Functions\when( 'is_post_publicly_viewable' )->justReturn( false );

        $this->assertFalse( ( new RestApi() )->is_content_viewable( $this->visibility_double() ) );
    }

    /**
     * A logged-in viewer's content is rendered as that viewer — WPForms adds
     * a per-user nonce to it — so the ETag has to tell viewers apart, or a
     * browser holding the anonymous body is told 304 and keeps a form that
     * cannot be submitted.
     */
    public function test_etag_differs_between_viewers(): void
    {
        $api  = new RestApi();
        $post = $this->post_double();

        $this->assertNotSame(
            $api->generate_etag( $post, '2.2.3', true, 0 ),
            $api->generate_etag( $post, '2.2.3', true, 7 )
        );
    }

    /**
     * A logged-in viewer's render carries their own nonces, and a shared cache
     * that stored it would hand it to the next visitor. Core's own no-cache
     * headers normally win for a logged-in REST request; this is the backstop
     * for a site that filters rest_send_nocache_headers off.
     */
    public function test_a_logged_in_viewers_response_is_never_stored(): void
    {
        $headers = ( new RestApi() )->cache_headers( '"etag"', 1757808000, true );

        $this->assertSame( 'private, no-store', $headers['Cache-Control'] );
    }

    public function test_an_anonymous_response_stays_publicly_cacheable(): void
    {
        $headers = ( new RestApi() )->cache_headers( '"etag"', 1757808000, false );

        $this->assertSame( 'public, max-age=3600, must-revalidate', $headers['Cache-Control'] );
        $this->assertSame( '"etag"', $headers['ETag'] );
    }

    /**
     * The same URL answers anonymous and authenticated requests. Without the
     * nonce header in Vary, a browser that cached the anonymous body serves it
     * to the authenticated fetch without asking the server.
     */
    public function test_every_response_varies_on_the_rest_nonce(): void
    {
        if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
            define( 'HOUR_IN_SECONDS', 3600 );
        }

        $api = new RestApi();

        foreach ( [ true, false ] as $personal ) {
            $vary = array_map( 'trim', explode( ',', $api->cache_headers( '"etag"', 1757808000, $personal )['Vary'] ) );
            $this->assertContains( 'X-WP-Nonce', $vary );
        }
    }

    /**
     * A logged-out render depends only on what the ETag already hashes, so
     * the render stored under that ETag can answer the next logged-out
     * request without running the render, wp_enqueue_scripts and wp_footer
     * again.
     */
    public function test_a_stored_render_answers_for_its_etag(): void
    {
        Functions\when( 'apply_filters' )->returnArg( 2 );
        $render = [ 'id' => 87, 'content' => '<p>Nick</p>' ];

        Functions\expect( 'get_transient' )
            ->once()
            ->with( 'pikari_modal_content_' . md5( '"abc"' ) )
            ->andReturn( $render );

        $this->assertSame( $render, ( new RestApi() )->get_cached_content( '"abc"' ) );
    }

    public function test_nothing_stored_means_no_cached_render(): void
    {
        Functions\when( 'apply_filters' )->returnArg( 2 );
        Functions\when( 'get_transient' )->justReturn( false );

        $this->assertNull( ( new RestApi() )->get_cached_content( '"abc"' ) );
    }

    /**
     * Anything other than a stored render, say a value some other code left
     * under the key, must not be sent as modal content.
     */
    public function test_a_stored_value_that_is_not_a_render_is_ignored(): void
    {
        Functions\when( 'apply_filters' )->returnArg( 2 );
        Functions\when( 'get_transient' )->justReturn( 'not a render' );

        $this->assertNull( ( new RestApi() )->get_cached_content( '"abc"' ) );
    }

    /**
     * A render is kept for as long as a browser may keep it, so the server
     * copy is never staler than the HTTP cache already allows.
     */
    public function test_a_render_is_stored_for_the_cache_duration(): void
    {
        Filters\expectApplied( 'pikari_gutenberg_modals_cache_duration' )->andReturn( 600 );
        $render = [ 'id' => 87 ];

        $stored = [];
        Functions\when( 'set_transient' )->alias(
            function ( ...$args ) use ( &$stored ) {
                $stored[] = $args;
                return true;
            }
        );

        ( new RestApi() )->cache_content( '"abc"', $render );

        $this->assertSame( [ [ 'pikari_modal_content_' . md5( '"abc"' ), $render, 600 ] ], $stored );
    }

    /**
     * A duration of 0 turns the server copy off as well as the browser's. A
     * transient stored with 0 would never expire, so storing must not happen.
     */
    public function test_a_zero_cache_duration_neither_reads_nor_stores(): void
    {
        Filters\expectApplied( 'pikari_gutenberg_modals_cache_duration' )->andReturn( 0 );
        Functions\expect( 'get_transient' )->never();
        Functions\expect( 'set_transient' )->never();

        $api = new RestApi();
        $this->assertNull( $api->get_cached_content( '"abc"' ) );
        $api->cache_content( '"abc"', [ 'id' => 87 ] );
    }
}
