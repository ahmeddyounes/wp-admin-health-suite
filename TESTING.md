# Testing Guide - WP Admin Health Suite

This document provides instructions for setting up and running the PHPUnit test suite for the WP Admin Health Suite plugin.

## Prerequisites

- PHP 7.4 or higher
- MySQL or MariaDB
- Composer
- Subversion (SVN) - for downloading WordPress test library

## Quick Setup

### 1. Install Composer Dependencies

```bash
composer install
```

### 2. Set Up WordPress Test Environment

Run the install script to set up the WordPress test suite:

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

**Parameters:**

- `wordpress_test` - Database name for tests (will be created)
- `root` - MySQL username
- `''` - MySQL password (empty in this example)
- `localhost` - MySQL host
- `latest` - WordPress version (`latest` or specific version like `6.4`)

**Important:** The test database will be completely reset each time tests run. Use a dedicated test database, never your development or production database.

### 3. Run Tests

```bash
composer test
```

or

```bash
vendor/bin/phpunit
```

## What Was Set Up

The M09-01 implementation configured:

### Directory Structure

```
tests/
├── bootstrap.php                    # Test bootstrap loader
├── test-case.php                    # Base test case class
├── README.md                        # Detailed testing guide
├── factories/                       # Custom factories for test data
│   ├── class-post-factory.php      # Extended post factory
│   ├── class-attachment-factory.php # Extended attachment factory
│   └── class-comment-factory.php   # Extended comment factory
├── unit/                           # Unit tests
│   └── test-sample.php            # Sample test demonstrating setup
└── integration/                    # Integration tests (empty, ready for use)
```

### Configuration Files

- **phpunit.xml** - PHPUnit configuration with test suites and coverage settings
- **composer.json** - Updated with PHPUnit and Yoast PHPUnit Polyfills dependencies
- **bin/install-wp-tests.sh** - WordPress test suite installer script
- **.gitignore** - Updated to exclude test artifacts

### Features

1. **Base Test Case Class** (`tests/test-case.php`)
    - Extends `WP_UnitTestCase`
    - Includes Yoast PHPUnit Polyfills for cross-version compatibility
    - Provides helper methods for creating test posts, attachments, and comments
    - Custom assertions for WordPress-specific testing

2. **Custom Factories** (`tests/factories/`)
    - **Post Factory**: Create posts with revisions, bulk post creation, trashed posts
    - **Attachment Factory**: Create images with dimensions, alt text, orphaned attachments
    - **Comment Factory**: Create spam/trashed comments, comment threads, bulk comments

3. **Isolated Test Database**
    - Completely separate from development/production databases
    - Automatically reset between test runs
    - Safe to destroy and recreate

4. **Test Coverage Support**
    - Configured to generate code coverage reports
    - Excludes index.php files and autoload.php from coverage

## Running Tests

### Run All Tests

```bash
composer test
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/unit/test-sample.php
```

### Run with Code Coverage

```bash
vendor/bin/phpunit --coverage-html coverage
```

Then open `coverage/index.html` in your browser.

### Run Specific Test Method

```bash
vendor/bin/phpunit --filter test_wordpress_loaded
```

## Writing Tests

### Example Test

```php
<?php
namespace WPAdminHealth\Tests\Unit;

use WPAdminHealth\Tests\Test_Case;

class Test_My_Feature extends Test_Case {

    public function test_post_creation() {
        $post_id = $this->create_test_post(
            array(
                'post_title' => 'Test Post',
            )
        );

        $this->assertGreaterThan(0, $post_id);
        $post = get_post($post_id);
        $this->assertEquals('Test Post', $post->post_title);
    }
}
```

### Using Custom Factories

```php
// Create a post with 5 revisions
$post_id = $this->factory()->post->create_with_revisions(5);

// Create multiple posts at once
$post_ids = $this->factory()->post->create_many_posts(10);

// Create an attachment with alt text
$attachment_id = $this->factory()->attachment->create_with_alt_text('Alt text');

// Create a comment thread (parent + replies)
$thread = $this->factory()->comment->create_thread($post_id, 3);
```

### Custom Assertions

```php
// Assert option value
$this->assertOptionEquals('my_option', 'expected_value');

// Assert post meta value
$this->assertPostMetaEquals($post_id, 'meta_key', 'expected_value');

// Assert hook has callback
$this->assertHookHasCallback('init', 'my_callback_function');
```

## Troubleshooting

### "WordPress test suite not found"

This means you need to run the WordPress test suite installer:

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Database Connection Errors

1. Verify MySQL/MariaDB is running
2. Check your database credentials
3. Ensure the MySQL user has permission to create databases

### Permission Errors

Ensure test directories are writable:

```bash
chmod -R 755 tests/
```

### SVN Not Installed

On macOS with Homebrew:

```bash
brew install subversion
```

On Ubuntu/Debian:

```bash
sudo apt-get install subversion
```

## Environment Variables

Customize the test environment:

- `WP_TESTS_DIR` - Path to WordPress test library (default: `/tmp/wordpress-tests-lib`)
- `WP_CORE_DIR` - Path to WordPress core files (default: `/tmp/wordpress`)

Example:

```bash
export WP_TESTS_DIR=/custom/path/wordpress-tests-lib
export WP_CORE_DIR=/custom/path/wordpress
composer test
```

## CI/CD Integration

The test suite works with continuous integration platforms. Example GitHub Actions:

```yaml
- name: Set up PHP
  uses: shivammathur/setup-php@v2
  with:
      php-version: '8.0'

- name: Install dependencies
  run: composer install

- name: Install WordPress Test Suite
  run: bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest

- name: Run tests
  run: composer test
```

## Best Practices

1. **Test Isolation**: Each test should be independent
2. **Use Factories**: Leverage factory methods for creating test data
3. **Descriptive Names**: Use clear, descriptive test method names
4. **One Concept Per Test**: Each test should verify one specific behavior
5. **Setup/Teardown**: Use `set_up()` and `tear_down()` methods for test-specific setup

## Additional Resources

- [WordPress PHPUnit Testing](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Test Suite on GitHub](https://github.com/WordPress/wordpress-develop)
- [Yoast PHPUnit Polyfills](https://github.com/Yoast/PHPUnit-Polyfills)

## Next Steps

Now that the test infrastructure is set up, you can:

1. Write unit tests for plugin classes in `tests/unit/`
2. Write integration tests in `tests/integration/`
3. Run tests as part of your development workflow
4. Set up continuous integration to run tests automatically
5. Monitor code coverage to ensure comprehensive testing

For detailed information about the test utilities and helper methods, see `tests/README.md`.
