<?php
/**
 * Modal Dialog Block - Server-side render.
 *
 * Outputs the overlay background elements and dialog container.
 * The dialog chrome (background, border, padding, shadow) is controlled
 * by block supports via get_block_wrapper_attributes().
 * The overlay (color, gradient, image) is controlled by custom
 * attributes rendered as separate elements. Overlay opacity is handled
 * via the alpha channel of the color value (e.g., rgba(0,0,0,0.8)).
 *
 * @package PikariGutenbergModals
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

// --- Strip serialized block wrapper from $content ---
// When the user sets block supports (background, border, shadow, etc.),
// the editor serializes them into a wrapper <div> around the inner blocks.
// Since render.php adds its own wrapper via get_block_wrapper_attributes(),
// we strip the duplicate to avoid double-wrapping.
$trimmed_content = trim( $content );
if ( str_starts_with( $trimmed_content, '<div' ) ) {
    $processor = new WP_HTML_Tag_Processor( $trimmed_content );
    if ( $processor->next_tag( 'div' ) && $processor->has_class( 'wp-block-pikari-gutenberg-modals-modal-dialog' ) ) {
        $content = preg_replace(
            '/^\s*<div\b[^>]*>(.*)<\/div>\s*$/s',
            '$1',
            $trimmed_content
        );
    }
}

// --- Overlay background color/gradient ---
// Gradient takes precedence over solid color.
// Color values include alpha for opacity (e.g., rgba(0,0,0,0.8)).
if ( ! empty( $attributes['overlayGradient'] ) ) {
    $overlay_style = 'background:' . $attributes['overlayGradient'];
} elseif ( ! empty( $attributes['overlayColor'] ) ) {
    $overlay_style = 'background-color:' . $attributes['overlayColor'];
} else {
    $overlay_style = 'background-color:rgba(0,0,0,0.8)';
}

// --- Background image ---
$has_bg_image         = ! empty( $attributes['backgroundImage']['url'] );
$bg_image_style_parts = [];

if ( $has_bg_image ) {
    $bg_image_style_parts[] = 'background-image:url(' . esc_url( $attributes['backgroundImage']['url'] ) . ')';
    $bg_image_style_parts[] = 'background-size:cover';

    if ( ! empty( $attributes['focalPoint'] ) ) {
        $x = round( $attributes['focalPoint']['x'] * 100 );
        $y = round( $attributes['focalPoint']['y'] * 100 );

        $bg_image_style_parts[] = 'background-position:' . $x . '% ' . $y . '%';
    } else {
        $bg_image_style_parts[] = 'background-position:50% 50%';
    }

    if ( ! empty( $attributes['hasParallax'] ) ) {
        $bg_image_style_parts[] = 'background-attachment:fixed';
    }
}

// --- Dialog container ---
$wrapper_attrs = get_block_wrapper_attributes(
    [
        'class'             => 'modal-content',
        'data-wp-on--click' => 'actions.stopPropagation',
    ]
);

?>
<?php if ( $has_bg_image ) : ?>
<span
    class="modal-overlay-image"
    style="<?php echo esc_attr( implode( ';', $bg_image_style_parts ) ); ?>"
    aria-hidden="true"
></span>
<?php endif; ?>
<span
    class="modal-overlay-background"
    style="<?php echo esc_attr( $overlay_style ); ?>"
    aria-hidden="true"
></span>
<div
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns pre-escaped HTML.
    echo $wrapper_attrs;
    ?>
>
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block content is pre-escaped by WordPress block rendering.
    echo $content;

    // Inject sr-only fallback close button when no close trigger exists in the dialog content.
    // Detects: legacy close-button (actions.closeModal), block close triggers (actions.handleCloseClick),
    // and inline close triggers (actions.closeModal on <button>).
    if ( ! str_contains( $content, 'actions.closeModal' ) && ! str_contains( $content, 'actions.handleCloseClick' ) ) {
        printf(
            '<button class="modal-close-fallback sr-only" data-wp-interactive="pikari-modal" data-wp-on--click="actions.closeModal" type="button" aria-label="%s">%s</button>',
            esc_attr__( 'Close dialog', 'pikari-gutenberg-modals' ),
            esc_html__( 'Close', 'pikari-gutenberg-modals' )
        );
    }
    ?>
</div>
