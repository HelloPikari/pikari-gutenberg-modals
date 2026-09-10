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

        // No subject to name the dialog with: omitting the key leaves the
        // container's own generic name in place, which is the right fallback.
        $label = self::dialog_label( $attributes, $base );
        if ( '' !== $label ) {
            $context['label'] = $label;
        }

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

    /**
     * Work out what to name the dialog this trigger opens.
     *
     * The trigger is named for the action ("Open X in modal dialog"); the
     * dialog is named for its subject. A command string makes a poor name for
     * a window, so the two are derived separately — this one from the content
     * the modal is about to show.
     *
     * Deriving it here rather than in each branch means every trigger surface
     * names its dialog the same way, which is what the branches did not do
     * when they each built their own context.
     *
     * @param array $attributes Block attributes.
     * @param array $base       Branch-specific context keys.
     * @return string The dialog name, or '' when there is no subject to use.
     */
    private static function dialog_label( array $attributes, array $base ): string
    {
        // An author-supplied label describes the content, so it names the
        // dialog whatever the content source is.
        $custom_label = trim( $attributes['pikariModalAccessibleLabel'] ?? '' );
        if ( '' !== $custom_label ) {
            return $custom_label;
        }

        $content_source = $base['contentSource'] ?? '';

        // Inline content names itself: the store reads the title out of
        // data-modal-inline-title once the content is in the DOM. The anchor
        // slug never travels — it is an authoring identifier, not a name.
        if ( 'inline' === $content_source ) {
            return '';
        }

        $target = (string) ( $base['postId'] ?? '' );
        if ( '' === $target ) {
            return '';
        }

        // An external URL has no title to borrow. Its host is the only subject
        // available, and it is what the iframe inside is titled with too.
        if ( 'url' === $content_source ) {
            return (string) wp_parse_url( $target, PHP_URL_HOST );
        }

        // get_the_title( 0 ) falls back to the global post, which would name
        // the dialog after the page the trigger sits on.
        $post_id = (int) $target;
        if ( $post_id <= 0 ) {
            return '';
        }

        // get_the_title() runs wptexturize, so an apostrophe comes back as
        // &#8217;. The context travels as JSON inside an attribute and the
        // store applies it with setAttribute(), which does not decode
        // entities — decode here or assistive tech reads the entity out.
        $decoded = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return trim( wp_strip_all_tags( $decoded ) );
    }
}
