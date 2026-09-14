<?php
/**
 * WPForms inside REST-loaded modal content.
 *
 * Two things the generic script transport cannot supply. WPForms prints its
 * `wpforms_settings` global with its own echo rather than through
 * wp_localize_script(), so it never reaches WP_Scripts; without it
 * wpforms.min.js throws on a page with no other form. And WPForms initialises
 * forms once, on document ready — a form that arrives later on a page that
 * already ran it is never bound, and submits as a plain POST.
 *
 * @package Pikari\Tests\GutenbergModals
 */

namespace Pikari\Tests\GutenbergModals\Compat;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\BlockSupport;
use Pikari\GutenbergModals\Compat\WPForms;
use Brain\Monkey\Functions;

class WPFormsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        $frontend = new class() {
            public function get_strings(): array {
                return [ 'ajaxurl' => 'https://example.com/wp-admin/admin-ajax.php' ];
            }
        };

        $wpforms = new class( $frontend ) {
            private object $frontend;

            public function __construct( object $frontend ) {
                $this->frontend = $frontend;
            }

            public function obj( string $name ): ?object {
                return 'frontend' === $name ? $this->frontend : null;
            }
        };

        Functions\when( 'wpforms' )->justReturn( $wpforms );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
    }

    /**
     * Build a script entry as BlockScriptCollector returns it.
     *
     * @param string $handle Script handle.
     * @param string $data   Localized data.
     * @return array The entry.
     */
    private function script( string $handle, string $data = '' ): array {
        return [
            'handle' => $handle,
            'src'    => 'https://example.com/' . $handle . '.js',
            'data'   => $data,
            'before' => '',
            'after'  => '',
        ];
    }

    public function test_constructor_filters_the_content_response(): void {
        $compat = new WPForms();

        $this->assertNotFalse(
            has_filter( 'pikari_gutenberg_modals_content_response', [ $compat, 'add_form_support' ] )
        );
    }

    public function test_content_without_wpforms_scripts_is_untouched(): void {
        $response = [ 'scripts' => [ $this->script( 'theme-script' ) ] ];

        $this->assertSame( $response, ( new WPForms() )->add_form_support( $response ) );
    }

    public function test_a_response_cached_without_scripts_is_untouched(): void {
        $response = [ 'content' => '<p>Hello</p>' ];

        $this->assertSame( $response, ( new WPForms() )->add_form_support( $response ) );
    }

    /**
     * Attached to the wpforms handle, the settings are skipped together with
     * the file on a page that already has WPForms — and so already has them.
     */
    public function test_settings_travel_as_the_wpforms_handles_localized_data(): void {
        $response = [
            'scripts' => [
                $this->script( 'jquery-core' ),
                $this->script( 'wpforms', 'var wpforms_utils = {};' ),
            ],
        ];

        $scripts = ( new WPForms() )->add_form_support( $response )['scripts'];

        $this->assertSame(
            "var wpforms_settings = {\"ajaxurl\":\"https:\\/\\/example.com\\/wp-admin\\/admin-ajax.php\"};\nvar wpforms_utils = {};",
            $scripts[1]['data']
        );
    }

    public function test_an_initialiser_follows_the_wpforms_scripts(): void {
        $response = [
            'scripts' => [
                $this->script( 'wpforms' ),
                $this->script( 'wpforms-modern' ),
            ],
        ];

        $scripts     = ( new WPForms() )->add_form_support( $response )['scripts'];
        $initialiser = end( $scripts );

        $this->assertSame( WPForms::INIT_HANDLE, $initialiser['handle'] );
        $this->assertNull( $initialiser['src'] );
        $this->assertStringContainsString( 'pikari-modal:content-loaded', $initialiser['after'] );
        $this->assertStringContainsString( 'wpforms.ready()', $initialiser['after'] );
    }

    protected function tearDown(): void {
        $this->set_page_has_modal_triggers( false );

        // Brain Monkey expectations are verified by Mockery, which PHPUnit
        // does not count — without this a test asserting only them is risky.
        $this->addToAssertionCount( \Mockery::getContainer()->mockery_getExpectationCount() );

        parent::tearDown();
    }

    /**
     * Set whether block rendering found a modal trigger on this page.
     *
     * @param bool $found Whether a trigger was found.
     */
    private function set_page_has_modal_triggers( bool $found ): void {
        ( new \ReflectionClass( BlockSupport::class ) )
            ->getProperty( 'has_modal_triggers' )
            ->setValue( null, $found );
    }

    /**
     * After WPForms enqueues its footer assets (priority 15), before footer
     * scripts print (priority 20).
     */
    public function test_constructor_hooks_the_page_initialiser_before_footer_scripts_print(): void {
        $compat = new WPForms();

        $this->assertSame( 19, has_action( 'wp_footer', [ $compat, 'print_page_initialiser' ] ) );
    }

    /**
     * Inline modal content is cloned from the page, so no REST response ever
     * carries the initialiser to it. The page has to print it.
     */
    public function test_a_page_with_wpforms_and_a_modal_trigger_prints_the_initialiser(): void {
        if ( ! defined( 'PIKARI_GUTENBERG_MODALS_VERSION' ) ) {
            define( 'PIKARI_GUTENBERG_MODALS_VERSION', '0.0.0-test' );
        }

        $this->set_page_has_modal_triggers( true );
        Functions\when( 'wp_script_is' )->justReturn( true );

        Functions\expect( 'wp_register_script' )
            ->once()
            ->with( WPForms::INIT_HANDLE, false, [ 'wpforms' ], PIKARI_GUTENBERG_MODALS_VERSION, true );
        Functions\expect( 'wp_add_inline_script' )
            ->once()
            ->with(
                WPForms::INIT_HANDLE,
                \Mockery::on( fn( $code ) => str_contains( $code, 'pikari-modal:content-loaded' ) && str_contains( $code, 'wpforms.ready()' ) )
            );
        Functions\expect( 'wp_enqueue_script' )->once()->with( WPForms::INIT_HANDLE );

        ( new WPForms() )->print_page_initialiser();
    }

    public function test_a_page_without_a_modal_trigger_prints_nothing(): void {
        $this->set_page_has_modal_triggers( false );
        Functions\when( 'wp_script_is' )->justReturn( true );
        Functions\expect( 'wp_register_script' )->never();

        ( new WPForms() )->print_page_initialiser();
    }

    public function test_a_page_without_wpforms_prints_nothing(): void {
        $this->set_page_has_modal_triggers( true );
        Functions\when( 'wp_script_is' )->justReturn( false );
        Functions\expect( 'wp_register_script' )->never();

        ( new WPForms() )->print_page_initialiser();
    }
}
