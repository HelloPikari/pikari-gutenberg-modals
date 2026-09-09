# Todo #441 — Modal placement browser QA

**Date:** 2026-09-07
**Branch:** `feature/modal-placement` @ `ae1ea8a` (14 commits on top of `main` @ `33637d1`, 0 behind)
**Environment:** wp-env from `_worktrees/modals-placement`, WordPress 7.1, Twenty Twenty-Five, PHP 8.4
**Verdict:** no defects found. Three checks could not be completed — see "Not verified".

## Automated baseline

`npm test` **74/74**, `composer test` **48/48 (79 assertions)**.

The todo recorded 78 / 49 / 80. The branch has been rebased since it was written
(it was "20 commits off main", it is now 14), which accounts for the drift. Nothing
is failing or skipped.

## Method

Test content was authored as block markup and published through `wp post create`,
then measured with `getBoundingClientRect()` and `getComputedStyle()` — not eyeballed.
Ten triggers on one page (`?page_id=5`), all `contentSource: url` except T10, plus a
mu-plugin registering a custom size slug and a custom panel-width slug with matching
site CSS.

**Two harness artifacts had to be worked around, and both initially looked like
critical bugs.** The automated tab runs backgrounded (`document.visibilityState:
"hidden"`), so Chrome freezes the animation timeline at `currentTime: 0` **and**
never fires `requestAnimationFrame`. The first consequence made every panel measure
as fully off-screen — a right panel at x=1554→1974 in a 1554px viewport, which is
just its `from` keyframe. The second stopped focus entering the dialog, because all
three `focusFirstElement()` call sites are wrapped in `requestAnimationFrame`.
Neither is a product defect. Measurements below were taken after calling
`Animation.finish()` on the running animations.

## Results

Viewport 1554 x 1323 throughout.

| #   | Trigger                   | data-size        | data-placement | width | height | left → right | chrome radius | Verdict                  |
| --- | ------------------------- | ---------------- | -------------- | ----- | ------ | ------------ | ------------- | ------------------------ |
| T1  | centered default          | –                | –              | 1024  | 260    | 265 → 1289   | 20px          | centered, symmetric      |
| T2  | right panel               | –                | right          | 420   | 1323   | 1134 → 1554  | 0px           | flush right, full height |
| T3  | left panel                | –                | left           | 420   | 1323   | 0 → 420      | 0px           | flush left, full height  |
| T4  | right + narrow            | narrow           | right          | 320   | 1323   | 1234 → 1554  | 0px           | correct width            |
| T5  | right + wide              | wide             | right          | 600   | 1323   | 954 → 1554   | 0px           | correct width            |
| T6  | right + **fullscreen**    | **null**         | right          | 420   | 1323   | 1134 → 1554  | 0px           | **slug dropped**         |
| T7  | centered large            | large            | –              | 1200  | 124    | 177 → 1377   | 20px          | matches main             |
| T8  | centered custom slug      | pikari-qa-custom | –              | 777   | 124    | 389 → 1166   | 20px          | filter reaches CSS       |
| T9  | right + custom panel slug | pikari-qa-panel  | right          | 333   | 1323   | 1221 → 1554  | 0px           | filter reaches CSS       |
| T10 | detected link             | –                | –              | 1024  | 124    | 265 → 1289   | 20px          | opened, did not navigate |

### The specific things the todo asked for

**Right/left panels pinned, full height, configured width** — pass. Both sit flush
against their edge (1134→1554 and 0→420) at exactly 420px, full viewport height, with
`align-items: stretch` and `justify-content: flex-end`/`flex-start`. The chrome Group's
20px author radius is squared to 0 on panels, per the documented precedent.

**Centered modal unchanged from main** — pass. `--modal-max-width: 1024px`,
`-small: 500px`, `-large: 1200px` are byte-identical to `main`, the whole
`style.css` diff is `[data-placement]`-scoped, and T1/T7 measured 1024 and 1200.

**Fullscreen on a panel does not produce a full-width sheet** — pass, and this is the
important one. T6's context ships `{"size":"fullscreen","placement":"right"}`, and the
opened container carries `data-placement="right"` with **no `data-size` at all**. The
panel rendered at its default 420. `resolveGeometry()` dropped the foreign slug rather
than letting CSS sort it out.

**Custom size slug still reaches `data-size` and matches site CSS** — pass, both
directions. `pikari-qa-custom` (registered via `pikari_gutenberg_modals_modal_sizes`)
reached `data-size` and my site CSS `max-width: 777px` applied, measured 777.
`pikari-qa-panel` (via `pikari_gutenberg_modals_panel_widths`) reached a placed panel
and `width: 333px` applied, measured 333. This is the released-filter contract the
review flagged; it holds.

**Per-slug breakpoints at width + 48px** — verified statically in the **shipped**
`build/blocks/modal-dialog/style.css`, not live:

    @media (max-width: 468px) -> [data-placement] .modal-content { width: 100% }   (420 + 48)
    @media (max-width: 368px) -> [data-placement][data-size="narrow"]  { width: 100% }   (320 + 48)
    @media (max-width: 648px) -> [data-placement][data-size="wide"]    { width: 100% }   (600 + 48)

**Focus trap, Escape, inert background, focus restored** — pass except the first.
All six landmarks (`HEADER`, `NAV` x3, `MAIN`, `FOOTER`) take `inert` while open and
lose it on close. `body` overflow goes `hidden` → `visible`. Escape closes
(`display: none`). Focus returns to the originating trigger
(`restoredToTrigger: true`). Focus _entering_ the dialog could not be observed — see
below.

## Two checklist items are stale

**"Inspect `data-wp-context` on a Detected-Link trigger and confirm a non-empty
label."** No trigger path emits a `label` key. T10's rendered context is
`{"postId":"4","modalId":"page-4"}`, and that is correct: the detected-link branch
gives the wrapper `role="group"` with `aria-labelledby` pointing at the primary
link's own id, so the accessible name comes from the link text in the DOM.
`get_modifiable_text()` appears nowhere in shipped code — only in `ceca70f`, the
plan document. The bug was caught in plan review; the implementation never used
that approach. The check cannot pass as written and should be struck.

**"THE DECISION THAT NEEDS REAL ASSISTIVE TECH ... whether the aria-label work earns
its place in this PR."** There is no aria-label work in this PR. `accessibleLabel`
was introduced by `baf78c1`, which is already on `main`, and
`git diff main...HEAD -- src includes` contains zero `aria-label` or `labelledby`
changes. The VoiceOver/NVDA decision is real, but it is not scoped to #441 and
should not block this merge.

## Follow-up run with Playwright (2026-09-09)

Three of the four items below were originally handed off as "needs a human". That was
wrong: the Playwright MCP driver runs a real foreground browser
(`visibility: visible`, `hasFocus: true`, `requestAnimationFrame` fires,
`setViewportSize` relayouts), which removes every limitation that blocked them. They
were re-run and all pass.

**Focus enters the dialog on open — PASS.** Clicking a trigger opens the modal and
`document.activeElement` lands inside `.modal-content` (`focusInsideDialog: true`).

**Slide animation — PASS.** Traced per frame at a 1200px viewport, right panel:
`x=1200 op=0` → `x=995 op=0.48` @98ms → `x=845 op=0.84` @198ms → `x=780 op=1` @298ms,
then held. 1200 − 420 = 780. Real ~300ms slide with fade.

**Narrow-viewport breakpoints — PASS**, measured live:

| Viewport | Panel         | Width         | Result                         |
| -------- | ------------- | ------------- | ------------------------------ |
| 500      | default (420) | 420, left 80  | holds above the 468 breakpoint |
| 440      | default (420) | 440, left 0   | full width below 468           |
| 440      | narrow (320)  | 320, left 120 | holds above the 368 breakpoint |
| 350      | narrow (320)  | 350, left 0   | full width below 368           |
| 440      | wide (600)    | 440, left 0   | full width below 648           |

**Still open — overlay controls under `settings.color.custom: false`.** Editor-side,
needs a logged-in Site Editor session, and credentials cannot be entered. Genuinely
unverified in this session and the previous one.

## Defect found: a button inside a Modal Trigger is dead

Not a placement bug — it predates this branch — but found while running the above and
serious enough to record here.

`handleGroupTriggerClick` ignores clicks matching
`a:not(.is-primary-link), button, input, select, textarea, [role="button"]` so that
genuine nested links keep working. `render.php` only adds `is-primary-link` in the
**detected-link** content source. In `url` and `inline` modes it decorates the wrapper
and marks no inner anchor at all.

A core Button block renders `<a class="wp-block-button__link">` with **no `href`** when
no link is set. It therefore matches the ignore list, the handler bails, and because the
anchor has no `href` it does not navigate either. The button is inert.

Confirmed in Playwright: clicking the button returned `is-open: false`; adding
`is-primary-link` to that same anchor and clicking again returned `is-open: true`.

The anchor has `href: null`. An anchor with no `href` is not a link — it is not
focusable and has no navigation to preserve — so the ignore list has nothing to protect
in this case. That is the seam for a fix, rather than `pointer-events: none`.

## Environment left in place

The wp-env for `_worktrees/modals-placement` is running on :5888 so the manual checks
can continue without setup. It contains:

- page **4** "Modal Target" — the modal payload
- page **5** "QA 441" — the ten triggers, in the table order above
- `wp-content/mu-plugins/qa-441-mu.php` — registers `pikari-qa-custom` /
  `pikari-qa-panel` and their site CSS

Remove with `wp post delete 4 5 --force` and by deleting the mu-plugin. The worktree
itself is clean — the two scratch files used to author the content were removed.

Note: the previously running wp-env for `_worktrees/modals-chrome` was stopped to free
port 5888. `npx wp-env start` from that worktree brings it back; nothing was destroyed.
