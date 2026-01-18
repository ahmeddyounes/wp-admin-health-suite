# Frontend Runtime Configuration

This document describes the `wpAdminHealthData` global object that is made available to JavaScript on plugin admin pages.

## Overview

The `wpAdminHealthData` object provides runtime configuration for the plugin's frontend code. It is localized via `wp_localize_script()` and contains URLs, nonces, feature flags, and internationalization strings needed by the UI.

## Contract

The object is always available on plugin admin pages at `window.wpAdminHealthData`. The structure is guaranteed stable within a major version.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `version` | `string` | Plugin version (e.g., `"1.5.0"`) |
| `ajax_url` | `string` | WordPress AJAX endpoint URL |
| `rest_url` | `string` | REST API base URL (alias of `rest_root`) |
| `rest_root` | `string` | REST API root URL (e.g., `"/wp-json/"`) |
| `rest_nonce` | `string` | REST API nonce for `X-WP-Nonce` header |
| `rest_namespace` | `string` | Plugin REST namespace (always `"wpha/v1"`) |
| `screen_id` | `string` | Current WordPress admin screen ID |
| `plugin_url` | `string` | Plugin directory URL |
| `features` | `object` | Feature flags (see below) |
| `i18n` | `object` | Internationalized UI strings |

### Feature Flags

The `features` object contains boolean flags indicating which plugin features are enabled:

| Flag | Type | Default | Description |
|------|------|---------|-------------|
| `restApiEnabled` | `boolean` | `true` | REST API endpoints are enabled |
| `debugMode` | `boolean` | `false` | Debug mode is active |
| `safeMode` | `boolean` | `false` | Safe mode prevents destructive operations |
| `dashboardWidget` | `boolean` | `true` | Dashboard widget is enabled |
| `adminBarMenu` | `boolean` | `true` | Admin bar menu is visible |
| `loggingEnabled` | `boolean` | `false` | System logging is enabled |
| `schedulingEnabled` | `boolean` | `true` | Background scheduling is enabled |
| `aiRecommendations` | `boolean` | `false` | AI recommendations feature is enabled |
| `actionSchedulerAvailable` | `boolean` | varies | Action Scheduler plugin is available |

### Internationalized Strings (`i18n`)

Common UI strings that are translated via WordPress i18n functions:

| Key | Description |
|-----|-------------|
| `loading` | Loading indicator text |
| `error` | Generic error message |
| `success` | Generic success message |
| `confirm` | Confirmation prompt |
| `save` | Save button label |
| `cancel` | Cancel button label |
| `delete` | Delete button label |
| `refresh` | Refresh button label |
| `no_data` | Empty state message |
| `processing` | Processing indicator text |
| `analyze` | Analyze action label |
| `clean` | Clean action label |
| `revisions` | Post revisions label |
| `transients` | Transients label |
| `spam` | Spam comments label |
| `trash` | Trash label |
| `orphaned` | Orphaned data label |
| `confirmCleanup` | Cleanup confirmation message |

## Usage Examples

### JavaScript

```javascript
// Access REST API configuration
const apiUrl = `${wpAdminHealthData.rest_root}${wpAdminHealthData.rest_namespace}/dashboard/stats`;

// Make authenticated API request
fetch(apiUrl, {
    headers: {
        'X-WP-Nonce': wpAdminHealthData.rest_nonce,
    },
});

// Check feature flags before rendering UI
if (wpAdminHealthData.features.safeMode) {
    console.log('Safe mode is enabled - destructive operations will preview only');
}

// Use internationalized strings
const loadingText = wpAdminHealthData.i18n.loading;

// Check current screen
if (wpAdminHealthData.screen_id === 'toplevel_page_admin-health') {
    // Dashboard-specific code
}
```

### Using the API Client

The plugin provides an API client that automatically uses these configuration values:

```javascript
import apiClient from './utils/api';

// The client reads rest_namespace from wpAdminHealthData
const stats = await apiClient.get('dashboard/stats');
```

See the [API client documentation](./rest-api.md) for more details.

## TypeScript Definition

For TypeScript projects, you can use this type definition:

```typescript
interface WpAdminHealthData {
    version: string;
    ajax_url: string;
    rest_url: string;
    rest_root: string;
    rest_nonce: string;
    rest_namespace: string;
    screen_id: string;
    plugin_url: string;
    features: {
        restApiEnabled: boolean;
        debugMode: boolean;
        safeMode: boolean;
        dashboardWidget: boolean;
        adminBarMenu: boolean;
        loggingEnabled: boolean;
        schedulingEnabled: boolean;
        aiRecommendations: boolean;
        actionSchedulerAvailable: boolean;
    };
    i18n: {
        loading: string;
        error: string;
        success: string;
        confirm: string;
        save: string;
        cancel: string;
        delete: string;
        refresh: string;
        no_data: string;
        processing: string;
        analyze: string;
        clean: string;
        revisions: string;
        transients: string;
        spam: string;
        trash: string;
        orphaned: string;
        confirmCleanup: string;
    };
}

declare global {
    interface Window {
        wpAdminHealthData: WpAdminHealthData;
    }
}
```

## Availability

The `wpAdminHealthData` object is only available on plugin admin pages (pages where the screen ID contains `"admin-health"`). It is not loaded on other WordPress admin pages or the frontend.

## Backward Compatibility

### Stability Promise

- Existing properties will not be removed within a major version
- New properties may be added at any time
- Property types will not change
- The `rest_namespace` value (`"wpha/v1"`) is stable

### Deprecated Properties

Currently there are no deprecated properties. When properties are deprecated:

1. They will continue to work for at least one minor release
2. Deprecation will be documented here
3. Console warnings may be added in debug mode

## Related Documentation

- [REST API Documentation](./rest-api.md)
- [Hooks Reference](./hooks.md)
- [Container & Dependency Injection](./container.md)
