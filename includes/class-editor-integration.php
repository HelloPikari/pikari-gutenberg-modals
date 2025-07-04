<?php
/**
 * Editor Integration
 * 
 * IMPORTANT: WordPress 5.8+ uses an iframe-based editor for better isolation.
 * Styles must use !important declarations and include .editor-styles-wrapper
 * selectors to ensure they apply within the iframe context.
 * 
 * @see https://make.wordpress.org/core/2021/06/29/blocks-in-an-iframed-template-editor/
 * 
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class Editor_Integration {
    /**
     * Block support instance
     * 
     * @var Block_Support
     */
    private Block_Support $block_support;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into editor asset loading
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        
        // Add editor styles for iframe editor
        add_action('after_setup_theme', [$this, 'add_editor_styles']);
    }
    
    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets(): void {
        $editor_asset_file = PIKARI_GUTENBERG_MODALS_PLUGIN_DIR . 'build/editor/index.asset.php';
        
        // Check if build exists
        if (!file_exists($editor_asset_file)) {
            error_log('Pikari Gutenberg Modals: Editor asset file not found at ' . $editor_asset_file);
            return;
        }
        
        $editor_assets = include $editor_asset_file;
        
        // Enqueue editor script
        wp_enqueue_script(
            'pikari-gutenberg-modals-editor',
            PIKARI_GUTENBERG_MODALS_PLUGIN_URL . 'build/editor/index.js',
            $editor_assets['dependencies'],
            $editor_assets['version'],
            true
        );
        
        // Get block support instance
        if (!isset($this->block_support)) {
            $this->block_support = new Block_Support();
        }
        
        // Localize script with data
        wp_localize_script('pikari-gutenberg-modals-editor', 'pikariGutenbergModals', [
            'supportedBlocks' => $this->block_support->get_supported_blocks_for_js(),
            'restUrl' => rest_url('pikari-gutenberg-modals/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'defaultSettings' => [
                'size' => 'medium',
                'animation' => 'fade',
                'closeOnClickOutside' => true,
                'showCloseButton' => true,
                'overlayOpacity' => 0.8,
            ],
        ]);
        
        // Enqueue editor styles
        if (file_exists(PIKARI_GUTENBERG_MODALS_PLUGIN_DIR . 'build/editor/style-index.css')) {
            wp_enqueue_style(
                'pikari-gutenberg-modals-editor',
                PIKARI_GUTENBERG_MODALS_PLUGIN_URL . 'build/editor/style-index.css',
                [],
                $editor_assets['version']
            );
        }
    }
    
    /**
     * Add editor styles for iframe editor
     */
    public function add_editor_styles(): void {
        // Add styles to the iframe editor
        add_editor_style(PIKARI_GUTENBERG_MODALS_PLUGIN_URL . 'build/editor/style-index.css');
    }
}