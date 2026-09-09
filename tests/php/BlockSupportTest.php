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

    /**
     * Test that get_trigger_blocks() returns the default block list.
     */
    public function test_get_trigger_blocks_returns_default_list(): void {
        $this->assertSame( [ 'core/group', 'core/button' ], $this->instance->get_trigger_blocks() );
    }

    /**
     * Test that get_trigger_blocks() applies the documented filter.
     */
    public function test_get_trigger_blocks_applies_filter(): void {
        \Brain\Monkey\Filters\expectApplied( 'pikari_gutenberg_modals_trigger_blocks' )
            ->once()
            ->with( [ 'core/group', 'core/button' ] )
            ->andReturn( [ 'core/group', 'core/button', 'core/quote' ] );

        $this->assertSame(
            [ 'core/group', 'core/button', 'core/quote' ],
            $this->instance->get_trigger_blocks()
        );
    }

    /**
     * Test that filter_button_block() leaves content unchanged with no modal action.
     */
    public function test_filter_button_block_ignores_missing_action(): void {
        $input = '<div class="wp-block-button"><a href="https://example.com">Link</a></div>';

        $result = $this->instance->filter_button_block( $input, [ 'attrs' => [] ] );

        $this->assertSame( $input, $result );
    }

    /**
     * Test that filter_button_block() leaves content unchanged for a close action.
     *
     * Close mode for buttons is handled by filter_close_mode_block(), not here.
     */
    public function test_filter_button_block_ignores_close_action(): void {
        $input = '<div class="wp-block-button"><a href="https://example.com">Link</a></div>';
        $block = [ 'attrs' => [ 'pikariModalAction' => 'close' ] ];

        $result = $this->instance->filter_button_block( $input, $block );

        $this->assertSame( $input, $result );
    }

    /**
     * Test that filter_button_block() no longer honours the legacy attribute name.
     *
     * Regression guard for the pikariOpenInModal -> pikariModalAction rename.
     */
    public function test_filter_button_block_ignores_legacy_open_in_modal_attribute(): void {
        $input = '<div class="wp-block-button"><a href="https://example.com">Link</a></div>';
        $block = [ 'attrs' => [ 'pikariOpenInModal' => true ] ];

        $result = $this->instance->filter_button_block( $input, $block );

        $this->assertSame( $input, $result );
    }

    /**
     * Test that filter_button_block() bails out for an empty direct URL.
     *
     * Covers the 'url' content-source branch's validation guard — the only
     * part of that branch reachable without WP_HTML_Tag_Processor, which
     * does not exist in this test environment.
     */
    public function test_filter_button_block_ignores_empty_direct_url(): void {
        Functions\when( 'esc_url_raw' )->returnArg();

        $input = '<div class="wp-block-button"><a>Link</a></div>';
        $block = [
            'attrs' => [
                'pikariModalAction'        => 'open',
                'pikariModalContentSource' => 'url',
            ],
        ];

        $result = $this->instance->filter_button_block( $input, $block );

        $this->assertSame( $input, $result );
    }

    /**
     * Test that filter_close_mode_block() leaves content unchanged with no modal action.
     */
    public function test_close_mode_block_ignores_missing_action(): void {
        $input = '<div class="wp-block-group">Content</div>';

        $result = $this->instance->filter_close_mode_block( $input, [] );

        $this->assertSame( $input, $result );
    }

    /**
     * Test that filter_close_mode_block() leaves content unchanged for an open action.
     *
     * Open mode is handled elsewhere (filter_button_block() here,
     * GroupModalTriggerSupport for core/group).
     */
    public function test_close_mode_block_ignores_open_action(): void {
        $input = '<div class="wp-block-group">Content</div>';
        $block = [ 'attrs' => [ 'pikariModalAction' => 'open' ] ];

        $result = $this->instance->filter_close_mode_block( $input, $block );

        $this->assertSame( $input, $result );
    }
}
