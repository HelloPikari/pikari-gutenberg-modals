# Modal Triggers as a Block Property Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make "opens a modal" a property of a Group or Button block rather than a wrapper block, deleting the Modal Trigger block and the styling loss and layout problems it causes.

**Architecture:** Merge the two existing block extensions into one shared module that adds a unified `pikariModal*` attribute set to `core/group` and `core/button` via `blocks.registerBlockType`, renders one Modal panel via `editor.BlockEdit`, and registers two block variations for inserter discovery. Server-side rendering already decorates a block's own root element, so it gains the unified attribute names and loses the Modal Trigger branch.

**Tech Stack:** WordPress 6.8+, PHP 8.4+, `@wordpress/scripts` webpack, `@wordpress/hooks` + `@wordpress/compose` filters, Jest (jsdom), PHPUnit with Brain\Monkey, Playwright MCP for browser verification.

**Spec:** `docs/superpowers/specs/2026-09-09-modal-trigger-as-block-property-design.md`

## Global Constraints

- **Branch base:** start after `feature/modal-placement` has merged to `main`. Branch `feature/modal-trigger-as-property`.
- **Sequencing:** this plan runs BEFORE the modal-overlay-templates plan. It deliberately does **not** rename `modal-dialog` — that rename belongs to the later plan, and Task 7 here amends that plan for the reduced surface set.
- **The `a[href]` click fix is NOT in this plan.** It is a one-line change with its own test, landed separately and immediately — a dead button exists today. See "Companion fix" below.
- **PHP:** 8.4+. WordPress Coding Standards, **4 spaces indentation, not tabs**.
- **JavaScript:** WordPress ESLint config, **tab indentation, not spaces**. Prettier ignores JS.
- **i18n:** text domain is exactly `pikari-gutenberg-modals`.
- **No new npm dependencies.** `@wordpress/data`, `core-data`, `components`, `block-editor`, `compose`, `hooks`, `blocks` are webpack externals and are NOT installed. Jest can only resolve a module that imports nothing from `@wordpress/*` except `@wordpress/i18n`. Any logic needing a unit test goes in such a module.
- **`render.php` and `block.json` live in `build/`.** Run `npm run build` after editing either or wp-env serves stale assets.
- **Code blocks below have been reformatted by Prettier** (lint-staged formats `*.md` and reaches inside fences). Tabs survived; WordPress ESLint wants `{ __( 'x' ) }` where fences show `{__('x')}`. Paste as given, then `npm run lint:fix`.
- **Commit format:** `type: Brief description`. **No `Co-Authored-By` and no Claude attribution.**
- **Before any commit:** `npm run lint:all && composer test && npm test`

## Companion fix (land separately, before or during this plan)

Branch `fix/href-less-anchor-triggers`, two commits, independent of everything here.

`handleGroupTriggerClick` ignores `a:not(.is-primary-link), …` so nested links keep
working. A core Button with no link set renders `<a class="wp-block-button__link">` with
no `href`, matches the ignore list, and is inert — it neither opens the modal nor
navigates. Narrow the selector to `a[href]:not(.is-primary-link)`. An anchor without an
`href` is not a link: not focusable, no navigation to preserve.

Extract the predicate so it is testable (see Task 1's note on the Jest constraint), add
unit tests for: href-less anchor is not ignored; anchor with href is ignored;
`.is-primary-link` anchor is not ignored; `button`/`input` are ignored.

---

### Task 1: Unified trigger attributes

Merges the attribute halves of `button-modal-extension.js` and
`group-modal-trigger-extension.js` into one module. Both already use
`addFilter( 'blocks.registerBlockType', … )` — reuse that mechanism, do not invent one.

**Files:**

- Create: `src/editor/modal-trigger-attributes.js`
- Create: `src/editor/trigger-blocks.js`
- Test: `tests/unit/editor/trigger-blocks.test.js`

**Interfaces:**

- Consumes: nothing

**Produces:**

- `TRIGGER_BLOCKS` — array, default `[ 'core/group', 'core/button' ]`
- `isTriggerBlock( name: string ) => boolean`
- `MODAL_ATTRIBUTES` — the attribute schema object
- `hasModalAction( attributes: Object ) => boolean`

- [ ] **Step 1: Write the failing test**

Create `tests/unit/editor/trigger-blocks.test.js`:

```js
/**
 * Tests for trigger block identification and attribute helpers.
 *
 * @see src/editor/trigger-blocks.js
 */

import {
	TRIGGER_BLOCKS,
	isTriggerBlock,
	MODAL_ATTRIBUTES,
	hasModalAction,
} from '../../../src/editor/trigger-blocks';

describe('TRIGGER_BLOCKS', () => {
	it('covers group and button only', () => {
		expect(TRIGGER_BLOCKS).toEqual(['core/group', 'core/button']);
	});

	it('does not include core/image', () => {
		// core attaches its own lightbox click handler to core/image;
		// two handlers on one click is the conflict this avoids.
		expect(TRIGGER_BLOCKS).not.toContain('core/image');
	});
});

describe('isTriggerBlock', () => {
	it('accepts a supported block', () => {
		expect(isTriggerBlock('core/group')).toBe(true);
		expect(isTriggerBlock('core/button')).toBe(true);
	});

	it('rejects an unsupported block', () => {
		expect(isTriggerBlock('core/paragraph')).toBe(false);
		expect(isTriggerBlock('core/image')).toBe(false);
	});

	it('rejects empty input', () => {
		expect(isTriggerBlock('')).toBe(false);
		expect(isTriggerBlock(undefined)).toBe(false);
	});
});

describe('MODAL_ATTRIBUTES', () => {
	it('declares every attribute the design names', () => {
		expect(Object.keys(MODAL_ATTRIBUTES).sort()).toEqual(
			[
				'pikariModalAccessibleLabel',
				'pikariModalAction',
				'pikariModalContentSource',
				'pikariModalDirectUrl',
				'pikariModalInlineAnchor',
				'pikariModalPlacement',
				'pikariModalPrimaryLinkId',
				'pikariModalSize',
				'pikariModalTemplatePart',
			].sort()
		);
	});

	it('defaults every attribute to an empty string', () => {
		Object.values(MODAL_ATTRIBUTES).forEach((def) => {
			expect(def.type).toBe('string');
			expect(def.default).toBe('');
		});
	});
});

describe('hasModalAction', () => {
	it('is false when the action is unset', () => {
		expect(hasModalAction({})).toBe(false);
		expect(hasModalAction({ pikariModalAction: '' })).toBe(false);
	});

	it('is true for open and close', () => {
		expect(hasModalAction({ pikariModalAction: 'open' })).toBe(true);
		expect(hasModalAction({ pikariModalAction: 'close' })).toBe(true);
	});

	it('is false for an unrecognised action', () => {
		expect(hasModalAction({ pikariModalAction: 'wobble' })).toBe(false);
	});

	it('tolerates null attributes', () => {
		expect(hasModalAction(null)).toBe(false);
	});
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
npm test -- --testPathPattern=trigger-blocks
```

Expected: FAIL — `Cannot find module '../../../src/editor/trigger-blocks'`.

- [ ] **Step 3: Write the implementation**

Create `src/editor/trigger-blocks.js`. **Tab indentation. Imports nothing from `@wordpress/*`** — that is what keeps it Jest-resolvable.

```js
/**
 * Which blocks can open a modal, and the attributes that make them do it.
 *
 * Deliberately free of `@wordpress/*` imports: the editor packages are webpack
 * externals and are not installed, so Jest cannot resolve them. Logic that
 * needs a unit test lives here; the filter wiring that needs the editor
 * packages lives in modal-trigger-attributes.js.
 */

/**
 * Blocks that can carry a modal action.
 *
 * core/image is excluded: core attaches its own lightbox click handler to it
 * via render_block_core/image, and the toggle ("Enlarge on click") is offered
 * by default. Two click handlers on one element is the conflict this avoids.
 * A site that wants it anyway can add it through the
 * `pikari_gutenberg_modals_trigger_blocks` filter.
 */
export const TRIGGER_BLOCKS = ['core/group', 'core/button'];

/**
 * Recognised values for pikariModalAction.
 */
export const MODAL_ACTIONS = ['open', 'close'];

/**
 * Attribute schema added to every trigger block.
 */
export const MODAL_ATTRIBUTES = {
	pikariModalAction: { type: 'string', default: '' },
	pikariModalContentSource: { type: 'string', default: '' },
	pikariModalDirectUrl: { type: 'string', default: '' },
	pikariModalPrimaryLinkId: { type: 'string', default: '' },
	pikariModalInlineAnchor: { type: 'string', default: '' },
	pikariModalSize: { type: 'string', default: '' },
	pikariModalPlacement: { type: 'string', default: '' },
	pikariModalTemplatePart: { type: 'string', default: '' },
	pikariModalAccessibleLabel: { type: 'string', default: '' },
};

/**
 * Whether a block name can carry a modal action.
 *
 * @param {string} name Block name.
 * @return {boolean} True when supported.
 */
export function isTriggerBlock(name) {
	return TRIGGER_BLOCKS.includes(name);
}

/**
 * Whether a block's attributes declare a modal action.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} True when the action is open or close.
 */
export function hasModalAction(attributes) {
	return MODAL_ACTIONS.includes(attributes?.pikariModalAction);
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
npm test -- --testPathPattern=trigger-blocks
```

Expected: PASS.

- [ ] **Step 5: Wire the attributes onto the blocks**

Create `src/editor/modal-trigger-attributes.js`:

```js
/**
 * Adds the modal attributes to every supported trigger block.
 *
 * Replaces the attribute halves of button-modal-extension.js and
 * group-modal-trigger-extension.js, which declared two different "on"
 * attributes (pikariOpenInModal, pikariModalTrigger) for one concept.
 */

import { addFilter } from '@wordpress/hooks';
import { isTriggerBlock, MODAL_ATTRIBUTES } from './trigger-blocks';

addFilter(
	'blocks.registerBlockType',
	'pikari-gutenberg-modals/modal-attributes',
	(settings, name) => {
		if (!isTriggerBlock(name)) {
			return settings;
		}

		return {
			...settings,
			attributes: {
				...settings.attributes,
				...MODAL_ATTRIBUTES,
			},
		};
	}
);
```

- [ ] **Step 6: Lint and commit**

```bash
npm run lint:js
git add src/editor/trigger-blocks.js src/editor/modal-trigger-attributes.js tests/unit/editor/trigger-blocks.test.js
git commit -m "feat: add unified modal trigger attributes to group and button"
```

---

### Task 2: One Modal inspector panel

**Files:**

- Create: `src/editor/modal-trigger-panel.js`
- Modify: `src/editor/index.js`

**Interfaces:**

- Consumes: `isTriggerBlock`, `hasModalAction`, `MODAL_ACTIONS` (Task 1); `findLinksInBlocks` from `src/editor/find-links-in-blocks.js`; `useModalTemplateParts` from `src/editor/use-modal-template-parts.js`
- Produces: nothing consumed by later tasks

- [ ] **Step 1: Write the panel**

Create `src/editor/modal-trigger-panel.js`. Model the structure on the existing
`withModalInspectorControls` HOC in `button-modal-extension.js` — same
`createHigherOrderComponent` + `editor.BlockEdit` pattern, one panel instead of two.

```js
/**
 * The single "Modal" panel shown on every supported trigger block.
 *
 * Supersedes the two near-identical panels in button-modal-extension.js and
 * group-modal-trigger-extension.js.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { isTriggerBlock, hasModalAction } from './trigger-blocks';
import useModalTemplateParts from './use-modal-template-parts';

const withModalPanel = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name, attributes, setAttributes, isSelected } = props;

		if (!isTriggerBlock(name)) {
			return <BlockEdit {...props} />;
		}

		const {
			pikariModalAction,
			pikariModalContentSource,
			pikariModalDirectUrl,
			pikariModalSize,
			pikariModalPlacement,
			pikariModalTemplatePart,
			pikariModalAccessibleLabel,
		} = attributes;

		const templateParts = useModalTemplateParts();
		const isOpen = pikariModalAction === 'open';

		return (
			<>
				<BlockEdit {...props} />
				{isSelected && (
					<InspectorControls>
						<PanelBody
							title={__('Modal', 'pikari-gutenberg-modals')}
							initialOpen={hasModalAction(attributes)}
						>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={__('Action', 'pikari-gutenberg-modals')}
								value={pikariModalAction}
								options={[
									{ label: __('None', 'pikari-gutenberg-modals'), value: '' },
									{
										label: __('Open a modal', 'pikari-gutenberg-modals'),
										value: 'open',
									},
									{
										label: __('Close the modal', 'pikari-gutenberg-modals'),
										value: 'close',
									},
								]}
								onChange={(value) =>
									setAttributes({ pikariModalAction: value })
								}
								help={__(
									'Close is for use inside a modal template part.',
									'pikari-gutenberg-modals'
								)}
							/>

							{isOpen && (
								<>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={__('Content', 'pikari-gutenberg-modals')}
										value={pikariModalContentSource || 'link'}
										options={[
											{
												label: __('Detected link', 'pikari-gutenberg-modals'),
												value: 'link',
											},
											{
												label: __('Custom URL', 'pikari-gutenberg-modals'),
												value: 'url',
											},
											{
												label: __('Inline content', 'pikari-gutenberg-modals'),
												value: 'inline',
											},
										]}
										onChange={(value) =>
											setAttributes({ pikariModalContentSource: value })
										}
									/>

									{pikariModalContentSource === 'url' && (
										<TextControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={__('URL', 'pikari-gutenberg-modals')}
											value={pikariModalDirectUrl}
											onChange={(value) =>
												setAttributes({ pikariModalDirectUrl: value })
											}
										/>
									)}

									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={__('Placement', 'pikari-gutenberg-modals')}
										value={pikariModalPlacement}
										options={[
											{
												label: __(
													'From the template',
													'pikari-gutenberg-modals'
												),
												value: '',
											},
											{
												label: __('Left panel', 'pikari-gutenberg-modals'),
												value: 'left',
											},
											{
												label: __('Right panel', 'pikari-gutenberg-modals'),
												value: 'right',
											},
										]}
										onChange={(value) =>
											setAttributes({ pikariModalPlacement: value })
										}
									/>

									{templateParts.hasMultiple && (
										<SelectControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={__('Modal template', 'pikari-gutenberg-modals')}
											value={pikariModalTemplatePart}
											options={templateParts.options}
											onChange={(value) =>
												setAttributes({ pikariModalTemplatePart: value })
											}
										/>
									)}

									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={__('Accessible label', 'pikari-gutenberg-modals')}
										value={pikariModalAccessibleLabel}
										onChange={(value) =>
											setAttributes({ pikariModalAccessibleLabel: value })
										}
										help={__(
											'Overrides the label announced to screen readers.',
											'pikari-gutenberg-modals'
										)}
									/>
								</>
							)}
						</PanelBody>
					</InspectorControls>
				)}
			</>
		);
	};
}, 'withModalPanel');

addFilter(
	'editor.BlockEdit',
	'pikari-gutenberg-modals/modal-panel',
	withModalPanel
);
```

Note: the size control is intentionally absent here. It is added by the
modal-overlay-templates plan alongside the template panel, which owns size and
template selection together. `pikariModalSize` exists as an attribute from Task 1 so
server rendering can read it in the meantime.

- [ ] **Step 2: Register the module**

In `src/editor/index.js`, replace the two extension imports:

```js
import './modal-format';
import './modal-trigger-attributes';
import './modal-trigger-panel';
import './style.scss';
```

Leave `group-modal-trigger-extension.js` and `button-modal-extension.js` on disk for
now — Task 6 deletes them, once server rendering reads the new attributes.

- [ ] **Step 3: Build and verify in the browser**

```bash
npm run build && npm run lint:js
```

Add a Group and a Button in the post editor. Each shows a single **Modal** panel with an
Action select. Choosing "Open a modal" reveals Content, Placement and Accessible label.
Choosing "None" collapses them. A Paragraph shows no Modal panel.

- [ ] **Step 4: Commit**

```bash
git add src/editor/modal-trigger-panel.js src/editor/index.js
git commit -m "feat: add a single modal panel to group and button blocks"
```

---

### Task 3: Block variations for discovery

**Files:**

- Create: `src/editor/modal-trigger-variations.js`
- Modify: `src/editor/index.js`

**Interfaces:**

- Consumes: nothing
- Produces: variations `pikari-gutenberg-modals/clickable-card` and `pikari-gutenberg-modals/modal-button`

A variation cannot declare new attributes and is not visible server-side — it only
pre-fills attributes the block already has (added in Task 1) and supplies an inserter
entry. That is its whole job here: discovery.

- [ ] **Step 1: Register the variations**

Create `src/editor/modal-trigger-variations.js`:

```js
/**
 * Inserter entries for the two trigger shapes.
 *
 * A variation is the core block with attributes pre-filled — it adds no
 * behaviour of its own and is not serialized into saved content, so server
 * rendering keys on pikariModalAction, never on the variation name.
 */

import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

registerBlockVariation('core/group', {
	name: 'pikari-modal-clickable-card',
	title: __('Clickable Card', 'pikari-gutenberg-modals'),
	description: __(
		'A group that opens its content in a modal when clicked.',
		'pikari-gutenberg-modals'
	),
	attributes: {
		pikariModalAction: 'open',
		pikariModalContentSource: 'link',
	},
	scope: ['inserter', 'transform'],
	isActive: (blockAttributes) => blockAttributes?.pikariModalAction === 'open',
});

registerBlockVariation('core/button', {
	name: 'pikari-modal-button',
	title: __('Modal Button', 'pikari-gutenberg-modals'),
	description: __(
		'A button that opens content in a modal.',
		'pikari-gutenberg-modals'
	),
	attributes: {
		pikariModalAction: 'open',
		pikariModalContentSource: 'url',
	},
	scope: ['inserter', 'transform'],
	isActive: (blockAttributes) => blockAttributes?.pikariModalAction === 'open',
});
```

- [ ] **Step 2: Register the module**

Add `import './modal-trigger-variations';` to `src/editor/index.js` after the panel import.

- [ ] **Step 3: Build and verify in the browser**

```bash
npm run build && npm run lint:js
```

The inserter shows **Clickable Card** and **Modal Button**. Inserting either produces a
plain Group/Button with the Modal panel already set to "Open a modal". List view labels
them by the variation title.

- [ ] **Step 4: Commit**

```bash
git add src/editor/modal-trigger-variations.js src/editor/index.js
git commit -m "feat: add clickable card and modal button block variations"
```

---

### Task 4: Server-side rendering on the block's own element

**Files:**

- Modify: `includes/BlockSupport.php`
- Modify: `includes/GroupModalTriggerSupport.php`
- Modify: `includes/TriggerContext.php`
- Test: `tests/php/TriggerContextTest.php`, `tests/php/BlockSupportTest.php`

**Interfaces:**

- Consumes: the attribute names from Task 1
- Produces: decorated block HTML; no wrapper element is emitted

`GroupModalTriggerSupport` already decorates a Group's own root element and already
implements two-phase rendering for Query Loop support. That logic is correct and stays.
This task renames the attributes it reads and extends the same treatment to
`core/button`.

- [ ] **Step 1: Write the failing test**

Add to `tests/php/TriggerContextTest.php`:

```php
    public function test_build_reads_the_unified_attribute_names(): void
    {
        $context = TriggerContext::build(
            [
                'pikariModalSize'         => 'wide',
                'pikariModalPlacement'    => 'right',
                'pikariModalTemplatePart' => 'sidebar',
            ],
            [ 'postId' => '7', 'modalId' => 'page-7' ],
            'sidebar'
        );

        $this->assertSame( 'wide', $context['size'] );
        $this->assertSame( 'right', $context['placement'] );
        $this->assertSame( 'sidebar', $context['templatePart'] );
    }

    public function test_build_omits_an_unknown_placement(): void
    {
        $context = TriggerContext::build(
            [ 'pikariModalPlacement' => 'diagonal' ],
            [ 'postId' => '7', 'modalId' => 'page-7' ]
        );

        $this->assertArrayNotHasKey( 'placement', $context );
    }
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit tests/php/TriggerContextTest.php --testdox
```

Expected: FAIL — `TriggerContext::build()` still reads `modalSize`, `modalPlacement`, `modalTemplatePart`.

- [ ] **Step 3: Update TriggerContext**

In `includes/TriggerContext.php`, change the three attribute reads:

```php
        $size = $attributes['pikariModalSize'] ?? '';
```

```php
        $placement = $attributes['pikariModalPlacement'] ?? '';
```

The `$template_part` parameter is passed in by the caller and is unchanged.

- [ ] **Step 4: Point the render filters at the new attributes**

In `includes/GroupModalTriggerSupport.php`, replace every read of
`$block['attrs']['pikariModalTrigger']` with a check on
`( $block['attrs']['pikariModalAction'] ?? '' ) === 'open'`, and
`pikariModalTriggerBlockId` with `pikariModalPrimaryLinkId`. The
`pikariModalTemplatePart` read is already correctly named.

In `includes/BlockSupport.php`, the `core/button` branch currently keys on
`pikariOpenInModal`; change it to the same `pikariModalAction === 'open'` check and
rename its `pikariModalContentSource` / `pikariModalInlineAnchor` reads to match Task 1
(they already use those names).

Register the block list behind a filter. Add to `BlockSupport`:

```php
    /**
     * Blocks that can carry a modal action.
     *
     * core/image is excluded deliberately — core's own lightbox attaches a
     * competing click handler to it via render_block_core/image.
     *
     * @return string[] Block names.
     */
    public function get_trigger_blocks(): array
    {
        return apply_filters(
            'pikari_gutenberg_modals_trigger_blocks',
            [ 'core/group', 'core/button' ]
        );
    }
```

- [ ] **Step 5: Add close-mode handling for ordinary blocks**

Close mode previously lived in the Modal Trigger block's `render.php`. Add the
equivalent to `BlockSupport`, applied when `pikariModalAction === 'close'`:

```php
    /**
     * Turn a block into a close trigger.
     *
     * No data-wp-interactive is added: close triggers live inside modal
     * template parts, where the container already provides the namespace.
     * Adding it here creates a nested Interactivity island and breaks events.
     *
     * @param string $block_content Rendered block HTML.
     * @return string Decorated HTML.
     */
    private function filter_close_trigger( string $block_content ): string
    {
        $processor = new \WP_HTML_Tag_Processor( $block_content );

        if ( $processor->next_tag() ) {
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleCloseClick' );
            $processor->set_attribute( 'data-wp-on--keydown', 'actions.handleCloseKeydown' );
            $processor->set_attribute( 'role', 'button' );
            $processor->set_attribute( 'tabindex', '0' );
            $processor->set_attribute(
                'aria-label',
                esc_attr__( 'Close dialog', 'pikari-gutenberg-modals' )
            );
            $processor->add_class( 'modal-close-trigger' );
        }

        return $processor->get_updated_html();
    }
```

- [ ] **Step 6: Run the tests**

```bash
vendor/bin/phpunit --testdox
composer lint
```

Expected: PASS, phpcs clean.

- [ ] **Step 7: Build and verify in the browser**

```bash
npm run build
```

A Group with Action "Open a modal" and a link inside opens the modal on click, **and
keeps its background, border, radius and padding**. Inspect the rendered element: the
Interactivity attributes are on `.wp-block-group` itself and there is no extra wrapper
div.

- [ ] **Step 8: Commit**

```bash
git add includes/ tests/php/
git commit -m "feat: render modal triggers on the block's own element"
```

---

### Task 5: Rewrite the modal template part close row

**Files:**

- Modify: `parts/modal.html`

**Interfaces:**

- Consumes: close-mode rendering (Task 4)
- Produces: a template part containing no Modal Trigger block

- [ ] **Step 1: Replace the close trigger**

In `parts/modal.html`, replace the `modal-trigger` block wrapping the close button with
a plain Button carrying the close action. The surrounding Group and the
`content-area` block are unchanged:

```html
<!-- wp:group {"layout":{"type":"flex","justifyContent":"right"}} -->
<div class="wp-block-group">
	<!-- wp:button {"pikariModalAction":"close"} -->
	<div class="wp-block-button">
		<a class="wp-block-button__link wp-element-button">Close</a>
	</div>
	<!-- /wp:button -->
</div>
<!-- /wp:group -->
```

Do **not** rename `modal-dialog` in this file. That rename belongs to the
modal-overlay-templates plan, whose Task 3 sed still needs to find it.

- [ ] **Step 2: Build and verify in the browser**

```bash
npm run build
```

Open any modal. The Close button dismisses it, keyboard activation works with Enter and
Space, and the fallback `sr-only` close button is **not** injected — confirm by
inspecting the dialog for `modal-close-fallback`, which should be absent because
`render.php` detects `actions.handleCloseClick` in the content.

- [ ] **Step 3: Commit**

```bash
git add parts/modal.html
git commit -m "feat: use a button close action in the modal template part"
```

---

### Task 6: Remove the Modal Trigger block

**Files:**

- Delete: `src/blocks/modal-trigger/` (whole directory)
- Delete: `src/editor/button-modal-extension.js`, `src/editor/group-modal-trigger-extension.js`
- Modify: `pikari-gutenberg-modals.php`, `webpack.config.js`, `src/editor/index.js`
- Modify: `includes/EditorIntegration.php`

**Interfaces:**

- Consumes: Tasks 1–5 must be complete and verified first
- Produces: nothing

- [ ] **Step 1: Remove the block and the superseded extensions**

```bash
git rm -r src/blocks/modal-trigger
git rm src/editor/button-modal-extension.js src/editor/group-modal-trigger-extension.js
```

Then remove:

1. `pikari-gutenberg-modals.php` — the `register_block_type( … 'build/blocks/modal-trigger' )` call.
2. `webpack.config.js` — the `blocks/modal-trigger/index` entry.
3. `src/editor/index.js` — the two deleted imports, if Task 2 left any.

- [ ] **Step 2: Fix the stale comment in index.js**

`src/editor/index.js` carries a `domReady` block whose comment reads "Modal Trigger is
kept registered because it supports close mode inside modal template parts." That is no
longer true. Update the comment to explain only the Modal Content unregistration; do
not change the behaviour.

- [ ] **Step 3: Check the block restriction list**

`EditorIntegration::restrict_modal_template_blocks()` restricts
`pikari-gutenberg-modals/modal-dialog`, not the trigger, so it needs no change. Confirm
by reading it rather than assuming.

- [ ] **Step 4: Verify nothing references the deleted block**

```bash
grep -rn "modal-trigger" \
  --exclude-dir=build --exclude-dir=node_modules --exclude-dir=vendor \
  --exclude-dir=.git --exclude-dir=docs --exclude-dir=_plans .
```

Expected: no output. `docs/` and `_plans/` are excluded because they record history.

Note for the reviewer: existing saved `modal-trigger` blocks in user content now render
**nothing on the front end** — a dynamic block with no registered type has no render
callback, though its inner blocks still render, so the page looks intact but the trigger
is inert. In the editor they appear as an unrecognised block. Migration is deliberately
out of scope.

- [ ] **Step 5: Build, lint, test**

```bash
npm run build
npm run lint:all
composer test
npm test
```

Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor: remove the Modal Trigger block in favour of block attributes"
```

---

### Task 7: Amend the modal-overlay-templates plan

**Files:**

- Modify: `docs/superpowers/plans/2026-09-07-modal-overlay-templates.md`
- Modify: `docs/superpowers/specs/2026-09-07-modal-overlay-templates-design.md`

**Interfaces:**

- Consumes: everything above
- Produces: a plan consistent with the reduced surface set

That plan assumes four trigger surfaces including the Modal Trigger block. Left
unamended it would tell an executor to wire a panel into a deleted file, and to author
patterns containing a deleted block.

- [ ] **Step 1: Amend Task 10 (trigger surfaces)**

Rewrite Task 10 to wire `ModalTemplatePanel` into the single Modal panel from Task 2 of
this plan, rather than into four call sites. Remove the Modal Trigger block and the
button/group extension steps. Keep the inline RichText format step — the format is
unchanged and still needs the panel in reduced form (`showCreate={false}`,
`showPreview={false}`).

- [ ] **Step 2: Amend Task 4 (starter patterns)**

The three pattern files author their close row as
`<!-- wp:pikari-gutenberg-modals/modal-trigger {"triggerAction":"close"} -->`. That
block no longer exists. Replace with the Button close action from Task 5 of this plan:

```html
<!-- wp:button {"pikariModalAction":"close"} -->
<div class="wp-block-button">
	<a class="wp-block-button__link wp-element-button">Close</a>
</div>
<!-- /wp:button -->
```

Update the `ModalPatternsTest` assertion accordingly — it asserts
`'"triggerAction":"close"'` appears in every pattern; it should assert
`'"pikariModalAction":"close"'`.

- [ ] **Step 3: Amend the spec's §6**

`docs/superpowers/specs/2026-09-07-modal-overlay-templates-design.md` §6 lists three
degraded modes, one of which is the `core/group` extension "keeps its existing select
until removal". That extension is now gone. Rewrite that bullet and the inline-popover
bullet to describe the two surfaces that remain.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/
git commit -m "docs: amend the overlay templates plan for the reduced trigger surface set"
```

---

### Task 8: Documentation

**Files:**

- Modify: `CLAUDE.md`, `readme.txt`, `README.md`, `CHANGELOG.md`

**Interfaces:**

- Consumes: everything
- Produces: nothing

- [ ] **Step 1: Update CLAUDE.md**

- Rewrite the **Trigger Types** section: three types become two mechanisms — the inline
  RichText format, and the `pikariModalAction` attribute on Group and Button. Delete the
  Group Block Modal Triggers and Modal Trigger Block entries.
- Remove `src/blocks/modal-trigger/` from Project Structure; remove
  `button-modal-extension.js` and `group-modal-trigger-extension.js` from the JavaScript
  table and add `trigger-blocks.js`, `modal-trigger-attributes.js`,
  `modal-trigger-panel.js`, `modal-trigger-variations.js`.
- Add `pikari_gutenberg_modals_trigger_blocks` to Custom Hooks & Filters.
- Add a gotcha: **A block variation is not visible server-side.** Variations pre-fill
  attributes and supply an inserter entry; the variation name is never serialized, so
  `render_block` must key on `pikariModalAction`, never on the variation.
- Add a gotcha: **core/image is excluded from trigger blocks on purpose.** Core's
  lightbox ("Enlarge on click") attaches a competing click handler via
  `render_block_core/image` and is offered by default.

- [ ] **Step 2: Update readme.txt and README.md**

Both get the new filter documented in the Developer section, and the trigger
description rewritten from three block types to "apply the modal action to a Group or
Button, or highlight text and use the inline format". The two files must stay identical
in content.

- [ ] **Step 3: Update CHANGELOG.md**

```markdown
### Changed

- **Breaking:** the Modal Trigger block has been removed. Opening a modal is now an
  action set on a Group or Button block, so the block keeps its own styling, alignment
  and layout. Existing Modal Trigger blocks will not render and must be rebuilt.

### Added

- Clickable Card and Modal Button block variations.
- `pikari_gutenberg_modals_trigger_blocks` filter.

### Fixed

- A Button with no link set inside a trigger no longer ignores clicks.
```

- [ ] **Step 4: Verify and commit**

```bash
npm run lint:all && composer test && npm test
diff <(sed -n '/Developer/,$p' readme.txt) <(sed -n '/Developer/,$p' README.md) || echo "readme files differ — reconcile"
git add -A
git commit -m "docs: document modal triggers as a block property"
```

---

## Self-Review

### Spec coverage

| Spec section                              | Task                                           |
| ----------------------------------------- | ---------------------------------------------- |
| Decision: two mechanisms                  | 1, 2                                           |
| Unified attribute namespace               | 1                                              |
| Supported blocks + filter                 | 1, 4                                           |
| Editor panel                              | 2                                              |
| Variations for discovery                  | 3                                              |
| Server renders on the block's own element | 4                                              |
| Close mode on ordinary blocks             | 4, 5                                           |
| `a[href]` fix                             | Companion fix (deliberately outside this plan) |
| What is removed                           | 6                                              |
| `parts/modal.html` rewrite                | 5                                              |
| No migration                              | 6 (stated in Step 4)                           |
| Impact on overlay-templates plan          | 7                                              |
| Testing                                   | 1 (Jest), 4 (PHPUnit), 2/3/4/5 (browser)       |

No spec section is unimplemented. The one open question in the spec — discovery beyond
the two variations — was answered "leave it to the variations for now" and needs no task.

### Type consistency

`isTriggerBlock( name )` and `hasModalAction( attributes )` keep the same signatures in
Tasks 1 and 2. `MODAL_ATTRIBUTES` keys match the PHP reads in Task 4 exactly
(`pikariModalAction`, `pikariModalSize`, `pikariModalPlacement`,
`pikariModalTemplatePart`, `pikariModalPrimaryLinkId`, `pikariModalContentSource`,
`pikariModalInlineAnchor`, `pikariModalDirectUrl`, `pikariModalAccessibleLabel`). The
close-mode attribute value `'close'` is the same string in Tasks 2, 4 and 5.

### Known risks

- **Task 4 is the widest change** and has no unit-test harness for `render_block`
  output beyond `TriggerContext`. Browser verification in Step 7 is the real coverage,
  matching the plugin's existing stance for render paths.
- **`pikariModalSize` has an attribute but no control** until the overlay-templates plan
  adds one. Deliberate, noted in Task 2, and harmless — the attribute simply stays empty.
- **Task 6 is irreversible** in the sense that saved content stops working. It is
  sequenced last among the code tasks so everything else is verified first.
