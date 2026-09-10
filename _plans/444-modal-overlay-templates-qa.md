# QA — modal overlay templates (#444)

Merged to `main` (fast-forward, 6097a1e → 3486c1b). The whole-branch review found no
Critical issues and named two steps as gating the merge; **both passed in a real
browser**.

**8 of 14 steps verified** by driving wp-env with Playwright, as admin and again as a
real Editor. Zero application console errors across the whole session — the only noise
was a favicon 404, plus a `/wp/v2/settings` 403 for the Editor, which is core's own
behaviour for non-admins.

Automated state: 91 PHP tests, 130 JS tests, production build, `composer lint`,
`lint:js`, `lint:css` — all green. `lint:md:docs` fails on 26 pre-existing markdown
violations (todo #443), unchanged in count by this branch.

Test environment: wp-env on Twenty Twenty-Five, admin unless a step says otherwise.

## Verified

- [x] **1. Panel appears.** Renders an `h3` "Modal template", a select reading
      **Default**, the Create (`+`) and Edit (pencil) buttons, and a preview. Both Edit
      and preview appear on the _default_ selection — that is the Critical fix working;
      before it they were permanently hidden on any site whose only template is the
      default.
- [x] **2. The preview renders real content.** _(merge gate)_ The iframe contains the
      real chrome card — "Close / Modal Content Area", 6 blocks, `.modal-chrome` and the
      close button present, 202px tall, no stuck spinner. Confirms `content` arrives as
      a string, which was reasoning, not observation, until now.
- [x] **3. Pattern tiles select on thumbnail click.** _(merge gate)_ Clicking the
      _iframe_ of the "Right panel" tile moved `aria-pressed` and applied `is-selected`,
      so `pointer-events: none` on the preview does let clicks through.
- [x] **4. Blank name.** Produced "Modal 2" / slug `modal-2`, and the select switched to
      it immediately — the thing the old localized array could not do.
- [x] **5. A name that cleans to `modal`.** `MODAL!` produced slug `wp-custom-part`,
      shown as "MODAL!" in the select. No masquerading as Default.
- [x] **6. Post-create settle.** No visible glitch; the select landed on the new
      template and the stored block attribute matched it.
- [x] **11. Non-admin.** As a real Editor: `canUser('create')` false, the select still
      populated with all three templates, and **both** the `+` and pencil absent while
      the preview remained. The capability gating added in the final fix wave works for
      the role it was written for.
- [x] **14. Frontend smoke.** Clicking the card opened `pikari-modal--wp-custom-part` —
      the container for the part created through the new panel, not the default — with
      `aria-label="example.com"`, while the untouched default container still read the
      generic "Modal dialog". No `modal-dialog` class anywhere in the rendered page.

Incidental: the Group's parent-block label reads **"Clickable Card"**, so the block
variation resolves in a real editor.

## Still to check by hand

- [ ] **7. Edit round-trip.** Type into the post body, leave it **unsaved**, click the
      pencil. Expect focus mode on the template part with a Back arrow; go Back and
      confirm the unsaved text survived. The plan flagged this behaviour as unobserved —
      if it discards the post, report it rather than working around it.
- [ ] **8. Inline format heading order.** I could not open the inline-format popover
      through automation: synthetic text selection does not trigger the RichText
      shortcut. The code path is statically confirmed (`modal-trigger-edit.js` passes
      `headingLevel={5}`), but nobody has seen it. Select text → Cmd+M, and check the
      popover's `h4` is followed by the panel's `h5`, and that the smaller heading does
      not look broken.
- [ ] **9. Inline re-apply persists.** On an **already-active** inline trigger, change
      the template from the popover, save, reload, and confirm
      `data-modal-template-part="…"` is in the markup. This is the regression check for
      a real defect the plan would otherwise have shipped.
- [ ] **10. Stale carry-over.** Apply a trigger using template X, click into plain text
      in the same paragraph, select new text, Cmd+M, apply — check whether the new
      trigger inherited X. Pre-existing behaviour for `size` and `contentSource`; this
      branch extends the pattern to the template field.
- [ ] **12. Hybrid theme.** Switch to a classic theme declaring `block-template-parts`
      support. Expect select only — no `+`, no pencil, no preview — populated from the
      localized array, zero console errors, and the select **not** stuck disabled.
- [ ] **13. Site Editor.** Edit the modal template part: the close-trigger toolbar
      button should still appear for inline text, and Modal Close Button / Modal Content
      Area should be insertable. This exercises the renamed `ancestor` arrays in a real
      inserter, which no test covers.

## Do not chase this

The panel's prominent **"Create modal template"** empty state can never render: the
plugin always provides a synthetic default template part, so `parts` is never empty on a
block theme, and PHP injects a default on hybrid themes too. The small `+` is the create
affordance. The dead branch was kept deliberately for fidelity to the spec's two-state
design and is documented as a gotcha in `CLAUDE.md`.
