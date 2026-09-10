<?php
/**
 * Tests for EditorIntegration.
 *
 * @package Pikari\Tests\GutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\EditorIntegration;
use Brain\Monkey\Functions;

class EditorIntegrationTest extends TestCase {

    /**
     * EditorIntegration instance.
     *
     * @var EditorIntegration
     */
    private EditorIntegration $instance;

    /**
     * Every block the inserter would offer before filtering.
     *
     * @var string[]
     */
    private array $all_blocks = [
        'core/paragraph',
        'pikari-gutenberg-modals/modal-overlay',
        'pikari-gutenberg-modals/modal-content',
    ];

    protected function setUp(): void {
        parent::setUp();

        Functions\when( 'add_action' )->justReturn( true );
        Functions\when( 'add_filter' )->justReturn( true );

        $this->instance = new EditorIntegration();
    }

    /**
     * Build a stand-in for WP_Block_Editor_Context.
     *
     * @param string      $name      Editor context name.
     * @param string|null $post_type Post type being edited, or null for none.
     * @return object
     */
    private function context( string $name, ?string $post_type = null ): object {
        $context       = new \stdClass();
        $context->name = $name;
        $context->post = null;

        if ( $post_type !== null ) {
            $post            = new \stdClass();
            $post->post_type = $post_type;
            $context->post   = $post;
        }

        return $context;
    }

    /**
     * The Modal Dialog block is only meaningful inside a template part.
     */
    public function test_post_editor_hides_the_modal_overlay_block(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-post' )
        );

        $this->assertNotContains( 'pikari-gutenberg-modals/modal-overlay', $result );
    }

    /**
     * Modal Content belongs in post content, so the post editor keeps it.
     */
    public function test_post_editor_keeps_the_modal_content_block(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-post' )
        );

        $this->assertContains( 'pikari-gutenberg-modals/modal-content', $result );
    }

    /**
     * The Site Editor is where template parts are edited, so Modal Dialog stays.
     */
    public function test_site_editor_keeps_the_modal_overlay_block(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-site', 'wp_template_part' )
        );

        $this->assertContains( 'pikari-gutenberg-modals/modal-overlay', $result );
    }

    /**
     * Editing a template part is not post content — Modal Content is hidden.
     */
    public function test_site_editor_hides_modal_content_when_editing_a_template_part(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-site', 'wp_template_part' )
        );

        $this->assertNotContains( 'pikari-gutenberg-modals/modal-content', $result );
    }

    /**
     * Editing a template is not post content either.
     */
    public function test_site_editor_hides_modal_content_when_editing_a_template(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-site', 'wp_template' )
        );

        $this->assertNotContains( 'pikari-gutenberg-modals/modal-content', $result );
    }

    /**
     * No post in context means the Site Editor is not editing post content.
     */
    public function test_site_editor_hides_modal_content_when_no_post_is_being_edited(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-site' )
        );

        $this->assertNotContains( 'pikari-gutenberg-modals/modal-content', $result );
    }

    /**
     * Regression: since WP 6.3 the Site Editor also edits pages, and the block
     * exists for exactly that context. Gating on the admin screen hid it.
     *
     * @see https://github.com/HelloPikari/pikari-gutenberg-modals — todo #439
     */
    public function test_site_editor_keeps_modal_content_when_editing_a_page(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-site', 'page' )
        );

        $this->assertContains( 'pikari-gutenberg-modals/modal-content', $result );
    }

    /**
     * Editing a page in the Site Editor is post content, so Modal Dialog is
     * as meaningless there as it is in the post editor.
     */
    public function test_site_editor_hides_the_modal_overlay_block_when_editing_a_page(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-site', 'page' )
        );

        $this->assertNotContains( 'pikari-gutenberg-modals/modal-overlay', $result );
    }

    /**
     * A custom post type edited in the Site Editor is post content too.
     */
    public function test_site_editor_keeps_modal_content_when_editing_a_custom_post_type(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-site', 'movie' )
        );

        $this->assertContains( 'pikari-gutenberg-modals/modal-content', $result );
    }

    /**
     * Contexts we do not know about are passed through untouched.
     */
    public function test_unknown_editor_context_is_left_alone(): void {
        $result = $this->instance->restrict_modal_template_blocks(
            $this->all_blocks,
            $this->context( 'core/edit-widgets' )
        );

        $this->assertSame( $this->all_blocks, $result );
    }

    /**
     * Editor config exposes block theme status as true for block themes.
     */
    public function test_get_editor_config_reports_block_theme(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( true );
        Functions\when( 'rest_url' )->justReturn( 'http://example.com/wp-json/pikari-gutenberg-modals/v1/' );
        Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
        Functions\when( '__' )->returnArg();
        Functions\when( 'apply_filters' )->alias( function ( $hook, $default ) {
            return $default;
        } );
        Functions\when( 'get_block_templates' )->justReturn( [] );

        $config = $this->instance->get_editor_config();

        $this->assertTrue( $config['isBlockTheme'] );
    }

    /**
     * Editor config exposes block theme status as false for hybrid themes.
     */
    public function test_get_editor_config_reports_hybrid_theme(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( false );
        Functions\when( 'rest_url' )->justReturn( 'http://example.com/wp-json/pikari-gutenberg-modals/v1/' );
        Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
        Functions\when( '__' )->returnArg();
        Functions\when( 'apply_filters' )->alias( function ( $hook, $default ) {
            return $default;
        } );
        Functions\when( 'get_stylesheet_directory' )->justReturn( '/wp-content/themes/test' );
        Functions\when( 'get_template_directory' )->justReturn( '/wp-content/themes/test' );

        $config = $this->instance->get_editor_config();

        $this->assertFalse( $config['isBlockTheme'] );
    }

    /**
     * Editor config contains exactly nine keys in the documented order.
     */
    public function test_get_editor_config_contains_exactly_nine_keys_in_correct_order(): void {
        $expected_keys = [
            'supportedBlocks',
            'triggerBlocks',
            'restUrl',
            'nonce',
            'modalSizes',
            'panelWidths',
            'modalTemplateParts',
            'isBlockTheme',
            'defaultSettings',
        ];

        Functions\when( 'wp_is_block_theme' )->justReturn( false );
        Functions\when( 'rest_url' )->justReturn( 'http://example.com/wp-json/pikari-gutenberg-modals/v1/' );
        Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
        Functions\when( '__' )->returnArg();
        Functions\when( 'apply_filters' )->alias( function ( $hook, $default ) {
            return $default;
        } );
        Functions\when( 'get_stylesheet_directory' )->justReturn( '/wp-content/themes/test' );
        Functions\when( 'get_template_directory' )->justReturn( '/wp-content/themes/test' );

        $config = $this->instance->get_editor_config();

        $this->assertCount( 9, $config );
        $this->assertSame( $expected_keys, array_keys( $config ) );
    }
}
