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
        $label = self::dialog_label( $attributes, $base, $template_part );
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

        // Same rule as placement: only slugs the store can act on travel.
        // The list is duplicated in src/frontend/video-providers.js, which
        // owns the slug-to-CSS-ratio mapping.
        $aspect_ratio = $attributes['pikariModalAspectRatio'] ?? '';
        if ( in_array( $aspect_ratio, [ '16-9', '9-16', '4-3', '1-1' ], true ) ) {
            $context['aspectRatio'] = $aspect_ratio;
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
     * @param array  $attributes    Block attributes.
     * @param array  $base          Branch-specific context keys.
     * @param string $template_part Template part slug, or '' for the default.
     * @return string The dialog name, or '' when there is no subject to use.
     */
    private static function dialog_label( array $attributes, array $base, string $template_part = '' ): string
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

        // Template-only modals have no content reference to name themselves
        // after — the template part IS the content, so its title is the
        // subject. Without this the dialog falls back to the container's
        // generic name, which is the one thing every other open-mode surface
        // already avoids.
        if ( 'none' === $content_source ) {
            return self::template_part_title( $template_part );
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

    /**
     * The title of a modal template part, for naming the dialog it fills.
     *
     * Cached per slug for the request: a Query Loop can render the same
     * template-only trigger dozens of times, and one lookup per card would
     * be one lookup too many.
     *
     * @param string $template_part Template part slug, or '' for the default.
     * @return string The title, or '' when the part cannot be found.
     */
    private static function template_part_title( string $template_part ): string
    {
        static $titles = [];

        // 'modal' is the default part's slug — the same literal the trigger
        // branches use, because ModalTemplatePart::SLUG is private.
        $slug = '' !== $template_part ? $template_part : 'modal';

        if ( array_key_exists( $slug, $titles ) ) {
            return $titles[ $slug ];
        }

        $template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
        $title    = $template ? trim( wp_strip_all_tags( (string) ( $template->title ?? '' ) ) ) : '';

        // WordPress stands the slug in for a title the part does not have,
        // for both DB-saved and theme-file parts. "video-player" is a worse
        // dialog name than the container's own generic one, so a title that
        // is only the slug counts as no title.
        if ( strtolower( $title ) === strtolower( $slug ) ) {
            $title = '';
        }

        $titles[ $slug ] = $title;

        return $titles[ $slug ];
    }
}
