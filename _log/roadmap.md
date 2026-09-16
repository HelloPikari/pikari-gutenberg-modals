# Roadmap — pikari-gutenberg-modals

**Last updated:** 2026-09-16 (Session 6)

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

~~Video modals collapsed around their video~~ — ✅ DONE (Session 4). The iframe was always
right (1200x675); its ancestors collapsed to **169px** and clipped it, because the flex chain
built for page-iframes contributes no intrinsic height. A `data-fit="video"` mode flips it to
content-driven and derives width from a height budget. Dialog now **1009x629** around a
1009x568 iframe. Orientation is undetectable — YouTube's oEmbed reports 200x113 for a Short —
so an Aspect ratio control carries it, and works on any host. In PR #122.

~~Pasting a YouTube link opened a blank modal~~ — ✅ DONE (Session 4). No watch-to-embed
conversion existed anywhere; those pages refuse framing. `normalizeEmbedUrl()` converts watch,
youtu.be, shorts, live and vimeo.com, carrying `t=` across. Only the iframe src — the trigger's
href stays the human-facing page. In PR #122.

~~The loading spinner pushed modal content around~~ — ✅ DONE (Session 4). `.modal-body.hidden`
had no display rule while `.modal-loading.hidden` did. The spinner now overlays once there is
content to overlay and stays in flow while the body is empty. Side effect worth knowing: the
error path now genuinely hides the body for the first time, on every modal. In PR #122.

~~A global modal needed a stand-in page~~ — ✅ DONE (Session 4). "Template only" content source:
the template part is the content. The dialog is named from the part's title — and WordPress
stands the **slug** in for a missing title (measured for both `source=custom` and `source=theme`),
so a title equal to its slug is discarded rather than announced. In PR #122.

~~Theme and plugin CSS missing inside modals~~ — ✅ DONE (Session 4). Not unqueued — **never
registered**. `block-style-variation-styles` needs both halves of the lifecycle (enqueued in
`wp_enqueue_scripts` before registration, promoted by `do_blocks()`); WPForms enqueues on
`wp_footer`. Both actions now fired, buffered, behind `pikari_gutenberg_modals_simulate_frontend`.
Eyebrow computes uppercase/600/1.92px; injected `.wpforms-field-hp` computes `display:none`.
In PR #122.

~~The modal-content ETag hashed only the post~~ — ✅ DONE (Session 4). The endpoint's output
changed shape without any post changing, so every browser holding a 2.0.0 response would have
revalidated 304 forever and never seen the CSS fix. Version and simulate-flag now in the hash.
**`bump-version.js` is therefore load-bearing beyond the build guard.** In PR #122.

~~Modal chrome did not stretch its children~~ — ✅ DONE (Session 4). Layout declared orientation
without justification, which generates `align-items: flex-start`. Content measured **645px inside
a 1024px chrome**; now 927 of 973. Fixed in all four places the markup is authored. In PR #122.

~~Three starter patterns previewed identically~~ — ✅ DONE (Session 4). They differ only in
`placement`, which is frontend-only geometry. The editor now previews panels at panel width,
hugging their edge. Cost two traps, both recorded as gotcha 16: the editor's centring rule is
equally specific _and_ `!important` _and_ later in source order, and it uses logical properties.
In PR #122.

~~Eyebrow styling lost in a footer-rendered template part~~ — ✅ DONE (Session 5, 2.2.1). Core
appends each `is-style-*--N` rule to a handle a block theme already printed in `<head>`, and never
prints a done handle again — measured **0 bytes** reprinted. Containers now print inline CSS added
to done handles as `modal-late-inline-styles`. PR #130, todo #506.

~~WPForms in inline-content modals was never bound~~ — ✅ DONE (Session 5, 2.2.2). Firing
`content-loaded` alone changed nothing — measured: no listener existed on an inline page. A
page-side initialiser now prints at `wp_footer` 19 when WPForms and a trigger are both present.
PR #132, todo #505.

~~A form only inside a template-only modal got no WPForms CSS or JS~~ — ✅ DONE (Session 5,
2.2.2). WPForms picks footer assets at 15 from forms already rendered; containers rendered at 999.
Moved to **10**; hid on Kindler because its footer form is on every page. PR #135, todo #509.

~~Deprecated `core/edit-site` selectors in the editor~~ — ✅ DONE (Session 5, 2.2.2). Read from
`core/editor`; 0 deprecations in both editors. PR #134.

~~Cmd/Ctrl+M never worked~~ — ✅ DONE (Session 5, 2.2.2) by dropping it, not binding it: macOS
takes Cmd+M for Minimize before the page does, and the nearby chords belong to Chrome, VoiceOver
or core. #133 closed; PR #136, todo #472.

~~SECURITY: the modal endpoint served hidden posts to anyone~~ — ✅ DONE (Session 5, 2.2.3). Checked
`post_status` alone since the initial commit: password-protected posts in full, and WPForms form
definitions, menus, global styles and template parts by ID. **Live Kindler returned form 593's
settings; 404 after install.** Now `is_post_publicly_viewable()` and no password, same 404 as
missing. PR #139, todo #512.

~~Logged-in users' WPForms submits refused in REST-loaded modals~~ — ✅ DONE (Session 5, 2.2.3).
The render ran anonymously; WPForms prints and checks its nonce only for logged-in users. Core
REST cookie auth for logged-in viewers only, never stored or 304'd, user in the ETag, `Vary:
X-WP-Nonce`, stale nonce refreshed via `rest-nonce`. PR #141, todo #510.

~~Opt-in prefetch link hints~~ — ✅ DONE (Session 5, 2.3.0) — removed with both filters. Unused,
rendered every linked modal per page view when on, and their URLs lacked `modal_id` so they
never warmed the fetch they were for. PR #145, todo #514.

~~A QA screenshot shipped in every release ZIP since 2.0.0~~ — ✅ DONE (Session 5, 2.3.0). PR #143.

~~Block filters registered twice per REST request~~ — ✅ DONE (Session 6, 2.3.1). The helpers
were made static and both second instances dropped: `RestApi`'s and `EditorIntegration`'s.
`render_block_core/button` went **2 → 4** callbacks after one request and now stays at **2**. PR #149.

~~Trigger decoration copied across 8 handlers~~ — ✅ DONE (Session 6, 2.3.1). All 8 decoration
sites were pinned by full-attribute tests first, then moved into `TriggerMarkup`. Old vs new
was **byte-identical over 522 renders**. PR #150.

~~v2.3.1 released~~ — ✅ DONE (Session 6). ZIP checksum verified, and the package index lists it.

## Open

- ~~**A WPForms form inside a modal probably cannot submit** (todo #484)~~ — ✅ DONE (Session 5).
  It could not; 2.2.0 collects and runs the render's scripts under the post's own REQUEST_URI, and
  2.2.2–2.2.3 closed the inline, template-only and logged-in variants (see Released).
- ~~**WPForms blocks crash any `BlockPreview`** (todo #492, upstream)~~ — cancelled by Steve (Session 6). `updateCopyPasteContent`
  reads `wp.data.select('core/block-editor').getBlockAttributes(clientId)` from the **default**
  registry while `BlockPreview` renders in an isolated one, so it gets null and throws on the
  first key. Not ours: WordPress's own Site Editor template-part list shows the identical crash.
  Fallback if upstream stalls — render the Modal template preview from server HTML instead.
- ~~**`RestApi` instantiates a second `BlockSupport`.**~~ — ✅ DONE (Session 6); see Released. `init` runs in REST, so the bootstrap
  instance is already live and every `render_block` filter is registered twice — measured
  **4 callbacks** on `render_block_core/button` after a modal-content request. Pre-existing;
  `suspend_container_render()` now works around the wp_footer half of it. The real fix is that
  `get_post_content_with_styles()` needs no constructor state.
- ~~**The shared trigger decoration is copied three times**~~ — ✅ DONE (Session 6); see Released. In `GroupModalTriggerSupport` and
  twice in `BlockSupport`. Two of four reviewers wanted it extracted; deferred because it
  touches three shipped handlers. The a11y attribute set is what is duplicated, and this plugin
  has already shipped a mouse-only trigger once — per-branch render tests are the mitigation.
- **The modal-content endpoint is uncached server-side.** Session 6 built it and held it back:
  draft PR #151. Two reviews each found request state leaking into the shared copy. Fixed:
  `?query-N-page` poisoning, comment and post-password cookies. Still open: the `Host` header
  and http/https scheme, and nonces from a logged-in cookie without a REST nonce. Live Kindler
  is not edge-cached (`cf-cache-status: DYNAMIC`). Original note: it runs every `wp_enqueue_scripts` and
  `wp_footer` callback on a public route that hover-prefetch can fire N times across a Query
  Loop. ~52ms measured on Kindler. The ETag is already the right cache key for logged-out
  responses; since 2.2.3 a logged-in render is per-user and must never share it. 2.3.0's removal
  of prefetch hints took away the path that rendered every modal on every page view.
  (Unhooking `wp_enqueue_global_styles` was **measured at 52.6 vs 52.2ms** — rejected.)
- **CI masks every test failure.** `.github/workflows/ci.yml` sets `continue-on-error: true`
  on the whole `test` job. There are now **151 PHP and 192 JS tests**. Every Session 5 merge was
  verified by reading the Test job log, not the badge. Handed to the monorepo agent in Session 6.
  The template is fixed locally, and the modals sync PR waits on that agent.
- **The `WP_CORE_DIR` CI fix will be reverted by the next template sync.** `ci.yml` is a
  synced monorepo file and this plugin's `skip-sync` covers only `tests/php/bootstrap.php`.
  Right fix is the template, not `skip-sync` — every plugin has this. The monorepo agent folded
  it into the template in Session 6 (#458); it lands with the same sync PR.
- **Prettier and markdownlint conflict repo-wide.** Prettier (lint-staged, every commit)
  forces 2-space nested-list indent; markdownlint MD007 demands 4. No nested list in any
  `.md` can satisfy both — the cause of every MD007 violation in the repo. One-line fix
  either way: set MD007 to 2, or add `*.md` to `.prettierignore`.
- ~~**Hybrid-theme editor QA is the last 2.0.0 check** (todo #459)~~ — ✅ DONE (Session 6):
  Twenty Twenty-One plus `block-template-parts`. The panel shows the select only, enabled, with
  0 console errors. Twenty Twenty-Three and CCLF's cclf25 are not hybrid. Session 5 drove the rest in
  wp-env: the pencil keeps unsaved work, a second inline trigger inherits the first's template
  (pre-existing, information only), the Site Editor inserter still offers Content Area, and the
  opacity layout was approved. wp-env has no classic theme.
- ~~**Cmd/Ctrl+M has never worked** (todo #472)~~ — ✅ DONE (Session 5) — dropped; see Released.
- ~~**Kindler `/about/` has a `link`-source trigger that renders nothing**~~ — not a bug (Session 6).
  All 7 team cards render and open, on local and live. The note on #485 was wrong. Before and after 2.2.2
  alike. Post 12 stores `pikariModalContentSource: "link"`, yet no trigger reaches the HTML.
  Undecided whether it is content (no resolvable primary link) or a plugin failing silently.
  Kindler's content thread is #485.
- ~~**`core/cover` is not a trigger block.**~~ — decided: pass (Session 6, #473 cancelled). A Group
  wrapper works, and the filter is the opt-in. Surfaced rebuilding Kindler: a Cover had to be
  wrapped in a Group to carry the modal action. Fine, but worth deciding whether Cover
  belongs in the default `pikari_gutenberg_modals_trigger_blocks` list (todo #473).

- ~~**Release-tag provenance**~~ — resolved by circumstance. `update-dist.yml` no longer
  exists (dist retired in #114), so nothing force-moves the tag. The v2.0.0 release went
  out clean and the "set the tag by hand" workaround is retired with it.
- ~~**Composer consumers cannot get 2.0.0**~~ — resolved. The package index at
  `hellopikari.github.io/packages` is live and lists every release through 2.3.0 (todo #455).
- **Fix CCLF's modal template part** now the chrome refactor has shipped (todo #436). Session 6:
  the CCLF agent found no customised part and no legacy triggers, and proposed #436 done. Local
  CCLF runs 2.3.1 on the unpushed branch `chore/modals-2x`.
  Only matters if it has a customised part — Kindler turned out not to, so check before
  assuming. The index no longer blocks it; CCLF's own Composer branch does (todo #478).
- ~~**Guardian Capital is on v0.3.2**~~ — out of scope (Session 6). Steve has no control over the site.
- **A wrap merge to `main` bumps the version.** #147 carried `skip-changelog`, so its 2.3.1 draft
  read `* No changes` and the bump still ran. Sync PR #152 (monorepo template) skips the bump for an
  empty draft. Steve decided a `docs` PR should still bump. So: merge #152 first, and label the
  wrap PR `skip-changelog`.
- **21 merged branches remain on the remote.** Auto mode blocked deleting them in Session 6.
  `fix/inline-trigger-shortcut` (PR #133, closed unmerged) was left out of the list.
- **A Button whose URL is `#` becomes a URL modal that opens nothing.** Should the plugin refuse
  `#` or an empty href? Found on Kindler `/what-we-do/` (fixed there in content).
- **A Button's inner `<a>` loses an author-set `id`.** Groups keep theirs. Pre-existing; the
  Session 6 review flagged it as a nit.
- **Hybrid select-only panel:** the "Select a template for this modal." help text sits flush
  against the Accessible Label heading. Cosmetic.
- **`@wordpress/primitives` 4.47 (#85)** — cannot merge. Peer-requires React 19 while
  `@wordpress/scripts` pins React 18. Blocked upstream, not on us.

## Conventions

- `main` only. `development` was retired; `main` requires a PR.
- Version lives in three places: plugin header, `PIKARI_GUTENBERG_MODALS_VERSION`,
  `package.json`, plus `readme.txt`'s Stable tag. Not `composer.json`.
- `main` is protected by a ruleset requiring a PR, with no bypass — local merges still
  have to go up as a branch and a PR.
- **Release flow, as run five times in Session 5.** The branch prefix picks the version
  (`feature/` minor, `fix/` patch, anything else patch). A PR must be up to date with `main`, so
  every merge that lands puts the rest behind: `gh pr update-branch`, then `--auto` merge, one at a
  time. Each merge that moves the draft opens "chore: Bump version to X", which merges itself.
  Publish only once the draft title is plain, then run "Regenerate package index".
- **Browser QA is possible**: wp-env logs in as `admin`/`password`. Kindler's local admin row is
  live Kindler's (kindler.pikari.io) `pikari` account, so Session 5 did not log in there — editor QA ran in wp-env,
  and frontend QA on Kindler needs no login. Earlier sessions wrongly recorded the editor as
  untestable and deferred real work.
- **QA both logged in and logged out.** #510 passed every logged-out check and failed only for
  an admin; #509 passed on Kindler only because every Kindler page has another form.
- Testing traps: `_plans/testing-notes.md`. Read it before writing a test page.
- **To stay a patch, use `refactor/` or `perf/`, never `feature/`.** A PR that touches `*.md` is
  autolabelled `docs` and lands under Documentation; relabel it `chore` and rerun Release Drafter.
- **Anything that shares a render across visitors needs its own threat model.** `public`
  Cache-Control was safe because browsers key on the full URL, per browser. A server copy is not.
- **In wp-env, write probe files with `docker cp`/`docker exec`, not `wp-env run cli sh -c`.**
  Nested quoting there fails silently: a probe mu-plugin, and later a `wp post update`, never ran,
  and both looked like results.
