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
     * Measured on WordPress 7.1: without this, firing wp_footer dropped
     * wp-block-paragraph, wp-block-heading and wp-block-group from the
     * response, and 1768 bytes of inline CSS with them.
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
}
