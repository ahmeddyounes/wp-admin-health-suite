# ESLint Configuration Review

Review of `.eslintrc.json` for proper linting rules, WordPress coding standards compliance, React/JSX rules, and custom rule justifications.

## Executive Summary

The ESLint configuration is well-structured and properly integrates with WordPress development standards. It extends the official `@wordpress/eslint-plugin/recommended` preset while adding React-specific configurations and sensible rule overrides.

**Overall Assessment: Good** - Minor improvements recommended for stricter prop-types validation and JSDoc type requirements.

---

## 1. Configuration Overview

### Current Setup

```json
{
	"env": {
		"browser": true,
		"es2021": true,
		"node": true
	},
	"extends": [
		"plugin:@wordpress/eslint-plugin/recommended",
		"plugin:react/recommended",
		"plugin:react-hooks/recommended",
		"prettier"
	],
	"parserOptions": {
		"ecmaFeatures": { "jsx": true },
		"ecmaVersion": "latest",
		"sourceType": "module"
	}
}
```

### Analysis

#### Strengths

- **WordPress integration**: Uses official `@wordpress/eslint-plugin/recommended` preset
- **Modern JavaScript**: ES2021 environment with latest parser options
- **React support**: Includes both React and React Hooks plugins
- **Prettier integration**: `eslint-config-prettier` disables conflicting rules

#### Extensions Hierarchy

The extends order is important - later configs override earlier ones:

1. `@wordpress/eslint-plugin/recommended` - Base WordPress rules (includes JSDoc, jsx-a11y)
2. `plugin:react/recommended` - React-specific rules
3. `plugin:react-hooks/recommended` - Hooks linting
4. `prettier` - Disables formatting rules that conflict with Prettier

**Assessment**: Order is correct. Prettier last ensures no formatting conflicts.

---

## 2. WordPress Coding Standards Compliance

### Plugin Configuration

```json
{
	"plugins": ["@wordpress", "react", "react-hooks"]
}
```

The `@wordpress/eslint-plugin` (v23.0.0) includes:

| Sub-Config    | Included Rules                    |
| ------------- | --------------------------------- |
| `jsx-a11y.js` | Accessibility rules for JSX       |
| `custom.js`   | WordPress-specific custom rules   |
| `react.js`    | React configuration for WordPress |
| `esnext.js`   | Modern JavaScript standards       |
| `i18n.js`     | Internationalization rules        |
| `jsdoc.js`    | Documentation requirements        |

### WordPress-Specific Rules Inherited

| Rule                                               | Level | Purpose                                |
| -------------------------------------------------- | ----- | -------------------------------------- |
| `@wordpress/no-unused-vars-before-return`          | error | Variables should not be assigned early |
| `@wordpress/no-base-control-with-label-without-id` | error | Accessibility for form controls        |
| `@wordpress/no-unguarded-get-range-at`             | error | Prevent selection API errors           |
| `@wordpress/no-global-active-element`              | error | Use ownerDocument.activeElement        |
| `@wordpress/no-global-get-selection`               | error | Use ownerDocument.getSelection         |
| `@wordpress/no-unsafe-wp-apis`                     | error | Prevent unstable API usage             |
| `@wordpress/no-wp-process-env`                     | error | Avoid process.env in client code       |

### Current Override in Project

```json
{
	"@wordpress/no-unused-vars-before-return": "warn"
}
```

**Analysis**: Downgraded from `error` to `warn`. This is acceptable during active development but should be elevated to `error` before production releases.

### Current Violations

```
database-health.js:346:10   @wordpress/no-unused-vars-before-return
database-health.js:347:10   @wordpress/no-unused-vars-before-return
media-audit.js:1030:10      @wordpress/no-unused-vars-before-return
```

**Recommendation**: Fix these violations rather than keeping as warnings.

---

## 3. React/JSX Rules

### Current Configuration

```json
{
	"rules": {
		"react/react-in-jsx-scope": "off",
		"react/prop-types": "warn"
	},
	"settings": {
		"react": {
			"version": "detect"
		}
	}
}
```

### Analysis

#### `react/react-in-jsx-scope: "off"`

**Justification**: Required for React 17+ with the new JSX transform. The webpack configuration uses `@babel/preset-react` with `runtime: 'automatic'`, which eliminates the need to import React in every JSX file.

**Status**: Correct configuration.

#### `react/prop-types: "warn"`

**Current State**: Set to `warn` but generates 50+ warnings in current codebase.

**Affected Files**:

| File                     | Violations |
| ------------------------ | ---------- |
| `Recommendations.jsx`    | 31         |
| `HealthScoreCircle.jsx`  | 5          |
| `ErrorBoundary.test.jsx` | 1          |
| `dashboard.test.js`      | 2          |
| `performance.js`         | 5          |

**Analysis**: The WordPress plugin default is `react/prop-types: "off"`. The project has chosen to enable it, which is good for code quality but has resulted in many warnings.

**Recommendation**: Either:

1. Fix all prop-types warnings (preferred)
2. Set to `"off"` if TypeScript migration is planned

### Inherited React Rules from WordPress Plugin

| Rule                       | Level | Notes                                  |
| -------------------------- | ----- | -------------------------------------- |
| `react/display-name`       | off   | Not required for functional components |
| `react/jsx-curly-spacing`  | error | Requires spaces in JSX expressions     |
| `react/jsx-equals-spacing` | error | No spaces around `=` in props          |
| `react/jsx-indent`         | error | Tab indentation                        |
| `react/jsx-indent-props`   | error | Tab indentation for props              |
| `react/jsx-key`            | error | Keys required for list items           |
| `react/jsx-tag-spacing`    | error | Consistent tag spacing                 |
| `react/no-children-prop`   | off   | Allow children as prop                 |
| `react/prop-types`         | off   | Overridden to `warn` in project        |
| `react/react-in-jsx-scope` | off   | React 17+ not needed                   |

### React Hooks Rules

| Rule                          | Level | Notes                    |
| ----------------------------- | ----- | ------------------------ |
| `react-hooks/rules-of-hooks`  | error | Enforce Rules of Hooks   |
| `react-hooks/exhaustive-deps` | warn  | Verify dependency arrays |

**Assessment**: Hooks rules are properly configured.

---

## 4. JSX Accessibility (jsx-a11y) Rules

### Current Configuration

```json
{
	"rules": {
		"jsx-a11y/no-redundant-roles": "warn",
		"jsx-a11y/click-events-have-key-events": "warn",
		"jsx-a11y/no-noninteractive-element-interactions": "warn",
		"jsx-a11y/no-static-element-interactions": "warn"
	}
}
```

### Analysis

All four accessibility rules are set to `warn` instead of `error`. This is a conscious decision that allows development to proceed while flagging accessibility concerns.

| Rule                                              | Default | Project | Purpose                            |
| ------------------------------------------------- | ------- | ------- | ---------------------------------- |
| `jsx-a11y/no-redundant-roles`                     | error   | warn    | Avoid redundant ARIA roles         |
| `jsx-a11y/click-events-have-key-events`           | error   | warn    | Keyboard accessibility for clicks  |
| `jsx-a11y/no-noninteractive-element-interactions` | error   | warn    | Prevent wrong semantics            |
| `jsx-a11y/no-static-element-interactions`         | error   | warn    | Add proper roles to clickable divs |

**Assessment**: Acceptable during development. Consider elevating to `error` before accessibility audit.

**Current Violations**: 0 (rules are currently satisfied)

---

## 5. JSDoc Configuration

### Current Configuration

```json
{
	"rules": {
		"jsdoc/require-param-type": "warn"
	}
}
```

### Analysis

The `jsdoc/require-param-type` rule is downgraded from `error` to `warn`. This generates significant warnings:

**Current Violations**: 77 warnings across files

| File                  | Violations |
| --------------------- | ---------- |
| `media-audit.js`      | 47         |
| `database-health.js`  | 27         |
| `admin.js`            | 4          |
| `charts.js`           | 2          |
| `Recommendations.jsx` | 16         |
| `performance.js`      | 12         |

### WordPress JSDoc Standards

The WordPress plugin enforces these JSDoc rules:

| Rule                         | Level | Purpose                       |
| ---------------------------- | ----- | ----------------------------- |
| `jsdoc/require-param`        | error | All params must be documented |
| `jsdoc/require-param-name`   | error | Param names required          |
| `jsdoc/require-param-type`   | error | Type annotations required     |
| `jsdoc/require-returns-type` | error | Return type required          |
| `jsdoc/check-types`          | error | Valid type syntax             |
| `jsdoc/check-line-alignment` | error | Aligned param descriptions    |

### Missing Type Examples

```javascript
// Current (generates warning):
/**
 * @param message The notification message
 */

// Expected:
/**
 * @param {string} message The notification message
 */
```

**Recommendation**: Add JSDoc types to all documented parameters. This improves IDE support and documentation quality.

---

## 6. Other Rules Configuration

### Console and Alert Rules

```json
{
	"rules": {
		"no-console": "off",
		"no-alert": "warn"
	}
}
```

#### `no-console: "off"`

**Justification**: Development convenience. Console statements are stripped in production by webpack's TerserPlugin (`drop_console: true`).

**Assessment**: Acceptable given build-time removal.

#### `no-alert: "warn"`

**Current Violations**: 6 warnings

| File                  | Line | Usage     |
| --------------------- | ---- | --------- |
| `Recommendations.jsx` | 178  | alert()   |
| `Recommendations.jsx` | 192  | alert()   |
| `database-health.js`  | 308  | alert()   |
| `database-health.js`  | 354  | confirm() |
| `database-health.js`  | 413  | alert()   |
| `media-audit.js`      | 959  | confirm() |
| `media-audit.js`      | 1033 | confirm() |

**Recommendation**: Replace native dialogs with WordPress admin notices or modal components for better UX.

### Unused Variables

```json
{
	"rules": {
		"no-unused-vars": "warn"
	}
}
```

**Current Violations**: 3 warnings

| File                     | Variable        |
| ------------------------ | --------------- |
| `ErrorBoundary.test.jsx` | `ThrowError`    |
| `database-health.js`     | `$progressText` |
| `performance.js`         | `grade`         |

**Recommendation**: Fix or remove unused variables.

---

## 7. Global Variables

### Current Configuration

```json
{
	"globals": {
		"jQuery": "readonly",
		"wp": "readonly",
		"wpAdminHealthData": "readonly",
		"Chart": "readonly"
	}
}
```

### Analysis

| Global              | Purpose                        | Assessment |
| ------------------- | ------------------------------ | ---------- |
| `jQuery`            | WordPress jQuery dependency    | ✅ Correct |
| `wp`                | WordPress JavaScript API       | ✅ Correct |
| `wpAdminHealthData` | Plugin localized data from PHP | ✅ Correct |
| `Chart`             | Chart.js library               | ✅ Correct |

**Assessment**: All globals are properly declared for WordPress plugin development.

---

## 8. Ignore Patterns

### Current Configuration

```json
{
	"ignorePatterns": ["assets/js/dist/", "node_modules/", "*.min.js"]
}
```

### Analysis

| Pattern           | Purpose                      |
| ----------------- | ---------------------------- |
| `assets/js/dist/` | Webpack output (built files) |
| `node_modules/`   | Dependencies                 |
| `*.min.js`        | Pre-minified vendor files    |

**Assessment**: Appropriate ignore patterns for a WordPress plugin.

---

## 9. Test File Overrides

### Current Configuration

```json
{
	"overrides": [
		{
			"files": ["**/*.test.js", "**/*.test.jsx"],
			"env": {
				"jest": true
			}
		}
	]
}
```

### Analysis

**Strengths**:

- Enables Jest globals (`describe`, `it`, `expect`, `beforeEach`, etc.)
- Applies only to test files (`.test.js`, `.test.jsx`)

**Missing Overrides** (consider adding):

```json
{
	"overrides": [
		{
			"files": ["**/*.test.js", "**/*.test.jsx"],
			"env": {
				"jest": true
			},
			"rules": {
				"@wordpress/no-global-active-element": "off",
				"@wordpress/no-global-get-selection": "off"
			}
		}
	]
}
```

These additional rules are disabled for test files in the WordPress plugin default because testing often requires direct DOM access.

---

## 10. Rule Severity Summary

### Rules Set to "warn" (Should Consider "error")

| Rule                                      | Warnings | Recommendation                       |
| ----------------------------------------- | -------- | ------------------------------------ |
| `jsdoc/require-param-type`                | 77       | Fix types, then elevate to error     |
| `react/prop-types`                        | 50+      | Fix or disable if TypeScript planned |
| `no-unused-vars`                          | 3        | Fix, then elevate to error           |
| `@wordpress/no-unused-vars-before-return` | 3        | Fix, then elevate to error           |
| `no-alert`                                | 6        | Replace with WordPress notices       |

### Rules Appropriately Set to "warn"

| Rule                                              | Rationale                 |
| ------------------------------------------------- | ------------------------- |
| `jsx-a11y/click-events-have-key-events`           | Progressive accessibility |
| `jsx-a11y/no-redundant-roles`                     | Progressive accessibility |
| `jsx-a11y/no-noninteractive-element-interactions` | Progressive accessibility |
| `jsx-a11y/no-static-element-interactions`         | Progressive accessibility |

### Rules Appropriately Set to "off"

| Rule                       | Rationale                           |
| -------------------------- | ----------------------------------- |
| `react/react-in-jsx-scope` | React 17+ automatic JSX transform   |
| `no-console`               | Build-time removal via TerserPlugin |

---

## 11. Recommendations Summary

### High Priority

1. **Fix JSDoc type annotations**: Add types to all 77 missing `@param {type}` annotations
2. **Address prop-types warnings**: Either add PropTypes definitions or decide on TypeScript migration

### Medium Priority

3. **Replace native alerts**: Use WordPress admin notices or modal components
4. **Fix unused variables**: Remove or use the 3 flagged variables
5. **Add test file overrides**: Disable WordPress-specific rules in test files

### Low Priority

6. **Consider stricter no-unused-vars**: Enable `ignoreRestSiblings` for cleaner destructuring
7. **Evaluate accessibility rule elevation**: Move jsx-a11y warnings to errors before production

---

## 12. Lint Status

### Current Output

```bash
$ npm run lint

✖ 161 problems (0 errors, 161 warnings)
```

### Warnings Breakdown

| Category                                  | Count |
| ----------------------------------------- | ----- |
| `jsdoc/require-param-type`                | 77    |
| `react/prop-types`                        | 50+   |
| `no-alert`                                | 6     |
| `no-unused-vars`                          | 3     |
| `@wordpress/no-unused-vars-before-return` | 3     |
| Other                                     | ~20   |

**Assessment**: No blocking errors. All warnings are non-critical and can be addressed incrementally.

---

## 13. Configuration Comparison

### vs WordPress Plugin Defaults

| Setting                                   | WordPress Default | Project Config | Notes                    |
| ----------------------------------------- | ----------------- | -------------- | ------------------------ |
| `react/prop-types`                        | off               | warn           | Stricter (good)          |
| `jsdoc/require-param-type`                | error             | warn           | Looser (needs work)      |
| `no-console`                              | warn              | off            | Different (build strips) |
| `no-alert`                                | error             | warn           | Looser (temporary)       |
| `@wordpress/no-unused-vars-before-return` | error             | warn           | Looser (needs work)      |

### vs Airbnb/Standard Configs

The project follows WordPress conventions rather than Airbnb or Standard JS. Key differences:

| Rule              | WordPress    | Airbnb        | Notes                |
| ----------------- | ------------ | ------------- | -------------------- |
| Indentation       | Tabs         | 2 spaces      | WordPress uses tabs  |
| JSX curly spacing | `{ spaces }` | `{no spaces}` | WordPress style      |
| PropTypes         | Off          | Error         | Project enables warn |

---

## Appendix: Full Configuration Reference

### Effective Rules (Merged)

The final effective configuration combines:

1. WordPress plugin base rules
2. React recommended rules
3. React Hooks rules
4. Prettier formatting overrides
5. Project-specific overrides

### File References

| File                                     | Purpose                      |
| ---------------------------------------- | ---------------------------- |
| `.eslintrc.json`                         | Project ESLint configuration |
| `package.json` (eslintConfig)            | None (uses file)             |
| `node_modules/@wordpress/eslint-plugin/` | WordPress ESLint rules       |

### Verification Commands

```bash
# Run linting
npm run lint

# Run linting with auto-fix
npm run lint:fix

# Check specific file
npx eslint assets/js/components/MetricCard.jsx
```
