# Roadmap — pikari-gutenberg-modals

**Last updated:** 2026-09-04 (Session 1)

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

## Open

- **Strand 3 — modal placement.** Centered / left / right, with contextual sizing.
  Design agreed and twice-corrected from browser testing:
  `docs/superpowers/specs/2026-09-04-modal-placement-design.md`. Not started.
- **Overlay opacity** — PR #104, awaiting merge. Lets a theme with
  `settings.color.custom: false` still produce a translucent backdrop.
- **Testing traps write-up** — PR #105, awaiting merge.
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
