# Roadmap — pikari-gutenberg-modals

**Last updated:** 2026-09-11 (Session 3)

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

~~Modal Content vanished when the Site Editor edited a page~~ — ✅ DONE (Session 3).
v1.3.0 regression reported on Kindler. The block was unregistered whenever
`window.pagenow === 'site-editor'`, which reads the admin screen, not what is being
edited — and since WP 6.3 the Site Editor edits pages too. Moved server-side into
`allowed_block_types_all`, which knows the difference. Verified identical in WP 6.8,
7.0 and 7.1, so it holds across the whole supported range. First-ever coverage of
editor block registration: 10 tests. See PR #117, todo #439.

~~Dialog `aria-label` was generic~~ — ✅ DONE (Session 3). A dialog announced itself as
"Modal dialog" even when the trigger had a good name. Derivation moved into
`TriggerContext::build()` so every open-mode surface names its dialog the same way:
author label, else post title (entity-decoded), else external host. The inline RichText
format nearly slipped through — it builds its own context and reaches no block, so it
was the one surface never calling TriggerContext. See PR #118, todo #442.

~~Modal overlay templates, and the `modal-dialog` → `modal-overlay` rename~~ — ✅ DONE
(Session 3). Every trigger now has a Modal template panel that can select, create from a
starter pattern, edit and preview a template part, entity-backed so a new part appears
immediately. Three starter patterns registered to the `modal` area. 11 tasks, each with
its own review, then a whole-branch review. 91 PHP tests (was 84) and 130 JS (was 102).
The reviews caught five defects the plan would have shipped — see Session 3 log. PR #120.

~~v2.0.0 released~~ — ✅ DONE (Session 3). The `breaking` label was all Release Drafter
needed: it re-resolved its own stale v1.4.0 draft to v2.0.0. ZIP and checksums verified.
Installed on Kindler, whose two affected pages were rebuilt and confirmed working.
Todos #460, #461.

## Open

- **CI masks every test failure.** `.github/workflows/ci.yml` sets `continue-on-error: true`
  on the whole `test` job, commented "remove once test suites have real tests". There are now
  **91 PHP and 130 JS tests**. Every green tick on every PR this session was weaker than it
  looked — each one was confirmed by reading the job log instead. Being handled by the CI agent.
- **The `WP_CORE_DIR` CI fix will be reverted by the next template sync.** `ci.yml` is a
  synced monorepo file and this plugin's `skip-sync` covers only `tests/php/bootstrap.php`.
  Right fix is the template, not `skip-sync` — every plugin has this.
- **Prettier and markdownlint conflict repo-wide.** Prettier (lint-staged, every commit)
  forces 2-space nested-list indent; markdownlint MD007 demands 4. No nested list in any
  `.md` can satisfy both — the cause of every MD007 violation in the repo. One-line fix
  either way: set MD007 to 2, or add `*.md` to `.prettierignore`.
- **Four QA steps still need a human** — `_plans/444-modal-overlay-templates-qa.md`. Ten
  of fourteen were driven in a real browser; the remainder are the Edit round-trip with
  unsaved changes, stale carry-over, a hybrid theme (wp-env has no classic theme), and
  the Site Editor inserter — the last being the one thing the rename could break silently.
- **Cmd/Ctrl+M has never worked** (todo #472). `RichTextToolbarButton`'s
  `shortcutType`/`shortcutCharacter` only draw the tooltip hint; binding needs
  `RichTextShortcut`, which the plugin has never used. Dates to the initial commit, and
  CLAUDE.md advertises it as a feature. Pre-existing, found during 2.0.0 QA.
- **`core/cover` is not a trigger block.** Surfaced rebuilding Kindler: a Cover had to be
  wrapped in a Group to carry the modal action. Fine, but worth deciding whether Cover
  belongs in the default `pikari_gutenberg_modals_trigger_blocks` list.

- ~~**Release-tag provenance**~~ — resolved by circumstance. `update-dist.yml` no longer
  exists (dist retired in #114), so nothing force-moves the tag. The v2.0.0 release went
  out clean and the "set the tag by hand" workaround is retired with it.
- **Composer consumers cannot get 2.0.0** until the package index exists (todo #455,
  needs Steve's admin on the HelloPikari account). CCLF is pinned `^1.0.0` and resolves
  through `hellopikari.github.io/packages`, which is not live. The release ZIP is the
  only distribution route today.
- **Fix CCLF's modal template part** now the chrome refactor has shipped (todo #436).
  Only matters if it has a customised part — Kindler turned out not to, so check before
  assuming. Blocked behind the Composer index in any case.
- **Guardian Capital is on v0.3.2** — a git checkout far enough behind that upgrading it
  is its own piece of work, not a version bump.
- **`@wordpress/primitives` 4.47 (#85)** — cannot merge. Peer-requires React 19 while
  `@wordpress/scripts` pins React 18. Blocked upstream, not on us.

## Conventions

- `main` only. `development` was retired; `main` requires a PR.
- Version lives in three places: plugin header, `PIKARI_GUTENBERG_MODALS_VERSION`,
  `package.json`, plus `readme.txt`'s Stable tag. Not `composer.json`.
- `main` is protected by a ruleset requiring a PR, with no bypass — local merges still
  have to go up as a branch and a PR.
- **Browser QA is possible**: wp-env logs in as `admin`/`password`, Kindler's ddev admin
  is `pikari`, and an auth cookie can be minted with `wp eval` when a password is unknown.
  Earlier sessions wrongly recorded the editor as untestable and deferred real work.
- Testing traps: `_plans/testing-notes.md`. Read it before writing a test page.
