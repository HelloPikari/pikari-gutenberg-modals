# Modal Triggers as a Block Property — Design

**Date:** 2026-09-09
**Status:** Proposed
**Supersedes parts of:** `docs/superpowers/specs/2026-09-07-modal-overlay-templates-design.md` (§6, trigger surfaces)

## The one-sentence version

Opening a modal is something a block **does**, not something you **wrap it in**.

## Problem

Three defects found during #441 QA share a single root cause: the trigger is a wrapper
around the thing the author cares about, rather than the thing itself.

**1. A button inside a Modal Trigger is inert.** `handleGroupTriggerClick` ignores
`a:not(.is-primary-link), button, input, select, textarea, [role="button"]` so genuine
nested links keep working. `render.php` only adds `is-primary-link` in the
**detected-link** content source; in `url` and `inline` modes it decorates the wrapper
and marks no inner anchor. A core Button with no link set renders
`<a class="wp-block-button__link">` with **no `href`**, so it matches the ignore list,
the handler bails, and it cannot navigate either.

Confirmed in Playwright: clicking returned `is-open: false`; adding `is-primary-link`
to that same anchor returned `is-open: true`.

**2. The wrapper is an unwanted layout box.** The block declares
`"supports": { "layout": false }` and no width control, so Twenty Twenty-Five's
`.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull))`
gives it `max-width: var(--wp--style--global--content-size)` and
`margin-left/right: auto !important`. The clickable area silently grows to the content
width, and because the block has no alignment controls, aligning a button inside it
requires nesting a Group. `width: fit-content` would patch the symptom.

**3. Converting a styled Group loses its styling.** `transforms.js` builds the trigger
from only the modal attributes — `style`, `backgroundColor`, `borderColor`, `layout`
and `className` are dropped — and the block has no colour, spacing or border supports
to receive them anyway (`"supports": { "html": false, "anchor": true, "layout": false }`).
The employee-card pattern in a Query Loop, which is the feature's best use case, comes
out unstyled. This is a regression introduced when styling supports were removed from
the block to stop it competing with the chrome Group.

### Why the block cannot be fixed in place

The block has two stable configurations and neither is acceptable:

- **With styling supports** — it is a Group with extra attributes. Two blocks that look
  and behave alike, and the author must know which to reach for.
- **Without styling supports** — the card pattern is dead, which is defect 3.

## Decision

Two mechanisms, not three:

| Author intent                             | Mechanism                     |
| ----------------------------------------- | ----------------------------- |
| A highlighted chunk of text opens a modal | RichText format (unchanged)   |
| A button / group / image opens a modal    | Block attribute on that block |

A text selection cannot carry attributes, so the format stays. Everything else becomes
a property of the block the author already has.

This is what WordPress does. There is no "Link Block" — you select a thing and set a
link. The Navigation Overlay keeps its settings on the Navigation block. The plugin
already has this shape in `button-modal-extension.js` and
`group-modal-trigger-extension.js`; this finishes the job rather than starting a new one.

### Rejected: keep the block, drop its wrapper

Removing the `save()` wrapper does work — verified by publishing wrapperless trigger
markup, after which `render.php` (which targets _the first tag_, not "my wrapper", and
needs no changes) decorated the child Group directly, preserving its background, border,
radius and padding, and a body click opened the modal.

It is rejected because it fixes defects 2 and 3 while introducing a worse one: a block
that emits no markup of its own and silently makes only its **first** child clickable.
Add a second block and it quietly is not a trigger, with nothing in the editor to say
so. Silent partial failure is the worst failure mode for an author, and the editor
would show a nesting level that does not exist on the front end.

### Rejected as an alternative, adopted as an addition: block variations

Variations were considered as a way to avoid coupling to core block updates. They do
not provide that:

- A variation **is** the core block. `core/group` with preset attributes. If core
  changes Group, the variation changes identically. There is no insulation.
- A variation **cannot declare new attributes** — it only pre-fills attributes the block
  type already has, so the `blocks.registerBlockType` filter is still required.
- A variation is **not visible server-side**. WordPress has `variation_callback` and
  `get_variations()` for registering them, but the variation name is never serialized
  into saved content — a variation of Group saves as plain `<!-- wp:group {…} -->`.
  `render_block` must key on the attribute regardless.

They are adopted for a different reason: a variation supplies an inserter entry with its
own title, icon and description, which answers the one genuine cost of the extension
model — an inspector toggle does not announce itself the way a block in the inserter
does. **Extension for behaviour, variation for discovery.**

## Architecture

### Attributes

One namespace across every supported block, replacing the current split between
`pikariOpenInModal` (button) and `pikariModalTrigger` (group):

| Attribute                    | Type   | Purpose                                      |
| ---------------------------- | ------ | -------------------------------------------- |
| `pikariModalAction`          | string | `''` (off), `'open'`, `'close'`              |
| `pikariModalContentSource`   | string | `link` \| `url` \| `inline`                  |
| `pikariModalDirectUrl`       | string | URL mode target                              |
| `pikariModalPrimaryLinkId`   | string | Detected-link identifier (JSON)              |
| `pikariModalInlineAnchor`    | string | Inline mode anchor                           |
| `pikariModalSize`            | string | Size slug                                    |
| `pikariModalPlacement`       | string | `''` \| `left` \| `right`                    |
| `pikariModalTemplatePart`    | string | Template part slug                           |
| `pikariModalAccessibleLabel` | string | Author override for the trigger's aria-label |

`pikariModalAction` replaces two boolean attributes with one tri-state, which also
gives close-mode triggers a home on ordinary blocks instead of needing a dedicated
block inside the template part.

### Supported blocks

`core/group`, `core/button`, `core/image`. The link-detection utility
(`find-links-in-blocks.js`) already understands button, image, navigation-link, heading,
paragraph, post-title, post-featured-image, post-date, read-more and post-excerpt as
_inner_ link sources, and that is unchanged — it is how a Group finds its primary link.

Filterable via a new `pikari_gutenberg_modals_trigger_blocks` filter.

### Editor

- `blocks.registerBlockType` filter adds the attributes to the supported blocks.
- `editor.BlockEdit` filter adds one **Modal** panel to InspectorControls, shown for
  supported blocks, collapsed until `pikariModalAction` is set.
- `registerBlockVariation` adds inserter entries: **Clickable Card** (`core/group`),
  **Modal Button** (`core/button`). `isActive` keys on
  `pikariModalAction === 'open'` so the editor labels them correctly in list view.

### Server

`render_block` decorates **the block's own root element** — no wrapper is emitted. The
existing `BlockSupport::filter_block()` and `GroupModalTriggerSupport` already work this
way; they gain the unified attribute names and lose the modal-trigger block branch.

Two-phase rendering for Query Loop support in `GroupModalTriggerSupport` is unchanged —
it is the reason the card pattern works inside a loop and is unaffected by this change.

### The `a[href]` fix — independent, do it regardless

Narrow the ignore list in `handleGroupTriggerClick`:

```js
'a[href]:not(.is-primary-link), button, input, select, textarea, [role="button"]';
```

An anchor with no `href` is not a link: it is not focusable, and there is no navigation
to preserve. The ignore list exists to protect genuine nested links, and this keeps that
protection intact for query-loop cards while letting a plain core Button act as a
trigger. This is a one-line change with its own unit test and does not depend on the
rest of this design.

## What is removed

- The `pikari-gutenberg-modals/modal-trigger` block, its `render.php`, `edit.js`,
  `transforms.js`, `style.css` and `editor.css`.
- The `core/group ↔ modal-trigger` transforms — with the trigger being a property, there
  is nothing to transform.
- The close-mode branch of the trigger block, replaced by `pikariModalAction: 'close'`.

`parts/modal.html` is rewritten so the close row uses a `core/button` with
`pikariModalAction: 'close'` instead of a `modal-trigger` block. The three starter
patterns from the overlay-templates plan are authored the same way.

**Migration: none.** The user has confirmed the block is in use on a single page of one
site, easier to rebuild than to migrate. Existing `modal-trigger` blocks will render as
an unrecognised block. No deprecation shim.

## Impact on the modal-overlay-templates plan

`docs/superpowers/plans/2026-09-07-modal-overlay-templates.md` assumes **four** trigger
surfaces including the block. It becomes three (group, button, image extensions) plus
the inline format, and its Task 10 simplifies — the shared `ModalTemplatePanel` is
rendered by the one Modal inspector panel rather than wired into four separate places.
That plan should be amended after this design is approved, not before.

The `modal-dialog → modal-overlay` rename, the starter patterns, and the entity-backed
template panel are all unaffected in substance.

## Testing

**Pure logic, Jest** — the same constraint as the overlay-templates work applies: the
`@wordpress/*` editor packages are not installed and cannot be resolved by Jest, so any
logic needing unit tests lives in a module importing nothing but `@wordpress/i18n`.

- The click ignore-list predicate, extracted as a pure function: an href-less anchor is
  not ignored; an anchor with an href is; a `.is-primary-link` anchor is not; buttons and
  inputs are.
- Attribute resolution: which content source wins, and what an unset action means.

**PHPUnit** — `render_block` decoration for each supported block and content source;
close-mode decoration; the `pikari_gutenberg_modals_trigger_blocks` filter.

**Browser, via Playwright** — this is now first-class, not a fallback. The MCP driver
runs a real foreground browser (`visibility: visible`, `hasFocus: true`,
`requestAnimationFrame` fires, `setViewportSize` relayouts), which is what made the
#441 follow-up possible. Cover: a styled Group card opens and keeps its styling; a plain
Button opens; a Button with a real link inside a Group card still navigates rather than
opening; focus enters the dialog; the card pattern works inside a Query Loop.

## Open questions

1. **`core/image` — worth including in the first pass?** It is in the link-detection
   list already, but an image trigger has no obvious affordance and may want a cursor
   or focus style the other two get for free from their own block styles.
2. **What replaces the block's inserter entry for people who learned it?** The two
   variations cover group and button. Nothing covers "I want a modal, where do I start" —
   a starter pattern may be the better answer than a third variation.
