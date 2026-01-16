# Hooks & Filters Reference

WP Admin Health Suite provides a comprehensive set of hooks and filters to extend and customize plugin functionality. All hooks follow the `wpha_` prefix naming convention.

## Table of Contents

- [Scan Hooks](#scan-hooks)
- [Cleanup Hooks](#cleanup-hooks)
- [UI Hooks](#ui-hooks)
- [Settings Hooks](#settings-hooks)
- [Core & Lifecycle Hooks](#core--lifecycle-hooks)
- [Common Customization Examples](#common-customization-examples)

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
    if ( class_exists( 'WP_Admin_Health\\Database' ) ) {
        // Dependencies are ready
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

### `wpha_rest_api_init`

Fires after REST API initialization.

**Type:** Action
**Location:** `includes/REST_API.php:59`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_rest_api_init', 'my_rest_api_setup' );

function my_rest_api_setup() {
    // Initialize custom REST API functionality
    register_custom_rest_fields();
}
```

---

### `wpha_register_rest_routes`

Fires after core REST routes are registered, allowing other controllers to register their routes.

**Type:** Action
**Location:** `includes/REST_API.php:107`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_register_rest_routes', 'register_my_custom_routes' );

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

### `wpha_database_init`

Fires after database initialization.

**Type:** Action
**Location:** `includes/Database.php:57`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_database_init', 'my_database_setup' );

function my_database_setup() {
    // Perform custom database operations after WPHA DB init
    global $wpdb;
    // Create custom tables, etc.
}
```

---

### `wpha_admin_init`

Fires after admin initialization.

**Type:** Action
**Location:** `includes/Admin.php:71`

**Parameters:** None

**Example:**

```php
add_action( 'wpha_admin_init', 'my_admin_customizations' );

function my_admin_customizations() {
    // Add custom admin functionality
    add_menu_page( 'Custom Page', 'Custom', 'manage_options', 'custom-page', 'render_custom_page' );
}
```

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

Add custom REST API endpoints for external integrations:

```php
/**
 * Register custom REST API endpoint
 */
add_action( 'wpha_register_rest_routes', 'register_custom_health_endpoint' );

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

**Last Updated:** 2026-01-07
**Plugin Version Compatibility:** 1.0.0+
