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
        $content = $this->get_default_content();
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
     * Falls back to empty string for classic themes without template part support.
     *
     * @param string $slug Template part slug (default: 'modal').
     * @return string Rendered template part HTML, or empty string.
     */
    public static function render( string $slug = self::SLUG ): string
    {
        if ( ! self::is_supported() ) {
            return '';
        }

        ob_start();
        block_template_part( $slug );
        return ob_get_clean();
    }

    /**
     * Load the default template part content from parts/modal.html.
     *
     * @return string Block markup.
     */
    private function get_default_content(): string
    {
        $path = PIKARI_GUTENBERG_MODALS_DIR . 'parts/modal.html';

        if ( ! file_exists( $path ) ) {
            return '';
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local plugin file.
        return (string) file_get_contents( $path );
    }
}
