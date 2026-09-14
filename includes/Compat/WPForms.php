<?php
/**
 * WPForms compatibility for REST-loaded modal content.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals\Compat;

use Pikari\GutenbergModals\BlockSupport;

class WPForms
{
    /**
     * Handle of the inline script that binds forms arriving in a modal.
     */
    public const INIT_HANDLE = 'pikari-gutenberg-modals-wpforms';

    /**
     * Bind a form that arrived after WPForms had already run.
     *
     * Skipped when the modal loaded wpforms.min.js itself: a fresh copy binds
     * every form from its own ready handler, and a second call would repeat it.
     */
    private const INITIALISER = <<<'JS'
document.addEventListener( 'pikari-modal:content-loaded', function ( event ) {
	var loaded = ( event.detail && event.detail.loaded ) || [];
	if ( ! window.wpforms || loaded.indexOf( 'wpforms' ) !== -1 || ! event.target.querySelector( '.wpforms-form' ) ) {
		return;
	}
	window.wpforms.ready();
} );
JS;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter( 'pikari_gutenberg_modals_content_response', [ $this, 'add_form_support' ] );

        // After WPForms enqueues its footer assets (15), before they print (20).
        add_action( 'wp_footer', [ $this, 'print_page_initialiser' ], 19 );
    }

    /**
     * Print the initialiser on a page that has WPForms and a modal trigger.
     *
     * Inline modal content is cloned from the page, so no REST response ever
     * carries the initialiser to it, and the cloned form is never bound. A REST
     * response that carries it later is skipped by the script loader, which
     * matches the `-js-after` element id WP_Scripts prints here.
     */
    public function print_page_initialiser(): void
    {
        if ( ! BlockSupport::has_modal_triggers() || ! wp_script_is( 'wpforms', 'enqueued' ) ) {
            return;
        }

        wp_register_script( self::INIT_HANDLE, false, [ 'wpforms' ], PIKARI_GUTENBERG_MODALS_VERSION, true );
        wp_add_inline_script( self::INIT_HANDLE, self::INITIALISER );
        wp_enqueue_script( self::INIT_HANDLE );
    }

    /**
     * Give WPForms what it needs to run a form inside modal content.
     *
     * WPForms prints its `wpforms_settings` global with its own echo rather
     * than wp_localize_script(), so the settings never reach WP_Scripts and
     * wpforms.min.js fails without them. They go in as the `wpforms` handle's
     * localized data, so a page that already has WPForms — and so already has
     * its settings — skips both together.
     *
     * WPForms also binds forms once, on document ready. When its file was
     * already on the page, a form arriving later is never bound and submits as
     * a plain POST, so the initialiser calls wpforms.ready() for it, as
     * WPForms' own Elementor and OptinMonster popup integrations do. That call
     * runs over every form on the page and adds another honeypot field to each
     * form that has one — the side effect those integrations already accept.
     *
     * @param array $response The modal-content response data.
     * @return array The response data.
     */
    public function add_form_support( array $response ): array
    {
        if ( empty( $response['scripts'] ) || ! function_exists( 'wpforms' ) ) {
            return $response;
        }

        $index = array_search( 'wpforms', array_column( $response['scripts'], 'handle' ), true );

        if ( false === $index ) {
            return $response;
        }

        $frontend = wpforms()->obj( 'frontend' );

        if ( ! $frontend || ! method_exists( $frontend, 'get_strings' ) ) {
            return $response;
        }

        $settings = 'var wpforms_settings = ' . wp_json_encode( $frontend->get_strings() ) . ';';
        $data     = $response['scripts'][ $index ]['data'];

        $response['scripts'][ $index ]['data'] = '' === $data ? $settings : $settings . "\n" . $data;

        $response['scripts'][] = array(
            'handle' => self::INIT_HANDLE,
            'src'    => null,
            'data'   => '',
            'before' => '',
            'after'  => self::INITIALISER,
        );

        return $response;
    }
}
