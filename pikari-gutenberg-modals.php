<?php
/**
 * Plugin Name: Pikari Gutenberg Modals
 * Plugin URI:  https://pikari.io
 * Description: Modal windows for the WordPress Gutenberg block editor. Adds accessible modal dialogs
 * Version:     2.1.0
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
define( 'PIKARI_GUTENBERG_MODALS_VERSION', '2.1.0' );

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
 * Check for plugin updates via GitHub releases.
 *
 * The Composer autoloader is loaded here rather than at the top of the file, and
 * guarded. Unlike pikari-team, this plugin has no runtime Composer dependencies
 * apart from the update checker, so a developer who has run `npm install` but not
 * `composer install` would otherwise get a fatal error on a file that is only
 * needed to check for updates. The release ZIP always ships vendor/, so in a real
 * install the guard never fires.
 */
if ( file_exists( PIKARI_GUTENBERG_MODALS_DIR . 'vendor/autoload.php' ) ) {
    require_once PIKARI_GUTENBERG_MODALS_DIR . 'vendor/autoload.php';

    $pikari_gutenberg_modals_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/HelloPikari/pikari-gutenberg-modals/',
        __FILE__,
        'pikari-gutenberg-modals'
    );

    $pikari_gutenberg_modals_vcs_api = $pikari_gutenberg_modals_update_checker->getVcsApi();
    $pikari_gutenberg_modals_vcs_api->enableReleaseAssets(
        '/pikari-gutenberg-modals.*\.zip/',
        // PUC's VCS classes live under a MINOR-version namespace — v5p7 today,
        // v5p6 before — and only PucFactory is aliased to v5. Hardcoding the
        // class would fatal on the next point release, so the constant is read
        // off the concrete instance. REQUIRE_RELEASE_ASSETS is mandatory: the
        // default PREFER_RELEASE_ASSETS falls back to GitHub's generated source
        // archive when a release has no ZIP, and that archive has no build/.
        constant( get_class( $pikari_gutenberg_modals_vcs_api ) . '::REQUIRE_RELEASE_ASSETS' )
    );
}

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
    register_block_type( PIKARI_GUTENBERG_MODALS_DIR . 'build/blocks/modal-overlay' );

    // Initialize main components.
    new \Pikari\GutenbergModals\ModalHandler();
    new \Pikari\GutenbergModals\EditorIntegration();
    new \Pikari\GutenbergModals\FrontendRenderer();
    new \Pikari\GutenbergModals\BlockSupport();
    new \Pikari\GutenbergModals\GroupModalTriggerSupport();
    new \Pikari\GutenbergModals\RestApi();
    new \Pikari\GutenbergModals\SpeculativeLoading();
    new \Pikari\GutenbergModals\ModalTemplatePart();
    new \Pikari\GutenbergModals\ModalPatterns();
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
