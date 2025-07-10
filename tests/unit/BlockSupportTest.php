<?php
/**
 * Tests for BlockSupport class
 *
 * @package Pikari_Gutenberg_Modals
 */

namespace Pikari\GutenbergModals\Tests;

use Pikari\GutenbergModals\BlockSupport;

require_once __DIR__ . '/class-test-case.php';
require_once dirname(dirname(__DIR__)) . '/includes/BlockSupport.php';

/**
 * Block Support test class
 */
class Test_BlockSupport extends Test_Case
{
    /**
     * Instance of BlockSupport
     *
     * @var BlockSupport
     */
    private $block_support;
    
    /**
     * Set up test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock filter system
        $this->createMockFilters();
        
        // Mock add_action
        if (! function_exists('add_action')) {
            function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
            {
                // Mock implementation
            }
        }
        
        // Create instance
        $this->block_support = new BlockSupport();
    }
    
    /**
     * Test get supported blocks returns defaults
     */
    public function testGetSupportedBlocksDefaults()
    {
        $blocks = $this->block_support->get_supported_blocks();
        
        $expected = [
            'core/paragraph',
            'core/heading',
            'core/list',
            'core/list-item',
            'core/quote',
            'core/verse',
            'core/preformatted',
            'core/navigation-link',
        ];
        
        $this->assertEquals($expected, $blocks);
        $this->assertNotContains('core/button', $blocks, 'Button block should not be supported');
    }
    
    /**
     * Test supported blocks filter
     */
    public function testSupportedBlocksFilter()
    {
        // Add custom block via filter
        add_filter('pikari_gutenberg_modals_supported_blocks', function ($blocks) {
            $blocks[] = 'custom/block';
            return $blocks;
        });
        
        $blocks = $this->block_support->get_supported_blocks();
        
        $this->assertContains('custom/block', $blocks);
        $this->assertContains('core/paragraph', $blocks); // Still has defaults
    }
    
    /**
     * Test filter block with modal span
     */
    public function testFilterBlockWithModalSpan()
    {
        $block_content = '<p>Text with <span class="modal-link-trigger" data-modal-content-type="post" data-modal-content-id="123">modal link</span>.</p>';
        $block = [
            'blockName' => 'core/paragraph',
            'attrs' => [],
        ];
        
        $result = $this->block_support->filter_block($block_content, $block);
        
        // Should contain data-has-modal attribute
        $this->assertStringContainsString('data-has-modal="true"', $result);
        
        // Should have modified the span
        $this->assertStringContainsString('data-modal-id=', $result);
    }
    
    /**
     * Test filter block without modal span
     */
    public function testFilterBlockWithoutModalSpan()
    {
        $block_content = '<p>Regular text without modal.</p>';
        $block = [
            'blockName' => 'core/paragraph',
            'attrs' => [],
        ];
        
        $result = $this->block_support->filter_block($block_content, $block);
        
        // Should return unchanged
        $this->assertEquals($block_content, $result);
    }
    
    /**
     * Test filter block with unsupported block type
     */
    public function testFilterBlockUnsupportedType()
    {
        $block_content = '<button>Click me</button>';
        $block = [
            'blockName' => 'core/button',
            'attrs' => [],
        ];
        
        $result = $this->block_support->filter_block($block_content, $block);
        
        // Should return unchanged
        $this->assertEquals($block_content, $result);
    }
    
    /**
     * Test extract modal config
     */
    public function testExtractModalConfig()
    {
        $span = '<span class="modal-link-trigger" data-modal-content-type="page" data-modal-content-id="456" data-modal-link=\'{"url":"/test-page/","title":"Test Page"}\'>link</span>';
        
        $config = $this->block_support->extract_modal_config($span);
        
        $this->assertEquals('page', $config['type']);
        $this->assertEquals('456', $config['id']);
        $this->assertArrayHasKey('link_data', $config);
        
        $link_data = json_decode($config['link_data'], true);
        $this->assertEquals('/test-page/', $link_data['url']);
        $this->assertEquals('Test Page', $link_data['title']);
    }
    
    /**
     * Test create trigger span
     */
    public function testCreateTriggerSpan()
    {
        $inner_html = 'Click here';
        $config = [
            'type' => 'post',
            'id' => '789',
            'modal_id' => 'modal-123',
            'link_data' => '{"url":"/post/","title":"Post"}',
        ];
        
        $result = $this->block_support->create_trigger_span($inner_html, $config);
        
        $this->assertStringContainsString('modal-link-trigger has-modal-link', $result);
        $this->assertStringContainsString('data-modal-content-type="post"', $result);
        $this->assertStringContainsString('data-modal-content-id="789"', $result);
        $this->assertStringContainsString('data-modal-id="modal-123"', $result);
        $this->assertStringContainsString('role="button"', $result);
        $this->assertStringContainsString('tabindex="0"', $result);
        $this->assertStringContainsString('Click here', $result);
    }
    
    /**
     * Test get modal content for post
     */
    public function testGetModalContentPost()
    {
        // Mock get_post function
        if (! function_exists('get_post')) {
            function get_post($id)
            {
                if ($id == 123) {
                    return (object) [
                        'ID' => 123,
                        'post_title' => 'Test Post',
                        'post_content' => '<p>Test content</p>',
                    ];
                }
                return null;
            }
        }
        
        // Mock Modal_Handler instance
        $this->block_support->modal_handler = new \Pikari\GutenbergModals\Modal_Handler();
        
        $content = $this->block_support->get_modal_content('post', 123);
        
        $this->assertNotNull($content);
        $this->assertArrayHasKey('title', $content);
        $this->assertArrayHasKey('content', $content);
        $this->assertEquals('Test Post', $content['title']);
    }
    
    /**
     * Test process modal span replacement
     */
    public function testProcessModalSpan()
    {
        $matches = [
            '<span class="modal-link-trigger" data-modal-content-type="url" data-modal-content-id="https://example.com">External</span>',
            ' class="modal-link-trigger"',
            'External',
        ];
        
        $result = $this->block_support->process_modal_span($matches);
        
        $this->assertStringContainsString('has-modal-link', $result);
        $this->assertStringContainsString('data-modal-id=', $result);
        $this->assertStringContainsString('External', $result);
    }
}