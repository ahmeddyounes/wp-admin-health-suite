# Container Conventions

WP Admin Health Suite uses a dependency injection (DI) container to manage service instantiation and resolve dependencies. This document outlines the conventions for registering services.

## Table of Contents

- [Identifier Conventions](#identifier-conventions)
- [Binding Services](#binding-services)
- [The $provides Array](#the-provides-array)
- [Resolving Services](#resolving-services)
- [Service Providers](#service-providers)
- [Best Practices](#best-practices)

---

## Identifier Conventions

When registering services with the container, use **class-string identifiers as the primary binding key**. String aliases should only be added for backward compatibility.

### Primary Identifiers

Use the fully-qualified class name as the primary identifier:

```php
// For interfaces (preferred when available):
$container->singleton( SettingsInterface::class, function () {
    return new Settings();
});

// For concrete classes (when no interface exists):
$container->bind( OrphanedTables::class, function ( $container ) {
    return new OrphanedTables(
        $container->get( ConnectionInterface::class )
    );
});
```

### String Aliases

Add string aliases only for backward compatibility with existing code:

```php
// Primary binding uses class-string.
$container->bind( DatabaseController::class, function ( $container ) {
    return new DatabaseController( /* dependencies */ );
});

// String alias for backward compatibility.
$container->alias( 'rest.database_controller', DatabaseController::class );
```

### Why Class-String Identifiers?

1. **IDE support**: Enables autocompletion, refactoring, and "go to definition"
2. **Type safety**: PHPStan/Psalm can verify types at static analysis time
3. **Refactoring**: Class renames propagate automatically via IDE tools
4. **Clarity**: The identifier self-documents what service is being resolved

---

## Binding Services

### Singleton vs Bind

Use `singleton()` for services that should have only one instance:

```php
$container->singleton( ConnectionInterface::class, function () {
    return new WpdbConnection();
});
```

Use `bind()` for services that should return a new instance each time:

```php
$container->bind( Analyzer::class, function ( $container ) {
    return new Analyzer(
        $container->get( ConnectionInterface::class )
    );
});
```

### Instance Registration

For pre-existing instances or class references:

```php
// Register an already-instantiated object.
$container->instance( SomeClass::class, $existingInstance );

// Register a class reference (for static method classes).
$container->instance( Installer::class, Installer::class );
```

### Alias Registration

Create aliases that point to existing bindings:

```php
// The alias 'settings.registry' now resolves to SettingsRegistryInterface::class.
$container->alias( 'settings.registry', SettingsRegistryInterface::class );
```

---

## The $provides Array

Service providers declare which services they provide in the `$provides` array. This is used for deferred loading.

### Convention

List identifiers in this order:

1. Interface class-strings (primary)
2. Concrete class-strings (for classes without interfaces)
3. String aliases (backward compatibility)

```php
protected array $provides = array(
    // Interface identifiers (primary).
    ConnectionInterface::class,
    AnalyzerInterface::class,
    OptimizerInterface::class,

    // Class-string identifiers for classes without interfaces.
    OrphanedTables::class,

    // String aliases (backward compatibility).
    'db.connection',
    'db.analyzer',
    'db.optimizer',
    'db.orphaned_tables',
);
```

### Comments

Add section comments to clarify the grouping:

```php
protected array $provides = array(
    // Class-string identifiers (primary).
    DatabaseController::class,
    MediaController::class,
    PerformanceController::class,

    // String aliases (backward compatibility).
    'rest.database_controller',
    'rest.media_controller',
    'rest.performance_controller',
);
```

---

## Resolving Services

### Preferred Method

Always resolve services using class-string identifiers:

```php
// Good: Uses interface for type safety.
$connection = $container->get( ConnectionInterface::class );

// Good: Uses concrete class when no interface exists.
$orphanedTables = $container->get( OrphanedTables::class );
```

### Deprecated Method

Avoid using string identifiers in new code:

```php
// Deprecated: Only use for backward compatibility.
$connection = $container->get( 'db.connection' );
```

### Type Hints

When resolving services, use proper type hints:

```php
/** @var SettingsInterface $settings */
$settings = $container->get( SettingsInterface::class );
```

---

## Service Providers

### Structure

A typical service provider follows this pattern:

```php
<?php
namespace WPAdminHealth\Providers;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\SomeInterface;
use WPAdminHealth\SomeClass;

class MyServiceProvider extends ServiceProvider {

    protected array $provides = array(
        // Class-string identifiers (primary).
        SomeInterface::class,
        SomeClass::class,

        // String aliases (backward compatibility).
        'some.service',
        'some.class',
    );

    public function register(): void {
        // Bind to interface (primary).
        $this->container->singleton(
            SomeInterface::class,
            function ( $container ) {
                return new SomeClass(
                    $container->get( DependencyInterface::class )
                );
            }
        );

        // Add backward-compatible alias.
        $this->container->alias( 'some.service', SomeInterface::class );

        // Bind concrete class.
        $this->container->bind(
            SomeClass::class,
            function () {
                return new SomeClass();
            }
        );
        $this->container->alias( 'some.class', SomeClass::class );
    }

    public function boot(): void {
        // Bootstrap logic here.
    }
}
```

### Deferred Providers

For providers that should only load when their services are needed:

```php
class AIServiceProvider extends ServiceProvider {

    protected bool $deferred = true;

    protected array $provides = array(
        OneClickFix::class,
        Recommendations::class,
        'ai.one_click_fix',
        'ai.recommendations',
    );

    // ...
}
```

---

## Best Practices

### 1. Always Use Interfaces When Available

Bind to interfaces rather than concrete classes to enable substitution:

```php
// Good: Binds to interface.
$container->singleton( CacheInterface::class, function () {
    return new ObjectCache();
});

// Then resolve via interface.
$cache = $container->get( CacheInterface::class );
```

### 2. Document Dependencies

Use PHPDoc to document factory dependencies:

```php
/**
 * Register the Recommendations service.
 *
 * Dependencies:
 * - AnalyzerInterface
 * - RevisionsManagerInterface
 * - TransientsCleanerInterface
 */
$this->container->bind(
    Recommendations::class,
    function ( $container ) {
        return new Recommendations(
            $container->get( AnalyzerInterface::class ),
            $container->get( RevisionsManagerInterface::class ),
            $container->get( TransientsCleanerInterface::class )
        );
    }
);
```

### 3. Keep String Aliases for Backward Compatibility

When migrating from string IDs to class-string IDs, always keep the old string aliases:

```php
// Migration: was 'rest.database_controller', now DatabaseController::class.
$this->container->bind(
    DatabaseController::class,
    function ( $container ) {
        return new DatabaseController( /* ... */ );
    }
);

// Keep alias for existing code.
$this->container->alias( 'rest.database_controller', DatabaseController::class );
```

### 4. Order Matters in $provides

List class-string identifiers before string aliases to make the primary identifiers obvious:

```php
protected array $provides = array(
    // Primary identifiers first.
    MyService::class,
    OtherService::class,

    // Then aliases.
    'my.service',
    'other.service',
);
```

### 5. Use Consistent Naming for String Aliases

String aliases should follow the pattern `{domain}.{service_name}`:

- `db.connection`
- `db.analyzer`
- `rest.database_controller`
- `settings.core`
- `ai.recommendations`

---

## Migration Guide

When updating existing code to use class-string identifiers:

1. **Update `$provides` array**: Add class-string identifiers at the top
2. **Update `register()` method**: Change `bind()` calls to use class-string
3. **Add aliases**: Use `alias()` to maintain backward compatibility
4. **Update internal references**: Change `$container->get('string.id')` to `$container->get(Class::class)`

### Example Migration

Before:

```php
protected array $provides = array(
    'settings.core',
    'settings.database',
);

public function register(): void {
    $this->container->bind( 'settings.core', function () {
        return new CoreSettings();
    });

    $this->container->bind( 'settings.database', function () {
        return new DatabaseSettings();
    });
}
```

After:

```php
protected array $provides = array(
    // Class-string identifiers (primary).
    CoreSettings::class,
    DatabaseSettings::class,

    // String aliases (backward compatibility).
    'settings.core',
    'settings.database',
);

public function register(): void {
    $this->container->bind( CoreSettings::class, function () {
        return new CoreSettings();
    });
    $this->container->alias( 'settings.core', CoreSettings::class );

    $this->container->bind( DatabaseSettings::class, function () {
        return new DatabaseSettings();
    });
    $this->container->alias( 'settings.database', DatabaseSettings::class );
}
```

---

## Support

For questions about the container system:

- Review the `includes/Container/` directory for implementation details
- Check existing service providers in `includes/Providers/` for examples
- Refer to PHP-DI or League Container documentation for general DI concepts

---

**Last Updated:** 2026-01-18
**Plugin Version Compatibility:** 1.3.0+
