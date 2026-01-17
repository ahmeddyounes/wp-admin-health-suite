# Multisite Support Review

Review Date: 2026-01-17
Task: Q18-08
Scope: `includes/Multisite.php` - network activation handling, site-specific vs network-wide settings, blog switching

## Summary

The `Multisite` class (`includes/Multisite.php`) provides comprehensive WordPress multisite network support. The implementation follows WordPress best practices for multisite architecture, with proper network activation detection, settings storage, and capability checks. **One bug was fixed during this review: a missing `get_multisite()` method in the Plugin class that the network templates depended on.**

## File Reviewed

### includes/Multisite.php

**Status: PASS**

The class handles all multisite-specific functionality including:

- Network activation detection
- Network admin menu registration
- Network settings management
- Site enumeration
- Permission checks for network-wide operations

## Network Activation Handling

### Detection Pattern

```php
public static function is_network_activated() {
    if ( ! is_multisite() ) {
        return false;
    }

    if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return is_plugin_active_for_network( WP_ADMIN_HEALTH_PLUGIN_BASENAME );
}
```

**Analysis:**

- Correctly checks `is_multisite()` first
- Properly includes `plugin.php` if needed (may not be loaded in all contexts)
- Uses WordPress core `is_plugin_active_for_network()` function
- Static method allows calling without instance

### Activation Flow (in Plugin.php)

```php
public function activate( $network_wide = false ) {
    if ( is_multisite() && $network_wide ) {
        $sites = get_sites( array( 'number' => 999 ) );
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            Installer::install();
            restore_current_blog();
        }
    } else {
        Installer::install();
    }
}
```

**Analysis:**

- Correctly handles both network-wide and single-site activation
- Uses proper `switch_to_blog()`/`restore_current_blog()` pattern
- Creates per-site database tables on network activation

### New Site Handling (in Installer.php)

```php
public static function install_on_new_site( $blog_id ) {
    if ( ! is_multisite() ) {
        return;
    }

    if ( ! \WPAdminHealth\Multisite::is_network_activated() ) {
        return;
    }

    switch_to_blog( $blog_id );
    self::install();
    restore_current_blog();
}
```

**Analysis:**

- Automatically installs on newly created sites when network activated
- Properly checks network activation status before installing
- Uses correct blog switching pattern

## Site-Specific vs Network-Wide Settings

### Settings Storage Pattern

| Setting Type      | Storage Method                               | Scope         |
| ----------------- | -------------------------------------------- | ------------- |
| Network settings  | `get_site_option()` / `update_site_option()` | Network-wide  |
| Per-site settings | `get_option()` / `update_option()`           | Site-specific |
| Plugin version    | `get_option()`                               | Site-specific |

### Network Settings Implementation

```php
const NETWORK_SETTINGS_OPTION = 'wpha_network_settings';

public function get_network_settings() {
    $defaults = $this->get_default_network_settings();
    $settings = get_site_option( self::NETWORK_SETTINGS_OPTION, array() );
    return wp_parse_args( $settings, $defaults );
}
```

**Analysis:**

- Uses `get_site_option()` for network-wide settings storage
- Provides sensible defaults via `get_default_network_settings()`
- Uses `wp_parse_args()` to merge with defaults

### Default Network Settings

```php
public function get_default_network_settings() {
    return array(
        'enable_network_wide'      => false,
        'shared_scan_results'      => false,
        'network_scan_mode'        => 'current_site',
        'allow_site_override'      => true,
        'network_admin_only_scans' => true,
    );
}
```

**Analysis:**

- Conservative defaults (network-wide disabled by default)
- Allows site-level override by default
- Restricts network scans to super admins by default

### Site Override Logic

```php
public function can_site_override() {
    return (bool) $this->get_network_setting( 'allow_site_override', true );
}
```

**Analysis:**

- Provides mechanism for network admins to control site customization
- When enabled, individual sites can have their own settings

## Blog Switching Patterns

### Correct Pattern Usage

The codebase correctly implements the WordPress blog switching pattern:

```php
switch_to_blog( $site->blog_id );
// ... operations using switched blog context ...
restore_current_blog();
```

### Locations Using Blog Switching

1. **Plugin.php:activate()** (lines 277-281): Network activation
2. **Installer.php:install_on_new_site()** (lines 463-465): New site setup
3. **Installer.php:uninstall()** (lines 504-508): Network uninstallation
4. **templates/network/network-database.php** (lines 49-73): Per-site database stats

### Uninstall Pattern

```php
public static function uninstall() {
    if ( is_multisite() ) {
        if ( ! current_user_can( 'manage_network' ) ) {
            return;
        }

        $sites = get_sites( array( 'number' => 999 ) );
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            self::uninstall_single_site();
            restore_current_blog();
        }

        delete_site_option( \WPAdminHealth\Multisite::NETWORK_SETTINGS_OPTION );
    } else {
        self::uninstall_single_site();
    }
}
```

**Analysis:**

- Requires `manage_network` capability for network uninstall
- Cleans up each site's data individually
- Removes network-level options separately

## Security Implementation

### Capability Checks

| Operation                | Required Capability               |
| ------------------------ | --------------------------------- |
| View network admin pages | `manage_network_options`          |
| Save network settings    | `manage_network_options`          |
| Run network-wide scans   | `is_super_admin()` (configurable) |
| Network uninstall        | `manage_network`                  |

### Settings Sanitization

```php
public function sanitize_network_settings( $input ) {
    $sanitized = array();

    $sanitized['enable_network_wide']      = ! empty( $input['enable_network_wide'] );
    $sanitized['shared_scan_results']      = ! empty( $input['shared_scan_results'] );
    $sanitized['allow_site_override']      = ! empty( $input['allow_site_override'] );
    $sanitized['network_admin_only_scans'] = ! empty( $input['network_admin_only_scans'] );

    $valid_scan_modes = array( 'current_site', 'network_wide' );
    $sanitized['network_scan_mode'] = in_array( $input['network_scan_mode'] ?? '', $valid_scan_modes, true )
        ? $input['network_scan_mode']
        : 'current_site';

    return $sanitized;
}
```

**Analysis:**

- Boolean values coerced with `! empty()`
- Enum values validated against whitelist with strict comparison
- Falls back to safe defaults for invalid input

### Form Handling Security

```php
public function save_network_settings() {
    check_admin_referer( 'wpha_network_settings_update' );

    if ( ! current_user_can( 'manage_network_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions...', 'wp-admin-health-suite' ) );
    }

    // ... save logic ...
}
```

**Analysis:**

- Nonce verification with `check_admin_referer()`
- Capability check before any data modification
- Safe redirect after save with `wp_safe_redirect()`

## Service Provider Integration

### MultisiteServiceProvider

```php
class MultisiteServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->container->singleton(
            Multisite::class,
            function () {
                return new Multisite();
            }
        );
        $this->container->alias( 'multisite', Multisite::class );
    }

    public function boot(): void {
        if ( is_multisite() ) {
            $multisite = $this->container->get( Multisite::class );
            $multisite->init();
        }
    }
}
```

**Analysis:**

- Registered as singleton (one instance per request)
- Only boots/initializes when multisite is active
- Available via both class name and `'multisite'` alias

## Bug Fixed During Review

### Missing `get_multisite()` Method

**Issue:** The network templates call `$plugin->get_multisite()` but this method did not exist in the Plugin class.

**Fix Applied:** Added the missing method to `includes/Plugin.php`:

```php
/**
 * Get the Multisite handler.
 *
 * @since 1.2.0
 *
 * @return Multisite|null Multisite instance, or null if not available.
 */
public function get_multisite(): ?Multisite {
    if ( $this->container->has( Multisite::class ) ) {
        return $this->container->get( Multisite::class );
    }
    return null;
}
```

This follows the same pattern as `get_integration_manager()` and provides a convenience accessor for templates.

## Network Admin Menu Structure

```
Admin Health (Network)
├── Dashboard (network-dashboard.php)
├── Settings (network-settings.php)
└── Database Health (network-database.php)
```

All pages require `manage_network_options` capability.

## Recommendations

### Minor Improvements

1. **Site limit handling**: `get_network_sites()` limits to 999 sites. For very large networks, consider pagination:

    ```php
    public function get_network_sites( $args = array() ) {
        $defaults = array( 'number' => 100, 'offset' => 0 );
        return get_sites( wp_parse_args( $args, $defaults ) );
    }
    ```

2. **Transient caching**: Network-wide operations could benefit from caching:

    ```php
    $sites = get_site_transient( 'wpha_network_sites' );
    if ( false === $sites ) {
        $sites = get_sites( array( 'number' => 999 ) );
        set_site_transient( 'wpha_network_sites', $sites, HOUR_IN_SECONDS );
    }
    ```

3. **Test coverage**: No unit tests found for Multisite class. Consider adding:
    - Network activation detection tests
    - Settings storage tests
    - Capability check tests

### Optional Enhancements

1. **Bulk operations**: Add ability to run operations across multiple selected sites
2. **Site health scoring**: Aggregate health scores across network
3. **Network-wide scheduled tasks**: Currently tasks are per-site

## Multisite API Usage Checklist

| WordPress Function               | Usage                       | Status |
| -------------------------------- | --------------------------- | ------ |
| `is_multisite()`                 | Check multisite context     | PASS   |
| `is_network_admin()`             | Check network admin context | PASS   |
| `is_super_admin()`               | Check super admin status    | PASS   |
| `is_plugin_active_for_network()` | Check network activation    | PASS   |
| `get_sites()`                    | Retrieve sites list         | PASS   |
| `get_site_option()`              | Read network options        | PASS   |
| `update_site_option()`           | Write network options       | PASS   |
| `delete_site_option()`           | Delete network options      | PASS   |
| `switch_to_blog()`               | Switch to site context      | PASS   |
| `restore_current_blog()`         | Restore original context    | PASS   |
| `get_blog_option()`              | Read site option            | PASS   |
| `get_site_url()`                 | Get site URL                | PASS   |
| `get_admin_url()`                | Get site admin URL          | PASS   |
| `network_admin_url()`            | Get network admin URL       | PASS   |

## Conclusion

The Multisite support implementation is well-designed and follows WordPress best practices:

1. **Network activation**: Correctly detected and handled with per-site installation
2. **Settings scope**: Proper separation of network-wide and site-specific settings
3. **Blog switching**: Consistently uses `switch_to_blog()`/`restore_current_blog()` pattern
4. **Security**: Appropriate capability checks at all levels
5. **Service integration**: Clean DI container integration via service provider

The bug fix (adding `get_multisite()` method) ensures network templates function correctly.
