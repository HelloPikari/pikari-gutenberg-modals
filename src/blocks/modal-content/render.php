<?php
/**
 * Modal Content Block - Server-side render.
 *
 * Outputs inner block content in a hidden container that triggers
 * can reference for instant modal display without a REST API call.
 *
 * @package PikariGutenbergModals
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

// Generate anchor from client ID if not set by user.
$anchor = ! empty( $attributes['anchor'] )
? $attributes['anchor']
: 'modal-content-' . wp_unique_id();
?>
<div
    data-modal-inline-content="<?php echo esc_attr( $anchor ); ?>"
    data-modal-inline-title="<?php echo esc_attr( $attributes['title'] ?? '' ); ?>"
    data-wp-interactive="pikari-modal"
    hidden
>
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner block content is pre-escaped by WordPress block rendering.
    echo $content;
    ?>
</div>
