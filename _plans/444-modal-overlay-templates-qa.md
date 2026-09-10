# QA script — modal overlay templates (#444)

Branch `feature/modal-overlay-templates`. Produced by the whole-branch review, which
found no Critical issues but could verify nothing in a running editor: **no part of this
branch has ever been opened in a browser**, because the editor needs a login. Static
reasoning, unit tests and live REST probes are all that stand behind it.

Automated state at the time of writing: 91 PHP tests, 130 JS tests, production build,
`composer lint`, `npm run lint:js`, `npm run lint:css` — all green. `lint:md:docs` fails
on 26 pre-existing markdown violations (todo #443), unchanged in count by this branch.

Run on a block theme (Twenty Twenty-Five) in wp-env, as an administrator unless a step
says otherwise.

## The two steps that gate merge

Everything else is confirmation. These two are the checks that could still overturn the
static reasoning:

- [ ] **2. The preview renders real content.** Insert a Group → Block settings → Modal →
      Action = _Open a modal_. The preview box below the select should show the white
      chrome card with a Close button and the Content Area placeholder — not an empty
      200px box. A blank box means the reasoning about `content` arriving as a string
      (rather than `{ raw, block_version }`) is wrong.
- [ ] **3. Pattern tiles select when you click the thumbnail.** Click `+` → three tiles
      with rendered previews. Click the **thumbnail**, not the caption, on "Right panel".
      The tile should gain a blue selected border. The preview iframe is rendered with
      `pointer-events: none`, so clicks should fall through to the button — if they do
      not, the picker is unusable by mouse.

## Full script, in order

- [ ] **1. Panel appears at all.** New post → Group → Modal → Action = _Open a modal_.
      Expect: a "Modal template" heading, a `+` button top-right, a select reading
      **Default**, a pencil Edit button, and a preview.
- [ ] **2.** See above.
- [ ] **3.** See above.
- [ ] **4. Blank name.** Leave Name empty → Create. Expect a part titled **"Modal 2"**,
      slug `modal-2`. Wrong: anything that shows up as "Default".
- [ ] **5. A name that cleans to `modal`.** Name = `MODAL!` → Create. Expect slug
      `wp-custom-part` (check Site Editor → Patterns → Template Parts). Wrong: a second
      part with slug `modal`, which would masquerade as the default.
- [ ] **6. Post-create settle.** Immediately after Create, watch the select for ~1s. It
      should land on the new template's name. Note any flash of blank or "Default" —
      known, self-correcting, but judge whether it reads as a glitch.
- [ ] **7. Edit round-trip.** Type into the post body and leave it **unsaved** → click
      the pencil. Expect focus mode on the template part with a Back arrow; go Back and
      confirm your unsaved text survived. This is the behaviour the plan flagged as
      unobserved — if it discards the post, report it rather than working around it.
- [ ] **8. Inline format heading order.** Select text → Cmd+M. The popover's own heading
      is `h4` and the panel now renders `h5` beneath it. Confirm the order reads
      correctly to a screen reader, and note that the h5 has no dedicated CSS so it
      renders smaller than the inspector's h3 — expected, but check it does not look
      broken.
- [ ] **9. Inline re-apply persists.** On an **already-active** inline trigger, change
      the template from the popover. Save, reload, inspect the markup for
      `data-modal-template-part="…"`. Wrong: the attribute is missing — that was a real
      defect the plan would have shipped, and this is its regression check.
- [ ] **10. Stale carry-over.** Apply a trigger using template X → click into plain text
      in the same paragraph → select new text → Cmd+M → apply. Check whether the new
      trigger silently inherited X. Pre-existing behaviour for `size` and
      `contentSource`; this branch extends the pattern to the template field.
- [ ] **11. Non-admin.** Log in as an **Editor**. The select should populate (reading is
      allowed — verified by REST probe), but the `+` and pencil should NOT appear. This
      is the capability gating added in the final fix wave; before it, an Editor got a
      raw _"Sorry, you are not allowed to access the templates on this site."_ Watch
      also for the known one-paint delay before the controls appear for an admin.
- [ ] **12. Hybrid theme.** Switch to a classic theme declaring `block-template-parts`
      support. Expect select only — no `+`, no pencil, no preview — populated from the
      localized array, and **zero console errors**. Confirm the select is not stuck
      disabled.
- [ ] **13. Site Editor.** Edit the modal template part. The close-trigger toolbar button
      should still appear for inline text, and Modal Close Button / Modal Content Area
      should be insertable — this exercises the renamed `ancestor` arrays in a real
      inserter, which no test covers.
- [ ] **14. Frontend smoke.** Point a trigger at a newly created template, view the page,
      open the modal. Confirm the right container renders and Close works.

## Do not chase this

The panel's prominent **"Create modal template"** empty state can never render. The
plugin always provides a synthetic default template part, so `parts` is never empty on a
block theme, and PHP injects a default on hybrid themes too. The small `+` is the create
affordance. The dead branch was kept deliberately for fidelity to the spec's two-state
design and is documented as a gotcha in `CLAUDE.md`.
