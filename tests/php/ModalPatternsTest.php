<?php
/**
 * Tests for ModalPatterns.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\ModalPatterns;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

class ModalPatternsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define plugin constant if not already defined (see ModalTemplatePartTest).
        if ( ! defined( 'PIKARI_GUTENBERG_MODALS_DIR' ) ) {
            define( 'PIKARI_GUTENBERG_MODALS_DIR', dirname( __DIR__, 2 ) . '/' );
        }
    }

    public function test_constructor_registers_init_hook(): void
    {
        Actions\expectAdded( 'init' )->once();

        new ModalPatterns();

        // Brain\Monkey's expectAdded() is verified via Mockery::close() in
        // tearDown(), which does not register as a PHPUnit assertion on its
        // own; this TestCase does not extend Mockery's PHPUnit adapter, so
        // without an explicit assertion phpunit.xml.dist's
        // failOnRisky="true" flags (and fails) this test.
        $this->assertNotFalse( has_action( 'init' ) );
    }

    /**
     * Registering onto 'init' at the default priority 10 is a real bug:
     * ModalPatterns is constructed from inside pikari_gutenberg_modals_init(),
     * itself an 'init' callback at priority 10, so add_action( 'init', ..., 10 )
     * would append to the priority-10 bucket mid-iteration and never run on a
     * normal request (WP_Hook's foreach has already passed it). Priority 11 is
     * a new bucket, which WP_Hook::resort_active_iterations() still visits
     * during the same pass. This pins that priority so a revert to the
     * default goes red instead of silently reintroducing the bug.
     */
    public function test_constructor_registers_init_hook_at_priority_eleven(): void
    {
        $instance = new ModalPatterns();

        $this->assertSame(
            11,
            has_action( 'init', [ $instance, 'register_patterns' ] )
        );
    }

    public function test_register_patterns_registers_three_modal_starters(): void
    {
        Functions\stubTranslationFunctions();

        $registered = [];

        Functions\when( 'register_block_pattern' )->alias(
            function ( $name, $properties ) use ( &$registered ) {
                $registered[ $name ] = $properties;
                return true;
            }
        );

        ( new ModalPatterns() )->register_patterns();

        $this->assertCount( 3, $registered );

        foreach ( ModalPatterns::PATTERN_SLUGS as $slug ) {
            $name = 'pikari-gutenberg-modals/' . $slug;

            $this->assertArrayHasKey( $name, $registered );
            $this->assertContains(
                'core/template-part/modal',
                $registered[ $name ]['blockTypes']
            );
            $this->assertNotEmpty( $registered[ $name ]['content'] );
        }
    }

    public function test_every_pattern_contains_a_close_trigger_and_content_area(): void
    {
        Functions\stubTranslationFunctions();

        $registered = [];

        Functions\when( 'register_block_pattern' )->alias(
            function ( $name, $properties ) use ( &$registered ) {
                $registered[ $name ] = $properties;
                return true;
            }
        );

        ( new ModalPatterns() )->register_patterns();

        foreach ( $registered as $name => $properties ) {
            $this->assertStringContainsString(
                '"pikariModalAction":"close"',
                $properties['content'],
                $name . ' is missing a close trigger'
            );
            $this->assertStringContainsString(
                'pikari-gutenberg-modals/content-area',
                $properties['content'],
                $name . ' is missing a content area'
            );
        }
    }
}
