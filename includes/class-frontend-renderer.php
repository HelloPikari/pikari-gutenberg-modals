<?php
/**
 * Frontend Renderer
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class Frontend_Renderer
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
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets(): void
    {
        $frontend_asset_file = PIKARI_GUTENBERG_MODALS_PLUGIN_DIR . 'build/frontend/index.asset.php';
        
        // Check if build exists
        if (!file_exists($frontend_asset_file)) {
            return;
        }
        
        $frontend_assets = include $frontend_asset_file;
        
        // Enqueue frontend script
        wp_enqueue_script(
            'pikari-gutenberg-modals-frontend',
            PIKARI_GUTENBERG_MODALS_PLUGIN_URL . 'build/frontend/index.js',
            $frontend_assets['dependencies'],
            $frontend_assets['version'],
            true
        );
        
        // Localize script with REST API data
        wp_localize_script(
            'pikari-gutenberg-modals-frontend',
            'pikariModalsData',
            [
                'apiUrl' => rest_url('pikari-gutenberg-modals/v1/modal-content/'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]
        );
        
        // Enqueue frontend styles
        if (file_exists(PIKARI_GUTENBERG_MODALS_PLUGIN_DIR . 'build/frontend/style-index.css')) {
            wp_enqueue_style(
                'pikari-gutenberg-modals-frontend',
                PIKARI_GUTENBERG_MODALS_PLUGIN_URL . 'build/frontend/style-index.css',
                [],
                $frontend_assets['version']
            );
        }
    }
}
