# Development Workflow for Pikari Gutenberg Modals

This document outlines the recommended development workflow when the plugin is installed via Composer in a WordPress site.

## Current Setup

- Plugin is installed via Composer from GitHub repository
- Site uses automated deployment (push to GitHub → staging server)
- Need to maintain single composer.json for both local and staging environments

## Recommended Workflow: Direct Development + Version Tagging

### 1. Local Development

Develop directly in the installed plugin directory:
```
/web/app/plugins/pikari-gutenberg-modals/
```

**Development Commands:**
```bash
# Navigate to plugin directory
cd web/app/plugins/pikari-gutenberg-modals

# Install dependencies
npm install

# Start development with hot reload
npm start

# Build for production
npm run build

# Run linters
npm run lint:js
npm run lint:css
phpcs
```

### 2. Version Control

The plugin directory is its own Git repository:

```bash
# Check current branch
git status

# Create feature branch
git checkout -b feature/new-modal-type

# Make changes and commit
git add .
git commit -m "feat: Add new modal type"

# Push to GitHub
git push origin feature/new-modal-type
```

### 3. Testing Changes

Since you're developing directly in the WordPress installation:
- Changes are immediately visible in your local WordPress
- Use `npm start` for live reloading during development
- Test thoroughly before pushing to GitHub

### 4. Releasing New Versions

When your feature is ready:

```bash
# Merge to main branch
git checkout main
git merge feature/new-modal-type

# Update version in plugin header and package.json
# Example: 0.2.0 → 0.3.0

# Build production assets
npm run build

# Commit version bump
git add .
git commit -m "chore: Bump version to 0.3.0"

# Create release document
# Add releases/v0.3.0.md with changelog

# Tag the release
git tag v0.3.0
git push origin main --tags
```

### 5. Deploying to Staging/Production

Update your site's composer.json to use the new version:

```json
{
  "require": {
    "pikari/pikari-gutenberg-modals": "^0.3"
  }
}
```

Then push to trigger deployment:
```bash
# From site repository (not plugin)
cd /Users/steveariss/Sites/cclf/childrensliteracy
git add composer.json
git commit -m "chore: Update pikari-gutenberg-modals to v0.3.0"
git push origin main
```

## Alternative: Branch-based Development

For testing features on staging before release:

1. Update composer.json to track a branch:
   ```json
   "pikari/pikari-gutenberg-modals": "dev-feature/new-modal-type"
   ```

2. Push changes to both repositories
3. Staging will pull from the feature branch
4. When approved, merge and tag as above

## Best Practices

1. **Always build before committing** - Run `npm run build` to ensure production assets are current
2. **Test on staging first** - Use branch-based deployment for major features
3. **Semantic versioning** - Follow semver (MAJOR.MINOR.PATCH)
4. **Document releases** - Create release notes in the `releases/` folder
5. **Clean commits** - Use conventional commit messages (feat:, fix:, chore:, etc.)

## Troubleshooting

### Composer Cache Issues
If Composer doesn't pull latest changes:
```bash
ddev composer clear-cache
ddev composer update pikari/pikari-gutenberg-modals
```

### Build Conflicts
If you have uncommitted build files:
```bash
# Stash changes
git stash

# Pull latest
git pull origin main

# Re-apply changes
git stash pop

# Rebuild
npm run build
```

### Version Mismatch
Ensure version numbers match in:
- `pikari-gutenberg-modals.php` (plugin header)
- `package.json`
- Git tag
- Composer requirement