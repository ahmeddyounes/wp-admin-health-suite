# Composer Configuration Review

Review of `composer.json` for proper dependency management, autoload configuration, dev dependencies, and script definitions.

## Configuration Overview

### Package Metadata

```json
{
	"name": "wp-admin-health/suite",
	"description": "A comprehensive suite for monitoring and maintaining WordPress admin health and performance",
	"type": "wordpress-plugin",
	"license": "GPL-2.0-or-later",
	"version": "1.0.0"
}
```

**Assessment:**

| Field       | Value                   | Status  | Notes                                                       |
| ----------- | ----------------------- | ------- | ----------------------------------------------------------- |
| name        | `wp-admin-health/suite` | Valid   | Follows vendor/package convention                           |
| description | Descriptive summary     | Valid   | Clear and informative                                       |
| type        | `wordpress-plugin`      | Valid   | Enables proper handling by `composer/installers`            |
| license     | `GPL-2.0-or-later`      | Valid   | Matches WordPress licensing requirements                    |
| version     | `1.0.0`                 | Warning | Not recommended for Packagist; version should come from VCS |

**Recommendation:** Remove the `version` field if publishing to Packagist. Composer derives version from git tags.

## Dependency Management

### Production Dependencies

```json
"require": {
  "php": ">=7.4",
  "composer/installers": "^1.0 || ^2.0"
}
```

**Analysis:**

| Dependency            | Version Constraint | Purpose                          | Assessment                       |
| --------------------- | ------------------ | -------------------------------- | -------------------------------- |
| `php`                 | `>=7.4`            | PHP version requirement          | Appropriate for typed properties |
| `composer/installers` | `^1.0 \|\| ^2.0`   | WordPress plugin path management | Flexible constraint is good      |

**Strengths:**

- Minimal production dependencies reduce deployment footprint
- PHP 7.4 requirement enables typed properties while maintaining broad compatibility
- `composer/installers` enables proper plugin placement in WordPress installations

**Considerations:**

- PHP 7.4 reached end-of-life in November 2022
- Consider upgrading minimum to PHP 8.0 or 8.1 for active security support
- Current constraint supports both major versions of `composer/installers`

### Development Dependencies

```json
"require-dev": {
  "dealerdirect/phpcodesniffer-composer-installer": "^1.2",
  "phpcompatibility/phpcompatibility-wp": "^2.1",
  "phpdocumentor/phpdocumentor": "^3.0",
  "phpstan/extension-installer": "^1.4",
  "phpstan/phpstan": "^2.1",
  "phpunit/phpunit": "^9.5",
  "squizlabs/php_codesniffer": "^3.13",
  "szepeviktor/phpstan-wordpress": "^2.0",
  "wp-coding-standards/wpcs": "^3.3",
  "yoast/phpunit-polyfills": "^1.0"
}
```

**Dependency Categories:**

| Category           | Packages                                                                        | Purpose                         |
| ------------------ | ------------------------------------------------------------------------------- | ------------------------------- |
| Static Analysis    | `phpstan/phpstan`, `szepeviktor/phpstan-wordpress`                              | Type checking and bug detection |
| Code Quality       | `squizlabs/php_codesniffer`, `wp-coding-standards/wpcs`                         | WordPress coding standards      |
| Compatibility      | `phpcompatibility/phpcompatibility-wp`                                          | PHP cross-version checks        |
| Testing            | `phpunit/phpunit`, `yoast/phpunit-polyfills`                                    | Unit and integration testing    |
| Documentation      | `phpdocumentor/phpdocumentor`                                                   | API documentation generation    |
| Plugin Integration | `dealerdirect/phpcodesniffer-composer-installer`, `phpstan/extension-installer` | Auto-configuration plugins      |

**Assessment:**

| Package                         | Version | Latest | Status     |
| ------------------------------- | ------- | ------ | ---------- |
| `phpstan/phpstan`               | `^2.1`  | 2.1.33 | Up-to-date |
| `phpunit/phpunit`               | `^9.5`  | 9.6.31 | Up-to-date |
| `squizlabs/php_codesniffer`     | `^3.13` | 3.13+  | Up-to-date |
| `wp-coding-standards/wpcs`      | `^3.3`  | 3.3+   | Up-to-date |
| `szepeviktor/phpstan-wordpress` | `^2.0`  | 2.0+   | Up-to-date |
| `phpdocumentor/phpdocumentor`   | `^3.0`  | 3.9.1  | Up-to-date |

**Notes:**

- PHPUnit 9.x is compatible with PHP 7.4-8.x; PHPUnit 10+ requires PHP 8.1+
- `yoast/phpunit-polyfills` bridges compatibility between PHPUnit versions
- All packages use semantic versioning with appropriate constraints

### Missing Recommended Dependencies

Consider adding:

```json
{
	"infection/infection": "^0.27",
	"php-stubs/wordpress-stubs": "^6.x",
	"phpstan/phpstan-strict-rules": "^2.0"
}
```

| Package                        | Purpose                          | Priority                                 |
| ------------------------------ | -------------------------------- | ---------------------------------------- |
| `infection/infection`          | Mutation testing                 | Optional                                 |
| `php-stubs/wordpress-stubs`    | WordPress function stubs         | Note: Installed as transitive dependency |
| `phpstan/phpstan-strict-rules` | Additional strict PHPStan checks | Optional                                 |

## Autoload Configuration

### PSR-4 Autoloading

```json
"autoload": {
  "psr-4": {
    "WPAdminHealth\\": "includes/"
  },
  "classmap": [
    "includes/database/",
    "includes/integrations/",
    "includes/media/",
    "includes/rest/",
    "includes/performance/",
    "includes/ai/"
  ]
}
```

**Issues Identified:**

| Directory in classmap    | Actual Directory Name    | Status        |
| ------------------------ | ------------------------ | ------------- |
| `includes/database/`     | `includes/Database/`     | Case mismatch |
| `includes/integrations/` | `includes/Integrations/` | Case mismatch |
| `includes/media/`        | `includes/Media/`        | Case mismatch |
| `includes/rest/`         | `includes/REST/`         | Case mismatch |
| `includes/performance/`  | `includes/Performance/`  | Case mismatch |
| `includes/ai/`           | `includes/AI/`           | Case mismatch |

**Impact:**

- On case-sensitive filesystems (Linux, most production servers), these paths will not be found
- The PSR-4 autoloader handles these correctly, making the classmap **redundant**
- Classmap entries with incorrect casing may cause issues during `composer dump-autoload`

**Recommendation:** Remove the classmap section entirely. PSR-4 already covers all classes under `WPAdminHealth\`:

```json
"autoload": {
  "psr-4": {
    "WPAdminHealth\\": "includes/"
  }
}
```

### Custom Autoloader Analysis

The project includes a custom PSR-4 autoloader at `includes/autoload.php`:

```php
spl_autoload_register(
    function ( $class ) {
        $prefix = 'WPAdminHealth\\';
        $base_dir = WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/';
        // ... PSR-4 implementation
    }
);
```

**Assessment:**

- **Redundant:** Composer's autoloader handles PSR-4 autoloading
- **Purpose:** Likely used when Composer autoloader is not available (production without vendor)
- **Implementation:** Correctly implements PSR-4 with error logging in debug mode

**Recommendation:** This custom autoloader is appropriate for WordPress plugin distribution where users may not use Composer. Retain for production use.

### Development Autoloading

```json
"autoload-dev": {
  "psr-4": {
    "WPAdminHealth\\Tests\\": "tests/"
  },
  "classmap": [
    "tests/unit/",
    "tests/unit-standalone/",
    "tests/integration/",
    "tests/Mocks/",
    "tests/factories/"
  ]
}
```

**Assessment:**

- PSR-4 mapping for `WPAdminHealth\Tests\` is correct
- Classmap entries provide explicit registration for test files

**Directory Verification:**

| Directory                | Exists | Contains PHP Files |
| ------------------------ | ------ | ------------------ |
| `tests/unit/`            | Yes    | Yes                |
| `tests/unit-standalone/` | Yes    | Yes                |
| `tests/integration/`     | Yes    | Yes                |
| `tests/Mocks/`           | Yes    | Yes (3 files)      |
| `tests/factories/`       | Yes    | Yes (3 files)      |

**Note:** Casing matches actual directories (e.g., `Mocks` vs `mocks`).

## Configuration Options

```json
"config": {
  "optimize-autoloader": true,
  "sort-packages": true,
  "allow-plugins": {
    "composer/installers": true,
    "dealerdirect/phpcodesniffer-composer-installer": true,
    "phpstan/extension-installer": true
  }
}
```

**Assessment:**

| Option                | Value  | Purpose                                 | Assessment            |
| --------------------- | ------ | --------------------------------------- | --------------------- |
| `optimize-autoloader` | `true` | Generates optimized classmap autoloader | Recommended           |
| `sort-packages`       | `true` | Alphabetically sorts packages           | Best practice         |
| `allow-plugins`       | {...}  | Explicit plugin authorization           | Required for security |

**Plugin Allowlist:**

- `composer/installers`: Handles WordPress plugin installation paths
- `dealerdirect/phpcodesniffer-composer-installer`: Auto-configures PHPCS standards
- `phpstan/extension-installer`: Auto-loads PHPStan extensions

## Script Definitions

```json
"scripts": {
  "test": "phpunit",
  "test:standalone": "phpunit --configuration phpunit-standalone.xml",
  "test:all": [
    "@test:standalone"
  ],
  "phpcs": "phpcs",
  "phpcbf": "phpcbf",
  "phpstan": "phpstan analyse --memory-limit=1G",
  "lint:php": "phpcs",
  "lint:php:fix": "phpcbf",
  "analyse": "phpstan analyse --memory-limit=1G",
  "docs": "phpdoc run -c phpdoc.xml",
  "docs:clear": "rm -rf docs/api .phpdoc"
}
```

**Script Categories:**

| Category        | Scripts                                       | Purpose                  |
| --------------- | --------------------------------------------- | ------------------------ |
| Testing         | `test`, `test:standalone`, `test:all`         | PHPUnit test execution   |
| Code Quality    | `phpcs`, `phpcbf`, `lint:php`, `lint:php:fix` | PHP CodeSniffer          |
| Static Analysis | `phpstan`, `analyse`                          | PHPStan analysis         |
| Documentation   | `docs`, `docs:clear`                          | phpDocumentor generation |

**Issues:**

1. **`test:all` incomplete:** Only runs standalone tests, not the full test suite:

    ```json
    "test:all": [
      "@test:standalone"
    ]
    ```

    **Expected:**

    ```json
    "test:all": [
      "@test",
      "@test:standalone"
    ]
    ```

2. **Duplicate scripts:** `phpcs`/`lint:php` and `phpcbf`/`lint:php:fix` are duplicates. Consider consolidating.

3. **Missing `test` in `test:all`:** The main test suite is not included in `test:all`.

4. **No coverage script:** Consider adding:
    ```json
    "test:coverage": "phpunit --coverage-html coverage"
    ```

**Recommendations:**

```json
"scripts": {
  "test": "phpunit",
  "test:standalone": "phpunit --configuration phpunit-standalone.xml",
  "test:all": [
    "@test",
    "@test:standalone"
  ],
  "test:coverage": "phpunit --coverage-html coverage",
  "lint": "phpcs",
  "lint:fix": "phpcbf",
  "analyse": "phpstan analyse --memory-limit=1G",
  "docs": "phpdoc run -c phpdoc.xml",
  "docs:clear": "rm -rf docs/api .phpdoc",
  "check": [
    "@lint",
    "@analyse",
    "@test:all"
  ]
}
```

## Stability Settings

```json
"minimum-stability": "stable",
"prefer-stable": true
```

**Assessment:**

- `minimum-stability: stable` ensures only stable releases are installed
- `prefer-stable: true` is redundant with `minimum-stability: stable` but harmless

## Validation Results

```
$ composer validate
./composer.json is valid, but with a few warnings
# General warnings
- The version field is present, it is recommended to leave it out if the package is published on Packagist.
```

**Status:** Valid with minor warning.

## Dependency Tree Analysis

### Direct Dependencies (Production)

```
composer/installers: v2.3.0
```

### Direct Dependencies (Development)

| Package                                          | Version | Transitive Dependencies |
| ------------------------------------------------ | ------- | ----------------------- |
| `dealerdirect/phpcodesniffer-composer-installer` | v1.2.0  | 0                       |
| `phpcompatibility/phpcompatibility-wp`           | 2.1.8   | 2                       |
| `phpdocumentor/phpdocumentor`                    | v3.9.1  | 50+                     |
| `phpstan/extension-installer`                    | 1.4.3   | 0                       |
| `phpstan/phpstan`                                | 2.1.33  | 1                       |
| `phpunit/phpunit`                                | 9.6.31  | 15+                     |
| `squizlabs/php_codesniffer`                      | 3.13+   | 0                       |
| `szepeviktor/phpstan-wordpress`                  | 2.0+    | 1                       |
| `wp-coding-standards/wpcs`                       | 3.3+    | 2                       |
| `yoast/phpunit-polyfills`                        | 1.0+    | 0                       |

**Note:** `phpdocumentor/phpdocumentor` brings in a large number of transitive dependencies. Consider if documentation generation is needed in the development environment or can be done separately.

## Recommendations

### Immediate Actions

1. **Fix classmap casing:**
   Remove the `classmap` entries from `autoload` as they reference incorrect paths and are redundant with PSR-4:

    ```json
    "autoload": {
      "psr-4": {
        "WPAdminHealth\\": "includes/"
      }
    }
    ```

2. **Fix `test:all` script:**

    ```json
    "test:all": [
      "@test",
      "@test:standalone"
    ]
    ```

3. **Remove version field** (if publishing to Packagist):
    ```diff
    - "version": "1.0.0",
    ```

### Short-Term Improvements

4. **Consolidate duplicate scripts:**
   Remove `phpcs`, `phpcbf`, `lint:php`, and `lint:php:fix` aliases. Keep `lint` and `lint:fix`.

5. **Add combined check script:**

    ```json
    "check": [
      "@lint",
      "@analyse",
      "@test:all"
    ]
    ```

6. **Add coverage script:**
    ```json
    "test:coverage": "phpunit --coverage-html coverage"
    ```

### Long-Term Goals

7. **Consider PHP version upgrade:**
   PHP 7.4 is end-of-life. Consider upgrading to PHP 8.0+ for security support.

8. **Evaluate phpDocumentor necessity:**
   If API documentation is rarely generated, consider running it via a CI pipeline instead of as a dev dependency to reduce dependency count.

9. **Add pre-commit hook script:**
    ```json
    "pre-commit": [
      "@lint",
      "@analyse"
    ]
    ```

## Configuration Quality Score

| Aspect                  | Score | Notes                                            |
| ----------------------- | ----- | ------------------------------------------------ |
| Package Metadata        | 9/10  | Complete; version field should be removed        |
| Production Dependencies | 10/10 | Minimal and appropriate                          |
| Dev Dependencies        | 9/10  | Comprehensive; all up-to-date                    |
| Autoload Configuration  | 6/10  | Classmap has case mismatches; PSR-4 is correct   |
| Autoload-dev Config     | 9/10  | Correctly configured                             |
| Config Options          | 10/10 | Best practices followed                          |
| Scripts                 | 7/10  | Functional but `test:all` incomplete, duplicates |
| Stability Settings      | 10/10 | Appropriate for production plugin                |

**Overall Score: 8.5/10**

The Composer configuration is well-structured with appropriate dependencies for a WordPress plugin development environment. The main issues are the incorrect classmap casing (which should be removed since PSR-4 handles it), the incomplete `test:all` script, and duplicate script aliases. Production dependencies are minimal, and development dependencies are comprehensive and up-to-date.
