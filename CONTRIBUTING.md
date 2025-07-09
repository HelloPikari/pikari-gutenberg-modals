# Contributing to Pikari Gutenberg Modals

Thank you for your interest in contributing to Pikari Gutenberg Modals! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for all contributors.

## How to Contribute

### Reporting Issues

1. Check existing issues to avoid duplicates
2. Use the issue template when available
3. Provide clear reproduction steps
4. Include system information (WordPress version, PHP version, browser)
5. Add screenshots or screen recordings when applicable

### Suggesting Features

1. Open an issue with the "Feature Request" label
2. Describe the problem your feature would solve
3. Provide use cases and examples
4. Be open to discussion and alternative solutions

### Submitting Code

#### Setup Development Environment

1. Fork the repository
2. Clone your fork locally
3. Install dependencies:
   ```bash
   npm install
   composer install
   ```
4. Start development build:
   ```bash
   npm start
   ```

#### Development Guidelines

1. **Code Standards**
   - Follow WordPress Coding Standards for PHP
   - Use PSR-2 with spaces (see `phpcs.xml`)
   - Follow WordPress JavaScript standards
   - Run linters before committing:
     ```bash
     npm run lint:js
     npm run lint:css
     composer lint
     ```

2. **Testing**
   - Add tests for new features
   - Ensure existing tests pass
   - Run the test suite:
     ```bash
     # JavaScript unit tests
     npm test
     
     # PHP unit tests (requires WordPress test suite)
     composer test
     
     # E2E tests
     npm run test:e2e
     ```
   - Test in multiple browsers
   - Test with keyboard navigation
   - Verify screen reader compatibility
   - See [Testing Guide](tests/README.md) for detailed testing documentation

3. **Documentation**
   - Update README.md for user-facing changes
   - Update CLAUDE.md for architectural changes
   - Add JSDoc comments for JavaScript functions
   - Add PHPDoc comments for PHP methods
   - Document new hooks and filters

#### Pull Request Process

1. Create a feature branch from `main`
2. Make your changes following the guidelines above
3. Commit with clear, descriptive messages
4. Push to your fork
5. Open a pull request with:
   - Clear title and description
   - Reference to related issues
   - Screenshots/recordings for UI changes
   - Testing instructions
   - Checklist completion

#### PR Checklist

- [ ] Code follows project standards
- [ ] Tests added/updated and passing
- [ ] Documentation updated
- [ ] Linting passes (`npm run lint:js`, `npm run lint:css`, `composer lint`)
- [ ] Unit tests pass (`npm test`)
- [ ] Compatible with WordPress 6.8+
- [ ] Compatible with PHP 8.3+
- [ ] Tested accessibility
- [ ] Tested responsive design

### Development Commands

```bash
# Start development server
npm start

# Build for production
npm run build

# Run JavaScript linting
npm run lint:js

# Run CSS linting
npm run lint:css

# Run PHP linting
composer lint
composer lint:fix

# Create plugin ZIP
npm run plugin-zip

# Update WordPress packages
npm run packages-update
```

## Project Structure

See [docs/development.md](docs/development.md) for detailed information about the project architecture.

## Release Process (Maintainers)

### Automated Release Process

The plugin uses GitHub Actions to automatically create releases when you push a version tag from the main branch. Here's the streamlined process:

1. **Ensure all changes are merged to main branch** (releases are only created from main)

2. **Update version numbers** in three files:
   - `pikari-gutenberg-modals.php` (plugin header)
   - `package.json`
   - `CHANGELOG.md` (move items from "Unreleased" to new version section with date)

2. **Run pre-release checks**:
   ```bash
   # Ensure clean working directory
   git status
   
   # Run linting
   npm run lint:js
   npm run lint:css
   composer lint
   
   # Run tests
   npm test
   
   # Build and test locally
   npm run build
   ```

3. **Commit version updates**:
   ```bash
   git add -A
   git commit -m "Prepare v0.2.0 release"
   git push origin your-branch
   ```

4. **Create and push tag**:
   ```bash
   git tag v0.2.0
   git push origin v0.2.0
   ```

5. **Automated release creation**:
   - GitHub Actions will automatically:
     - Run all tests and linting
     - Build production assets
     - Create a ZIP file with only distribution files
     - Extract release notes from CHANGELOG.md
     - Create a GitHub release with the ZIP attached
     - Mark as pre-release if version contains "alpha" or "beta"

### Manual Release Process (Alternative)

If you need to create a release manually:

1. Run the release script:
   ```bash
   ./bin/create-release.sh
   ```
2. Upload the generated ZIP to GitHub releases manually

### Composer Distribution

The plugin can be installed via Composer. The `.gitattributes` file ensures that only production files are included when installing from a git tag, excluding:
- Development files (src/, tests/, docs/)
- Build configuration files
- Node modules and composer dev dependencies

### Version Naming Convention

- `x.y.z-alpha` - Alpha releases (early testing)
- `x.y.z-beta` - Beta releases (feature complete, testing)
- `x.y.z-rc.1` - Release candidates
- `x.y.z` - Stable releases

Follow [Semantic Versioning](https://semver.org/):
- MAJOR version for incompatible API changes
- MINOR version for new functionality (backwards compatible)
- PATCH version for backwards compatible bug fixes

## Questions?

Feel free to open an issue for clarification or reach out to the maintainers.

Thank you for contributing!