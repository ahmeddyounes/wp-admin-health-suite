# WP Admin Health Suite — Architecture Improvement Plan

**Status:** Proposal  
**Generated:** 2026-01-18  
**Scope:** Whole repository (`includes/`, `templates/`, `assets/`, `scripts/`, `tests/`, `docs/`)

---

## 1) Goals (What “better architecture” means here)

1. **Correctness by default:** plugin behavior (REST routes, integrations, scheduled tasks) should work without relying on side effects or “someone else” to instantiate a class first.
2. **Clear boundaries:** separate **WordPress edge concerns** (hooks, REST, cron, options/transients, capabilities) from **domain logic** (analysis, scanning, optimization).
3. **Predictable bootstrap:** every module should have an explicit, testable bootstrap path (who registers hooks, when, and why).
4. **Composable + testable:** prefer constructor injection, small interfaces, and deterministic services; avoid global state and static service location except at the “edges”.
5. **Extensibility:** integrations and the JS extension API should be first-class and not duplicated by hard-coded heuristics.
6. **Operational safety:** long-running tasks should be resumable, observable, and safe-mode aware across REST/manual/cron execution.

---

## 2) Current Architecture Snapshot (as implemented today)

### PHP

- **Bootstrap:** `wp-admin-health-suite.php` defines constants, loads autoloader, and calls `Plugin::get_instance()->init()` on `plugins_loaded`.
- **Composition:** `includes/Plugin.php` uses a custom **PSR-11-ish** container (`includes/Container/*`) and registers multiple service providers (`includes/Providers/*`).
- **Modules:** primarily organized by directory:
    - `includes/Database`, `includes/Media`, `includes/Performance`, `includes/AI`, `includes/Scheduler`, `includes/Settings`, `includes/Integrations`
    - **Presentation:** `includes/Admin` + `templates/*`, and `includes/REST/*`
    - **Application layer:** `includes/Application/*` (partially implemented)
    - **Cross-cutting:** `includes/Contracts`, `includes/Services`, `includes/Cache`, `includes/Exceptions`

### JavaScript

- React entrypoints in `assets/js/entries/*` mount into PHP templates (`templates/admin/*`).
- Global namespace is a mix of:
    - `assets/js/admin.js` (IIFE) exporting `WPAdminHealth.API`, `WPAdminHealth.Events`, etc.
    - `assets/js/utils/api.js` (module) exporting a newer API client; entrypoints also set `WPAdminHealth.api`.
    - `assets/js/utils/extension-api.js` exporting `WPAdminHealth.extensions`.

---

## 3) Key Architectural Findings (Problems to fix)

### 3.1 Deferred providers that must register WordPress hooks

Some providers are marked deferred (good for performance), but also contain essential WP hook registration:

- `includes/Providers/RESTServiceProvider.php` registers `rest_api_init` in `boot()`, but as a deferred provider it may never boot → routes may never register.
- `includes/Providers/IntegrationServiceProvider.php` registers `plugins_loaded` in `boot()`, but as a deferred provider it may never boot → integrations may never initialize → feature accuracy regressions (e.g., media usage detection hooks).

**Risk:** Real production behavior can differ from tests because tests often instantiate controllers directly (`tests/integration/RestApiTest.php`) and don’t validate provider-driven bootstrap.

### 3.2 “Application layer” is incomplete / inconsistent

Some use-cases exist and are wired (e.g. `RunScan`, `RunCleanup`, `RunHealthCheck`), but others are stubs:

- `includes/Application/AI/GenerateRecommendations.php`
- `includes/Application/Media/ProcessDuplicates.php`
- `includes/Application/Performance/CollectMetrics.php`

**Risk:** Controllers/scheduled tasks either bypass the application layer or require ad-hoc orchestration, increasing coupling and duplicating validation/side-effects.

### 3.3 Inconsistent caching and locking abstractions

- There is a `CacheInterface` and multiple implementations in `includes/Cache/*`.
- Many services still use direct WordPress transients (`get_transient`, `set_transient`) instead of the cache abstraction (e.g., `includes/Database/Analyzer.php`, `includes/AI/Recommendations.php`).
- Locking is implemented multiple ways (REST rate limiting lock vs scheduler locks) with duplicated edge-case handling.

**Risk:** fragmented behavior across sites with/without persistent object cache; harder to test; inconsistent performance characteristics.

### 3.4 Duplicate JS “public API” surfaces

There are two REST client patterns in JS:

- `WPAdminHealth.API` (from `assets/js/admin.js`)
- `WPAdminHealth.api` (from `assets/js/utils/api.js`)

**Risk:** extensions and internal code can pick different APIs; inconsistent error handling and caching; harder to deprecate safely.

### 3.5 Case-sensitivity / PSR-4 risks

Example: `WPAdminHealth\\Integrations\\ACF` currently lives in `includes/Integrations/Acf.php`.  
On Linux/case-sensitive filesystems, this breaks PSR-4 autoloading expectations.

**Risk:** “works on macOS” but fails in CI/production builds.

### 3.6 Data model drift

The installer creates tables that appear unused in runtime code (e.g., `wpha_scheduled_tasks` is referenced mainly in tests/installer).

**Risk:** schema complexity without clear ownership; migrations become risky; harder to reason about data retention and cleanup.

---

## 4) Target Architecture (Practical, incremental)

### 4.1 Make “bootstrap” a first-class layer

Create a clear separation between:

- **Hook registration (bootstrap layer):** tiny, always-loaded, registers WP actions/filters and delegates to services.
- **Service wiring (providers):** binds interfaces and factories; may remain deferred for performance.
- **Runtime orchestration (application layer):** use-cases called by REST, cron, manual actions, and CLI.

Concrete convention:

- Every module exposes one bootstrap entrypoint with a `register(): void` method that only wires WP hooks (no heavy work).
- Providers only _bind services_; boot should only initialize hook registrars.

### 4.2 Formalize module boundaries

Adopt the following directory/namespace convention going forward (no big-bang move required):

- **Domain:** pure logic, no `add_action`, minimal WP function calls (prefer ports).
- **Application:** use-cases that orchestrate domain + persistence + side effects.
- **Infrastructure:** WordPress adapters (options/transients, cron/action scheduler, filesystem, wpdb).
- **Presentation:** REST controllers, admin page renderers, templates, React entrypoints.

The code already approximates this; the plan is to make it explicit and consistent.

### 4.3 Single source of truth for cross-cutting concerns

- **Cache:** all caching via `CacheInterface` (with clear key conventions).
- **Locks:** a shared `LockService` (backed by GET_LOCK / options / object cache) with consistent timeouts and observability.
- **Results/Errors:** prefer typed result objects (or consistent associative-array schemas) + domain exceptions.

### 4.4 Public API stability (PHP + JS)

- Define and document what is public/stable:
    - PHP: hooks/filters, REST routes, a small set of service interfaces.
    - JS: `WPAdminHealth.extensions`, `WPAdminHealth.api` (one client), and `WPAdminHealth.Events`.
- Everything else is internal and may change.

---

## 5) Phased Roadmap (What to do, in what order)

### Phase 1 — Fix bootstrap correctness (highest priority)

**Outcome:** REST routes + integrations work reliably; no hidden “must instantiate X” behavior.

1. **Introduce a non-deferred “Bootstrap Hooks” provider** (or extend `BootstrapServiceProvider`) whose only job is to:
    - Register `rest_api_init` and perform route registration by resolving controllers from the container (which triggers `RESTServiceProvider` lazily).
    - Initialize integrations at the right time (once per request), e.g. during `plugins_loaded` or just-in-time before media scans run.
2. **Keep `RESTServiceProvider` deferred**, but remove reliance on its `boot()` to attach the `rest_api_init` hook. Treat it as “bindings only”.
3. **Keep `IntegrationServiceProvider` deferred**, but ensure integrations are initialized deterministically (no reliance on its `boot()`).
4. Add an integration test that asserts:
    - routes exist after `do_action('rest_api_init')` when the plugin is initialized normally.
    - integration hooks affect media usage detection (a representative fixture).

### Phase 2 — Close the application-layer gap

**Outcome:** all “entrypoints” (REST, cron, admin actions) call use-cases, not domain services directly.

1. Implement and wire the stub use-cases:
    - `Application\\AI\\GenerateRecommendations`
    - `Application\\Media\\ProcessDuplicates`
    - `Application\\Performance\\CollectMetrics`
2. Refactor REST controllers to depend on use-cases (application layer), not on multiple domain services directly.
3. Align scheduled tasks to call the same use-cases (avoid duplicated logic between cron and REST).
4. Define consistent request/response DTO shapes per use-case; reuse in controllers and JS.

### Phase 3 — Consolidate caching + locking + runtime safety

**Outcome:** predictable behavior across environments; reduced duplication; improved testability.

1. Create `LockService` (infrastructure) and migrate:
    - REST rate limiting lock code in `includes/REST/RestController.php`
    - scheduler task locks in `includes/Scheduler/SchedulerRegistry.php`
2. Enforce cache usage through `CacheInterface`:
    - convert direct transient calls in domain services to cache abstraction
    - standardize cache keys + TTLs in dedicated “key” classes/constants
3. Add observability:
    - log lock contention and rate-limit events to ActivityLogger (or a dedicated debug channel)
4. Validate safe-mode behavior is consistently applied across REST, cron, and admin actions.

### Phase 4 — Unify and harden the JavaScript architecture

**Outcome:** one stable JS API surface; less global drift; better extension guarantees.

1. Choose a single supported REST client for JS:
    - Prefer `assets/js/utils/api.js` as the canonical client.
    - Deprecate or wrap `WPAdminHealth.API` from `assets/js/admin.js` to call the same implementation.
2. Define a stable global contract:
    - `WPAdminHealth.api` (canonical)
    - `WPAdminHealth.Events` (event bus)
    - `WPAdminHealth.extensions` (extension surface)
3. Add contract tests for extension API stability (events, widget zones, filters).
4. Optional (later): gradual TypeScript adoption for shared API contracts (REST payloads and UI state).

### Phase 5 — Reduce technical debt and improve maintainability

**Outcome:** fewer “legacy wrappers”, cleaner schemas, fewer surprises for contributors.

1. **Fix PSR-4/case-sensitivity mismatches**, starting with `includes/Integrations/Acf.php` → `includes/Integrations/ACF.php`.
2. **Audit installer tables**:
    - confirm ownership/usage for each table (`wpha_scan_history`, `wpha_query_log`, `wpha_ajax_log`, `wpha_deleted_media`, `wpha_scheduled_tasks`)
    - remove or repurpose unused schema (`wpha_scheduled_tasks`) with a migration plan.
3. Clarify legacy/deprecated paths:
    - `includes/RestApi.php` deprecation timeline and removal plan
    - template facades/service locators (e.g., `includes/Settings.php`) — keep as edge adapters but shrink their surface
4. Update docs to reflect actual architecture and versioning:
    - ensure `docs/` content matches code (avoid version drift and placeholder references)
    - add Architecture Decision Records (ADRs) for key choices (DI container, deferred providers strategy, caching/locking approach).

---

## 6) Acceptance Criteria (How to know this worked)

- REST routes are registered in a normal plugin boot (no tests “cheating” by instantiating controllers manually).
- Integrations initialize deterministically and influence runtime behavior (media usage detection) in integration tests.
- All “entrypoints” (REST + cron + admin actions) route through application-layer use-cases.
- Cache + lock behavior is consistent across object-cache/no-object-cache environments (tested via mocks).
- JS exposes one stable API client and the extension API remains backward compatible with a documented contract.
- CI includes at least one test that validates the bootstrap graph (providers + hooks) end-to-end.

---

## 7) Suggested File/Namespace Additions (minimal, targeted)

These are optional, but recommended to keep responsibilities clear:

- `includes/Bootstrap/*` (hook registrars, route registrars)
- `includes/Infrastructure/WordPress/*` (Options/Transients/Cron/Capabilities wrappers)
- `includes/Support/*` (shared primitives: Result objects, validation helpers, key registries)

---

## 8) Notes on Backward Compatibility

- Keep existing WordPress hooks/filters stable (documented in `docs/developers/hooks.md`).
- Preserve string service aliases temporarily, but plan a deprecation window and remove in a major release.
- For JS, keep `WPAdminHealth.API` as a compatibility wrapper until extensions migrate to `WPAdminHealth.api`.
