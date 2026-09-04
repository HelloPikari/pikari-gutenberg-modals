<?php

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\TriggerContext;

class TriggerContextTest extends TestCase
{
    public function test_returns_base_unchanged_when_no_options_set(): void
    {
        $base = [ 'postId' => 12, 'modalId' => 'post-12' ];

        $this->assertSame( $base, TriggerContext::build( [], $base ) );
    }

    public function test_adds_size_when_set(): void
    {
        $context = TriggerContext::build( [ 'modalSize' => 'large' ], [ 'postId' => 1 ] );

        $this->assertSame( 'large', $context['size'] );
    }

    public function test_omits_empty_size(): void
    {
        $context = TriggerContext::build( [ 'modalSize' => '' ], [ 'postId' => 1 ] );

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
            [ 'modalSize' => 'small' ],
            [ 'contentSource' => 'inline', 'inlineAnchor' => 'promo' ],
            'promo'
        );

        $this->assertSame( 'inline', $context['contentSource'] );
        $this->assertSame( 'promo', $context['inlineAnchor'] );
        $this->assertSame( 'small', $context['size'] );
        $this->assertSame( 'promo', $context['templatePart'] );
    }
}
