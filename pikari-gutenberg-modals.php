<?php
/**
 * Plugin Name: Pikari Gutenberg Modals
 * Plugin URI:  https://pikari.io
 * Description: Modal windows for the WordPress Gutenberg block editor. Adds accessible modal dialogs
 * Version:     1.3.0
 * Author:      Pikari Inc.
 * Author URI:  https://pikari.io
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pikari-gutenberg-modals
 * Domain Path: /languages
 * Requires at least: 6.8
 * Tested up to: 7.1
 * Requires PHP: 8.4
 *
 * @package pikari-gutenberg-modals
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version.
 */
define( 'PIKARI_GUTENBERG_MODALS_VERSION', '1.3.0' );

/**
 * Plugin directory path.
 */
define( 'PIKARI_GUTENBERG_MODALS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'PIKARI_GUTENBERG_MODALS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Default cache duration for external content (in seconds).
 * Can be overridden by defining the constant in wp-config.php
 */
define( 'PIKARI_GUTENBERG_MODALS_CACHE_DURATION', 1 * HOUR_IN_SECONDS );

// Autoloader for plugin classes.
spl_autoload_register(
    function ( $class ) {
        $prefix   = 'Pikari\\GutenbergModals\\';
        $base_dir = PIKARI_GUTENBERG_MODALS_DIR . 'includes/';

        $len = strlen($prefix);
        if ( strncmp($prefix, $class, $len) !== 0 ) {
            return;
        }

        $relative_class = substr($class, $len);
        $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if ( file_exists($file) ) {
            require $file;
        }
    }
);

/**
 * Initialize the plugin.
 */
function pikari_gutenberg_modals_init() {
    // Load plugin text domain.
    load_plugin_textdomain( 'pikari-gutenberg-modals', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Hook into WordPress.
    add_action( 'wp_enqueue_scripts', 'pikari_gutenberg_modals_enqueue_scripts' );

    // Register blocks.
    register_block_type( PIKARI_GUTENBERG_MODALS_DIR . 'build/blocks/close-button' );
    register_block_type( PIKARI_GUTENBERG_MODALS_DIR . 'build/blocks/content-area' );
    register_block_type( PIKARI_GUTENBERG_MODALS_DIR . 'build/blocks/modal-content' );
    register_block_type( PIKARI_GUTENBERG_MODALS_DIR . 'build/blocks/modal-dialog' );
    register_block_type( PIKARI_GUTENBERG_MODALS_DIR . 'build/blocks/modal-trigger' );

    // Initialize main components.
    new \Pikari\GutenbergModals\ModalHandler();
    new \Pikari\GutenbergModals\EditorIntegration();
    new \Pikari\GutenbergModals\FrontendRenderer();
    new \Pikari\GutenbergModals\BlockSupport();
    new \Pikari\GutenbergModals\GroupModalTriggerSupport();
    new \Pikari\GutenbergModals\RestApi();
    new \Pikari\GutenbergModals\SpeculativeLoading();
    new \Pikari\GutenbergModals\ModalTemplatePart();
}
// add_action( 'plugins_loaded', 'pikari_gutenberg_modals_init' );
add_action( 'init', 'pikari_gutenberg_modals_init' );

/**
 * Enqueue plugin scripts and styles.
 */
function pikari_gutenberg_modals_enqueue_scripts() {
    // Enqueue your scripts and styles here.
    // Example:
    // wp_enqueue_style( 'pikari-gutenberg-modals', PIKARI_GUTENBERG_MODALS_URL . 'assets/css/style.css', array(), PIKARI_GUTENBERG_MODALS_VERSION );
    // wp_enqueue_script( 'pikari-gutenberg-modals', PIKARI_GUTENBERG_MODALS_URL . 'assets/js/script.js', array( 'jquery' ), PIKARI_GUTENBERG_MODALS_VERSION, true );
}

/**
 * Activation hook.
 */
function pikari_gutenberg_modals_activate() {
    // Code to run on plugin activation.
    // Check minimum requirements.
    if ( version_compare(get_bloginfo('version'), '6.8', '<') ) {
        deactivate_plugins(plugin_basename(__DIR__ . '/pikari-gutenberg-modals.php'));
        wp_die(
            esc_html__('This plugin requires WordPress 6.8 or higher.', 'pikari-gutenberg-modals')
        );
    }

    if ( version_compare(PHP_VERSION, '8.4', '<') ) {
        deactivate_plugins(plugin_basename(__DIR__ . '/pikari-gutenberg-modals.php'));
        wp_die(
            esc_html__('This plugin requires PHP 8.4 or higher.', 'pikari-gutenberg-modals')
        );
    }

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pikari_gutenberg_modals_activate' );

/**
 * Deactivation hook.
 */
function pikari_gutenberg_modals_deactivate() {
    // Code to run on plugin deactivation.
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pikari_gutenberg_modals_deactivate' );
