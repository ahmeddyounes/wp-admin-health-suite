# Application Layer

The Application layer contains use-case classes that orchestrate domain operations. These classes serve as the bridge between delivery mechanisms (REST controllers, CLI commands, scheduled tasks) and domain services.

## Purpose

Use-case classes provide:

1. **Separation of concerns** - REST controllers handle HTTP concerns, use-cases handle business logic orchestration
2. **Reusability** - The same use-case can be invoked from REST, CLI, or scheduled tasks
3. **Testability** - Use-cases can be unit tested without HTTP/WordPress dependencies
4. **Consistency** - Cross-cutting concerns (logging, validation) are applied uniformly

## Directory Structure

```
includes/Application/
├── Database/           # Database-related use-cases
│   ├── RunCleanup.php
│   └── RunOptimization.php
├── Media/              # Media library use-cases
│   ├── RunScan.php
│   └── ProcessDuplicates.php
├── Performance/        # Performance analysis use-cases
│   ├── RunHealthCheck.php
│   └── CollectMetrics.php
├── Dashboard/          # Dashboard-related use-cases
│   └── GetHealthScore.php
└── AI/                 # AI-powered feature use-cases
    └── GenerateRecommendations.php
```

## Usage Pattern

### From REST Controllers

```php
use WPAdminHealth\Application\Database\RunCleanup;

class CleanupController extends RestController {
    private RunCleanup $run_cleanup;

    public function __construct( RunCleanup $run_cleanup ) {
        $this->run_cleanup = $run_cleanup;
    }

    public function handle_cleanup( WP_REST_Request $request ): WP_REST_Response {
        // Controller handles: validation, permissions, response formatting
        $options = $request->get_params();

        // Use-case handles: orchestration, business logic, logging
        $result = $this->run_cleanup->execute( $options );

        return new WP_REST_Response( $result, $result['success'] ? 200 : 500 );
    }
}
```

### From Scheduled Tasks

```php
use WPAdminHealth\Application\Database\RunCleanup;

class ScheduledCleanupTask {
    private RunCleanup $run_cleanup;

    public function __construct( RunCleanup $run_cleanup ) {
        $this->run_cleanup = $run_cleanup;
    }

    public function execute(): void {
        $result = $this->run_cleanup->execute( [
            'safe_mode' => true,
            'batch_size' => 100,
        ] );

        // Log result for monitoring
    }
}
```

## Available Use-Cases

### Database Module

| Class             | Description                                                                     |
| ----------------- | ------------------------------------------------------------------------------- |
| `RunCleanup`      | Orchestrates cleanup of revisions, drafts, trash, transients, and orphaned data |
| `RunOptimization` | Orchestrates table optimization and defragmentation                             |

### Media Module

| Class               | Description                                        |
| ------------------- | -------------------------------------------------- |
| `RunScan`           | Orchestrates media library scanning for issues     |
| `ProcessDuplicates` | Orchestrates duplicate media detection and cleanup |

### Performance Module

| Class            | Description                                 |
| ---------------- | ------------------------------------------- |
| `RunHealthCheck` | Orchestrates full performance health check  |
| `CollectMetrics` | Orchestrates performance metrics collection |

### Dashboard Module

| Class            | Description                                                                    |
| ---------------- | ------------------------------------------------------------------------------ |
| `GetHealthScore` | Retrieves the current overall health score and related factors/recommendations |

### AI Module

| Class                     | Description                                       |
| ------------------------- | ------------------------------------------------- |
| `GenerateRecommendations` | Orchestrates AI-powered recommendation generation |

## Design Guidelines

When implementing use-case classes:

1. **Single responsibility** - Each use-case should do one thing well
2. **Accept dependencies via constructor** - Use dependency injection for all services
3. **Return structured results** - Always return arrays with `success` key and relevant data
4. **Handle errors gracefully** - Catch exceptions and return error information
5. **Support options** - Accept configuration via the `$options` parameter
6. **Log significant actions** - Use `ActivityLoggerInterface` for audit trails

## Future Development

As REST controllers are refactored (per Phase 2 of the architecture plan), orchestration logic will be migrated from controllers into these use-case classes. The shell classes provide the foundation for this migration.

See `docs/architecture-improvement-plan.md` Section 9 for the full code organization strategy.
