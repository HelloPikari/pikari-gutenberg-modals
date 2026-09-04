# Testing this plugin — traps worth knowing

Hard-won notes from 2026-09-04. Everything here cost time to discover and is not
obvious from the code.

## Hand-authored trigger markup silently does nothing

`handleGroupTriggerClick` (`modal-store.js`) deliberately ignores clicks on
`a:not(.is-primary-link), button, input, select, textarea, [role="button"]` so that
nested links inside a clickable card still work as links.

`render.php` adds `is-primary-link` to the *detected* primary link. Hand-written
markup does not get it. So a test page authored by hand, with an anchor inside the
trigger, **navigates instead of opening the modal** — and looks exactly like a broken
modal rather than working-as-designed.

Two ways round it when testing:

- click the trigger *wrapper* (the element with `role="button"`), not the inner link
- or author the content through the editor, so `is-primary-link` is applied

The Kindler theme hit the sibling of this: hand-written pattern markup was rejected by
the editor's validator, and regenerating it with `wp.blocks.serialize()` produced
different — and more forward-compatible — output than the hand-written version.
**Prefer editor-generated markup over hand-written for anything you intend to trust.**

## wp-env recipes

Start from a worktree so the mounted plugin is the branch under test:

    npx wp-env start          # http://localhost:5888, admin / password

**The plugin directory name becomes the URL.** wp-env mounts the checkout directory,
so from a worktree called `modals-chrome` the frontend module loads from
`/wp-content/plugins/modals-chrome/build/frontend/index.js`. Grepping the page for
"pikari" to confirm the script loaded returns nothing and looks like a bug. It is not.

**Rebuild before testing.** wp-env serves `build/`, not `src/`. Run `npm run build`
after changing frontend JS or block CSS or you are testing stale assets.

**wp-cli runs in the container.** A host path like `/tmp/foo.html` does not exist
there. Write the file into the plugin directory and reference the container path:

    /var/www/html/wp-content/plugins/<worktree-dir>/foo.html

**Multi-line block markup breaks `--post_content`.** Pass a file instead, per above.

**Overriding the file-based template part** needs all three of these, or the file
version keeps winning:

    wp post create <file> --post_type=wp_template_part --post_name=modal --post_title=modal
    wp post term set <id> wp_theme twentytwentyfive
    wp post term set <id> wp_template_part_area uncategorized

Delete it afterwards (`wp post delete <id> --force`) or it silently overrides the
default part for every later test.

## Testing the editor without clicking

Driving the block editor by clicking is slow and brittle. Select a block through the
data store instead, then read the rendered inspector from the DOM:

    wp.data.dispatch( 'core/block-editor' ).selectBlock( clientId );

The canvas is an iframe — reach it via
`document.querySelector('iframe[name="editor-canvas"]').contentDocument`.

## prefers-reduced-motion cannot be emulated through the Playwright MCP

There is no media-emulation call exposed. Options, in order of usefulness:

1. Assert the CSS *invariant* instead — see `tests/unit/frontend/reduced-motion.test.js`,
   which checks that every selector declaring an `animation` outside the media block is
   overridden inside it, **and that nothing is overridden that does not animate**. The
   second half is what caught the original bug: the override named
   `.modal-entering`/`.modal-leaving`, which the store never applies, so it matched
   nothing while looking entirely plausible.
2. Playwright's own `newContext({ reducedMotion: 'reduce' })` works, but `playwright`
   is not a dependency here and pulling browser binaries is disproportionate for a
   CSS check.

## Geometry may override author block styles — there is precedent

`modal-dialog/style.css` uses `border-radius: 0 !important` for mobile and fullscreen,
beating the inline style block supports put on `.modal-chrome`. That is deliberate: a
fullscreen dialog with rounded corners looks broken. Any future geometry work
(edge-anchored panels, for instance) should follow that precedent rather than invent a
softer rule. Background, padding and shadow are left to the author.

## Do not run sync-all.sh on modals without checking tests/

`.github/config-templates/tests/` is in the monorepo sync set, and modals'
`tests/php/bootstrap.php` diverges from the shared template: it defines a
`WP_Block_Template` stub the template lacks. `ModalTemplatePartTest.php` depends on it
(`assertInstanceOf` and `new \WP_Block_Template()`). modals' `.github/plugin.json`
has no `skip-sync` key, so a sync would replace bootstrap.php and break those
tests.

This already happened to pikari-team, breaking 37 tests. Also note
`check-template-drift.sh` compares workflow YAML only — it reports "Templates are
current" while blind to `tests/` and the config set entirely.

Reported by the wordpress-plugins monorepo session, 2026-09-04.
