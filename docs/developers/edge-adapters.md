# Edge Adapters — Service-Locator Fallbacks

This document describes the classes that use the service-locator pattern
(`Plugin::get_instance()->get_container()`) instead of pure constructor injection,
and explains why this pattern is necessary for each case.

## Background

The plugin uses a dependency injection (DI) container managed by service providers.
Most services should receive their dependencies via constructor injection when
registered in the container. This makes dependencies explicit and facilitates testing.

However, certain classes operate in contexts where constructor injection is not
feasible. These are called **edge adapters** — they sit at the boundary between
WordPress lifecycle events and our DI-managed application code.

## Edge Adapters List

### 1. `WPAdminHealth\Settings` (Template Facade)

**Location:** `includes/Settings.php`

**Why service-locator is needed:**
- Instantiated directly in PHP templates (e.g., `templates/admin/settings.php`)
- Templates cannot receive constructor-injected dependencies
- Provides `render_field()` for HTML generation, which is not part of `SettingsInterface`

**Usage context:**
- Admin settings page templates
- Any template needing access to settings with HTML rendering capabilities

**Alternative for application code:**
For non-template code, inject `SettingsInterface` via the container instead.

---

### 2. `WPAdminHealth\Installer` (Activation/Lifecycle)

**Location:** `includes/Installer.php`

**Why service-locator is needed:**
- Called during plugin activation before container is initialized
- Uses static methods (WordPress expects this pattern for activation hooks)
- Must function even when container is unavailable

**Usage context:**
- Plugin activation (`register_activation_hook`)
- Plugin deactivation (`register_deactivation_hook`)
- Plugin upgrade checks
- Plugin uninstallation

**Notes:**
- Falls back to global `$wpdb` when `ConnectionInterface` is unavailable
- The `set_connection()` method allows injection when container is available

---

### 3. `WPAdminHealth\BatchProcessor` (Static Utility)

**Location:** `includes/BatchProcessor.php`

**Why service-locator is needed:**
- Provides static utility methods for batch processing
- Called from various contexts (services, CLI, cron tasks)
- Static methods cannot receive constructor-injected dependencies

**Usage context:**
- Large dataset processing (100k+ posts)
- Memory-efficient batch operations
- Generator-based iteration

**Notes:**
- Falls back to global `$wpdb` when `ConnectionInterface` is unavailable
- The `set_connection()` method allows injection when container is available

---

## Classes with Fallbacks Removed

The following classes previously had service-locator fallbacks that have been
removed in favor of pure constructor injection. They are now fully container-managed:

### `WPAdminHealth\Media\Exclusions`
- **Provider:** `MediaServiceProvider`
- **Dependencies:** `SettingsInterface`

### `WPAdminHealth\REST\RestController`
- **Provider:** `RESTServiceProvider`
- **Dependencies:** `SettingsInterface`, `ConnectionInterface`
- **Note:** Throws `RuntimeException` if dependencies not injected

### `WPAdminHealth\Performance\HeartbeatController`
- **Provider:** `BootstrapServiceProvider`
- **Dependencies:** `SettingsInterface`

---

## CI Enforcement

The `forbidden-patterns` CI job (`scripts/check-forbidden-patterns.sh`) automatically
enforces these guidelines by detecting `Plugin::get_instance()` usage.

### Allowed Locations (No Documentation Needed)

The following locations may use `Plugin::get_instance()` without documentation:

| Location | Reason |
|----------|--------|
| `wp-admin-health-suite.php` | Main plugin entry point (initialization) |
| `uninstall.php` | Plugin uninstallation hook |
| `templates/` | Template edge adapters |
| `tests/` | Test harnesses |

### Forbidden Locations (Will Fail CI)

The following directories must NEVER use `Plugin::get_instance()` or direct
container access:

| Location | Reason |
|----------|--------|
| `includes/Contracts/` | Interfaces should never need service location |
| `includes/Settings/Contracts/` | Interface contracts |
| `includes/Settings/Domain/` | Pure domain logic |
| `includes/Scheduler/Contracts/` | Interface contracts |
| `includes/Exceptions/` | Exception classes |
| `includes/Services/` | Services should use constructor injection |
| `includes/Container/` | Container infrastructure |
| `includes/AI/` | AI services should use DI |

### Requires Documentation (Warning)

Other files in `includes/` may use `Plugin::get_instance()` but must include
an `EDGE ADAPTER:` comment explaining why DI cannot be used. The CI check
will warn (but not fail) for undocumented usages.

---

## Guidelines for New Code

### When to Use Constructor Injection (Preferred)

Use constructor injection when:
- The class is registered as a service in a provider
- The class is instantiated by the container
- Dependencies are known at construction time

### When Service-Locator May Be Necessary

Consider service-locator only when:
- WordPress instantiates the class directly (hooks, filters, shortcodes)
- The class uses static methods that cannot receive dependencies
- The code runs before the container is initialized (activation hooks)
- Templates instantiate the class without DI support

### Documentation Requirements

When adding a new edge adapter:
1. Add `EDGE ADAPTER:` comment at the top of the file explaining why
2. Add the class to this document
3. Provide `$wpdb` fallback for database operations
4. Consider adding a `set_*()` method for optional injection when container is available

---

## Related Documentation

- [Architecture Improvement Plan](../architecture-improvement-plan.md) — Overall architecture goals
- [Hooks Reference](hooks.md) — WordPress hooks provided by the plugin
- [REST API Reference](rest-api.md) — REST API endpoints and usage
