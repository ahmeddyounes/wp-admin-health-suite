# WP Admin Health Suite - Testing Guide

This directory contains the PHPUnit test suite for the WP Admin Health Suite plugin.

## Setup

### Prerequisites

- PHP 7.4 or higher
- MySQL or MariaDB
- Composer
- SVN (for downloading WordPress test library)

### Installation

1. Install Composer dependencies:
   ```bash
   composer install
   ```

2. Set up the WordPress test environment:
   ```bash
   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

   **Parameters:**
   - `wordpress_test` - Database name for tests (will be created)
   - `root` - MySQL username
   - `''` - MySQL password (empty in this example)
   - `localhost` - MySQL host
   - `latest` - WordPress version (or specify version like `6.4`)

   **Note:** The test database will be reset each time tests run, so use a dedicated test database.

### Environment Variables

You can customize the test environment using these environment variables:

- `WP_TESTS_DIR` - Path to WordPress test library (default: `/tmp/wordpress-tests-lib`)
- `WP_CORE_DIR` - Path to WordPress core files (default: `/tmp/wordpress`)

## Running Tests

### Run all tests:
```bash
composer test
```

or

```bash
vendor/bin/phpunit
```

### Run specific test file:
```bash
vendor/bin/phpunit tests/unit/test-sample.php
```

### Run tests with coverage:
```bash
vendor/bin/phpunit --coverage-html coverage
```

## Test Structure

```
tests/
├── bootstrap.php          # Test bootstrap file
├── test-case.php         # Base test case class
├── factories/            # Custom test factories
│   ├── class-post-factory.php
│   ├── class-attachment-factory.php
│   └── class-comment-factory.php
├── unit/                 # Unit tests
│   └── test-sample.php
└── integration/          # Integration tests
```

## Writing Tests

### Basic Test Example

```php
<?php
namespace WPAdminHealth\Tests\Unit;

use WPAdminHealth\Tests\Test_Case;

class Test_My_Feature extends Test_Case {

    public function test_something() {
        $post_id = $this->create_test_post();
        $this->assertGreaterThan(0, $post_id);
    }
}
```

### Using Factories

The test suite includes custom factories with helpful methods:

#### Post Factory
```php
// Create a post with revisions
$post_id = $this->create_test_post();
$this->factory()->post->create_with_revisions(5);

// Create multiple posts
$post_ids = $this->factory()->post->create_many_posts(10);
```

#### Attachment Factory
```php
// Create an image attachment
$attachment_id = $this->create_test_attachment();

// Create attachment with alt text
$this->factory()->attachment->create_with_alt_text('My alt text');

// Create orphaned attachments
$this->factory()->attachment->create_orphaned();
```

#### Comment Factory
```php
// Create comments for a post
$comment_ids = $this->factory()->comment->create_many_for_post($post_id, 5);

// Create a comment thread
$thread = $this->factory()->comment->create_thread($post_id, 3);
```

### Custom Assertions

The base test case provides custom assertions:

```php
// Assert option value
$this->assertOptionEquals('my_option', 'expected_value');

// Assert post meta value
$this->assertPostMetaEquals($post_id, 'meta_key', 'expected_value');

// Assert hook has callback
$this->assertHookHasCallback('init', 'my_callback_function');
```

## Test Database

The test suite uses an isolated test database that is:
- Created automatically by the install script
- Completely separate from your development/production databases
- Reset between test runs
- Safe to destroy and recreate

## Continuous Integration

This test suite is designed to work with CI/CD pipelines. Example GitHub Actions workflow:

```yaml
- name: Install WordPress Test Suite
  run: bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest

- name: Run tests
  run: composer test
```

## Troubleshooting

### "WordPress test suite not found"

Run the install script:
```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### Database connection errors

Check your database credentials and ensure MySQL/MariaDB is running.

### Permission errors

Ensure the test directories are writable:
```bash
chmod -R 755 tests/
```

## Best Practices

1. **Test Isolation**: Each test should be independent and not rely on other tests
2. **Use Factories**: Leverage the factory methods for creating test data
3. **Clean Up**: The base test case handles cleanup automatically
4. **Descriptive Names**: Use descriptive test method names (e.g., `test_attachment_deletes_orphaned_files`)
5. **One Assertion Per Concept**: Each test should verify one specific behavior

## Resources

- [WordPress PHPUnit Testing](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/plugin-unit-tests/)
