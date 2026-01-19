# Hooks & Filters Reference

WP Admin Health Suite provides a comprehensive set of hooks and filters to extend and customize plugin functionality. All hooks follow the `wpha_` prefix naming convention.

## Table of Contents

- [JavaScript Extension API](#javascript-extension-api)
- [Scan Hooks](#scan-hooks)
- [Cleanup Hooks](#cleanup-hooks)
- [UI Hooks](#ui-hooks)
- [Settings Hooks](#settings-hooks)
- [Integration Hooks](#integration-hooks)
- [Core & Lifecycle Hooks](#core--lifecycle-hooks)
- [Observability Hooks](#observability-hooks)
- [Diagnosing Lock Contention & Rate Limiting](#diagnosing-lock-contention--rate-limiting)
- [Common Customization Examples](#common-customization-examples)

---

## JavaScript Extension API

The JavaScript Extension API provides a stable, documented interface for third-party extensions to hook into the plugin's frontend functionality. This API is available via `window.WPAdminHealth.extensions`.

> **Note:** As of version 1.9.0, the `window.WPAdminHealthComponents` global has been removed. Internal React components are no longer exposed globally. Extensions should use the Extension API described below.

### API Reference

#### `WPAdminHealth.extensions.version`

The current version of the Extension API.

**Type:** `string`

```javascript
console.log(WPAdminHealth.extensions.version); // "1.0.0"
```

---

#### `WPAdminHealth.extensions.hooks`

Object containing available hook name constants.

**Available Hooks:**

| Constant                     | Value                        | Description                  |
| ---------------------------- | ---------------------------- | ---------------------------- |
| `READY`                      | `'ready'`                    | Plugin is ready              |
| `PAGE_INIT`                  | `'pageInit'`                 | Page-specific initialization |
| `DASHBOARD_INIT`             | `'dashboardInit'`            | Dashboard page loaded        |
| `DASHBOARD_REFRESH`          | `'dashboardRefresh'`         | Dashboard data refreshed     |
| `DATABASE_CLEANUP_START`     | `'databaseCleanupStart'`     | Database cleanup started     |
| `DATABASE_CLEANUP_COMPLETE`  | `'databaseCleanupComplete'`  | Database cleanup finished    |
| `MEDIA_SCAN_START`           | `'mediaScanStart'`           | Media scan started           |
| `MEDIA_SCAN_COMPLETE`        | `'mediaScanComplete'`        | Media scan finished          |
| `PERFORMANCE_CHECK_START`    | `'performanceCheckStart'`    | Performance check started    |
| `PERFORMANCE_CHECK_COMPLETE` | `'performanceCheckComplete'` | Performance check finished   |

---

#### `WPAdminHealth.extensions.registerWidget(zone, config)`

Register a custom widget to be rendered in a specific zone.

**Parameters:**

- `zone` (string) - Zone identifier where the widget will be rendered
- `config` (object) - Widget configuration:
    - `id` (string, required) - Unique widget identifier
    - `render` (function, required) - Render function that receives the container element
    - `priority` (number, optional) - Render priority (lower = earlier, default: 10)

**Available Zones:**

- `dashboard-top` - Top of the dashboard page
- `dashboard-bottom` - Bottom of the dashboard page

**Example:**

```javascript
// Register a simple DOM widget
WPAdminHealth.extensions.registerWidget('dashboard-bottom', {
	id: 'my-custom-widget',
	render: (container) => {
		container.innerHTML = `
            <div class="wpha-card my-widget">
                <h3>Custom Widget</h3>
                <p>This is my custom extension widget.</p>
            </div>
        `;
	},
	priority: 20,
});

// Register a widget that returns a React element (if you're using React)
WPAdminHealth.extensions.registerWidget('dashboard-top', {
	id: 'react-widget',
	render: () => React.createElement('div', null, 'React Widget'),
	priority: 5,
});
```

---

#### `WPAdminHealth.extensions.unregisterWidget(zone, id)`

Remove a previously registered widget.

**Parameters:**

- `zone` (string) - Zone identifier
- `id` (string) - Widget identifier to remove

**Example:**

```javascript
WPAdminHealth.extensions.unregisterWidget(
	'dashboard-bottom',
	'my-custom-widget'
);
```

---

#### `WPAdminHealth.extensions.addFilter(filterName, callback, priority)`

Add a filter callback to modify data before it's used.

**Parameters:**

- `filterName` (string) - Filter name
- `callback` (function) - Filter callback that receives the value and returns the modified value
- `priority` (number, optional) - Priority (lower = earlier, default: 10)

**Returns:** `function` - Unsubscribe function

**Example:**

```javascript
// Modify dashboard data before display
const removeFilter = WPAdminHealth.extensions.addFilter(
	'dashboardData',
	(data) => {
		data.customMetric = calculateCustomMetric();
		return data;
	}
);

// Later, to remove the filter:
removeFilter();
```

---

#### `WPAdminHealth.extensions.on(hookName, callback)`

Subscribe to a hook/event.

**Parameters:**

- `hookName` (string) - Hook name (use `WPAdminHealth.extensions.hooks` constants)
- `callback` (function) - Callback function that receives event data

**Returns:** `function` - Unsubscribe function

**Example:**

```javascript
// React to dashboard initialization
const unsubscribe = WPAdminHealth.extensions.on('dashboardInit', (data) => {
	console.log('Dashboard is ready!', data);
	initMyExtension();
});

// Using hook constants
WPAdminHealth.extensions.on(
	WPAdminHealth.extensions.hooks.DATABASE_CLEANUP_COMPLETE,
	(result) => {
		console.log('Cleanup completed:', result);
	}
);
```

---

#### `WPAdminHealth.extensions.once(hookName, callback)`

Subscribe to a hook/event for one-time execution only.

**Parameters:**

- `hookName` (string) - Hook name
- `callback` (function) - Callback function

**Example:**

```javascript
// Run only once when the dashboard initializes
WPAdminHealth.extensions.once('dashboardInit', () => {
	showWelcomeMessage();
});
```

---

### Complete Extension Example

Here's a complete example of creating an extension that adds a custom widget and responds to events:

```javascript
(function () {
	'use strict';

	// Wait for the plugin to be ready
	WPAdminHealth.extensions.on('ready', function () {
		// Register our custom widget
		WPAdminHealth.extensions.registerWidget('dashboard-bottom', {
			id: 'my-analytics-widget',
			render: function (container) {
				container.innerHTML = `
                    <div class="wpha-card">
                        <div class="wpha-card-header">
                            <h3>My Analytics</h3>
                        </div>
                        <div class="wpha-card-body" id="my-analytics-content">
                            Loading analytics data...
                        </div>
                    </div>
                `;

				// Fetch and display data
				loadAnalyticsData();
			},
			priority: 50,
		});

		// Listen for cleanup events
		WPAdminHealth.extensions.on(
			'databaseCleanupComplete',
			function (result) {
				updateAnalyticsAfterCleanup(result);
			}
		);
	});

	function loadAnalyticsData() {
		// Use the API client if available
		if (WPAdminHealth.api) {
			WPAdminHealth.api
				.get('my-extension/analytics')
				.then(function (data) {
					document.getElementById('my-analytics-content').innerHTML =
						'<p>Total optimizations: ' + data.total + '</p>';
				})
				.catch(function (error) {
					document.getElementById('my-analytics-content').innerHTML =
						'<p>Failed to load analytics.</p>';
				});
		}
	}

	function updateAnalyticsAfterCleanup(result) {
		var content = document.getElementById('my-analytics-content');
		if (content) {
			content.innerHTML +=
				'<p>Last cleanup freed: ' + result.bytes_freed + ' bytes</p>';
		}
	}
})();
```

---

### Migration from WPAdminHealthComponents

If you were previously using `window.WPAdminHealthComponents` to access React components, you should migrate to the Extension API:

**Before (deprecated):**

```javascript
// Old approach - no longer works
const { MetricCard, React, createRoot } = window.WPAdminHealthComponents;
const root = createRoot(document.getElementById('my-container'));
root.render(React.createElement(MetricCard, { title: 'Custom' }));
```

**After (recommended):**

```javascript
// New approach - use the Extension API
WPAdminHealth.extensions.registerWidget('dashboard-bottom', {
	id: 'my-custom-metric',
	render: (container) => {
		// Either manipulate DOM directly
		container.innerHTML = '<div class="wpha-metric-card">...</div>';

		// Or return your own React element if you bundle React
		// return React.createElement(MyCustomComponent, props);
	},
});
```

---

## Scan Hooks

Currently, no dedicated scan hooks are available. Custom scan functionality can be extended through the cleanup hooks system.

---

## Cleanup Hooks

### `wpha_execute_cleanup`

Allows custom cleanup logic to be executed for specific task types.

**Type:** Filter
**Location:** `includes/class-scheduler.php:481`

**Parameters:**

- `$result` (array) - Cleanup result containing:
    - `items_cleaned` (int) - Number of items cleaned
    - `bytes_freed` (int) - Bytes freed during cleanup
- `$task_type` (string) - Type of cleanup task being executed
- `$settings` (array) - Task-specific settings

**Returns:** Modified cleanup result array

**Example:**

```php
add_filter( 'wpha_execute_cleanup', 'my_custom_cleanup', 10, 3 );

function my_custom_cleanup( $result, $task_type, $settings ) {
    // Execute custom cleanup for specific task type
    if ( $task_type === 'my_custom_task' ) {
        $custom_result = perform_my_cleanup( $settings );

        return array(
            'items_cleaned' => $custom_result['count'],
            'bytes_freed' => $custom_result['bytes'],
        );
    }

    return $result;
}
```

---

## UI Hooks

### `wpha_registered_plugin_tables`

Allows plugins to register their custom database tables to prevent them from being detected as orphaned.

**Type:** Filter
**Location:** `includes/database/class-orphaned-tables.php:192`

**Parameters:**

- `$plugin_tables` (array) - Array of plugin table names (without prefix)

**Returns:** Modified array of plugin table names

**Example:**

```php
add_filter( 'wpha_registered_plugin_tables', 'register_my_plugin_tables' );

function register_my_plugin_tables( $plugin_tables ) {
    // Add your custom plugin tables
    $plugin_tables[] = 'my_custom_data';
    $plugin_tables[] = 'my_custom_meta';
    $plugin_tables[] = 'my_custom_cache';

    return $plugin_tables;
}
```

---

## Settings Hooks

Currently, no dedicated settings hooks are available. Settings can be extended through the WordPress Settings API or custom implementations.

---

## Integration Hooks

### `wpha_configure_integration_factory`

Fires when the integration factory is being configured. This is the **recommended hook** for registering custom integrations as it allows proper dependency injection via the container.

**Type:** Action
**Location:** `includes/Providers/IntegrationServiceProvider.php:148`

**Parameters:**

- `$factory` (IntegrationFactory) - The integration factory instance

**When to Use:**
Use this hook when your integration needs dependencies injected via the container. Register your integration class with the container first, then map it in the factory.

**Example:**

```php
// Step 1: Register your integration class with the container (in your plugin's service provider or init)
add_action( 'plugins_loaded', function() {
    // Assuming you have access to the WPHA container
    $container = wpha_get_container(); // hypothetical helper
    $container->bind( My_Custom_Integration::class, function( $c ) {
        return new My_Custom_Integration(
            $c->get( ConnectionInterface::class ),
            $c->get( CacheInterface::class )
        );
    });
}, 5 );

// Step 2: Register the integration with the factory
add_action( 'wpha_configure_integration_factory', 'register_my_integration_with_factory' );

function register_my_integration_with_factory( $factory ) {
    $factory->register( 'my-integration', My_Custom_Integration::class );
}
```

---

### `wpha_register_integrations`

Fires to allow third-party integrations to register with the Integration Manager. Use this hook when you have a pre-instantiated integration object.

**Type:** Action
**Location:** `includes/Integrations/IntegrationManager.php:147`

**Parameters:**

- `$manager` (IntegrationManager) - The integration manager instance

**When to Use:**
Use this hook for simpler integrations that don't require container-based dependency injection, or when you need full control over instantiation.

**Example:**

```php
add_action( 'wpha_register_integrations', 'register_my_custom_integration' );

function register_my_custom_integration( $manager ) {
    // Register a custom integration instance
    $integration = new My_Custom_Integration();
    $manager->register( $integration );
}
```

---

### `wpha_before_integration_discovery`

Fires before built-in integrations are discovered. Use this to prepare resources or state before the integration discovery process begins.

**Type:** Action
**Location:** `includes/Integrations/IntegrationManager.php:135`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_before_integration_discovery', 'prepare_for_integrations' );

function prepare_for_integrations() {
    // Perform setup before integrations are loaded
    do_something_before_integrations();
}
```

---

### `wpha_integrations_loaded`

Fires after all integrations have been registered (but not necessarily initialized). Use this to inspect or modify the set of registered integrations.

**Type:** Action
**Location:** `includes/Integrations/IntegrationManager.php:158`

**Parameters:**

- `$manager` (IntegrationManager) - The integration manager instance

**Example:**

```php
add_action( 'wpha_integrations_loaded', 'after_integrations_loaded' );

function after_integrations_loaded( $manager ) {
    // Access registered integrations
    $woo = $manager->get( 'woocommerce' );
    if ( $woo && $woo->is_available() ) {
        // WooCommerce integration is available
    }
}
```

---

### `wpha_integration_initialized`

Fires after an individual integration has been initialized. Use this for integration-specific setup that depends on the integration being fully ready.

**Type:** Action
**Location:** `includes/Integrations/AbstractIntegration.php:151`

**Parameters:**

- `$integration` (IntegrationInterface) - The integration instance

**Example:**

```php
add_action( 'wpha_integration_initialized', 'log_integration_init' );

function log_integration_init( $integration ) {
    error_log( 'Integration initialized: ' . $integration->get_id() );
}
```

---

### `wpha_integration_deactivated`

Fires after an integration has been deactivated. Use this to clean up resources associated with the integration.

**Type:** Action
**Location:** `includes/Integrations/AbstractIntegration.php:172`

**Parameters:**

- `$integration` (IntegrationInterface) - The integration instance

**Example:**

```php
add_action( 'wpha_integration_deactivated', 'handle_integration_deactivation' );

function handle_integration_deactivation( $integration ) {
    // Clean up resources when integration is deactivated
    clear_integration_cache( $integration->get_id() );
}
```

---

## Core & Lifecycle Hooks

### `wpha_init`

Fires after the plugin initialization is complete.

**Type:** Action
**Location:** `includes/Plugin.php:146`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_init', 'my_plugin_init' );

function my_plugin_init() {
    // Initialize custom functionality after WPHA is ready
    do_something_after_wpha_init();
}
```

---

### `wpha_dependencies_loaded`

Fires after all plugin dependencies are loaded.

**Type:** Action
**Location:** `includes/Plugin.php:182`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_dependencies_loaded', 'my_dependencies_check' );

function my_dependencies_check() {
    // Verify all dependencies are available
    if ( class_exists( 'WPAdminHealth\\Database\\Analyzer' ) ) {
        // Database services are ready
    }
}
```

---

### `wpha_activate`

Fires before plugin activation tasks are run.

**Type:** Action
**Location:** `includes/Plugin.php:200`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_activate', 'my_activation_tasks' );

function my_activation_tasks() {
    // Run custom tasks before WPHA activation
    create_custom_tables();
    set_default_options();
}
```

---

### `wpha_activated`

Fires after plugin activation is complete.

**Type:** Action
**Location:** `includes/Plugin.php:215`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_activated', 'my_post_activation_tasks' );

function my_post_activation_tasks() {
    // Run tasks after WPHA is fully activated
    schedule_initial_scan();
}
```

---

### `wpha_deactivate`

Fires before plugin deactivation tasks are run.

**Type:** Action
**Location:** `includes/Plugin.php:233`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_deactivate', 'my_deactivation_tasks' );

function my_deactivation_tasks() {
    // Clean up before deactivation
    clear_custom_caches();
}
```

---

### `wpha_deactivated`

Fires after plugin deactivation is complete.

**Type:** Action
**Location:** `includes/Plugin.php:245`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_deactivated', 'my_post_deactivation_tasks' );

function my_post_deactivation_tasks() {
    // Final cleanup after deactivation
    remove_scheduled_events();
}
```

---

### `wpha_upgraded`

Fires after plugin upgrade is complete.

**Type:** Action
**Location:** `includes/class-installer.php:215`

**Parameters:**

- `$from_version` (string) - The version being upgraded from
- `$to_version` (string) - The version being upgraded to

**Example:**

```php
add_action( 'wpha_upgraded', 'my_upgrade_routine', 10, 2 );

function my_upgrade_routine( $from_version, $to_version ) {
    // Handle version-specific upgrades
    if ( version_compare( $from_version, '1.5.0', '<' ) ) {
        migrate_old_data();
    }
}
```

---

### `wpha_uninstalled`

Fires after plugin uninstall is complete.

**Type:** Action
**Location:** `includes/class-installer.php:248`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_uninstalled', 'my_uninstall_cleanup' );

function my_uninstall_cleanup() {
    // Clean up custom data when WPHA is uninstalled
    delete_custom_options();
    drop_custom_tables();
}
```

---

### `wpha_rest_api_init` _(Deprecated)_

> **Deprecated since 1.3.0:** This hook is no longer fired at runtime. The legacy `RestApi` class that fired this hook is no longer instantiated. Use the WordPress `rest_api_init` action instead.

Fires after REST API initialization.

**Type:** Action
**Location:** `includes/RestApi.php:59` _(Legacy - not executed)_

**Parameters:** None

**Migration Example:**

```php
// Before (deprecated - no longer fires):
add_action( 'wpha_rest_api_init', 'my_rest_api_setup' );

// After (recommended):
add_action( 'rest_api_init', 'my_rest_api_setup' );

function my_rest_api_setup() {
    // Initialize custom REST API functionality
    register_custom_rest_fields();
}
```

---

### `wpha_register_rest_routes` _(Deprecated)_

> **Deprecated since 1.3.0:** This hook is no longer fired at runtime. The legacy `RestApi` class that fired this hook is no longer instantiated. Use the WordPress `rest_api_init` action instead to register custom routes.

Fires after core REST routes are registered, allowing other controllers to register their routes.

**Type:** Action
**Location:** `includes/RestApi.php:143` _(Legacy - not executed)_

**Parameters:** None

**Migration Example:**

```php
// Before (deprecated - no longer fires):
add_action( 'wpha_register_rest_routes', 'register_my_custom_routes' );

// After (recommended):
add_action( 'rest_api_init', 'register_my_custom_routes' );

function register_my_custom_routes() {
    register_rest_route( 'wpha/v1', '/custom-endpoint', array(
        'methods' => 'GET',
        'callback' => 'my_custom_endpoint_handler',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
}
```

---

### `wpha_task_started`

Fires when a scheduled task begins execution.

**Type:** Action
**Location:** `includes/Scheduler/AbstractScheduledTask.php`
**Since:** 1.8.0

**Parameters:**

- `$task_id` (string) - Task identifier (e.g., 'database_cleanup', 'media_scan')
- `$task_name` (string) - Human-readable task name
- `$context` (array) - Optional context data passed to the task
- `$start_time` (float) - Microtime when task started

**Example:**

```php
add_action( 'wpha_task_started', 'log_task_start', 10, 4 );

function log_task_start( $task_id, $task_name, $context, $start_time ) {
    error_log( sprintf( 'Task %s (%s) started at %s', $task_id, $task_name, gmdate( 'Y-m-d H:i:s' ) ) );
}
```

---

### `wpha_task_completed`

Fires when a scheduled task completes successfully.

**Type:** Action
**Location:** `includes/Scheduler/AbstractScheduledTask.php`
**Since:** 1.8.0

**Parameters:**

- `$task_id` (string) - Task identifier
- `$task_name` (string) - Human-readable task name
- `$result` (array) - Task result containing:
    - `success` (bool) - Whether the task succeeded
    - `items_found` (int) - Number of items found/scanned
    - `items_cleaned` (int) - Number of items processed/cleaned
    - `bytes_freed` (int) - Bytes freed (if applicable)
    - `was_interrupted` (bool) - Whether task was interrupted by timeout
- `$elapsed_time` (float) - Execution time in seconds

**Example:**

```php
add_action( 'wpha_task_completed', 'log_task_completion', 10, 4 );

function log_task_completion( $task_id, $task_name, $result, $elapsed_time ) {
    if ( $result['items_cleaned'] > 0 ) {
        error_log( sprintf(
            'Task %s cleaned %d items in %.2f seconds',
            $task_id,
            $result['items_cleaned'],
            $elapsed_time
        ) );
    }
}
```

---

### `wpha_task_interrupted`

Fires when a scheduled task is interrupted (e.g., timeout) and needs to resume later.

**Type:** Action
**Location:** `includes/Scheduler/AbstractScheduledTask.php`
**Since:** 1.8.0

**Parameters:**

- `$task_id` (string) - Task identifier
- `$task_name` (string) - Human-readable task name
- `$result` (array) - Partial result up to the point of interruption
- `$progress` (array) - Progress state for resumption
- `$elapsed_time` (float) - Execution time before interruption

**Example:**

```php
add_action( 'wpha_task_interrupted', 'handle_task_interruption', 10, 5 );

function handle_task_interruption( $task_id, $task_name, $result, $progress, $elapsed_time ) {
    // Log interruption for monitoring
    error_log( sprintf(
        'Task %s interrupted after %.2f seconds. Progress saved for resumption.',
        $task_id,
        $elapsed_time
    ) );

    // Optionally notify admin of long-running task
    if ( $elapsed_time > 60 ) {
        wp_mail( get_option( 'admin_email' ), 'Task Timeout', "Task {$task_name} timed out." );
    }
}
```

---

### `wpha_task_failed`

Fires when a scheduled task fails with an error.

**Type:** Action
**Location:** `includes/Scheduler/AbstractScheduledTask.php`
**Since:** 1.8.0

**Parameters:**

- `$task_id` (string) - Task identifier
- `$task_name` (string) - Human-readable task name
- `$error` (string) - Error message
- `$context` (array) - Additional error context (e.g., subtask that failed)
- `$elapsed_time` (float) - Execution time before failure

**Example:**

```php
add_action( 'wpha_task_failed', 'handle_task_failure', 10, 5 );

function handle_task_failure( $task_id, $task_name, $error, $context, $elapsed_time ) {
    // Log failure
    error_log( sprintf( 'Task %s failed: %s', $task_id, $error ) );

    // Send notification for critical tasks
    if ( in_array( $task_id, array( 'database_cleanup', 'media_scan' ) ) ) {
        wp_mail(
            get_option( 'admin_email' ),
            'Scheduled Task Failed',
            sprintf( "Task: %s\nError: %s", $task_name, $error )
        );
    }
}
```

---

### `wpha_activity_log_pruned`

Fires after activity log entries are pruned. Use this hook to trigger related cleanup operations.

**Type:** Action
**Location:** `includes/Services/ActivityLogger.php`
**Since:** 1.8.0

**Parameters:**

- `$rows_deleted` (int) - Number of rows deleted from the activity log

**Example:**

```php
add_action( 'wpha_activity_log_pruned', 'cleanup_related_data' );

function cleanup_related_data( $rows_deleted ) {
    if ( $rows_deleted > 0 ) {
        // Trigger progress data cleanup
        do_action( 'my_plugin_prune_cache' );
    }
}
```

---

### `wpha_scheduler_log`

Fires when the scheduler logs an execution event.

**Type:** Action
**Location:** `includes/class-scheduler.php:584`

**Parameters:**

- `$task_id` (int) - Task ID being logged
- `$action` (string) - Action performed (e.g., 'scheduled', 'completed', 'recovered')
- `$message` (string) - Log message with execution details

**Example:**

```php
add_action( 'wpha_scheduler_log', 'my_scheduler_logger', 10, 3 );

function my_scheduler_logger( $task_id, $action, $message ) {
    // Log to custom logging system
    if ( $action === 'completed' ) {
        my_custom_logger( "Task {$task_id} completed: {$message}" );
    }
}
```

---

### `wpha_assets_init`

Fires after assets initialization.

**Type:** Action
**Location:** `includes/Assets.php:76`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_assets_init', 'enqueue_my_custom_assets' );

function enqueue_my_custom_assets() {
    // Enqueue custom scripts/styles after WPHA assets
    wp_enqueue_style( 'my-custom-style', MY_PLUGIN_URL . 'css/custom.css' );
}
```

---

### `wpha_database_init` _(Deprecated)_

> **Deprecated since 1.3.0:** This hook is no longer fired at runtime. The legacy `Database` class that fired this hook is no longer instantiated. Database operations are now handled via `DatabaseServiceProvider` and related service classes. Use the `wpha_init` hook instead for initialization tasks.

Fires after database initialization.

**Type:** Action
**Location:** `includes/Database.php:57` _(Legacy - not executed)_

**Parameters:** None

**Migration Example:**

```php
// Before (deprecated - no longer fires):
add_action( 'wpha_database_init', 'my_database_setup' );

// After (recommended - use wpha_init for general initialization):
add_action( 'wpha_init', 'my_database_setup' );

function my_database_setup() {
    // Perform custom database operations after WPHA init
    global $wpdb;
    // Create custom tables, etc.
}
```

---

### `wpha_admin_init`

Fires after admin initialization is complete. This hook fires after the admin menu is registered and assets are loaded.

**Type:** Action
**Location:** `includes/Providers/BootstrapServiceProvider.php:171`

**Parameters:** None

**Note:** As of version 1.4.0, the admin system has been refactored into separate services:

- `MenuRegistrar` - handles WordPress admin menu registration
- `PageRenderer` - handles template rendering
- `SettingsViewModel` - provides settings data to templates

The `wpha_admin_init` hook now fires from `BootstrapServiceProvider` after these services are initialized.

**Example:**

```php
add_action( 'wpha_admin_init', 'my_admin_customizations' );

function my_admin_customizations() {
    // Add custom admin functionality after WPHA admin is ready
    add_action( 'admin_notices', 'my_custom_admin_notice' );
}
```

---

## Observability Hooks

The observability hooks provide insight into lock contention and rate-limiting events. These hooks are essential for diagnosing performance issues in high-concurrency environments.

> **Note:** Events are only logged when debug mode is enabled (via settings, `WPHA_DEBUG` constant, or `WP_DEBUG` with `WP_DEBUG_LOG`). This prevents performance overhead in production while enabling detailed diagnostics during troubleshooting.

### `wpha_lock_acquired`

Fires when a lock is successfully acquired for task execution or rate limiting.

**Type:** Action
**Location:** `includes/Scheduler/SchedulerRegistry.php`, `includes/REST/RestController.php`
**Since:** 1.8.0

**Parameters:**

- `$lock_name` (string) - Lock identifier (task ID or lock key)
- `$context` (array) - Context including:
    - `method` (string) - Lock acquisition method (`mysql_advisory`, `option_fallback`, `stale_recovery`)

**Example:**

```php
add_action( 'wpha_lock_acquired', 'monitor_lock_acquisition', 10, 2 );

function monitor_lock_acquisition( $lock_name, $context ) {
    // Track lock acquisition for monitoring
    if ( $context['method'] === 'stale_recovery' ) {
        error_log( sprintf( 'Lock %s acquired via stale recovery', $lock_name ) );
    }
}
```

---

### `wpha_lock_contention`

Fires when lock contention is detected (another process holds the lock).

**Type:** Action
**Location:** `includes/Scheduler/SchedulerRegistry.php`, `includes/REST/RestController.php`
**Since:** 1.8.0

**Parameters:**

- `$lock_name` (string) - Lock identifier
- `$context` (array) - Context including:
    - `attempts` (int) - Number of lock acquisition attempts
    - `method` (string) - Lock method being used (`mysql_advisory`, `option_fallback`, `rate_limit_transient`)

**Example:**

```php
add_action( 'wpha_lock_contention', 'alert_on_contention', 10, 2 );

function alert_on_contention( $lock_name, $context ) {
    if ( $context['attempts'] > 3 ) {
        // High contention detected - consider increasing lock timeout
        // or reviewing task scheduling configuration
        error_log( sprintf(
            'High lock contention on %s (%d attempts, method: %s)',
            $lock_name,
            $context['attempts'],
            $context['method']
        ) );
    }
}
```

---

### `wpha_lock_released`

Fires when a lock is released after task completion.

**Type:** Action
**Location:** `includes/Scheduler/SchedulerRegistry.php`
**Since:** 1.8.0

**Parameters:**

- `$lock_name` (string) - Lock identifier (task ID)
- `$context` (array) - Context including:
    - `held_time` (int|null) - Duration in seconds the lock was held

**Example:**

```php
add_action( 'wpha_lock_released', 'track_lock_duration', 10, 2 );

function track_lock_duration( $lock_name, $context ) {
    if ( isset( $context['held_time'] ) && $context['held_time'] > 60 ) {
        // Task held lock for over a minute - may need optimization
        error_log( sprintf(
            'Task %s held lock for %d seconds',
            $lock_name,
            $context['held_time']
        ) );
    }
}
```

---

### `wpha_lock_timeout`

Fires when all lock acquisition attempts are exhausted.

**Type:** Action
**Location:** `includes/REST/RestController.php`
**Since:** 1.8.0

**Parameters:**

- `$lock_name` (string) - Lock identifier
- `$context` (array) - Context including:
    - `max_attempts` (int) - Maximum number of attempts made

**Example:**

```php
add_action( 'wpha_lock_timeout', 'handle_lock_timeout', 10, 2 );

function handle_lock_timeout( $lock_name, $context ) {
    // Log timeout for investigation
    error_log( sprintf(
        'Lock timeout on %s after %d attempts',
        $lock_name,
        $context['max_attempts']
    ) );

    // Optionally notify admin
    if ( function_exists( 'wp_mail' ) ) {
        wp_mail(
            get_option( 'admin_email' ),
            'WPHA Lock Timeout',
            sprintf( 'Lock %s timed out after %d attempts', $lock_name, $context['max_attempts'] )
        );
    }
}
```

---

### `wpha_lock_stale_recovery`

Fires when a stale lock is detected and being recovered.

**Type:** Action
**Location:** `includes/Scheduler/SchedulerRegistry.php`
**Since:** 1.8.0

**Parameters:**

- `$lock_name` (string) - Lock identifier
- `$context` (array) - Context including:
    - `age` (int) - Age of the stale lock in seconds

**Example:**

```php
add_action( 'wpha_lock_stale_recovery', 'log_stale_lock', 10, 2 );

function log_stale_lock( $lock_name, $context ) {
    // Stale locks indicate interrupted processes
    error_log( sprintf(
        'Recovering stale lock for %s (age: %d seconds)',
        $lock_name,
        $context['age']
    ) );
}
```

---

### `wpha_rate_limit_hit`

Fires when a rate limit is incremented. Only fires when approaching the limit (80%+) to minimize overhead.

**Type:** Action
**Location:** `includes/REST/RestController.php`
**Since:** 1.8.0

**Parameters:**

- `$user_id` (int) - Current user ID
- `$count` (int) - Current request count in the window
- `$limit` (int) - Rate limit threshold

**Example:**

```php
add_action( 'wpha_rate_limit_hit', 'warn_approaching_limit', 10, 3 );

function warn_approaching_limit( $user_id, $count, $limit ) {
    // Only fires when count >= 80% of limit
    $percentage = ( $count / $limit ) * 100;
    error_log( sprintf(
        'User %d at %.1f%% of rate limit (%d/%d)',
        $user_id,
        $percentage,
        $count,
        $limit
    ) );
}
```

---

### `wpha_rate_limit_exceeded`

Fires when a user exceeds their rate limit and the request is rejected.

**Type:** Action
**Location:** `includes/REST/RestController.php`
**Since:** 1.8.0

**Parameters:**

- `$user_id` (int) - Current user ID
- `$count` (int) - Request count that exceeded the limit
- `$limit` (int) - Rate limit threshold

**Example:**

```php
add_action( 'wpha_rate_limit_exceeded', 'log_rate_limit_violation', 10, 3 );

function log_rate_limit_violation( $user_id, $count, $limit ) {
    $user = get_userdata( $user_id );
    error_log( sprintf(
        'Rate limit exceeded for user %s (ID: %d) - %d requests (limit: %d)',
        $user ? $user->user_login : 'unknown',
        $user_id,
        $count,
        $limit
    ) );
}
```

---

### `wpha_rate_limit_unavailable`

Fires when the rate limiter is unavailable (lock acquisition failed).

**Type:** Action
**Location:** `includes/REST/RestController.php`
**Since:** 1.8.0

**Parameters:**

- `$user_id` (int) - Current user ID
- `$context` (array) - Context including:
    - `reason` (string) - Reason for unavailability (e.g., `lock_failure`)

**Example:**

```php
add_action( 'wpha_rate_limit_unavailable', 'handle_rate_limiter_failure', 10, 2 );

function handle_rate_limiter_failure( $user_id, $context ) {
    // This indicates potential database or concurrency issues
    error_log( sprintf(
        'Rate limiter unavailable for user %d: %s',
        $user_id,
        $context['reason']
    ) );
}
```

---

### `wpha_observability_event`

Generic hook fired for all observability events. Use this for centralized logging or external monitoring integration.

**Type:** Action
**Location:** `includes/Services/ObservabilityEventLogger.php`
**Since:** 1.8.0

**Parameters:**

- `$event_type` (string) - Event type identifier (e.g., `lock_contention`, `rate_limit_exceeded`)
- `$level` (string) - Log level (`debug`, `info`, `warning`, `error`)
- `$data` (array) - Sanitized event data (never contains sensitive information)

**Example:**

```php
add_action( 'wpha_observability_event', 'send_to_monitoring', 10, 3 );

function send_to_monitoring( $event_type, $level, $data ) {
    // Send to external monitoring service
    wp_remote_post( 'https://monitoring.example.com/api/events', array(
        'body' => wp_json_encode( array(
            'source' => 'wp-admin-health-suite',
            'event'  => $event_type,
            'level'  => $level,
            'data'   => $data,
            'time'   => gmdate( 'c' ),
        ) ),
        'headers' => array( 'Content-Type' => 'application/json' ),
    ) );
}
```

---

## Diagnosing Lock Contention & Rate Limiting

This section provides guidance on diagnosing and resolving lock contention and rate limiting issues.

### Enabling Observability Logging

Observability events are only logged when debug mode is enabled. Enable logging using one of these methods:

1. **Via Settings:** Enable "Debug Mode" in the plugin settings.

2. **Via Constant:** Add to `wp-config.php`:

    ```php
    define( 'WPHA_DEBUG', true );
    ```

3. **Via WP_DEBUG:** Enable WordPress debug logging:
    ```php
    define( 'WP_DEBUG', true );
    define( 'WP_DEBUG_LOG', true );
    ```

### Reading the Logs

Events are logged to the WordPress debug log (typically `wp-content/debug.log`). Look for entries with the prefix `[WP Admin Health]`:

```
[WP Admin Health] [WARNING] lock_contention: {"lock_name":"database_cleanup","attempts":3,"method":"mysql_advisory"}
[WP Admin Health] [WARNING] rate_limit_exceeded: {"user_id":1,"count":61,"limit":60}
```

### Common Issues and Solutions

#### High Lock Contention

**Symptoms:**

- Multiple `wpha_lock_contention` events in the log
- Tasks reporting "Task is already running" errors
- Stale lock recovery events

**Causes:**

1. Tasks running longer than expected
2. Overlapping scheduled task execution times
3. Manual task triggers during scheduled runs

**Solutions:**

1. **Increase time budgets:** Adjust the task's `max_execution_time` setting.
2. **Stagger schedules:** Ensure tasks don't overlap by spacing their execution times.
3. **Review task scope:** Break large tasks into smaller chunks.
4. **Check for slow queries:** Use the performance insights to identify bottlenecks.

#### Stale Locks

**Symptoms:**

- `wpha_lock_stale_recovery` events with high `age` values
- Tasks not completing successfully

**Causes:**

1. PHP fatal errors during task execution
2. Server timeouts or restarts
3. Memory limit exhaustion

**Solutions:**

1. **Check error logs:** Look for PHP fatal errors around the time of the stale lock.
2. **Increase memory limits:** Adjust `WP_MEMORY_LIMIT` if memory is the issue.
3. **Review task complexity:** Simplify tasks that consistently fail.

#### Rate Limiting Issues

**Symptoms:**

- `wpha_rate_limit_exceeded` events
- HTTP 429 responses from the REST API
- `wpha_rate_limit_unavailable` events

**Causes:**

1. Automated scripts making too many requests
2. Multiple browser tabs open
3. Third-party integrations polling the API
4. Rate limit set too low for legitimate usage

**Solutions:**

1. **Adjust rate limit:** Increase the rate limit in settings if legitimate.
2. **Optimize client code:** Batch API requests where possible.
3. **Use caching:** Cache API responses to reduce request frequency.
4. **Review integrations:** Identify third-party tools making excessive requests.

### Monitoring Best Practices

1. **Set up alerts:** Use the `wpha_observability_event` hook to send events to your monitoring system.

2. **Track patterns:** Look for recurring contention at specific times.

3. **Baseline metrics:** Establish normal behavior before investigating anomalies.

4. **Review periodically:** Check logs after major site changes or traffic spikes.

### Example Monitoring Setup

```php
/**
 * Custom monitoring integration for observability events
 */
add_action( 'wpha_observability_event', 'custom_monitoring_handler', 10, 3 );

function custom_monitoring_handler( $event_type, $level, $data ) {
    // Only alert on warnings and errors
    if ( ! in_array( $level, array( 'warning', 'error' ), true ) ) {
        return;
    }

    // Track metrics (example using custom options)
    $metrics_key = 'wpha_observability_metrics';
    $metrics = get_option( $metrics_key, array() );

    $today = gmdate( 'Y-m-d' );
    if ( ! isset( $metrics[ $today ] ) ) {
        $metrics[ $today ] = array();
    }
    if ( ! isset( $metrics[ $today ][ $event_type ] ) ) {
        $metrics[ $today ][ $event_type ] = 0;
    }
    $metrics[ $today ][ $event_type ]++;

    // Keep only last 7 days
    $metrics = array_slice( $metrics, -7, 7, true );
    update_option( $metrics_key, $metrics );

    // Alert if threshold exceeded
    if ( $metrics[ $today ][ $event_type ] > 100 ) {
        // Send alert to admin
        wp_mail(
            get_option( 'admin_email' ),
            sprintf( 'WPHA Alert: High %s count', $event_type ),
            sprintf(
                "Event type: %s\nCount today: %d\nLatest data: %s",
                $event_type,
                $metrics[ $today ][ $event_type ],
                wp_json_encode( $data )
            )
        );
    }
}
```

---

## Recommended Extension Patterns

### Creating Custom Integrations

The recommended approach for creating custom integrations is to implement the `IntegrationInterface` and register via the factory hook. Here's a complete example:

```php
<?php
/**
 * Custom Integration Example
 *
 * @package MyPlugin\Integrations
 */

namespace MyPlugin\Integrations;

use WPAdminHealth\Integrations\AbstractIntegration;

/**
 * Custom integration class.
 *
 * Extend AbstractIntegration for common functionality.
 */
class MyCustomIntegration extends AbstractIntegration {

    /**
     * Get unique integration identifier.
     */
    public function get_id(): string {
        return 'my-custom-integration';
    }

    /**
     * Get human-readable name.
     */
    public function get_name(): string {
        return 'My Custom Integration';
    }

    /**
     * Check if the target plugin is active.
     */
    public function is_available(): bool {
        return class_exists( 'MyTargetPlugin' );
    }

    /**
     * Get minimum supported version of the target plugin.
     */
    public function get_min_version(): string {
        return '2.0.0';
    }

    /**
     * Get capabilities this integration provides.
     */
    public function get_capabilities(): array {
        return array( 'custom_cleanup', 'performance_insights' );
    }

    /**
     * Initialize the integration (called when available and compatible).
     */
    protected function setup(): void {
        // Set up hooks, filters, etc.
        add_filter( 'wpha_execute_cleanup', array( $this, 'handle_cleanup' ), 10, 3 );
    }
}

// Register via the factory hook (recommended)
add_action( 'wpha_configure_integration_factory', function( $factory ) {
    // First, ensure your class is registered with the container if it needs dependencies
    // Then register with the factory
    $factory->register( 'my-custom-integration', MyCustomIntegration::class );
} );
```

### Integration Lifecycle

1. **Factory Configuration** (`wpha_configure_integration_factory`) - Register integration classes
2. **Before Discovery** (`wpha_before_integration_discovery`) - Prepare resources
3. **Registration** (`wpha_register_integrations`) - Register pre-instantiated integrations
4. **Loaded** (`wpha_integrations_loaded`) - All integrations registered
5. **Initialization** (`wpha_integration_initialized`) - Each integration initialized
6. **Deactivation** (`wpha_integration_deactivated`) - Integration cleanup

---

## Common Customization Examples

### Example 1: Adding Custom Plugin Tables to Exclusions

Prevent your plugin's tables from being detected as orphaned:

```php
/**
 * Register custom plugin tables with WP Admin Health Suite
 */
add_filter( 'wpha_registered_plugin_tables', 'register_my_ecommerce_tables' );

function register_my_ecommerce_tables( $plugin_tables ) {
    // Add all your plugin's table names (without wp_ prefix)
    $my_tables = array(
        'shop_orders',
        'shop_order_items',
        'shop_products',
        'shop_customers',
        'shop_sessions',
    );

    return array_merge( $plugin_tables, $my_tables );
}
```

### Example 2: Implementing Custom Cleanup Task

Add a custom cleanup task for your plugin's temporary data:

```php
/**
 * Add custom cleanup logic for temporary cache files
 */
add_filter( 'wpha_execute_cleanup', 'cleanup_my_plugin_cache', 10, 3 );

function cleanup_my_plugin_cache( $result, $task_type, $settings ) {
    // Only handle our custom task type
    if ( $task_type !== 'my_plugin_cache' ) {
        return $result;
    }

    $items_cleaned = 0;
    $bytes_freed = 0;

    // Get cache directory
    $cache_dir = WP_CONTENT_DIR . '/cache/my-plugin/';

    if ( is_dir( $cache_dir ) ) {
        $files = glob( $cache_dir . '*.cache' );
        $max_age = isset( $settings['max_age'] ) ? $settings['max_age'] : 86400; // 24 hours

        foreach ( $files as $file ) {
            if ( time() - filemtime( $file ) > $max_age ) {
                $bytes_freed += filesize( $file );
                unlink( $file );
                $items_cleaned++;
            }
        }
    }

    return array(
        'items_cleaned' => $items_cleaned,
        'bytes_freed' => $bytes_freed,
    );
}
```

### Example 3: Extending Scan Locations

Hook into initialization to add custom scan locations:

```php
/**
 * Add custom directories to be scanned
 */
add_action( 'wpha_init', 'register_custom_scan_locations' );

function register_custom_scan_locations() {
    // Example: Add custom upload directories
    // This would require extending the core scanner class
    // Implementation depends on your specific requirements
}
```

### Example 4: Modifying Cleanup Recommendations

Intercept cleanup results to add custom recommendations:

```php
/**
 * Add custom recommendations based on cleanup results
 */
add_filter( 'wpha_execute_cleanup', 'add_cleanup_recommendations', 20, 3 );

function add_cleanup_recommendations( $result, $task_type, $settings ) {
    // Add recommendations based on cleanup results
    if ( isset( $result['items_cleaned'] ) && $result['items_cleaned'] > 1000 ) {
        // Could trigger a notification or log a recommendation
        do_action( 'my_plugin_high_cleanup_alert', $result );
    }

    return $result;
}
```

### Example 5: Custom Logging Integration

Integrate with external logging systems:

```php
/**
 * Send scheduler events to external logging service
 */
add_action( 'wpha_scheduler_log', 'log_to_external_service', 10, 3 );

function log_to_external_service( $task_id, $action, $message ) {
    // Only log important events
    $important_actions = array( 'completed', 'failed', 'recovered' );

    if ( in_array( $action, $important_actions ) ) {
        // Send to logging service
        wp_remote_post( 'https://logging-service.example.com/api/log', array(
            'body' => array(
                'plugin' => 'wp-admin-health-suite',
                'task_id' => $task_id,
                'action' => $action,
                'message' => $message,
                'timestamp' => current_time( 'mysql' ),
            ),
        ) );
    }
}
```

### Example 6: Database Migration on Upgrade

Handle custom data migration during plugin upgrades:

```php
/**
 * Migrate data when upgrading from older versions
 */
add_action( 'wpha_upgraded', 'migrate_custom_data', 10, 2 );

function migrate_custom_data( $from_version, $to_version ) {
    global $wpdb;

    // Migrate from version 1.0 to 2.0 format
    if ( version_compare( $from_version, '2.0.0', '<' ) ) {
        // Update table structure
        $wpdb->query( "ALTER TABLE {$wpdb->prefix}my_custom_table ADD COLUMN new_field VARCHAR(255)" );

        // Migrate data
        $wpdb->query( "UPDATE {$wpdb->prefix}my_custom_table SET new_field = old_field WHERE new_field IS NULL" );
    }
}
```

### Example 7: Custom REST API Endpoint

Add custom REST API endpoints for external integrations using the standard WordPress `rest_api_init` hook:

```php
/**
 * Register custom REST API endpoint
 */
add_action( 'rest_api_init', 'register_custom_health_endpoint' );

function register_custom_health_endpoint() {
    register_rest_route( 'wpha/v1', '/custom-health-check', array(
        'methods' => 'GET',
        'callback' => 'get_custom_health_status',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
}

function get_custom_health_status( $request ) {
    // Perform custom health checks
    $health_data = array(
        'database_size' => get_database_size(),
        'cache_status' => check_cache_status(),
        'custom_metrics' => get_custom_metrics(),
    );

    return rest_ensure_response( $health_data );
}
```

---

## Best Practices

1. **Prefix Your Functions**: Always prefix your custom functions to avoid conflicts (e.g., `my_plugin_custom_function`).

2. **Priority Matters**: Use appropriate priority values when adding hooks. Lower numbers run earlier (default is 10).

3. **Check for Existence**: Always verify that classes and functions exist before using them:

    ```php
    if ( function_exists( 'wpha_get_scanner' ) ) {
        // Use WPHA functions
    }
    ```

4. **Return Values**: For filters, always return a value, even if unchanged:

    ```php
    add_filter( 'wpha_registered_plugin_tables', 'my_function' );
    function my_function( $tables ) {
        // Your code here
        return $tables; // Always return!
    }
    ```

5. **Error Handling**: Implement proper error handling in your hook callbacks to prevent breaking the plugin.

6. **Documentation**: Document your custom implementations for future reference.

---

## Support

For questions about extending WP Admin Health Suite:

- Check the main plugin documentation
- Review the source code for implementation details
- Refer to WordPress Plugin Handbook for general hook usage
- Submit issues or feature requests on the plugin repository

---

**Last Updated:** 2026-01-18
**Plugin Version Compatibility:** 1.0.0+

**Version Notes:**

- **1.3.0:** Deprecated `wpha_rest_api_init`, `wpha_register_rest_routes`, and `wpha_database_init` hooks
- **1.4.0:** Admin system refactored - `wpha_admin_init` now fires from `BootstrapServiceProvider`
- **1.5.0:** Added `SettingsViewModel` for template data injection
- **1.8.0:** Added task observability hooks (`wpha_task_started`, `wpha_task_completed`, `wpha_task_interrupted`, `wpha_task_failed`, `wpha_activity_log_pruned`) and lock/rate-limit observability hooks (`wpha_lock_acquired`, `wpha_lock_contention`, `wpha_lock_released`, `wpha_lock_timeout`, `wpha_lock_stale_recovery`, `wpha_rate_limit_hit`, `wpha_rate_limit_exceeded`, `wpha_rate_limit_unavailable`, `wpha_observability_event`)
