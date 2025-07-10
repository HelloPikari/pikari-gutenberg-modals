<?php
/**
 * Main plugin functionality
 *
 * @package PikariGutenbergModals
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('PIKARI_GUTENBERG_MODALS_VERSION', '0.3.1');
define('PIKARI_GUTENBERG_MODALS_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
define('PIKARI_GUTENBERG_MODALS_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));

// Autoloader for plugin classes.
spl_autoload_register(
    function ($class) {
        $prefix   = 'Pikari\\GutenbergModals\\';
        $base_dir = PIKARI_GUTENBERG_MODALS_PLUGIN_DIR . 'includes/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    }
);

// Initialize the plugin.
add_action(
    'plugins_loaded',
    function () {
      // Load text domain.
        load_plugin_textdomain(
            'pikari-gutenberg-modals',
            false,
            dirname(plugin_basename(dirname(__FILE__))) . '/languages'
        );

      // Initialize main components.
        new \Pikari\GutenbergModals\ModalHandler();
        new \Pikari\GutenbergModals\EditorIntegration();
        new \Pikari\GutenbergModals\FrontendRenderer();
        new \Pikari\GutenbergModals\BlockSupport();
        new \Pikari\GutenbergModals\RestApi();
    }
);

// Activation hook.
register_activation_hook(
    dirname(__FILE__) . '/../pikari-gutenberg-modals.php',
    function () {
      // Check minimum requirements.
        if (version_compare(get_bloginfo('version'), '6.8', '<')) {
            deactivate_plugins(plugin_basename(dirname(__FILE__) . '/../pikari-gutenberg-modals.php'));
            wp_die(
                esc_html__('This plugin requires WordPress 6.8 or higher.', 'pikari-gutenberg-modals')
            );
        }

        if (version_compare(PHP_VERSION, '8.2', '<')) {
            deactivate_plugins(plugin_basename(dirname(__FILE__) . '/../pikari-gutenberg-modals.php'));
            wp_die(
                esc_html__('This plugin requires PHP 8.2 or higher.', 'pikari-gutenberg-modals')
            );
        }
    }
);

