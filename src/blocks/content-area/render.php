<?php
/**
 * Content Area Block - Server-side render.
 *
 * Outputs the loading, error, and content sections of the modal.
 *
 * @package PikariGutenbergModals
 */

?>
<!-- Screen reader announcements -->
<div class="sr-only" aria-live="polite" aria-atomic="true">
    <span data-wp-text="state.loading ? '<?php echo esc_js( __( 'Loading content...', 'pikari-gutenberg-modals' ) ); ?>' : ''"></span>
</div>
<div class="sr-only" role="status" aria-live="assertive" aria-atomic="true">
    <span data-wp-text="state.hasError ? state.errorMessage : ''"></span>
</div>

<!-- Loading state (visual) -->
<div
    class="modal-loading"
    data-wp-class--hidden="!state.loading"
    aria-hidden="true"
>
    <div class="loading-spinner"></div>
    <p><?php esc_html_e( 'Loading...', 'pikari-gutenberg-modals' ); ?></p>
</div>

<!-- Error state (visual) -->
<div
    class="modal-error"
    data-wp-class--hidden="!state.hasError"
    aria-hidden="true"
>
    <p data-wp-text="state.errorMessage"></p>
</div>

<!-- Content body -->
<div
    id="modal-content"
    class="modal-body"
    data-wp-class--hidden="state.loading || state.hasError"
></div>
