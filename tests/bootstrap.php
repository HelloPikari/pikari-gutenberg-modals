<?php
/**
 * PHPUnit bootstrap file for Pikari Gutenberg Modals
 *
 * @package Pikari_Gutenberg_Modals
 */

// Require composer autoloader if available.
$composer_autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// Define test constants.
define('PIKARI_GUTENBERG_MODALS_TESTS', true);
define('PIKARI_GUTENBERG_MODALS_PATH', dirname(__DIR__));

// Load WordPress test environment if available.
$wp_tests_dir = getenv('WP_TESTS_DIR');
if (! $wp_tests_dir) {
    $wp_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (! file_exists($wp_tests_dir . '/includes/functions.php')) {
    echo "Could not find WordPress test suite at '$wp_tests_dir/includes/functions.php'." . PHP_EOL;
    echo "Please install the WordPress test suite by running the install script:" . PHP_EOL;
    echo "bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]" . PHP_EOL;
    exit(1);
}

// Give access to tests_add_filter() function.
require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin()
{
    require dirname(__DIR__) . '/pikari-gutenberg-modals.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment.
require $wp_tests_dir . '/includes/bootstrap.php';
