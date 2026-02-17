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
    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
</button>
