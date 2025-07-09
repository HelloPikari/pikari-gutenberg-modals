<?php
/**
 * Main plugin functionality
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('PIKARI_GUTENBERG_MODALS_VERSION', '0.1.0-alpha');
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
        $file           = $base_dir . 'class-' . str_replace('\\', '-', str_replace('_', '-', strtolower($relative_class))) . '.php';

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
        new Modal_Handler();
        new Editor_Integration();
        new Frontend_Renderer();
        new Block_Support();
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

        if (version_compare(PHP_VERSION, '8.3', '<')) {
            deactivate_plugins(plugin_basename(dirname(__FILE__) . '/../pikari-gutenberg-modals.php'));
            wp_die(
                esc_html__('This plugin requires PHP 8.3 or higher.', 'pikari-gutenberg-modals')
            );
        }
    }
);

// Register REST API endpoint for enhanced search.
add_action(
    'rest_api_init',
    function () {
        register_rest_route(
            'pikari-gutenberg-modals/v1',
            '/search',
            array(
            'methods'             => 'GET',
            'callback'            => __NAMESPACE__ . '\\search_modal_content',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args'                => array(
            'search'   => array(
            'required'          => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'description'       => __('Search term to find posts.', 'pikari-gutenberg-modals'),
            ),
            'per_page' => array(
            'default'           => 20,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'description'       => __('Number of results per page.', 'pikari-gutenberg-modals'),
            ),
            'page' => array(
            'default'           => 1,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'description'       => __('Page number for pagination.', 'pikari-gutenberg-modals'),
            ),
            ),
            )
        );

      // Register modal content endpoint
        register_rest_route(
            'pikari-gutenberg-modals/v1',
            '/modal-content/(?P<id>\d+)',
            array(
            'methods'             => 'GET',
            'callback'            => __NAMESPACE__ . '\\get_modal_content',
            'permission_callback' => '__return_true', // Public endpoint for frontend use
            'args'                => array(
            'id' => array(
            'required'          => true,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'validate_callback' => function ($param) {
                return is_numeric($param);
            },
            'description'       => __('Post ID to retrieve content for.', 'pikari-gutenberg-modals'),
            ),
            ),
            )
        );
    }
);

/**
 * Search function for modal content.
 *
 * Provides a REST API endpoint for searching WordPress content
 * to be displayed in modals. Returns formatted results suitable
 * for the LinkControl component.
 *
 * @param \WP_REST_Request $request The REST request object.
 * @return \WP_REST_Response The search results with pagination info.
 */
function search_modal_content($request)
{
    $search   = $request->get_param('search');
    $per_page = $request->get_param('per_page');
    $page     = $request->get_param('page') ?: 1;

  // Build query arguments
    $args = array(
    's'              => $search,
    'posts_per_page' => $per_page,
    'paged'          => $page,
    'post_type'      => get_post_types(array( 'public' => true )),
    'post_status'    => 'publish',
    'orderby'        => 'relevance date',
    'order'          => 'DESC',
    );

  /**
   * Filter the search query arguments.
   *
   * @param array $args WP_Query arguments
   * @param string $search The search term
   */
    $args = apply_filters('modal_toolbar_search_args', $args, $search);

  // Execute search query
    $query = new \WP_Query($args);

  // Format results for LinkControl
    $results = array();
    foreach ($query->posts as $post) {
      // Get post type object for label
        $post_type_obj = get_post_type_object($post->post_type);

        $results[] = array(
        'id'      => $post->ID,
        'title'   => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
        'type'    => $post->post_type,
        'subtype' => $post->post_type, // For LinkControl compatibility
        'url'     => get_permalink($post),
        'kind'    => 'post-type',
        'date'    => get_the_date('c', $post), // ISO 8601 format
        // Additional metadata for display
        '_embedded' => array(
        'self' => array(
          array(
            'post_type_label' => $post_type_obj->labels->singular_name,
            'excerpt'         => wp_trim_words($post->post_excerpt ?: $post->post_content, 20),
          ),
        ),
        ),
        );
    }

  // Prepare response with pagination headers
    $response = rest_ensure_response($results);

  // Add pagination headers
    $response->header('X-WP-Total', $query->found_posts);
    $response->header('X-WP-TotalPages', $query->max_num_pages);

  // Add Link header for pagination
    $links = array();

    if ($page > 1) {
        $links['prev'] = add_query_arg('page', $page - 1, $request->get_route());
    }

    if ($page < $query->max_num_pages) {
        $links['next'] = add_query_arg('page', $page + 1, $request->get_route());
    }

    if (! empty($links)) {
        $response->link_header('Link', $links);
    }

    return $response;
}

/**
 * Get modal content with styles via REST API.
 *
 * Returns both the rendered content and associated block support styles
 * for proper display in modal windows.
 *
 * @param \WP_REST_Request $request The REST request object.
 * @return \WP_REST_Response|WP_Error The modal content or error.
 */
function get_modal_content($request)
{
    $post_id = $request->get_param('id');
  
  // Get the post
    $post = get_post($post_id);
  
    if (! $post || $post->post_status !== 'publish') {
        return new \WP_Error(
            'post_not_found',
            __('Post not found or not published.', 'pikari-gutenberg-modals'),
            array( 'status' => 404 )
        );
    }
  
  // Use the Block_Support class method to get content with properly captured styles
    $block_support = new Block_Support();
  
  // Get content and styles using the working method
    $content_data = $block_support->get_post_content_with_styles($post);
  
  // Extract CSS from style tag if present
    $styles = '';
    if (! empty($content_data['styles'])) {
      // Extract content between style tags
        if (preg_match('/<style[^>]*>(.*?)<\/style>/s', $content_data['styles'], $matches)) {
            $styles = $matches[1];
        }
    }
  
  // Prepare response data
    $response_data = array(
    'id'      => $post->ID,
    'title'   => get_the_title($post),
    'content' => $content_data['content'],
    'styles'  => $styles,
    'type'    => $post->post_type,
    );
  
  /**
   * Filter the modal content response.
   *
   * @param array $response_data The response data
   * @param WP_Post $post The post object
   */
    $response_data = apply_filters('pikari_gutenberg_modals_content_response', $response_data, $post);
  
    return rest_ensure_response($response_data);
}
