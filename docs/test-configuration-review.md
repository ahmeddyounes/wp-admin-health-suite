# Test Configuration Review

Review of phpunit.xml, phpunit-standalone.xml, and jest.config.js for proper test configuration, coverage reporting, and CI compatibility.

## Executive Summary

The test configuration files are well-structured and follow best practices for WordPress plugin testing. The project uses a dual PHPUnit configuration (WordPress-integrated and standalone) along with Jest for JavaScript testing. All configurations are CI-compatible and include appropriate coverage reporting settings.

**Overall Assessment: Good** - Minor improvements recommended.

---

## 1. PHPUnit Configuration (phpunit.xml)

### Purpose

This configuration is used for WordPress integration tests that require the full WordPress test suite environment.

### Configuration Analysis

#### Strengths

- **Schema validation**: Uses PHPUnit 9.5 schema for configuration validation
- **Proper bootstrap**: Points to `tests/bootstrap.php` which correctly loads WordPress test environment
- **Error handling**: All error-to-exception conversions enabled (`convertErrorsToExceptions`, `convertNoticesToExceptions`, `convertWarningsToExceptions`)
- **Test organization**: Clear testsuite definition covering both `tests/unit` and `tests/integration` directories
- **Coverage scope**: Properly includes `includes/` directory with `.php` suffix filter

#### Issues & Recommendations

| Issue                                             | Severity | Recommendation                                                                           |
| ------------------------------------------------- | -------- | ---------------------------------------------------------------------------------------- |
| Hardcoded temp paths for WP_TESTS_DIR/WP_CORE_DIR | Medium   | Use environment variables with fallback in bootstrap.php (already implemented correctly) |
| No coverage report configuration                  | Low      | Add `<report>` section for CI artifacts                                                  |
| Missing `failOnWarning` attribute                 | Low      | Consider adding `failOnWarning="true"` for stricter testing                              |

#### Coverage Exclusion Analysis

```xml
<exclude>
    <directory>includes/*/index.php</directory>
    <file>includes/autoload.php</file>
</exclude>
```

**Assessment**: Appropriate - index.php files are typically security guards, and autoload.php is generated.

---

## 2. PHPUnit Standalone Configuration (phpunit-standalone.xml)

### Purpose

This configuration runs unit tests without WordPress dependencies, using function stubs defined in `bootstrap-standalone.php`.

### Configuration Analysis

#### Strengths

- **Independent testing**: Allows testing pure PHP logic without WordPress installation
- **Comprehensive stubs**: `bootstrap-standalone.php` provides extensive WordPress function stubs (1450+ lines)
- **Fast execution**: No WordPress loading overhead
- **Same coverage scope**: Mirrors main config for consistent coverage measurement
- **Well-documented stubs**: Each stub function has proper PHPDoc comments

#### Stub Coverage Analysis

The standalone bootstrap provides stubs for:

- Translation functions (`__`, `_e`, `esc_html__`, etc.)
- Escaping functions (`esc_html`, `esc_attr`, `esc_url`, `esc_sql`)
- Option functions (`get_option`, `update_option`, `delete_option`)
- Cache functions (`wp_cache_get`, `wp_cache_set`, `wp_cache_delete`, etc.)
- Hook functions (`add_filter`, `add_action`, `apply_filters`, `do_action`)
- Sanitization functions (`sanitize_text_field`, `sanitize_key`, etc.)
- URL functions (`site_url`, `home_url`, `admin_url`, `plugins_url`)
- Post functions (`get_post`, `get_post_type`, `get_post_meta`, etc.)
- `WP_Error` class implementation

#### Issues & Recommendations

| Issue                                 | Severity | Recommendation                                                            |
| ------------------------------------- | -------- | ------------------------------------------------------------------------- |
| Some stubs always return fixed values | Low      | Document which stubs support test customization via globals               |
| No `wpdb` stub                        | Medium   | Add wpdb mock for database tests (or ensure integration tests cover this) |

---

## 3. Jest Configuration (jest.config.js)

### Purpose

JavaScript testing using Jest for React components and utility functions.

### Configuration Analysis

#### Strengths

- **Environment**: Uses `jsdom` for DOM simulation
- **CSS handling**: Uses `identity-obj-proxy` for CSS module mocking
- **Transform**: Babel-jest configured for modern JS/JSX
- **Coverage configuration**: Well-defined with appropriate exclusions
- **Coverage thresholds**: 70% minimum for branches, functions, lines, and statements
- **Multiple reporters**: `text`, `lcov`, and `html` for different use cases

#### Test File Discovery

```javascript
testMatch: [
    '**/__tests__/**/*.(test|spec).(js|jsx)',
    '**/*.(test|spec).(js|jsx)',
],
```

**Assessment**: Good pattern coverage, but the project currently uses `*.test.js` pattern directly in source directories (e.g., `assets/js/entries/dashboard.test.js`), which matches the second pattern.

#### Coverage Collection

```javascript
collectCoverageFrom: [
    'assets/js/**/*.{js,jsx}',
    '!assets/js/dist/**',
    '!assets/js/entries/**',
    '!**/*.test.{js,jsx}',
    '!**/node_modules/**',
],
```

**Issue Identified**: The configuration excludes `!assets/js/entries/**` from coverage, but the entry files contain application logic that should be tested. The test files exist (`dashboard.test.js`, `settings.test.js`, etc.) but their coverage of the entry point logic won't be counted.

#### Issues & Recommendations

| Issue                              | Severity | Recommendation                                                              |
| ---------------------------------- | -------- | --------------------------------------------------------------------------- |
| Entry files excluded from coverage | Medium   | Remove `!assets/js/entries/**` exclusion or create separate component files |
| No `testTimeout` configured        | Low      | Consider adding `testTimeout: 10000` for slower tests                       |
| No `maxWorkers` for CI             | Low      | Add `maxWorkers: '50%'` for CI environments                                 |

---

## 4. CI Workflow Compatibility

### Current CI Configuration (.github/workflows/ci.yml)

The CI workflow includes:

- **PHP Linting** (PHPCS)
- **PHPStan Static Analysis**
- **JavaScript Linting** (ESLint + Prettier)
- **PHP Tests** (Matrix: PHP 7.4, 8.0, 8.1, 8.2)
- **JavaScript Tests** (Jest)

### Compatibility Assessment

| Test Type | CI Command      | Configuration Used     | Status        |
| --------- | --------------- | ---------------------- | ------------- |
| PHP Tests | `composer test` | phpunit-standalone.xml | ✅ Compatible |
| JS Tests  | `npm test`      | jest.config.js         | ✅ Compatible |

### CI Improvement Recommendations

| Improvement                 | Priority | Description                                           |
| --------------------------- | -------- | ----------------------------------------------------- |
| Add coverage upload         | Medium   | Upload coverage to Codecov/Coveralls for tracking     |
| Add coverage artifact       | Medium   | Archive coverage HTML reports                         |
| WordPress integration tests | Low      | Consider adding WP integration test job (requires DB) |

---

## 5. Coverage Reporting Analysis

### PHP Coverage

- **Configuration**: `processUncoveredFiles="true"` ensures all PHP files are considered
- **Output**: Not explicitly configured - defaults to console output
- **CI Integration**: No coverage reporting to external services

### JavaScript Coverage

- **Configuration**: Well-configured with multiple reporters
- **Thresholds**: Enforced at 70% across all metrics
- **Output Directory**: `coverage/`

### Recommended Coverage Configuration Additions

#### phpunit.xml Coverage Reports

```xml
<coverage processUncoveredFiles="true">
    <include>
        <directory suffix=".php">includes</directory>
    </include>
    <exclude>
        <directory>includes/*/index.php</directory>
        <file>includes/autoload.php</file>
    </exclude>
    <report>
        <clover outputFile="coverage/php/clover.xml"/>
        <html outputDirectory="coverage/php/html"/>
        <text outputFile="php://stdout"/>
    </report>
</coverage>
```

#### CI Workflow Coverage Job

```yaml
- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v4
  with:
      files: coverage/lcov.info,coverage/php/clover.xml
      fail_ci_if_error: false
```

---

## 6. Bootstrap File Analysis

### tests/bootstrap.php

**Quality Assessment**: Good

Key features:

- Custom namespace autoloader for test classes
- Fallback detection for WordPress test library
- Clear error messaging when test suite not found
- Proper plugin loading via `muplugins_loaded` hook

### tests/bootstrap-standalone.php

**Quality Assessment**: Excellent

Key features:

- Comprehensive WordPress function stubs
- Support for test customization via globals (e.g., `$GLOBALS['wpha_test_wp_cache_get']`)
- Full `WP_Error` class implementation
- Essential WordPress constants defined

---

## 7. Summary of Recommendations

### High Priority

1. **Fix Jest coverage exclusion**: Remove `!assets/js/entries/**` from `collectCoverageFrom` to measure entry point code coverage

### Medium Priority

2. **Add PHPUnit coverage reports**: Configure `<report>` section for CI artifacts
3. **Add wpdb stub**: For standalone tests that need database interaction mocking
4. **Configure CI coverage uploads**: Add Codecov or similar integration

### Low Priority

5. **Add `failOnWarning="true"`**: To phpunit.xml for stricter testing
6. **Add Jest `testTimeout`**: Configure explicit timeout for complex tests
7. **Add Jest `maxWorkers`**: Optimize CI resource usage

---

## 8. Configuration Validation

### PHPUnit Schema Validation

Both phpunit.xml and phpunit-standalone.xml reference the PHPUnit 9.5 schema:

```xml
xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
```

**Status**: Valid for PHPUnit 9.x (matched by composer.json requirement)

### Jest Configuration Validation

The jest.config.js exports a valid configuration object. All referenced setup files exist:

- `jest.setup.js` ✅
- `identity-obj-proxy` package ✅
- `babel-jest` package ✅

---

## Appendix: File Checksums

| File                           | Lines | Purpose                     |
| ------------------------------ | ----- | --------------------------- |
| phpunit.xml                    | 35    | WordPress integration tests |
| phpunit-standalone.xml         | 31    | Standalone unit tests       |
| jest.config.js                 | 32    | JavaScript tests            |
| tests/bootstrap.php            | 80    | WP test environment setup   |
| tests/bootstrap-standalone.php | 1461  | Standalone test stubs       |
| jest.setup.js                  | 31    | Jest global mocks           |
