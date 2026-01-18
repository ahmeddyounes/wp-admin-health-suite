# ADR-001: Code Organization Strategy

**Status:** Accepted
**Decision Date:** 2026-01-18
**Context:** C01-02 — Decide code organization target

## Summary

Choose **Option A**: Stay in `includes/` while adding `WPAdminHealth\Application\*` and `WPAdminHealth\Infrastructure\*` namespaces. Do not migrate to `src/`.

## Context

The plugin currently has a well-organized codebase:

- **139 PHP files** in `includes/` directory
- **25 namespaces** already established
- **PSR-4 autoloading** via `composer.json` mapping `WPAdminHealth\` → `includes/`
- **Domain-Driven Design patterns** with clear separation (Database, Media, Performance, etc.)
- **Service providers and DI container** already in place

The architecture improvement plan (see `docs/architecture-improvement-plan.md`) identified two options:

| Option | Description                                                                              |
| ------ | ---------------------------------------------------------------------------------------- |
| **A**  | Stay in `includes/`, add `Application\*` and `Infrastructure\*` namespaces incrementally |
| **B**  | Migrate to `src/`, keep `includes/` as thin wrappers for backward compatibility          |

## Decision

**Option A** — Stay in `includes/` with incremental namespace additions.

### Rationale

1. **Minimal disruption**: The existing structure is already well-organized. A `src/` migration would touch 139+ files for marginal benefit.

2. **WordPress ecosystem alignment**: The WordPress plugin ecosystem conventionally uses `includes/` for PHP code. Tools, documentation, and developer expectations align with this pattern.

3. **Autoloading already clean**: The `composer.json` PSR-4 mapping is straightforward:

    ```json
    "autoload": {
      "psr-4": {
        "WPAdminHealth\\": "includes/"
      }
    }
    ```

    Adding new namespaces requires zero configuration changes.

4. **Backward compatibility without wrappers**: Staying in `includes/` means no need for deprecated wrapper files, no dual-path maintenance, and no risk of wrapper drift.

5. **Incremental improvement**: New Application and Infrastructure namespaces can be added as refactoring proceeds, without a "big bang" migration.

6. **Cost-benefit analysis**:

    | Factor                      | Option A (includes/)    | Option B (src/ migration)   |
    | --------------------------- | ----------------------- | --------------------------- |
    | Files to modify             | ~10-20 (new files only) | 139+ (all files)            |
    | Risk of breakage            | Low                     | High                        |
    | Git history                 | Preserved               | Disrupted                   |
    | Deprecation wrappers needed | None                    | Yes                         |
    | Developer onboarding        | No change               | Must explain dual structure |

## Implementation

### New Namespace Structure

The following namespaces will be added under `includes/`:

```
includes/
├── Application/           # Use-cases / application services
│   ├── Database/         # RunCleanup, RunOptimization, etc.
│   ├── Media/            # RunScan, ProcessDuplicates, etc.
│   ├── Performance/      # RunHealthCheck, CollectMetrics, etc.
│   └── AI/               # GenerateRecommendations, etc.
├── Infrastructure/        # WP adapters / technical services
│   ├── WordPress/        # WP-specific adapters (hooks, options, etc.)
│   ├── Persistence/      # Database connections, repositories
│   └── External/         # Third-party API clients
```

### Namespace Mapping

| Layer          | Namespace                                    | Location                   | Purpose                         |
| -------------- | -------------------------------------------- | -------------------------- | ------------------------------- |
| Application    | `WPAdminHealth\Application\*`                | `includes/Application/`    | Use-cases, orchestration        |
| Infrastructure | `WPAdminHealth\Infrastructure\*`             | `includes/Infrastructure/` | WP adapters, persistence        |
| Domain         | `WPAdminHealth\Database\*`, `\Media\*`, etc. | `includes/{Module}/`       | Business logic (existing)       |
| Interface      | `WPAdminHealth\REST\*`                       | `includes/REST/`           | REST API controllers (existing) |

### Migration Path for Existing Code

1. **Do not move existing files**. They remain in their current locations.

2. **New application services** go in `includes/Application/{Module}/`.

3. **Infrastructure extractions** go in `includes/Infrastructure/`.

4. **Gradual refactoring**: As heavy REST controllers are refactored (per Phase 2 of the architecture plan), logic moves into Application services while the controller files stay in `includes/REST/`.

## Deprecation Policy

When refactoring moves logic out of an existing class:

1. **Keep the original class** in place for at least one minor version.

2. **Add deprecation notice** using `_doing_it_wrong()`:

    ```php
    if ( WP_DEBUG ) {
        _doing_it_wrong(
            __METHOD__,
            'This method is deprecated. Use WPAdminHealth\Application\Database\RunCleanup instead.',
            '2.0.0'
        );
    }
    ```

3. **Document in CHANGELOG.md** under a "Deprecated" section.

4. **Remove after grace period** (typically next major version or 6 months, whichever is later).

### Deprecation Examples

| Original                                      | Replacement                       | Deprecation Version | Removal Target |
| --------------------------------------------- | --------------------------------- | ------------------- | -------------- |
| `DatabaseController::cleanup()` orchestration | `Application\Database\RunCleanup` | 2.0.0               | 3.0.0          |
| `AbstractIntegration` self-construction       | Container-resolved integrations   | 2.0.0               | 3.0.0          |

## Consequences

### Positive

- No "big bang" migration — changes are incremental and reviewable
- Existing tests, imports, and IDE references continue to work
- Clear architectural layers without disrupting developer muscle memory
- Git blame/history preserved for all existing files

### Negative

- `includes/` grows in depth (but this is manageable with clear conventions)
- WordPress-unconventional namespaces (`Application`, `Infrastructure`) may initially confuse contributors expecting WP patterns

### Neutral

- The `src/` vs `includes/` debate is settled; no further discussion needed
- Future projects can choose `src/` from the start if preferred; this decision is specific to this codebase's maturity

## References

- `docs/architecture-improvement-plan.md` — Full architecture improvement roadmap
- `docs/architecture-improvement-plan.md#3-3` — Recommended code organization options
- Section 8 — Autoload strategy decision (Composer-first with fallback)

## Decision Record

| Date       | Author              | Action               |
| ---------- | ------------------- | -------------------- |
| 2026-01-18 | Architecture Review | Created and approved |
