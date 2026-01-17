# Code Quality Documentation Review

This document provides a comprehensive review of the code quality tooling and configuration for the WP Admin Health Suite plugin, covering PHPCS, PHPStan, ESLint, and Prettier setup documentation and coding standards reference.

## Overview

The project employs a multi-layered code quality strategy:

| Tool                | Language       | Purpose                      |
| ------------------- | -------------- | ---------------------------- |
| PHPCS               | PHP            | Coding standards enforcement |
| PHPStan             | PHP            | Static analysis              |
| ESLint              | JavaScript     | Linting and code quality     |
| Prettier            | JS/CSS/JSON/MD | Code formatting              |
| Husky + lint-staged | All            | Pre-commit hooks             |

## PHP Code Quality

### PHPCS (PHP_CodeSniffer)

**Configuration File:** `phpcs.xml`

#### Analyzed Paths

- `includes/`
- `admin/`
- `wp-admin-health-suite.php`
- `uninstall.php`

#### Excluded Paths

- `vendor/`
- `node_modules/`
- `tests/`
- `assets/`

#### Standards Used

| Standard           | Description                                 |
| ------------------ | ------------------------------------------- |
| WordPress-Extra    | Core WordPress coding standards with extras |
| WordPress-Docs     | Documentation standards (relaxed)           |
| PHPCompatibilityWP | PHP cross-version compatibility             |

#### Key Exclusions

The following rules are intentionally excluded:

| Rule                                                  | Reason                                               |
| ----------------------------------------------------- | ---------------------------------------------------- |
| `Universal.Arrays.DisallowShortArraySyntax`           | Allows modern `[]` array syntax                      |
| `Universal.Operators.DisallowShortTernary`            | Allows `?:` ternary operator                         |
| `WordPress.Files.FileName.NotHyphenatedLowercase`     | PSR-4 autoloading compatibility                      |
| `WordPress.Files.FileName.InvalidClassFileName`       | PSR-4 autoloading compatibility                      |
| `WordPress.DB.PreparedSQL.NotPrepared`                | Avoids false positives with `$wpdb->prepare()`       |
| `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`    | Avoids false positives with interpolated table names |
| `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | Project logs exceptions without rendering            |

#### Configuration

```xml
<!-- PHP Version -->
<config name="testVersion" value="7.4-"/>

<!-- WordPress Version -->
<config name="minimum_wp_version" value="5.8"/>

<!-- Text Domain -->
<property name="text_domain" type="array">
    <element value="wp-admin-health-suite"/>
</property>

<!-- Global Prefixes -->
<property name="prefixes" type="array">
    <element value="wp_admin_health"/>
    <element value="WPAdminHealth"/>
    <element value="wpha"/>
</property>
```

#### Commands

```bash
# Run PHPCS
composer phpcs

# Auto-fix issues
composer phpcbf

# Or directly
./vendor/bin/phpcs
./vendor/bin/phpcbf
```

### PHPStan

**Configuration File:** `phpstan.neon`

**Supporting Files:**

- `phpstan-bootstrap.php` - Constants definition for static analysis
- `phpstan-baseline.neon` - Known issues baseline (35 entries)

#### Analysis Level

**Level 5** - Medium strictness including:

- Unknown variables
- Unknown method calls
- Return type checking
- Dead code detection
- Union types

#### Analyzed Paths

- `includes/`
- `admin/`
- `wp-admin-health-suite.php`
- `uninstall.php`

#### Excluded Paths

- `vendor/`
- `node_modules/`
- `tests/`

#### Bootstrap File

The `phpstan-bootstrap.php` defines constants normally set at runtime:

```php
define('ABSPATH', '/');
define('WP_ADMIN_HEALTH_VERSION', '0.0.0-phpstan');
define('WP_ADMIN_HEALTH_PLUGIN_DIR', __DIR__ . '/');
define('WP_ADMIN_HEALTH_PLUGIN_BASENAME', 'wp-admin-health-suite/wp-admin-health-suite.php');
define('WP_UNINSTALL_PLUGIN', true);
```

#### Global Ignores

```yaml
ignoreErrors:
    # WordPress function stubs may not be fully typed
    - '#Function [a-z_]+ invoked with [0-9]+ parameters?, [0-9]+ required\.?#'
    # Intentionally kept properties for future extensibility
    - '#Property .* is never read, only written\.#'
```

#### Baseline Summary

The baseline contains known issues categorized as:

| Category                      | Count | Description                                |
| ----------------------------- | ----- | ------------------------------------------ |
| `new.static` unsafe           | 3     | Exception classes using static constructor |
| `constant.notFound` (DB_NAME) | 11    | WordPress constant not in stubs            |
| `argument.type`               | 5     | Type mismatches with WP stubs              |
| `phpDoc.parseError`           | 5     | PHPDoc syntax issues                       |
| `return.unusedType`           | 5     | WP_Error never returned                    |
| Other                         | 6     | Various minor issues                       |

#### Commands

```bash
# Run PHPStan
composer phpstan

# Or with memory limit
./vendor/bin/phpstan analyse --memory-limit=1G
```

## JavaScript Code Quality

### ESLint

**Configuration File:** `.eslintrc.json`

#### Environment

```json
{
	"browser": true,
	"es2021": true,
	"node": true
}
```

#### Extends

| Config                                        | Description                                |
| --------------------------------------------- | ------------------------------------------ |
| `plugin:@wordpress/eslint-plugin/recommended` | WordPress coding standards                 |
| `plugin:react/recommended`                    | React best practices                       |
| `plugin:react-hooks/recommended`              | React Hooks rules                          |
| `prettier`                                    | Disables rules that conflict with Prettier |

#### Plugins

- `@wordpress` - WordPress ESLint rules
- `react` - React-specific linting
- `react-hooks` - React Hooks linting

#### Custom Rules

| Rule                                              | Level | Description                |
| ------------------------------------------------- | ----- | -------------------------- |
| `react/react-in-jsx-scope`                        | off   | Not needed with React 17+  |
| `react/prop-types`                                | warn  | PropTypes validation       |
| `no-unused-vars`                                  | warn  | Unused variable detection  |
| `no-console`                                      | off   | Console statements allowed |
| `no-alert`                                        | warn  | Alert statements warning   |
| `jsdoc/require-param-type`                        | warn  | JSDoc param types          |
| `jsx-a11y/no-redundant-roles`                     | warn  | Accessibility              |
| `jsx-a11y/click-events-have-key-events`           | warn  | Accessibility              |
| `jsx-a11y/no-noninteractive-element-interactions` | warn  | Accessibility              |
| `jsx-a11y/no-static-element-interactions`         | warn  | Accessibility              |
| `@wordpress/no-unused-vars-before-return`         | warn  | WordPress specific         |

#### Global Variables

```json
{
	"jQuery": "readonly",
	"wp": "readonly",
	"wpAdminHealthData": "readonly",
	"Chart": "readonly"
}
```

#### Ignored Patterns

- `assets/js/dist/`
- `node_modules/`
- `*.min.js`

#### Test File Overrides

```json
{
	"files": ["**/*.test.js", "**/*.test.jsx"],
	"env": {
		"jest": true
	}
}
```

#### Commands

```bash
# Run ESLint
npm run lint

# Auto-fix issues
npm run lint:fix
```

### Prettier

**Configuration File:** `.prettierrc.json`

#### Core Settings

| Setting          | Value  | Description                               |
| ---------------- | ------ | ----------------------------------------- |
| `useTabs`        | true   | Use tabs for indentation                  |
| `tabWidth`       | 4      | Tab width of 4 spaces                     |
| `printWidth`     | 80     | Line wrap at 80 characters                |
| `singleQuote`    | true   | Use single quotes                         |
| `trailingComma`  | es5    | Trailing commas where valid in ES5        |
| `bracketSpacing` | true   | Spaces in object literals                 |
| `arrowParens`    | always | Always use parentheses in arrow functions |
| `semi`           | true   | Use semicolons                            |
| `endOfLine`      | lf     | Unix line endings                         |

#### Overrides

JSON and YAML files use different settings:

```json
{
	"files": ["*.json", "*.yml", "*.yaml"],
	"options": {
		"useTabs": false,
		"tabWidth": 2
	}
}
```

#### Ignored Files (.prettierignore)

- `node_modules`
- `vendor`
- `assets/js/dist`
- `*.min.js`
- `*.min.css`
- `build`
- `dist`
- `.git`
- `.t2`
- `.logs`
- `.plans`
- `composer.lock`
- `package-lock.json`

#### Commands

```bash
# Format all files
npm run format

# Check formatting
npm run format:check
```

## Pre-commit Hooks

### Husky Configuration

**Location:** `.husky/pre-commit`

The pre-commit hook runs:

1. **lint-staged** - For JS/CSS files
2. **PHPCS** - For staged PHP files

#### lint-staged Configuration (package.json)

```json
{
	"*.{js,jsx}": ["eslint --fix", "prettier --write"],
	"*.{json,css,scss,md}": ["prettier --write"]
}
```

#### PHP Pre-commit Flow

```bash
# Gets staged PHP files
STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep ".php\{0,1\}$")

# Runs PHPCS on staged files
./vendor/bin/phpcs $STAGED_PHP_FILES
```

## Coding Standards Reference

### PHP Standards

#### Naming Conventions

| Type      | Convention            | Example                   |
| --------- | --------------------- | ------------------------- |
| Classes   | PascalCase            | `HealthCalculator`        |
| Methods   | snake_case            | `get_health_score()`      |
| Variables | snake_case            | `$health_data`            |
| Constants | UPPER_SNAKE_CASE      | `WP_ADMIN_HEALTH_VERSION` |
| Hooks     | lowercase with prefix | `wpha_init`               |

#### Global Prefixes

- Functions: `wp_admin_health_*`
- Classes: `WPAdminHealth\*`
- Hooks: `wpha_*`
- Options: `wpha_*`

#### File Naming

Files follow PSR-4 autoloading:

- Class `WPAdminHealth\Database\Analyzer` lives at `includes/database/Analyzer.php`
- No `class-` prefix required

### JavaScript Standards

#### Naming Conventions

| Type       | Convention       | Example               |
| ---------- | ---------------- | --------------------- |
| Components | PascalCase       | `HealthDashboard`     |
| Functions  | camelCase        | `calculateScore()`    |
| Variables  | camelCase        | `healthData`          |
| Constants  | UPPER_SNAKE_CASE | `API_ENDPOINT`        |
| Files      | kebab-case       | `health-dashboard.js` |

#### React Patterns

- Functional components with hooks
- PropTypes for prop validation
- JSX accessibility (jsx-a11y)

### Text Domain

All translatable strings must use: `wp-admin-health-suite`

```php
__('Health Score', 'wp-admin-health-suite')
esc_html__('Dashboard', 'wp-admin-health-suite')
```

## Available Commands Summary

### PHP

```bash
composer phpcs          # Run PHPCS
composer phpcbf         # Auto-fix PHPCS issues
composer phpstan        # Run PHPStan
composer lint:php       # Alias for phpcs
composer lint:php:fix   # Alias for phpcbf
composer analyse        # Alias for phpstan
```

### JavaScript

```bash
npm run lint            # Run ESLint
npm run lint:fix        # Auto-fix ESLint issues
npm run format          # Format with Prettier
npm run format:check    # Check formatting
```

### Combined

```bash
npm test                # Run Jest tests
composer test           # Run PHPUnit tests
```

## Dependencies

### PHP Dev Dependencies

| Package                                        | Version | Purpose                     |
| ---------------------------------------------- | ------- | --------------------------- |
| squizlabs/php_codesniffer                      | ^3.13   | PHPCS core                  |
| wp-coding-standards/wpcs                       | ^3.3    | WordPress standards         |
| phpcompatibility/phpcompatibility-wp           | ^2.1    | PHP compatibility           |
| dealerdirect/phpcodesniffer-composer-installer | ^1.2    | Auto-install standards      |
| phpstan/phpstan                                | ^2.1    | Static analysis             |
| szepeviktor/phpstan-wordpress                  | ^2.0    | WordPress PHPStan extension |
| phpstan/extension-installer                    | ^1.4    | Auto-install extensions     |

### Node Dev Dependencies

| Package                    | Version | Purpose                   |
| -------------------------- | ------- | ------------------------- |
| eslint                     | ^8.50.0 | ESLint core               |
| @wordpress/eslint-plugin   | ^23.0.0 | WordPress ESLint plugin   |
| eslint-plugin-react        | ^7.33.0 | React ESLint              |
| eslint-plugin-react-hooks  | ^4.6.0  | React Hooks ESLint        |
| eslint-config-prettier     | ^10.1.8 | Prettier integration      |
| prettier                   | ^3.7.4  | Prettier core             |
| @wordpress/prettier-config | ^4.37.0 | WordPress Prettier config |
| husky                      | ^9.1.7  | Git hooks                 |
| lint-staged                | ^16.2.7 | Staged files linting      |

## Recommendations

### Existing Strengths

1. **Comprehensive tooling** - Full coverage of PHP and JS with PHPCS, PHPStan, ESLint, and Prettier
2. **WordPress alignment** - Uses official WordPress coding standards packages
3. **Pre-commit enforcement** - Husky + lint-staged ensures quality at commit time
4. **Baseline management** - PHPStan baseline allows gradual improvement
5. **Reasonable rule relaxation** - Excludes rules that conflict with PSR-4 or cause false positives

### Potential Improvements

1. **PHPStan level increase** - Consider moving from level 5 to 6+ over time
2. **Baseline reduction** - Work to reduce the 35 baseline entries
3. **DB_NAME constant** - Add to bootstrap file to eliminate 11 baseline entries
4. **PHPDoc fixes** - Address the 5 phpDoc.parseError entries
5. **ESLint flat config** - Consider migrating to ESLint flat config format
6. **CI/CD integration** - Document CI pipeline integration if not present

### Review Status

| Tool       | Status     | Notes                            |
| ---------- | ---------- | -------------------------------- |
| PHPCS      | Configured | Standards aligned with WordPress |
| PHPStan    | Configured | Level 5 with baseline            |
| ESLint     | Configured | WordPress + React + Hooks        |
| Prettier   | Configured | WordPress-compatible settings    |
| Pre-commit | Configured | Husky + lint-staged              |

## Conclusion

The WP Admin Health Suite project has a well-configured code quality setup that:

- Enforces WordPress coding standards for PHP
- Provides static analysis with PHPStan at level 5
- Lints JavaScript with WordPress and React best practices
- Formats code consistently with Prettier
- Prevents quality regressions with pre-commit hooks

The configuration strikes a balance between strictness and practicality, with reasonable rule exclusions and a baseline for known issues that allows gradual improvement.
