<?php
/**
 * Editor Integration
 *
 * IMPORTANT: WordPress 5.8+ uses an iframe-based editor for better isolation.
 * Styles must use !important declarations and include .editor-styles-wrapper
 * selectors to ensure they apply within the iframe context.
 *
 * @see https://make.wordpress.org/core/2021/06/29/blocks-in-an-iframed-template-editor/
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class EditorIntegration
{
    /**
     * Block support instance
     *
     * @var BlockSupport
     */
    private BlockSupport $block_support;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Hook into editor asset loading for scripts
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_scripts']);

        // Hook into block assets for styles (works with iframe editor)
        add_action('enqueue_block_assets', [$this, 'enqueue_block_styles']);

        // Restrict modal template blocks to template part editors
        add_filter('allowed_block_types_all', [$this, 'restrict_modal_template_blocks'], 10, 2);
    }

    /**
     * Enqueue editor scripts
     */
    public function enqueue_editor_scripts(): void
    {
        $editor_asset_file = PIKARI_GUTENBERG_MODALS_DIR . 'build/editor/index.asset.php';

        // Check if build exists
        if ( ! file_exists($editor_asset_file) ) {
            error_log('Pikari Gutenberg Modals: Editor asset file not found at ' . $editor_asset_file);
            return;
        }

        $editor_assets = include $editor_asset_file;

        // Enqueue editor script
        wp_enqueue_script(
            'pikari-gutenberg-modals-editor',
            PIKARI_GUTENBERG_MODALS_URL . 'build/editor/index.js',
            $editor_assets['dependencies'],
            $editor_assets['version'],
            true
        );

        // Get block support instance
        if ( ! isset($this->block_support) ) {
            $this->block_support = new BlockSupport();
        }

        // Localize script with data
        wp_localize_script(
            'pikari-gutenberg-modals-editor',
            'pikariGutenbergModals',
            [
                'supportedBlocks'    => $this->block_support->get_supported_blocks_for_js(),
                'restUrl'            => rest_url('pikari-gutenberg-modals/v1/'),
                'nonce'              => wp_create_nonce('wp_rest'),
                'modalSizes'         => $this->get_modal_sizes(),
                'modalTemplateParts' => $this->get_modal_template_parts(),
                'defaultSettings'    => [
                    'size' => 'medium',
                    'animation' => 'fade',
                    'closeOnClickOutside' => true,
                    'showCloseButton' => true,
                    'overlayOpacity' => 0.8,
                ],
            ]
        );
    }

    /**
     * Get available modal sizes for the editor.
     *
     * Returns an array of size options for the modal size selector.
     * Developers can add custom sizes via the
     * `pikari_gutenberg_modals_modal_sizes` filter.
     *
     * Each size entry should have:
     * - `label` (string) Translated display label.
     * - `value` (string) Size slug used as the `data-size` attribute value.
     *                     Empty string means default (uses `--modal-max-width`).
     *
     * Custom sizes require matching CSS, e.g.:
     * ```css
     * .modal-overlay[data-size="custom-slug"] .modal-content {
     *     max-width: 768px;
     * }
     * ```
     *
     * @return array<int, array{label: string, value: string}> Modal size options.
     */
    private function get_modal_sizes(): array
    {
        $default_sizes = [
            [
                'label' => __('Default', 'pikari-gutenberg-modals'),
                'value' => '',
            ],
            [
                'label' => __('Small', 'pikari-gutenberg-modals'),
                'value' => 'small',
            ],
            [
                'label' => __('Large', 'pikari-gutenberg-modals'),
                'value' => 'large',
            ],
            [
                'label' => __('Fullscreen', 'pikari-gutenberg-modals'),
                'value' => 'fullscreen',
            ],
        ];

        /**
         * Filters the available modal size options.
         *
         * @since 0.2.0
         *
         * @param array $sizes Array of size options with 'label' and 'value' keys.
         */
        return apply_filters('pikari_gutenberg_modals_modal_sizes', $default_sizes);
    }

    /**
     * Get available modal template parts for the editor.
     *
     * Block themes use the native get_block_templates() query.
     * Hybrid themes scan the theme's parts/ directory for modal*.html files.
     * Both paths ensure the default 'modal' slug is always present.
     *
     * @return array<int, array{slug: string, title: string}> Template part options.
     */
    private function get_modal_template_parts(): array
    {
        if ( ModalTemplatePart::is_supported() ) {
            $parts = $this->get_block_theme_template_parts();
        } else {
            $parts = $this->scan_theme_modal_templates();
        }

        // Ensure the default 'modal' slug is always present.
        $has_default = false;
        foreach ( $parts as $part ) {
            if ( 'modal' === $part['slug'] ) {
                $has_default = true;
                break;
            }
        }

        if ( ! $has_default ) {
            array_unshift(
                $parts,
                [
                    'slug'  => 'modal',
                    'title' => __( 'Modal', 'pikari-gutenberg-modals' ),
                ]
            );
        }

        return $parts;
    }

    /**
     * Get template parts from the block template system.
     *
     * @return array<int, array{slug: string, title: string}> Template part options.
     */
    private function get_block_theme_template_parts(): array
    {
        $templates = get_block_templates(
            [ 'area' => 'modal' ],
            'wp_template_part'
        );

        $parts = [];
        foreach ( $templates as $template ) {
            $parts[] = [
                'slug'  => $template->slug,
                'title' => $template->title ?? $template->slug,
            ];
        }

        return $parts;
    }

    /**
     * Scan theme directories for modal template files.
     *
     * Looks for parts/modal*.html files in child and parent theme directories.
     * Derives slugs from filenames:
     * - parts/modal.html → slug 'modal', title 'Modal'
     * - parts/modal-compact.html → slug 'compact', title 'Compact'
     * - parts/modal-my-template.html → slug 'my-template', title 'My Template'
     *
     * Child theme files take precedence over parent theme duplicates.
     *
     * @return array<int, array{slug: string, title: string}> Template part options.
     */
    private function scan_theme_modal_templates(): array
    {
        $parts      = [];
        $seen_slugs = [];

        // Check child theme first, then parent theme.
        $dirs = [ get_stylesheet_directory() ];
        if ( get_stylesheet_directory() !== get_template_directory() ) {
            $dirs[] = get_template_directory();
        }

        foreach ( $dirs as $dir ) {
            $pattern = $dir . '/parts/modal*.html';
            $files   = glob( $pattern );

            if ( empty( $files ) ) {
                continue;
            }

            foreach ( $files as $file ) {
                $basename = basename( $file, '.html' );

                // Derive slug: 'modal' → 'modal', 'modal-compact' → 'compact'.
                if ( 'modal' === $basename ) {
                    $slug = 'modal';
                } elseif ( str_starts_with( $basename, 'modal-' ) ) {
                    $slug = substr( $basename, 6 );
                } else {
                    continue;
                }

                // Child theme files take precedence.
                if ( in_array( $slug, $seen_slugs, true ) ) {
                    continue;
                }

                $seen_slugs[] = $slug;

                // Title: 'modal' → 'Modal', 'compact' → 'Compact', 'my-template' → 'My Template'.
                $title = ucwords( str_replace( '-', ' ', $slug ) );

                $parts[] = [
                    'slug'  => $slug,
                    'title' => $title,
                ];
            }
        }

        return $parts;
    }

    /**
     * Enqueue block styles
     *
     * Uses enqueue_block_assets hook which properly handles styles
     * for both the editor iframe and frontend contexts.
     */
    public function enqueue_block_styles(): void
    {
        // Only enqueue in editor context
        if ( ! is_admin() ) {
            return;
        }

        $style_file = PIKARI_GUTENBERG_MODALS_DIR . 'build/editor/style-index.css';

        if ( file_exists($style_file) ) {
            // Get version from asset file if available
            $version = PIKARI_GUTENBERG_MODALS_VERSION;
            $asset_file = PIKARI_GUTENBERG_MODALS_DIR . 'build/editor/index.asset.php';
            if ( file_exists($asset_file) ) {
                $assets = include $asset_file;
                $version = $assets['version'] ?? PIKARI_GUTENBERG_MODALS_VERSION;
            }

            wp_enqueue_style(
                'pikari-gutenberg-modals-editor',
                PIKARI_GUTENBERG_MODALS_URL . 'build/editor/style-index.css',
                [],
                $version
            );
        }
    }

    /**
     * Restrict modal template blocks to template part editors.
     *
     * The Content Area and Modal Dialog blocks are only meaningful inside
     * a template part (e.g., the modal template part). This filter hides
     * them from the block inserter in post and page editors.
     *
     * In the Site Editor (core/edit-site), all blocks are allowed because
     * template parts are edited within it. The allowed_block_types_all
     * filter fires once on page load, not when navigating between
     * templates and template parts within the single-page Site Editor.
     *
     * @param bool|string[]            $allowed_block_types Array of allowed block type slugs,
     *                                                      or true for all registered blocks.
     * @param \WP_Block_Editor_Context $editor_context      The editor context.
     * @return bool|string[] Filtered allowed block types.
     */
    public function restrict_modal_template_blocks( $allowed_block_types, $editor_context )
    {
        // Only restrict blocks in the post editor. The Site Editor needs
        // all blocks available because template parts are edited within it.
        if (
            ! isset( $editor_context->name ) ||
            $editor_context->name !== 'core/edit-post'
        ) {
            return $allowed_block_types;
        }

        $restricted_blocks = [
            'pikari-gutenberg-modals/content-area',
            'pikari-gutenberg-modals/modal-dialog',
        ];

        // When $allowed_block_types is true (WordPress default: all blocks allowed),
        // convert to an explicit array so we can filter out our blocks.
        if ( $allowed_block_types === true ) {
            $all_block_types     = \WP_Block_Type_Registry::get_instance()->get_all_registered();
            $allowed_block_types = array_keys( $all_block_types );
        }

        // Remove restricted blocks from post/page editors
        if ( is_array( $allowed_block_types ) ) {
            $allowed_block_types = array_values(
                array_filter(
                    $allowed_block_types,
                    static function ( $block_type ) use ( $restricted_blocks ) {
                        return ! in_array( $block_type, $restricted_blocks, true );
                    }
                )
            );
        }

        return $allowed_block_types;
    }
}
