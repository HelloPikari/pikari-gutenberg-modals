<?php
/**
 * Frontend Renderer
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class FrontendRenderer
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Register frontend assets.
     *
     * Assets are registered here but only enqueued by BlockSupport
     * when modal triggers are detected during block rendering.
     */
    public function enqueue_frontend_assets(): void
    {
        $frontend_asset_file = PIKARI_GUTENBERG_MODALS_DIR . 'build/frontend/index.asset.php';

        // Check if build exists
        if ( ! file_exists($frontend_asset_file) ) {
            return;
        }

        $frontend_assets = include $frontend_asset_file;

        // Register frontend script module (will be enqueued by BlockSupport when triggers are detected)
        wp_register_script_module(
            'pikari-gutenberg-modals-frontend',
            PIKARI_GUTENBERG_MODALS_URL . 'build/frontend/index.js',
            ['@wordpress/interactivity'],
            $frontend_assets['version']
        );

        // Register frontend styles (will be enqueued by BlockSupport when triggers are detected)
        if ( file_exists(PIKARI_GUTENBERG_MODALS_DIR . 'build/frontend/style-index.css') ) {
            wp_register_style(
                'pikari-gutenberg-modals-frontend',
                PIKARI_GUTENBERG_MODALS_URL . 'build/frontend/style-index.css',
                [],
                $frontend_assets['version']
            );
        }
    }
}
