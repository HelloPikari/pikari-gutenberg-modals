<?php
/**
 * Tests for BlockStyleCollector render-enqueued style collection.
 *
 * @package Pikari\Tests\GutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\BlockStyleCollector;
use Brain\Monkey\Functions;

class BlockStyleCollectorTest extends TestCase {

    /**
     * BlockStyleCollector instance.
     *
     * @var BlockStyleCollector
     */
    private BlockStyleCollector $collector;

    protected function setUp(): void {
        parent::setUp();
        $this->collector = new BlockStyleCollector();
    }

    /**
     * Create a mock WP_Styles object with given queue and registered handles.
     *
     * @param array $queue      Array of handle strings in the queue.
     * @param array $registered Associative array of handle => config arrays.
     *                          Each config: ['src' => string, 'ver' => string, 'path' => string|null, 'after' => array|null]
     * @return object Mock WP_Styles object.
     */
    private function create_mock_wp_styles( array $queue, array $registered = [] ): object {
        $mock = new \stdClass();
        $mock->queue = $queue;
        $mock->registered = [];

        foreach ( $registered as $handle => $config ) {
            $dep = new \stdClass();
            $dep->src = $config['src'] ?? '';
            $dep->ver = $config['ver'] ?? '';
            $dep->extra = [];

            if ( ! empty( $config['path'] ) ) {
                $dep->extra['path'] = $config['path'];
            }

            if ( ! empty( $config['after'] ) ) {
                $dep->extra['after'] = $config['after'];
            }

            $mock->registered[ $handle ] = $dep;
        }

        return $mock;
    }

    /**
     * Test that new handles with URLs are captured.
     */
    public function test_collect_render_enqueued_styles_returns_new_urls(): void {
        $before_queue = [ 'wp-block-library' ];

        $mock_styles = $this->create_mock_wp_styles(
            [ 'wp-block-library', 'wp-block-button-theme-style' ],
            [
                'wp-block-button-theme-style' => [
                    'src' => '/wp-content/themes/twentytwentyfive/assets/css/button.css',
                    'ver' => '1.0',
                ],
            ]
        );

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );
        Functions\when( 'site_url' )->alias( function ( $path ) {
            return 'https://example.com' . $path;
        } );
        Functions\when( 'add_query_arg' )->alias( function ( $key, $value, $url ) {
            return $url . '?ver=' . $value;
        } );

        $result = $this->collector->collect_render_enqueued_styles( $before_queue );

        $this->assertArrayHasKey( 'urls', $result );
        $this->assertArrayHasKey( 'css', $result );
        $this->assertCount( 1, $result['urls'] );
        $this->assertStringContainsString( 'button.css', $result['urls'][0] );
    }

    /**
     * Test that path-based styles are captured as inline CSS.
     */
    public function test_collect_render_enqueued_styles_captures_path_based_inline_css(): void {
        $before_queue = [];

        // Create a temporary CSS file to read.
        $css_content = '.wp-block-button .wp-block-button__link { border-radius: 99px; }';
        $temp_file = tempnam( sys_get_temp_dir(), 'css_test_' );
        file_put_contents( $temp_file, $css_content );

        $mock_styles = $this->create_mock_wp_styles(
            [ 'wp-block-button-outline' ],
            [
                'wp-block-button-outline' => [
                    'src'  => '',
                    'path' => $temp_file,
                ],
            ]
        );

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );

        $result = $this->collector->collect_render_enqueued_styles( $before_queue );

        $this->assertEmpty( $result['urls'] );
        $this->assertStringContainsString( 'border-radius: 99px', $result['css'] );

        unlink( $temp_file );
    }

    /**
     * Test that handles with neither src nor path are skipped.
     */
    public function test_collect_render_enqueued_styles_ignores_handles_without_src_or_path(): void {
        $before_queue = [];

        $mock_styles = $this->create_mock_wp_styles(
            [ 'empty-handle' ],
            [
                'empty-handle' => [
                    'src' => '',
                ],
            ]
        );

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );

        $result = $this->collector->collect_render_enqueued_styles( $before_queue );

        $this->assertEmpty( $result['urls'] );
        $this->assertEmpty( $result['css'] );
    }

    /**
     * Test that no diff returns empty results.
     */
    public function test_collect_render_enqueued_styles_returns_empty_when_no_new_handles(): void {
        $before_queue = [ 'wp-block-library', 'wp-block-button' ];

        $mock_styles = $this->create_mock_wp_styles( $before_queue );

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );

        $result = $this->collector->collect_render_enqueued_styles( $before_queue );

        $this->assertEmpty( $result['urls'] );
        $this->assertEmpty( $result['css'] );
    }

    /**
     * Test that inline CSS from wp_add_inline_style() is captured.
     */
    public function test_collect_render_enqueued_styles_captures_inline_after_css(): void {
        $before_queue = [];

        $inline_css = '.wp-block-button.is-style-outline .wp-block-button__link { border: 2px solid; }';
        $mock_styles = $this->create_mock_wp_styles(
            [ 'theme-button-styles' ],
            [
                'theme-button-styles' => [
                    'src'   => '/wp-content/themes/test/button.css',
                    'ver'   => '1.0',
                    'after' => [ '', $inline_css ],
                ],
            ]
        );

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );
        Functions\when( 'site_url' )->alias( function ( $path ) {
            return 'https://example.com' . $path;
        } );
        Functions\when( 'add_query_arg' )->alias( function ( $key, $value, $url ) {
            return $url . '?ver=' . $value;
        } );

        $result = $this->collector->collect_render_enqueued_styles( $before_queue );

        // Should have both the URL and inline CSS.
        $this->assertCount( 1, $result['urls'] );
        $this->assertStringContainsString( 'border: 2px solid', $result['css'] );
    }

    /**
     * Test that unregistered handles in the queue are gracefully skipped.
     */
    public function test_collect_render_enqueued_styles_skips_unregistered_handles(): void {
        $before_queue = [];

        // Handle is in queue but not in registered.
        $mock_styles = $this->create_mock_wp_styles(
            [ 'ghost-handle' ]
        );

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );

        $result = $this->collector->collect_render_enqueued_styles( $before_queue );

        $this->assertEmpty( $result['urls'] );
        $this->assertEmpty( $result['css'] );
    }

    /**
     * Test that both URL and path-based styles are collected together.
     */
    public function test_collect_render_enqueued_styles_collects_mixed_styles(): void {
        $before_queue = [];

        $css_content = '.custom-style { color: red; }';
        $temp_file = tempnam( sys_get_temp_dir(), 'css_test_' );
        file_put_contents( $temp_file, $css_content );

        $mock_styles = $this->create_mock_wp_styles(
            [ 'url-style', 'path-style' ],
            [
                'url-style' => [
                    'src' => 'https://example.com/wp-content/themes/test/style.css',
                    'ver' => '2.0',
                ],
                'path-style' => [
                    'src'  => '',
                    'path' => $temp_file,
                ],
            ]
        );

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );
        Functions\when( 'add_query_arg' )->alias( function ( $key, $value, $url ) {
            return $url . '?ver=' . $value;
        } );

        $result = $this->collector->collect_render_enqueued_styles( $before_queue );

        $this->assertCount( 1, $result['urls'] );
        $this->assertStringContainsString( 'style.css', $result['urls'][0] );
        $this->assertStringContainsString( 'color: red', $result['css'] );

        unlink( $temp_file );
    }

    /**
     * Inline CSS added to a stylesheet that already printed is returned.
     *
     * The case measured on a block theme: the page body renders before
     * wp_head(), so block-style-variation-styles prints in <head>. A modal
     * template part rendered at wp_footer then gets its variation rule
     * (is-style-eyebrow--N) appended to that same handle, and it never prints.
     */
    public function test_collect_inline_styles_added_since_returns_css_appended_to_a_printed_handle(): void {
        $page_rule  = ':root :where(p.is-style-eyebrow--9){text-transform: uppercase;}';
        $modal_rule = ':root :where(p.is-style-eyebrow--2043){text-transform: uppercase;}';

        $mock_styles = $this->create_mock_wp_styles(
            [ 'block-style-variation-styles' ],
            [
                'block-style-variation-styles' => [
                    'after' => [ $page_rule ],
                ],
            ]
        );
        $mock_styles->done = [ 'block-style-variation-styles' ];

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );

        $snapshot = $this->collector->snapshot_printed_inline_styles();

        $mock_styles->registered['block-style-variation-styles']->extra['after'][] = $modal_rule;

        $css = $this->collector->collect_inline_styles_added_since( $snapshot );

        $this->assertStringContainsString( 'is-style-eyebrow--2043', $css );
        $this->assertStringNotContainsString( 'is-style-eyebrow--9', $css, 'CSS that already printed must not print twice.' );
    }

    /**
     * A printed handle with no inline CSS yet still counts from zero.
     */
    public function test_collect_inline_styles_added_since_covers_a_printed_handle_that_had_no_inline_css(): void {
        $mock_styles = $this->create_mock_wp_styles(
            [ 'global-styles' ],
            [
                'global-styles' => [ 'src' => '' ],
            ]
        );
        $mock_styles->done = [ 'global-styles' ];

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );

        $snapshot = $this->collector->snapshot_printed_inline_styles();

        $mock_styles->registered['global-styles']->extra['after'] = [ '.late { color: red; }' ];

        $this->assertSame( '.late { color: red; }', $this->collector->collect_inline_styles_added_since( $snapshot ) );
    }

    /**
     * A handle that was not printed at snapshot time is left alone.
     *
     * Newly enqueued handles are printed by wp_styles()->do_items(), which
     * outputs their inline CSS itself — returning it here would duplicate it.
     */
    public function test_collect_inline_styles_added_since_ignores_handles_printed_after_the_snapshot(): void {
        $mock_styles = $this->create_mock_wp_styles( [] );
        $mock_styles->done = [];

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );

        $snapshot = $this->collector->snapshot_printed_inline_styles();

        $late = $this->create_mock_wp_styles(
            [ 'wp-block-button-theme-style' ],
            [
                'wp-block-button-theme-style' => [
                    'src'   => '/button.css',
                    'after' => [ '.wp-block-button { border-radius: 0; }' ],
                ],
            ]
        );
        $mock_styles->registered = $late->registered;
        $mock_styles->done       = [ 'wp-block-button-theme-style' ];

        $this->assertSame( '', $this->collector->collect_inline_styles_added_since( $snapshot ) );
    }

    /**
     * Nothing appended since the snapshot returns an empty string.
     */
    public function test_collect_inline_styles_added_since_returns_empty_when_nothing_was_appended(): void {
        $mock_styles = $this->create_mock_wp_styles(
            [ 'block-style-variation-styles' ],
            [
                'block-style-variation-styles' => [
                    'after' => [ ':root :where(p.is-style-eyebrow--9){text-transform: uppercase;}' ],
                ],
            ]
        );
        $mock_styles->done = [ 'block-style-variation-styles' ];

        Functions\when( 'wp_styles' )->justReturn( $mock_styles );

        $snapshot = $this->collector->snapshot_printed_inline_styles();

        $this->assertSame( '', $this->collector->collect_inline_styles_added_since( $snapshot ) );
    }
}
