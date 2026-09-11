<?php
/**
 * Modal Starter Patterns
 *
 * Registers block patterns scoped to the `modal` template part area. These
 * seed a new modal template part when the user creates one from a trigger's
 * Modal template panel.
 *
 * Core's own template-part area pattern selector only handles the `header`
 * and `footer` areas, so these never appear in it. That is expected: the
 * plugin renders its own picker and filters `getBlockPatterns()` on the
 * `core/template-part/modal` block type.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class ModalPatterns
{
    /**
     * Pattern slugs, in the order they appear in the picker.
     *
     * Titles live in register_patterns() rather than here: a PHP constant
     * cannot hold a __() call, and the WordPress i18n sniff rejects passing
     * a variable to a translation function.
     *
     * @var string[]
     */
    public const PATTERN_SLUGS = [
        'modal-centered',
        'modal-panel-right',
        'modal-panel-left',
    ];

    /**
     * Block type used to scope patterns to the modal template part area.
     *
     * @var string
     */
    public const BLOCK_TYPE = 'core/template-part/modal';

    /**
     * Constructor.
     *
     * Hooked at priority 11, not the default 10: the plugin's own bootstrap
     * (`pikari_gutenberg_modals_init()`) instantiates this class from inside
     * an `init` callback running at priority 10. Registering back onto the
     * same priority bucket mid-iteration means WP_Hook's `foreach` over that
     * bucket has already snapshotted past this point and never calls it; a
     * later priority is picked up by `WP_Hook::resort_active_iterations()`
     * and still runs during the same `init` pass.
     */
    public function __construct()
    {
        add_action('init', [ $this, 'register_patterns' ], 11);
    }

    /**
     * Register the modal starter patterns.
     */
    public function register_patterns(): void
    {
        $titles = [
            'modal-centered'    => __( 'Centered dialog', 'pikari-gutenberg-modals' ),
            'modal-panel-right' => __( 'Right panel', 'pikari-gutenberg-modals' ),
            'modal-panel-left'  => __( 'Left panel', 'pikari-gutenberg-modals' ),
        ];

        foreach ( self::PATTERN_SLUGS as $slug ) {
            $content = $this->load_pattern( $slug );

            if ( '' === $content ) {
                continue;
            }

            register_block_pattern(
                'pikari-gutenberg-modals/' . $slug,
                [
                    'title'      => $titles[ $slug ],
                    'categories' => [ 'call-to-action' ],
                    'blockTypes' => [ self::BLOCK_TYPE ],
                    'content'    => $content,
                ]
            );
        }
    }

    /**
     * Load a pattern file and return its rendered markup.
     *
     * @param string $slug Pattern slug.
     * @return string Pattern markup, or an empty string when the file is missing.
     */
    private function load_pattern( string $slug ): string
    {
        $file = PIKARI_GUTENBERG_MODALS_DIR . 'patterns/' . $slug . '.php';

        if ( ! file_exists( $file ) ) {
            return '';
        }

        ob_start();
        include $file;

        return trim( (string) ob_get_clean() );
    }
}
