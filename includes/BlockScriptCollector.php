<?php
/**
 * Block Script Collector
 *
 * Collects the classic scripts enqueued while modal content renders, so the
 * client can run them itself.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

class BlockScriptCollector
{
    /**
     * Collect scripts enqueued during block rendering.
     *
     * Content inserted with innerHTML never runs its scripts, and a script a
     * plugin enqueues while rendering (WPForms, on wp_footer) is otherwise never
     * sent to the browser at all. Each entry carries what WP_Scripts would have
     * printed for the handle, ordered so every dependency comes first —
     * including dependencies the page may already have, which the client skips.
     *
     * @param array $before_queue The wp_scripts()->queue snapshot taken before rendering.
     * @return array<int, array{handle: string, src: ?string, data: string, before: string, after: string}>
     */
    public function collect_render_enqueued_scripts( array $before_queue ): array
    {
        $wp_scripts  = wp_scripts();
        $new_handles = array_diff( $wp_scripts->queue, $before_queue );

        $ordered = array();
        foreach ( $new_handles as $handle ) {
            $this->add_with_dependencies( $handle, $wp_scripts, $ordered );
        }

        $scripts = array();
        foreach ( array_keys( $ordered ) as $handle ) {
            $script = $this->describe_script( $handle, $wp_scripts->registered[ $handle ] );

            // An alias such as `jquery` has nothing of its own to run; its
            // dependencies are already in the list ahead of it.
            if ( null === $script['src'] && '' === $script['data'] . $script['before'] . $script['after'] ) {
                continue;
            }

            $scripts[] = $script;
        }

        return $scripts;
    }

    /**
     * Append a handle to the ordered list after its dependencies.
     *
     * @param string $handle     The script handle.
     * @param object $wp_scripts The WordPress scripts object.
     * @param array  $ordered    Handle => true, in load order. Modified in place.
     */
    private function add_with_dependencies( string $handle, object $wp_scripts, array &$ordered ): void
    {
        if ( isset( $ordered[ $handle ] ) || ! isset( $wp_scripts->registered[ $handle ] ) ) {
            return;
        }

        // Marked before recursing, so a dependency cycle terminates.
        $ordered[ $handle ] = true;

        foreach ( (array) $wp_scripts->registered[ $handle ]->deps as $dependency ) {
            $this->add_with_dependencies( $dependency, $wp_scripts, $ordered );
        }

        // Moved to the end, behind everything it depends on.
        unset( $ordered[ $handle ] );
        $ordered[ $handle ] = true;
    }

    /**
     * Describe a registered script for the client.
     *
     * @param string $handle The script handle.
     * @param object $dep    The registered _WP_Dependency.
     * @return array{handle: string, src: ?string, data: string, before: string, after: string}
     */
    private function describe_script( string $handle, object $dep ): array
    {
        return array(
            'handle' => $handle,
            'src'    => $this->get_script_url( $dep ),
            'data'   => (string) ( $dep->extra['data'] ?? '' ),
            'before' => $this->join_inline( $dep->extra['before'] ?? array() ),
            'after'  => $this->join_inline( $dep->extra['after'] ?? array() ),
        );
    }

    /**
     * Join wp_add_inline_script() chunks, which core seeds with a leading false.
     *
     * @param mixed $inline The stored inline scripts.
     * @return string The joined script.
     */
    private function join_inline( $inline ): string
    {
        $chunks = array_filter(
            (array) $inline,
            function ( $chunk ) {
                return is_string( $chunk ) && '' !== $chunk;
            }
        );

        return implode( "\n", $chunks );
    }

    /**
     * Get the absolute, versioned URL for a registered script.
     *
     * @param object $dep The registered _WP_Dependency.
     * @return string|null The script URL, or null for an inline-only handle.
     */
    private function get_script_url( object $dep ): ?string
    {
        $src = $dep->src;

        if ( empty( $src ) ) {
            return null;
        }

        if ( strpos( $src, '//' ) === false ) {
            $src = site_url( $src );
        }

        if ( ! empty( $dep->ver ) ) {
            $src = add_query_arg( 'ver', $dep->ver, $src );
        }

        return $src;
    }
}
