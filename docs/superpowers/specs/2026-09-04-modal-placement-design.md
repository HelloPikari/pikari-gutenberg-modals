# Modal placement — design

**Status:** approved in principle, pending spec review
**Date:** 2026-09-04

## Problem

A modal is currently always a centered dialog. The Kindler and Company build needs
two presentations from the same mechanism: a traditional centered dialog for a
YouTube video, and a right-hand slide-in panel holding a contact form.

The slide-in is not a different feature. It is a dialog — `role="dialog"`,
`aria-modal="true"`, focus trapped inside, background inert, Escape closes, focus
restored to the trigger on close. Every one of those behaviours already exists and
is unchanged. What differs is where the dialog sits and how it arrives.

## Decisions

### Placement lives on the trigger, alongside size

A `placement` attribute on the Modal Trigger block, defaulting to centered. It
follows the path `size` already takes:

```
block attribute  →  data-wp-context  →  data-* on the container  →  CSS
modalSize            $context['size']    data-size                  .modal-overlay[data-size]
placement            $context['placement'] data-placement           .modal-overlay[data-placement]
```

No new plumbing. The store already writes `data-size` at `modal-store.js:162`;
`data-placement` is written the same way at the same point.

Rejected: making placement a property of the modal-dialog block or of a template
part. Chrome is already a separate axis (see below), and an author wants to choose
presentation per trigger without authoring a new template part each time.

### Size becomes contextual

"Size" means a different dimension per placement, so a single Small/Large/Fullscreen
list is wrong once panels exist.

| Placement | Slug | Size means | Options |
| --- | --- | --- | --- |
| Centered *(default)* | `""` | max-width | Default / Small / Large / Fullscreen — unchanged |
| Left edge | `left` | panel width; height is always full | Narrow / Default / Wide |
| Right edge | `right` | panel width; height is always full | Narrow / Default / Wide |

Placement slugs follow the `modalSize` convention: empty string means default, so
`data-placement` is omitted entirely for a centered modal and existing CSS is
untouched.

Panel width slugs are `narrow`, `""` (default) and `wide`, resolving to:

```css
--modal-panel-width-narrow: 320px;
--modal-panel-width:        420px;  /* default */
--modal-panel-width-wide:   600px;
```

Below `--modal-panel-width` + a margin, a panel goes full-width rather than leaving
an unusable sliver — the same breakpoint behaviour the centered dialog already has.

The control relabels itself — **Size** when centered, **Panel width** on an edge —
and swaps its option list. One attribute underneath; its meaning is
placement-relative.

**Scope:** left and right only. Top and bottom sheets are a natural extension of the
same attribute but nothing needs them yet, and each costs its own size vocabulary
(height rather than width).

### Placement owns geometry; template parts own chrome

An author can point a trigger at any template part, so they can pick the centered
part and set placement to right. The centered chrome carries `border-radius: 20px`
and `max-height: 90vh`, which are wrong on a full-height panel.

The boundary that prevents this:

- **Placement CSS owns geometry** — position, width and height, border-radius,
  max-height. `[data-placement="right"]` unsets what does not apply.
- **Template parts own chrome and content** — close button placement, padding,
  background, and the content itself.

So any template part works in either placement. Template parts stay about look and
reusable content — a site-wide newsletter form, a booking form — rather than
geometry. Per-page modals via the Modal Content block get placement for free, since
they resolve to the same container.

### Two customisation surfaces, unchanged in kind

The plugin already separates *which options appear* from *what they measure*, and
panels mirror it exactly:

| Surface | Controls | Existing | New |
| --- | --- | --- | --- |
| PHP filter | which options appear in the dropdown | `pikari_gutenberg_modals_modal_sizes` | `pikari_gutenberg_modals_panel_widths` |
| CSS custom properties | what each option measures | `--modal-max-width{,-small,-large}` | `--modal-panel-width{,-narrow,-wide}` |

A theme author who wants "Narrow" to mean 280px writes one CSS line and touches
neither a filter nor the admin.

**A sibling filter, not a parameterised one.** Adding a `$placement` argument to
`pikari_gutenberg_modals_modal_sizes` would be one concept rather than two, but the
editor needs both lists at once, so `pikariGutenbergModals.modalSizes` would change
from a flat array to a keyed object. That is a documented, released interface. The
sibling costs a few lines of duplication; parameterising costs a breaking change.

## Components touched

| File | Change |
| --- | --- |
| `src/blocks/modal-trigger/block.json` | `placement` attribute, string, default `""` (centered) |
| `src/blocks/modal-trigger/edit.js` | Placement control; Size control relabels and swaps options |
| `src/blocks/modal-trigger/render.php` | `$context['placement']` — **three sites** (url, inline, link branches) |
| `src/frontend/modal-store.js` | write/remove `data-placement` beside `data-size` |
| `src/blocks/modal-dialog/style.css` | placement geometry, panel widths, slide animations, custom properties |
| `includes/EditorIntegration.php` | `get_panel_widths()` + filter; localised |
| `CLAUDE.md`, `readme.txt`, `README.md` | new filter and properties |

`render.php` builds the context in three parallel branches. Adding placement means
three near-identical edits. Worth extracting a small helper in the same change
rather than a fourth copy later — but a helper, not an abstraction layer.

## Motion

Slide-from-edge on open, reverse on close, matching the existing 300ms in / 200ms
out.

**A prerequisite bug, fixed separately first.** `prefers-reduced-motion` does not
currently work. `modal-dialog/style.css:192` targets `.modal-entering` and
`.modal-leaving`; those class names appear nowhere else in the tree. The store
applies `is-open` and `is-closing` (`modal-store.js:157,363`). So the override
matches nothing and reduced-motion users get the full fade-and-scale today.

That is a live accessibility defect independent of this feature. It ships as
its own PR before this one. Once the selectors are correct, panels are covered by the same block.

**Known limitation, accepted:** `closeModal` holds a hardcoded
`setTimeout(…, 200)` matching the exit animation. With animations disabled the modal
stays visible for that 200ms. Imperceptible; not worth coupling JS to media queries.

**Open, minor:** whether reduced motion means *no* transition or a plain fade. A
full-height panel appearing instantly may feel abrupt, and a fade is
motion-free in the vestibular sense. Defaulting to none; revisit if it reads badly.

## Accessibility

Unchanged. Same `role="dialog"`, `aria-modal="true"`, focus trap, inert background,
Escape handling and focus restoration. Panels are dialogs that happen to be against
an edge. The mockup dims the page behind, so it is modal in the ARIA sense,
not a dismissible non-modal drawer.

Related, folded in: the dialog element's own `aria-label` is the generic "Modal
dialog" even when the trigger has a good accessible name — measured on the Kindler
install, where a trigger named "Watch the Talks" opens a dialog named "Modal dialog".
The trigger naming is correct; only the dialog is generic.

## Backwards compatibility

Existing content has no `placement`, so it defaults to centered and behaves exactly
as today. No migration, no deprecation. `modalSizes` and its filter are untouched.

## Testing

- **jest** — placement/size option resolution as a pure function, the way
  `video-providers` is tested. Tests first.
- **jest, DOM** — the store writes and clears `data-placement` alongside `data-size`,
  including switching between two triggers with different placements.
- **Manual, in a browser** — focus trap and Escape in panel placement; reduced-motion
  with the OS setting on; a centered template part used in panel placement, to prove
  the geometry boundary holds.

`render.php` has no unit-test harness, so the editor and render changes are
verified in the editor rather than covered by tests. Stated here rather than
discovered later.

## Out of scope

- Top and bottom sheets.
- Non-modal (dismissible, background-interactive) drawers.
- Per-trigger custom pixel widths — CSS custom properties already cover this without
  adding admin UI.
- The `git tag -f` release-tagging problem, tracked separately.
