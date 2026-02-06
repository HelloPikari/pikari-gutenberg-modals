<?php
/**
 * Block Style Collector
 *
 * Detects blocks in content and collects their stylesheet URLs for dynamic loading.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class BlockStyleCollector
{
    /**
     * Get block style URLs for content.
     *
     * Parses the content to find all blocks, then looks up their registered
     * stylesheets in the WordPress block type registry.
     *
     * @param string $content The post content to analyze.
     * @return array Array with 'urls' key containing stylesheet URLs.
     */
    public function get_block_styles_for_content( string $content ): array
    {
        $blocks = parse_blocks( $content );
        $block_names = $this->extract_block_names_recursive( $blocks );
        $style_urls = $this->get_style_urls_for_blocks( $block_names );

        return array(
            'urls' => array_values( array_unique( $style_urls ) ),
        );
    }

    /**
     * Recursively extract all block names from parsed blocks.
     *
     * Handles nested blocks (inner blocks) to ensure all block styles are collected.
     *
     * @param array $blocks Array of parsed blocks from parse_blocks().
     * @return array Array of unique block names (e.g., 'core/button', 'core/paragraph').
     */
    private function extract_block_names_recursive( array $blocks ): array
    {
        $names = array();

        foreach ( $blocks as $block ) {
            if ( ! empty( $block['blockName'] ) ) {
                $names[] = $block['blockName'];
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                $names = array_merge(
                    $names,
                    $this->extract_block_names_recursive( $block['innerBlocks'] )
                );
            }
        }

        return array_unique( $names );
    }

    /**
     * Get stylesheet URLs for an array of block names.
     *
     * Handles both block themes (separate block assets) and classic themes
     * (combined wp-block-library stylesheet).
     *
     * @param array $block_names Array of block names to get styles for.
     * @return array Array of stylesheet URLs.
     */
    private function get_style_urls_for_blocks( array $block_names ): array
    {
        $style_urls = array();
        $registry = \WP_Block_Type_Registry::get_instance();
        $wp_styles = wp_styles();

        // Check if WordPress is loading separate block assets (block themes)
        // or combined assets (classic themes)
        $separate_assets = wp_should_load_separate_core_block_assets();

        foreach ( $block_names as $block_name ) {
            $block_type = $registry->get_registered( $block_name );

            if ( ! $block_type ) {
                continue;
            }

            // Get style handles for this block
            $style_handles = $this->get_block_style_handles( $block_type );

            foreach ( $style_handles as $handle ) {
                $url = $this->get_style_url_from_handle( $handle, $wp_styles );
                if ( $url ) {
                    $style_urls[] = $url;
                }
            }
        }

        // For classic themes without separate block assets,
        // include the combined block library if we have core blocks
        if ( ! $separate_assets && $this->has_core_blocks( $block_names ) ) {
            $block_library_url = $this->get_style_url_from_handle( 'wp-block-library', $wp_styles );
            if ( $block_library_url && ! in_array( $block_library_url, $style_urls, true ) ) {
                $style_urls[] = $block_library_url;
            }
        }

        return $style_urls;
    }

    /**
     * Get style handles for a block type.
     *
     * Uses the style_handles property available in WordPress 6.1+.
     * Falls back to style property for older blocks.
     *
     * @param \WP_Block_Type $block_type The block type object.
     * @return array Array of style handles.
     */
    private function get_block_style_handles( \WP_Block_Type $block_type ): array
    {
        $handles = array();

        // WordPress 6.1+ uses style_handles array
        if ( ! empty( $block_type->style_handles ) && is_array( $block_type->style_handles ) ) {
            $handles = $block_type->style_handles;
        } elseif ( ! empty( $block_type->style ) ) {
            // Fallback for older style property (can be string or array)
            $handles = (array) $block_type->style;
        }

        return $handles;
    }

    /**
     * Get the URL for a style handle from wp_styles().
     *
     * @param string     $handle    The style handle to look up.
     * @param \WP_Styles $wp_styles The WordPress styles object.
     * @return string|null The stylesheet URL or null if not found.
     */
    private function get_style_url_from_handle( string $handle, \WP_Styles $wp_styles ): ?string
    {
        if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
            return null;
        }

        $style = $wp_styles->registered[ $handle ];
        $src = $style->src;

        if ( empty( $src ) ) {
            return null;
        }

        // Handle relative URLs
        if ( strpos( $src, '//' ) === false ) {
            $src = site_url( $src );
        }

        // Add version query string if available
        if ( ! empty( $style->ver ) ) {
            $src = add_query_arg( 'ver', $style->ver, $src );
        }

        return $src;
    }

    /**
     * Check if block names array contains any core blocks.
     *
     * @param array $block_names Array of block names.
     * @return bool True if core blocks are present.
     */
    private function has_core_blocks( array $block_names ): bool
    {
        foreach ( $block_names as $name ) {
            if ( strpos( $name, 'core/' ) === 0 ) {
                return true;
            }
        }
        return false;
    }
}
