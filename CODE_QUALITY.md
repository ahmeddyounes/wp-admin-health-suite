# Code Quality Tools

This document describes the code quality tools configured for the WP Admin Health Suite plugin.

## PHP Tools

### PHP_CodeSniffer (PHPCS)

WordPress Coding Standards enforcement for PHP files.

**Configuration:** `phpcs.xml`

**Commands:**

```bash
# Run code sniffer
composer phpcs
composer lint:php

# Auto-fix issues
composer phpcbf
composer lint:php:fix
```

**Standards:**

- WordPress-Extra
- WordPress-Docs
- PHPCompatibilityWP (PHP 7.4+)

### PHPStan

Static analysis tool for PHP code at level 5.

**Configuration:** `phpstan.neon`

**Commands:**

```bash
# Run static analysis
composer phpstan
composer analyse
```

**Level:** 5

## JavaScript Tools

### ESLint

JavaScript linting with WordPress and React configurations.

**Configuration:** `.eslintrc.json`

**Commands:**

```bash
# Run linter
npm run lint

# Auto-fix issues
npm run lint:fix
```

**Extends:**

- `@wordpress/eslint-plugin/recommended`
- `plugin:react/recommended`
- `plugin:react-hooks/recommended`
- `prettier`

### Prettier

Code formatter for consistent styling.

**Configuration:** `.prettierrc.json`

**Commands:**

```bash
# Format all files
npm run format

# Check formatting
npm run format:check
```

## Pre-commit Hooks

Husky is configured to run linting and formatting checks before each commit.

**Configuration:** `.husky/pre-commit`

**What runs:**

- `lint-staged` for JavaScript/CSS files
- PHPCS for staged PHP files

## Continuous Integration

GitHub Actions workflow runs on all PRs and pushes.

**Configuration:** `.github/workflows/ci.yml`

**Jobs:**

- PHP linting (PHPCS)
- PHPStan static analysis
- JavaScript linting (ESLint)
- Prettier formatting check
- PHP tests (multiple PHP versions)
- JavaScript tests (Jest)

## Testing

### PHP Tests

```bash
# Run PHPUnit tests
composer test
```

### JavaScript Tests

```bash
# Run Jest tests
npm test
```

## Git Workflow

1. Make your changes
2. Pre-commit hooks automatically run:
    - ESLint on JS files
    - Prettier on JS/CSS/JSON/MD files
    - PHPCS on PHP files
3. If checks fail, fix issues before committing
4. Push to GitHub
5. CI workflow runs all checks on PR

## Troubleshooting

### PHPCS Issues

If you have coding standard violations:

```bash
# See what's wrong
composer phpcs

# Auto-fix what can be fixed
composer phpcbf

# Fix remaining issues manually
```

### ESLint Issues

If you have linting errors:

```bash
# See what's wrong
npm run lint

# Auto-fix what can be fixed
npm run lint:fix

# Fix remaining issues manually
```

### Skipping Pre-commit Hooks

In rare cases, you may need to skip hooks:

```bash
git commit --no-verify
```

**Note:** This is NOT recommended as it bypasses quality checks.

## Configuration Files

| Tool           | Configuration File         |
| -------------- | -------------------------- |
| PHPCS          | `phpcs.xml`                |
| PHPStan        | `phpstan.neon`             |
| ESLint         | `.eslintrc.json`           |
| Prettier       | `.prettierrc.json`         |
| Husky          | `.husky/pre-commit`        |
| lint-staged    | `package.json`             |
| GitHub Actions | `.github/workflows/ci.yml` |
