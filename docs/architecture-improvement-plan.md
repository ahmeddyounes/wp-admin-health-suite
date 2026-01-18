# WP Admin Health Suite — Architecture Improvement Plan

**Generated:** 2026-01-18  
**Scope:** PHP plugin architecture + admin UI (PHP templates + React bundles) + tests/tooling in this repository  
**Audience:** Maintainers and contributors implementing medium/large refactors

## Executive Summary

The plugin already has a strong architectural foundation: a PSR-11-style DI container, service providers, domain modules (Database/Media/Performance/etc.), a REST API layer, scheduled tasks, and a React-based admin UI. The biggest opportunities are to:

1. **Remove remaining “legacy islands”** (manual `require_once`, non-autoloaded admin classes, unused/duplicated entry points).
2. **Strengthen boundaries** between _WordPress adapters_ (hooks, REST controllers, templates) and _domain logic_ (scanners, cleaners, analyzers, tasks).
3. **Centralize cross-cutting concerns** (activity logging, table existence checks, option access, exception mapping) so they’re not duplicated in controllers/tasks.
4. **Normalize autoloading and filesystem casing** to avoid Linux/case-sensitive production issues.
5. **Align tests and CI** to the current code paths and close the largest coverage gaps.

This document lays out a phased plan to improve maintainability, testability, and long-term extensibility while preserving current behavior and public hooks.

---

## 1) Current Architecture Snapshot (as-is)

### 1.1 Bootstrap flow

1. `wp-admin-health-suite.php` defines constants, checks requirements, and loads `includes/autoload.php`.
2. `Plugin::get_instance()` creates a DI container (`includes/Container`) and registers service providers (`includes/Providers/*`).
3. Providers bind domain services (Database/Media/Performance/etc.), REST controllers, scheduler registry/tasks, integrations, and UI bootstrap (admin/assets).

### 1.2 High-level module map

**Core**

- `wp-admin-health-suite.php` (bootstrap, activation/deactivation)
- `includes/Plugin.php` (container + provider lifecycle)
- `includes/autoload.php` (custom PSR-4 autoloader)

**Infrastructure / Cross-cutting**

- `includes/Container/*` (DI container + providers)
- `includes/Contracts/*` (interfaces for major services)
- `includes/Exceptions/*` (domain exceptions; currently underused)
- `includes/Services/*` (ActivityLogger, Configuration, TableChecker)
- `includes/Cache/*` (cache backends + factory)

**Domain modules**

- `includes/Database/*` (+ `includes/Database/Tasks/*`)
- `includes/Media/*` (+ `includes/Media/Tasks/*`)
- `includes/Performance/*` (+ `includes/Performance/Tasks/*`)
- `includes/AI/*`
- `includes/Integrations/*`
- `includes/Scheduler/*`
- `includes/Settings/*` (+ `includes/Settings.php` facade)
- `includes/REST/*` (REST controllers + base controller)

**UI**

- Admin menu: `includes/Admin.php` + `admin/Admin.php`
- Templates: `templates/admin/*`, `templates/network/*`
- Assets: `includes/Assets.php`, built JS/CSS in `assets/*`

**Tooling**

- PHP tooling: `composer.json`, `phpstan.neon`, `phpcs.xml`, `phpunit*.xml`
- JS tooling: `package.json`, `webpack.config.js`, `jest.config.js`
- CI: `.github/workflows/ci.yml`

---

## 2) Key Architectural Issues / Opportunities

### 2.1 Autoloading and filesystem casing risks

- Runtime uses `includes/autoload.php`, while tests/CI rely on Composer autoload (`vendor/autoload.php`).
- `composer.json` autoload contains **redundant classmap entries** with **case-mismatched paths** (e.g., `includes/rest/` vs `includes/REST/`), which is risky on Linux.
- Some code paths still use manual `require_once` with lowercased paths.

**Goal:** Make autoloading single-source-of-truth, case-safe, and consistent between runtime and CI.

#### Enforced Casing Rule

**All manual `require_once` paths must use exact-case matching the filesystem.**

Directory and file names in this repository use **PascalCase** for namespace directories (e.g., `includes/REST/`, `includes/Database/`, `includes/Media/`) and **PascalCase** for class files (e.g., `RestController.php`, `MediaController.php`).

When adding or modifying `require_once` statements:

- ✅ `require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/REST/RestController.php';`
- ❌ `require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/rest/RestController.php';`

This ensures the plugin works correctly on case-sensitive filesystems (Linux production servers) and not just on case-insensitive development environments (macOS, Windows).

**Note:** As of C01-07, all manual `require_once` paths in `includes/RestApi.php` have been corrected from `includes/rest/` to `includes/REST/`.

### 2.2 “Legacy islands” and mixed patterns

- Admin menu code lives outside autoload mapping (`admin/`), requiring manual includes.
- Some classes use older style (untyped properties, optional globals), while others are strongly typed and container-managed.
- There are legacy/duplicate entry points (e.g., `includes/RestApi.php`) that overlap with provider-driven REST registration.

**Goal:** Reduce cognitive load by converging on one architecture style: container-managed services with explicit dependencies.

### 2.3 Cross-cutting concerns are duplicated

Examples found across REST controllers/tasks:

- Activity logging duplicated via `log_activity()` helpers, instead of using `Services/ActivityLogger`.
- Direct `table_exists()` checks repeated, instead of using `Services/TableChecker`.
- Direct `get_option( 'wpha_settings' )` reads in several places, bypassing `SettingsInterface`.

**Goal:** Consolidate these patterns into shared services and enforce via conventions.

### 2.4 REST layer is heavy and partially duplicated

- “Large” controllers (`DatabaseController`, `MediaController`, `PerformanceController`) contain lots of orchestration and repeated logic.
- Specialized controllers exist under `includes/REST/{Database,Media,Performance}/`, but older/large controllers still remain.

**Goal:** Move orchestration into application services (use-cases) and keep REST controllers thin (input → call use-case → output).

### 2.5 Integrations bypass the container

- `IntegrationManager` instantiates integrations with `new $class()` and `AbstractIntegration` constructs its own `WpdbConnection` + cache.

**Goal:** Make integrations container-resolved so they can share core services (Connection, Cache, Settings, Logger) and be testable.

### 2.6 Settings provider is monolithic

`SettingsServiceProvider` combines:

- registry creation
- WP Settings API wiring
- import/export/reset handlers
- admin bar menu + dashboard widget
- inline UI behavior (custom CSS output)
- scheduling update handling

**Goal:** Split responsibilities into focused services, making it easier to evolve settings without growing a “god-provider”.

### 2.7 Frontend data and API calls aren’t fully standardized

- JS has an `assets/js/utils/api.js` client, but components also call `wp.apiFetch()` directly.
- Localized admin data does not explicitly provide a REST nonce (recommended).
- Some code exports many globals (e.g., `window.WPAdminHealthComponents`) which is convenient but increases coupling.

**Goal:** Standardize API access and runtime configuration, and keep globals minimal.

### 2.8 Tests and CI drift

There are known patterns in the repo suggesting drift:

- Some tests reference old class/method names (needs confirmation and cleanup).
- Coverage gaps remain in Performance module and some Database/Media classes (see `docs/test-coverage-gap-analysis.md`).

**Goal:** Align tests with current architecture, increase confidence for refactors.

---

## 3) Target Architecture (to-be)

### 3.1 Guiding principles

1. **One composition root**: Container + providers are the only place that wires dependencies.
2. **Explicit dependencies**: Avoid hidden fallbacks to `Plugin::get_instance()` or `new` inside domain code (except “edge adapters”).
3. **Thin adapters**: REST controllers, WP hooks, templates, and cron callbacks should delegate to services/use-cases.
4. **Stable public surface**: Preserve public hooks/filters, REST routes (or add versioned replacements), and settings option names unless intentionally migrated.
5. **Case-safe + portable**: The repo should behave the same on macOS and Linux.

### 3.2 Recommended layering model

You can implement this _without_ a full directory migration on day one.

- **Domain** (pure-ish logic): Database, Media, Performance, AI rules; minimal WP function calls.
- **Application** (use-cases): “scan database”, “cleanup transients”, “run media scan task”, “generate recommendations”, “calculate health score”.
- **Infrastructure** (WP adapters): wpdb connection, transients/object cache, Action Scheduler/WP-Cron, filesystem operations.
- **Interface** (delivery): REST controllers, admin pages, CLI (optional), templates.

### 3.3 Recommended code organization (incremental)

Option A (minimal churn): Keep `includes/` but introduce sub-namespaces:

- `WPAdminHealth\\Application\\*` for use-cases
- `WPAdminHealth\\Infrastructure\\*` for WP/IO adapters
- Keep domain modules under `WPAdminHealth\\Database`, `\\Media`, `\\Performance`, etc.

Option B (bigger change): Migrate to `src/` and keep `includes/` as thin wrappers for backward compatibility.

---

## 4) Roadmap (Phased Plan)

### Phase 0 — “Stabilize the foundation” (low risk, immediate)

**Goals**

- Make the repository portable (case-safe) and reduce duplicated entry points.
- Prepare for refactors by clarifying the true runtime paths.

**Work items**

1. **Autoload cleanup**
    - Remove redundant/incorrect Composer `classmap` entries and rely on PSR-4 mapping.
    - Ensure runtime either:
        - uses Composer autoload (`vendor/autoload.php`) when present, and falls back to `includes/autoload.php`, **or**
        - fully commits to the custom autoloader and adjusts test bootstraps accordingly.
2. **Case-sensitivity audit**
    - Eliminate path strings that mismatch repository casing (e.g., `includes/rest/...`).
    - Add a CI step that detects case-only path issues (Linux).
3. **Identify and deprecate unused legacy entry points**
    - Confirm whether `includes/RestApi.php` and `includes/Database.php` are in use. If not:
        - mark deprecated in docblocks + stop referencing them, or
        - remove after a grace period.
4. **Frontend configuration hygiene**
    - Add `rest_nonce` (or rely consistently on `wpApiSettings.nonce`) and document it.
    - Ensure bundles depend on `wp-api-fetch` when using `wp.apiFetch`.

**Deliverables**

- Green CI on Ubuntu with Composer autoload.
- A documented, single supported boot/autoload strategy.

### Phase 1 — “Make dependencies explicit everywhere”

**Goals**

- Reduce hidden globals/singletons; make services easy to test and reason about.

**Work items**

1. **Move admin menu code into autoloaded namespace**
    - Relocate `admin/` classes into `includes/Admin/*` (or `src/Admin/*`) and update references.
    - Remove runtime `require_once` from `includes/Admin.php`.
2. **Replace service-locator fallbacks**
    - Remove “if null, pull from `Plugin::get_instance()->get_container()`” patterns in core services where practical.
    - Keep this fallback only in very specific adapters where WordPress instantiates classes directly.
3. **Stop constructing infrastructure inside domain/integration code**
    - Refactor `AbstractIntegration` to accept `ConnectionInterface` and `CacheInterface` via DI only.
    - Update `IntegrationManager` to resolve integrations via container or factory.
4. **Normalize identifiers**
    - Prefer class-strings as container keys where possible.
    - If string IDs are kept, standardize naming and document them.

**Deliverables**

- Integrations and admin menu are container-managed and autoloaded.
- Clear rules: “no `new WpdbConnection()` outside providers”, etc.

### Phase 2 — “Application services + thin controllers”

**Goals**

- Make REST controllers and tasks orchestrate via dedicated use-cases.
- Consolidate cross-cutting concerns (logging, table checking, error mapping).

**Work items**

1. **Introduce application/use-case classes**
    - Examples:
        - `Application\\Database\\RunCleanup`
        - `Application\\Media\\RunScan`
        - `Application\\Performance\\RunHealthCheck`
        - `Application\\AI\\GenerateRecommendations`
2. **Refactor REST controllers**
    - Convert heavy controllers into:
        - request validation + permission checks
        - call use-case
        - format response
    - Retire duplicated controllers where specialized controllers supersede them.
3. **Standardize activity logging**
    - Replace controller-specific `log_activity()` with `ActivityLoggerInterface`.
    - Use `TableCheckerInterface` for existence checks.
4. **Adopt exceptions consistently**
    - Use `WPAdminHealthException` and domain exceptions to represent failures.
    - Provide one mapping layer in REST base controller to convert exceptions into `WP_Error`/REST responses.

**Deliverables**

- REST controllers shrink significantly and become consistent.
- Activity logging and table checks happen through services.

### Phase 3 — “Scheduler & task orchestration hardening”

**Goals**

- Ensure scheduled tasks are robust, observable, and consistent between Action Scheduler and WP-Cron.

**Work items**

1. **Single scheduling authority**
    - Ensure one “scheduling service” is responsible for:
        - schedule creation
        - rescheduling on settings changes
        - unscheduling on disable/uninstall
    - Make installer call into this service rather than directly scheduling hooks.
2. **Task execution standards**
    - Standardize result shapes and error reporting across tasks.
    - Centralize progress persistence and retention policy (options/transients/DB).
3. **Operational safety**
    - Ensure “safe mode” is applied consistently across UI, REST, and scheduled runs.

**Deliverables**

- Predictable scheduling behavior across environments.
- Consistent task results and progress reporting.

### Phase 4 — “Frontend architecture modernization”

**Goals**

- Reduce coupling between PHP templates and React.
- Make API usage consistent and secure.

**Work items**

1. **Standardize API access**
    - Prefer `@wordpress/api-fetch` with one wrapper (`apiClient`) that:
        - injects nonce
        - handles errors consistently
        - supports request caching where appropriate
    - Remove direct `wp.apiFetch` usage from components (or route it through the wrapper).
2. **Reduce globals**
    - Replace `window.WPAdminHealthComponents` with an internal mount system per page.
    - Keep a minimal, documented global surface only for third-party extensions if required.
3. **Template simplification**
    - Move complex dynamic markup fully into React; keep PHP templates as:
        - `<div id="wpha-app-root" data-page="dashboard"></div>` plus accessibility scaffolding.
4. **Type safety**
    - Optional but recommended: migrate to TypeScript incrementally (`.ts/.tsx`) starting with API types and DTOs.

**Deliverables**

- One consistent frontend runtime configuration (nonce, rest root, i18n).
- Clear, testable API layer for React.

### Phase 5 — “Testing & maintainability upgrades” (ongoing, parallel)

**Goals**

- Enable safe refactoring through better tests and guardrails.

**Work items**

1. **Fix test drift**
    - Update tests referencing old symbols to the current code paths.
2. **Close critical coverage gaps**
    - Add dedicated tests for high-priority classes identified in `docs/test-coverage-gap-analysis.md`:
        - Database: `TrashCleaner`, `OrphanedTables`
        - Media: `DuplicateDetector`, `AltTextChecker`
        - Performance: core services/controllers
3. **Contract tests for the container**
    - Add a “service provider contract” test to assert all providers boot successfully in isolation.
4. **CI checks**
    - Add case-sensitivity checks and autoload sanity checks.
    - Ensure both `composer test` and `composer test:standalone` are covered by CI if intended.

**Deliverables**

- Higher confidence for architectural changes.
- Fewer regressions in REST/task behaviors.

---

## 5) Migration & Backward Compatibility Strategy

### 5.1 Preserve public surfaces

Keep stable unless intentionally versioning:

- Action/filter hooks documented in `docs/developers/hooks.md`
- REST namespace `wpha/v1` (introduce `v2` only when breaking)
- Settings option name `wpha_settings` and key semantics

### 5.2 Deprecation policy

For any public class/function being replaced:

1. Keep a thin wrapper for 1 minor release cycle.
2. Add `_doing_it_wrong()` notices in development mode.
3. Document in `CHANGELOG.md`.

### 5.3 Data migrations

When changing storage formats:

- Add explicit “upgrade steps” to installer upgrade routine.
- Make migrations idempotent and safe for multisite.

---

## 6) Success Metrics (Definition of Done)

By the end of Phases 0–2, the architecture should meet these criteria:

- **Single autoload strategy** works in production and CI on Linux.
- **No manual includes** for autoloadable classes (admin and REST included).
- **REST controllers are thin** and delegate to application services.
- **Cross-cutting concerns are centralized** (logging/table checks/settings access).
- **Integrations are container-resolved** with explicit dependencies.
- **CI remains green** while refactors land incrementally.

---

## 7) Open Questions (decide early)

1. ~~**Autoloader strategy:** Prefer Composer autoload in production, or keep custom autoloader as primary?~~ **DECIDED** — See Section 8.
2. ~~**Directory migration:** Stay in `includes/` with new namespaces vs migrate to `src/`?~~ **DECIDED** — See Section 9.
3. ~~**REST evolution:** Keep `v1` stable and add `v2`, or refactor `v1` in-place behind compatibility?~~ **DECIDED** — See Section 10.
4. ~~**Frontend architecture:** Incremental improvements within current React setup vs full WP Components/data store adoption?~~ **DECIDED** — See Section 11.

---

## 8) Decided: Autoload Strategy

**Decision Date:** 2026-01-18
**Status:** Approved
**Strategy:** Prefer Composer autoload (`vendor/autoload.php`) with fallback to custom autoloader (`includes/autoload.php`)

### 8.1 Summary

Production runtime should attempt to load Composer's autoloader first. If unavailable (edge case), fall back to the custom PSR-4 autoloader in `includes/autoload.php`.

### 8.2 Rationale

| Criterion        | Composer Autoload                                      | Custom Autoloader                                            |
| ---------------- | ------------------------------------------------------ | ------------------------------------------------------------ |
| **Consistency**  | ✅ Same loader in tests and production                 | ❌ Different loaders may behave differently                  |
| **Case-safety**  | ✅ Optimized classmap catches mismatches at build time | ⚠️ Runtime file_exists() may pass on macOS but fail on Linux |
| **Performance**  | ✅ Optimized classmap avoids filesystem lookups        | ⚠️ Filesystem lookup per class load                          |
| **Maintenance**  | ✅ Composer ecosystem, standard tooling                | ⚠️ Custom code to maintain                                   |
| **Availability** | ⚠️ Requires vendor/ directory                          | ✅ Always available                                          |

The optimized classmap (already enabled via `config.optimize-autoloader: true` in `composer.json`) maps fully-qualified class names to exact file paths during `composer install/update`. This means:

- Case mismatches are detected at build time, not runtime
- No filesystem probing at runtime → faster class loading
- Tests and production use identical class resolution

### 8.3 Implementation

The main plugin file (`wp-admin-health-suite.php`) should be updated to:

```php
// Load autoloader: prefer Composer, fallback to custom.
$composer_autoload = WP_ADMIN_HEALTH_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
    require_once $composer_autoload;
} else {
    require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/autoload.php';
}
```

### 8.4 Acceptance Criteria

1. **Linux case-safe**: CI runs on Ubuntu (Linux, case-sensitive filesystem). All tests must pass.
2. **Tests/CI consistent**: Both `composer test` and `composer test:standalone` use the same autoloader as production.
3. **Optimized classmap generated**: Running `composer dump-autoload --optimize` must succeed without errors.
4. **No manual requires for autoloadable classes**: No `require_once` for classes under `WPAdminHealth\` namespace (except the autoloader itself).

### 8.5 Migration Steps

1. Update `wp-admin-health-suite.php` to prefer Composer autoload with fallback.
2. Remove redundant `classmap` entries from `composer.json` that duplicate PSR-4 paths (keep PSR-4 as single source of truth).
3. Ensure all directory names in `includes/` use correct PascalCase matching their namespace segments.
4. Run `composer dump-autoload --optimize` and verify no warnings.
5. Run `composer test:standalone` (CI equivalent) to verify green on Linux-like behavior.

### 8.6 Notes

- The custom autoloader in `includes/autoload.php` remains as a fallback but is not the primary path.
- WordPress plugin distribution (zip) should include `vendor/` with autoload files. Use `composer install --no-dev --optimize-autoloader` for production builds.
- For development, `composer install` (with dev dependencies) is sufficient.

---

## 9) Decided: Code Organization Strategy

**Decision Date:** 2026-01-18
**Status:** Approved
**Strategy:** Stay in `includes/` with incremental namespace additions (Option A)
**Full Documentation:** See `docs/decisions/ADR-001-code-organization.md`

### 9.1 Summary

The codebase will remain in `includes/` rather than migrating to `src/`. New Application and Infrastructure namespaces will be added incrementally as refactoring proceeds.

### 9.2 Rationale

| Factor               | Option A (Stay in includes/) | Option B (Migrate to src/)          |
| -------------------- | ---------------------------- | ----------------------------------- |
| Files to modify      | ~10-20 (new files only)      | 139+ (all existing files)           |
| Risk of breakage     | Low                          | High                                |
| Git history          | Preserved                    | Disrupted                           |
| Deprecation wrappers | None needed                  | Required for backward compatibility |
| WordPress convention | Aligned                      | Non-standard                        |

### 9.3 New Namespace Structure

```
includes/
├── Application/           # Use-cases / application services
│   ├── Database/         # RunCleanup, RunOptimization, etc.
│   ├── Media/            # RunScan, ProcessDuplicates, etc.
│   ├── Performance/      # RunHealthCheck, CollectMetrics, etc.
│   └── AI/               # GenerateRecommendations, etc.
├── Infrastructure/        # WP adapters / technical services
│   ├── WordPress/        # WP-specific adapters
│   ├── Persistence/      # Database connections, repositories
│   └── External/         # Third-party API clients
├── [Existing modules]     # Database/, Media/, Performance/, etc.
```

### 9.4 Migration Rules

1. **Do not move existing files** — they remain in current locations
2. **New application services** go in `includes/Application/{Module}/`
3. **Infrastructure extractions** go in `includes/Infrastructure/`
4. **Gradual refactoring** — as controllers are refactored per Phase 2, logic moves to Application services

### 9.5 Deprecation Policy

When refactoring moves logic out of an existing class:

1. Keep the original class for at least one minor version
2. Add `_doing_it_wrong()` deprecation notice in WP_DEBUG mode
3. Document in CHANGELOG.md
4. Remove after grace period (next major version or 6 months)

### 9.6 Acceptance Criteria

1. No existing file paths change during initial implementation
2. New `Application/` and `Infrastructure/` directories follow PSR-4 naming
3. Autoloading works without `composer.json` changes (already mapped to `includes/`)
4. All existing tests continue to pass

---

## 10) Decided: REST API Evolution Strategy

**Decision Date:** 2026-01-18
**Status:** Approved
**Strategy:** Refactor `wpha/v1` in place with no breaking changes (no v2 namespace)
**Full Documentation:** See `docs/decisions/ADR-002-rest-api-evolution.md`

### 10.1 Summary

The `wpha/v1` REST API namespace will be refactored in place without introducing a `wpha/v2` namespace. Breaking changes are avoided through careful, backward-compatible evolution. Route deprecation follows the standard deprecation policy (1 minor version grace period).

### 10.2 Rationale

| Factor                    | Option A (Refactor v1) | Option B (Introduce v2) |
| ------------------------- | ---------------------- | ----------------------- |
| Maintenance burden        | Low                    | High (parallel APIs)    |
| Consumer impact           | None (internal only)   | Migration required      |
| Testing overhead          | Unchanged              | Doubled                 |
| WordPress alignment       | Consistent             | Non-standard            |
| Architecture plan support | Direct (Phase 2)       | Indirect                |

The REST API is consumed exclusively by the plugin's own React admin UI. There are no documented external integrations. Refactoring controllers to delegate to Application services (Phase 2) does not change the API contract.

### 10.3 Compatibility Rules

1. **Route paths are stable** — once published, paths cannot change without deprecation
2. **Additive parameter evolution** — new optional parameters allowed, existing ones unchanged
3. **Additive response evolution** — new fields allowed, existing ones unchanged
4. **Error codes are stable** — documented codes continue for same error conditions
5. **HTTP status codes follow REST conventions** — no changes to status semantics

### 10.4 Route Deprecation Strategy

When a route needs to be replaced:

1. Add deprecation headers (`X-WPHA-Deprecated`, `X-WPHA-Sunset-Version`)
2. Log `_doing_it_wrong()` warnings in `WP_DEBUG` mode
3. Document in CHANGELOG.md
4. Maintain for one minor version cycle
5. Remove in next major version

### 10.5 Integration with Phase 2

This decision enables Phase 2 refactoring:

- REST controllers become thin (validation, permissions, response formatting)
- Orchestration moves to `WPAdminHealth\Application\*` services
- API contract remains unchanged for React frontend
- Existing REST API tests continue to pass

### 10.6 Acceptance Criteria

1. All existing endpoints continue to function with identical behavior
2. Response structures remain backward compatible
3. Deprecation headers implemented for any route changes
4. `docs/developers/rest-api.md` updated with compatibility policy

---

## 11) Decided: Frontend Modernization Scope

**Decision Date:** 2026-01-18
**Status:** Approved
**Strategy:** Incremental improvements within current React + Webpack setup (Option A)
**Full Documentation:** See `docs/decisions/ADR-003-frontend-modernization-scope.md`

### 11.1 Summary

Phase 4 will focus on incremental improvements to the existing React + Webpack frontend. The codebase will **not** adopt `@wordpress/data` stores, `@wordpress/components`, or TypeScript in the first iteration.

### 11.2 Rationale

| Factor           | Option A (Incremental)                   | Option B (Broader Adoption)                          |
| ---------------- | ---------------------------------------- | ---------------------------------------------------- |
| Current state    | Mature, tested, production-ready         | Would require significant rewrites                   |
| State complexity | Simple (local state sufficient)          | @wordpress/data is over-engineered for this use case |
| Type safety      | PropTypes already provide runtime checks | TypeScript migration: 30+ files, 20-40 hours         |
| Bundle size      | Minimal                                  | +40-60KB for WP packages                             |
| Risk             | Low                                      | High                                                 |

The frontend is already well-architected with React 18, proper error boundaries, comprehensive accessibility utilities, a robust API client with caching/retry, and 70% test coverage. Wholesale replacement would waste this investment.

### 11.3 First Iteration Scope

**In Scope:**

1. Standardize API access (all components use `ApiClient`)
2. Reduce global namespace exposure (`window.WPAdminHealthComponents` audit)
3. Template simplification (PHP → minimal container divs)
4. Configuration standardization (consistent localized data)
5. Dependency audit (asset registration)

**Non-Goals (Out of Scope):**

1. TypeScript migration
2. `@wordpress/data` store adoption
3. `@wordpress/components` replacement
4. Build tool migration to `@wordpress/scripts`
5. SSR/hydration
6. Micro-frontend architecture

### 11.4 Success Criteria

1. All React components use `ApiClient` for REST calls
2. JavaScript public API documented in `docs/developers/`
3. Localized data includes `rest_nonce`, `rest_root`, `i18n`
4. PHP templates provide only container elements
5. Asset registration specifies correct dependencies
6. All existing frontend tests continue to pass

### 11.5 Future Triggers

| Trigger                          | Recommended Response                                 |
| -------------------------------- | ---------------------------------------------------- |
| Need for cross-page shared state | Evaluate `@wordpress/data` for specific use case     |
| Contributors request TypeScript  | Start with API types (`.d.ts`) before full migration |
| Need WP-native UI patterns       | Adopt specific `@wordpress/components` incrementally |
