# Plan: Streamline Version & Documentation Management

## Context

Version numbers and documentation are frequently out of sync because multiple files need manual updates at release time. Currently: `composer.json` is stuck at `1.1.0` (should be `1.2.1`), and `CHANGELOG.md` is missing comparison links for `[1.2.1]` and `[1.2.0]`. The goal is a single-command version bump that syncs all files, plus CLAUDE.md instructions so Claude Code handles documentation updates automatically.

## Approach: `npm version` lifecycle hook + CLAUDE.md instructions

- `npm version patch|minor|major` already updates `package.json` — we add a `version` lifecycle script that propagates the new version to the PHP header, PHP constant, and `composer.json`
- `.npmrc` with `git-tag-version=false` prevents auto-commit/tag (tags come from GitHub Releases, not local)
- CLAUDE.md gets a "Release Workflow" section so Claude Code always updates docs alongside version bumps

## Changes

### 1. Create `scripts/sync-version.sh`

New file. Reads version from `package.json`, updates:

- `pikari-gutenberg-modals.php` line 6 (`Version:` header)
- `pikari-gutenberg-modals.php` line 25 (`PIKARI_GUTENBERG_MODALS_VERSION` constant)
- `composer.json` `version` field

Uses Node.js for the replacements (always available, no sed portability issues). Stages modified files so they're ready to commit.

### 2. Add `version` script to `package.json`

Add to existing `scripts` block:

```json
"version": "bash scripts/sync-version.sh"
```

This runs automatically when `npm version` is executed — after `package.json` is updated but before any commit. Safe from monorepo sync overwrite (canonical template doesn't define `version`).

### 3. Create `.npmrc`

```
git-tag-version=false
```

Prevents `npm version` from auto-committing and auto-tagging. Fits the existing PR-based workflow where GitHub Releases create the authoritative tags.

### 4. Update CLAUDE.md — add Release Workflow section

New section instructing Claude Code to:

1. Run `npm version patch|minor|major` (syncs all version files)
2. Update `CHANGELOG.md` — move Unreleased items to new version, update comparison links
3. Update `readme.txt` and `README.md` changelog sections to match
4. Commit all changes together

### 5. Fix existing version drift (immediate)

- `composer.json`: `1.1.0` → `1.2.1`
- `CHANGELOG.md`: Add missing `[1.2.1]` and `[1.2.0]` comparison links, update `[Unreleased]` to compare from `v1.2.1`

## Files to create/modify

| File                      | Action                        |
| ------------------------- | ----------------------------- |
| `scripts/sync-version.sh` | Create                        |
| `package.json`            | Add `version` script          |
| `.npmrc`                  | Create                        |
| `CLAUDE.md`               | Add Release Workflow section  |
| `composer.json`           | Fix version `1.1.0` → `1.2.1` |
| `CHANGELOG.md`            | Fix comparison links          |

## Workflow after implementation

```
npm version patch          # 1.2.1 → 1.2.2 in all 4 files
                           # Claude updates CHANGELOG, readme.txt, README.md
git add -A && git commit   # Single commit with version + docs
git push                   # Push branch, create PR
```

## Verification

1. Run `npm version patch` on a test branch and confirm all 4 files update consistently
2. Verify `grep -n "version" package.json composer.json pikari-gutenberg-modals.php | head -6` shows matching versions
3. Run `npm run lint:all` to ensure no formatting issues introduced
4. Confirm `CHANGELOG.md` links resolve correctly on GitHub
