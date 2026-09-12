<?php

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\TriggerContext;
use Brain\Monkey\Functions;

class TriggerContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Label derivation reaches for these on every build; tests that care
        // about the label override them.
        Functions\when( 'get_the_title' )->justReturn( '' );
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
    }

    public function test_returns_base_unchanged_when_no_options_set(): void
    {
        $base = [ 'postId' => 12, 'modalId' => 'post-12' ];

        $this->assertSame( $base, TriggerContext::build( [], $base ) );
    }

    public function test_adds_size_when_set(): void
    {
        $context = TriggerContext::build( [ 'pikariModalSize' => 'large' ], [ 'postId' => 1 ] );

        $this->assertSame( 'large', $context['size'] );
    }

    public function test_omits_empty_size(): void
    {
        $context = TriggerContext::build( [ 'pikariModalSize' => '' ], [ 'postId' => 1 ] );

        $this->assertArrayNotHasKey( 'size', $context );
    }

    public function test_adds_template_part_when_set(): void
    {
        $context = TriggerContext::build( [], [ 'postId' => 1 ], 'promo' );

        $this->assertSame( 'promo', $context['templatePart'] );
    }

    public function test_omits_empty_template_part(): void
    {
        $context = TriggerContext::build( [], [ 'postId' => 1 ], '' );

        $this->assertArrayNotHasKey( 'templatePart', $context );
    }

    public function test_preserves_base_keys_alongside_options(): void
    {
        $context = TriggerContext::build(
            [ 'pikariModalSize' => 'small' ],
            [ 'contentSource' => 'inline', 'inlineAnchor' => 'promo' ],
            'promo'
        );

        $this->assertSame( 'inline', $context['contentSource'] );
        $this->assertSame( 'promo', $context['inlineAnchor'] );
        $this->assertSame( 'small', $context['size'] );
        $this->assertSame( 'promo', $context['templatePart'] );
    }

    public function test_adds_aspect_ratio_when_valid(): void
    {
        $context = TriggerContext::build( [ 'pikariModalAspectRatio' => '9-16' ], [ 'postId' => 1 ] );

        $this->assertSame( '9-16', $context['aspectRatio'] );
    }

    public function test_omits_empty_aspect_ratio(): void
    {
        $context = TriggerContext::build( [ 'pikariModalAspectRatio' => '' ], [ 'postId' => 1 ] );

        $this->assertArrayNotHasKey( 'aspectRatio', $context );
    }

    public function test_omits_unknown_aspect_ratio(): void
    {
        $context = TriggerContext::build( [ 'pikariModalAspectRatio' => '21-9' ], [ 'postId' => 1 ] );

        $this->assertArrayNotHasKey( 'aspectRatio', $context );
    }

    public function test_adds_placement_when_valid(): void
    {
        $context = TriggerContext::build( [ 'pikariModalPlacement' => 'right' ], [ 'postId' => 1 ] );

        $this->assertSame( 'right', $context['placement'] );
    }

    public function test_omits_empty_placement(): void
    {
        $context = TriggerContext::build( [ 'pikariModalPlacement' => '' ], [ 'postId' => 1 ] );

        $this->assertArrayNotHasKey( 'placement', $context );
    }

    public function test_omits_unknown_placement(): void
    {
        $context = TriggerContext::build( [ 'pikariModalPlacement' => 'top' ], [ 'postId' => 1 ] );

        $this->assertArrayNotHasKey( 'placement', $context );
    }

    public function test_build_reads_the_unified_attribute_names(): void
    {
        $context = TriggerContext::build(
            [
                'pikariModalSize'         => 'wide',
                'pikariModalPlacement'    => 'right',
                'pikariModalTemplatePart' => 'sidebar',
            ],
            [ 'postId' => '7', 'modalId' => 'page-7' ],
            'sidebar'
        );

        $this->assertSame( 'wide', $context['size'] );
        $this->assertSame( 'right', $context['placement'] );
        $this->assertSame( 'sidebar', $context['templatePart'] );
    }

    public function test_build_omits_an_unknown_placement(): void
    {
        $context = TriggerContext::build(
            [ 'pikariModalPlacement' => 'diagonal' ],
            [ 'postId' => '7', 'modalId' => 'page-7' ]
        );

        $this->assertArrayNotHasKey( 'placement', $context );
    }

    public function test_names_the_dialog_after_the_linked_post(): void
    {
        Functions\when( 'get_the_title' )->justReturn( 'Annual report' );

        $context = TriggerContext::build( [], [ 'postId' => '7', 'modalId' => 'page-7' ] );

        $this->assertSame( 'Annual report', $context['label'] );
    }

    public function test_decodes_entities_in_the_dialog_name(): void
    {
        // get_the_title() runs wptexturize, so an apostrophe comes back as an
        // entity. The context travels as JSON and the store applies it with
        // setAttribute(), which does not decode — so assistive tech would
        // otherwise read the entity out.
        Functions\when( 'get_the_title' )->justReturn( 'Steve&#8217;s report' );

        $context = TriggerContext::build( [], [ 'postId' => '7' ] );

        $this->assertSame( "Steve\u{2019}s report", $context['label'] );
    }

    public function test_omits_the_label_when_the_post_has_no_title(): void
    {
        Functions\when( 'get_the_title' )->justReturn( '' );

        $context = TriggerContext::build( [], [ 'postId' => '7' ] );

        $this->assertArrayNotHasKey( 'label', $context );
    }

    public function test_does_not_ask_for_the_title_of_a_zero_post_id(): void
    {
        // get_the_title( 0 ) falls back to the global post, which would name
        // the dialog after whatever page the trigger happens to sit on.
        Functions\expect( 'get_the_title' )->never();

        $context = TriggerContext::build( [], [ 'postId' => '0' ] );

        $this->assertArrayNotHasKey( 'label', $context );
    }

    public function test_names_an_external_dialog_after_its_host(): void
    {
        $context = TriggerContext::build(
            [],
            [
                'postId'        => 'https://example.com/some/page',
                'contentSource' => 'url',
                'modalId'       => 'url-https://example.com/some/page',
            ]
        );

        $this->assertSame( 'example.com', $context['label'] );
    }

    public function test_leaves_inline_content_to_name_itself(): void
    {
        // The store reads data-modal-inline-title once the content is in the
        // DOM; the anchor slug is an authoring identifier, not a name.
        $context = TriggerContext::build(
            [],
            [ 'contentSource' => 'inline', 'inlineAnchor' => 'promo' ]
        );

        $this->assertArrayNotHasKey( 'label', $context );
    }

    public function test_author_label_wins_over_the_post_title(): void
    {
        Functions\when( 'get_the_title' )->justReturn( 'Annual report' );

        $context = TriggerContext::build(
            [ 'pikariModalAccessibleLabel' => 'Our 2026 results' ],
            [ 'postId' => '7' ]
        );

        $this->assertSame( 'Our 2026 results', $context['label'] );
    }

    public function test_author_label_names_an_inline_dialog(): void
    {
        $context = TriggerContext::build(
            [ 'pikariModalAccessibleLabel' => 'Newsletter signup' ],
            [ 'contentSource' => 'inline', 'inlineAnchor' => 'promo' ]
        );

        $this->assertSame( 'Newsletter signup', $context['label'] );
    }
}
