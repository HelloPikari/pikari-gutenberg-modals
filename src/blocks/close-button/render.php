<?php
/**
 * Close Button Block - Server-side render.
 *
 * @package PikariGutenbergModals
 */

?>
<button
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns pre-escaped HTML.
    echo get_block_wrapper_attributes( [ 'class' => 'modal-close' ] );
    ?>
    data-wp-on--click="actions.closeModal"
    aria-label="<?php esc_attr_e( 'Close modal', 'pikari-gutenberg-modals' ); ?>"
>
    <span aria-hidden="true">&times;</span>
</button>
