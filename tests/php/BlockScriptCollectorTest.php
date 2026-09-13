<?php
/**
 * Tests for BlockScriptCollector render-enqueued script collection.
 *
 * Scripts inserted with innerHTML never run, and a script a plugin enqueues
 * while modal content renders (WPForms, on wp_footer) otherwise never reaches
 * the browser at all. The collector hands the client what it needs to run
 * them itself — URL, localized data, inline before/after — in an order that
 * puts every dependency first.
 *
 * @package Pikari\Tests\GutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\BlockScriptCollector;
use Brain\Monkey\Functions;

class BlockScriptCollectorTest extends TestCase {

    /**
     * BlockScriptCollector instance.
     *
     * @var BlockScriptCollector
     */
    private BlockScriptCollector $collector;

    protected function setUp(): void {
        parent::setUp();
        $this->collector = new BlockScriptCollector();

        Functions\when( 'site_url' )->alias( function ( $path ) {
            return 'https://example.com' . $path;
        } );
        Functions\when( 'add_query_arg' )->alias( function ( $key, $value, $url ) {
            return $url . '?' . $key . '=' . $value;
        } );
    }

    /**
     * Create a mock WP_Scripts object with given queue and registered handles.
     *
     * @param array $queue      Handles in the queue.
     * @param array $registered Handle => ['src', 'ver', 'deps', 'extra'].
     * @return object Mock WP_Scripts object.
     */
    private function create_mock_wp_scripts( array $queue, array $registered = [] ): object {
        $mock             = new \stdClass();
        $mock->queue      = $queue;
        $mock->registered = [];

        foreach ( $registered as $handle => $config ) {
            $dep        = new \stdClass();
            $dep->src   = $config['src'] ?? false;
            $dep->ver   = $config['ver'] ?? false;
            $dep->deps  = $config['deps'] ?? [];
            $dep->extra = $config['extra'] ?? [];

            $mock->registered[ $handle ] = $dep;
        }

        return $mock;
    }

    public function test_returns_only_scripts_enqueued_since_the_snapshot(): void {
        Functions\when( 'wp_scripts' )->justReturn(
            $this->create_mock_wp_scripts(
                [ 'theme-script', 'wpforms' ],
                [
                    'theme-script' => [ 'src' => '/wp-content/themes/test/index.js' ],
                    'wpforms'      => [ 'src' => '/wp-content/plugins/wpforms-lite/wpforms.min.js' ],
                ]
            )
        );

        $scripts = $this->collector->collect_render_enqueued_scripts( [ 'theme-script' ] );

        $this->assertSame( [ 'wpforms' ], array_column( $scripts, 'handle' ) );
    }

    public function test_src_is_absolute_and_carries_the_version(): void {
        Functions\when( 'wp_scripts' )->justReturn(
            $this->create_mock_wp_scripts(
                [ 'relative', 'absolute' ],
                [
                    'relative' => [ 'src' => '/wp-includes/js/jquery/jquery.min.js', 'ver' => '3.7.1' ],
                    'absolute' => [ 'src' => 'https://cdn.example.org/lib.js' ],
                ]
            )
        );

        $scripts = $this->collector->collect_render_enqueued_scripts( [] );

        $this->assertSame( 'https://example.com/wp-includes/js/jquery/jquery.min.js?ver=3.7.1', $scripts[0]['src'] );
        $this->assertSame( 'https://cdn.example.org/lib.js', $scripts[1]['src'] );
    }

    /**
     * Queue order is enqueue order, not load order: WPForms queues
     * wpforms-modern, which needs wpforms, which needs jQuery. The `jquery`
     * handle itself is an alias with no src, so there is nothing to run for
     * it — but its own dependencies still have to come first.
     */
    public function test_dependencies_come_before_the_scripts_that_need_them(): void {
        Functions\when( 'wp_scripts' )->justReturn(
            $this->create_mock_wp_scripts(
                [ 'wpforms-modern', 'wpforms' ],
                [
                    'wpforms-modern' => [ 'src' => '/modern.js', 'deps' => [ 'wpforms' ] ],
                    'wpforms'        => [ 'src' => '/wpforms.js', 'deps' => [ 'jquery' ] ],
                    'jquery'         => [ 'deps' => [ 'jquery-core' ] ],
                    'jquery-core'    => [ 'src' => '/jquery.js' ],
                ]
            )
        );

        $scripts = $this->collector->collect_render_enqueued_scripts( [] );

        $this->assertSame( [ 'jquery-core', 'wpforms', 'wpforms-modern' ], array_column( $scripts, 'handle' ) );
    }

    public function test_a_shared_dependency_is_listed_once(): void {
        Functions\when( 'wp_scripts' )->justReturn(
            $this->create_mock_wp_scripts(
                [ 'one', 'two' ],
                [
                    'one'    => [ 'src' => '/one.js', 'deps' => [ 'shared' ] ],
                    'two'    => [ 'src' => '/two.js', 'deps' => [ 'shared' ] ],
                    'shared' => [ 'src' => '/shared.js' ],
                ]
            )
        );

        $scripts = $this->collector->collect_render_enqueued_scripts( [] );

        $this->assertSame( [ 'shared', 'one', 'two' ], array_column( $scripts, 'handle' ) );
    }

    /**
     * wp_localize_script() stores `data`; wp_add_inline_script() stores
     * `before` and `after` as arrays whose first element core seeds with false.
     */
    public function test_localized_data_and_inline_scripts_travel_with_the_handle(): void {
        Functions\when( 'wp_scripts' )->justReturn(
            $this->create_mock_wp_scripts(
                [ 'wpforms' ],
                [
                    'wpforms' => [
                        'src'   => '/wpforms.js',
                        'extra' => [
                            'data'   => 'var wpforms_utils = {};',
                            'before' => [ false, 'window.a = 1;' ],
                            'after'  => [ false, 'window.b = 2;', 'window.c = 3;' ],
                        ],
                    ],
                ]
            )
        );

        $script = $this->collector->collect_render_enqueued_scripts( [] )[0];

        $this->assertSame( 'var wpforms_utils = {};', $script['data'] );
        $this->assertSame( 'window.a = 1;', $script['before'] );
        $this->assertSame( "window.b = 2;\nwindow.c = 3;", $script['after'] );
    }

    public function test_an_inline_only_handle_is_kept_without_a_src(): void {
        Functions\when( 'wp_scripts' )->justReturn(
            $this->create_mock_wp_scripts(
                [ 'inline-only' ],
                [
                    'inline-only' => [ 'extra' => [ 'after' => [ false, 'init();' ] ] ],
                ]
            )
        );

        $scripts = $this->collector->collect_render_enqueued_scripts( [] );

        $this->assertCount( 1, $scripts );
        $this->assertNull( $scripts[0]['src'] );
        $this->assertSame( 'init();', $scripts[0]['after'] );
    }

    public function test_unregistered_handles_and_dependencies_are_skipped(): void {
        Functions\when( 'wp_scripts' )->justReturn(
            $this->create_mock_wp_scripts(
                [ 'ghost', 'real' ],
                [
                    'real' => [ 'src' => '/real.js', 'deps' => [ 'missing' ] ],
                ]
            )
        );

        $scripts = $this->collector->collect_render_enqueued_scripts( [] );

        $this->assertSame( [ 'real' ], array_column( $scripts, 'handle' ) );
    }

    public function test_returns_empty_when_no_new_handles(): void {
        Functions\when( 'wp_scripts' )->justReturn(
            $this->create_mock_wp_scripts( [ 'theme-script' ], [ 'theme-script' => [ 'src' => '/index.js' ] ] )
        );

        $this->assertSame( [], $this->collector->collect_render_enqueued_scripts( [ 'theme-script' ] ) );
    }
}
