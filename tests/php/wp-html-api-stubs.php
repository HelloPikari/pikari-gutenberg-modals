<?php
/**
 * Minimal WordPress function stubs for tests/php/BlockSupportRenderTest.php.
 *
 * Deliberately in the global namespace (this file has no `namespace`
 * declaration) and in its own file rather than declared inline inside a
 * namespaced class method — a `function` declared inside a method body
 * still belongs to that file's namespace, not the global one, so WP core's
 * html-api classes (which live in the global namespace, like all of
 * WordPress core) would call an undefined
 * Pikari\Tests\GutenbergModals\apply_filters() instead of falling through
 * to the real global one.
 *
 * Only what the real WP_HTML_Tag_Processor and ModalHandler::validate_url()
 * call is stubbed here — see BlockSupportRenderTest::load_wp_html_api() for
 * how this is loaded, and why it is not Brain\Monkey.
 *
 * @package Pikari\Tests\GutenbergModals
 */

if ( function_exists( '__' ) ) {
    return;
}

function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
    return $text;
}

function esc_attr( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
    return $url;
}

function esc_url_raw( $url ) {
    return $url;
}

function _doing_it_wrong( $function_name, $message, $version ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
}

function wp_parse_url( $url ) {
    return parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

function apply_filters( $tag, $value, ...$args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    return $value;
}

function add_filter( ...$args ) {
    return true;
}

function add_action( ...$args ) {
    return true;
}

function wp_unique_id( $prefix = '' ) {
    return $prefix . 'test';
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
    return json_encode( $data, $options, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

function url_to_postid( $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    return 0;
}

function wp_enqueue_script_module( $handle ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
}

function wp_enqueue_style( $handle ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
}

function wp_interactivity_config( $store, $config ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
}

function rest_url( $path = '' ) {
    return 'https://example.com/wp-json/' . ltrim( $path, '/' );
}

/**
 * Copied verbatim from wp-includes/kses.php::wp_kses_uri_attributes(),
 * minus its apply_filters() extension point (unused here), so
 * WP_HTML_Tag_Processor::set_attribute() does not need the rest of the
 * kses subsystem loaded just to check whether an attribute is a URI.
 */
function wp_kses_uri_attributes() {
    return [
        'action', 'archive', 'background', 'cite', 'classid', 'codebase',
        'data', 'formaction', 'href', 'icon', 'longdesc', 'manifest',
        'poster', 'profile', 'src', 'usemap', 'xmlns',
    ];
}
