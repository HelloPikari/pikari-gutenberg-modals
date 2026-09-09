<?php
/**
 * Regression test for filter_button_block()'s decoration target.
 *
 * Runs against the REAL WP_HTML_Tag_Processor, loaded directly from a
 * WordPress core checkout, rather than Brain\Monkey. The bug this pins —
 * decorating core/button's wrapper <div> instead of the inner <a>/<button>
 * it wraps — is a tag-traversal-order mistake. A hand-rolled fake of
 * WP_HTML_Tag_Processor would only model next_tag() the way its author
 * understands it, which is exactly the understanding that produced the bug
 * in the first place; only the real class independently validates the call
 * sequence.
 *
 * Deliberately NOT extending Pikari\Tests\TestCase / using Brain\Monkey:
 * Brain\Monkey defines its own stand-in implementations of add_filter(),
 * apply_filters(), __(), etc. as real global functions (guarded by
 * function_exists(), see vendor/brain/monkey/inc/wp-hook-functions.php),
 * lazily on the first Monkey\setUp() call in the process. Once any other
 * test in the same PHPUnit run has done that, those definitions are
 * permanent for the rest of the process — so the class below runs in an
 * isolated child process, with its own annotations (see there — this
 * file-level docblock is not itself read for annotations, since a
 * namespace/use statement sits between it and the class).
 *
 * Skips itself when no WordPress core checkout is discoverable (WP_CORE_DIR
 * env var, or wp-env's local cache), so `composer test` still passes with
 * no WordPress available — e.g. CI that hasn't set one up.
 *
 * @package Pikari\Tests\GutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use PHPUnit\Framework\TestCase;
use Pikari\GutenbergModals\BlockSupport;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * preserveGlobalState must be disabled alongside separate-process isolation:
 * PHPUnit's default is to replay the parent process's get_included_files()
 * list into the child, which re-includes wp-hook-functions.php there too if
 * an earlier Brain\Monkey test already loaded it in the parent — silently
 * defeating the isolation and causing "Cannot redeclare apply_filters()".
 * Verified empirically (both the collision without this, and a clean run
 * with it) before relying on it — see the fix-round-2 report.
 */
class BlockSupportRenderTest extends TestCase
{
    protected function setUp(): void
    {
        if ( ! defined( 'PIKARI_GUTENBERG_MODALS_DIR' ) ) {
            define( 'PIKARI_GUTENBERG_MODALS_DIR', dirname( __DIR__, 2 ) . '/' );
        }

        if ( ! $this->load_wp_html_api() ) {
            $this->markTestSkipped(
                'WP_HTML_Tag_Processor unavailable — set WP_CORE_DIR to a WordPress ' .
                'checkout (or run where wp-env has one cached in ~/.wp-env/*/WordPress).'
            );
        }
    }

    /**
     * Locate and load the real WP_HTML_Tag_Processor and its dependencies.
     *
     * @return bool Whether the class is available.
     */
    private function load_wp_html_api(): bool
    {
        if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
            return true;
        }

        $core_dir = $this->find_wp_core_dir();

        if ( null === $core_dir ) {
            return false;
        }

        // In the global namespace, not declared inline here — see
        // wp-html-api-stubs.php's own docblock for why.
        require_once __DIR__ . '/wp-html-api-stubs.php';

        // Load order taken from this exact core checkout's wp-settings.php
        // (grep -n html-api), plus utf8.php — set_attribute() needs
        // wp_has_noncharacters() from it and it is not in that require list.
        foreach (
            [
                '/wp-includes/class-wp-token-map.php',
                '/wp-includes/html-api/html5-named-character-references.php',
                '/wp-includes/html-api/class-wp-html-attribute-token.php',
                '/wp-includes/html-api/class-wp-html-span.php',
                '/wp-includes/html-api/class-wp-html-doctype-info.php',
                '/wp-includes/html-api/class-wp-html-text-replacement.php',
                '/wp-includes/html-api/class-wp-html-decoder.php',
                '/wp-includes/utf8.php',
                '/wp-includes/html-api/class-wp-html-tag-processor.php',
            ] as $relative_path
        ) {
            require_once $core_dir . $relative_path;
        }

        return class_exists( 'WP_HTML_Tag_Processor' );
    }

    /**
     * Find a WordPress core checkout that has the HTML API classes.
     *
     * Checks WP_CORE_DIR first — the standard WP test-suite convention, and
     * what CI would set — then falls back to scanning wp-env's local cache,
     * since that is what a dev machine running this plugin's wp-env
     * actually has, with no extra setup needed.
     *
     * @return string|null Core directory path, or null if none found.
     */
    private function find_wp_core_dir(): ?string
    {
        $candidates = [];

        $env_dir = getenv( 'WP_CORE_DIR' );
        if ( $env_dir ) {
            $candidates[] = rtrim( $env_dir, '/' );
        }

        $home = getenv( 'HOME' );
        if ( $home ) {
            $matches    = glob( $home . '/.wp-env/*/WordPress', GLOB_ONLYDIR );
            $candidates = array_merge( $candidates, $matches ?: [] );
        }

        foreach ( $candidates as $candidate ) {
            if ( file_exists( $candidate . '/wp-includes/html-api/class-wp-html-tag-processor.php' ) ) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Pins the fix-round-1 regression: decorating core/button's own root
     * <div> instead of the inner <a> it wraps. Also covers the underlying
     * I3 fix — an href-less <a> gets a synthetic href from the resolved
     * URL, which the Modal Button variation depends on since it ships no
     * url of its own.
     */
    public function test_filter_button_block_decorates_inner_anchor_not_wrapper(): void
    {
        $instance = new BlockSupport();

        // No href: the Modal Button variation's default shape.
        $input = '<div class="wp-block-button"><a class="wp-block-button__link">Text</a></div>';
        $block = [
            'attrs' => [
                'pikariModalAction'        => 'open',
                'pikariModalContentSource' => 'url',
                'pikariModalDirectUrl'     => 'https://example.com',
            ],
        ];

        $result = $instance->filter_button_block( $input, $block );

        $processor = new \WP_HTML_Tag_Processor( $result );

        // The wrapper <div> must be left completely undecorated.
        $this->assertTrue( $processor->next_tag() );
        $this->assertSame( 'DIV', $processor->get_tag() );
        $this->assertNull( $processor->get_attribute( 'id' ) );
        $this->assertNull( $processor->get_attribute( 'data-wp-interactive' ) );
        $this->assertFalse( $processor->has_class( 'has-pikari-modal' ) );

        // The inner <a> gets the trigger's decoration, including a
        // synthetic href since core/button ships none of its own here.
        $this->assertTrue( $processor->next_tag() );
        $this->assertSame( 'A', $processor->get_tag() );
        $this->assertStringStartsWith( 'modal-trigger-', (string) $processor->get_attribute( 'id' ) );
        $this->assertSame( 'https://example.com', $processor->get_attribute( 'href' ) );
        $this->assertSame( 'pikari-modal', $processor->get_attribute( 'data-wp-interactive' ) );
        $this->assertTrue( $processor->has_class( 'has-pikari-modal' ) );

        // Nothing else in the markup gets decorated.
        $this->assertFalse( $processor->next_tag() );
    }
}
