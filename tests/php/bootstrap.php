<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads the Composer autoloader and base TestCase class.
 * Brain\Monkey handles WordPress function mocking — no WordPress installation needed.
 *
 * @package Pikari\Tests
 */

// Load Composer autoloader (provides Brain\Monkey, Mockery, and plugin classes).
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Load the base TestCase class (handles Brain\Monkey setUp/tearDown).
require_once __DIR__ . '/TestCase.php';

// Stub WordPress classes not available outside a full WordPress environment.
if ( ! class_exists( 'WP_Block_Template' ) ) {
	// Minimal stub matching WordPress core's WP_Block_Template public properties.
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	class WP_Block_Template {
		public string $id             = '';
		public string $slug           = '';
		public string $theme          = '';
		public string $type           = '';
		public string $source         = '';
		public string $origin         = '';
		public string $title          = '';
		public string $description    = '';
		public string $status         = '';
		public bool   $has_theme_file = false;
		public bool   $is_custom      = false;
		public string $area           = '';
		public string $content        = '';
	}
}
