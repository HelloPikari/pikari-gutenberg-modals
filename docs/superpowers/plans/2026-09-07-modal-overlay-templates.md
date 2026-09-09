# Modal Overlay Templates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every modal trigger a discoverable panel to select, create, edit, and preview a modal template part, and rename the `modal-dialog` block to `modal-overlay` so its name matches what it does.

**Architecture:** Replace the PHP-localized template-part array with the `@wordpress/core-data` entity store, so parts created mid-session appear immediately and their content can be previewed. All branching logic lives in a pure, Jest-covered module (`src/editor/modal-template-parts.js`); the React panel is thin wiring, browser-verified. Create seeds a new `wp_template_part` from a plugin-registered pattern scoped to `core/template-part/modal`.

**Tech Stack:** WordPress 6.8+, PHP 8.4+, `@wordpress/scripts` webpack, `@wordpress/core-data` entity store, Jest (jsdom), PHPUnit with Brain\Monkey.

**Spec:** `docs/superpowers/specs/2026-09-07-modal-overlay-templates-design.md`

## Global Constraints

- **Branch base:** this plan assumes `feature/modal-placement` has merged to `main` and the three `feature/simplify-modal-dialog-ux` commits have been rebased on top. Do not start Tasks 2–11 until Pikari todo #441 is closed. Work on a new branch `feature/modal-overlay-templates`.
- **Task 1 was run ahead of that gate, deliberately.** It writes no production code and touches nothing the placement QA depends on, so it could answer the design's open questions early. It is complete; its findings already changed Tasks 2, 5, and 8.
- **PHP:** 8.4+ (`composer.json` requires `>=8.4`; the plugin header says `Requires PHP: 8.4`). WordPress Coding Standards, **4 spaces indentation, not tabs**. Enforced by `phpcs.xml`.
- **JavaScript:** WordPress ESLint config, **tab indentation, not spaces**. Prettier ignores JS. When using Edit, `old_string` must preserve exact tab characters.
- **i18n:** every user-facing string uses `__()` / `sprintf()` with text domain `pikari-gutenberg-modals`.
- **Text domain is exactly:** `pikari-gutenberg-modals`
- **No new npm dependencies.** `@wordpress/data`, `core-data`, `components`, `block-editor`, `compose`, `html-entities`, `notices`, `url`, `blocks`, and `@testing-library/react` are NOT installed and must NOT be added. They are webpack externals available at build time only. Any module you want Jest to cover must import nothing from `@wordpress/*` except `@wordpress/i18n` (mapped to a mock in `jest.config.js`).
- **`render.php` lives in `build/`.** After editing any `render.php` or `block.json`, run `npm run build` or changes will not appear in wp-env.
- **Commit format:** `type: Brief description` (feat, fix, docs, style, refactor, test, chore). **No `Co-Authored-By` lines and no "Generated with Claude Code" attribution.**
- **Before any commit:** `npm run lint:all && composer test && npm test`
- **The JS code blocks in this plan have been reformatted by Prettier.** `lint-staged` runs `prettier --write` on `*.md`, and Prettier formats code inside fences even though `.prettierignore` excludes `*.js` files themselves. Tab indentation survived, but WordPress ESLint wants `{ __( 'x' ) }` where the fences now show `{__('x')}`. Paste the code as given, then run `npm run lint:fix` before committing. The logic is correct; only the spacing differs.

---

### Task 1: Verify the three unknowns — COMPLETE (2026-09-07)

Done. Do not repeat. Full findings and raw output: `_plans/modal-overlay-templates-verification.md` (commit `8fcb4b2`). Run against WordPress 7.1 with Twenty Twenty-Five.

- [x] **1. Synthetic default part surfaces through REST with content — PASS.** Returns `id: twentytwentyfive//modal`, `theme: twentytwentyfive`, `source: "plugin"`, `wp_id: 0`, `content.raw` of 1280 characters from `parts/modal.html`. Verified separately from the saved case, because a customised part already existed in the dev database. Spec §1 needs no change.

- [x] **2. Plugin patterns keep arbitrary `blockTypes` — PASS.** With one wrinkle now recorded in Task 7: REST emits snake_case `block_types`, the client store converts to camelCase `blockTypes`. `selectModalPatterns()` is correct as specified.

- [x] **3. `onNavigateToEntityRecord` absent in the post editor — REFUTED.** It is `typeof "function"` there on 7.1. This killed the hand-built Site Editor URL fallback: spec §4 was rewritten, and `buildEditUrl()` plus its four unit tests were removed from Task 2, `siteEditorUrl` from Task 5, and the fallback branch from Task 8.

**Method note for anyone repeating this on another environment.** Do not add a temporary `register_block_pattern()` call to `pikari-gutenberg-modals.php` to probe pattern registration, and do not delete a customised template part to expose the synthetic one. Both mutate what may be a live QA environment. Register the probe in-process instead, and drive REST internally, so nothing persists:

```bash
npx wp-env run tests-cli wp eval '
wp_set_current_user( 1 );
register_block_pattern( "probe/x", [
    "title" => "Probe", "content" => "<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->",
    "blockTypes" => [ "core/template-part/modal" ],
] );
$res = rest_do_request( new WP_REST_Request( "GET", "/wp/v2/block-patterns/patterns" ) );
// ... inspect $res->get_data()
'
```

The `tests-cli` environment has its own database, so it shows the uncustomised state without touching the dev site.

**Unresolved, and only relevant if the URL fallback is ever revived:** the verbatim Site Editor URL for a template part was not captured. The guessed `?p=…&canvas=edit` shape loaded the shell with no canvas, and the SPA routes via `history.pushState` rather than anchors. Core registers `wp_template_part` with `'_edit_link' => '/site-editor.php?canvas=edit'` (`wp-includes/post.php:503`), which has no id placeholder — there is no stable public URL contract to build against.

### Task 2: Pure template-part logic module

**Files:**

- Create: `src/editor/modal-template-parts.js`
- Test: `tests/unit/editor/modal-template-parts.test.js`

**Interfaces:**

- Consumes: nothing. Imports only `__` and `sprintf` from `@wordpress/i18n`.
- Produces, all named exports:

  - `MODAL_TEMPLATE_PART_AREA` — string `'modal'`
  - `MODAL_PATTERN_BLOCK_TYPE` — string `'core/template-part/modal'`
  - `DEFAULT_MODAL_SLUG` — string `'modal'`
  - `filterModalParts( records: Array|null ) => Array`
  - `getPartTitle( part: Object ) => string`
  - `buildPartOptions( { parts, selectedSlug, hasResolved, isResolving } ) => Array<{ label: string, value: string }>`
  - `getUniqueTitle( base: string, parts: Array ) => string`
  - `getCleanSlug( title: string ) => string`
  - `createTemplatePartId( theme: string, slug: string ) => string|null`
  - `selectModalPatterns( patterns: Array|null ) => Array`

- [ ] **Step 1: Write the failing test**

Create `tests/unit/editor/modal-template-parts.test.js`:

```js
/**
 * Tests for the pure modal template part helpers.
 *
 * @see src/editor/modal-template-parts.js
 */

import {
	filterModalParts,
	getPartTitle,
	buildPartOptions,
	getUniqueTitle,
	getCleanSlug,
	createTemplatePartId,
	selectModalPatterns,
	MODAL_PATTERN_BLOCK_TYPE,
} from '../../../src/editor/modal-template-parts';

const part = (slug, title, area = 'modal') => ({
	slug,
	area,
	title: { rendered: title },
});

describe('filterModalParts', () => {
	it('returns an empty array for null', () => {
		expect(filterModalParts(null)).toEqual([]);
	});

	it('returns an empty array for a non-array', () => {
		expect(filterModalParts(undefined)).toEqual([]);
	});

	it('keeps only parts in the modal area', () => {
		const records = [
			part('modal', 'Modal'),
			part('header', 'Header', 'header'),
			part('sidebar', 'Sidebar'),
		];
		expect(filterModalParts(records).map((p) => p.slug)).toEqual([
			'modal',
			'sidebar',
		]);
	});

	it('tolerates records without an area', () => {
		expect(filterModalParts([{ slug: 'x' }])).toEqual([]);
	});
});

describe('getPartTitle', () => {
	it('prefers the rendered title', () => {
		expect(getPartTitle({ slug: 's', title: { rendered: 'R' } })).toBe('R');
	});

	it('falls back to the raw title', () => {
		expect(getPartTitle({ slug: 's', title: { raw: 'W' } })).toBe('W');
	});

	it('falls back to the slug when there is no title', () => {
		expect(getPartTitle({ slug: 's' })).toBe('s');
	});
});

describe('buildPartOptions', () => {
	const resolved = { hasResolved: true, isResolving: false };

	it('returns only the default option while resolving', () => {
		expect(
			buildPartOptions({
				parts: [part('sidebar', 'Sidebar')],
				selectedSlug: '',
				hasResolved: false,
				isResolving: true,
			})
		).toEqual([{ label: 'Default', value: '' }]);
	});

	it('maps the modal slug to the default option rather than duplicating it', () => {
		const options = buildPartOptions({
			parts: [part('modal', 'Modal'), part('sidebar', 'Sidebar')],
			selectedSlug: '',
			...resolved,
		});
		expect(options).toEqual([
			{ label: 'Default', value: '' },
			{ label: 'Sidebar', value: 'sidebar' },
		]);
	});

	it('adds a default option when no modal-slug part exists', () => {
		const options = buildPartOptions({
			parts: [part('sidebar', 'Sidebar')],
			selectedSlug: '',
			...resolved,
		});
		expect(options[0]).toEqual({ label: 'Default', value: '' });
		expect(options).toHaveLength(2);
	});

	it('puts the default option first regardless of record order', () => {
		const options = buildPartOptions({
			parts: [part('sidebar', 'Sidebar'), part('modal', 'Modal')],
			selectedSlug: '',
			...resolved,
		});
		expect(options[0].value).toBe('');
	});

	it('adds a missing entry for a selection that no longer resolves', () => {
		const options = buildPartOptions({
			parts: [part('modal', 'Modal')],
			selectedSlug: 'deleted-part',
			...resolved,
		});
		expect(options).toEqual([
			{ label: 'Default', value: '' },
			{ label: 'deleted-part (missing)', value: 'deleted-part' },
		]);
	});

	it('does not add a missing entry for the default selection', () => {
		const options = buildPartOptions({
			parts: [part('modal', 'Modal')],
			selectedSlug: '',
			...resolved,
		});
		expect(options).toEqual([{ label: 'Default', value: '' }]);
	});

	it('does not add a missing entry when the selection resolves', () => {
		const options = buildPartOptions({
			parts: [part('modal', 'Modal'), part('sidebar', 'Sidebar')],
			selectedSlug: 'sidebar',
			...resolved,
		});
		expect(options).toHaveLength(2);
	});

	it('handles a null parts list', () => {
		expect(
			buildPartOptions({ parts: null, selectedSlug: '', ...resolved })
		).toEqual([{ label: 'Default', value: '' }]);
	});
});

describe('getUniqueTitle', () => {
	it('returns the base when nothing collides', () => {
		expect(getUniqueTitle('Modal', [])).toBe('Modal');
	});

	it('appends 2 on the first collision', () => {
		expect(getUniqueTitle('Modal', [part('modal', 'Modal')])).toBe('Modal 2');
	});

	it('skips over existing numbered titles', () => {
		const parts = [
			part('a', 'Modal'),
			part('b', 'Modal 2'),
			part('c', 'Modal 3'),
		];
		expect(getUniqueTitle('Modal', parts)).toBe('Modal 4');
	});

	it('ignores parts without titles', () => {
		expect(getUniqueTitle('Modal', [{ slug: 'x' }])).toBe('Modal');
	});
});

describe('getCleanSlug', () => {
	it('lowercases and hyphenates', () => {
		expect(getCleanSlug('Booking Sidebar')).toBe('booking-sidebar');
	});

	it('strips punctuation', () => {
		expect(getCleanSlug("Steve's Modal!")).toBe('steve-s-modal');
	});

	it('trims leading and trailing separators', () => {
		expect(getCleanSlug('  --Modal--  ')).toBe('modal');
	});

	it('returns an empty string for empty input', () => {
		expect(getCleanSlug('')).toBe('');
	});
});

describe('createTemplatePartId', () => {
	it('joins theme and slug with a double slash', () => {
		expect(createTemplatePartId('twentytwentyfive', 'sidebar')).toBe(
			'twentytwentyfive//sidebar'
		);
	});

	it('returns null without a theme', () => {
		expect(createTemplatePartId('', 'sidebar')).toBeNull();
	});

	it('returns null without a slug', () => {
		expect(createTemplatePartId('theme', '')).toBeNull();
	});
});

describe('selectModalPatterns', () => {
	it('returns an empty array for null', () => {
		expect(selectModalPatterns(null)).toEqual([]);
	});

	it('keeps only patterns scoped to the modal template part area', () => {
		const patterns = [
			{ name: 'a', blockTypes: [MODAL_PATTERN_BLOCK_TYPE] },
			{ name: 'b', blockTypes: ['core/template-part/header'] },
			{ name: 'c' },
		];
		expect(selectModalPatterns(patterns).map((p) => p.name)).toEqual(['a']);
	});
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
npm test -- --testPathPattern=modal-template-parts
```

Expected: FAIL — `Cannot find module '../../../src/editor/modal-template-parts'`.

- [ ] **Step 3: Write the implementation**

Create `src/editor/modal-template-parts.js`. **Tab indentation.**

```js
/**
 * Pure helpers for modal template parts.
 *
 * Everything in this module is deliberately free of `@wordpress/*` imports
 * except `@wordpress/i18n`, which is mapped to a mock in jest.config.js.
 * The editor packages this feature uses (core-data, components, block-editor)
 * are webpack externals and are NOT installed, so they cannot be resolved by
 * Jest. Keeping the decisions here means they stay testable.
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * Template part area this plugin registers.
 */
export const MODAL_TEMPLATE_PART_AREA = 'modal';

/**
 * Block type used to scope starter patterns to the modal area.
 */
export const MODAL_PATTERN_BLOCK_TYPE = 'core/template-part/modal';

/**
 * Slug of the default modal template part.
 *
 * Triggers store an empty string to mean "the default", so this slug is
 * mapped to '' in the options list rather than offered twice.
 */
export const DEFAULT_MODAL_SLUG = 'modal';

/**
 * Keep only template part records belonging to the modal area.
 *
 * @param {Array|null} records Entity records, or null while unresolved.
 * @return {Array} Modal template parts.
 */
export function filterModalParts(records) {
	if (!Array.isArray(records)) {
		return [];
	}

	return records.filter((record) => record?.area === MODAL_TEMPLATE_PART_AREA);
}

/**
 * Human label for a template part.
 *
 * @param {Object} part Template part record.
 * @return {string} Title, falling back to the slug.
 */
export function getPartTitle(part) {
	return part?.title?.rendered || part?.title?.raw || part?.slug || '';
}

/**
 * Build SelectControl options for the modal template panel.
 *
 * @param {Object}     options              Options.
 * @param {Array|null} options.parts        Modal template parts.
 * @param {string}     options.selectedSlug Currently stored slug ('' = default).
 * @param {boolean}    options.hasResolved  Whether the entity query resolved.
 * @param {boolean}    options.isResolving  Whether the entity query is in flight.
 * @return {Array<{label: string, value: string}>} Options.
 */
export function buildPartOptions({
	parts,
	selectedSlug,
	hasResolved,
	isResolving,
}) {
	const defaultOption = {
		label: __('Default', 'pikari-gutenberg-modals'),
		value: '',
	};

	if (!hasResolved || isResolving) {
		return [defaultOption];
	}

	const list = Array.isArray(parts) ? parts : [];

	const options = list.map((part) =>
		part.slug === DEFAULT_MODAL_SLUG
			? defaultOption
			: { label: getPartTitle(part), value: part.slug }
	);

	if (!options.some((option) => option.value === '')) {
		options.unshift(defaultOption);
	}

	options.sort((a, b) => {
		if (a.value === '') {
			return -1;
		}
		if (b.value === '') {
			return 1;
		}
		return 0;
	});

	// A slug that no longer resolves is surfaced rather than silently
	// discarded, so the user can see what was lost and choose a replacement.
	const isMissing =
		selectedSlug && !list.some((part) => part.slug === selectedSlug);

	if (isMissing) {
		options.splice(1, 0, {
			label: sprintf(
				/* translators: %s: template part slug. */
				__('%s (missing)', 'pikari-gutenberg-modals'),
				selectedSlug
			),
			value: selectedSlug,
		});
	}

	return options;
}

/**
 * Produce a title that does not collide with existing parts.
 *
 * @param {string} base  Desired title.
 * @param {Array}  parts Existing modal template parts.
 * @return {string} Unique title.
 */
export function getUniqueTitle(base, parts) {
	const existing = new Set(
		(Array.isArray(parts) ? parts : [])
			.map((part) => part?.title?.rendered || part?.title?.raw || '')
			.filter(Boolean)
	);

	if (!existing.has(base)) {
		return base;
	}

	let suffix = 2;
	while (existing.has(`${base} ${suffix}`)) {
		suffix++;
	}

	return `${base} ${suffix}`;
}

/**
 * Convert a title into a URL-safe template part slug.
 *
 * @param {string} title Title.
 * @return {string} Slug.
 */
export function getCleanSlug(title) {
	return (title || '')
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
}

/**
 * Build the entity id WordPress uses for template parts.
 *
 * @param {string} theme Theme stylesheet.
 * @param {string} slug  Template part slug.
 * @return {string|null} Id in `theme//slug` form, or null if either is missing.
 */
export function createTemplatePartId(theme, slug) {
	if (!theme || !slug) {
		return null;
	}

	return `${theme}//${slug}`;
}

/**
 * Keep only patterns registered as modal template part starters.
 *
 * @param {Array|null} patterns All registered block patterns.
 * @return {Array} Modal starter patterns.
 */
export function selectModalPatterns(patterns) {
	if (!Array.isArray(patterns)) {
		return [];
	}

	return patterns.filter((pattern) =>
		pattern?.blockTypes?.includes(MODAL_PATTERN_BLOCK_TYPE)
	);
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
npm test -- --testPathPattern=modal-template-parts
```

Expected: PASS, all suites green.

- [ ] **Step 5: Lint and commit**

```bash
npm run lint:js
git add src/editor/modal-template-parts.js tests/unit/editor/modal-template-parts.test.js
git commit -m "feat: add pure modal template part helpers"
```

---

### Task 3: Rename modal-dialog to modal-overlay

Mechanical but wide. Do it in one commit so the tree is never half-renamed.

**Files:**

- Rename: `src/blocks/modal-dialog/` → `src/blocks/modal-overlay/`
- Modify: `src/blocks/modal-overlay/block.json`, `style.css`, `editor.css`, `edit.js`, `render.php`
- Modify: `src/blocks/close-button/block.json:10`, `src/blocks/content-area/block.json:10`
- Modify: `pikari-gutenberg-modals.php:77`, `webpack.config.js:22`
- Modify: `includes/EditorIntegration.php:329`, `includes/BlockSupport.php:132`
- Modify: `parts/modal.html`
- Test: `tests/php/EditorIntegrationTest.php`

**Interfaces:**

- Consumes: nothing
- Produces: block name `pikari-gutenberg-modals/modal-overlay`, CSS class `wp-block-pikari-gutenberg-modals-modal-overlay`, style handle `pikari-gutenberg-modals-modal-overlay-style`

- [ ] **Step 1: Write the failing test**

Create `tests/php/EditorIntegrationTest.php`:

```php
<?php
/**
 * Tests for EditorIntegration.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\EditorIntegration;

class EditorIntegrationTest extends TestCase
{
    public function test_restrict_modal_template_blocks_removes_modal_overlay_from_post_editor(): void
    {
        $integration = new EditorIntegration();

        $context       = new \stdClass();
        $context->name = 'core/edit-post';

        $allowed = [
            'core/paragraph',
            'pikari-gutenberg-modals/modal-overlay',
            'pikari-gutenberg-modals/modal-trigger',
        ];

        $result = $integration->restrict_modal_template_blocks( $allowed, $context );

        $this->assertNotContains( 'pikari-gutenberg-modals/modal-overlay', $result );
        $this->assertContains( 'core/paragraph', $result );
        $this->assertContains( 'pikari-gutenberg-modals/modal-trigger', $result );
    }

    public function test_restrict_modal_template_blocks_leaves_site_editor_untouched(): void
    {
        $integration = new EditorIntegration();

        $context       = new \stdClass();
        $context->name = 'core/edit-site';

        $allowed = [ 'pikari-gutenberg-modals/modal-overlay' ];

        $this->assertSame(
            $allowed,
            $integration->restrict_modal_template_blocks( $allowed, $context )
        );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit tests/php/EditorIntegrationTest.php --testdox
```

Expected: FAIL on the first test — `modal-overlay` is still present in the result because the allowlist names `modal-dialog`.

- [ ] **Step 3: Perform the rename**

```bash
git mv src/blocks/modal-dialog src/blocks/modal-overlay

grep -rl "modal-dialog" \
  --exclude-dir=build --exclude-dir=node_modules --exclude-dir=vendor \
  --exclude-dir=.git --exclude-dir=docs --exclude-dir=_plans \
  --exclude=CHANGELOG.md \
  . | xargs sed -i '' 's/modal-dialog/modal-overlay/g'
```

`_plans/` and `CHANGELOG.md` are excluded deliberately. `_plans/` holds recorded
history — including Task 1's verification findings, which quote the old block name
verbatim — and rewriting a released changelog would falsify it. Task 11 adds the
correct changelog entry.

Then fix by hand what `sed` cannot know:

1. `src/blocks/modal-overlay/block.json` — set `"title": "Modal Overlay"` and update `description` to: `"The modal backdrop. Controls the overlay appearance (color, gradient, image) and where the dialog sits within it."`
2. `src/blocks/modal-overlay/block.json` — delete the `color`, `__experimentalBorder`, `spacing`, and `shadow` entries from `supports`, leaving only `"html": false`, `"multiple": false`, `"reusable": false`. **Keep every attribute except `style`.** In particular `overlayOpacity` (added by PR #104) is an overlay concern and must survive; `style` existed only so the editor could detect legacy chrome styling, so it goes with the deprecation notice in step 3.

   **The Overlay control survives this — verified, not assumed.** Removing `color`
   support does not remove the Color panel. `InspectorControlsSlot`
   (`block-editor/src/components/inspector-controls/slot.jsx`) gates the panel on
   whether the slot has fills, not on block supports:

   ```js
   const fills = useSlotFills( slotFill?.name );
   if ( ! fills?.length ) { return null; }
   if ( label ) { return <BlockSupportToolsPanel group={ group } label={ label }>…; }
   ```

   `StylesTab` renders `<InspectorControls.Slot group="color" label="Color" />`
   unconditionally for non-section blocks, and the block's own
   `ColorGradientSettingsDropdown` is a fill. Confirm visually anyway in step 6 — if
   the Overlay swatch ever vanished, the modal backdrop would become unstylable with
   no error to say why.

3. `src/blocks/modal-overlay/edit.js` — remove the `hasLegacyChromeStyles` constant and the entire `<InspectorControls>` block that renders the deprecation `Notice`, plus the now-unused `Notice` import. The notice existed to migrate chrome styling off this block; with the supports gone there is nothing left to warn about.
4. `readme.txt:188` and `README.md:190` — the sed pass already updated the filter example. Confirm both files still match each other exactly.

- [ ] **Step 4: Verify nothing was missed**

```bash
grep -rn "modal-dialog\|modalDialog\|modal_dialog" \
  --exclude-dir=build --exclude-dir=node_modules --exclude-dir=vendor \
  --exclude-dir=.git --exclude-dir=docs --exclude-dir=_plans \
  --exclude=CHANGELOG.md .
```

Expected: no output. Any hit outside the excluded paths is a miss — fix it before
continuing.

- [ ] **Step 5: Build and run the tests**

```bash
npm run build
vendor/bin/phpunit tests/php/EditorIntegrationTest.php --testdox
composer test
npm test
```

Expected: all PASS. The build must succeed — a stale `webpack.config.js` entry path is the most likely failure.

- [ ] **Step 6: Verify in the browser**

Open the Site Editor, edit the Modal template part. Check all four:

1. The block is titled **Modal Overlay** in list view.
2. The **Color** panel is still present with the **Overlay** swatch in it, and setting a
   colour still works. This is the one that would fail silently — see the note in step 3.
3. The **Overlay** panel still holds Opacity, image, focal point and parallax, and the
   opacity slider renders at full width rather than squashed.
4. No Background, Dimensions or Border & Shadow panels remain, and Close Button plus
   Content Area are still insertable inside the block — a stale `ancestor` array in
   either `block.json` makes them silently uninsertable.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor: rename modal-dialog block to modal-overlay"
```

---

### Task 4: Register modal starter patterns

**Files:**

- Create: `includes/ModalPatterns.php`
- Create: `patterns/modal-centered.php`, `patterns/modal-panel-right.php`, `patterns/modal-panel-left.php`
- Modify: `pikari-gutenberg-modals.php` (register the class alongside the other initialisations, around line 88)
- Test: `tests/php/ModalPatternsTest.php`

**Interfaces:**

- Consumes: block name `pikari-gutenberg-modals/modal-overlay` from Task 3
- Produces: three registered patterns named `pikari-gutenberg-modals/modal-centered`, `pikari-gutenberg-modals/modal-panel-right`, `pikari-gutenberg-modals/modal-panel-left`, each with `blockTypes` containing `core/template-part/modal`

- [ ] **Step 1: Write the failing test**

Create `tests/php/ModalPatternsTest.php`:

```php
<?php
/**
 * Tests for ModalPatterns.
 *
 * @package PikariGutenbergModals
 */

namespace Pikari\Tests\GutenbergModals;

use Pikari\Tests\TestCase;
use Pikari\GutenbergModals\ModalPatterns;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

class ModalPatternsTest extends TestCase
{
    public function test_constructor_registers_init_hook(): void
    {
        Actions\expectAdded( 'init' )->once();

        new ModalPatterns();
    }

    public function test_register_patterns_registers_three_modal_starters(): void
    {
        Functions\stubTranslationFunctions();

        $registered = [];

        Functions\when( 'register_block_pattern' )->alias(
            function ( $name, $properties ) use ( &$registered ) {
                $registered[ $name ] = $properties;
                return true;
            }
        );

        ( new ModalPatterns() )->register_patterns();

        $this->assertCount( 3, $registered );

        foreach ( ModalPatterns::PATTERN_SLUGS as $slug ) {
            $name = 'pikari-gutenberg-modals/' . $slug;

            $this->assertArrayHasKey( $name, $registered );
            $this->assertContains(
                'core/template-part/modal',
                $registered[ $name ]['blockTypes']
            );
            $this->assertNotEmpty( $registered[ $name ]['content'] );
        }
    }

    public function test_every_pattern_contains_a_close_trigger_and_content_area(): void
    {
        Functions\stubTranslationFunctions();

        $registered = [];

        Functions\when( 'register_block_pattern' )->alias(
            function ( $name, $properties ) use ( &$registered ) {
                $registered[ $name ] = $properties;
                return true;
            }
        );

        ( new ModalPatterns() )->register_patterns();

        foreach ( $registered as $name => $properties ) {
            $this->assertStringContainsString(
                '"triggerAction":"close"',
                $properties['content'],
                $name . ' is missing a close trigger'
            );
            $this->assertStringContainsString(
                'pikari-gutenberg-modals/content-area',
                $properties['content'],
                $name . ' is missing a content area'
            );
        }
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit tests/php/ModalPatternsTest.php --testdox
```

Expected: FAIL — class `Pikari\GutenbergModals\ModalPatterns` not found.

- [ ] **Step 3: Write the pattern files**

Create `patterns/modal-centered.php`:

```php
<?php
/**
 * Centered dialog modal pattern.
 *
 * @package PikariGutenbergModals
 */

?>
<!-- wp:pikari-gutenberg-modals/modal-overlay -->
<div class="wp-block-pikari-gutenberg-modals-modal-overlay"><!-- wp:group {"className":"modal-chrome","style":{"color":{"background":"#ffffff"},"border":{"radius":"20px"},"spacing":{"padding":{"top":"1.5rem","right":"1.5rem","bottom":"1.5rem","left":"1.5rem"}},"shadow":"0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06)"},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group modal-chrome has-background" style="border-radius:20px;background-color:#ffffff;padding-top:1.5rem;padding-right:1.5rem;padding-bottom:1.5rem;padding-left:1.5rem;box-shadow:0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06)"><!-- wp:group {"layout":{"type":"flex","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:pikari-gutenberg-modals/modal-trigger {"triggerAction":"close"} -->
<div class="wp-block-pikari-gutenberg-modals-modal-trigger"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button"><?php echo esc_html__( 'Close', 'pikari-gutenberg-modals' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:pikari-gutenberg-modals/modal-trigger --></div>
<!-- /wp:group -->

<!-- wp:pikari-gutenberg-modals/content-area /--></div>
<!-- /wp:group --></div>
<!-- /wp:pikari-gutenberg-modals/modal-overlay -->
```

Create `patterns/modal-panel-right.php` as an exact copy of the above with two changes: the opening comment becomes `<!-- wp:pikari-gutenberg-modals/modal-overlay {"placement":"right"} -->`, and the chrome Group's `"border":{"radius":"20px"}` and the matching `border-radius:20px;` inline style are removed (an edge-pinned panel squares off against the viewport edge — see spec §5 and `modal-overlay/style.css`).

Create `patterns/modal-panel-left.php` identically, with `{"placement":"left"}`.

- [ ] **Step 4: Write the registration class**

Create `includes/ModalPatterns.php`. **4 spaces, not tabs.**

```php
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
     */
    public function __construct()
    {
        add_action('init', [ $this, 'register_patterns' ]);
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
```

- [ ] **Step 5: Register the class**

In `pikari-gutenberg-modals.php`, after `new \Pikari\GutenbergModals\ModalTemplatePart();`:

```php
    new \Pikari\GutenbergModals\ModalPatterns();
```

- [ ] **Step 6: Run the tests**

```bash
vendor/bin/phpunit tests/php/ModalPatternsTest.php --testdox
composer lint
```

Expected: PASS, and phpcs clean.

- [ ] **Step 7: Verify in the browser**

In the post editor console:

```js
wp.data
	.select('core')
	.getBlockPatterns()
	.filter((p) => p.blockTypes?.includes('core/template-part/modal'))
	.map((p) => p.title);
```

Expected: `[ 'Centered dialog', 'Right panel', 'Left panel' ]`.

- [ ] **Step 8: Commit**

```bash
git add includes/ModalPatterns.php patterns/ pikari-gutenberg-modals.php tests/php/ModalPatternsTest.php
git commit -m "feat: register modal starter patterns for the modal template part area"
```

---

### Task 5: Localize isBlockTheme

**Files:**

- Modify: `includes/EditorIntegration.php` — the `wp_localize_script` array in `enqueue_editor_scripts()`
- Test: `tests/php/EditorIntegrationTest.php` (extend the file created in Task 3)

**Interfaces:**

- Consumes: nothing
- Produces: `window.pikariGutenbergModals.isBlockTheme` (boolean), consumed by Task 6

- [ ] **Step 1: Write the failing test**

Add to `tests/php/EditorIntegrationTest.php`:

```php
    public function test_get_editor_config_reports_block_theme(): void
    {
        Functions\when( 'wp_is_block_theme' )->justReturn( true );

        $this->assertTrue(
            ( new EditorIntegration() )->get_editor_config()['isBlockTheme']
        );
    }

    public function test_get_editor_config_reports_hybrid_theme(): void
    {
        Functions\when( 'wp_is_block_theme' )->justReturn( false );

        $this->assertFalse(
            ( new EditorIntegration() )->get_editor_config()['isBlockTheme']
        );
    }
```

Add `use Brain\Monkey\Functions;` to the file's imports if Task 3 did not already.

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/phpunit tests/php/EditorIntegrationTest.php --testdox
```

Expected: FAIL — `Call to undefined method … ::get_editor_config()`.

- [ ] **Step 3: Extract and extend the config**

In `includes/EditorIntegration.php`, replace the inline array passed to `wp_localize_script` with a call to a new public method, and add that method. This makes the config testable without invoking the enqueue path.

```php
        wp_localize_script(
            'pikari-gutenberg-modals-editor',
            'pikariGutenbergModals',
            $this->get_editor_config()
        );
```

```php
    /**
     * Build the config object localized to the editor script.
     *
     * `isBlockTheme` drives the Modal template panel's degraded mode: hybrid
     * themes have no wp_template_part entities and no Site Editor, so the
     * panel falls back to a plain select over `modalTemplateParts`.
     *
     * @return array Editor configuration.
     */
    public function get_editor_config(): array
    {
        if ( ! isset( $this->block_support ) ) {
            $this->block_support = new BlockSupport();
        }

        return [
            'supportedBlocks'    => $this->block_support->get_supported_blocks_for_js(),
            'restUrl'            => rest_url('pikari-gutenberg-modals/v1/'),
            'nonce'              => wp_create_nonce('wp_rest'),
            'modalSizes'         => $this->get_modal_sizes(),
            'modalTemplateParts' => $this->get_modal_template_parts(),
            'isBlockTheme'       => wp_is_block_theme(),
            'defaultSettings'    => [
                'size' => 'medium',
                'animation' => 'fade',
                'closeOnClickOutside' => true,
                'showCloseButton' => true,
                'overlayOpacity' => 0.8,
            ],
        ];
    }
```

Remove the now-duplicated `$this->block_support` initialisation and the inline array from `enqueue_editor_scripts()`.

- [ ] **Step 4: Run the tests**

```bash
vendor/bin/phpunit tests/php/EditorIntegrationTest.php --testdox
composer test
```

Expected: PASS. If the existing tests mock `get_supported_blocks_for_js`, `rest_url`, `wp_create_nonce`, or the size/template-part helpers, add the matching `Functions\when()` stubs to the two new tests.

- [ ] **Step 5: Commit**

```bash
npm run lint:php
git add includes/EditorIntegration.php tests/php/EditorIntegrationTest.php
git commit -m "feat: localize isBlockTheme for the editor"
```

---

### Task 6: Modal template panel — data and the two states

**Files:**

- Create: `src/editor/modal-template-panel.js`
- Create: `src/editor/use-modal-template-entities.js`
- Modify: `src/editor/style.scss` (panel styles)

**Interfaces:**

- Consumes: everything from `src/editor/modal-template-parts.js` (Task 2), `window.pikariGutenbergModals.isBlockTheme` (Task 5)
- Produces:
  - `useModalTemplateEntities()` returning `{ parts, options, isResolving, hasResolved, currentTheme, isBlockTheme, selectedPart }` — takes `selectedSlug` as its only argument
  - `<ModalTemplatePanel value onChange showCreate showPreview />` — default export of `modal-template-panel.js`. `value` is the slug (`''` = default), `onChange` receives the new slug. `showCreate` and `showPreview` default to `true`; the inline format surface passes `false` for both.

This task builds the select and the two states only. Create is stubbed, Edit and preview arrive in Tasks 7–9.

- [ ] **Step 1: Write the entity hook**

Create `src/editor/use-modal-template-entities.js`. **Tab indentation.**

```js
/**
 * Reads modal template parts from the core entity store.
 *
 * Replaces the previous localized-array approach: entities update the moment
 * a part is created, and they carry content, which the preview needs. The
 * localized array survives only for hybrid themes, which have no
 * wp_template_part entities at all.
 */

import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useEntityRecords, store as coreStore } from '@wordpress/core-data';
import {
	filterModalParts,
	buildPartOptions,
	getPartTitle,
} from './modal-template-parts';

export default function useModalTemplateEntities(selectedSlug) {
	const isBlockTheme = Boolean(window.pikariGutenbergModals?.isBlockTheme);

	const { records, isResolving, hasResolved } = useEntityRecords(
		'postType',
		'wp_template_part',
		{ per_page: -1 },
		{ enabled: isBlockTheme }
	);

	const currentTheme = useSelect(
		(select) => select(coreStore).getCurrentTheme()?.stylesheet,
		[]
	);

	const parts = useMemo(() => {
		if (!isBlockTheme) {
			// Hybrid themes: shape the localized array like entity records so
			// the rest of the panel does not need a second code path.
			return (window.pikariGutenbergModals?.modalTemplateParts || []).map(
				(part) => ({
					slug: part.slug,
					area: 'modal',
					title: { rendered: part.title },
				})
			);
		}

		return filterModalParts(records);
	}, [isBlockTheme, records]);

	const options = useMemo(
		() =>
			buildPartOptions({
				parts,
				selectedSlug,
				hasResolved: isBlockTheme ? hasResolved : true,
				isResolving: isBlockTheme ? isResolving : false,
			}),
		[parts, selectedSlug, hasResolved, isResolving, isBlockTheme]
	);

	const selectedPart = useMemo(
		() => parts.find((part) => part.slug === selectedSlug) || null,
		[parts, selectedSlug]
	);

	return {
		parts,
		options,
		selectedPart,
		currentTheme,
		isBlockTheme,
		isResolving: isBlockTheme ? isResolving : false,
		hasResolved: isBlockTheme ? hasResolved : true,
		getPartTitle,
	};
}
```

If `useEntityRecords` in the installed WordPress does not accept a fourth options argument with `enabled`, drop that argument and guard the call by returning early from the `parts` memo instead. Verify against the WordPress version in wp-env before assuming.

- [ ] **Step 2: Write the panel component**

Create `src/editor/modal-template-panel.js`. **Tab indentation.**

```js
/**
 * Shared "Modal template" panel.
 *
 * Rendered by every trigger surface. Two states, matching core's navigation
 * overlay selector: a prominent create button when no modal template parts
 * exist, and a select plus a small create button when they do.
 *
 * All branching logic lives in ./modal-template-parts.js so it stays unit
 * testable — the editor packages this file imports are webpack externals and
 * cannot be resolved by Jest.
 */

import {
	SelectControl,
	Button,
	FlexBlock,
	FlexItem,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plus } from '@wordpress/icons';
import { useInstanceId } from '@wordpress/compose';
import useModalTemplateEntities from './use-modal-template-entities';

export default function ModalTemplatePanel({
	value,
	onChange,
	showCreate = true,
	showPreview = true,
}) {
	const headingId = useInstanceId(
		ModalTemplatePanel,
		'pikari-modal-template-panel-heading'
	);

	const { parts, options, isResolving, hasResolved, isBlockTheme } =
		useModalTemplateEntities(value);

	// Hybrid themes have no Site Editor and no entities: select only.
	const canCreate = showCreate && isBlockTheme;

	const isEmpty = hasResolved && parts.length === 0;

	const helpText = isEmpty
		? __('No modal templates found.', 'pikari-gutenberg-modals')
		: __('Select a template for this modal.', 'pikari-gutenberg-modals');

	return (
		<div className="pikari-modal-template-panel">
			<h3 id={headingId} className="pikari-modal-template-panel__heading">
				{__('Modal template', 'pikari-gutenberg-modals')}
			</h3>

			{canCreate && isEmpty ? (
				<Button
					__next40pxDefaultSize
					variant="secondary"
					disabled={isResolving}
					accessibleWhenDisabled
					className="pikari-modal-template-panel__create-prominent"
				>
					{__('Create modal template', 'pikari-gutenberg-modals')}
				</Button>
			) : (
				<>
					{canCreate && (
						<Button
							size="small"
							icon={plus}
							disabled={isResolving}
							accessibleWhenDisabled
							label={__('Create new modal template', 'pikari-gutenberg-modals')}
							showTooltip
							className="pikari-modal-template-panel__create"
						/>
					)}
					<HStack
						alignment="flex-start"
						className="pikari-modal-template-panel__controls"
					>
						<FlexBlock>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={__('Modal template', 'pikari-gutenberg-modals')}
								hideLabelFromVision
								aria-labelledby={headingId}
								value={value || ''}
								options={options}
								onChange={(next) => onChange(next)}
								disabled={isResolving}
								accessibleWhenDisabled
								help={helpText}
							/>
						</FlexBlock>
						<FlexItem>{/* Edit button added in Task 8. */}</FlexItem>
					</HStack>
				</>
			)}
		</div>
	);
}
```

- [ ] **Step 3: Add panel styles**

Append to `src/editor/style.scss`:

```scss
.pikari-modal-template-panel {
	position: relative;

	&__heading {
		font-size: 11px;
		font-weight: 500;
		text-transform: uppercase;
		margin: 0 0 8px;
	}

	&__create {
		position: absolute;
		top: 0;
		right: 0;
	}

	&__create-prominent {
		width: 100%;
		justify-content: center;
	}

	&__controls {
		align-items: flex-start;
	}
}
```

- [ ] **Step 4: Build and lint**

```bash
npm run build
npm run lint:js
npm run lint:css
```

Expected: build succeeds, both linters clean. `npm test` should still pass — nothing here is unit tested by design.

- [ ] **Step 5: Commit**

```bash
git add src/editor/modal-template-panel.js src/editor/use-modal-template-entities.js src/editor/style.scss
git commit -m "feat: add shared modal template panel with entity-backed select"
```

---

### Task 7: Create flow and pattern picker

**Files:**

- Create: `src/editor/use-create-modal-template.js`
- Create: `src/editor/modal-template-create-modal.js`
- Modify: `src/editor/modal-template-panel.js`

**Interfaces:**

- Consumes: `getUniqueTitle`, `getCleanSlug`, `selectModalPatterns` (Task 2); patterns from Task 4; `useModalTemplateEntities` (Task 6)
- Produces:

  - `useCreateModalTemplate( parts )` returning `async ( { title, patternContent } ) => templatePart`
  - `<ModalTemplateCreateModal parts onClose onCreated />` — default export

- [ ] **Step 1: Write the create hook**

Create `src/editor/use-create-modal-template.js`:

```js
/**
 * Creates a new modal template part seeded from a starter pattern.
 *
 * Mirrors core's use-create-overlay.js, with one substitution: core resolves
 * its single starter via unlock( blockEditorStore ).getPatternBySlug(), a
 * private API. We read all registered patterns from the core store and filter
 * on the modal block type instead, which is also what powers the picker.
 */

import { useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { parse, serialize, createBlock } from '@wordpress/blocks';
import {
	getUniqueTitle,
	getCleanSlug,
	MODAL_TEMPLATE_PART_AREA,
} from './modal-template-parts';

export default function useCreateModalTemplate(parts) {
	const { saveEntityRecord } = useDispatch(coreStore);

	return useCallback(
		async ({ title, patternContent }) => {
			const uniqueTitle = getUniqueTitle(title, parts);
			const slug = getCleanSlug(uniqueTitle);

			const content = patternContent
				? serialize(
						parse(patternContent, {
							__unstableSkipMigrationLogs: true,
						})
				  )
				: serialize([createBlock('core/paragraph')]);

			return await saveEntityRecord(
				'postType',
				'wp_template_part',
				{
					slug,
					title: uniqueTitle,
					content,
					area: MODAL_TEMPLATE_PART_AREA,
				},
				{ throwOnError: true }
			);
		},
		[parts, saveEntityRecord]
	);
}
```

- [ ] **Step 2: Write the picker modal**

Create `src/editor/modal-template-create-modal.js`:

```js
/**
 * Create-modal-template dialog: pick a starter pattern, name it, create it.
 */

import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { parse } from '@wordpress/blocks';
import { BlockPreview } from '@wordpress/block-editor';
import {
	Modal,
	Button,
	TextControl,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { selectModalPatterns } from './modal-template-parts';
import useCreateModalTemplate from './use-create-modal-template';

export default function ModalTemplateCreateModal({
	parts,
	onClose,
	onCreated,
}) {
	const patterns = useSelect(
		(select) => selectModalPatterns(select(coreStore).getBlockPatterns()),
		[]
	);

	const [selectedName, setSelectedName] = useState(patterns[0]?.name || '');
	const [title, setTitle] = useState('');
	const [isBusy, setIsBusy] = useState(false);

	const createModalTemplate = useCreateModalTemplate(parts);
	const selectedPattern = patterns.find((p) => p.name === selectedName);

	const onSubmit = async (event) => {
		event.preventDefault();
		setIsBusy(true);

		try {
			const created = await createModalTemplate({
				title: title || __('Modal', 'pikari-gutenberg-modals'),
				patternContent: selectedPattern?.content,
			});
			onCreated(created);
		} finally {
			setIsBusy(false);
		}
	};

	return (
		<Modal
			title={__('Create modal template', 'pikari-gutenberg-modals')}
			onRequestClose={onClose}
		>
			<form onSubmit={onSubmit}>
				<div className="pikari-modal-template-create__patterns">
					{patterns.map((pattern) => (
						<button
							key={pattern.name}
							type="button"
							className={`pikari-modal-template-create__pattern${
								pattern.name === selectedName ? ' is-selected' : ''
							}`}
							aria-pressed={pattern.name === selectedName}
							onClick={() => setSelectedName(pattern.name)}
						>
							<BlockPreview
								blocks={parse(pattern.content)}
								viewportWidth={800}
							/>
							<span>{pattern.title}</span>
						</button>
					))}
				</div>

				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={__('Name', 'pikari-gutenberg-modals')}
					value={title}
					onChange={setTitle}
				/>

				<HStack justify="right">
					<Button variant="tertiary" onClick={onClose}>
						{__('Cancel', 'pikari-gutenberg-modals')}
					</Button>
					<Button
						variant="primary"
						type="submit"
						isBusy={isBusy}
						disabled={isBusy || !selectedName}
						accessibleWhenDisabled
					>
						{__('Create', 'pikari-gutenberg-modals')}
					</Button>
				</HStack>
			</form>
		</Modal>
	);
}
```

- [ ] **Step 3: Wire the picker into the panel**

In `src/editor/modal-template-panel.js`: import `useState` from `@wordpress/element`, import `ModalTemplateCreateModal`, add `const [ isCreating, setIsCreating ] = useState( false );`, give both create buttons `onClick={ () => setIsCreating( true ) }`, and render before the closing `</div>`:

```jsx
{
	isCreating && (
		<ModalTemplateCreateModal
			parts={parts}
			onClose={() => setIsCreating(false)}
			onCreated={(created) => {
				setIsCreating(false);
				onChange(created.slug);
			}}
		/>
	);
}
```

- [ ] **Step 4: Add picker styles**

Append to `src/editor/style.scss`:

```scss
.pikari-modal-template-create {
	&__patterns {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 12px;
		margin-bottom: 16px;
	}

	&__pattern {
		background: none;
		border: 1px solid #ddd;
		border-radius: 4px;
		cursor: pointer;
		padding: 8px;
		text-align: center;

		&.is-selected {
			border-color: var(--wp-admin-theme-color);
			box-shadow: 0 0 0 1px var(--wp-admin-theme-color);
		}
	}
}
```

- [ ] **Step 5: Build and verify in the browser**

```bash
npm run build
npm run lint:js && npm run lint:css
```

In the post editor, add a Modal Trigger block. The Modal template panel shows a prominent **Create modal template** button only if no modal parts exist; otherwise a `+`. Click it: three patterns render with previews, name it "Booking sidebar", click Create. The select must immediately show "Booking sidebar" as the active value — this is the behaviour the old localized array could not provide, so it is the key check.

- [ ] **Step 6: Commit**

```bash
git add src/editor/use-create-modal-template.js src/editor/modal-template-create-modal.js src/editor/modal-template-panel.js src/editor/style.scss
git commit -m "feat: create modal templates from starter patterns"
```

---

### Task 8: Edit navigation

**Files:**

- Modify: `src/editor/modal-template-panel.js`

**Interfaces:**

- Consumes: `createTemplatePartId` (Task 2); `currentTheme`, `selectedPart` (Task 6)
- Produces: nothing consumed by later tasks

- [ ] **Step 1: Add the navigation logic**

In `src/editor/modal-template-panel.js`, add imports:

```js
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { pencil } from '@wordpress/icons';
import { createTemplatePartId } from './modal-template-parts';
```

Pull `selectedPart` and `currentTheme` from `useModalTemplateEntities`, then add:

```js
const onNavigateToEntityRecord = useSelect(
	(select) => select(blockEditorStore).getSettings().onNavigateToEntityRecord,
	[]
);

const theme = selectedPart?.theme || currentTheme;

const onEdit = () => {
	if (!selectedPart || !theme || !onNavigateToEntityRecord) {
		return;
	}

	const postId = createTemplatePartId(theme, selectedPart.slug);

	onNavigateToEntityRecord({
		postId,
		postType: 'wp_template_part',
	});
};
```

- [ ] **Step 2: Render the Edit button**

Replace the `{ /* Edit button added in Task 8. */ }` placeholder inside `<FlexItem>`:

```jsx
{
	isBlockTheme && selectedPart && hasResolved && onNavigateToEntityRecord && (
		<Button
			__next40pxDefaultSize
			variant="secondary"
			icon={pencil}
			onClick={onEdit}
			label={__('Edit modal template', 'pikari-gutenberg-modals')}
			showTooltip
		/>
	);
}
```

Two notes on the render guard.

`selectedPart` is null for the default selection (`value === ''`) unless a `modal`-slug part exists in the records. Task 1 confirmed the synthetic default does surface, so editing the default works.

`onNavigateToEntityRecord` gates the button because it is the only route to the Site Editor. Task 1 found it present in the post editor on WordPress 7.1, so in practice the button shows in both editors; on the 6.8 floor, where it may be absent, the button simply does not render. This is core's own behaviour — see spec §4 for why the earlier hand-built URL fallback was dropped.

- [ ] **Step 3: Build and verify both paths in the browser**

```bash
npm run build && npm run lint:js
```

- **Post editor:** select a non-default modal template, click Edit. Confirm where it lands and that the post's unsaved changes survive — core routes `onNavigateToEntityRecord` differently per editor, and this specific behaviour for a `wp_template_part` from the post editor has not been observed yet. If it navigates away destructively, that is a finding: report it rather than working around it.
- **Site Editor:** place a Modal Trigger inside a template, select a modal template, click Edit. It navigates in place, and the editor's back affordance returns you to the template.

- [ ] **Step 4: Commit**

```bash
git add src/editor/modal-template-panel.js
git commit -m "feat: edit modal templates from any trigger"
```

---

### Task 9: Template part preview

**Files:**

- Create: `src/editor/modal-template-preview.js`
- Modify: `src/editor/modal-template-panel.js`, `src/editor/style.scss`

**Interfaces:**

- Consumes: `createTemplatePartId` (Task 2)
- Produces: `<ModalTemplatePreview slug theme />` — default export, renders null when it cannot resolve

- [ ] **Step 1: Write the preview component**

Create `src/editor/modal-template-preview.js`:

```js
/**
 * Read-only preview of the selected modal template part.
 *
 * Prefers the edited blocks so unsaved Site Editor changes show, falling
 * back to parsing the stored content.
 */

import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { parse } from '@wordpress/blocks';
import { BlockPreview } from '@wordpress/block-editor';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createTemplatePartId } from './modal-template-parts';

export default function ModalTemplatePreview({ slug, theme }) {
	const templatePartId = useMemo(
		() => createTemplatePartId(theme, slug),
		[theme, slug]
	);

	const { content, editedBlocks, hasResolved } = useSelect(
		(select) => {
			if (!templatePartId) {
				return { content: null, editedBlocks: null, hasResolved: true };
			}

			const { getEditedEntityRecord, hasFinishedResolution } =
				select(coreStore);

			const args = [
				'postType',
				'wp_template_part',
				templatePartId,
				{ context: 'view' },
			];

			const record = getEditedEntityRecord(...args);

			return {
				content: record?.content,
				editedBlocks: record?.blocks,
				hasResolved: hasFinishedResolution('getEditedEntityRecord', args),
			};
		},
		[templatePartId]
	);

	const blocks = useMemo(() => {
		if (editedBlocks?.length) {
			return editedBlocks;
		}

		if (typeof content === 'string' && content) {
			return parse(content);
		}

		return [];
	}, [editedBlocks, content]);

	if (!templatePartId) {
		return null;
	}

	if (!hasResolved) {
		return (
			<div className="pikari-modal-template-preview is-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div
			className="pikari-modal-template-preview"
			role="region"
			aria-label={__('Modal template preview', 'pikari-gutenberg-modals')}
		>
			<BlockPreview.Async
				placeholder={
					<div className="pikari-modal-template-preview__placeholder" />
				}
			>
				<BlockPreview blocks={blocks} viewportWidth={400} minHeight={200} />
			</BlockPreview.Async>
		</div>
	);
}
```

- [ ] **Step 2: Render it from the panel**

In `src/editor/modal-template-panel.js`, import it and add before the closing `</div>`:

```jsx
{
	showPreview && isBlockTheme && selectedPart && (
		<ModalTemplatePreview slug={selectedPart.slug} theme={theme} />
	);
}
```

- [ ] **Step 3: Add preview styles**

Append to `src/editor/style.scss`:

```scss
.pikari-modal-template-preview {
	border: 1px solid #ddd;
	border-radius: 4px;
	margin-top: 12px;
	overflow: hidden;

	&.is-loading {
		align-items: center;
		display: flex;
		justify-content: center;
		min-height: 200px;
	}

	&__placeholder {
		min-height: 200px;
	}
}
```

- [ ] **Step 4: Build and verify in the browser**

```bash
npm run build && npm run lint:js && npm run lint:css
```

Select a modal template in a trigger's panel — the preview renders its blocks. Edit that part in the Site Editor, change something obvious, return: the preview reflects the change.

- [ ] **Step 5: Commit**

```bash
git add src/editor/modal-template-preview.js src/editor/modal-template-panel.js src/editor/style.scss
git commit -m "feat: preview the selected modal template part"
```

---

### Task 10: Wire the panel into every trigger surface

**Files:**

- Modify: `src/blocks/modal-trigger/edit.js` (around line 465)
- Modify: `src/editor/button-modal-extension.js` (around line 245)
- Modify: `src/editor/modal-trigger-edit.js` (around line 489)
- Delete: `src/editor/use-modal-template-parts.js`
- Leave alone: `src/editor/group-modal-trigger-extension.js`

**Interfaces:**

- Consumes: `<ModalTemplatePanel>` (Tasks 6–9)
- Produces: nothing

- [ ] **Step 1: Replace the selector in the Modal Trigger block**

In `src/blocks/modal-trigger/edit.js`, remove the `useModalTemplateParts` import and its `const templateParts = useModalTemplateParts();` line. Replace the entire `{ templateParts.hasMultiple && ( … ) }` block and any adjacent `isValidSelection` fallback with:

```jsx
<ModalTemplatePanel
	value={templatePart}
	onChange={(next) => setAttributes({ templatePart: next })}
/>
```

Add `import ModalTemplatePanel from '../../editor/modal-template-panel';`.

- [ ] **Step 2: Replace the selector in the button extension**

In `src/editor/button-modal-extension.js`, same substitution, with the attribute name `pikariModalTemplatePart`:

```jsx
<ModalTemplatePanel
	value={pikariModalTemplatePart}
	onChange={(next) =>
		setAttributes({
			pikariModalTemplatePart: next,
		})
	}
/>
```

Add `import ModalTemplatePanel from './modal-template-panel';` and remove the `useModalTemplateParts` import and call.

- [ ] **Step 3: Replace the selector in the inline format**

In `src/editor/modal-trigger-edit.js`, the UI is a `Popover`, so create and preview are suppressed:

```jsx
<ModalTemplatePanel
	value={templatePart}
	onChange={setTemplatePart}
	showCreate={false}
	showPreview={false}
/>
```

Add `import ModalTemplatePanel from './modal-template-panel';` and remove the `useModalTemplateParts` import and call.

- [ ] **Step 4: Point the group extension at the localized array directly**

`src/editor/group-modal-trigger-extension.js` is deprecated and does not get the new panel, but it is the last consumer of `use-modal-template-parts.js`. Inline the small amount it needs so the old hook can be deleted: replace `const templateParts = useModalTemplateParts();` with a local `useMemo` over `window.pikariGutenbergModals?.modalTemplateParts` producing the same `{ options, hasMultiple, isValidSelection }` shape, then delete `src/editor/use-modal-template-parts.js`.

```bash
git rm src/editor/use-modal-template-parts.js
```

- [ ] **Step 5: Build, lint, test**

```bash
npm run build
npm run lint:all
composer test
npm test
```

Expected: all green. `npm test` must still pass — `find-links-in-blocks.test.js` and `modal-template-parts.test.js` are unaffected.

- [ ] **Step 6: Verify all four surfaces in the browser**

For each of: Modal Trigger block, a core Button with the modal toggle on, an inline modal trigger (Cmd/Ctrl+M), and a group modal trigger —

- The Modal template panel appears **even when only the default part exists** (the old `hasMultiple` gate is gone).
- Selecting a non-default template and viewing the page opens that template's modal.
- The inline format's popover shows select + Edit only, with no create button and no preview.
- The group block still works with its old select.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: use the shared modal template panel on every trigger surface"
```

---

### Task 11: Documentation

**Files:**

- Modify: `CLAUDE.md` (block table, gotchas 5/6/12, Project Structure, Custom Hooks section)
- Modify: `readme.txt` and `README.md` (must stay in sync)
- Modify: `CHANGELOG.md`

**Interfaces:**

- Consumes: everything
- Produces: nothing

- [ ] **Step 1: Update CLAUDE.md**

- Rename every `modal-dialog` reference to `modal-overlay` in gotchas 5, 6, and 12, and in the Style architecture note.
- Add `ModalPatterns` to the PHP Classes table: `| ModalPatterns | ~90 | Starter patterns registered to the modal template part area |`.
- Add to the JavaScript Editor table: `modal-template-parts.js` (pure helpers), `modal-template-panel.js`, `use-modal-template-entities.js`, `use-create-modal-template.js`, `modal-template-create-modal.js`, `modal-template-preview.js`.
- Add `patterns/` to Project Structure.
- Add a new gotcha: **Editor packages are webpack externals, not devDependencies.** `@wordpress/data`, `core-data`, `components`, `block-editor`, `compose`, `html-entities`, `notices`, `url`, and `blocks` are not installed. Jest cannot resolve them. Any logic that needs unit tests must live in a module importing nothing from `@wordpress/*` except `@wordpress/i18n` — see `src/editor/modal-template-parts.js`.
- Add a new gotcha: **The default modal part maps to an empty slug.** Triggers store `''` to mean the default. The entity list contains a part with slug `modal`; `buildPartOptions()` collapses the two so the select never offers "Default" twice.

- [ ] **Step 2: Update readme.txt and README.md**

Both files get, in the Developer section, the new pattern extension point:

> Modal starter patterns are registered with `blockTypes` set to `core/template-part/modal`. Themes and plugins can register their own with `register_block_pattern()` using the same block type, and they will appear in the Create modal template picker.

Confirm the `modal-overlay` rename landed in the filter example in both files, and that the two files remain identical in content.

- [ ] **Step 3: Update CHANGELOG.md**

Add an Unreleased entry covering the breaking rename and the new panel:

```markdown
### Changed

- **Breaking:** the `pikari-gutenberg-modals/modal-dialog` block is now `pikari-gutenberg-modals/modal-overlay`. Customized modal template parts containing the old block will render as an unrecognised block and must be recreated. Chrome styling (background, border, padding, shadow) belongs on the inner `.modal-chrome` Group.

### Added

- Modal template panel on every trigger: select, create from a starter pattern, edit, and preview a modal template part.
- Three starter patterns — Centered dialog, Right panel, Left panel — registered to the `modal` template part area.
```

- [ ] **Step 4: Verify and commit**

```bash
npm run lint:all && composer test && npm test
diff <(sed -n '/Developer/,$p' readme.txt) <(sed -n '/Developer/,$p' README.md) || echo "readme files differ — reconcile before committing"
git add -A
git commit -m "docs: document the modal overlay rename and template panel"
```

---

## Self-Review

### Spec coverage

| Spec section                                      | Task                                          |
| ------------------------------------------------- | --------------------------------------------- |
| §1 Data source                                    | 6 (entity hook), gated by 1                   |
| §2 Component architecture — two states            | 6                                             |
| §2 Missing-selection handling                     | 2 (`buildPartOptions`)                        |
| §2 Preview                                        | 9                                             |
| §2 Theme resolution                               | 2 (`createTemplatePartId`), 6                 |
| §3 Create flow                                    | 7                                             |
| §3 Patterns                                       | 4                                             |
| §3 `getBlockPatterns` substitution for `unlock()` | 7                                             |
| §4 Edit navigation                                | 8                                             |
| §5 Rename                                         | 3                                             |
| §6 Hybrid themes                                  | 6 (`isBlockTheme` branch), 5 (localized flag) |
| §6 Inline popover                                 | 10 (`showCreate`/`showPreview` false)         |
| §6 Group extension untouched                      | 10                                            |
| §7 Pure module tests                              | 2                                             |
| §7 PHPUnit                                        | 4, 5                                          |
| §7 Browser-only checks                            | 3, 6, 7, 8, 9, 10                             |
| Verified before building                          | 1 (complete)                                  |

No spec section is unimplemented.

### Type consistency

`buildPartOptions` takes an object in Task 2 and is called with an object in Task 6. `createTemplatePartId( theme, slug )` has the same argument order in Tasks 2, 8, and 9. `useModalTemplateEntities( selectedSlug )` returns `selectedPart` and `currentTheme`, both consumed in Tasks 8 and 9. `useCreateModalTemplate( parts )` returns a function taking `{ title, patternContent }`, matching its call site in Task 7.

### Known risk

Task 6's `useEntityRecords` fourth argument (`{ enabled }`) is version-dependent and is flagged inline with a fallback. Task 8's post-editor behaviour for `onNavigateToEntityRecord` with a `wp_template_part` is confirmed _present_ but its landing behaviour is unobserved; Task 8 Step 3 says to report rather than work around it.
