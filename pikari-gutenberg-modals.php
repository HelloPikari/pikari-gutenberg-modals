<?php
/**
 * Plugin Name: Pikari Gutenberg Modals
 * Plugin URI: https://github.com/HelloPikari/pikari-gutenberg-modals
 * Description: Beautiful modal windows for the WordPress block editor. Create engaging content with smooth animations and accessible modal dialogs.
 * Version: 0.3.2
 * Author: Pikari Inc.
 * Author URI: https://pikari.com
 * Text Domain: pikari-gutenberg-modals
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package PikariGutenbergModals
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Load the plugin functionality.
require_once __DIR__ . '/includes/plugin.php';
