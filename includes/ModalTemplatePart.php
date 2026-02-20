<?php
/**
 * Modal Template Part
 *
 * Registers a custom template part area for the modal dialog and provides
 * a default template that users can customize in the Site Editor.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class ModalTemplatePart
{
    /**
     * Template part slug.
     *
     * @var string
     */
    private const SLUG = 'modal';

    /**
     * Template part area name.
     *
     * @var string
     */
    private const AREA = 'modal';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_filter('default_wp_template_part_areas', [ $this, 'register_area' ]);
        add_filter('get_block_templates', [ $this, 'provide_default_template' ], 10, 3);
        add_filter('get_block_file_template', [ $this, 'provide_individual_template' ], 10, 3);
    }

    /**
     * Register the modal template part area.
     *
     * @param array $areas Existing template part areas.
     * @return array Modified areas.
     */
    public function register_area( array $areas ): array
    {
        // Only register the modal area for block themes with full Site Editor support.
        // Hybrid themes render modals from file-based templates and don't use the area system.
        if ( ! wp_is_block_theme() ) {
            return $areas;
        }

        $areas[] = [
            'area'        => self::AREA,
            'area_tag'    => 'div',
            'label'       => __( 'Modal', 'pikari-gutenberg-modals' ),
            'description' => __( 'The modal dialog container. Customize the close button and content area layout.', 'pikari-gutenberg-modals' ),
            'icon'        => 'welcome-view-site',
        ];

        return $areas;
    }

    /**
     * Provide the default modal template part when none exists.
     *
     * @param \WP_Block_Template[] $query_result Array of found templates.
     * @param array                $query        Query arguments.
     * @param string               $template_type Template type (wp_template or wp_template_part).
     * @return \WP_Block_Template[] Modified query results.
     */
    public function provide_default_template( array $query_result, array $query, string $template_type ): array
    {
        // Only provide synthetic templates for block themes.
        // Hybrid themes use file-based rendering and should not see phantom template parts.
        if ( ! wp_is_block_theme() ) {
            return $query_result;
        }

        if ( 'wp_template_part' !== $template_type ) {
            return $query_result;
        }

        // If filtering by slug, only proceed if our slug is included.
        if ( ! empty( $query['slug__in'] ) && ! in_array( self::SLUG, $query['slug__in'], true ) ) {
            return $query_result;
        }

        // If filtering by area, only proceed if our area is included.
        if ( ! empty( $query['area'] ) && self::AREA !== $query['area'] ) {
            return $query_result;
        }

        // Don't override if a user customization or theme override already exists.
        foreach ( $query_result as $template ) {
            if ( self::SLUG === $template->slug ) {
                return $query_result;
            }
        }

        $template = $this->build_template_object();
        if ( null === $template ) {
            return $query_result;
        }

        $query_result[] = $template;

        return $query_result;
    }

    /**
     * Provide the modal template part for individual lookups.
     *
     * This is called by get_block_template() (singular) when WordPress
     * needs to find a specific template by ID — e.g. during REST API saves.
     *
     * @param \WP_Block_Template|null $block_template The found template, or null.
     * @param string                  $id             Template ID (e.g. "theme//modal").
     * @param string                  $template_type  Template type.
     * @return \WP_Block_Template|null The template, or null if not ours.
     */
    public function provide_individual_template( ?\WP_Block_Template $block_template, string $id, string $template_type ): ?\WP_Block_Template
    {
        // Only provide synthetic templates for block themes.
        if ( ! wp_is_block_theme() ) {
            return $block_template;
        }

        if ( 'wp_template_part' !== $template_type ) {
            return $block_template;
        }

        // Already found (database or theme override).
        if ( null !== $block_template ) {
            return $block_template;
        }

        // Check if this is our template by slug.
        $parts = explode( '//', $id, 2 );
        if ( count( $parts ) < 2 || self::SLUG !== $parts[1] ) {
            return $block_template;
        }

        return $this->build_template_object();
    }

    /**
     * Build the WP_Block_Template object for the default modal template part.
     *
     * @return \WP_Block_Template|null Template object, or null if content unavailable.
     */
    private function build_template_object(): ?\WP_Block_Template
    {
        $content = self::get_default_content();
        if ( empty( $content ) ) {
            return null;
        }

        $template                 = new \WP_Block_Template();
        $template->id             = get_stylesheet() . '//' . self::SLUG;
        $template->slug           = self::SLUG;
        $template->theme          = get_stylesheet();
        $template->type           = 'wp_template_part';
        $template->source         = 'plugin';
        $template->origin         = 'plugin';
        $template->title          = __( 'Modal', 'pikari-gutenberg-modals' );
        $template->description    = __( 'Default modal dialog template part. Customize the close button, content area, and layout.', 'pikari-gutenberg-modals' );
        $template->status         = 'publish';
        $template->has_theme_file = false;
        $template->is_custom      = false;
        $template->area           = self::AREA;
        $template->content        = $content;

        return $template;
    }

    /**
     * Check whether the current theme supports block template parts.
     *
     * @return bool
     */
    public static function is_supported(): bool
    {
        return wp_is_block_theme() || current_theme_supports( 'block-template-parts' );
    }

    /**
     * Render a modal template part by slug.
     *
     * Two rendering paths based on theme type:
     * - Block themes: block_template_part() with full Site Editor support.
     * - Non-block themes (hybrid/classic): file-based fallback via do_blocks().
     *
     * @param string $slug Template part slug (default: 'modal').
     * @return string Rendered template part HTML, or empty string.
     */
    public static function render( string $slug = self::SLUG ): string
    {
        // Block themes: trust block_template_part() fully, including
        // intentionally blank user-customized template parts.
        if ( wp_is_block_theme() ) {
            ob_start();
            block_template_part( $slug );
            return ob_get_clean();
        }

        // Non-block themes: resolve content from theme files or plugin default.
        // We skip block_template_part() because the plugin does not inject
        // synthetic templates for non-block themes (see filter guards above).
        $content = self::get_fallback_content( $slug );
        if ( empty( $content ) ) {
            return '';
        }

        return do_blocks( $content );
    }

    /**
     * Get fallback template content for non-block themes.
     *
     * Resolution order:
     * 1. `pikari_gutenberg_modals_fallback_template` filter
     * 2. Theme file: child theme parts/modal-{slug}.html → parts/modal.html → parent theme (same)
     * 3. Plugin default: parts/modal.html
     *
     * @param string $slug Template part slug.
     * @return string Block markup, or empty string.
     */
    public static function get_fallback_content( string $slug = self::SLUG ): string
    {
        /**
         * Filters the fallback template content for non-block themes.
         *
         * Return non-empty string to override the default resolution.
         *
         * @since 1.0.0
         *
         * @param string $content Empty string (return markup to override).
         * @param string $slug    Template part slug.
         */
        $filtered = apply_filters( 'pikari_gutenberg_modals_fallback_template', '', $slug );
        if ( ! empty( $filtered ) ) {
            return $filtered;
        }

        // Search theme directories for a matching template file.
        $theme_content = self::get_theme_template_content( $slug );
        if ( ! empty( $theme_content ) ) {
            return $theme_content;
        }

        // Fall back to plugin default.
        return self::get_default_content();
    }

    /**
     * Search theme directories for a modal template file.
     *
     * Checks child theme first, then parent theme. Looks for
     * parts/modal-{slug}.html first, then parts/modal.html for the default slug.
     *
     * @param string $slug Template part slug.
     * @return string Block markup from theme file, or empty string.
     */
    private static function get_theme_template_content( string $slug ): string
    {
        $filenames = [];

        // Slug-specific file first (e.g., parts/modal-compact.html).
        if ( self::SLUG !== $slug ) {
            $filenames[] = 'parts/modal-' . $slug . '.html';
        }

        // Default file (parts/modal.html).
        $filenames[] = 'parts/modal.html';

        foreach ( $filenames as $filename ) {
            // locate_template checks child theme first, then parent theme.
            $path = locate_template( $filename );
            if ( ! empty( $path ) ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local theme file.
                $content = (string) file_get_contents( $path );
                if ( ! empty( $content ) ) {
                    return $content;
                }
            }
        }

        return '';
    }

    /**
     * Load the default template part content from parts/modal.html.
     *
     * @return string Block markup.
     */
    public static function get_default_content(): string
    {
        $path = PIKARI_GUTENBERG_MODALS_DIR . 'parts/modal.html';

        if ( ! file_exists( $path ) ) {
            return '';
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.
        return (string) file_get_contents( $path );
    }
}
