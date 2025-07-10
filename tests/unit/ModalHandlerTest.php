<?php
/**
 * Tests for ModalHandler class
 *
 * @package Pikari_Gutenberg_Modals
 */

namespace Pikari\GutenbergModals\Tests;

use Pikari\GutenbergModals\ModalHandler;

require_once __DIR__ . '/class-test-case.php';
require_once dirname(dirname(__DIR__)) . '/includes/ModalHandler.php';

/**
 * Modal Handler test class
 */
class Test_ModalHandler extends Test_Case
{
    /**
     * Instance of ModalHandler
     *
     * @var ModalHandler
     */
    private $handler;
    
    /**
     * Set up test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock filter system
        $this->createMockFilters();
        
        // Create instance
        $this->handler = new ModalHandler();
    }
    
    /**
     * Test URL validation with valid URLs
     */
    public function testValidateUrlWithValidUrls()
    {
        $valid_urls = [
            'https://example.com',
            'http://example.com/page',
            'https://subdomain.example.com/path/to/page',
            'https://example.com:8080/page',
            'https://example.com/page?query=1&param=2',
            'https://example.com/page#section',
        ];
        
        foreach ($valid_urls as $url) {
            $this->assertTrue(
                $this->handler->validate_url($url),
                "URL should be valid: $url"
            );
        }
    }
    
    /**
     * Test URL validation with invalid URLs
     */
    public function testValidateUrlWithInvalidUrls()
    {
        $invalid_urls = [
            'javascript:alert("XSS")',
            'data:text/html,<script>alert("XSS")</script>',
            'vbscript:msgbox("XSS")',
            'not-a-url',
            'ftp://example.com', // Only http/https allowed
            '',
            null,
        ];
        
        foreach ($invalid_urls as $url) {
            $this->assertFalse(
                $this->handler->validate_url($url),
                "URL should be invalid: $url"
            );
        }
    }
    
    /**
     * Test domain allowlist
     */
    public function testAllowedDomains()
    {
        // Mock the filter
        add_filter('pikari_gutenberg_modals_allowed_domains', function ($domains) {
            $domains[] = 'trusted.com';
            return $domains;
        });
        
        // Test allowed domain
        $this->assertTrue($this->handler->validate_url('https://trusted.com/page'));
        
        // Test non-allowed domain when allowlist is active
        // This would depend on implementation - if allowlist is exclusive
    }
    
    /**
     * Test domain blocklist
     */
    public function testBlockedDomains()
    {
        // Mock the filter
        add_filter('pikari_gutenberg_modals_blocked_domains', function ($domains) {
            $domains[] = 'blocked.com';
            return $domains;
        });
        
        // Test blocked domain
        $this->assertFalse($this->handler->validate_url('https://blocked.com/page'));
    }
    
    /**
     * Test local URL detection
     */
    public function testIsLocalUrl()
    {
        // Mock site_url and home_url functions
        if (! function_exists('site_url')) {
            function site_url()
            {
                return 'https://mysite.com';
            }
        }
        
        if (! function_exists('home_url')) {
            function home_url()
            {
                return 'https://mysite.com';
            }
        }
        
        // Test local URLs
        $this->assertTrue($this->handler->is_local_url('https://mysite.com/page'));
        $this->assertTrue($this->handler->is_local_url('https://mysite.com/subdir/page'));
        
        // Test external URLs
        $this->assertFalse($this->handler->is_local_url('https://external.com/page'));
        $this->assertFalse($this->handler->is_local_url('https://subdomain.mysite.com/page'));
    }
    
    /**
     * Test cache duration filter
     */
    public function testCacheDuration()
    {
        // Default should be 3600 (1 hour)
        $this->assertEquals(3600, $this->handler->get_cache_duration());
        
        // Test with constant
        if (! defined('PIKARI_GUTENBERG_MODALS_CACHE_DURATION')) {
            define('PIKARI_GUTENBERG_MODALS_CACHE_DURATION', 7200);
        }
        $this->assertEquals(7200, $this->handler->get_cache_duration());
        
        // Test with filter
        add_filter('pikari_gutenberg_modals_cache_duration', function () {
            return 1800;
        });
        $this->assertEquals(1800, $this->handler->get_cache_duration());
    }
    
    /**
     * Test content processing
     */
    public function testProcessModalContent()
    {
        $content = '<p>Test content</p>';
        $type = 'post';
        $id = 123;
        
        // Test default processing (should add loading indicator)
        $processed = $this->handler->process_modal_content($content, $type, $id);
        $this->assertStringContainsString('modal-loading', $processed);
        $this->assertStringContainsString($content, $processed);
        
        // Test with custom filter
        add_filter('pikari_gutenberg_modals_content', function ($content, $type, $id) {
            return '<div class="custom-wrapper">' . $content . '</div>';
        }, 20, 3);
        
        $processed = $this->handler->process_modal_content($content, $type, $id);
        $this->assertStringContainsString('custom-wrapper', $processed);
    }
    
    /**
     * Test adding loading indicator
     */
    public function testAddLoadingIndicator()
    {
        $content = '<p>Test content</p>';
        
        $result = $this->handler->add_loading_indicator($content);
        
        $this->assertStringContainsString('modal-loading', $result);
        $this->assertStringContainsString('style="display:none"', $result);
        $this->assertStringContainsString($content, $result);
        $this->assertStringContainsString('Loading...', $result);
    }
}