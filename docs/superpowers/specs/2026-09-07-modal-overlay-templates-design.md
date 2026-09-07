# Modal Overlay Templates — Design

**Date:** 2026-09-07
**Status:** Approved, not yet implemented
**Depends on:** `feature/modal-placement` (blocked on browser QA, Pikari todo #441)

## Problem

Users have no discoverable way to choose — or create — a modal template part.

A selector is in fact already wired into all four trigger surfaces:

| Surface                 | File                                          | Line |
| ----------------------- | --------------------------------------------- | ---- |
| Modal Trigger block     | `src/blocks/modal-trigger/edit.js`            | 465  |
| Inline RichText format  | `src/editor/modal-trigger-edit.js`            | 489  |
| `core/button` extension | `src/editor/button-modal-extension.js`        | 245  |
| `core/group` extension  | `src/editor/group-modal-trigger-extension.js` | 358  |

All four render behind the same gate in `src/editor/use-modal-template-parts.js`:

```js
hasMultiple: parts.length >= 2;
```

A site shipping only `parts/modal.html` has `parts.length === 1`, so every selector hides
itself. Combined with the absence of any create affordance, the feature is unreachable: you
cannot select a second template part, and you cannot make one.

The server-side plumbing is complete and working — `pikariModalTemplatePart` →
`data-modal-template-part` → `templatePart` Interactivity context → per-slug modal container.
Nothing there needs to change.

## Prior art: core's Navigation Overlay

WordPress 7.0 ships customizable navigation overlays built on a `navigation-overlay`
template part area. The implementation is the reference for this work:

- `packages/block-library/src/navigation/edit/overlay-template-part-selector.jsx`
- `packages/block-library/src/navigation/edit/use-create-overlay.js`
- `packages/block-library/src/navigation/edit/overlay-preview.jsx`

Two findings from reading it shaped this design.

**Core puts zero settings on the template part itself.** Everything configurable lives on the
Navigation block — the consumer — and the overlay's appearance comes from ordinary block
styling on the blocks inside the part. Core shipped a dedicated _Navigation Overlay Close
block_ rather than a settings panel.

This is not an accident of implementation, and it is the answer to "could our settings move
onto the template part instead of onto a block?" Technically, `PluginDocumentSettingPanel`
(from `@wordpress/editor`) does render in the Site Editor for template parts since Gutenberg
18.0/18.3 → WP 6.6, so the UI half is possible. The storage half is not viable:
`WP_REST_Templates_Controller`'s schema exposes no `meta` field, so post meta on
`wp_template_part` does not round-trip. `register_rest_field()` is technically open — the
controller does call `add_additional_fields_to_object` and
`update_additional_fields_for_object` — but the editor-side round-trip is unverified.

It is moot regardless, for a design reason rather than an implementation one:

> **Settings stored as block markup travel with the part. REST fields and meta do not.**

A theme registering `modal-sidebar` in `theme.json` plus `parts/modal-sidebar.html` can only
express "I am a sidebar" if placement lives in the block markup. Create Block Theme exports
`.html` only. Patterns, copy/paste, and our own file-based default all carry markup; none
carry meta. So placement stays on a block inside the part.

**Core's overlay is always fullscreen.** Non-fullscreen placement is explicitly not yet
supported upstream. Our `placement` control is a genuine capability core lacks — the nav
overlay panel feels simple partly because it has almost nothing to configure.

## Decisions

1. **Full nav-overlay parity** — select + create + edit + live preview, always visible.
2. **Rename** `modal-dialog` → `modal-overlay`, stripping supports that duplicate `.modal-chrome`.
3. **Create opens a pattern picker** — Centered dialog / Right panel / Left panel.
4. **Sequencing** — finish #441, merge placement, rebase, then build. No code before that.

## 1. Data source

`useModalTemplateParts()` reads `window.pikariGutenbergModals.modalTemplateParts`, a
PHP-localized array of `{slug, title}`. It carries no content and cannot reflect a part
created in the same session, so live preview and post-create refresh are impossible on it.

Replace it with the entity store, matching core:

```js
const { records, isResolving, hasResolved } = useEntityRecords(
	'postType',
	'wp_template_part',
	{ per_page: -1 }
);
const modalParts = records?.filter((part) => part.area === 'modal') ?? [];
```

Core filters by area client-side rather than passing it as a query arg; we follow.

`EditorIntegration::get_modal_template_parts()` and `scan_theme_modal_templates()` survive
only to feed the hybrid-theme degraded path (§6).

**This is the change that carries risk.** `ModalTemplatePart::provide_default_template()`
synthesises the default `modal` part via the `get_block_templates` filter. That filter is
what the REST controller queries, so the default _should_ surface with `content.raw` — but
this must be verified first. If it does not, the default part has no preview and the empty
state is wrong.

## 2. Component architecture

A single shared `<ModalTemplatePanel>` in `src/editor/`, modelled on
`overlay-template-part-selector.jsx`.

**Two states**, replacing the `hasMultiple >= 2` gate:

- **Zero modal parts** — a prominent secondary "Create modal template" button with heading
  and help text. (Core arrived here too: PR #74971 replaced a small `+` icon with exactly
  this, because the icon was not discoverable.)
- **One or more** — small `+` icon button, `SelectControl`, `Edit` button, preview below.

**Missing-selection handling.** When the stored slug no longer resolves, core prepends a
`sprintf( __( '%s (missing)' ), slug )` option rather than silently discarding the value.
Our current `isValidSelection` nulls the attribute, which destroys the user's intent without
telling them. Adopt core's behaviour.

**Preview.**

```jsx
<BlockPreview.Async placeholder={<div className="…-preview-placeholder" />}>
	<BlockPreview blocks={blocks} viewportWidth={400} minHeight={200} />
</BlockPreview.Async>
```

Blocks come from `getEditedEntityRecord( 'postType', 'wp_template_part', id, { context: 'view' } ).blocks`,
falling back to `parse( content )` when the record has no edited blocks.

**Theme resolution.** `select( coreStore ).getCurrentTheme()?.stylesheet`, combined with the
slug through core's `createTemplatePartId( theme, slug )` helper (`theme//slug`). That helper
lives behind a core-internal path; reimplement the two-line join rather than importing it.

## 3. Create flow

Core's `use-create-overlay.js`, adapted:

```js
const templatePart = await saveEntityRecord(
	'postType',
	'wp_template_part',
	{
		slug: cleanSlug,
		title: uniqueTitle,
		content: initialContent,
		area: 'modal',
	},
	{ throwOnError: true }
);
```

Then set the trigger's `templatePart` attribute to the new slug and navigate to it (§4).
Errors surface through `createErrorNotice` as a snackbar, matching core.

**One substitution.** Core resolves a single hardcoded starter via
`unlock( select( blockEditorStore ) ).getPatternBySlug( 'core/navigation-overlay' )`.
`unlock()` is a core-private API and is **not available to us**. Use instead:

```js
select('core')
	.getBlockPatterns()
	.filter((p) => p.blockTypes?.includes('core/template-part/modal'));
```

This is the same mechanism that powers the picker, and it lets themes register their own
modal starters into the area.

**Patterns to ship** — three, registered with `blockTypes: [ 'core/template-part/modal' ]`:

| Pattern         | Placement | Notes                                    |
| --------------- | --------- | ---------------------------------------- |
| Centered dialog | (none)    | Equivalent to today's `parts/modal.html` |
| Right panel     | `right`   | Edge-pinned, full height                 |
| Left panel      | `left`    | Edge-pinned, full height                 |

Each must contain a valid close trigger and a Content Area block. A modal part missing either
is functionally broken — unlike a nav overlay, which can legitimately be near-empty. This is
why "blank canvas" was rejected as the create behaviour.

Core's _built-in_ template-part area pattern selector only handles `header` and `footer`
areas, so it will not pick these up. That is irrelevant: we render our own picker and filter
`getBlockPatterns()` ourselves. Noted so a future reader does not rediscover the limitation
and assume the design is broken.

## 4. Edit navigation

```js
const { onNavigateToEntityRecord } = useSelect(
	(select) => ({
		onNavigateToEntityRecord:
			select(blockEditorStore).getSettings().onNavigateToEntityRecord,
	}),
	[]
);
```

Called with `{ postId: templatePartId, postType: 'wp_template_part' }`.

**It is undefined in the post editor** — the Site Editor sets it; the post editor does not.
Core simply hides the Edit button when it is absent. Our triggers live overwhelmingly in post
and page content, so hiding Edit there would remove the affordance from the common case.

Fallback when `onNavigateToEntityRecord` is unavailable: open

```
site-editor.php?p=/wp_template_part/{theme}//{slug}&canvas=edit
```

in a new tab, built with `addQueryArgs()` against `window.pikariGutenbergModals.siteEditorUrl`
(a new localized value — do not hand-build admin URLs client-side). A new tab, not a
same-tab navigation, because the user may have unsaved post content.

## 5. Modal Overlay rename

`pikari-gutenberg-modals/modal-dialog` → `pikari-gutenberg-modals/modal-overlay`,
titled "Modal Overlay".

The block is renamed for accuracy: it renders the full-viewport backdrop and governs where
the dialog sits within it. The dialog's own chrome — background, border, padding, shadow —
belongs to the inner `.modal-chrome` Group, and today's `block.json` advertising both is the
source of the "where do I set this?" confusion.

**Supports removed:** `color.background`, `__experimentalBorder`, `spacing.padding`, `shadow`.

**Attributes kept:** `overlayColor`, `overlayGradient`, `backgroundImage`, `focalPoint`,
`hasParallax`, plus `placement` arriving from `feature/modal-placement`.

**Touch points:** `src/blocks/modal-dialog/` (directory rename), `block.json`, the
`wp-block-pikari-gutenberg-modals-modal-dialog` CSS class, `parts/modal.html`,
`BlockSupport::render_modal_containers()`, the fallback-close scan in `render.php`, and
`INNER_BLOCKS_TEMPLATE` in `edit.js`.

**Accepted breaking change, stated explicitly:** existing customized `wp_template_part` posts
containing `pikari-gutenberg-modals/modal-dialog` will render as an unrecognised block.
No deprecation shim and no migration is planned. This was accepted deliberately.

## 6. Degraded modes

**Hybrid themes.** `scan_theme_modal_templates()` covers classic themes with
`block-template-parts` support. They have no `wp_template_part` REST entities and no Site
Editor. The panel degrades to select-only — no create, no edit, no preview — driven off the
retained localized array. Detect via a localized `isBlockTheme` flag; the panel must degrade,
not error.

**Inline RichText format.** The format's UI is a transient `Popover`, not `InspectorControls`.
A `BlockPreview` there is cramped and disappears on blur. That surface gets select + Edit
only; create and preview live on the three block surfaces.

**`core/group` extension.** Deprecated in favour of the Modal Trigger block. It keeps its
existing select as-is and does **not** receive the new panel. Removing the extension is
separate work.

## 7. Testing

**Jest** (`tests/unit/editor/modal-template-panel.test.js`):

- Empty state renders the prominent create button; populated state renders select + `+` + Edit.
- A stored slug absent from the records list renders a `(missing)` option and does not clear
  the attribute.
- Hybrid mode renders select only, with no create/edit/preview.
- Create calls `saveEntityRecord` with `area: 'modal'` and the picked pattern's content.
- Edit falls back to the site-editor URL when `onNavigateToEntityRecord` is undefined.

**PHPUnit:** pattern registration — three patterns registered with
`blockTypes: [ 'core/template-part/modal' ]`.

**Browser only** (no harness exists for these): preview rendering, cross-editor navigation,
and the created part actually opening as a modal on the front end. Same constraint documented
in todo #441 — for the render path, browser verification _is_ the coverage, not a supplement.

## Verify before building

1. Does the synthetic default `modal` part surface through `useEntityRecords` with
   `content.raw`? (§1 — everything depends on it.)
2. Does `select( 'core' ).getBlockPatterns()` return plugin-registered patterns with arbitrary
   `blockTypes` values intact? (§3)
3. Is `getSettings().onNavigateToEntityRecord` genuinely absent in the post editor at our
   minimum WP version, or merely absent in some contexts? (§4)

## Out of scope

- `modalSize` stays on the trigger; the size-vs-placement interaction that #441 flags
  ("a fullscreen size on a trigger pointing at a panel dialog") is a separate question.
- Removing the `core/group` modal trigger extension.
- Any migration or deprecation shim for the renamed block.
