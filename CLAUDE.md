# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with the Pikari Gutenberg Modals plugin.

## Project Overview

WordPress plugin that adds accessible modal dialogs to the block editor. Content (posts, pages, custom post types, external URLs) is displayed in overlays triggered by inline links, buttons, or clickable group blocks. Uses the WordPress Interactivity API for reactive state management with progressive enhancement (triggers are real links that work without JavaScript).

## Required Agents

Always use these agents proactively:

- **`wordpress-core-expert`** — Review all PHP and JavaScript code changes
- **`accessibility-expert`** — Review modal functionality, keyboard navigation, focus management, ARIA attributes

## Architecture

### Three Trigger Types

1. **Inline Modal Links** — RichText format applied to text in supported blocks

   - Format: `modal-toolbar-button/modal-link` (Cmd/Ctrl+M shortcut)
   - Editor: `src/editor/modal-format.js` + `src/editor/modal-link-edit.js`
   - Server: `BlockSupport::filter_block()` transforms `<span class="modal-link-trigger">` into interactive `<a>` tags
   - Supported blocks: paragraph, heading, list, list-item, quote, verse, preformatted, navigation-link

2. **Button Block Modals** — extends core/button with a toggle

   - Adds `pikariOpenInModal` boolean attribute
   - Editor: `src/editor/button-modal-extension.js`
   - Server: `BlockSupport::filter_button_block()` adds Interactivity API attributes

3. **Group Block Modal Triggers** — makes entire group blocks clickable (card pattern)
   - Adds `pikariModalTrigger` + `pikariModalTriggerBlockId` attributes
   - Editor: `src/editor/group-modal-trigger-extension.js` — recursively detects links in inner blocks
   - Server: `includes/GroupModalTriggerSupport.php` — two-phase rendering for Query Loop support
   - Supported link sources: button, image, navigation-link, heading/paragraph (inline links), post-title, post-featured-image, post-date, read-more, post-excerpt

### PHP Classes (`includes/`)

| Class                      | Lines | Purpose                                                                             |
| -------------------------- | ----- | ----------------------------------------------------------------------------------- |
| `BlockSupport`             | ~750  | Core block rendering, trigger transformation, single modal container in `wp_footer` |
| `GroupModalTriggerSupport` | ~350  | Group block clickable cards, two-phase rendering for Query Loop                     |
| `RestApi`                  | ~340  | Search endpoint (editor) + modal-content endpoint (frontend, HTTP cached)           |
| `ModalHandler`             | ~230  | Content processing pipeline, URL validation, domain allow/block lists               |
| `BlockStyleCollector`      | ~185  | Detects blocks in modal content, collects stylesheet URLs for dynamic loading       |
| `SpeculativeLoading`       | ~155  | Hover-based prefetch (200ms delay), optional `<link rel="prefetch">` hints          |
| `EditorIntegration`        | ~115  | Editor asset enqueuing, localized config via `pikariGutenbergModals` JS object      |
| `FrontendRenderer`         | ~55   | Frontend script module + stylesheet registration (lazy-loaded)                      |

### JavaScript Files

**Editor (`src/editor/`):**

| File                               | Lines | Purpose                                                                  |
| ---------------------------------- | ----- | ------------------------------------------------------------------------ |
| `group-modal-trigger-extension.js` | ~365  | HOC for group block — link detection, auto-selection, Query Loop support |
| `modal-link-edit.js`               | ~290  | RichText format toolbar UI, LinkControl popover, post search             |
| `button-modal-extension.js`        | ~115  | HOC for button block — modal toggle in InspectorControls                 |
| `modal-format.js`                  | ~20   | RichText format type registration                                        |
| `index.js`                         | ~13   | Entry point, exports `toggleFormat`/`applyFormat`/`removeFormat`         |
| `style.scss`                       | ~65   | Editor visual indicators (dashed purple underline on modal links)        |

**Frontend (`src/frontend/`):**

| File                    | Lines | Purpose                                                                         |
| ----------------------- | ----- | ------------------------------------------------------------------------------- |
| `modal-store.js`        | ~340  | Interactivity API store — reactive state, async content loading, prefetch       |
| `modal-a11y.js`         | ~95   | Focus trap, inert background, keyboard navigation utilities                     |
| `block-style-loader.js` | ~75   | Dynamic stylesheet loading (prevents FOUC for modal content)                    |
| `index.js`              | ~9    | Entry point                                                                     |
| `style.scss`            | ~370  | Full modal UI — overlay, content, animations, responsive, print, reduced motion |

### Single Modal Container Pattern

One `<div id="pikari-modal">` is rendered in `wp_footer` (only if triggers are detected on the page). Content is loaded dynamically via REST API and inserted with proper escaping. The store name is `pikari-modal` with `data-wp-interactive="pikari-modal"`.

### REST API Endpoints

**Search** — `GET /pikari-gutenberg-modals/v1/search`

- Permission: `edit_posts`
- Params: `search` (required), `per_page`, `page`
- Returns posts with pagination headers (`X-WP-Total`, `X-WP-TotalPages`)

**Modal Content** — `GET /pikari-gutenberg-modals/v1/modal-content/{id}`

- Permission: public
- HTTP cached: ETag, Last-Modified, Cache-Control (1 hour), 304 Not Modified support
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

## Custom Hooks & Filters

```php
// Content processing
pikari_gutenberg_modals_supported_blocks       // Customize blocks supporting inline modal links
pikari_gutenberg_modals_post_content           // Filter post content before modal rendering
pikari_gutenberg_modals_url_content            // Filter external URL content
pikari_gutenberg_modals_content                // General content filter

// REST API
pikari_gutenberg_modals_search_args            // Modify WP_Query args for search endpoint
pikari_gutenberg_modals_content_response       // Modify modal-content REST response
pikari_gutenberg_modals_cache_duration         // HTTP cache max-age (default: HOUR_IN_SECONDS)

// Security
pikari_gutenberg_modals_allowed_domains        // Domain allowlist for external URLs
pikari_gutenberg_modals_blocked_domains        // Domain blocklist for external URLs

// Editor
pikari_gutenberg_modals_modal_sizes            // Add/modify modal size options in the editor dropdown

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
├── languages/                    # Translation files (.pot, .po, .mo)
├── _playground/                  # WordPress Playground blueprints
├── docs/                         # Documentation
└── assets/                       # Static assets
```

## Requirements

- WordPress 6.8+
- PHP 8.2+
- Node.js + Composer for development

## Testing

No test files exist yet. Infrastructure is configured:

- `npm test` → Jest via `@wordpress/scripts`
- `composer test` → PHPUnit
- `npm run test:e2e` → Playwright
- ESLint config includes test environment overrides
