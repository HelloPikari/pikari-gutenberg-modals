# QA — modal overlay templates (#444)

Merged to `main` (fast-forward, 6097a1e → 3486c1b). The whole-branch review found no
Critical issues and named two steps as gating the merge; **both passed in a real
browser**.

**10 of 14 steps verified** by driving wp-env with Playwright, as admin and again as a
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

- [x] **8. Inline format heading order.** Opened the popover via the real UI route
      (block toolbar → More → "Modal Trigger"). Headings in DOM order are
      `H4: "Add Modal Trigger"` then `H5: "Modal template"` — correct hierarchy, so the
      inversion fix works. The popover also correctly suppresses create and preview.
- [x] **9. Inline re-apply persists.** Applied an inline trigger with template
      "Modal #2", then reopened it and switched to Default: the serialized markup
      updated immediately (`data-modal-template-part="modal-2"` gone). Without the
      re-apply fix this change would have been silently dropped, so this is the
      regression check passing.

Incidental: the Group's parent-block label reads **"Clickable Card"**, so the block
variation resolves in a real editor.

**Separate bug found while doing this (pre-existing, not from #444):** the documented
Cmd/Ctrl+M shortcut for the inline trigger does nothing. The toolbar route is the only
way in. Tracked as todo #472.

## Still to check by hand

Four left. Written plainly, because the earlier version of this list assumed too much
context.

- [ ] **7. Does clicking the pencil lose your unsaved work?**
      Start a new post and type a sentence. Do NOT save. Add a Group, set it to open a
      modal, then click the pencil next to the Modal template dropdown. WordPress should
      switch to editing the modal template itself, with a back arrow. Press back.
      **Good:** your unsaved sentence is still there. **Bad:** it is gone — tell me
      rather than working around it, because the fix is a design decision.

- [ ] **10. Does a second inline trigger inherit the first one's template?**
      In one paragraph, highlight a word and make it a modal trigger (toolbar → More →
      Modal Trigger), setting its Modal template to something other than Default.
      Then highlight a _different_ word in the same paragraph and make that a trigger
      too. **Good:** the second one starts on Default. **Bad:** it silently starts on
      the first one's template. Either way it is pre-existing behaviour — the Modal Size
      field does the same thing — so this is information, not a blocker.

- [ ] **12. Does a classic theme still work?**
      Only relevant if you have a classic theme that declares `block-template-parts`
      support. Switch to it, then look at a modal trigger's Modal panel. **Good:** you
      see the dropdown only — no `+`, no pencil, no preview — and nothing errors in the
      browser console. I skipped this because wp-env has only block themes installed.

- [ ] **13. Site Editor inserter.**
      Edit the modal template part in the Site Editor. **Good:** you can still insert
      Modal Close Button and Modal Content Area inside it, and the close-trigger toolbar
      button still appears when you select inline text. This is the one thing that would
      break silently from the block rename, and no test covers it.

## Do not chase this

The panel's prominent **"Create modal template"** empty state can never render: the
plugin always provides a synthetic default template part, so `parts` is never empty on a
block theme, and PHP injects a default on hybrid themes too. The small `+` is the create
affordance. The dead branch was kept deliberately for fidelity to the spec's two-state
design and is documented as a gotcha in `CLAUDE.md`.
