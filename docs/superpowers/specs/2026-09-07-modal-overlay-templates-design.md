# Modal Overlay Templates — Design

**Date:** 2026-09-07
**Status:** Approved, not yet implemented
**Depends on:** `feature/modal-placement` — merged to `main` (PR #112, `1db0e1f`); Pikari
todo #441 is closed. `feature/simplify-modal-dialog-ux` also merged (PR #113, `460a9c2`).
As of the 2026-09-09 amendments below, this plan additionally depends on
`feature/modal-trigger-as-property`, which has not yet merged.

## Problem

Users have no discoverable way to choose — or create — a modal template part.

A selector is in fact already wired into both trigger surfaces:

> **Amended 2026-09-09.** This originally listed four surfaces: the Modal Trigger block,
> the inline RichText format, and separate `core/button`/`core/group` extensions.
> `feature/modal-trigger-as-property` deleted the block and both extensions, replacing them
> with `pikariModalAction` and related attributes registered directly on `core/group` and
> `core/button`, surfaced through one shared "Modal" panel
> (`src/editor/modal-trigger-panel.js`). That leaves two surfaces: the shared panel
> (serving both blocks) and the inline format.

| Surface                                            | File                                | Line |
| -------------------------------------------------- | ----------------------------------- | ---- |
| Shared "Modal" panel (`core/group`, `core/button`) | `src/editor/modal-trigger-panel.js` | 308  |
| Inline RichText format                             | `src/editor/modal-trigger-edit.js`  | 489  |

Both render behind the same gate in `src/editor/use-modal-template-parts.js`:

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

Called with `{ postId: templatePartId, postType: 'wp_template_part' }`. Render the Edit
button only when the setting is present; hide it when it is not. That is core's own
behaviour, and it needs no URL construction.

**Corrected after verification.** An earlier draft of this section asserted that
`onNavigateToEntityRecord` is undefined in the post editor, and specified a fallback that
hand-built `site-editor.php?p=/wp_template_part/{theme}//{slug}&canvas=edit` and opened it
in a new tab. Both were wrong:

- The setting is `typeof "function"` in the post editor on WordPress 7.1 (verified —
  see `_plans/modal-overlay-templates-verification.md`). Core leaves a route there.
- There is no stable public URL contract to build against even if we wanted one. Core
  registers `wp_template_part` with `'_edit_link' => '/site-editor.php?canvas=edit'`
  (`wp-includes/post.php:503`), which carries no id placeholder — the id is applied by
  the JS router, not by anything a plugin can construct.

The fallback and its `siteEditorUrl` localized value are therefore dropped entirely.

On the WordPress 6.8 floor, where the setting may be absent, the button simply does not
render. That degrades the same way core does and needs no version detection.

## 5. Modal Overlay rename

`pikari-gutenberg-modals/modal-dialog` → `pikari-gutenberg-modals/modal-overlay`,
titled "Modal Overlay".

The block is renamed for accuracy: it renders the full-viewport backdrop and governs where
the dialog sits within it. The dialog's own chrome — background, border, padding, shadow —
belongs to the inner `.modal-chrome` Group, and today's `block.json` advertising both is the
source of the "where do I set this?" confusion.

**Supports removed:** `color.background`, `__experimentalBorder`, `spacing.padding`, `shadow`.

**Attributes kept:** `overlayColor`, `overlayGradient`, `overlayOpacity`, `backgroundImage`,
`focalPoint`, `hasParallax`, plus `placement` arriving from `feature/modal-placement`.

`overlayOpacity` was added to the block by PR #104 after this spec was first written and is
easy to drop by accident during the rename — it is an overlay concern, not chrome, so it
stays. The `style` attribute becomes vestigial once the supports are removed: it exists only
so the editor can detect legacy chrome styling. Remove it with the deprecation notice.

**Touch points** (enumerated from a full-tree grep, `build/` excluded):

| File                                        | What                                                                                                                                      |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `src/blocks/modal-dialog/`                  | Directory rename to `modal-overlay/`                                                                                                      |
| `src/blocks/modal-dialog/block.json:4,61`   | `name`, `title`, `description`, `editorScript` path, supports                                                                             |
| `src/blocks/modal-dialog/style.css:18`      | `.wp-block-…-modal-dialog` selector                                                                                                       |
| `src/blocks/modal-dialog/editor.css:6,9,15` | Two block selectors plus `.modal-dialog-image-control`                                                                                    |
| `src/blocks/modal-dialog/edit.js:89,153`    | `modal-dialog-deprecation-notice`, `modal-dialog-image-control`                                                                           |
| `src/blocks/modal-dialog/render.php:27`     | `has_class( 'wp-block-…-modal-dialog' )` unwrap check                                                                                     |
| `src/blocks/close-button/block.json:10`     | `ancestor` array                                                                                                                          |
| `src/blocks/content-area/block.json:10`     | `ancestor` array                                                                                                                          |
| `pikari-gutenberg-modals.php:77`            | `register_block_type( … 'build/blocks/modal-dialog' )`                                                                                    |
| `webpack.config.js:22`                      | Entry point path                                                                                                                          |
| `includes/EditorIntegration.php:329`        | `restrict_modal_template_blocks()` allowlist                                                                                              |
| `includes/BlockSupport.php:132`             | `wp_enqueue_style( 'pikari-gutenberg-modals-modal-dialog-style' )` — handle is derived from the block name, so it changes with the rename |
| `parts/modal.html:1,2,13`                   | Block comment and wrapper class                                                                                                           |
| `readme.txt:188`, `README.md:190`           | Filter example in the Developer section                                                                                                   |
| `CLAUDE.md:113,125,127`                     | Gotchas 5, 6, 12 reference `modal-dialog/` paths                                                                                          |

The `close-button` and `content-area` `ancestor` entries are easy to miss and fail silently —
a stale ancestor makes those blocks uninsertable in the template part editor with no error.

**Accepted breaking change, stated explicitly:** existing customized `wp_template_part` posts
containing `pikari-gutenberg-modals/modal-dialog` will render as an unrecognised block.
No deprecation shim and no migration is planned. This was accepted deliberately.

## 6. Degraded modes

**Hybrid themes.** `scan_theme_modal_templates()` covers classic themes with
`block-template-parts` support. They have no `wp_template_part` REST entities and no Site
Editor. The panel degrades to select-only — no create, no edit, no preview — driven off the
retained localized array. Detect via a localized `isBlockTheme` flag; the panel must degrade,
not error.

**Amended 2026-09-09.** The two bullets below originally named three surfaces: the inline
RichText format; a `core/group` extension — kept its own old select, never given the new
panel — deprecated in favour of the Modal Trigger block; and that block itself, named only
as the extension's replacement, not as a degraded surface of its own.
`feature/modal-trigger-as-property` removed the block and both the `core/group` and
`core/button` extensions, replacing them with attributes on `core/group` and `core/button`
directly, served by one shared "Modal" panel (`src/editor/modal-trigger-panel.js`). There is
no `core/group`-specific degraded mode left to describe. The inline format still degrades,
for the reason below — and, per the Hybrid themes bullet directly above, so does the
select-only experience on classic themes without Site Editor support.

**Inline RichText format.** The format's UI is a transient `Popover`, not `InspectorControls`.
A `BlockPreview` there is cramped and disappears on blur. That surface gets select + Edit
only; create and preview live on the shared block panel (`core/group` and `core/button`).

## 7. Testing

**Constraint discovered while planning.** None of `@wordpress/data`, `core-data`,
`components`, `block-editor`, `compose`, `html-entities`, `notices`, `url`, or `blocks` is
installed — they are webpack externals, present at build time only. `@testing-library/react`
is absent too. Jest therefore cannot render any component that imports them. The one existing
editor test, `find-links-in-blocks.test.js`, passes precisely because that module imports
nothing from `@wordpress/*`.

Rather than add ten devDependencies or nine hand-written mocks, **every decision moves into a
pure module and the component keeps only wiring.**

`src/editor/modal-template-parts.js` — pure, fully Jest-covered, importing only `__` from
`@wordpress/i18n` (already mapped to a mock in `jest.config.js`):

| Export                                                                  | Responsibility                                      |
| ----------------------------------------------------------------------- | --------------------------------------------------- |
| `filterModalParts( records )`                                           | `area === 'modal'`, null-safe                       |
| `getPartTitle( part )`                                                  | Rendered title, falling back to raw, then slug      |
| `buildPartOptions( { parts, selectedSlug, hasResolved, isResolving } )` | Default option, resolved options, `(missing)` entry |
| `getUniqueTitle( base, parts )`                                         | Appends a numeric suffix against existing titles    |
| `getCleanSlug( title )`                                                 | Title → URL-safe slug                               |
| `createTemplatePartId( theme, slug )`                                   | `theme//slug`                                       |
| `selectModalPatterns( patterns )`                                       | `blockTypes.includes( 'core/template-part/modal' )` |

`src/editor/modal-template-panel.js` — hooks plus JSX only, no branching logic worth a unit
test. Browser-verified.

**PHPUnit:** pattern registration — three patterns registered with
`blockTypes: [ 'core/template-part/modal' ]`; the new localized `isBlockTheme` value
present in `EditorIntegration`.

**Browser only** (no harness exists for these): panel states, preview rendering, cross-editor
navigation, and a created part actually opening as a modal on the front end. Same constraint
documented in todo #441 — for the render path, browser verification _is_ the coverage, not a
supplement to it.

## Verified before building

All three open questions were answered on 2026-09-07 against WordPress 7.1 with Twenty
Twenty-Five. Full detail and raw output: `_plans/modal-overlay-templates-verification.md`.

1. **Does the synthetic default `modal` part surface through REST with `content.raw`?**
   **Yes.** Checked separately from the saved case, because the dev database already held
   a customised part. The synthetic record returns `id: twentytwentyfive//modal`,
   `source: "plugin"`, `origin: "plugin"`, `wp_id: 0`, and `content.raw` of 1280 characters
   read from `parts/modal.html`. §1 stands unchanged.

2. **Does `getBlockPatterns()` preserve arbitrary `blockTypes`?** **Yes**, with a naming
   wrinkle worth recording: the REST layer emits snake_case `block_types`, and the client
   store converts it back to camelCase `blockTypes`. Filter on the camelCase form in the
   editor and the snake_case form if you ever read the endpoint directly. WordPress 7.1
   ships 31 patterns scoped to `core/template-part/navigation-overlay`.

3. **Is `onNavigateToEntityRecord` absent in the post editor?** **No — the opposite.** It is
   a function there. This refuted §4 as originally written; that section has been rewritten
   and the URL fallback dropped.

One residual, deliberately not chased: the verbatim Site Editor URL for a template part was
never captured. It only mattered for the fallback, which no longer exists.

## Out of scope

- `modalSize` stays on the trigger; the size-vs-placement interaction that #441 flags
  ("a fullscreen size on a trigger pointing at a panel dialog") is a separate question.
- ~~Removing the `core/group` modal trigger extension.~~ Superseded by
  `feature/modal-trigger-as-property`, which removes it as part of its own work; that
  branch has not yet merged. See the amendment note in §6.
- Any migration or deprecation shim for the renamed block.
