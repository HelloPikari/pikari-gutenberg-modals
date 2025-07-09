# Pikari Gutenberg Modals - Test Suite

This directory contains the comprehensive test suite for the Pikari Gutenberg Modals plugin.

## Test Structure

```
tests/
├── unit/               # Unit tests for individual components
├── integration/        # Integration tests (REST API, etc.)
├── e2e/               # End-to-end tests with Playwright
├── fixtures/          # Test data and HTML files
├── manual/            # Manual testing documentation
├── utils/             # Test utilities and helpers
└── mocks/             # Mock objects and functions
```

## Running Tests

### JavaScript Unit Tests

```bash
# Run all JavaScript tests
npm test

# Run tests in watch mode
npm test -- --watch

# Run tests with coverage
npm test -- --coverage
```

### PHP Unit Tests

```bash
# Install PHPUnit (if not already installed)
composer install --dev

# Run PHP unit tests
./vendor/bin/phpunit tests/unit

# Run with coverage
./vendor/bin/phpunit tests/unit --coverage-html tests/coverage
```

### Integration Tests

Integration tests require a WordPress test environment:

```bash
# Set up WordPress test environment
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run integration tests
./vendor/bin/phpunit tests/integration
```

### End-to-End Tests

E2E tests use Playwright:

```bash
# Install Playwright browsers
npx playwright install

# Run e2e tests
npx playwright test

# Run in headed mode (see browser)
npx playwright test --headed

# Run specific test file
npx playwright test tests/e2e/frontend-modal.e2e.js

# Open Playwright UI
npx playwright test --ui
```

## Test Coverage

Current test coverage includes:

### Unit Tests
- ✅ Format registration (`modal-format.test.js`)
- ✅ Modal link editor component (`modal-link-edit.test.js`)
- ✅ Frontend modal handler (`frontend-index.test.js`)
- ✅ Modal handler class (`class-modal-handler.test.php`)
- ✅ Block support class (`class-block-support.test.php`)

### Integration Tests
- ✅ REST API endpoints (`rest-api.test.php`)
- ✅ Search functionality
- ✅ Modal content retrieval
- ✅ External URL handling

### E2E Tests
- ✅ Editor modal creation (`editor-modal.e2e.js`)
- ✅ Frontend modal display (`frontend-modal.e2e.js`)
- ✅ Keyboard navigation
- ✅ Focus management
- ✅ Error handling

## Writing New Tests

### JavaScript Unit Tests

```javascript
// Use the test helpers
import { createMockBlock, createMockRichTextValue } from '../utils/test-helpers';

describe( 'Component Name', () => {
	it( 'should do something', () => {
		// Test implementation
	} );
} );
```

### PHP Unit Tests

```php
class Test_Class_Name extends Test_Case {
    public function test_something() {
        // Test implementation
        $this->assertTrue( $result );
    }
}
```

### E2E Tests

```javascript
const { test, expect } = require( '@playwright/test' );

test( 'should perform user action', async ( { page } ) => {
	await page.goto( '/test-page' );
	await page.click( '.selector' );
	await expect( page.locator( '.result' ) ).toBeVisible();
} );
```

## Best Practices

1. **Test Isolation**: Each test should be independent and not rely on other tests
2. **Mock External Dependencies**: Use mocks for WordPress functions, API calls, etc.
3. **Clear Assertions**: Use descriptive assertion messages
4. **Test Edge Cases**: Include tests for error conditions and boundary cases
5. **Accessibility Testing**: Include keyboard navigation and screen reader tests
6. **Performance**: Keep tests fast by mocking heavy operations

## Continuous Integration

Tests are automatically run on:
- Pull requests
- Commits to main branch
- Release tags

See `.github/workflows/ci.yml` for CI configuration.

## Troubleshooting

### Common Issues

1. **Tests fail locally but pass in CI**
   - Check Node.js and PHP versions
   - Clear test cache: `npm test -- --clearCache`
   - Ensure clean database for integration tests

2. **E2E tests timeout**
   - Increase timeout in `playwright.config.js`
   - Check if local WordPress is running
   - Verify selectors haven't changed

3. **Coverage reports missing**
   - Ensure coverage directory exists
   - Run with coverage flag explicitly
   - Check `.gitignore` isn't excluding reports

## Contributing

When adding new features:
1. Write tests first (TDD approach)
2. Ensure all tests pass
3. Add tests for edge cases
4. Update this README if adding new test categories