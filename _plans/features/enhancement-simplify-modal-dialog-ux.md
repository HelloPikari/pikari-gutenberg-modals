# Spec: Simplify Modal Dialog Block UX

## Context

## Problem

## Future Enhancements

### 1. Live Overlay Preview in Editor

Currently, the overlay is only visible on the frontend. Users must save, switch to the frontend, and reload to see how their overlay looks. A live preview in the editor would show the overlay color/image behind the dialog chrome as users make changes, dramatically improving the design workflow.

### 2. Dialog Positioning

Add an option to the Modal Dialog block to control where the dialog chrome appears on screen:

- **Center** (current default) — dialog centered vertically and horizontally
- **Top** — dialog anchored to the top of the viewport (slide-down drawer pattern)
- **Bottom** — dialog anchored to the bottom (bottom sheet pattern)
- **Additional options** could include left/right side panels or fullscreen

This would be exposed as a simple visual picker in the Modal Dialog inspector — select a position and the dialog moves accordingly.

## Open Questions

1. For the overlay preview, should it be always-on in the editor or toggled?
2. What dialog positions should be included in the initial positioning enhancement?
