<?php
/**
 * Base test case for Pikari Gutenberg Modals
 *
 * @package Pikari_Gutenberg_Modals
 */

namespace Pikari\GutenbergModals\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base test case class
 */
abstract class Test_Case extends TestCase
{
    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Define plugin constants if not already defined
        if (! defined('PIKARI_GUTENBERG_MODALS_VERSION')) {
            define('PIKARI_GUTENBERG_MODALS_VERSION', '0.2.0');
        }
        
        if (! defined('PIKARI_GUTENBERG_MODALS_PLUGIN_DIR')) {
            define('PIKARI_GUTENBERG_MODALS_PLUGIN_DIR', dirname(dirname(__DIR__)) . '/');
        }
        
        if (! defined('PIKARI_GUTENBERG_MODALS_PLUGIN_URL')) {
            define('PIKARI_GUTENBERG_MODALS_PLUGIN_URL', 'https://example.com/wp-content/plugins/pikari-gutenberg-modals/');
        }
        
        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }
    
    /**
     * Mock common WordPress functions
     */
    protected function mockWordPressFunctions()
    {
        // Mock __ function for translations
        if (! function_exists('__')) {
            function __($text, $domain = 'default')
            {
                return $text;
            }
        }
        
        // Mock esc_html function
        if (! function_exists('esc_html')) {
            function esc_html($text)
            {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }
        
        // Mock esc_attr function
        if (! function_exists('esc_attr')) {
            function esc_attr($text)
            {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            }
        }
        
        // Mock wp_kses_post function
        if (! function_exists('wp_kses_post')) {
            function wp_kses_post($content)
            {
                // Simple mock - in reality this would strip dangerous tags
                return strip_tags($content, '<p><br><strong><em><a><ul><ol><li><blockquote><h1><h2><h3><h4><h5><h6>');
            }
        }
        
        // Mock wp_json_encode
        if (! function_exists('wp_json_encode')) {
            function wp_json_encode($data, $options = 0, $depth = 512)
            {
                return json_encode($data, $options, $depth);
            }
        }
        
        // Mock plugin_dir_url
        if (! function_exists('plugin_dir_url')) {
            function plugin_dir_url($file)
            {
                return PIKARI_GUTENBERG_MODALS_PLUGIN_URL;
            }
        }
        
        // Mock plugin_dir_path
        if (! function_exists('plugin_dir_path')) {
            function plugin_dir_path($file)
            {
                return PIKARI_GUTENBERG_MODALS_PLUGIN_DIR;
            }
        }
    }
    
    /**
     * Create a mock filter system
     *
     * @return array
     */
    protected function createMockFilters()
    {
        $filters = [];
        
        // Mock add_filter
        if (! function_exists('add_filter')) {
            function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) use (&$filters)
            {
                if (! isset($filters[$tag])) {
                    $filters[$tag] = [];
                }
                $filters[$tag][] = [
                    'callback' => $callback,
                    'priority' => $priority,
                    'accepted_args' => $accepted_args,
                ];
            }
        }
        
        // Mock apply_filters
        if (! function_exists('apply_filters')) {
            function apply_filters($tag, $value, ...$args) use (&$filters)
            {
                if (! isset($filters[$tag])) {
                    return $value;
                }
                
                foreach ($filters[$tag] as $filter) {
                    $value = call_user_func_array($filter['callback'], array_merge([$value], $args));
                }
                
                return $value;
            }
        }
        
        return $filters;
    }
}