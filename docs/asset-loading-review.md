# Asset Loading Review

Review of includes/Assets.php for proper script/style enqueueing, conditional loading by admin page, version strings for cache busting, and dependency management.

## Executive Summary

The Assets class is well-implemented and follows WordPress plugin development best practices. It uses proper conditional loading to only enqueue assets on plugin-specific admin pages, implements intelligent cache busting based on file modification times in development, and integrates well with the WordPress dependency system via webpack-generated asset manifests.

**Overall Assessment: Good** - Minor improvements recommended for CSS coverage and async/defer attribute handling.

---

## 1. Script/Style Enqueueing Patterns

### Styles Enqueueing

```php
wp_enqueue_style(
    'wpha-admin-css',
    $this->plugin_url . 'assets/css/admin.css',
    array(),
    $this->get_asset_version( 'assets/css/admin.css' ),
    'all'
);
```

### Analysis

#### Strengths

- **Proper hook usage**: Uses `admin_enqueue_scripts` hook with callback for admin assets
- **Correct function calls**: Uses `wp_enqueue_style()` and `wp_enqueue_script()` WordPress functions
- **Proper handle prefixing**: All handles use `wpha-` prefix to avoid conflicts
- **Media parameter**: Styles include `'all'` media parameter
- **Footer loading**: Scripts are loaded in footer with `true` as last parameter

#### Style Enqueueing Flow

| Step | Action                      | Location                              |
| ---- | --------------------------- | ------------------------------------- |
| 1    | Hook registered             | `admin_enqueue_scripts`               |
| 2    | Plugin page check           | `is_plugin_admin_page()`              |
| 3    | Base styles loaded          | `wpha-admin-css`                      |
| 4    | Page-specific styles loaded | Based on `$css_map` and `$screen->id` |

### Scripts Enqueueing

```php
wp_enqueue_script(
    'wpha-' . $bundle_name . '-js',
    $this->plugin_url . $bundle_path,
    $bundle_deps,
    $this->get_asset_version( $bundle_path ),
    true  // Load in footer
);
```

#### Strengths

- **Vendor bundle support**: Loads shared vendor bundle when available
- **Dynamic bundle selection**: Chooses appropriate bundle based on current screen
- **Localization**: Uses `wp_localize_script()` for passing PHP data to JavaScript
- **Translation support**: Uses `wp_set_script_translations()` for i18n

---

## 2. Conditional Loading by Admin Page

### Page Detection Mechanism

```php
private function is_plugin_admin_page() {
    $screen = get_current_screen();

    if ( ! $screen ) {
        return false;
    }

    return strpos( $screen->id, 'admin-health' ) !== false;
}
```

### Analysis

#### Strengths

- **Early bailout**: Assets not loaded on non-plugin pages, saving resources
- **Screen API usage**: Uses WordPress `get_current_screen()` API correctly
- **Null check**: Handles case where `$screen` may be null
- **Broad match**: Uses `strpos()` to catch all plugin pages including submenus

#### Page-Specific CSS Loading

```php
$css_map = array(
    'toplevel_page_admin-health'                  => 'dashboard.css',
    'admin-health_page_admin-health-database'     => 'database-health.css',
    'admin-health_page_admin-health-media'        => 'media-audit.css',
    'admin-health_page_admin-health-performance'  => 'performance.css',
);
```

#### Page-Specific Script Loading

```php
$bundle_map = array(
    'toplevel_page_admin-health'                  => 'dashboard',
    'admin-health_page_admin-health-database'     => 'database-health',
    'admin-health_page_admin-health-media'        => 'media-audit',
    'admin-health_page_admin-health-performance'  => 'performance',
    'admin-health_page_admin-health-settings'     => 'settings',
);
```

### Screen ID Mapping

| Admin Page      | Screen ID                                    | CSS File              | JS Bundle                   |
| --------------- | -------------------------------------------- | --------------------- | --------------------------- |
| Dashboard       | `toplevel_page_admin-health`                 | `dashboard.css`       | `dashboard.bundle.js`       |
| Database Health | `admin-health_page_admin-health-database`    | `database-health.css` | `database-health.bundle.js` |
| Media Audit     | `admin-health_page_admin-health-media`       | `media-audit.css`     | `media-audit.bundle.js`     |
| Performance     | `admin-health_page_admin-health-performance` | `performance.css`     | `performance.bundle.js`     |
| Settings        | `admin-health_page_admin-health-settings`    | N/A                   | `settings.bundle.js`        |

#### Issues Identified

| Issue                         | Severity | Description                                                           |
| ----------------------------- | -------- | --------------------------------------------------------------------- |
| Missing CSS for Settings page | Low      | Settings page has a JS bundle but no page-specific CSS in `$css_map`  |
| `tables.css` not loaded       | Low      | `assets/css/tables.css` exists but is not enqueued anywhere           |
| Script fallback to dashboard  | Info     | If screen ID not found in `$bundle_map`, defaults to dashboard bundle |

---

## 3. Version Strings and Cache Busting

### Implementation

```php
private function get_asset_version( $relative_path ) {
    $file_path = $this->plugin_path . $relative_path;

    // Use filemtime for cache busting in development.
    if ( file_exists( $file_path ) && ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
        return (string) filemtime( $file_path );
    }

    // Use plugin version in production.
    return $this->version;
}
```

### Analysis

#### Strengths

- **Development-aware**: Uses file modification time when `WP_DEBUG` is true
- **Production stable**: Uses plugin version for stable production caching
- **File existence check**: Verifies file exists before calling `filemtime()`
- **Type casting**: Returns string for consistent version format

#### Version Strategy Comparison

| Environment | Strategy                  | Cache Behavior                |
| ----------- | ------------------------- | ----------------------------- |
| Development | `filemtime()` (timestamp) | Bust cache on every file save |
| Production  | Plugin version (`1.0.0`)  | Stable until version bump     |

#### Webpack Integration

The webpack build generates an `assets.php` file with content hashes:

```php
<?php return array(
    'dashboard.bundle.js' => array(
        'dependencies' => array('react', 'react-dom', 'wp-polyfill'),
        'version' => '5f7f4affbea5d60b417a'  // Content hash
    ),
    // ...
);
```

**Important Observation**: The `get_asset_data()` method reads this file but **does not use the version hash** from it. Instead, `get_asset_version()` is called separately, which uses either `filemtime()` or plugin version.

#### Issues & Recommendations

| Issue                           | Severity | Recommendation                                                         |
| ------------------------------- | -------- | ---------------------------------------------------------------------- |
| Webpack version hashes not used | Medium   | Consider using the `version` from `assets.php` for production JS files |
| No filemtime cache              | Low      | Could cache filemtime results for multiple asset loads                 |
| Condition parentheses           | Info     | `WP_DEBUG` check has extra parentheses but is functionally correct     |

#### Recommended Enhancement

```php
private function get_asset_version( $relative_path ) {
    $file_path = $this->plugin_path . $relative_path;

    // For webpack-generated JS bundles, use content hash from assets.php.
    if ( strpos( $relative_path, 'assets/js/dist/' ) === 0 ) {
        $bundle_name = basename( $relative_path );
        $asset_data = $this->get_asset_data( str_replace( '.bundle.js', '', $bundle_name ) );
        if ( ! empty( $asset_data['version'] ) ) {
            return $asset_data['version'];
        }
    }

    // Development: use filemtime for immediate cache busting.
    if ( file_exists( $file_path ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return (string) filemtime( $file_path );
    }

    return $this->version;
}
```

---

## 4. Dependency Management

### WordPress Dependencies

```php
$bundle_deps = array_merge(
    array( 'react', 'react-dom', 'wp-i18n' ),
    $bundle_asset['dependencies']
);
```

### Analysis

#### Strengths

- **Core dependencies declared**: React, ReactDOM, and wp-i18n are explicitly required
- **Webpack integration**: Merges with auto-detected dependencies from `assets.php`
- **Vendor bundle chaining**: Adds vendor bundle as dependency when present
- **Style dependency chaining**: Page-specific CSS depends on base admin CSS

#### Dependency Chain

```
                    WordPress
                        │
            ┌───────────┼───────────┐
            │           │           │
          react    react-dom    wp-i18n
            │           │           │
            └───────────┼───────────┘
                        │
                   wp-polyfill (from assets.php)
                        │
                wpha-vendor-js (if exists)
                        │
              wpha-{bundle}-js
```

#### CSS Dependency Chain

```
                 wpha-admin-css
                        │
            ┌───────────┼───────────┐───────────┐
            │           │           │           │
    wpha-dashboard-css  │  wpha-database-health-css
                        │                       │
            wpha-media-audit-css   wpha-performance-css
```

#### Vendor Bundle Loading

```php
$vendor_path = 'assets/js/dist/vendor.bundle.js';
if ( file_exists( $this->plugin_path . $vendor_path ) ) {
    $vendor_asset = $this->get_asset_data( 'vendor' );
    wp_enqueue_script(
        'wpha-vendor-js',
        $this->plugin_url . $vendor_path,
        $vendor_asset['dependencies'],
        $this->get_asset_version( $vendor_path ),
        true
    );
}
```

**Current State**: The vendor bundle check exists but per the webpack review, `vendor.bundle.js` is not being generated because React/ReactDOM are externalized and remaining node_modules dependencies are too small to split.

#### Issues & Recommendations

| Issue                                     | Severity | Recommendation                                                                       |
| ----------------------------------------- | -------- | ------------------------------------------------------------------------------------ |
| Duplicate React/ReactDOM in deps          | Info     | Listed in both hardcoded array and may appear in assets.php - harmless but redundant |
| Vendor bundle may not exist               | Info     | Code handles this gracefully with file_exists check                                  |
| No jQuery dependency for scripts using it | Low      | If admin.js uses jQuery, should add 'jquery' to dependencies                         |

---

## 5. Script Localization

### Implementation

```php
private function localize_admin_script( $handle ) {
    wp_localize_script(
        $handle,
        'wpAdminHealthData',
        array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'rest_url'   => rest_url(),
            'plugin_url' => $this->plugin_url,
            'i18n'       => array(
                'loading'        => __( 'Loading…', 'wp-admin-health-suite' ),
                'error'          => __( 'An error occurred.', 'wp-admin-health-suite' ),
                // ... more strings
            ),
        )
    );
}
```

### Analysis

#### Strengths

- **REST API URL**: Provides `rest_url()` for modern API calls
- **AJAX URL**: Includes `admin_url( 'admin-ajax.php' )` for legacy AJAX
- **Internationalization**: All user-facing strings wrapped in `__()` function
- **Consistent object name**: Uses `wpAdminHealthData` across all bundles

#### Localized Data Structure

| Key          | Type   | Purpose                         |
| ------------ | ------ | ------------------------------- |
| `ajax_url`   | String | WordPress AJAX endpoint         |
| `rest_url`   | String | WordPress REST API base URL     |
| `plugin_url` | String | Plugin directory URL for assets |
| `i18n`       | Object | Translated UI strings           |

#### Issues & Recommendations

| Issue                     | Severity | Recommendation                                                          |
| ------------------------- | -------- | ----------------------------------------------------------------------- |
| No nonce for REST API     | Medium   | Consider adding `wp_create_nonce('wp_rest')` for REST authentication    |
| Missing user capabilities | Low      | Could include `current_user_can()` results for UI conditional rendering |
| No API namespace          | Low      | Could add REST API namespace constant                                   |

#### Recommended Enhancement

```php
wp_localize_script(
    $handle,
    'wpAdminHealthData',
    array(
        'ajax_url'   => admin_url( 'admin-ajax.php' ),
        'rest_url'   => rest_url(),
        'rest_nonce' => wp_create_nonce( 'wp_rest' ),
        'api_namespace' => 'wpha/v1',
        'plugin_url' => $this->plugin_url,
        // ...
    )
);
```

---

## 6. Script Translation Support

### Implementation

```php
wp_set_script_translations(
    'wpha-' . $bundle_name . '-js',
    'wp-admin-health-suite',
    $this->plugin_path . 'languages'
);
```

### Analysis

#### Strengths

- **WordPress standard**: Uses official `wp_set_script_translations()` function
- **Text domain**: Correctly uses plugin text domain `wp-admin-health-suite`
- **Custom path**: Points to plugin's languages directory

#### Requirements

For this to work, the following must be present:

1. JavaScript files using `wp.i18n.__()` or `@wordpress/i18n` imports
2. JED-formatted translation files (`wp-admin-health-suite-{locale}-{script-handle}.json`)
3. `wp-i18n` script dependency (correctly listed in dependencies)

---

## 7. Async/Defer Script Attributes

### Implementation

```php
public function add_async_defer_attributes( $tag, $handle, $src ) {
    // Only apply to our plugin scripts.
    if ( strpos( $handle, 'wpha-' ) !== 0 ) {
        return $tag;
    }

    // Don't defer vendor bundle or critical dependencies.
    $no_defer_scripts = array(
        'wpha-vendor-js',
    );

    if ( in_array( $handle, $no_defer_scripts, true ) ) {
        return $tag;
    }

    // Add defer attribute for non-critical scripts.
    if ( strpos( $tag, ' defer' ) === false ) {
        $tag = str_replace( ' src=', ' defer src=', $tag );
    }

    return $tag;
}
```

### Analysis

#### Strengths

- **Selective application**: Only affects plugin scripts (prefix check)
- **Critical script protection**: Vendor bundle excluded from defer
- **Duplicate prevention**: Checks if defer already present

#### Issues & Recommendations

| Issue                                       | Severity | Recommendation                                                |
| ------------------------------------------- | -------- | ------------------------------------------------------------- |
| Modern WordPress has `wp_script_add_data()` | Low      | Consider using `wp_script_add_data( $handle, 'defer', true )` |
| String manipulation fragile                 | Low      | Current approach works but may break with complex script tags |
| Async option not used                       | Info     | Consider offering async for truly independent scripts         |

#### Recommended WordPress Approach

```php
// In enqueue_admin_scripts(), after wp_enqueue_script():
wp_script_add_data( 'wpha-' . $bundle_name . '-js', 'defer', true );
```

This is more robust and uses WordPress's built-in mechanism (WordPress 6.3+).

---

## 8. Asset Manifest Integration

### Implementation

```php
private function get_asset_data( $bundle_name ) {
    $assets_file = $this->plugin_path . 'assets/js/dist/assets.php';

    if ( file_exists( $assets_file ) ) {
        $all_assets = require $assets_file;
        $key        = $bundle_name . '.bundle.js';

        if ( isset( $all_assets[ $key ] ) ) {
            return $all_assets[ $key ];
        }
    }

    // Return default asset data if file doesn't exist.
    return array(
        'dependencies' => array(),
        'version'      => $this->version,
    );
}
```

### Analysis

#### Strengths

- **Graceful degradation**: Returns sensible defaults if manifest missing
- **Key construction**: Properly constructs bundle filename key
- **File existence check**: Verifies manifest exists before loading

#### Manifest File Format

```php
<?php return array(
    'dashboard.bundle.js' => array(
        'dependencies' => array('react', 'react-dom', 'wp-polyfill'),
        'version' => '5f7f4affbea5d60b417a'
    ),
    // ...
);
```

#### Issues & Recommendations

| Issue                        | Severity | Recommendation                                                       |
| ---------------------------- | -------- | -------------------------------------------------------------------- |
| File included multiple times | Low      | `require` instead of `require_once` means file executes on each call |
| No caching of manifest data  | Low      | Consider caching parsed manifest in class property                   |

#### Recommended Caching

```php
private $asset_manifest = null;

private function get_asset_manifest() {
    if ( null === $this->asset_manifest ) {
        $assets_file = $this->plugin_path . 'assets/js/dist/assets.php';
        if ( file_exists( $assets_file ) ) {
            $this->asset_manifest = require $assets_file;
        } else {
            $this->asset_manifest = array();
        }
    }
    return $this->asset_manifest;
}
```

---

## 9. Hook Registration

### Implementation

```php
private function init_hooks() {
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    add_filter( 'script_loader_tag', array( $this, 'add_async_defer_attributes' ), 10, 3 );

    do_action( 'wpha_assets_init' );
}
```

### Analysis

#### Strengths

- **Correct hooks**: Uses `admin_enqueue_scripts` for admin assets
- **Custom action**: Provides `wpha_assets_init` hook for extensibility
- **Priority**: Uses default priority (10) which is appropriate

#### Hooks Used

| Hook                    | Type          | Priority | Purpose               |
| ----------------------- | ------------- | -------- | --------------------- |
| `admin_enqueue_scripts` | Action        | 10       | Main asset enqueueing |
| `script_loader_tag`     | Filter        | 10       | Add defer attributes  |
| `wpha_assets_init`      | Custom Action | N/A      | Extensibility hook    |

---

## 10. Summary of Recommendations

### High Priority

1. **Add REST nonce**: Include `wp_create_nonce('wp_rest')` in localized script data for authenticated REST API calls

### Medium Priority

2. **Use webpack version hashes**: For JS bundles in production, use the content hash from `assets.php` instead of plugin version
3. **Add settings.css to CSS map**: Create or map a CSS file for the Settings page

### Low Priority

4. **Cache asset manifest**: Avoid re-requiring `assets.php` on each bundle load
5. **Use `wp_script_add_data()`**: For defer attribute instead of string manipulation
6. **Load tables.css**: Either enqueue it where needed or remove unused file
7. **Require manifest once**: Change `require` to `require_once` for `assets.php`

---

## 11. File Coverage Analysis

### CSS Files

| File                  | Enqueued | Pages                   |
| --------------------- | -------- | ----------------------- |
| `admin.css`           | ✅       | All plugin pages (base) |
| `dashboard.css`       | ✅       | Dashboard               |
| `database-health.css` | ✅       | Database Health         |
| `media-audit.css`     | ✅       | Media Audit             |
| `performance.css`     | ✅       | Performance             |
| `tables.css`          | ❌       | Not enqueued            |

### JS Bundles

| Bundle                      | Enqueued | Pages                     |
| --------------------------- | -------- | ------------------------- |
| `dashboard.bundle.js`       | ✅       | Dashboard (default)       |
| `database-health.bundle.js` | ✅       | Database Health           |
| `media-audit.bundle.js`     | ✅       | Media Audit               |
| `performance.bundle.js`     | ✅       | Performance               |
| `settings.bundle.js`        | ✅       | Settings                  |
| `vendor.bundle.js`          | ⚠️       | Conditionally (if exists) |

---

## 12. Security Considerations

### Current State

| Aspect            | Status | Notes                                                             |
| ----------------- | ------ | ----------------------------------------------------------------- |
| URL escaping      | ✅     | URLs passed directly to WordPress functions which handle escaping |
| Data sanitization | ✅     | `wp_localize_script()` handles JSON encoding                      |
| Nonce             | ⚠️     | REST nonce not included (should be added)                         |
| Capability check  | N/A    | Done in admin page rendering, not assets                          |

### Recommendations

1. Add `rest_nonce` to localized data for authenticated REST calls
2. Consider adding `current_user_can()` checks for conditional UI features

---

## Appendix: Assets Class Structure

```
Assets
├── Properties
│   ├── $version (string)
│   ├── $plugin_url (string)
│   └── $plugin_path (string)
│
├── Constructor
│   └── __construct($version, $plugin_url)
│
├── Hooks
│   └── init_hooks()
│
├── Page Detection
│   └── is_plugin_admin_page()
│
├── Style Management
│   └── enqueue_admin_styles()
│
├── Script Management
│   ├── enqueue_admin_scripts()
│   ├── localize_admin_script($handle)
│   └── add_async_defer_attributes($tag, $handle, $src)
│
└── Asset Utilities
    ├── get_asset_data($bundle_name)
    └── get_asset_version($relative_path)
```

---

## Appendix: Configuration Checklist

| Feature               | Status | Notes                            |
| --------------------- | ------ | -------------------------------- |
| Conditional loading   | ✅     | Only loads on plugin pages       |
| Page-specific assets  | ✅     | Correct bundles per page         |
| Cache busting         | ✅     | filemtime (dev) / version (prod) |
| Dependency management | ✅     | Integrates with webpack manifest |
| Localization          | ✅     | wp_localize_script used          |
| Translation support   | ✅     | wp_set_script_translations used  |
| Defer loading         | ✅     | Non-vendor scripts deferred      |
| Extensibility         | ✅     | wpha_assets_init hook provided   |
| Footer scripts        | ✅     | Scripts loaded in footer         |
| Style dependencies    | ✅     | Page CSS depends on admin CSS    |

---

## Appendix: File References

| File                                              | Lines | Purpose                                      |
| ------------------------------------------------- | ----- | -------------------------------------------- |
| `includes/Assets.php`                             | 343   | Main asset management class                  |
| `includes/Providers/BootstrapServiceProvider.php` | 133   | Registers Assets in DI container             |
| `webpack.config.js`                               | 125   | Build configuration                          |
| `assets/js/dist/assets.php`                       | 2     | Generated dependency manifest                |
| `admin/Admin.php`                                 | 214   | Admin menu registration (defines screen IDs) |
