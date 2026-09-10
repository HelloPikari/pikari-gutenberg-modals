# CLAUDE.md

## Project Overview

WordPress plugin that adds accessible modal dialogs to the block editor. Content (posts, pages, custom post types, external URLs) is displayed in overlays triggered by inline links, buttons, or clickable group blocks. Uses the WordPress Interactivity API for reactive state management with progressive enhancement (triggers are real links that work without JavaScript).

## Required Agents

Always use these agents proactively:

- **`wordpress-core-expert`** — Review all PHP and JavaScript code changes
- **`accessibility-expert`** — Review modal functionality, keyboard navigation, focus management, ARIA attributes

## Architecture

### Trigger Types

1. **Inline Modal Triggers** — RichText format applied to text in supported blocks

   - Format: `modal-toolbar-button/modal-trigger` (Cmd/Ctrl+M shortcut)
   - Editor: `src/editor/modal-format.js` + `src/editor/modal-trigger-edit.js`
   - Server: `BlockSupport::filter_block()` transforms `<span class="modal-trigger">` into interactive `<a>` tags (open mode) or `<button>` tags (close mode)
   - Supported blocks: paragraph, heading, list, list-item, quote, verse, preformatted, navigation-link
   - **Close mode:** `data-modal-action="close"` attribute converts span to inline button with close action (for use in modal template parts)

2. **`pikariModalAction` on Group and Button blocks** — a modal action set directly as a block attribute, so the block keeps its own styling, alignment and layout instead of needing a wrapper block

   - Attribute schema (`pikariModalAction` plus `pikariModalContentSource`, `pikariModalDirectUrl`, `pikariModalPrimaryLinkId`, `pikariModalInlineAnchor`, `pikariModalSize`, `pikariModalPlacement`, `pikariModalTemplatePart`, `pikariModalAccessibleLabel`) is defined in `src/editor/trigger-blocks.js` and registered on every trigger block via a `blocks.registerBlockType` filter in `src/editor/modal-trigger-attributes.js`
   - Which blocks qualify: `isTriggerBlock()` reads `window.pikariGutenbergModals.triggerBlocks` (localized from the `pikari_gutenberg_modals_trigger_blocks` PHP filter), falling back to the hardcoded default `['core/group', 'core/button']` when the global is absent (e.g. Jest)
   - Editor: one "Modal" panel — `src/editor/modal-trigger-panel.js`, added via a single `editor.BlockEdit` filter; hidden in `contentOnly` editing mode
     - **Open mode** (`pikariModalAction: 'open'`) content source: Detected link (`core/group` only — finds links in inner blocks via `src/editor/find-links-in-blocks.js`; a `core/button`'s own URL is its detected link and needs no picker), Custom URL, or Inline content (anchor into a Modal Content block)
     - **Close mode** (`pikariModalAction: 'close'`) — for use inside modal template parts
   - Discovery: two block variations pre-fill attributes for the inserter — `pikari-modal-clickable-card` (`core/group`) and `pikari-modal-button` (`core/button`) — registered in `src/editor/modal-trigger-variations.js`
   - Server: `BlockSupport::filter_button_block()` handles open mode for `core/button`; `GroupModalTriggerSupport` handles open mode for `core/group` (two-phase rendering, Query Loop support); `BlockSupport::filter_close_mode_block()` handles close mode generically for every block `get_trigger_blocks()` returns. Each decorates the block's own rendered root element (or, for a `core/button` close trigger, its inner `<a>`/`<button>`) rather than adding a wrapper
   - `core/image` is deliberately not a trigger block — see gotcha below

### PHP Classes (`includes/`)

| Class                      | Lines | Purpose                                                               |
| -------------------------- | ----- | --------------------------------------------------------------------- | --- |
| `BlockSupport`             | ~850  | Core rendering, trigger transformation, containers, block support CSS |     |
| `GroupModalTriggerSupport` | ~350  | Group block cards, two-phase rendering for Query Loop                 |     |
| `RestApi`                  | ~340  | Modal-content + search endpoints, theme per-block style collection    |     |
| `ModalHandler`             | ~230  | Content processing, URL validation, domain allow/block lists          |     |
| `BlockStyleCollector`      | ~250  | Block detection, stylesheet URLs, theme per-block styles              |     |
| `SpeculativeLoading`       | ~155  | Hover prefetch (200ms delay), prefetch hints                          |     |
| `EditorIntegration`        | ~130  | Editor assets, localized config, block context restrictions           |     |
| `ModalTemplatePart`        | ~285  | Template part registration (block themes), file-based fallback        |     |
| `FrontendRenderer`         | ~55   | Frontend script module + stylesheet registration (lazy-loaded)        |     |

### JavaScript Files

**Editor (`src/editor/`):**

| File                          | Lines | Purpose                                                                                                       |
| ----------------------------- | ----- | ------------------------------------------------------------------------------------------------------------- |
| `modal-trigger-edit.js`       | ~290  | RichText format toolbar UI, LinkControl popover, post search                                                  |
| `modal-trigger-panel.js`      | ~350  | The single "Modal" inspector panel shown on every trigger block                                               |
| `trigger-blocks.js`           | ~70   | Pure, import-free: `TRIGGER_BLOCKS`, `MODAL_ATTRIBUTES`, `isTriggerBlock()`, `hasModalAction()` — unit tested |
| `modal-trigger-attributes.js` | ~30   | Registers `MODAL_ATTRIBUTES` on every trigger block via `blocks.registerBlockType`                            |
| `modal-trigger-variations.js` | ~40   | Registers the Clickable Card and Modal Button block variations                                                |
| `modal-format.js`             | ~20   | RichText format type registration                                                                             |
| `index.js`                    | ~13   | Entry point, exports `toggleFormat`/`applyFormat`/`removeFormat`                                              |
| `style.scss`                  | ~65   | Editor visual indicators (dashed purple underline on modal triggers)                                          |

**Frontend (`src/frontend/`):**

| File                    | Lines | Purpose                                                                   |
| ----------------------- | ----- | ------------------------------------------------------------------------- |
| `modal-store.js`        | ~340  | Interactivity API store — reactive state, async content loading, prefetch |
| `modal-a11y.js`         | ~95   | Focus trap, inert background, keyboard navigation utilities               |
| `block-style-loader.js` | ~75   | Dynamic stylesheet loading (prevents FOUC for modal content)              |
| `index.js`              | ~9    | Entry point                                                               |
| `style.scss`            | ~77   | Trigger-only styles — inline triggers, group triggers, close triggers     |

### Modal Container Pattern

One or more modal containers are rendered in `wp_footer` (only if triggers are detected on the page) — one per unique template part slug used by triggers. The default container ID is `pikari-modal`; custom template parts produce `pikari-modal--{slug}`. Content is loaded dynamically via REST API and inserted with proper escaping. The store name is `pikari-modal` with `data-wp-interactive="pikari-modal"`.

Each container's `aria-labelledby` points at a title element that doesn't exist until modal content loads, so assistive tech falls through to `aria-label` at open time. The container ships a static `aria-label="Modal dialog"`; the store overwrites it with the trigger's own accessible name (the `label` key in Interactivity context, derived once for every trigger surface by `TriggerContext::build()`) when the modal opens, and restores the original on close.

### REST API Endpoints

**Modal Content** — `GET /pikari-gutenberg-modals/v1/modal-content/{id}`

- Permission: public
- Params: `id` (required, integer path param), `modal_id` (optional, string query param for HTTP cache key)
- HTTP cached: ETag, Last-Modified, Cache-Control (1 hour), 304 Not Modified support
- Schema: discoverable via `OPTIONS` request
- Returns: `{ id, title, content, styles, blockStyles: { urls: [...] }, type }`

### Key Design Patterns

1. **Generator functions for async** — Interactivity API uses `function*` + `yield` (not async/await)
2. **`withSyncEvent` wrapper** — All DOM event handlers must use `withSyncEvent()` for proper scope
3. **`withScope` for callbacks** — Preserves Interactivity API context in setTimeout/callbacks
4. **Two-phase rendering** — `GroupModalTriggerSupport` marks post-link candidates in phase 1, matches selected link in phase 2 (enables Query Loop support)
5. **Progressive enhancement** — Triggers render as real `<a href="...">` tags that navigate without JS
6. **Lazy asset loading** — Frontend JS/CSS only enqueued when `BlockSupport::$has_modal_triggers` is true
7. **Dynamic block styles** — `BlockStyleCollector` finds blocks in modal content, `block-style-loader.js` loads their stylesheets on-demand
8. **Hover prefetch** — 200ms debounced prefetch warms browser HTTP cache before click
9. **External URL iframe** — External URLs load in a sandboxed iframe; internal URLs use REST API. Sites blocking iframes (X-Frame-Options/CSP) will show a blank page; progressive enhancement provides fallback navigation
10. **Theme per-block styles** — `BlockStyleCollector::collect_render_enqueued_styles()` captures styles enqueued during `do_blocks()` by comparing `wp_styles()->queue` before/after rendering (catches theme button styles registered via `wp_enqueue_block_style()`)
11. **Block support CSS in wp_footer** — `BlockSupport::render_modal_containers()` snapshots block support CSS before rendering template parts and outputs any newly generated layout/spacing CSS in a `<style>` tag (needed because `wp_enqueue_block_support_styles()` runs earlier)
12. **Style architecture split** — `modal-dialog/style.css` owns ALL modal visual styles (overlay, `.modal-content`, `.modal-chrome`, animations, keyframes, size variants, mobile, print, reduced motion, CSS custom properties). `frontend/style.scss` contains only trigger-specific styles (inline triggers, group triggers, close triggers). This separation means the modal chrome appearance is governed by WordPress block styles on the `modal-chrome` Group, not by custom CSS properties on `:root`.
13. **Placement is container geometry** — The Modal Dialog block's `placement` attribute renders as `data-default-placement` on `.modal-content`; the store resolves it against a trigger override (`placement` in context) and writes the winner to `data-placement` on `.modal-overlay`. All geometry CSS keys off the overlay. Size is contextual: `small`/`large`/`fullscreen` when centered, `narrow`/`wide` on a panel, and a slug from the wrong list is dropped at open time. Panels square off `border-radius` with `!important`, following the fullscreen and mobile precedent; background, padding and shadow stay with the author's chrome Group.

### Critical Implementation Gotchas

1. **Close triggers must NOT add `data-wp-interactive`** — Close triggers exist inside modal template parts where the parent modal container already provides `data-wp-interactive="pikari-modal"` scope. Adding it in `BlockSupport::filter_close_mode_block()` (or the inline format's close-mode handling in `filter_block()`) creates nested Interactivity API islands that break event handling. Open triggers need it because they're outside the modal container; close triggers inherit scope.

2. **render.php lives in build/, not src/** — WordPress reads `render.php` from `build/blocks/`, not `src/blocks/`. After editing any `render.php` file, you MUST run `npm run build` for changes to take effect in wp-env. The `@wordpress/scripts` build process copies PHP files from `src/` to `build/`.

3. **Interactivity API namespace inheritance** — Elements with `data-wp-on--*` directives don't need their own `data-wp-interactive` if they're inside a parent element that has it. The namespace is inherited through the island's vdom tree. Adding unnecessary `data-wp-interactive` creates nested islands which cause hydration/event issues.

4. **Modal Dialog block controls overlay only** — Dialog chrome (background, border, padding, shadow) belongs on an inner `core/group` block with class `modal-chrome`, not on the Modal Dialog block itself. The editor shows a deprecation `Notice` when legacy chrome attributes are detected directly on the Modal Dialog. The `INNER_BLOCKS_TEMPLATE` in `edit.js` sets up the correct structure: Modal Dialog wraps a `core/group.modal-chrome` (white bg, 20px radius, 1.5rem padding, shadow, vertical flex) which contains the close trigger row and content area.

5. **`.modal-chrome` flex bridge** — The `modal-chrome` Group is the scroll architecture bridge between `.modal-content` (90vh cap) and the scrollable content area. Mobile and fullscreen overrides zero border-radius with `!important` (in `modal-dialog/style.css`). CSS custom properties `--modal-content-bg`, `--modal-content-shadow`, and `--modal-border-radius` were removed in the UX simplification — use block attributes on the chrome Group instead.

6. **Fallback close button injection** — `modal-dialog/render.php` detects whether the rendered content contains a close trigger (by scanning for `actions.closeModal` or `actions.handleCloseClick`). If none is found, it injects a visually hidden `sr-only` fallback close button so keyboard/AT users always have a way to dismiss the modal.

7. **A block variation is not visible server-side** — `src/editor/modal-trigger-variations.js` pre-fills attributes and supplies an inserter entry; the variation name (e.g. `pikari-modal-clickable-card`) is never serialized into saved content. `render_block` (and `isTriggerBlock()`/`hasModalAction()` on the JS side) must key on `pikariModalAction`, never on the variation.

8. **`core/image` is excluded from trigger blocks on purpose** — Core's own lightbox ("Enlarge on click") attaches a competing click handler via `render_block_core/image` and is offered by default. Adding `core/image` to `pikari_gutenberg_modals_trigger_blocks` would put two click handlers on one element. A site that wants it anyway can add it through that filter, deliberately.

## Custom Hooks & Filters

```php
// Content processing
pikari_gutenberg_modals_supported_blocks       // Customize blocks supporting inline modal triggers
pikari_gutenberg_modals_trigger_blocks         // Customize which blocks can carry a modal action (default: core/group, core/button)
pikari_gutenberg_modals_post_content           // Filter post content before modal rendering
pikari_gutenberg_modals_url_content            // Filter external URL content
pikari_gutenberg_modals_content                // General content filter

// REST API
pikari_gutenberg_modals_content_response       // Modify modal-content REST response
pikari_gutenberg_modals_cache_duration         // HTTP cache max-age (default: HOUR_IN_SECONDS)

// Security
pikari_gutenberg_modals_allowed_domains        // Domain allowlist for external URLs (default: empty = all allowed)
pikari_gutenberg_modals_blocked_domains        // Domain blocklist for external URLs

// Editor
pikari_gutenberg_modals_modal_sizes            // Add/modify modal size options in the editor dropdown
pikari_gutenberg_modals_panel_widths           // Add/modify panel width options for edge-placed modals

// Prefetch
pikari_gutenberg_modals_enable_prefetch_hints  // Enable auto <link rel="prefetch"> (default: false)
pikari_gutenberg_modals_prefetch_urls          // Modify prefetch URL list
```

## Documentation Rule

When adding developer-facing customization points (PHP filters, CSS custom properties, JS hooks, etc.), always document them in **both** `CLAUDE.md` (Custom Hooks & Filters section) **and** the plugin's `readme.txt` (Developer section). This ensures developers can discover customizations through both the code reference and the plugin readme.

`readme.txt` and `README.md` must be kept in sync — they contain the same content in WordPress readme format and GitHub markdown format respectively. When updating one, always update the other.

## Coding Standards

- **PHP**: WordPress Coding Standards with **4 spaces indentation (NOT tabs)** — enforced by `phpcs.xml`
- **JavaScript**: WordPress ESLint config. **Tab indentation (NOT spaces)**. Prettier is configured to **ignore** JS files.
- **CSS/SCSS**: WordPress Stylelint config. Prettier formats CSS/SCSS.
- **i18n**: All user-facing strings use `__()`, `_e()`, etc. with text domain `pikari-gutenberg-modals`

**AI/LLM editing note:** JavaScript files use tabs. When using the Edit tool, ensure `old_string` preserves exact tab characters. Nesting depth in `modal-store.js`: store properties = 1 tab, action methods = 2 tabs, code inside actions = 3 tabs, nested blocks = 4+ tabs.

### Webpack Configuration

`webpack.config.js` exports two configs: editor (standard script) and frontend (ES module for Interactivity API). The frontend config bundles `@wordpress/escape-html` (~1.2KB) because it's NOT available as a WordPress Script Module. Only `@wordpress/interactivity`, `@wordpress/interactivity-router`, and `@wordpress/a11y` are exposed as script modules (as of WP 6.7). The custom `requestToExternalModule()` function handles this — see `webpack.config.js` comments for details.

## Interactivity API Patterns

```javascript
// Store name (NOT pikari/gutenberg-modals)
const { state, actions } = store( 'pikari-modal', { ... } );

// Generator functions for async (NOT async/await)
*openModal() {
    const response = yield fetch( url );
    const data = yield response.json();
}

// DOM event handlers require withSyncEvent wrapper
handleTriggerClick: withSyncEvent( ( event ) => { ... } ),

// Preserve context in setTimeout
withScope( () => { /* runs with correct store scope */ } )

// Debug: store exposed as window.pikariModal in development
```

## Development Commands

```bash
npm start              # Dev build with file watching
npm run build          # Production build
npm run lint:all       # All linters (JS + CSS + PHP + MD)
npm run lint:fix       # Auto-fix lint issues
npm run playground     # Local WordPress Playground
```

See monorepo `CLAUDE.md` for full command list, git workflow, branching strategy, and release process.

## Git Workflow

- Commit format: `type: Brief description` (types: feat, fix, docs, style, refactor, test, chore)
- **Do NOT include `Co-Authored-By` lines or "Generated with Claude Code" in commits**
- Pre-commit hooks: Husky + lint-staged auto-lint staged files and sync lock files
- **Commit regularly:** When the user confirms changes are working and moves on to the next task, proactively suggest committing the current changes before starting new work

## Project Structure

```text
pikari-gutenberg-modals/
├── pikari-gutenberg-modals.php   # Plugin entry point, autoloader, class initialization
├── includes/                     # PHP classes (PSR-4: Pikari\GutenbergModals\)
├── src/editor/                   # Block editor JS + SCSS
├── src/frontend/                 # Frontend Interactivity API JS + SCSS
├── build/                        # Compiled assets (gitignored)
├── parts/                        # Block template parts (modal.html — Modal Dialog > modal-chrome Group > close row + content area)
├── languages/                    # Translation files (.pot, .po, .mo)
├── _playground/                  # WordPress Playground blueprints
├── docs/                         # Documentation
└── assets/                       # Static assets
```

## Requirements

- WordPress 6.8+
- PHP 8.4+
- Node.js + Composer for development
- **Theme support:** Block themes and hybrid themes (classic themes with `block-template-parts` support). Classic themes without `block-template-parts` support are NOT supported.

## Testing

See the monorepo root [CLAUDE.md](../CLAUDE.md) for full TDD workflow, commands, and example patterns.

### Plugin-Specific Test Guidance

**PHP classes to prioritize for testing:**

- `ModalHandler` — URL validation (`validate_url`), content processing, cache duration
- `RestApi` — Modal-content endpoint, ETag generation, cache headers
- `BlockStyleCollector` — Block detection in content, stylesheet URL collection
- `SpeculativeLoading` — Prefetch URL generation, filter hooks

**JavaScript modules to prioritize for testing:**

- `modal-a11y.js` — Focus trap, inert background, focusable element detection (pure DOM)
- `block-style-loader.js` — Duplicate detection, stylesheet loading (pure DOM)
- `modal-store.js` — State transitions, context validation, generator function flow

**Common Brain\Monkey mocks needed:**

- `esc_url_raw`, `wp_parse_url`, `esc_attr`, `esc_html`, `esc_html__` — ModalHandler, BlockSupport
- `apply_filters` — Content processing pipeline
- `register_rest_route` — RestApi route registration
- `get_post`, `get_the_title`, `get_permalink` — RestApi content retrieval
- `home_url`, `wp_get_environment_type` — URL validation

---

Last updated: 2026-02-27 (v1.2.2..HEAD)
