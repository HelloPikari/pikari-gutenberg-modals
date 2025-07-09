<?php
/**
 * Mock WordPress functions for unit testing
 *
 * @package Pikari_Gutenberg_Modals
 */

// Prevent direct access.
if ( ! defined( 'PIKARI_GUTENBERG_MODALS_TESTS' ) ) {
	exit;
}

// Mock WordPress functions if they don't exist.
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// Mock implementation
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// Mock implementation
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
		// Mock implementation
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
		// Mock implementation
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		// Mock implementation
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $object_name, $l10n ) {
		// Mock implementation
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.com/wp-content/plugins/pikari-gutenberg-modals/';
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( dirname( __DIR__ ) ) . '/';
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'test_nonce_123';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $content ) {
		return strip_tags( $content, '<p><a><strong><em><ul><ol><li><blockquote><h1><h2><h3><h4><h5><h6>' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url, $protocols = null, $_context = 'display' ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( intval( $maybeint ) );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return defined( 'WP_ADMIN' ) && WP_ADMIN;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
		if ( is_numeric( $post ) ) {
			return (object) array(
				'ID'           => $post,
				'post_title'   => 'Test Post',
				'post_content' => '<p>Test content</p>',
				'post_type'    => 'post',
				'post_status'  => 'publish',
			);
		}
		return null;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0, $leavename = false ) {
		return 'http://example.com/test-post/';
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) {
		return 'Test Post Title';
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return true; // Mock as having all capabilities
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$parsed_args = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$parsed_args = $args;
		} else {
			wp_parse_str( $args, $parsed_args );
		}

		if ( is_array( $defaults ) && $defaults ) {
			return array_merge( $defaults, $parsed_args );
		}
		return $parsed_args;
	}
}

if ( ! function_exists( 'wp_parse_str' ) ) {
	function wp_parse_str( $string, &$array ) {
		parse_str( $string, $array );
	}
}

// Define constants if not defined
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', false );
}