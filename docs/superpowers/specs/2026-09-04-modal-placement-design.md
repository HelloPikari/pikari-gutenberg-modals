# Modal dialog architecture — placement, chrome and overlay

**Status:** design agreed, pending review
**Date:** 2026-09-04
**Adopts:** `_plans/features/feature-simplify-modal-dialog-ux.md` (Feb 2026)

## How this document came about

Two designs were written six months apart, independently, for overlapping problems.

In February a spec — *Simplify Modal Dialog Block UX* — argued that the Modal Dialog
block does two jobs at once, overlay **and** dialog-box styling, and that the second
should move to a standard `core/group` the author already knows how to style. It
listed, as a future item:

> **Dialog positioning** *(future)* — where the dialog chrome sits on screen
> (centered, anchored to top, bottom, or sides)

That work was started on `feature/simplify-modal-dialog-ux`, never finished, and
never pushed. It survived in one working copy until 2026-09-04.

In September, without knowledge of it, the same feature was designed again from the
Kindler requirement — a slide-in panel — and reached a *different* answer: placement
on the trigger, mirroring `size`.

This document reconciles them. The February direction is adopted; the September
placement work is rebased on top of it.

## The reconciliation

### Where positioning lives

February said the Modal Dialog block. September said the trigger. The deciding
argument only becomes visible once chrome moves out.

Once chrome is a `core/group`, the author already controls its background, radius,
padding, shadow and its own dimensions with core controls. What a Group **cannot**
express is being pinned to a viewport edge at full height with a slide-in
transition. That is container-level, so it belongs to the container.

The September argument — "one template part should serve both a centered video and a
right-hand panel" — turns out weaker than it looked. Those two cases want *different
chrome anyway*: the Kindler panel is a cream full-height column containing a form;
the video modal is a rounded box. In practice they are two template parts
regardless, so per-part positioning costs nothing there.

**Decision: placement is an attribute of the Modal Dialog block, defaulting to
centered. The trigger keeps an optional override.**

The override is retained rather than added, because the trigger *already* overrides
`size`. Removing that would be its own breaking change with no benefit here, and
placement being overridable while size is not would be arbitrary.

```
trigger value, if set  ->  else the Modal Dialog block's value  ->  else centered
```

Longer term `size` arguably belongs on Modal Dialog too, for the same reason
placement does — it is geometry, and geometry belongs to the stage manager. Noted,
not scheduled.

### What each layer owns after this work

| Layer | Owns |
| --- | --- |
| Modal Dialog block | overlay appearance, placement, structural and a11y role |
| `core/group.modal-chrome` | background, border, radius, padding, shadow |
| Content area and inner blocks | the content |
| Trigger | which modal, plus optional per-invocation overrides |

### The selector consequence

The September draft said placement CSS would unset `border-radius` and `max-height`
on `.modal-content`. After the February change those live on `.modal-chrome`,
authored in the template part as block styles.

This matters more than a rename: **placement CSS must not unset an author's block
styles**, because they are inline styles from the Group's own controls. So geometry
applies to the *container*, and the chrome Group fills it:

- `[data-placement="right"]` pins the container to the edge, full height, at the
  panel width.
- `.modal-chrome` inside a placed container stretches to fill.

On radius specifically, the earlier draft said placement must leave an author's
border-radius alone. Browser testing showed the codebase already disagrees: the
mobile and fullscreen rules use `border-radius: 0 !important` to beat the Group's
inline style, because a fullscreen dialog with rounded corners looks broken. An edge
panel is the same situation, so placement overrides radius the same way rather than
inventing a different rule for the same problem.

Everything else the author sets — background, padding, shadow — is left alone.
Placement owns the container's geometry and the geometry-dependent corners; the
author owns the box.

## Overlay controls

A theme setting `settings.color.custom: false` leaves the overlay unusable.
`useMultipleOriginColorsAndGradients()` correctly honours that, so the picker offers
palette swatches only — and with no separate opacity control there is no way to
produce a translucent backdrop.

`core/cover` keeps colour and opacity independent (`dimRatio`), so a locked-down
palette colour at 40% still works.

Modals is closer to cover than it looks: `overlayColor`, `overlayGradient`,
`backgroundImage`, `focalPoint` and `hasParallax` already exist, and the overlay is
already its own element (`span.modal-overlay-background`). The gap is only opacity.

**Decision: add an `overlayOpacity` attribute (0-100, default 100) with a
`RangeControl` beside the colour control, applied as `opacity` on the existing
overlay layer.** No restructuring, and it makes the block usable under a locked-down
theme, which is the actual requirement.

Full cover parity — duotone, fixed backgrounds — is explicitly out of scope.

## Sequencing

Three strands, in dependency order. Each ships separately.

1. **`prefers-reduced-motion` fix.** Independent, and a live defect on 1.3.0. Ships
   first, on its own.
2. **Modal Dialog simplification** — the February branch, rebased. Chrome moves to
   `core/group.modal-chrome`; overlay opacity lands at the same time, since both
   touch the same block and inspector.
3. **Placement** — on top of 2, so it targets settled markup.

Doing 3 before 2 means writing placement CSS against `.modal-content` and rewriting
it weeks later. That is the reason this document exists.

## The reduced-motion defect

`prefers-reduced-motion` does not currently work. `modal-dialog/style.css:192`
targets `.modal-entering` and `.modal-leaving`; those names appear nowhere else in
the tree. The store applies `is-open` and `is-closing` (`modal-store.js:157,363`).
The override matches nothing, so reduced-motion users get the full 300ms
fade-and-scale on 1.3.0 today.

**Known limitation, accepted:** `closeModal` holds a hardcoded `setTimeout(..., 200)`
matching the exit animation. With animations disabled the modal stays visible for
that 200ms. Imperceptible; not worth coupling JS to media queries.

**Open, minor:** whether reduced motion means *no* transition or a plain fade. A
full-height panel appearing instantly may feel abrupt. Defaulting to none.

## Placement detail

| Placement | Slug | Size means | Options |
| --- | --- | --- | --- |
| Centered *(default)* | `""` | max-width | Default / Small / Large / Fullscreen |
| Left edge | `left` | panel width, full height | Narrow / Default / Wide |
| Right edge | `right` | panel width, full height | Narrow / Default / Wide |

Empty string means default, so `data-placement` is omitted for a centered modal and
existing CSS is untouched.

```css
--modal-panel-width-narrow: 320px;
--modal-panel-width:        420px;  /* default */
--modal-panel-width-wide:   600px;
```

Below the panel width plus a margin a panel goes full-width, rather than leaving an
unusable sliver.

**Scope:** left and right only. Top and bottom are a natural extension of the same
attribute; nothing needs them yet.

### Customisation surfaces

The plugin separates *which options appear* from *what they measure*. Panels mirror
it:

| Surface | Existing | New |
| --- | --- | --- |
| PHP filter | `..._modal_sizes` | `..._panel_widths` |
| CSS custom properties | `--modal-max-width{,-small,-large}` | `--modal-panel-width{,-narrow,-wide}` |

A sibling filter rather than parameterising `..._modal_sizes`: the editor needs both
lists at once, so parameterising would change `pikariGutenbergModals.modalSizes` from
a flat array to a keyed object — a breaking change to released localised data, to
save a few lines of duplication.

## Breaking changes and live sites

Strand 2 is breaking, and browser testing sharpened what that means. The block keeps
its `color`, `border`, `spacing` and `shadow` supports, so nothing leaves its API and
author-set chrome still applies. What goes is the *fallback* chrome on
`.modal-content` — a free white background, 20px radius and shadow driven by
`--modal-content-bg`, `--modal-content-shadow` and `--modal-border-radius`.

Measured on a database-saved template part carrying the old markup, which is what an
upgraded site has:

    contentPadding:      24px              author's padding survives
    contentBackground:   rgba(0, 0, 0, 0)  was white
    contentBorderRadius: 0px               was 20px
    contentBoxShadow:    none              was a shadow

That is a transparent dialog with page content showing through the text — visually
broken rather than gracefully degraded. The modal still opens, traps focus and closes
correctly; it has no box.

Affected: a site with a **customised** modal template part. A site that never
customised it picks up the new `parts/modal.html` and is unaffected.

**A `:has()` fallback was considered and declined.** Scoping the old chrome to
`.modal-content:not(:has(.modal-chrome))` would make this non-breaking. Of the two
known installs, WinSuccession runs a beta version that would break more than this on
any update, and CCLF is fully controlled and gets fixed forward. A permanent
compatibility shim for a case that is already unprotectable is complexity with no
beneficiary.

Still warrants a **minor version bump and an explicit upgrade note**.

## Accessibility

Unchanged by placement. Same `role="dialog"`, `aria-modal="true"`, focus trap, inert
background, Escape and focus restoration. Panels are dialogs against an edge; the
backdrop dims, so they are modal in the ARIA sense, not dismissible drawers.

Folded in: the dialog element's own `aria-label` is the generic "Modal dialog" even
when the trigger has a good accessible name — measured on the Kindler install, where
a trigger named "Watch the Talks" opens a dialog named "Modal dialog". The trigger
naming is correct; only the dialog is generic.

## Testing

`render.php` has no unit-test harness, so browser verification is the coverage for
the render path, not a supplement to it.

- **jest** — placement and size resolution as a pure function, tests first.
- **jest, DOM** — the store writes and clears `data-placement` beside `data-size`,
  including switching between triggers with different placements.
- **Browser, measured** — panel pinned to edge at full height and configured width;
  centered unchanged; narrow viewport full-width; overlay opacity under a theme with
  `color.custom: false`; reduced motion emulated.
- **Browser, behavioural** — focus trap, Escape, inert background, focus restored.
- **By hand** — authoring flow, slide easing, real assistive tech.

## Out of scope

- Top and bottom sheets.
- Non-modal dismissible drawers.
- Full `core/cover` parity for the overlay.
- Moving `size` from the trigger to Modal Dialog.
- Per-trigger custom pixel widths — custom properties cover this without admin UI.
