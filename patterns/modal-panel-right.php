<?php
/**
 * Right panel modal pattern.
 *
 * @package PikariGutenbergModals
 */

?>
<!-- wp:pikari-gutenberg-modals/modal-overlay {"placement":"right"} -->
<div class="wp-block-pikari-gutenberg-modals-modal-overlay"><!-- wp:group {"className":"modal-chrome","style":{"color":{"background":"#ffffff"},"spacing":{"padding":{"top":"1.5rem","right":"1.5rem","bottom":"1.5rem","left":"1.5rem"}},"shadow":"0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06)"},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group modal-chrome has-background" style="background-color:#ffffff;padding-top:1.5rem;padding-right:1.5rem;padding-bottom:1.5rem;padding-left:1.5rem;box-shadow:0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06)"><!-- wp:group {"layout":{"type":"flex","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:button {"pikariModalAction":"close"} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button"><?php echo esc_html__( 'Close', 'pikari-gutenberg-modals' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:group -->

<!-- wp:pikari-gutenberg-modals/content-area /--></div>
<!-- /wp:group --></div>
<!-- /wp:pikari-gutenberg-modals/modal-overlay -->
