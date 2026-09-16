<?php
/**
 * Trigger Markup
 *
 * The attributes an open-mode trigger element carries, set in one place for
 * every block mode that decorates one. Each piece used to be copied into
 * every handler, and a copy that lost its keydown handler is how a
 * mouse-only trigger shipped once.
 *
 * Close triggers do not use this: they live inside a modal container and
 * must not open an Interactivity island of their own.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class TriggerMarkup
{
    /**
     * Put a trigger element in the store's scope and wire its click.
     *
     * @param \WP_HTML_Tag_Processor $processor    Positioned on the trigger element.
     * @param array                  $context      Interactivity context from TriggerContext::build().
     * @param string                 $click_action Store action the click runs.
     * @param bool                   $prefetch     Whether hovering warms the content fetch.
     */
    public static function bind( \WP_HTML_Tag_Processor $processor, array $context, string $click_action, bool $prefetch ): void
    {
        $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
        $processor->set_attribute( 'data-wp-context', wp_json_encode( $context ) );
        $processor->set_attribute( 'data-wp-on--click', $click_action );

        if ( $prefetch ) {
            $processor->set_attribute( 'data-wp-on--mouseenter', 'actions.handlePrefetchHover' );
            $processor->set_attribute( 'data-wp-on--mouseleave', 'actions.handlePrefetchLeave' );
        }
    }

    /**
     * Announce that the element opens a dialog, and keep its open state bound.
     *
     * @param \WP_HTML_Tag_Processor $processor Positioned on the trigger element.
     */
    public static function announce_dialog( \WP_HTML_Tag_Processor $processor ): void
    {
        $processor->set_attribute( 'aria-haspopup', 'dialog' );
        $processor->set_attribute( 'aria-expanded', 'false' );
        $processor->set_attribute( 'data-wp-bind--aria-expanded', 'state.isExpanded' );
    }

    /**
     * Make an element that is not natively interactive operable as a button.
     *
     * All three are needed: without the role it is not announced as one,
     * without tabindex it cannot be reached, and without the keydown handler
     * Enter and Space do nothing (WCAG 2.1.1).
     *
     * @param \WP_HTML_Tag_Processor $processor Positioned on the trigger element.
     */
    public static function make_button( \WP_HTML_Tag_Processor $processor ): void
    {
        $processor->set_attribute( 'role', 'button' );
        $processor->set_attribute( 'tabindex', '0' );
        $processor->set_attribute( 'data-wp-on--keydown', 'actions.handleTriggerKeydown' );
    }

    /**
     * The author's accessible label, or a fallback when none was typed.
     *
     * @param array  $attributes Block attributes.
     * @param string $fallback   Label to use without an author label.
     * @return string The label; empty when neither exists.
     */
    public static function accessible_label( array $attributes, string $fallback = '' ): string
    {
        $custom_label = trim( $attributes['pikariModalAccessibleLabel'] ?? '' );

        return '' !== $custom_label ? $custom_label : $fallback;
    }
}
