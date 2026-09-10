<?php
/**
 * Regression test for GroupModalTriggerSupport's open-mode wrapper decoration.
 *
 * Runs against the REAL WP_HTML_Tag_Processor, loaded directly from a
 * WordPress core checkout, rather than Brain\Monkey — same rationale as
 * BlockSupportRenderTest.php, which this file mirrors: the bugs pinned here
 * are tag-traversal / attribute-presence mistakes that only the real class
 * independently validates.
 *
 * Pins the whole-branch-review findings that the Group's inline-content and
 * direct-URL open modes (handle_inline_content(), handle_direct_url()) make
 * the wrapper <div> itself the trigger (role="button" tabindex="0") but
 * left it with no `id` — so modal-store.js's focus-restore via
 * document.getElementById(state.activeTriggerId) never finds it — and, for
 * inline mode specifically, no keydown handler — so Enter/Space did nothing
 * despite the element being focusable (WCAG 2.1.1).
 *
 * Deliberately NOT extending Pikari\Tests\TestCase / using Brain\Monkey —
 * see BlockSupportRenderTest.php's docblock for the full explanation. Runs
 * in an isolated child process for the same reason.
 *
 * Skips itself when no WordPress core checkout is discoverable (WP_CORE_DIR
 * env var, or wp-env's local cache), so `composer test` still passes with
 * no WordPress available.
 *
 * @package Pikari\Tests\GutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use PHPUnit\Framework\TestCase;
use Pikari\GutenbergModals\GroupModalTriggerSupport;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 *
 * See BlockSupportRenderTest.php's class docblock — preserveGlobalState must
 * be disabled alongside separate-process isolation for the same reason.
 */
class GroupModalTriggerSupportRenderTest extends TestCase
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

        // Shared with BlockSupportRenderTest.php — see that stub file's own
        // docblock for why it must be in the global namespace.
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
     * Pins the Important-2 whole-branch-review finding: the inline-content
     * open mode's wrapper is the trigger itself (role="button" tabindex="0")
     * but shipped with only a click handler — no id (breaking focus
     * restore) and no keydown handler (breaking keyboard activation).
     */
    public function test_group_inline_mode_wrapper_has_id_and_keydown(): void
    {
        $instance = new GroupModalTriggerSupport();

        $input = '<div class="wp-block-group"><p>Card content</p></div>';
        $block = [
            'attrs' => [
                'pikariModalAction'        => 'open',
                'pikariModalContentSource' => 'inline',
                'pikariModalInlineAnchor'  => 'promo',
            ],
        ];

        $result = $instance->filter_group_block( $input, $block );

        $processor = new \WP_HTML_Tag_Processor( $result );
        $this->assertTrue( $processor->next_tag() );
        $this->assertSame( 'DIV', $processor->get_tag() );

        // Focus restore: modal-store.js reads document.activeElement.id on
        // open and document.getElementById() on close.
        $this->assertStringStartsWith( 'modal-trigger-', (string) $processor->get_attribute( 'id' ) );

        // Keyboard activation: role="button" must respond to Enter/Space.
        $this->assertSame( 'actions.handleTriggerKeydown', $processor->get_attribute( 'data-wp-on--keydown' ) );
        $this->assertSame( 'actions.handleGroupTriggerClick', $processor->get_attribute( 'data-wp-on--click' ) );
        $this->assertSame( 'button', $processor->get_attribute( 'role' ) );
        $this->assertSame( '0', $processor->get_attribute( 'tabindex' ) );
    }

    /**
     * Pins the Important-1 whole-branch-review finding: the direct-URL open
     * mode's wrapper is the trigger itself (role="button" tabindex="0") but
     * had no id, so modal-store.js's focus-restore-on-close could never
     * find it.
     */
    public function test_group_url_mode_wrapper_has_id(): void
    {
        $instance = new GroupModalTriggerSupport();

        $input = '<div class="wp-block-group"><p>Card content</p></div>';
        $block = [
            'attrs' => [
                'pikariModalAction'        => 'open',
                'pikariModalContentSource' => 'url',
                'pikariModalDirectUrl'     => 'https://example.com',
            ],
        ];

        $result = $instance->filter_group_block( $input, $block );

        $processor = new \WP_HTML_Tag_Processor( $result );
        $this->assertTrue( $processor->next_tag() );
        $this->assertSame( 'DIV', $processor->get_tag() );

        // Focus restore: modal-store.js reads document.activeElement.id on
        // open and document.getElementById() on close.
        $this->assertStringStartsWith( 'modal-trigger-', (string) $processor->get_attribute( 'id' ) );

        // This mode already had the keydown handler before the fix; assert
        // it alongside the id so a future refactor can't silently drop it.
        $this->assertSame( 'actions.handleTriggerKeydown', $processor->get_attribute( 'data-wp-on--keydown' ) );
    }

    /**
     * Pins the fix-round-4 regression the id addition itself introduced:
     * both open-mode paths call set_attribute( 'id', ... ) unconditionally
     * on the group wrapper — the same element core's `anchor` block support
     * writes an author-set HTML anchor to. Without a guard, every render
     * silently overwrites that anchor with a generated modal-trigger- id,
     * breaking in-page #anchor links to the group and (ironically) giving
     * modal-store.js's getElementById() a moving target instead of a stable
     * one. An existing id is exactly as good for focus restore as a
     * generated one, so it must be preserved rather than replaced.
     */
    public function test_group_inline_mode_preserves_existing_wrapper_id(): void
    {
        $instance = new GroupModalTriggerSupport();

        $input = '<div class="wp-block-group" id="my-custom-anchor"><p>Card content</p></div>';
        $block = [
            'attrs' => [
                'pikariModalAction'        => 'open',
                'pikariModalContentSource' => 'inline',
                'pikariModalInlineAnchor'  => 'promo',
            ],
        ];

        $result = $instance->filter_group_block( $input, $block );

        $processor = new \WP_HTML_Tag_Processor( $result );
        $this->assertTrue( $processor->next_tag() );
        $this->assertSame( 'my-custom-anchor', $processor->get_attribute( 'id' ) );
    }

    /**
     * Sibling of the above for the direct-URL open mode's wrapper.
     */
    public function test_group_url_mode_preserves_existing_wrapper_id(): void
    {
        $instance = new GroupModalTriggerSupport();

        $input = '<div class="wp-block-group" id="my-custom-anchor"><p>Card content</p></div>';
        $block = [
            'attrs' => [
                'pikariModalAction'        => 'open',
                'pikariModalContentSource' => 'url',
                'pikariModalDirectUrl'     => 'https://example.com',
            ],
        ];

        $result = $instance->filter_group_block( $input, $block );

        $processor = new \WP_HTML_Tag_Processor( $result );
        $this->assertTrue( $processor->next_tag() );
        $this->assertSame( 'my-custom-anchor', $processor->get_attribute( 'id' ) );
    }

    /**
     * The derived name has to survive the trip into the serialized context —
     * the whole point of the label is that it reaches the dialog element.
     */
    public function test_group_url_mode_carries_the_host_as_the_dialog_label(): void
    {
        $instance = new GroupModalTriggerSupport();

        $input = '<div class="wp-block-group"><p>Card content</p></div>';
        $block = [
            'attrs' => [
                'pikariModalAction'        => 'open',
                'pikariModalContentSource' => 'url',
                'pikariModalDirectUrl'     => 'https://example.com/page',
            ],
        ];

        $result = $instance->filter_group_block( $input, $block );

        $processor = new \WP_HTML_Tag_Processor( $result );
        $this->assertTrue( $processor->next_tag() );

        $context = json_decode( (string) $processor->get_attribute( 'data-wp-context' ), true );
        $this->assertSame( 'example.com', $context['label'] ?? null );
    }
}
