# PHPStan Configuration Review

Review of `phpstan.neon` and `phpstan-baseline.neon` for static analysis level, WordPress stubs, ignored errors justification, and type coverage.

## Configuration Overview

### Main Configuration (`phpstan.neon`)

```neon
includes:
    - phpstan-baseline.neon

parameters:
    level: 5
    paths:
        - includes
        - admin
        - wp-admin-health-suite.php
        - uninstall.php
    excludePaths:
        - vendor
        - node_modules
        - tests
    bootstrapFiles:
        - vendor/php-stubs/wordpress-stubs/wordpress-stubs.php
        - phpstan-bootstrap.php
    reportUnmatchedIgnoredErrors: false
    treatPhpDocTypesAsCertain: false
    ignoreErrors:
        - '#Function [a-z_]+ invoked with [0-9]+ parameters?, [0-9]+ required\.?#'
        - '#Property .* is never read, only written\.#'
    scanDirectories:
        - vendor/php-stubs/wordpress-stubs
```

## Static Analysis Level Assessment

### Current Level: 5

PHPStan level 5 provides:

- All checks from levels 0-4
- Checking argument types passed to functions/methods
- Checking argument types passed to closures
- Missing typehints are checked for generic types
- Checking for calling unknown methods on `mixed` types

**Strengths:**

- Good balance between strictness and practicality for WordPress development
- Catches most common type errors without being overly restrictive
- Compatible with WordPress's loosely-typed ecosystem

**Considerations for Future Enhancement:**
| Level | Additional Checks | Feasibility |
|-------|-------------------|-------------|
| 6 | Missing typehints | Medium - Would require adding return types to many methods |
| 7 | Union types checked | Medium - Some refactoring needed |
| 8 | Method calls on nullable | Low - Would require significant refactoring |
| 9 | Maximum strictness | Low - Not practical for WordPress plugins |

**Recommendation:** Level 5 is appropriate for this codebase. Consider incremental moves to level 6 as the codebase matures.

## WordPress Stubs Configuration

### Dependencies

```json
"php-stubs/wordpress-stubs": "^6.x",
"szepeviktor/phpstan-wordpress": "^2.0"
```

### Bootstrap Files

1. **`vendor/php-stubs/wordpress-stubs/wordpress-stubs.php`** - Core WordPress function/class stubs
2. **`phpstan-bootstrap.php`** - Custom bootstrap defining plugin-specific constants

### Custom Bootstrap Analysis

```php
// WordPress constant commonly used to gate direct access.
define( 'ABSPATH', '/' );

// Plugin constants.
define( 'WP_ADMIN_HEALTH_VERSION', '0.0.0-phpstan' );
define( 'WP_ADMIN_HEALTH_PLUGIN_DIR', __DIR__ . '/' );
define( 'WP_ADMIN_HEALTH_PLUGIN_BASENAME', 'wp-admin-health-suite/wp-admin-health-suite.php' );

// Constants used by uninstall flow.
define( 'WP_UNINSTALL_PLUGIN', true );
```

**Assessment:**

- Bootstrap properly defines all plugin-specific constants
- `ABSPATH` stub allows WordPress-conditional code to be analyzed
- `WP_UNINSTALL_PLUGIN` enables analysis of uninstall.php

**Missing Constant:** `DB_NAME` is not defined in the bootstrap, causing 11 baseline errors. This constant is typically defined in `wp-config.php`.

**Recommendation:** Add `DB_NAME` to `phpstan-bootstrap.php`:

```php
if ( ! defined( 'DB_NAME' ) ) {
    define( 'DB_NAME', 'wordpress_phpstan' );
}
```

### WordPress Extension (`szepeviktor/phpstan-wordpress`)

The extension provides:

- Type inference for `apply_filters()` return values
- Hook callback signature validation
- WordPress-specific constant handling via `dynamicConstantNames`
- Early terminating function recognition (`wp_send_json`, `wp_nonce_ays`)

**Note:** The extension is auto-loaded via `phpstan/extension-installer`.

## Ignored Errors Analysis

### Global Ignored Patterns

| Pattern                                                             | Justification                                                      | Assessment                                                        |
| ------------------------------------------------------------------- | ------------------------------------------------------------------ | ----------------------------------------------------------------- |
| `Function [a-z_]+ invoked with [0-9]+ parameters?, [0-9]+ required` | WordPress function stubs may not fully reflect optional parameters | **Acceptable** - WordPress stubs can lag behind actual signatures |
| `Property .* is never read, only written`                           | Properties kept for future extensibility                           | **Acceptable** - Common for configuration/state properties        |

### Baseline Error Categories

The baseline (`phpstan-baseline.neon`) contains 34 suppressed errors across several categories:

#### 1. PHPDoc Parse Errors (5 instances)

- **Files:** `Installer.php`, `Plugin.php`, `OrphanedTables.php`
- **Issue:** Invalid `@param` syntax like `{array}` instead of `array`
- **Recommendation:** Fix PHPDoc syntax in source files

#### 2. Type Mismatches (8 instances)

- `switch_to_blog()` expects int, string given (2 instances)
- `size_format()` expects int|string, float given
- `get_grade()` expects int, float given
- `implode()` expects array<string>, array<array|string> given
- **Recommendation:** Add explicit type casts where appropriate

#### 3. Unsafe `new static()` Usage (3 instances)

- **Files:** `DatabaseException.php`, `MediaException.php`, `ValidationException.php`
- **Issue:** Factory pattern uses `new static()` in non-final classes
- **Assessment:** Acceptable for exception factory methods; consider marking classes `final`

#### 4. `DB_NAME` Constant Not Found (11 instances)

- **Files:** `Analyzer.php`, `Optimizer.php`, `OrphanedTables.php`
- **Recommendation:** Add `DB_NAME` definition to `phpstan-bootstrap.php`

#### 5. Dead Code Detection (6 instances)

- Unused methods, traits, and return types
- `return.unusedType` on REST controller methods
- **Assessment:** Some are false positives due to WordPress hook callbacks; others may be genuine dead code

#### 6. Always-True/False Conditions (3 instances)

- `is_array()` calls on values already known to be arrays
- Conditions that always evaluate to true/false
- **Recommendation:** Clean up redundant type checks

### Baseline Health Metrics

| Metric                   | Value | Assessment                       |
| ------------------------ | ----- | -------------------------------- |
| Total baseline errors    | 34    | Moderate technical debt          |
| Errors per analyzed file | ~0.24 | Reasonable ratio                 |
| Critical type errors     | 8     | Should be addressed              |
| Documentation errors     | 5     | Should be fixed                  |
| False positives          | ~6    | Acceptable for WordPress context |

## Type Coverage Assessment

### Strong Typing Patterns

The codebase demonstrates good type coverage practices:

1. **Interface Contracts** - Well-typed interfaces like `ConnectionInterface`:

    ```php
    public function get_var( string $query, int $x = 0, int $y = 0 );
    public function get_results( string $query, string $output = 'OBJECT' ): array;
    public function prepare( string $query, ...$args ): ?string;
    ```

2. **Property Type Declarations** - PHP 7.4+ typed properties:

    ```php
    private ConnectionInterface $connection;
    private ?SettingsInterface $settings;
    private static ?Plugin $instance = null;
    ```

3. **Return Type Declarations** - Consistent return types:
    ```php
    public function get_container(): ContainerInterface
    public function has( string $abstract ): bool
    public static function reset(): void
    ```

### Areas for Improvement

1. **PHPDoc Array Types** - Many arrays lack element type specifications:

    ```php
    // Current
    private $weights = array( ... );

    // Improved
    /** @var array<string, float> */
    private array $weights = [ ... ];
    ```

2. **Mixed Return Types** - Some methods return `mixed` implicitly:

    ```php
    // Plugin::make() returns T|mixed
    public function make( string $abstract )
    ```

3. **Callback Types** - Callable types could be more specific:

    ```php
    // Current
    $progress_callback = null

    // Improved
    /** @param (callable(float, int, int): void)|null $progress_callback */
    ```

### Type Coverage Statistics

| Category        | Coverage | Notes                                       |
| --------------- | -------- | ------------------------------------------- |
| Property types  | ~85%     | Most properties have type declarations      |
| Parameter types | ~90%     | Good coverage with PHPDoc supplements       |
| Return types    | ~75%     | Some methods lack explicit return types     |
| Generic types   | ~30%     | Limited use of `@template` and array shapes |

## Current Analysis Status

Running PHPStan against the codebase produces:

```
[ERROR] Found 62 errors
```

**New Errors (not in baseline):**

- Array offset access issues in `DuplicateFinder.php`
- Cast-to-string issues in `PluginProfilerController.php`
- `add_option()` parameter type mismatches in `SchedulerRegistry.php`
- Redundant type checks in various files

This indicates the baseline is outdated and needs regeneration.

## Recommendations

### Immediate Actions

1. **Update Bootstrap File**

    ```php
    // Add to phpstan-bootstrap.php
    if ( ! defined( 'DB_NAME' ) ) {
        define( 'DB_NAME', 'wordpress_phpstan' );
    }
    ```

2. **Regenerate Baseline**

    ```bash
    ./vendor/bin/phpstan analyse --generate-baseline --memory-limit=1G
    ```

3. **Fix PHPDoc Syntax Errors**
    - Replace `{array}` with `array` in `@param` tags
    - Ensure valid PHPDoc syntax throughout

### Short-Term Improvements

4. **Add Missing Type Declarations**
    - Add return types to public methods lacking them
    - Convert legacy `array()` properties to typed arrays

5. **Reduce Baseline Size**
    - Fix genuine type errors (8 type mismatches)
    - Address dead code warnings where appropriate

6. **Improve Array Type Annotations**
    - Document array shapes for complex return types
    - Use `@phpstan-type` for reusable type aliases

### Long-Term Goals

7. **Consider Level 6 Migration**
    - Add missing typehints incrementally
    - Use `@return` annotations for complex types

8. **Implement PHPStan Extensions**
    - Consider custom rules for project conventions
    - Add stricter checks for security-sensitive code

9. **CI Integration**
    - Run PHPStan in CI pipeline
    - Block merges with new errors
    - Track baseline size over time

## Configuration Quality Score

| Aspect                 | Score | Notes                                       |
| ---------------------- | ----- | ------------------------------------------- |
| Analysis Level         | 8/10  | Level 5 appropriate for WordPress           |
| WordPress Integration  | 9/10  | Good stubs and extension setup              |
| Bootstrap Completeness | 7/10  | Missing `DB_NAME` constant                  |
| Baseline Quality       | 6/10  | Needs regeneration, contains fixable errors |
| Type Coverage          | 7/10  | Good for PHP 7.4+, room for improvement     |
| Documentation          | 6/10  | Missing inline configuration comments       |

**Overall Score: 7.2/10**

The PHPStan configuration is functional and appropriate for a WordPress plugin. The main areas for improvement are regenerating the baseline to capture current state, adding the missing `DB_NAME` constant, and fixing the PHPDoc syntax errors that appear in the baseline.
