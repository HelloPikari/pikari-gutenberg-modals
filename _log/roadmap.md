# Roadmap — pikari-gutenberg-modals

**Last updated:** 2026-09-09 (Session 2)

What we've done, at a glance. Narrative lives in the session logs.

## Released

~~External URL modals silently inert outside `local`~~ — ✅ DONE (Session 1).
`is_local_url()` read a `filter_var()` failure as "private IP", so every hostname was
classified local and `validate_url()` returned false on any site not typed `local` —
the default being `production`. Verified fixed on a production-typed site. Shipped in
v1.3.0. See PR #95.

~~Release ZIP could ship a mismatched version~~ — ✅ DONE (Session 1). v1.2.2 shipped a
header reading 1.2.1. Guards now assert the tag against both the plugin header and
package.json, in `release.yml` **and** `update-dist.yml`. Caught a real mismatch the
same day. See PRs #96, #99.

~~CI had never run on the integration branch~~ — ✅ DONE (Session 1). `ci.yml` watched a
`develop` branch that has never existed. Every run since ~13 July had also failed on
`composer audit --no-dev`, which audits nothing here and became an error in Composer
2.10. Fixing it surfaced **3 real CVEs** in the dev toolchain. Ten Dependabot PRs were
red, not un-reviewed. See PRs #98, #100.

~~prefers-reduced-motion had no effect~~ — ✅ DONE (Session 1). The override named
`.modal-entering`/`.modal-leaving`; the store applies `is-open`/`is-closing`. Test now
asserts the invariant, not the selectors. See PR #102.

~~Modal Dialog owned both overlay and chrome~~ — ✅ DONE (Session 1). Chrome moved to a
`core/group.modal-chrome`. Recovered from a six-month-old unfinished branch. Breaking
for sites with a **customised** template part: measured as a transparent dialog, not
graceful degradation. See PR #103.

~~Strand 3 — modal placement~~ — ✅ DONE (Session 1). Modal Dialog gained `placement`
(centered/left/right), overridable per trigger, with contextual sizing so `fullscreen`
can't turn a panel into a full-width sheet. Panels square off `border-radius` with
`!important`, following the mobile/fullscreen precedent — chrome (background, padding,
shadow) stays with the author's Group.

~~Modal placement — centered, left and right panels~~ — ✅ DONE (Session 2). Edge panels
pin flush at 420px (narrow 320, wide 600) with breakpoints at width + 48px. Browser-QA'd by
measurement, not eyeball. The check that mattered: a `fullscreen` size on a panel dialog
drops the slug rather than producing a full-width sheet. See PR #112,
`_plans/441-placement-qa-results.md`.

~~A button with no link was a dead modal trigger~~ — ✅ DONE (Session 2). `handleGroupTriggerClick`
deferred to `a:not(.is-primary-link)`; a core Button with no link renders an `<a>` with **no
href**, so it neither opened nor navigated. Narrowed to `a[href]`. 10 new unit tests. PR #111.

~~Modal triggers are a block property, not a wrapper block~~ — ✅ DONE (Session 2). The Modal
Trigger block is gone; `pikariModalAction` on `core/group`/`core/button` carries it, so a
Query Loop card keeps its own background and border instead of losing them to a wrapper.
20 commits, 64 PHP tests (was 48) and 98 JS (was 58). Merged to local `main` only — not
pushed. See `docs/superpowers/specs/2026-09-09-modal-trigger-as-block-property-design.md`.

## Open

- **CI masks every test failure.** `.github/workflows/ci.yml` sets `continue-on-error: true`
  on the whole `test` job, commented "remove once test suites have real tests". There are now
  64 PHP and 98 JS tests. Every green check on PRs #111-#113 was weaker than it looked.
  Being handled by the CI agent.
- **The `WP_CORE_DIR` CI fix will be reverted by the next template sync.** `ci.yml` is a
  synced monorepo file and this plugin's `skip-sync` covers only `tests/php/bootstrap.php`.
  Right fix is the template, not `skip-sync` — every plugin has this.
- **Prettier and markdownlint conflict repo-wide.** Prettier (lint-staged, every commit)
  forces 2-space nested-list indent; markdownlint MD007 demands 4. No nested list in any
  `.md` can satisfy both — the cause of every MD007 violation in the repo. One-line fix
  either way: set MD007 to 2, or add `*.md` to `.prettierignore`.
- **Modal overlay templates (#444) — next.** Design and 11-task plan written and amended for
  the reduced trigger surface set. Includes the `modal-dialog` → `modal-overlay` rename.
- **Overlay opacity control layout** — fixed in PR #112 but never seen in a browser; the
  editor needs a login I cannot do.

- **Testing traps write-up** — PR #105, merged.
- **Release-tag provenance (design item, not started).** `update-dist.yml` runs
  `git tag -f`, moving release tags onto the `dist` branch. Release Drafter's
  `commitish: main` then has no valid base, re-counts all history, and mis-resolves
  every version — this is how two dependency PRs produced a v1.4.0. The tag must stay
  on `dist` for Composer, so it is not a rename. Likely `dist` in its own repository.
  **Until fixed: set the tag by hand when publishing.**
- **Dialog `aria-label` is generic.** "Modal dialog" even when the trigger has a good
  accessible name. Measured on a live install.
- **Fix CCLF's modal template part** after the chrome refactor ships (Pikari todo #436).
  Only matters if it has a customised part.
- **`@wordpress/primitives` 4.47 (#85)** — cannot merge. Peer-requires React 19 while
  `@wordpress/scripts` pins React 18. Blocked upstream, not on us.

## Conventions

- `main` only. `development` was retired; `main` requires a PR.
- Version lives in three places: plugin header, `PIKARI_GUTENBERG_MODALS_VERSION`,
  `package.json`. Not `composer.json` — `update-dist.yml` regenerates that.
- Testing traps: `_plans/testing-notes.md`. Read it before writing a test page.
