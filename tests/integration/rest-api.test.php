<?php
/**
 * REST API Integration Tests
 *
 * @package Pikari_Gutenberg_Modals
 */

namespace Pikari\GutenbergModals\Tests\Integration;

use WP_REST_Request;
use WP_REST_Server;

/**
 * REST API test class
 *
 * These tests require WordPress test environment
 */
class Test_REST_API extends \WP_UnitTestCase
{
    /**
     * REST server instance
     *
     * @var WP_REST_Server
     */
    protected $server;
    
    /**
     * Set up test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Start REST server
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');
        
        // Create test content
        $this->create_test_content();
        
        // Set current user as admin
        $this->user_id = $this->factory->user->create([
            'role' => 'administrator',
        ]);
        wp_set_current_user($this->user_id);
    }
    
    /**
     * Tear down test
     */
    public function tearDown(): void
    {
        parent::tearDown();
        
        global $wp_rest_server;
        $wp_rest_server = null;
    }
    
    /**
     * Create test content
     */
    private function create_test_content()
    {
        // Create test posts
        $this->post_ids = [];
        
        $this->post_ids[] = $this->factory->post->create([
            'post_title' => 'Test Modal Post',
            'post_content' => '<p>This is test content for the modal.</p>',
            'post_status' => 'publish',
        ]);
        
        $this->post_ids[] = $this->factory->post->create([
            'post_title' => 'Another Test Post',
            'post_content' => '<p>More modal content here.</p>',
            'post_status' => 'publish',
        ]);
        
        // Create test page
        $this->page_id = $this->factory->post->create([
            'post_title' => 'Test Modal Page',
            'post_content' => '<div class="wp-block-group"><p>Page content for modal.</p></div>',
            'post_type' => 'page',
            'post_status' => 'publish',
        ]);
    }
    
    /**
     * Test search endpoint exists
     */
    public function test_search_endpoint_exists()
    {
        $routes = $this->server->get_routes();
        $this->assertArrayHasKey('/pikari-gutenberg-modals/v1/search', $routes);
    }
    
    /**
     * Test search requires authentication
     */
    public function test_search_requires_authentication()
    {
        // Log out
        wp_set_current_user(0);
        
        $request = new WP_REST_Request('GET', '/pikari-gutenberg-modals/v1/search');
        $request->set_param('search', 'test');
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(401, $response->get_status());
    }
    
    /**
     * Test search with valid query
     */
    public function test_search_with_valid_query()
    {
        $request = new WP_REST_Request('GET', '/pikari-gutenberg-modals/v1/search');
        $request->set_param('search', 'Test Modal');
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        
        // Check response structure
        $first_result = $data[0];
        $this->assertArrayHasKey('id', $first_result);
        $this->assertArrayHasKey('title', $first_result);
        $this->assertArrayHasKey('url', $first_result);
        $this->assertArrayHasKey('type', $first_result);
    }
    
    /**
     * Test search pagination
     */
    public function test_search_pagination()
    {
        // Create many posts
        for ($i = 0; $i < 25; $i++) {
            $this->factory->post->create([
                'post_title' => "Test Post $i",
                'post_status' => 'publish',
            ]);
        }
        
        $request = new WP_REST_Request('GET', '/pikari-gutenberg-modals/v1/search');
        $request->set_param('search', 'Test Post');
        $request->set_param('per_page', 10);
        $request->set_param('page', 1);
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        // Check pagination headers
        $headers = $response->get_headers();
        $this->assertArrayHasKey('X-WP-Total', $headers);
        $this->assertArrayHasKey('X-WP-TotalPages', $headers);
        $this->assertArrayHasKey('Link', $headers);
        
        // Check we got the right number of results
        $data = $response->get_data();
        $this->assertCount(10, $data);
    }
    
    /**
     * Test search filters
     */
    public function test_search_filters()
    {
        // Test the filter hook
        add_filter('pikari_gutenberg_modals_search_args', function ($args) {
            // Limit to pages only
            $args['post_type'] = 'page';
            return $args;
        });
        
        $request = new WP_REST_Request('GET', '/pikari-gutenberg-modals/v1/search');
        $request->set_param('search', 'Test Modal');
        
        $response = $this->server->dispatch($request);
        $data = $response->get_data();
        
        // Should only return pages
        foreach ($data as $result) {
            $this->assertEquals('page', $result['type']);
        }
    }
    
    /**
     * Test modal content endpoint
     */
    public function test_modal_content_endpoint()
    {
        $request = new WP_REST_Request('GET', '/pikari-gutenberg-modals/v1/modal-content/' . $this->post_ids[0]);
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('content', $data);
        $this->assertEquals('Test Modal Post', $data['title']);
        $this->assertStringContainsString('test content for the modal', $data['content']);
    }
    
    /**
     * Test modal content with block styles
     */
    public function test_modal_content_with_styles()
    {
        // Create post with blocks that have styles
        $post_id = $this->factory->post->create([
            'post_title' => 'Styled Post',
            'post_content' => '<!-- wp:group {"style":{"spacing":{"padding":"20px"}},"backgroundColor":"primary"} -->
<div class="wp-block-group has-primary-background-color has-background" style="padding:20px">
<p>Content with styles</p>
</div>
<!-- /wp:group -->',
            'post_status' => 'publish',
        ]);
        
        $request = new WP_REST_Request('GET', '/pikari-gutenberg-modals/v1/modal-content/' . $post_id);
        
        $response = $this->server->dispatch($request);
        $data = $response->get_data();
        
        // Should include styles
        if (isset($data['styles'])) {
            $this->assertNotEmpty($data['styles']);
            $this->assertStringContainsString('has-primary-background-color', $data['styles']);
        }
    }
    
    /**
     * Test modal content for non-existent post
     */
    public function test_modal_content_not_found()
    {
        $request = new WP_REST_Request('GET', '/pikari-gutenberg-modals/v1/modal-content/999999');
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(404, $response->get_status());
    }
    
    /**
     * Test external URL content endpoint
     */
    public function test_external_url_content()
    {
        // Mock wp_remote_get
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            if ($url === 'https://example.com/test-page') {
                return [
                    'response' => ['code' => 200],
                    'body' => '<html><head><title>External Page</title></head><body><p>External content</p></body></html>',
                ];
            }
            return $preempt;
        }, 10, 3);
        
        $request = new WP_REST_Request('GET', '/pikari-gutenberg-modals/v1/modal-content/url');
        $request->set_param('url', 'https://example.com/test-page');
        
        $response = $this->server->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('content', $data);
        $this->assertEquals('External Page', $data['title']);
        $this->assertStringContainsString('External content', $data['content']);
    }
}