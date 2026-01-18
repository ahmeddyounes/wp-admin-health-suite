# ADR-003: Frontend Modernization Scope

**Status:** Accepted
**Decision Date:** 2026-01-18
**Context:** C01-04 — Decide frontend modernization scope for Phase 4

## Summary

Choose **Option A**: Incremental improvements within the current React + Webpack setup. Do not adopt `@wordpress/data` stores, `@wordpress/components`, or migrate to TypeScript in the first iteration.

## Context

The plugin has a mature frontend architecture:

| Aspect                | Current State                                         |
| --------------------- | ----------------------------------------------------- |
| Framework             | React 18.2.0                                          |
| Language              | JavaScript/JSX (no TypeScript)                        |
| Build Tool            | Webpack 5 with proper code splitting                  |
| State Management      | React hooks + local component state                   |
| API Layer             | Custom `ApiClient` with caching, retry, deduplication |
| WordPress Integration | `wp.apiFetch`, `wp.i18n`, `wp.element` via externals  |
| Testing               | Jest 29.7.0 + Testing Library (70% coverage required) |
| Components            | 6 well-tested components with PropTypes               |
| Code Quality          | ESLint + Prettier + Husky pre-commit hooks            |

The architecture improvement plan (see `docs/architecture-improvement-plan.md`, Phase 4) identified two possible directions:

| Option | Description                                                                               |
| ------ | ----------------------------------------------------------------------------------------- |
| **A**  | Incremental improvements within current React + Webpack setup                             |
| **B**  | Broader adoption: `@wordpress/data` stores, `@wordpress/components`, TypeScript migration |

## Decision

**Option A** — Incremental improvements within the current setup.

### Rationale

#### 1. Current Setup is Already Mature

The frontend is not a legacy system needing rescue—it's a well-architected, tested, production-ready codebase:

- **Modern React 18**: Functional components, hooks throughout (useState, useEffect, useRef, useCallback, useMemo)
- **Proper error boundaries**: ErrorBoundary component with comprehensive tests
- **Accessibility-first**: Dedicated `accessibility.js` utilities, ARIA attributes, keyboard navigation, `prefers-reduced-motion` respect
- **Robust API layer**: Request caching with TTL, automatic retry with exponential backoff, request deduplication
- **Test coverage**: 70% minimum enforced, all components have test files
- **Code quality gates**: ESLint, Prettier, Husky pre-commit hooks

#### 2. `@wordpress/data` Would Add Complexity Without Proportional Benefit

| Factor                    | Current Approach                                       | @wordpress/data                         |
| ------------------------- | ------------------------------------------------------ | --------------------------------------- |
| Learning curve            | Low (standard React)                                   | Higher (Redux-like patterns)            |
| Bundle size               | Minimal                                                | +40-60KB for stores/selectors/resolvers |
| State complexity          | Local, predictable                                     | Global, requires careful structuring    |
| Testing                   | Standard React Testing Library                         | Additional store mocking required       |
| This plugin's state needs | Simple (6 components, no cross-component shared state) | Over-engineered                         |

The plugin's admin UI consists of independent dashboard pages (database-health, media-audit, performance, settings). Each page loads its own data and manages its own state. There's no complex cross-page state synchronization that would justify a global store.

#### 3. `@wordpress/components` Would Reduce Design Flexibility

| Factor         | Custom Components         | @wordpress/components                     |
| -------------- | ------------------------- | ----------------------------------------- |
| Design control | Full control over styling | WordPress admin aesthetic                 |
| Bundle size    | Only what we use          | Large package with many unused components |
| Updates        | We control timing         | Must track WordPress versions             |
| Accessibility  | Already built-in          | Built-in (parity)                         |
| Customization  | Easy                      | Requires CSS overrides                    |

The existing components (MetricCard, HealthScoreCircle, ActivityTimeline, QuickActions, Recommendations) are already well-designed with proper accessibility. Replacing them with `@wordpress/components` would mean:

- Rewriting all component tests
- Losing custom visual design
- Adding significant bundle size for components we may not use

#### 4. TypeScript Migration Has High Cost-to-Benefit Ratio

| Factor                     | Assessment                       |
| -------------------------- | -------------------------------- |
| Files to migrate           | 30+ JS/JSX files                 |
| PropTypes already provide  | Runtime type checking            |
| Migration effort           | 20-40 hours minimum              |
| Benefit                    | Compile-time type safety         |
| Risk                       | Build complexity, learning curve |
| TypeScript in WP ecosystem | Not standard, mixed support      |

The codebase already uses PropTypes consistently, providing runtime type checking. A TypeScript migration would require:

- Converting all `.js`/`.jsx` files to `.ts`/`.tsx`
- Adding type definitions for WordPress globals
- Updating build configuration
- Rewriting tests for type expectations
- Training contributors on TypeScript

This effort is better spent on actual feature development.

#### 5. Alignment with Previous ADRs

- **ADR-001** chose incremental namespace additions over a `src/` migration—same principle applies to frontend
- **ADR-002** chose in-place REST refactoring over v2—same principle of minimal disruption applies

### Decision Summary

Phase 4 will focus on **incremental improvements** that enhance the existing architecture without wholesale replacement:

1. **Standardize API access** (already planned in architecture doc)
2. **Reduce globals** (already planned)
3. **Template simplification** (already planned)
4. **NO** TypeScript migration
5. **NO** `@wordpress/data` adoption
6. **NO** `@wordpress/components` replacement

## Scope: What Phase 4 Will Include

### In Scope (First Iteration)

1. **Standardize API Access**
    - Ensure all components use the existing `ApiClient` wrapper consistently
    - Remove any direct `wp.apiFetch` calls that bypass the wrapper
    - Document the API client's caching and retry behavior

2. **Reduce Global Namespace Exposure**
    - Audit `window.WPAdminHealthComponents` usage
    - Keep only what's necessary for third-party extension
    - Document the public JavaScript API

3. **Template Simplification**
    - Move complex dynamic markup from PHP templates into React
    - PHP templates should provide minimal container divs with data attributes
    - Example: `<div id="wpha-app-root" data-page="dashboard" data-nonce="..."></div>`

4. **Configuration Standardization**
    - Ensure consistent localized data structure across all pages
    - Document the `window.wpAdminHealthData` shape
    - Add explicit `rest_nonce` if not already present

5. **Dependency Audit**
    - Ensure bundles properly depend on `wp-api-fetch` when using `wp.apiFetch`
    - Verify asset dependencies in PHP enqueue calls

### Out of Scope (Non-Goals for First Iteration)

1. **TypeScript Migration**
    - PropTypes provide sufficient type safety for current codebase size
    - Can be revisited if codebase grows significantly or contributors request it

2. **`@wordpress/data` Store Adoption**
    - Current hook-based state management is adequate
    - Can be revisited if cross-page state sharing becomes necessary

3. **`@wordpress/components` Replacement**
    - Existing custom components are well-tested and accessible
    - Can adopt individual WP components for new features if beneficial

4. **Build Tool Migration (wp-scripts)**
    - Current Webpack configuration is comprehensive and working
    - No compelling reason to change

5. **SSR/Hydration**
    - The plugin renders client-side, which is appropriate for admin UIs
    - No SEO requirements for admin pages

6. **Micro-frontend Architecture**
    - Current page-based bundle splitting is sufficient
    - No need for runtime module federation

## Success Criteria

Phase 4 will be considered complete when:

1. **API Consistency**: All React components use `ApiClient` for REST calls (no direct `wp.apiFetch`)
2. **Documentation**: JavaScript public API is documented in `docs/developers/`
3. **Configuration**: Localized data includes all required fields (`rest_nonce`, `rest_root`, `i18n`)
4. **Templates**: PHP templates provide only container elements, no complex markup
5. **Dependencies**: Asset registration specifies correct WordPress script dependencies
6. **Tests Pass**: All existing frontend tests continue to pass (no regression)

## Future Considerations

If future requirements emerge that would benefit from broader modernization:

| Trigger                          | Recommended Response                                 |
| -------------------------------- | ---------------------------------------------------- |
| Need for cross-page shared state | Evaluate `@wordpress/data` for specific use case     |
| Contributors request TypeScript  | Start with API types (`.d.ts`) before full migration |
| Need WP-native UI patterns       | Adopt specific `@wordpress/components` incrementally |
| Build complexity increases       | Evaluate `@wordpress/scripts`                        |

These can be addressed in future iterations without blocking Phase 4 progress.

## Consequences

### Positive

- **Lower risk**: No wholesale rewrites, incremental improvements
- **Faster delivery**: Focus on tangible improvements, not migration overhead
- **Preserved investment**: Existing tests, components, and patterns remain valid
- **Contributor-friendly**: Standard React knowledge sufficient, no WP-specific patterns required

### Negative

- **No compile-time type safety**: Must rely on PropTypes and tests
- **No WP admin UI consistency**: Plugin has its own visual design (which may be intentional)
- **Manual state management**: Must handle caching, loading states manually (already implemented)

### Neutral

- **Decision is reversible**: Future iterations can adopt TypeScript or WP packages if justified
- **Sets precedent**: Incremental improvement over big-bang migration

## References

- `docs/architecture-improvement-plan.md` — Full architecture roadmap, Phase 4 section
- `docs/decisions/ADR-001-code-organization.md` — Related decision on code organization
- `docs/decisions/ADR-002-rest-api-evolution.md` — Related decision on REST evolution
- `assets/js/utils/api.js` — Current API client implementation
- `assets/js/components/` — Current React components
- `webpack.config.js` — Build configuration
- `package.json` — Dependencies and scripts

## Decision Record

| Date       | Author              | Action               |
| ---------- | ------------------- | -------------------- |
| 2026-01-18 | Architecture Review | Created and approved |
