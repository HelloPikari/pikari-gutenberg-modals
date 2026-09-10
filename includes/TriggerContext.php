<?php
/**
 * Interactivity API context for modal triggers.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

/**
 * Merges the optional `data-wp-context` keys shared by every open-mode trigger.
 *
 * Each content-source branch across GroupModalTriggerSupport and BlockSupport
 * still builds its own base keys and passes them in. What they have in
 * common is the set of optional keys — size, template part, placement — so
 * those are added here once rather than in every branch.
 */
class TriggerContext
{
    /**
     * Build the Interactivity API context for an open-mode trigger.
     *
     * Empty options are omitted rather than emitted as empty strings, so the
     * serialized context stays as small as it was before this existed.
     *
     * @param array  $attributes    Block attributes.
     * @param array  $base          Branch-specific keys (postId, modalId, contentSource...).
     * @param string $template_part Template part slug, or '' for the default.
     * @return array The context array.
     */
    public static function build( array $attributes, array $base, string $template_part = '' ): array
    {
        $context = $base;

        $size = $attributes['pikariModalSize'] ?? '';
        if ( ! empty( $size ) ) {
            $context['size'] = $size;
        }

        if ( ! empty( $template_part ) ) {
            $context['templatePart'] = $template_part;
        }

        // Only known placements travel. An unknown slug would reach the store
        // and be discarded there anyway; dropping it here keeps the context
        // honest about what it can express.
        $placement = $attributes['pikariModalPlacement'] ?? '';
        if ( in_array( $placement, [ 'left', 'right' ], true ) ) {
            $context['placement'] = $placement;
        }

        return $context;
    }
}
