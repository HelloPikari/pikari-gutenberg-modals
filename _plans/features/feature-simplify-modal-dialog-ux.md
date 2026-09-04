# Spec: Simplify Modal Dialog Block UX

## Context

The Modal Dialog block currently serves two purposes: controlling the **overlay** (the fullscreen backdrop behind the dialog) and styling the **dialog chrome** (the dialog box's background, border, padding, and shadow). This dual responsibility is confusing for users who already know how to style blocks like Group and Cover. By separating these concerns, we let users design modal chrome with familiar tools while giving the Modal Dialog block a clearer, focused purpose.

## Problem

When users open the Modal Dialog block's inspector panel, they see a mix of overlay controls (overlay color, background image) and dialog chrome controls (background color, border, padding, shadow). It's not immediately clear which controls affect the backdrop vs. the dialog box itself. The distinction between "overlay color" and "background color" is subtle and easy to confuse.

Users already understand how to style Group and Cover blocks — they use these daily for page layouts. Forcing them to learn a separate, similar set of controls inside Modal Dialog adds unnecessary friction.

## Vision

**Modal Dialog becomes a focused "stage manager"** — it controls the environment the dialog sits in (the overlay backdrop) and where the dialog appears on screen. The actual dialog box design is handled by a standard core block (Group or Cover) that users already know.

### What Modal Dialog Would Own

1. **Overlay appearance** — background image, overlay color/gradient with opacity
2. **Dialog positioning** (future) — where the dialog chrome sits on screen (centered, anchored to top, bottom, or sides)
3. **Structural role** — the outermost modal container that manages open/close behavior, focus trapping, and accessibility

### What Moves to Inner Blocks

The dialog's visual design — background color, border, border radius, padding, and shadow — would be handled by a core/group (or core/cover) block nested inside the Modal Dialog. Users would style this inner block exactly as they would anywhere else in their site.

## How It Works for Users

### Current Experience

1. User edits the modal template part
2. Sees a Modal Dialog block with many inspector controls
3. Must learn which controls affect the overlay vs. the dialog box
4. Has limited flexibility — only the options Modal Dialog exposes

### Proposed Experience

1. User edits the modal template part
2. Sees a Modal Dialog block with only overlay controls (simple, clear purpose)
3. Inside it, sees a familiar Group block that wraps the close button and content area
4. Styles the dialog chrome using the Group block's standard controls — background, border, padding, shadow, and everything else Group supports
5. Gets the full power of core blocks (e.g., can swap Group for Cover to add media backgrounds)

### Default Template Part

The default `parts/modal.html` template would ship with:

- **Modal Dialog** (overlay controls only)
  - **Group block** (styled as the dialog box — white background, rounded corners, shadow, padding)
    - Close button row
    - Content Area

Users can swap the Group for a Cover block or any other container block if they want more design options.

## Benefits

- **Familiar tools** — Users style the dialog box the same way they style every other container on their site
- **More flexibility** — Group and Cover blocks offer features we'd never replicate in a custom block (layout options, media backgrounds, inner block flexibility)
- **Simpler Modal Dialog** — Fewer controls with a clear, singular purpose
- **Less maintenance** — We don't need to maintain custom inspector controls that duplicate what core blocks already provide
- **Better forward compatibility** — As WordPress adds new features to Group/Cover, modal chrome automatically gets them

## Migration Considerations

- Existing modal template parts would continue to work — the current block supports on Modal Dialog wouldn't break, they'd just be deprecated in favor of the inner Group block approach
- The default template part (`parts/modal.html`) would ship with the new structure for new installations
- Documentation and examples would guide users to the new pattern
- A deprecation notice could appear when users have chrome-related styles directly on the Modal Dialog block

## Decisions

- **Default inner block**: core/group — simpler, lighter, and familiar to all users. Cover can be documented as an alternative for advanced use cases.
- **Migration**: Deprecation notice only — old templates keep working, editor shows a notice suggesting users move chrome styles to an inner Group block. No automatic migration.
