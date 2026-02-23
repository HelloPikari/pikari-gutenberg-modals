<?php
/**
 * Tests for BlockSupport close-mode trigger processing.
 *
 * @package Pikari\Tests\GutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\BlockSupport;
use Brain\Monkey\Functions;

class BlockSupportTest extends TestCase {

    /**
     * BlockSupport instance.
     *
     * @var BlockSupport
     */
    private BlockSupport $instance;

    protected function setUp(): void {
        parent::setUp();

        // Define plugin constants if not already defined.
        if ( ! defined( 'PIKARI_GUTENBERG_MODALS_DIR' ) ) {
            define( 'PIKARI_GUTENBERG_MODALS_DIR', dirname( __DIR__, 2 ) . '/' );
        }

        // Stub WordPress functions that may be called during processing.
        Functions\when( 'wp_enqueue_script_module' )->justReturn( null );
        Functions\when( 'wp_enqueue_style' )->justReturn( null );
        Functions\when( 'wp_interactivity_config' )->justReturn( null );
        Functions\when( 'rest_url' )->justReturn( 'https://example.com/wp-json/' );

        $this->instance = new BlockSupport();

        // Reset static state between tests.
        $this->reset_static_state();
    }

    /**
     * Reset static properties that persist between tests.
     */
    private function reset_static_state(): void {
        $reflection = new \ReflectionClass( BlockSupport::class );

        $reflection->getProperty( 'has_modal_triggers' )->setValue( null, false );
        $reflection->getProperty( 'modal_template_slugs' )->setValue( null, [] );
    }

    /**
     * Test that close-mode spans are not skipped by the early return.
     *
     * The early return in filter_block() must detect close-mode spans
     * (which have the modal-trigger CSS class but NOT the data-modal-trigger attribute).
     */
    public function test_filter_block_processes_close_mode_spans(): void {
        $input = '<p><span class="modal-trigger" data-modal-action="close">X</span></p>';

        $result = $this->instance->filter_block( $input, [] );

        $this->assertNotSame( $input, $result );
    }

    /**
     * Test that close-mode spans produce button elements.
     */
    public function test_close_mode_span_produces_button(): void {
        $input = '<p>Click <span class="modal-trigger" data-modal-action="close">Dismiss</span> to close.</p>';

        $result = $this->instance->filter_block( $input, [] );

        $this->assertStringContainsString( '<button', $result );
        $this->assertStringContainsString( 'Dismiss', $result );
        $this->assertStringNotContainsString( '<span class="modal-trigger"', $result );
    }

    /**
     * Test that close-mode button has the correct type attribute.
     */
    public function test_close_mode_button_is_type_button(): void {
        $input = '<p><span class="modal-trigger" data-modal-action="close">Close</span></p>';

        $result = $this->instance->filter_block( $input, [] );

        $this->assertStringContainsString( 'type="button"', $result );
    }

    /**
     * Test that close-mode button has Interactivity API scope.
     */
    public function test_close_mode_button_has_interactive_scope(): void {
        $input = '<p><span class="modal-trigger" data-modal-action="close">Close</span></p>';

        $result = $this->instance->filter_block( $input, [] );

        $this->assertStringContainsString( 'data-wp-interactive="pikari-modal"', $result );
    }

    /**
     * Test that close-mode button triggers closeModal action.
     */
    public function test_close_mode_button_triggers_close_action(): void {
        $input = '<p><span class="modal-trigger" data-modal-action="close">Close</span></p>';

        $result = $this->instance->filter_block( $input, [] );

        $this->assertStringContainsString( 'data-wp-on--click="actions.closeModal"', $result );
    }

    /**
     * Test that close-mode button has the correct CSS classes.
     */
    public function test_close_mode_button_has_correct_classes(): void {
        $input = '<p><span class="modal-trigger" data-modal-action="close">Close</span></p>';

        $result = $this->instance->filter_block( $input, [] );

        $this->assertStringContainsString( 'modal-close-trigger', $result );
        $this->assertStringContainsString( 'modal-close-trigger--inline', $result );
    }

    /**
     * Test that close-mode preserves surrounding paragraph content.
     */
    public function test_close_mode_preserves_surrounding_content(): void {
        $input = '<p>Click <span class="modal-trigger" data-modal-action="close">here</span> to dismiss.</p>';

        $result = $this->instance->filter_block( $input, [] );

        $this->assertStringContainsString( 'Click ', $result );
        $this->assertStringContainsString( ' to dismiss.', $result );
        $this->assertStringContainsString( '<p>', $result );
        $this->assertStringContainsString( '</p>', $result );
        $this->assertStringContainsString( '<button', $result );
    }

    /**
     * Test that close-mode processing does not set the modal triggers flag.
     *
     * Close triggers only exist inside modal template parts which are already
     * rendered because an open trigger exists — no need to re-enqueue assets.
     */
    public function test_close_mode_does_not_set_modal_triggers_flag(): void {
        $input = '<p><span class="modal-trigger" data-modal-action="close">Close</span></p>';

        $this->instance->filter_block( $input, [] );

        $reflection = new \ReflectionClass( BlockSupport::class );

        $this->assertFalse( $reflection->getProperty( 'has_modal_triggers' )->getValue() );
    }

    /**
     * Test that content without any modal trigger spans passes through unchanged.
     */
    public function test_content_without_triggers_passes_through(): void {
        $input = '<p>Regular paragraph content.</p>';

        $result = $this->instance->filter_block( $input, [] );

        $this->assertSame( $input, $result );
    }
}
