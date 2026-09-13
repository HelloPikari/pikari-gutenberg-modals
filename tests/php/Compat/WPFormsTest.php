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
}
