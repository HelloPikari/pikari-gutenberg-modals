# Modal Overlay Templates — Pre-build Verification

**Date:** 2026-09-07
**WordPress:** 7.1
**Theme:** Twenty Twenty-Five (block theme)
**Environment:** wp-env on :5888 (dev) / :5889 (tests), serving `_worktrees/modals-chrome` (branch `chore/session-log`)
**Plan:** `docs/superpowers/plans/2026-09-07-modal-overlay-templates.md`, Task 1

Note: the plugin's floor is WordPress 6.8. Everything below was observed on 7.1.
Where that distinction matters it is called out.

Two deviations from the plan's written steps, both to avoid mutating a live QA
environment:

- Step 3 was to add `register_block_pattern()` to `pikari-gutenberg-modals.php`
  temporarily. Instead the probe was registered in-process via `wp eval`, which
  persists nothing.
- The synthetic-default check could not use the dev database, which already holds
  a customised modal part. It was run against the filter directly and re-confirmed
  through REST on the clean tests database.

## 1. Does the synthetic default part surface through REST with content? — PASS

Two paths checked separately, because the dev database has a **saved** part and so
only proves the customised case.

**Saved path** (dev DB, `/wp/v2/template-parts?per_page=-1&context=edit`):

```text
modalCount: 1
id: twentytwentyfive//modal   slug: modal   theme: twentytwentyfive
source: custom   origin: plugin
content.raw present, 1959 chars
```

**Synthetic path** (`ModalTemplatePart::provide_default_template()` called directly
with an empty result set — no DB writes):

```text
count: 1
id: twentytwentyfive//modal   slug: modal   theme: twentytwentyfive
area: modal   source: plugin   status: publish
content present, 1280 chars, from parts/modal.html
```

**Synthetic path through REST** (clean tests DB, `rest_do_request` as user 1):

```text
status: 200   modalCount: 1
id: twentytwentyfive//modal   slug: modal   theme: twentytwentyfive
source: plugin   origin: plugin   wp_id: 0
content.raw present, 1280 chars
```

**Conclusion:** spec §1 holds. `useEntityRecords( 'postType', 'wp_template_part' )`
sees the synthetic default, with content, and with a usable `theme` for building the
entity id. No design change needed.

Worth knowing: the synthetic record reports `wp_id: 0` and `source: "plugin"`. It has
no underlying post until first save, which is normal WordPress behaviour for
plugin- and theme-provided templates.

## 2. Do plugin patterns keep arbitrary blockTypes? — PASS

A probe registered in-process with `blockTypes => [ 'core/template-part/modal' ]`
came back from `/wp/v2/block-patterns/patterns` as:

```text
keys: name, title, content, block_types
block_types: [ "core/template-part/modal" ]
```

The REST layer emits **snake_case `block_types`**; the client store converts it back.
In the post editor, `wp.data.select( 'core' ).getBlockPatterns()` returns:

```text
totalPatterns: 161
sample keys: name, title, content, categories, blockTypes, source
patterns scoped to core/template-part/*: 31
e.g. core/navigation-overlay => core/template-part/navigation-overlay
```

**Conclusion:** `selectModalPatterns()` filtering on
`blockTypes.includes( 'core/template-part/modal' )` is correct as planned.

Incidental but useful: WP 7.1 ships **31** patterns scoped to
`core/template-part/navigation-overlay`. That is the prior art the design is modelled
on, now confirmed present in this environment and available to read.

## 3. Is onNavigateToEntityRecord undefined in the post editor? — NO. THE PLAN IS WRONG

```js
typeof wp.data.select('core/block-editor').getSettings()
	.onNavigateToEntityRecord;
// => "function"     (post editor, WP 7.1)
```

Spec §4 and plan Task 8 both assert it is undefined in the post editor and that core
therefore hides the Edit button there. **That is not true on WordPress 7.1.**

### What this changes

The `buildEditUrl()` fallback is not the primary post-editor path it was designed to
be. It is at most a guard for older WordPress — and the plugin's floor is 6.8, which
was not tested here, so whether it is needed at all is still open.

### Recommendation, for review before Task 2 or Task 8 is written

**Drop `buildEditUrl()` from the plan.** Hand-building an admin URL was only ever
justified by the assumption that core left no route in the post editor. It does. Use
`onNavigateToEntityRecord` when present and hide the Edit button when it is not,
which is core's own behaviour and needs no URL construction.

This removes from Task 2 both `buildEditUrl()` and its four unit tests, removes the
`siteEditorUrl` localized value from Task 5, and simplifies Task 8 to a single path.

### Residual, deliberately not chased

The verbatim Site Editor URL for a template part was **not** captured. The guessed
shape `?p=%2Fwp_template_part%2F{theme}%2F%2F{slug}&canvas=edit` loaded the Site
Editor shell with no canvas, and three attempts to read the real scheme out of the
SPA came back empty — it routes via `history.pushState`, not anchors.

Core registers `wp_template_part` with `'_edit_link' => '/site-editor.php?canvas=edit'`
(`wp-includes/post.php:503`), which carries no id placeholder, so the id is applied by
the JS router rather than by a PHP-buildable URL. That is further evidence for the
recommendation above: there is no stable, public URL contract to build against.

If the fallback is kept anyway, capture the real URL by hand — open the Modal template
part in the Site Editor and copy the address bar — before writing the assertion in
Task 2 Step 1.
