<?php
/**
 * Tests for ModalTemplatePart.
 *
 * @package Pikari\Tests\GutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\ModalTemplatePart;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

class ModalTemplatePartTest extends TestCase {

    /**
     * ModalTemplatePart instance.
     *
     * @var ModalTemplatePart
     */
    private ModalTemplatePart $instance;

    protected function setUp(): void {
        parent::setUp();

        // Define plugin constants if not already defined.
        if ( ! defined( 'PIKARI_GUTENBERG_MODALS_DIR' ) ) {
            define( 'PIKARI_GUTENBERG_MODALS_DIR', dirname( __DIR__, 2 ) . '/' );
        }

        // Mock WordPress functions used in the constructor.
        Functions\when( 'add_filter' )->justReturn( true );

        $this->instance = new ModalTemplatePart();
    }

    /**
     * Test that register_area adds the modal area for block themes.
     */
    public function test_register_area_adds_modal_area_for_block_themes(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( true );
        Functions\when( '__' )->returnArg();

        $areas  = [];
        $result = $this->instance->register_area( $areas );

        $this->assertCount( 1, $result );
        $this->assertSame( 'modal', $result[0]['area'] );
        $this->assertSame( 'div', $result[0]['area_tag'] );
    }

    /**
     * Test that register_area skips for non-block themes.
     */
    public function test_register_area_skips_for_non_block_themes(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( false );

        $areas  = [];
        $result = $this->instance->register_area( $areas );

        $this->assertEmpty( $result );
    }

    /**
     * Test that register_area preserves existing areas for block themes.
     */
    public function test_register_area_preserves_existing_areas(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( true );
        Functions\when( '__' )->returnArg();

        $existing = [
            [ 'area' => 'header', 'area_tag' => 'header', 'label' => 'Header' ],
        ];

        $result = $this->instance->register_area( $existing );

        $this->assertCount( 2, $result );
        $this->assertSame( 'header', $result[0]['area'] );
        $this->assertSame( 'modal', $result[1]['area'] );
    }

    /**
     * Test that provide_default_template returns unmodified result for non-block themes.
     */
    public function test_provide_default_template_skips_for_non_block_themes(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( false );

        $result = $this->instance->provide_default_template( [], [], 'wp_template_part' );

        $this->assertEmpty( $result );
    }

    /**
     * Test that provide_default_template ignores non-template-part queries for block themes.
     */
    public function test_provide_default_template_ignores_non_template_part_type(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( true );

        $result = $this->instance->provide_default_template( [], [], 'wp_template' );

        $this->assertEmpty( $result );
    }

    /**
     * Test that provide_default_template provides template for block themes.
     */
    public function test_provide_default_template_provides_for_block_themes(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( true );
        Functions\when( 'get_stylesheet' )->justReturn( 'twentytwentyfive' );
        Functions\when( '__' )->returnArg();

        $result = $this->instance->provide_default_template( [], [], 'wp_template_part' );

        $this->assertCount( 1, $result );
        $this->assertInstanceOf( \WP_Block_Template::class, $result[0] );
        $this->assertSame( 'modal', $result[0]->slug );
        $this->assertSame( 'modal', $result[0]->area );
        $this->assertSame( 'plugin', $result[0]->source );
    }

    /**
     * Test that provide_default_template does not override existing template.
     */
    public function test_provide_default_template_does_not_override_existing(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( true );

        $existing_template       = new \WP_Block_Template();
        $existing_template->slug = 'modal';

        $result = $this->instance->provide_default_template(
            [ $existing_template ],
            [],
            'wp_template_part'
        );

        $this->assertCount( 1, $result );
        $this->assertSame( $existing_template, $result[0] );
    }

    /**
     * Test that provide_individual_template returns null for non-block themes.
     */
    public function test_provide_individual_template_skips_for_non_block_themes(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( false );

        $result = $this->instance->provide_individual_template(
            null,
            'theme//modal',
            'wp_template_part'
        );

        $this->assertNull( $result );
    }

    /**
     * Test that provide_individual_template provides template for block themes.
     */
    public function test_provide_individual_template_provides_for_block_themes(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( true );
        Functions\when( 'get_stylesheet' )->justReturn( 'twentytwentyfive' );
        Functions\when( '__' )->returnArg();

        $result = $this->instance->provide_individual_template(
            null,
            'twentytwentyfive//modal',
            'wp_template_part'
        );

        $this->assertInstanceOf( \WP_Block_Template::class, $result );
        $this->assertSame( 'modal', $result->slug );
    }

    /**
     * Test that provide_individual_template does not override existing template.
     */
    public function test_provide_individual_template_does_not_override_existing(): void {
        Functions\when( 'wp_is_block_theme' )->justReturn( true );

        $existing       = new \WP_Block_Template();
        $existing->slug = 'modal';

        $result = $this->instance->provide_individual_template(
            $existing,
            'theme//modal',
            'wp_template_part'
        );

        $this->assertSame( $existing, $result );
    }
}
