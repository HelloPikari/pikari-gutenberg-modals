#!/bin/bash

# Create Release Script for Pikari Gutenberg Modals
# This script prepares the plugin for release by building assets
# and creating a distributable archive

set -e

echo "🚀 Creating release for Pikari Gutenberg Modals..."

# Check if we're in the right directory
if [ ! -f "pikari-gutenberg-modals.php" ]; then
    echo "❌ Error: Must be run from the plugin root directory"
    exit 1
fi

# Check for uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    echo "❌ Error: You have uncommitted changes. Please commit or stash them first."
    exit 1
fi

# Get version from plugin header
VERSION=$(grep "Version:" pikari-gutenberg-modals.php | awk '{print $3}')
echo "📦 Creating release for version: $VERSION"

# Install dependencies
echo "📥 Installing dependencies..."
npm ci --silent
composer install --no-dev --optimize-autoloader --quiet

# Build assets
echo "🔨 Building production assets..."
npm run build

# Create a temporary directory for the release
TEMP_DIR=$(mktemp -d)
RELEASE_DIR="$TEMP_DIR/pikari-gutenberg-modals"

# Copy files to release directory
echo "📋 Copying files..."
mkdir -p "$RELEASE_DIR"

# Copy only necessary files (respecting .gitattributes)
rsync -av --exclude-from=<(git ls-files -co --exclude-standard | grep -E '(export-ignore|^\.git)') \
    --exclude='.git*' \
    --exclude='node_modules' \
    --exclude='src' \
    --exclude='tests' \
    --exclude='docs' \
    --exclude='issues' \
    --exclude='releases' \
    --exclude='*.log' \
    --exclude='*.lock' \
    --exclude='composer.json' \
    --exclude='package*.json' \
    --exclude='phpcs.xml' \
    --exclude='phpunit.xml.dist' \
    --exclude='webpack.config.js' \
    --exclude='CONTRIBUTING.md' \
    --exclude='CLAUDE.md' \
    --exclude='bin' \
    ./ "$RELEASE_DIR/"

# Create zip file
echo "📦 Creating zip archive..."
cd "$TEMP_DIR"
zip -rq "pikari-gutenberg-modals-$VERSION.zip" pikari-gutenberg-modals/

# Move zip to original directory
mv "pikari-gutenberg-modals-$VERSION.zip" "$OLDPWD/"

# Cleanup
rm -rf "$TEMP_DIR"

echo "✅ Release created successfully: pikari-gutenberg-modals-$VERSION.zip"
echo ""
echo "📝 Next steps:"
echo "1. Test the zip file by installing it on a fresh WordPress site"
echo "2. Create a git tag: git tag v$VERSION"
echo "3. Push the tag: git push origin v$VERSION"
echo "4. Upload the zip to GitHub releases or WordPress.org"