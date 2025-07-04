<?php
/**
 * Uninstall Pikari Gutenberg Modals
 * 
 * @package PikariGutenbergModals
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Currently, this plugin doesn't store any options or custom tables
// If we add any in the future, clean them up here

// Example cleanup code (currently not needed):
// delete_option('pikari_gutenberg_modals_options');
// global $wpdb;
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pikari_gutenberg_modals_data");