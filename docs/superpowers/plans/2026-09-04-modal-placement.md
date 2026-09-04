# Modal Placement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a modal dialog be centered (today's behaviour) or pinned to the left or right viewport edge as a full-height panel, with the panel width selectable per trigger.

**Architecture:** Placement is an attribute of the **Modal Dialog block**, rendered as `data-default-placement` on `.modal-content`. A trigger may override it through Interactivity context. At open time the store resolves `trigger → dialog → centered` in one pure function and writes the winner to `data-placement` on `.modal-overlay`, mirroring how `data-size` already works. All geometry CSS keys off `.modal-overlay[data-placement]`, so the chrome Group's author-set background, padding and shadow are untouched — only radius is overridden, exactly as fullscreen and mobile already do.

**Tech Stack:** WordPress 6.8+ block API (`block.json`, `render.php`), WordPress Interactivity API (generator actions, `data-wp-context`), Jest for JS units, PHPUnit + Brain\Monkey for PHP units, `@wordpress/scripts` webpack build.

**Spec:** `docs/superpowers/specs/2026-09-04-modal-placement-design.md` — read it first. This plan implements strand 3 of that document; strands 1 and 2 are already on `main`.

**Worktree:** `/Users/steveariss/Sites/pikari/_worktrees/modals-placement`, branch `feature/modal-placement`, based on `origin/main` (`33637d1`). Every command below assumes that directory is the working directory.

## Global Constraints

- **PHP 8.4+**, **WordPress 6.8+**. Do not lower either.
- **PHP indentation is 4 spaces, not tabs** (`phpcs.xml` enforces it).
- **JavaScript indentation is tabs, not spaces.** Prettier ignores JS; `npm run lint:fix` will correct drift.
- All user-facing strings use `__()` / `esc_attr__()` / `esc_html__()` with text domain `pikari-gutenberg-modals`.
- **`render.php` is read from `build/`, not `src/`.** Run `npm run build` after editing any `render.php` or block CSS, or wp-env serves stale assets.
- **Never hand-edit these files** — they are synced from the monorepo and edits are silently lost: `phpcs.xml`, `phpunit.xml.dist`, `jest.config.js`, anything in `.github/workflows/`, `tests/php/TestCase.php`. (`tests/php/bootstrap.php` is protected by `skip-sync` and is safe.)
- **Do not bump the version in this branch.** The version lives in three places that CI asserts agree (plugin header `* Version:`, `PIKARI_GUTENBERG_MODALS_VERSION`, `package.json`). When the release happens, run `node .github/bump-version.js pikari-gutenberg-modals <version>` from the monorepo root — never hand-edit one of the three.
- CI required checks are `Code Quality`, `Build` and `Test`, on `main` only. Before pushing: `npm run lint:all && composer test && npm test`.
- New developer-facing customisation points must be documented in **`CLAUDE.md`**, **`readme.txt`** *and* **`README.md`** (the Documentation Rule; readme.txt and README.md are the same content in two formats and must stay in sync).
- Read `_plans/testing-notes.md` before authoring any browser test page. Hand-authored trigger markup navigates instead of opening the modal, and looks exactly like a broken feature.

## Sequencing note

Tasks 1–5 deliver the Kindler driver: a right-hand full-height panel configured on the Modal Dialog block. Tasks 6–7 add the per-trigger override and the panel-width picker, and are separable — they can be split into a follow-up PR without leaving tasks 1–5 incomplete. Task 8 (dialog `aria-label`) is folded in per todo #437 but is independent of placement and may be dropped.

---

### Task 1: Geometry resolution as a pure module

Placement precedence and contextual sizing are the only real logic in this feature. They go in their own module so they can be tested without the store, mirroring `src/frontend/video-providers.js` and its test.

The size filter matters: `fullscreen` is a centered-only slug that sets `max-width:100%; height:100%`. Left leaking onto a right panel it produces a full-width sheet, silently undoing the placement.

**Files:**
- Create: `src/frontend/modal-geometry.js`
- Test: `tests/unit/frontend/modal-geometry.test.js`

**Interfaces:**
- Consumes: nothing.
- Produces: `resolveGeometry( { triggerPlacement, dialogPlacement, size } ) => { placement: string, size: string }`, plus exported constants `PANEL_PLACEMENTS` (`['left','right']`), `PANEL_SIZES` (`['narrow','wide']`), `CENTERED_SIZES` (`['small','large','fullscreen']`). Task 4 imports `resolveGeometry`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/frontend/modal-geometry.test.js`:

```js
/**
 * Placement precedence and contextual sizing.
 *
 * @see src/frontend/modal-geometry.js
 */

import { resolveGeometry } from '../../../src/frontend/modal-geometry';

describe( 'resolveGeometry', () => {
	it( 'defaults to centered with no size', () => {
		expect( resolveGeometry() ).toEqual( { placement: '', size: '' } );
	} );

	it( 'uses the dialog placement when the trigger sets none', () => {
		expect(
			resolveGeometry( { dialogPlacement: 'right' } )
		).toEqual( { placement: 'right', size: '' } );
	} );

	it( 'lets the trigger override the dialog placement', () => {
		expect(
			resolveGeometry( {
				triggerPlacement: 'left',
				dialogPlacement: 'right',
			} )
		).toEqual( { placement: 'left', size: '' } );
	} );

	it( 'ignores an unknown trigger placement and falls back to the dialog', () => {
		expect(
			resolveGeometry( {
				triggerPlacement: 'top',
				dialogPlacement: 'right',
			} )
		).toEqual( { placement: 'right', size: '' } );
	} );

	it( 'ignores an unknown dialog placement and centers', () => {
		expect(
			resolveGeometry( { dialogPlacement: 'bottom' } )
		).toEqual( { placement: '', size: '' } );
	} );

	it( 'keeps a centered size slug when centered', () => {
		expect( resolveGeometry( { size: 'fullscreen' } ) ).toEqual( {
			placement: '',
			size: 'fullscreen',
		} );
	} );

	it( 'drops a centered size slug on a panel', () => {
		expect(
			resolveGeometry( { dialogPlacement: 'right', size: 'fullscreen' } )
		).toEqual( { placement: 'right', size: '' } );
	} );

	it( 'keeps a panel size slug on a panel', () => {
		expect(
			resolveGeometry( { dialogPlacement: 'right', size: 'wide' } )
		).toEqual( { placement: 'right', size: 'wide' } );
	} );

	it( 'drops a panel size slug when centered', () => {
		expect( resolveGeometry( { size: 'narrow' } ) ).toEqual( {
			placement: '',
			size: '',
		} );
	} );
} );
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
npm test -- --testPathPattern=modal-geometry
```

Expected: FAIL — `Cannot find module '../../../src/frontend/modal-geometry'`.

- [ ] **Step 3: Write the minimal implementation**

Create `src/frontend/modal-geometry.js` (tabs for indentation):

```js
/**
 * Geometry resolution for modal placement and size.
 *
 * Placement precedence: the trigger's override, else the Modal Dialog
 * block's own value, else centered.
 *
 * Size is contextual. Centered modals measure a max-width; panels measure
 * a width. A slug only survives if it belongs to the resolved placement —
 * without that, `fullscreen` on a right panel would apply
 * `max-width: 100%` and silently turn the panel into a full-width sheet.
 */

export const PANEL_PLACEMENTS = [ 'left', 'right' ];
export const PANEL_SIZES = [ 'narrow', 'wide' ];
export const CENTERED_SIZES = [ 'small', 'large', 'fullscreen' ];

/**
 * Resolve the effective placement and size for an opening modal.
 *
 * @param {Object} options                  Resolution inputs.
 * @param {string} options.triggerPlacement Placement from the trigger's context.
 * @param {string} options.dialogPlacement  Placement declared by the Modal Dialog block.
 * @param {string} options.size             Size slug from the trigger's context.
 * @return {{placement: string, size: string}} Effective geometry. Empty strings mean default.
 */
export function resolveGeometry( {
	triggerPlacement = '',
	dialogPlacement = '',
	size = '',
} = {} ) {
	let placement = '';

	if ( PANEL_PLACEMENTS.includes( triggerPlacement ) ) {
		placement = triggerPlacement;
	} else if ( PANEL_PLACEMENTS.includes( dialogPlacement ) ) {
		placement = dialogPlacement;
	}

	const allowed = placement ? PANEL_SIZES : CENTERED_SIZES;

	return {
		placement,
		size: allowed.includes( size ) ? size : '',
	};
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
npm test -- --testPathPattern=modal-geometry
```

Expected: PASS, 9 tests.

- [ ] **Step 5: Commit**

```bash
git add src/frontend/modal-geometry.js tests/unit/frontend/modal-geometry.test.js
git commit -m "feat: resolve modal placement and contextual size as a pure function"
```

---

### Task 2: Placement CSS

Geometry applies to `.modal-overlay` and `.modal-content`; the chrome Group inside fills it. Border-radius is overridden with `!important` for panels, following the precedent already set by the fullscreen and mobile rules — a panel flush against the viewport edge with rounded corners looks broken. Background, padding and shadow are left to the author.

`reduced-motion.test.js` asserts that **every** selector declaring an `animation` outside the media block is overridden inside it, and that nothing is overridden that does not animate. The four new panel animation selectors must be added to the `prefers-reduced-motion` block **in this same edit** or that test fails. Selector strings are compared exactly after trimming, so they must match character for character.

No new breakpoint is needed: the existing `@media (max-width: 768px)` block already forces full width and height, and the widest panel (600px) plus margin sits under that threshold.

**Files:**
- Modify: `src/blocks/modal-dialog/style.css` — add panel width custom properties to the `:root` block (lines 10-15), placement rules after the size variations (line 119), keyframes after the existing ones (line 183), and new selectors in the reduced-motion block (lines 186-199).
- Test: `tests/unit/frontend/reduced-motion.test.js` (existing — must keep passing, do not edit it)

**Interfaces:**
- Consumes: nothing.
- Produces: `.modal-overlay[data-placement="left"|"right"]` as the CSS contract Task 4's store writes to; custom properties `--modal-panel-width`, `--modal-panel-width-narrow`, `--modal-panel-width-wide`; `data-size` slugs `narrow` and `wide` used by Task 7.

- [ ] **Step 1: Run the reduced-motion test to record the green baseline**

```bash
npm test -- --testPathPattern=reduced-motion
```

Expected: PASS, 4 tests. This is the guard the next step must not break.

- [ ] **Step 2: Add the panel width custom properties**

In `src/blocks/modal-dialog/style.css`, extend the `:root` block so it reads:

```css
:root {
	--modal-max-width: 1024px;
	--modal-max-width-small: 500px;
	--modal-max-width-large: 1200px;
	--modal-panel-width: 420px;
	--modal-panel-width-narrow: 320px;
	--modal-panel-width-wide: 600px;
	--modal-focus-color: #3b82f6;
}
```

- [ ] **Step 3: Add the placement rules**

Immediately after the `[data-size="fullscreen"]` rules (currently ending line 119), before the `/* Mobile responsiveness */` comment:

```css
/* Placement — applied via data-placement on .modal-overlay.
   Geometry belongs to the container; the chrome Group inside fills it.
   Author-set background, padding and shadow are untouched. */
.modal-overlay[data-placement="left"],
.modal-overlay[data-placement="right"] {
	align-items: stretch;
}
.modal-overlay[data-placement="left"] {
	justify-content: flex-start;
}
.modal-overlay[data-placement="right"] {
	justify-content: flex-end;
}
.modal-overlay[data-placement="left"] .modal-content,
.modal-overlay[data-placement="right"] .modal-content {
	width: var(--modal-panel-width, 420px);
	max-width: 100%;
	max-height: 100%;
	height: 100%;
}
.modal-overlay[data-placement="left"][data-size="narrow"] .modal-content,
.modal-overlay[data-placement="right"][data-size="narrow"] .modal-content {
	width: var(--modal-panel-width-narrow, 320px);
}
.modal-overlay[data-placement="left"][data-size="wide"] .modal-content,
.modal-overlay[data-placement="right"][data-size="wide"] .modal-content {
	width: var(--modal-panel-width-wide, 600px);
}

/* Square off the corners against the viewport edge, the same way
   fullscreen and mobile already beat the Group's inline radius. */
.modal-overlay[data-placement] .modal-content .modal-chrome {
	border-radius: 0 !important;
}

/* Panels slide in from their edge rather than scaling from the centre. */
.modal-overlay[data-placement="left"].is-open .modal-content {
	animation: modal-slide-in-left 300ms ease-out forwards;
}
.modal-overlay[data-placement="right"].is-open .modal-content {
	animation: modal-slide-in-right 300ms ease-out forwards;
}
.modal-overlay[data-placement="left"].is-closing .modal-content {
	animation: modal-slide-out-left 200ms ease-in forwards;
}
.modal-overlay[data-placement="right"].is-closing .modal-content {
	animation: modal-slide-out-right 200ms ease-in forwards;
}
```

- [ ] **Step 4: Add the keyframes**

After the existing `@keyframes modal-scale-out` block (currently ending line 183), before the `/* Accessibility */` comment:

```css
@keyframes modal-slide-in-left {
	from {
		opacity: 0;
		transform: translateX(-100%);
	}
	to {
		opacity: 1;
		transform: translateX(0);
	}
}

@keyframes modal-slide-out-left {
	from {
		opacity: 1;
		transform: translateX(0);
	}
	to {
		opacity: 0;
		transform: translateX(-100%);
	}
}

@keyframes modal-slide-in-right {
	from {
		opacity: 0;
		transform: translateX(100%);
	}
	to {
		opacity: 1;
		transform: translateX(0);
	}
}

@keyframes modal-slide-out-right {
	from {
		opacity: 1;
		transform: translateX(0);
	}
	to {
		opacity: 0;
		transform: translateX(100%);
	}
}
```

- [ ] **Step 5: Extend the reduced-motion override**

Inside the existing `@media (prefers-reduced-motion: reduce)` block, after the
`.modal-overlay.is-open .modal-content, .modal-overlay.is-closing .modal-content` rule, add:

```css
	.modal-overlay[data-placement="left"].is-open .modal-content,
	.modal-overlay[data-placement="right"].is-open .modal-content,
	.modal-overlay[data-placement="left"].is-closing .modal-content,
	.modal-overlay[data-placement="right"].is-closing .modal-content {
		animation: none;
	}
```

- [ ] **Step 6: Run the tests and the CSS linter**

```bash
npm test -- --testPathPattern=reduced-motion
npm run lint:css
```

Expected: 4 tests PASS (in particular "overrides every selector that animates" and "overrides nothing that does not animate"), and stylelint clean. If "overrides every selector that animates" fails, a selector string in step 5 does not match step 3 character for character.

- [ ] **Step 7: Commit**

```bash
git add src/blocks/modal-dialog/style.css
git commit -m "feat: add left and right panel placement styles for the modal dialog"
```

---

### Task 3: Placement attribute on the Modal Dialog block

The block declares the placement; the store resolves it at open time. `render.php` emits `data-default-placement` rather than `data-placement` so the *declared* value on `.modal-content` can never be confused with the *effective* value the store writes on `.modal-overlay` — they are different elements with different meanings, and CSS reads only the latter.

**Files:**
- Modify: `src/blocks/modal-dialog/block.json` — add to `attributes` (after `hasParallax`, line 30)
- Modify: `src/blocks/modal-dialog/render.php:78-83` — the `get_block_wrapper_attributes()` call
- Modify: `src/blocks/modal-dialog/edit.js` — imports (line 15-22), destructuring (line 63-70), new InspectorControls panel

**Interfaces:**
- Consumes: nothing.
- Produces: block attribute `placement` (string, default `''`, valid values `''`, `'left'`, `'right'`); the `data-default-placement` attribute on `.modal-content`, which Task 4's store reads.

- [ ] **Step 1: Add the attribute to block.json**

In `src/blocks/modal-dialog/block.json`, inside `attributes`, after the `hasParallax` entry:

```json
		"placement": {
			"type": "string",
			"default": ""
		},
```

- [ ] **Step 2: Emit it from render.php**

Replace the `--- Dialog container ---` block in `src/blocks/modal-dialog/render.php` (lines 77-83) with:

```php
// --- Dialog container ---
// The declared placement travels as `data-default-placement`. The store
// resolves it against any trigger override and writes the winner to
// `data-placement` on the overlay, which is what the CSS reads.
$wrapper_args = [
    'class'             => 'modal-content',
    'data-wp-on--click' => 'actions.stopPropagation',
];

$placement = $attributes['placement'] ?? '';
if ( in_array( $placement, [ 'left', 'right' ], true ) ) {
    $wrapper_args['data-default-placement'] = $placement;
}

$wrapper_attrs = get_block_wrapper_attributes( $wrapper_args );
```

- [ ] **Step 3: Add the inspector control**

In `src/blocks/modal-dialog/edit.js`, add `SelectControl` to the `@wordpress/components` import list, add `placement` to the destructured attributes, and insert a new `InspectorControls` section immediately before the existing `<InspectorControls group="color">`:

```jsx
			<InspectorControls>
				<PanelBody
					title={ __( 'Placement', 'pikari-gutenberg-modals' ) }
				>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Dialog placement',
							'pikari-gutenberg-modals'
						) }
						value={ placement }
						options={ [
							{
								label: __(
									'Centered',
									'pikari-gutenberg-modals'
								),
								value: '',
							},
							{
								label: __(
									'Left edge',
									'pikari-gutenberg-modals'
								),
								value: 'left',
							},
							{
								label: __(
									'Right edge',
									'pikari-gutenberg-modals'
								),
								value: 'right',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { placement: value } )
						}
						help={ __(
							'Edge placements pin the dialog to the side of the screen at full height. The corners are squared off against the edge.',
							'pikari-gutenberg-modals'
						) }
					/>
				</PanelBody>
			</InspectorControls>
```

- [ ] **Step 4: Build and lint**

```bash
npm run build && npm run lint:js && npm run lint:php
```

Expected: build succeeds, both linters clean. The build is required — WordPress reads `render.php` from `build/blocks/`, not `src/`.

- [ ] **Step 5: Commit**

```bash
git add src/blocks/modal-dialog/block.json src/blocks/modal-dialog/render.php src/blocks/modal-dialog/edit.js
git commit -m "feat: add a placement attribute to the Modal Dialog block"
```

---

### Task 4: Store applies the resolved geometry

`data-size` is written in three places today — the open path, the close timeout, and the cancel-pending-close branch that finishes a previous modal immediately when a second trigger fires before the first has finished closing. All three need the placement equivalent, or a right panel stays a right panel after a centered modal opens over it.

**Files:**
- Modify: `src/frontend/modal-store.js` — imports (line 12-21), `openModal` context destructuring (line 74-81), cancel-pending-close branch (line 119), the "Apply size from trigger context" block (line 160-166), `closeModal` timeout (line 372)
- Test: `tests/unit/frontend/modal-store-geometry.test.js` (create)

**Interfaces:**
- Consumes: `resolveGeometry` from Task 1; `data-default-placement` from Task 3; the `placement` context key, which Task 7 populates (absent until then, which resolves to the dialog's value — the intended default).
- Produces: `data-placement` on the `.modal-overlay` container element.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/frontend/modal-store-geometry.test.js`. The inline content source is used because it returns before any `fetch`, so the whole open path runs synchronously:

```js
/**
 * The store writes effective geometry to the container.
 *
 * @see src/frontend/modal-geometry.js for the resolution rules themselves.
 */

import { store, getContext } from '@wordpress/interactivity';

import '../../../src/frontend/modal-store';

/**
 * Build a modal container with an inline content source on the page.
 *
 * @param {string} dialogPlacement Value for data-default-placement, or ''.
 * @return {HTMLElement} The container element.
 */
function setUpContainer( dialogPlacement = '' ) {
	document.body.innerHTML = `
		<div id="pikari-modal" class="modal-overlay">
			<div class="modal-content"${
				dialogPlacement
					? ` data-default-placement="${ dialogPlacement }"`
					: ''
			}>
				<div class="modal-body"></div>
			</div>
		</div>
		<div data-modal-inline-content="promo" data-modal-inline-title="Promo">
			<p>Inline content</p>
		</div>
	`;
	return document.getElementById( 'pikari-modal' );
}

/**
 * Run the openModal generator to completion.
 *
 * @param {Object} actions Store actions.
 */
function runOpen( actions ) {
	const generator = actions.openModal();
	let step = generator.next();
	while ( ! step.done ) {
		step = generator.next();
	}
}

describe( 'modal store geometry', () => {
	let actions;

	beforeEach( () => {
		( { actions } = store.getStore( 'pikari-modal' ) );
		jest.clearAllMocks();
	} );

	it( 'leaves a centered dialog with no placement attribute', () => {
		const container = setUpContainer();
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
		} );

		runOpen( actions );

		expect( container.hasAttribute( 'data-placement' ) ).toBe( false );
	} );

	it( "applies the dialog's own placement", () => {
		const container = setUpContainer( 'right' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'data-placement' ) ).toBe( 'right' );
	} );

	it( 'lets the trigger override the dialog placement', () => {
		const container = setUpContainer( 'right' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			placement: 'left',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'data-placement' ) ).toBe( 'left' );
	} );

	it( 'drops a centered size slug on a panel', () => {
		const container = setUpContainer( 'right' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			size: 'fullscreen',
		} );

		runOpen( actions );

		expect( container.hasAttribute( 'data-size' ) ).toBe( false );
	} );

	it( 'keeps a panel size slug on a panel', () => {
		const container = setUpContainer( 'right' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			size: 'wide',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'data-size' ) ).toBe( 'wide' );
	} );
} );
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
npm test -- --testPathPattern=modal-store-geometry
```

Expected: FAIL on "applies the dialog's own placement" — `data-placement` is never written, so `getAttribute` returns `null`.

- [ ] **Step 3: Import the resolver and read the trigger's placement**

In `src/frontend/modal-store.js`, add to the imports beside `video-providers`:

```js
import { resolveGeometry } from './modal-geometry';
```

and add `placement` to the context destructuring in `openModal`, so it reads:

```js
			const {
				postId,
				modalId,
				size,
				placement,
				contentSource,
				inlineAnchor,
				templatePart,
			} = context;
```

- [ ] **Step 4: Apply the resolved geometry on open**

Replace the "Apply size from trigger context" block with:

```js
			// Geometry: the trigger's override wins, else the Modal Dialog
			// block's own placement, else centered. The size slug is dropped
			// when it does not belong to the resolved placement.
			const dialogEl = modal.querySelector( '.modal-content' );
			const geometry = resolveGeometry( {
				triggerPlacement: placement,
				dialogPlacement:
					dialogEl?.getAttribute( 'data-default-placement' ) || '',
				size,
			} );

			if ( geometry.size ) {
				modal.setAttribute( 'data-size', geometry.size );
			} else {
				modal.removeAttribute( 'data-size' );
			}

			if ( geometry.placement ) {
				modal.setAttribute( 'data-placement', geometry.placement );
			} else {
				modal.removeAttribute( 'data-placement' );
			}
```

- [ ] **Step 5: Clear placement on both close paths**

In the cancel-pending-close branch, beside `activeContainer.removeAttribute( 'data-size' );`, add:

```js
					activeContainer.removeAttribute( 'data-placement' );
```

In the `closeModal` timeout, beside `modal.removeAttribute( 'data-size' );`, add:

```js
					modal.removeAttribute( 'data-placement' );
```

- [ ] **Step 6: Run the tests**

```bash
npm test
```

Expected: all suites PASS, including the 5 new geometry tests and the existing 58.

- [ ] **Step 7: Build, lint and commit**

```bash
npm run build && npm run lint:js
git add src/frontend/modal-store.js tests/unit/frontend/modal-store-geometry.test.js
git commit -m "feat: apply resolved placement and size to the modal container"
```

---

### Task 5: Extract the trigger context builder

`modal-trigger/render.php` builds the Interactivity context in four near-identical blocks — the url/post branch (lines 139-156), the inline branch (194-206), the post-link branch (301-312) and the url-link branch (386-401). Adding placement in Task 7 means editing all four. Extract first, with no behaviour change, so Task 7 is a one-line addition and this refactor can be reviewed on its own.

A static method on a class, not a function declared in `render.php` — `render.php` runs once per block instance, so a plain function declaration fatals on the second trigger on a page.

**Files:**
- Create: `includes/TriggerContext.php`
- Create: `tests/php/TriggerContextTest.php`
- Modify: `src/blocks/modal-trigger/render.php` — the four context blocks and the `use` statements (lines 15-18)

**Interfaces:**
- Consumes: nothing.
- Produces: `Pikari\GutenbergModals\TriggerContext::build( array $attributes, array $base, string $template_part = '' ): array` — returns `$base` with `size` and `templatePart` added when non-empty. Task 7 adds `placement` to it.

- [ ] **Step 1: Write the failing test**

Create `tests/php/TriggerContextTest.php`:

```php
<?php

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\TriggerContext;

class TriggerContextTest extends TestCase
{
    public function test_returns_base_unchanged_when_no_options_set(): void
    {
        $base = [ 'postId' => 12, 'modalId' => 'post-12' ];

        $this->assertSame( $base, TriggerContext::build( [], $base ) );
    }

    public function test_adds_size_when_set(): void
    {
        $context = TriggerContext::build( [ 'modalSize' => 'large' ], [ 'postId' => 1 ] );

        $this->assertSame( 'large', $context['size'] );
    }

    public function test_omits_empty_size(): void
    {
        $context = TriggerContext::build( [ 'modalSize' => '' ], [ 'postId' => 1 ] );

        $this->assertArrayNotHasKey( 'size', $context );
    }

    public function test_adds_template_part_when_set(): void
    {
        $context = TriggerContext::build( [], [ 'postId' => 1 ], 'promo' );

        $this->assertSame( 'promo', $context['templatePart'] );
    }

    public function test_omits_empty_template_part(): void
    {
        $context = TriggerContext::build( [], [ 'postId' => 1 ], '' );

        $this->assertArrayNotHasKey( 'templatePart', $context );
    }

    public function test_preserves_base_keys_alongside_options(): void
    {
        $context = TriggerContext::build(
            [ 'modalSize' => 'small' ],
            [ 'contentSource' => 'inline', 'inlineAnchor' => 'promo' ],
            'promo'
        );

        $this->assertSame( 'inline', $context['contentSource'] );
        $this->assertSame( 'promo', $context['inlineAnchor'] );
        $this->assertSame( 'small', $context['size'] );
        $this->assertSame( 'promo', $context['templatePart'] );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit tests/php/TriggerContextTest.php
```

Expected: FAIL — `Class "Pikari\GutenbergModals\TriggerContext" not found`.

- [ ] **Step 3: Write the class**

Create `includes/TriggerContext.php` (4 spaces, not tabs):

```php
<?php

/**
 * Interactivity API context for modal triggers.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\GutenbergModals;

/**
 * Builds the `data-wp-context` payload shared by every open-mode trigger.
 *
 * The four content-source branches in the Modal Trigger block's render.php
 * differ only in their base keys; the optional keys are identical. Keeping
 * them here means a new option is added once rather than four times.
 */
class TriggerContext
{
    /**
     * Build the Interactivity API context for an open-mode trigger.
     *
     * Empty options are omitted rather than emitted as empty strings, so the
     * serialized context stays as small as it was before this existed.
     *
     * @param array  $attributes    Block attributes.
     * @param array  $base          Branch-specific keys (postId, modalId, contentSource...).
     * @param string $template_part Template part slug, or '' for the default.
     * @return array The context array.
     */
    public static function build( array $attributes, array $base, string $template_part = '' ): array
    {
        $context = $base;

        $size = $attributes['modalSize'] ?? '';
        if ( ! empty( $size ) ) {
            $context['size'] = $size;
        }

        if ( ! empty( $template_part ) ) {
            $context['templatePart'] = $template_part;
        }

        return $context;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/phpunit tests/php/TriggerContextTest.php
```

Expected: PASS, 6 tests.

- [ ] **Step 5: Use it in all four branches**

In `src/blocks/modal-trigger/render.php`, add to the `use` statements:

```php
use Pikari\GutenbergModals\TriggerContext;
```

Then replace each of the four context blocks. **url/post branch** — replace lines 139-156 (`$context = [...]` through the `templatePart` block) with:

```php
        $base = [
            'postId'  => $content_id,
            'modalId' => $modal_id,
        ];

        // Mark external URLs so the frontend renders an iframe instead of fetching via REST API.
        if ( $content_type === 'url' ) {
            $base['contentSource'] = 'url';
        }

        $context = TriggerContext::build( $attributes, $base, $template_part );
```

**inline branch** — replace lines 194-206 with:

```php
        $context = TriggerContext::build(
            $attributes,
            [
                'contentSource' => 'inline',
                'inlineAnchor'  => $inline_anchor,
                'modalId'       => 'inline-' . $inline_anchor,
            ],
            $template_part
        );
```

Delete the now-unused `$modal_size = $attributes['modalSize'] ?? '';` line above it (line 189).

**post-link branch** — replace lines 299-312 with:

```php
        $context = TriggerContext::build(
            $attributes,
            [
                'postId'  => $post_id,
                'modalId' => 'post-' . $post_id,
            ],
            $template_part
        );
```

**url-link branch** — replace lines 384-401 with:

```php
        $base = [
            'postId'  => $content_id,
            'modalId' => $modal_id,
        ];

        if ( $content_type === 'url' ) {
            $base['contentSource'] = 'url';
        }

        $context = TriggerContext::build( $attributes, $base, $template_part );
```

- [ ] **Step 6: Verify nothing changed and everything passes**

```bash
composer test && npm run lint:php && npm run build
grep -n "modalSize" src/blocks/modal-trigger/render.php
```

Expected: all PHP tests pass, phpcs clean, and the `grep` returns **nothing** — every `modalSize` read now lives in `TriggerContext`.

- [ ] **Step 7: Commit**

```bash
git add includes/TriggerContext.php tests/php/TriggerContextTest.php src/blocks/modal-trigger/render.php
git commit -m "refactor: extract the modal trigger Interactivity context builder"
```

---

### Task 6: Panel widths filter and localized editor data

Panel widths get their own filter rather than parameterising `pikari_gutenberg_modals_modal_sizes`. The editor needs both lists at once, so parameterising would change the released `pikariGutenbergModals.modalSizes` from a flat array into a keyed object — a breaking change to localised data, to save a few lines.

**Files:**
- Modify: `includes/EditorIntegration.php` — the `wp_localize_script` array (around line 77) and a new private method beside `get_modal_sizes()` (line 111-139)

**Interfaces:**
- Consumes: nothing.
- Produces: `window.pikariGutenbergModals.panelWidths` — an array of `{ label, value }` with values `''`, `'narrow'`, `'wide'`; the `pikari_gutenberg_modals_panel_widths` filter. Task 7's editor UI reads it.

- [ ] **Step 1: Add the localized key**

In `includes/EditorIntegration.php`, in the `wp_localize_script` array, after the `'modalSizes'` line:

```php
                'panelWidths'        => $this->get_panel_widths(),
```

- [ ] **Step 2: Add the method**

Immediately after `get_modal_sizes()`:

```php
    /**
     * Get available panel widths for the editor.
     *
     * Panels measure a width, not a max-width, so they need their own list.
     * A sibling filter rather than a parameter on
     * `pikari_gutenberg_modals_modal_sizes`: the editor needs both lists at
     * once, and parameterising would turn the released
     * `pikariGutenbergModals.modalSizes` from a flat array into a keyed
     * object.
     *
     * Each entry has:
     * - `label` (string) Translated display label.
     * - `value` (string) Slug used as the `data-size` attribute value.
     *                     Empty string means default (`--modal-panel-width`).
     *
     * Custom widths require matching CSS, e.g.:
     * ```css
     * .modal-overlay[data-placement="right"][data-size="custom-slug"] .modal-content {
     *     width: 720px;
     * }
     * ```
     *
     * @return array<int, array{label: string, value: string}> Panel width options.
     */
    private function get_panel_widths(): array
    {
        $default_widths = [
            [
                'label' => __('Default', 'pikari-gutenberg-modals'),
                'value' => '',
            ],
            [
                'label' => __('Narrow', 'pikari-gutenberg-modals'),
                'value' => 'narrow',
            ],
            [
                'label' => __('Wide', 'pikari-gutenberg-modals'),
                'value' => 'wide',
            ],
        ];

        /**
         * Filters the available modal panel width options.
         *
         * @param array $widths Array of width options with 'label' and 'value' keys.
         */
        return apply_filters('pikari_gutenberg_modals_panel_widths', $default_widths);
    }
```

- [ ] **Step 3: Lint and commit**

```bash
npm run lint:php
git add includes/EditorIntegration.php
git commit -m "feat: add a panel widths filter for edge-placed modals"
```

---

### Task 7: Per-trigger placement override

The trigger already overrides `size`, so placement being overridable keeps the two consistent — the spec retains this rather than adding it as a new idea.

**Known limitation to record, not solve:** the size dropdown shows panel widths only when the *trigger's own* placement is left or right. A trigger that inherits an edge placement from the Modal Dialog block still shows the centered size list. Resolving that properly means the editor introspecting the template part's blocks, which is disproportionate; the store drops the mismatched slug at runtime anyway (Task 1), so the worst case is a control that does nothing.

**Files:**
- Modify: `src/blocks/modal-trigger/block.json` — add to `attributes` after `modalSize`
- Modify: `src/blocks/modal-trigger/edit.js` — size options constant (line 25-26), the InspectorControls block containing the size `SelectControl` (around line 478)
- Modify: `includes/TriggerContext.php` — `build()`
- Modify: `tests/php/TriggerContextTest.php` — add placement cases

**Interfaces:**
- Consumes: `panelWidths` from Task 6; `TriggerContext::build()` from Task 5.
- Produces: block attribute `modalPlacement`; the `placement` key in Interactivity context, which Task 4's store already reads.

- [ ] **Step 1: Write the failing PHP test**

Append to `tests/php/TriggerContextTest.php`:

```php
    public function test_adds_placement_when_valid(): void
    {
        $context = TriggerContext::build( [ 'modalPlacement' => 'right' ], [ 'postId' => 1 ] );

        $this->assertSame( 'right', $context['placement'] );
    }

    public function test_omits_empty_placement(): void
    {
        $context = TriggerContext::build( [ 'modalPlacement' => '' ], [ 'postId' => 1 ] );

        $this->assertArrayNotHasKey( 'placement', $context );
    }

    public function test_omits_unknown_placement(): void
    {
        $context = TriggerContext::build( [ 'modalPlacement' => 'top' ], [ 'postId' => 1 ] );

        $this->assertArrayNotHasKey( 'placement', $context );
    }
```

- [ ] **Step 2: Run it to verify it fails**

```bash
vendor/bin/phpunit tests/php/TriggerContextTest.php
```

Expected: FAIL on `test_adds_placement_when_valid` — undefined array key `placement`.

- [ ] **Step 3: Add placement to the builder**

In `includes/TriggerContext.php`, inside `build()`, after the size block:

```php
        // Only known placements travel. An unknown slug would reach the store
        // and be discarded there anyway; dropping it here keeps the context
        // honest about what it can express.
        $placement = $attributes['modalPlacement'] ?? '';
        if ( in_array( $placement, [ 'left', 'right' ], true ) ) {
            $context['placement'] = $placement;
        }
```

- [ ] **Step 4: Run the PHP tests**

```bash
composer test
```

Expected: PASS, including the 3 new cases.

- [ ] **Step 5: Add the block attribute**

In `src/blocks/modal-trigger/block.json`, after the `modalSize` entry:

```json
		"modalPlacement": {
			"type": "string",
			"default": ""
		},
```

- [ ] **Step 6: Add the editor controls**

In `src/blocks/modal-trigger/edit.js`, beside the existing `MODAL_SIZE_OPTIONS` constant, add:

```js
// Panel widths from PHP filter (pikari_gutenberg_modals_panel_widths)
const PANEL_WIDTH_OPTIONS = window.pikariGutenbergModals?.panelWidths || [
	{ label: 'Default', value: '' },
	{ label: 'Narrow', value: 'narrow' },
	{ label: 'Wide', value: 'wide' },
];

const PLACEMENT_OPTIONS = [
	{ label: 'Inherit from dialog', value: '' },
	{ label: 'Left edge', value: 'left' },
	{ label: 'Right edge', value: 'right' },
];
```

Add `modalPlacement` to the destructured attributes, then add a placement `SelectControl` immediately before the existing size control, and switch the size control's `options` to depend on it:

```jsx
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __(
										'Placement',
										'pikari-gutenberg-modals'
									) }
									value={ modalPlacement }
									options={ PLACEMENT_OPTIONS }
									onChange={ ( value ) =>
										setAttributes( {
											modalPlacement: value,
											modalSize: '',
										} )
									}
									help={ __(
										'Overrides the placement set on the Modal Dialog block.',
										'pikari-gutenberg-modals'
									) }
								/>
```

The size control's `options` becomes:

```jsx
									options={
										modalPlacement
											? PANEL_WIDTH_OPTIONS
											: MODAL_SIZE_OPTIONS
									}
```

Resetting `modalSize` when placement changes is deliberate: the two lists share no slugs, so a stale value would silently mean "default".

- [ ] **Step 7: Build, lint, test and commit**

```bash
npm run build && npm run lint:all && composer test && npm test
git add src/blocks/modal-trigger/block.json src/blocks/modal-trigger/edit.js includes/TriggerContext.php tests/php/TriggerContextTest.php
git commit -m "feat: let a modal trigger override the dialog placement"
```

---

### Task 8: Dialog aria-label from the trigger

**Diagnosis, already done — do not re-derive it.** The container carries both `aria-label="Modal dialog"` and `aria-labelledby="modal-title--{slug}"` (`includes/BlockSupport.php:866-868`). `aria-labelledby` wins whenever its target exists — but `#modal-title--{slug}` is the `<h2>` written into `.modal-body` *during content loading*, and `.modal-body` is empty at the moment the dialog opens. So at announcement time the reference is dangling, the name computation falls through to `aria-label`, and assistive tech says "Modal dialog". That is what was measured on the Kindler install for a trigger named "Watch the Talks".

The fix is to carry the trigger's accessible name — already computed in every branch of `modal-trigger/render.php` as `$aria_label` — into context, and set it on the container at open time.

**Files:**
- Modify: `includes/TriggerContext.php` — `build()` gains a `label` option
- Modify: `src/blocks/modal-trigger/render.php` — pass `'label' => $aria_label` in each of the four `$base` arrays
- Modify: `src/frontend/modal-store.js` — `openModal` sets it, `closeModal` restores it
- Modify: `tests/php/TriggerContextTest.php`
- Test: `tests/unit/frontend/modal-store-geometry.test.js` — add a label case

**Interfaces:**
- Consumes: `TriggerContext::build()` from Task 5.
- Produces: the `label` context key; `aria-label` on the container reflecting the trigger.

- [ ] **Step 1: Write the failing JS test**

Add to `tests/unit/frontend/modal-store-geometry.test.js`:

```js
	it( "labels the dialog with the trigger's accessible name", () => {
		const container = setUpContainer();
		container.setAttribute( 'aria-label', 'Modal dialog' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
			label: 'Watch the Talks',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'aria-label' ) ).toBe(
			'Watch the Talks'
		);
	} );

	it( 'leaves the generic label alone when the trigger supplies none', () => {
		const container = setUpContainer();
		container.setAttribute( 'aria-label', 'Modal dialog' );
		getContext.mockReturnValue( {
			contentSource: 'inline',
			inlineAnchor: 'promo',
		} );

		runOpen( actions );

		expect( container.getAttribute( 'aria-label' ) ).toBe( 'Modal dialog' );
	} );
```

- [ ] **Step 2: Run it to verify it fails**

```bash
npm test -- --testPathPattern=modal-store-geometry
```

Expected: FAIL on the first new test — the label stays "Modal dialog".

- [ ] **Step 3: Set and restore the label in the store**

Add a module-scope variable beside `activeContainer` and `closeTimeoutId` in `src/frontend/modal-store.js`:

```js
let previousAriaLabel = null;
```

Add `label` to the `openModal` context destructuring, and after the geometry block:

```js
			// The container's aria-labelledby points at a heading that does not
			// exist until content loads, so at announcement time the name falls
			// through to aria-label. Carry the trigger's own name across.
			if ( label ) {
				previousAriaLabel = modal.getAttribute( 'aria-label' );
				modal.setAttribute( 'aria-label', label );
			}
```

In the `closeModal` timeout, beside the `data-placement` removal:

```js
					if ( previousAriaLabel !== null ) {
						modal.setAttribute( 'aria-label', previousAriaLabel );
						previousAriaLabel = null;
					}
```

and the same restoration in the cancel-pending-close branch, beside its `data-placement` removal:

```js
					if ( previousAriaLabel !== null ) {
						activeContainer.setAttribute(
							'aria-label',
							previousAriaLabel
						);
						previousAriaLabel = null;
					}
```

- [ ] **Step 4: Carry the label from PHP**

The label is not a block attribute — it is computed per branch from the post title,
the inline anchor or the author's `accessibleLabel`. So it travels in `$base`, and
`TriggerContext::build()` needs no change at all for this task.

In `src/blocks/modal-trigger/render.php`, add `'label' => $aria_label,` to each of the
four `$base` arrays. The url/post branch becomes:

```php
        $base = [
            'postId'  => $content_id,
            'modalId' => $modal_id,
            'label'   => $aria_label,
        ];
```

The inline branch:

```php
        $context = TriggerContext::build(
            $attributes,
            [
                'contentSource' => 'inline',
                'inlineAnchor'  => $inline_anchor,
                'modalId'       => 'inline-' . $inline_anchor,
                'label'         => $aria_label,
            ],
            $template_part
        );
```

The post-link branch has no `$aria_label` of its own — it labels the wrapper with
`aria-labelledby="$trigger_id"` pointing at the matched link. Read that link's text
while the processor is on it (line 270-288, just after `$found_primary_link = true;`)
and carry it:

```php
                $link_text = trim( wp_strip_all_tags( $processor->get_modifiable_text() ) );
```

then:

```php
        $context = TriggerContext::build(
            $attributes,
            [
                'postId'  => $post_id,
                'modalId' => 'post-' . $post_id,
                'label'   => $link_text,
            ],
            $template_part
        );
```

The url-link branch takes the same treatment, capturing `$link_text` inside its own
`while ( $processor->next_tag( 'a' ) )` loop before the `break`.

Empty labels are dropped by the store's `if ( label )` guard, so a link with no text
falls back to today's generic name rather than blanking it.

- [ ] **Step 5: Run everything**

```bash
npm run build && npm run lint:all && composer test && npm test
```

Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add includes/TriggerContext.php src/blocks/modal-trigger/render.php src/frontend/modal-store.js tests/php/TriggerContextTest.php tests/unit/frontend/modal-store-geometry.test.js
git commit -m "fix: label the dialog with the trigger's accessible name"
```

---

### Task 9: Documentation

The Documentation Rule requires new customisation points in `CLAUDE.md` **and** `readme.txt` **and** `README.md`. `readme.txt` and `README.md` carry the same content in two formats and must stay in sync.

**Files:**
- Modify: `CLAUDE.md` — the "Custom Hooks & Filters" block and the Modal Container Pattern section
- Modify: `readme.txt` — Developer section
- Modify: `README.md` — the matching Developer section
- Modify: `_log/roadmap.md` — strike through the strand 3 entry

- [ ] **Step 1: Document the filter and custom properties in CLAUDE.md**

In the `## Custom Hooks & Filters` code block, under Editor:

```php
pikari_gutenberg_modals_panel_widths            // Add/modify panel width options for edge-placed modals
```

And add to the Key Design Patterns list:

```markdown
12. **Placement is container geometry** — The Modal Dialog block's `placement` attribute renders as `data-default-placement` on `.modal-content`; the store resolves it against a trigger override (`placement` in context) and writes the winner to `data-placement` on `.modal-overlay`. All geometry CSS keys off the overlay. Size is contextual: `small`/`large`/`fullscreen` when centered, `narrow`/`wide` on a panel, and a slug from the wrong list is dropped at open time. Panels square off `border-radius` with `!important`, following the fullscreen and mobile precedent; background, padding and shadow stay with the author's chrome Group.
```

- [ ] **Step 2: Document in readme.txt and README.md**

Add to the Developer section of both, in each file's own format:

```text
= Modal placement =

Set placement on the Modal Dialog block: Centered (default), Left edge or Right
edge. Edge placements pin the dialog full height at a panel width. A trigger can
override the dialog's placement.

Panel widths are set with CSS custom properties:

    --modal-panel-width:        420px;  /* default */
    --modal-panel-width-narrow: 320px;
    --modal-panel-width-wide:   600px;

The options offered in the editor come from a filter:

    add_filter( 'pikari_gutenberg_modals_panel_widths', function ( $widths ) {
        $widths[] = [ 'label' => 'Extra wide', 'value' => 'xwide' ];
        return $widths;
    } );

Custom slugs need matching CSS:

    .modal-overlay[data-placement="right"][data-size="xwide"] .modal-content {
        width: 720px;
    }
```

- [ ] **Step 3: Update the roadmap**

In `_log/roadmap.md`, move the strand 3 entry from Open to Released, struck through, on one line with its outcome — matching the existing entries' shape.

- [ ] **Step 4: Lint and commit**

```bash
npm run lint:md
git add CLAUDE.md readme.txt README.md _log/roadmap.md
git commit -m "docs: document modal placement and the panel widths filter"
```

---

## Verification before opening a PR

- [ ] `npm run lint:all && composer test && npm test` — all green, with counts recorded.
- [ ] `npm run build` has been run since the last `render.php` or block CSS edit.
- [ ] Browser pass in wp-env from this worktree (`npx wp-env start`, http://localhost:5888, admin/password). Author the test content **through the editor**, not by hand — see `_plans/testing-notes.md`. Measure, do not eyeball:
  - right panel pinned to the edge, full height, at the configured width;
  - a centered modal unchanged from `main`;
  - narrow viewport: panel goes full width;
  - `fullscreen` size on a trigger pointing at a panel dialog does **not** produce a full-width sheet;
  - focus trap, Escape, inert background, focus restored to the trigger;
  - the item never verified in the previous session: overlay controls and overlay opacity still work, under a theme with `settings.color.custom: false`.
- [ ] Version untouched in this branch — the release bump is a separate step via `node .github/bump-version.js`.
- [ ] PR targets `main`.
