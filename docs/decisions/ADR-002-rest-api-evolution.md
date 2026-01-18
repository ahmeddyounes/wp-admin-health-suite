# ADR-002: REST API Evolution Strategy

**Status:** Accepted
**Decision Date:** 2026-01-18
**Context:** C01-03 — Decide REST evolution approach

## Summary

Refactor `wpha/v1` endpoints in place without introducing a `wpha/v2` namespace. Breaking changes are avoided through careful, backward-compatible refactoring. Route deprecation follows the same policy as code deprecation (1 minor version grace period).

## Context

The plugin exposes a REST API under the `wpha/v1` namespace with approximately 40 endpoints across 5 main controller domains (Dashboard, Database, Media, Performance, Activity). The architecture improvement plan (see `docs/architecture-improvement-plan.md`) Phase 2 calls for refactoring REST controllers to be thin, delegating orchestration to Application services.

The question is whether to:

| Option | Description                                                               |
| ------ | ------------------------------------------------------------------------- |
| **A**  | Refactor `v1` in place with backward-compatible changes only              |
| **B**  | Introduce `wpha/v2` for new patterns while keeping `v1` frozen/deprecated |

## Decision

**Option A** — Refactor `wpha/v1` in place with no breaking changes.

### Rationale

1. **Internal consumer only**: The REST API is consumed exclusively by the plugin's own React admin UI. There are no documented external integrations or third-party consumers. This means:
    - We control both sides of the contract
    - Frontend and backend can be updated atomically in the same release
    - No need to maintain parallel APIs for external compatibility

2. **Refactoring is internal, not breaking**: Moving orchestration from controllers to Application services does not change:
    - Route paths (`/wpha/v1/database/clean`)
    - HTTP methods (GET, POST, DELETE)
    - Request parameter schemas
    - Response data structures
    - Error codes and formats

3. **v2 would add maintenance burden**: Introducing a second namespace means:
    - Duplicating route registration and documentation
    - Testing both versions in CI
    - Deciding when v1 can be removed (which may never happen due to inertia)
    - Confusing contributors about which version to extend

4. **Precedent from ADR-001**: The code organization decision chose incremental change over big-bang migration. REST evolution should follow the same principle.

5. **WordPress ecosystem alignment**: WordPress core REST API (`wp/v2`) maintains backward compatibility within major versions. Breaking changes introduce new routes rather than new namespaces. This plugin follows that pattern.

## Implementation

### Compatibility Rules

1. **Route paths are stable**: Once an endpoint is published in a minor release, its path cannot change without deprecation.

2. **Request parameters use additive evolution**:
    - New optional parameters can be added
    - Existing parameters cannot be removed or have their meaning changed
    - Parameter types cannot be narrowed (e.g., changing `string` to `enum`)
    - Default values cannot change in ways that alter behavior for existing callers

3. **Response structure uses additive evolution**:
    - New fields can be added to response objects
    - Existing fields cannot be removed or renamed
    - Field types cannot change (e.g., `int` to `string`)
    - Nested object structures cannot be flattened or restructured

4. **Error codes are stable**: Once an error code is documented, it must continue to be returned for the same error condition.

5. **HTTP status codes follow REST conventions**:
    - 200 for successful GET/POST operations
    - 201 for resource creation
    - 400 for client errors (invalid input)
    - 401 for authentication required
    - 403 for authorization failures
    - 404 for not found
    - 429 for rate limiting
    - 500 for server errors

### Route Deprecation Strategy

When a route needs to be replaced (not just refactored internally):

1. **Announce deprecation**: Add to CHANGELOG.md and release notes

2. **Add deprecation header**: Include in response headers for deprecated routes:

    ```
    X-WPHA-Deprecated: true
    X-WPHA-Deprecated-Message: Use /wpha/v1/new-route instead
    X-WPHA-Sunset-Version: 2.0.0
    ```

3. **Log deprecation warnings**: When `WP_DEBUG` is true, log usage of deprecated routes:

    ```php
    if ( WP_DEBUG ) {
        _doing_it_wrong(
            'wpha/v1/old-route',
            'This endpoint is deprecated. Use /wpha/v1/new-route instead.',
            '1.5.0'
        );
    }
    ```

4. **Maintain for one minor version cycle**: Deprecated routes continue to function for at least one minor release after deprecation announcement.

5. **Remove in next major version**: Deprecated routes can be removed in the next major version (e.g., 2.0.0).

### Alias Routes (Already Implemented)

The codebase already uses alias routes for backward compatibility. This pattern is endorsed:

```php
// Legacy alias for renamed endpoint
register_rest_route(
    $this->namespace,
    '/' . $this->rest_base . '/score',  // Old path
    array(
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => array( $this, 'get_stats' ),  // Points to new handler
        'permission_callback' => array( $this, 'check_permissions' ),
    )
);
```

### Versioning Future Breaking Changes

If a future requirement necessitates breaking changes that cannot be done via additive evolution:

1. **First, attempt backward-compatible alternatives**:
    - Add new route alongside old one
    - Use feature flags/parameters to opt into new behavior
    - Version at the field level (e.g., `data_v2` alongside `data`)

2. **If truly breaking, introduce `wpha/v2`**:
    - Only for the affected endpoints, not the entire API
    - Document the migration path clearly
    - Provide at least 6 months overlap before removing v1 endpoints

3. **Coordinate with major plugin version**:
    - Breaking API changes should align with major plugin versions (2.0.0, 3.0.0)
    - Never introduce breaking changes in minor/patch releases

## Examples

### Acceptable Changes (No Version Bump)

| Change                                          | Rationale                                 |
| ----------------------------------------------- | ----------------------------------------- |
| Add `include_metadata` optional parameter       | Additive, doesn't affect existing callers |
| Add `optimization_score` field to response      | Additive, existing consumers ignore it    |
| Extract controller logic to Application service | Internal refactoring, contract unchanged  |
| Improve error message text                      | Messages are for humans, not machines     |
| Add rate limit headers to responses             | Additive, informational only              |

### Changes Requiring Deprecation

| Change                                            | Approach                                                  |
| ------------------------------------------------- | --------------------------------------------------------- |
| Rename `/media/scan` to `/media/start-scan`       | Add new route, deprecate old, keep both working           |
| Split `/database/clean` into type-specific routes | Add new routes, keep umbrella route working               |
| Change `bytes_freed` from int to object           | Add `bytes_freed_v2` object, keep int for backward compat |

### Changes Requiring v2 (Future, Unlikely)

| Change                                    | Why v2                           |
| ----------------------------------------- | -------------------------------- |
| Complete restructure of response envelope | Cannot be done additively        |
| GraphQL-style API                         | Fundamentally different paradigm |
| Change authentication mechanism           | Would break all existing clients |

## Consequences

### Positive

- No parallel API maintenance burden
- Clear rules for what changes are allowed
- Aligns with WordPress REST API conventions
- Frontend/backend evolve together atomically
- Contributors don't need to decide "which version"

### Negative

- Truly breaking changes require more creative solutions
- Long-term accumulation of deprecated aliases (manageable with major version cleanup)
- External integrators (if any emerge) must track deprecation notices

### Neutral

- This decision can be revisited if external API consumers emerge
- If v2 becomes necessary in the future, it can be scoped to affected endpoints only

## Integration with Architecture Improvement Plan

This decision directly supports Phase 2 ("Application services + thin controllers"):

1. **REST controllers become thin**: Route registration, input validation, permission checks, and response formatting stay in controllers.

2. **Orchestration moves to Application services**: Business logic, cross-service coordination, and activity logging move to `WPAdminHealth\Application\*` classes.

3. **No API contract changes**: The refactoring is transparent to API consumers (including the React frontend).

4. **Test contracts remain valid**: Existing REST API tests continue to pass without modification.

## Documentation Updates

The following documentation should be updated to reflect this decision:

1. **`docs/developers/rest-api.md`**: Add "API Compatibility" section explaining:
    - The v1 stability promise
    - How to identify deprecated endpoints (headers)
    - Version support lifecycle

2. **`docs/architecture-improvement-plan.md`**: Mark Question 3 (REST evolution) as decided, reference this ADR.

3. **`CHANGELOG.md`**: Document any endpoint deprecations in future releases.

## References

- `docs/architecture-improvement-plan.md` — Overall architecture roadmap
- `docs/decisions/ADR-001-code-organization.md` — Related decision on code organization
- `docs/developers/rest-api.md` — Current REST API documentation
- `includes/REST/RestController.php` — Base controller implementation

## Decision Record

| Date       | Author              | Action               |
| ---------- | ------------------- | -------------------- |
| 2026-01-18 | Architecture Review | Created and approved |
