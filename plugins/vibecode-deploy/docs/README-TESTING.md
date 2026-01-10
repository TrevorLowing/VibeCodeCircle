# Testing Guide for Vibe Code Deploy

This document explains how to set up and run tests for the Vibe Code Deploy plugin.

## Prerequisites

- PHP 8.0 or higher
- Composer (for PHPUnit)
- MySQL database for WordPress test suite
- WordPress test suite

## Installation

### 1. Install PHPUnit via Composer

```bash
cd plugins/vibecode-deploy
composer require --dev phpunit/phpunit:^9.5
```

### 2. Install WordPress Test Suite

```bash
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
```

Example:
```bash
bin/install-wp-tests.sh wordpress_test root root localhost latest
```

This will:
- Download WordPress core
- Set up the WordPress test suite
- Create a test database

## Running Tests

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/test-settings.php
```

### Run with Coverage

```bash
vendor/bin/phpunit --coverage-html coverage/
```

## Test Structure

Tests are located in the `tests/` directory:

- `test-settings.php` - Tests for Settings class
- `test-plugin-lifecycle.php` - Tests for activation/deactivation hooks
- `test-security.php` - Tests for security features (ABSPATH checks, sanitization)

## Writing New Tests

1. Create a new test file in `tests/` directory
2. Extend `WP_UnitTestCase`
3. Use WordPress test functions (`$this->factory`, etc.)
4. Follow naming convention: `test-*.php` for files, `test_*` for methods

Example:

```php
<?php
class Test_My_Feature extends WP_UnitTestCase {
    public function test_my_function() {
        $result = my_function();
        $this->assertEquals( 'expected', $result );
    }
}
```

## Continuous Integration

Tests should be run in CI/CD pipelines:

```yaml
# Example GitHub Actions
- name: Run PHPUnit tests
  run: |
    composer install
    bin/install-wp-tests.sh test_db test_user test_pass
    vendor/bin/phpunit
```

## Notes

- Tests use WordPress test database (separate from production)
- Test database is created/destroyed automatically
- Use `WP_UnitTestCase` for WordPress-specific tests
- Use `PHPUnit\Framework\TestCase` for unit tests without WordPress
