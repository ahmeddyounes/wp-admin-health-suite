# JavaScript API Reference

This document describes the stable JavaScript API surface for WP Admin Health Suite. Use these documented interfaces for integrations and extensions.

## Global Namespace

All plugin JavaScript APIs are exposed under the `window.WPAdminHealth` namespace:

```javascript
window.WPAdminHealth = {
	api, // REST API client (canonical)
	ApiError, // API error class
	extensions, // Extension API for third-party integrations
	Events, // Custom event system
	// Internal utilities (do not depend on):
	// API, Toast, Modal, Progress, Storage, ErrorHandler, utils
};
```

## WPAdminHealth.api

The canonical REST API client. This is the single supported way to make REST API calls.

### Overview

```javascript
import apiClient from 'assets/js/utils/api.js';
// or use the global:
const api = window.WPAdminHealth.api;
```

### Methods

#### `api.get(endpoint, options)`

Make a GET request.

```javascript
// Fetch dashboard data
const data = await WPAdminHealth.api.get('dashboard');

// With query parameters
const stats = await WPAdminHealth.api.get('database/stats', {
	params: { include_details: true },
});
```

#### `api.post(endpoint, data, options)`

Make a POST request.

```javascript
// Trigger a database cleanup
const result = await WPAdminHealth.api.post('database/cleanup', {
	types: ['revisions', 'transients'],
});
```

#### `api.put(endpoint, data, options)`

Make a PUT request.

```javascript
// Update settings
await WPAdminHealth.api.put('settings', {
	enable_scheduling: true,
});
```

#### `api.patch(endpoint, data, options)`

Make a PATCH request.

```javascript
// Partial update
await WPAdminHealth.api.patch('settings', {
	debug_mode: false,
});
```

#### `api.delete(endpoint, data, options)`

Make a DELETE request.

```javascript
// Delete specific items
await WPAdminHealth.api.delete('media/duplicates', {
	ids: [123, 456],
});
```

### Request Options

All methods accept an `options` object:

| Option              | Type          | Default        | Description                             |
| ------------------- | ------------- | -------------- | --------------------------------------- |
| `params`            | `Object`      | `{}`           | Query parameters (GET requests)         |
| `cache`             | `boolean`     | `true` for GET | Enable response caching                 |
| `cacheTTL`          | `number`      | `60000`        | Cache time-to-live in milliseconds      |
| `skipDeduplication` | `boolean`     | `false`        | Skip request deduplication              |
| `signal`            | `AbortSignal` | -              | AbortController signal for cancellation |

### Example: Full Request

```javascript
const controller = new AbortController();

try {
	const data = await WPAdminHealth.api.get('performance/metrics', {
		params: { period: '7d' },
		cache: true,
		cacheTTL: 30000,
		signal: controller.signal,
	});
	console.log('Metrics:', data);
} catch (error) {
	if (error instanceof WPAdminHealth.ApiError) {
		console.error('API Error:', error.getUserMessage());
	}
}

// Cancel request if needed
controller.abort();
```

### Cache Management

```javascript
// Clear all cached responses
WPAdminHealth.api.clearCache();

// Clear cache for specific patterns
WPAdminHealth.api.clearCachePattern(/dashboard/);

// Invalidate specific endpoint cache
WPAdminHealth.api.invalidateCache('database/stats');
```

## WPAdminHealth.ApiError

Error class for structured API error handling.

### Properties

| Property     | Type     | Description                             |
| ------------ | -------- | --------------------------------------- |
| `message`    | `string` | Error message                           |
| `status`     | `number` | HTTP status code (0 for network errors) |
| `code`       | `string` | Error code (e.g., `rest_forbidden`)     |
| `data`       | `*`      | Additional error data                   |
| `innerError` | `Error`  | Original error if wrapped               |

### Methods

```javascript
try {
	await WPAdminHealth.api.get('protected-endpoint');
} catch (error) {
	if (error instanceof WPAdminHealth.ApiError) {
		// Check error type
		if (error.isNetworkError()) {
			console.log('Network issue');
		} else if (error.isAuthError()) {
			console.log('Permission denied');
		} else if (error.isValidationError()) {
			console.log('Invalid input');
		} else if (error.isServerError()) {
			console.log('Server error');
		} else if (error.isTimeoutError()) {
			console.log('Request timed out');
		}

		// Get user-friendly message
		const message = error.getUserMessage();

		// Check if request should be retried
		if (error.shouldRetry()) {
			// Retry logic...
		}
	}
}
```

## WPAdminHealth.Events

Custom event system for plugin communication.

### Subscribing to Events

```javascript
// Subscribe to an event
const unsubscribe = WPAdminHealth.Events.on('dashboardInit', (data) => {
	console.log('Dashboard initialized:', data);
});

// One-time subscription
WPAdminHealth.Events.once('ready', (data) => {
	console.log('Plugin ready, version:', data.version);
});

// Unsubscribe
unsubscribe();
```

### Triggering Events

```javascript
// Trigger custom event
WPAdminHealth.Events.trigger('myCustomEvent', {
	someData: 'value',
});
```

### Built-in Events

| Event           | Data                 | Description           |
| --------------- | -------------------- | --------------------- |
| `ready`         | `{ version }`        | Plugin is initialized |
| `dashboardInit` | `{ extensions }`     | Dashboard page loaded |
| `error`         | `{ error, message }` | Global error occurred |

## WPAdminHealth.extensions

Extension API for third-party integrations. See [Extension Development](#extension-development) below.

### API Version

```javascript
console.log(WPAdminHealth.extensions.version); // "1.0.0"
```

### Available Hooks

```javascript
const hooks = WPAdminHealth.extensions.hooks;
// {
//   READY: 'ready',
//   PAGE_INIT: 'pageInit',
//   DASHBOARD_INIT: 'dashboardInit',
//   DASHBOARD_REFRESH: 'dashboardRefresh',
//   DATABASE_CLEANUP_START: 'databaseCleanupStart',
//   DATABASE_CLEANUP_COMPLETE: 'databaseCleanupComplete',
//   MEDIA_SCAN_START: 'mediaScanStart',
//   MEDIA_SCAN_COMPLETE: 'mediaScanComplete',
//   PERFORMANCE_CHECK_START: 'performanceCheckStart',
//   PERFORMANCE_CHECK_COMPLETE: 'performanceCheckComplete'
// }
```

### Registering Widgets

```javascript
// Register a widget in a dashboard zone
WPAdminHealth.extensions.registerWidget('dashboard-bottom', {
	id: 'my-custom-widget',
	render: (container) => {
		container.innerHTML = `
      <div class="wpha-card">
        <h3>My Custom Widget</h3>
        <p>Custom content here</p>
      </div>
    `;
	},
	priority: 20, // Lower = renders earlier
});

// Unregister widget
WPAdminHealth.extensions.unregisterWidget(
	'dashboard-bottom',
	'my-custom-widget'
);
```

### Available Zones

| Zone               | Location                 |
| ------------------ | ------------------------ |
| `dashboard-top`    | Top of dashboard page    |
| `dashboard-bottom` | Bottom of dashboard page |

### Adding Filters

```javascript
// Modify data before display
const removeFilter = WPAdminHealth.extensions.addFilter(
	'dashboardData',
	(data) => {
		data.customMetric = calculateCustomMetric();
		return data;
	},
	10 // priority
);

// Remove filter
removeFilter();
```

### Subscribing to Hooks

```javascript
// React to events
WPAdminHealth.extensions.on('dashboardInit', (data) => {
	console.log('Dashboard ready!', data);
});

// One-time subscription
WPAdminHealth.extensions.once('ready', () => {
	console.log('Plugin initialized');
});
```

## Extension Development

### Complete Example

```javascript
/**
 * My Custom Extension for WP Admin Health Suite
 */
(function () {
	'use strict';

	// Wait for plugin to be ready
	if (typeof WPAdminHealth === 'undefined') {
		console.warn('WP Admin Health Suite not loaded');
		return;
	}

	// Get extension API reference
	const ext = WPAdminHealth.extensions;

	// Register a dashboard widget
	ext.registerWidget('dashboard-bottom', {
		id: 'my-extension-widget',
		priority: 50,
		render: (container) => {
			container.innerHTML = `
        <div class="wpha-card my-extension">
          <div class="wpha-card-header">
            <h3>My Extension</h3>
          </div>
          <div class="wpha-card-body">
            <p>Loading data...</p>
          </div>
        </div>
      `;

			// Fetch data using the API client
			WPAdminHealth.api
				.get('my-extension/data')
				.then((data) => {
					container.querySelector('.wpha-card-body').innerHTML = `
            <p>Data: ${JSON.stringify(data)}</p>
          `;
				})
				.catch((error) => {
					container.querySelector('.wpha-card-body').innerHTML = `
            <p class="error">Error: ${error.getUserMessage()}</p>
          `;
				});
		},
	});

	// Add a filter to modify dashboard data
	ext.addFilter('dashboardData', (data) => {
		data.myExtensionActive = true;
		return data;
	});

	// Subscribe to events
	ext.on(ext.hooks.DASHBOARD_REFRESH, (data) => {
		console.log('Dashboard refreshed, updating widget...');
	});

	console.log('My Extension loaded, API version:', ext.version);
})();
```

### Best Practices

1. **Always check for `WPAdminHealth`** before using the API
2. **Use unique widget IDs** prefixed with your extension name
3. **Handle errors gracefully** using `ApiError` methods
4. **Clean up subscriptions** when your extension is unloaded
5. **Respect priority order** for widgets (default is 10)
6. **Use the canonical `api`** not the legacy `API` property

### TypeScript Support

Type definitions are not yet available. For TypeScript projects, you can declare the global:

```typescript
declare global {
	interface Window {
		WPAdminHealth: {
			api: {
				get(endpoint: string, options?: RequestOptions): Promise<any>;
				post(
					endpoint: string,
					data?: object,
					options?: RequestOptions
				): Promise<any>;
				put(
					endpoint: string,
					data?: object,
					options?: RequestOptions
				): Promise<any>;
				patch(
					endpoint: string,
					data?: object,
					options?: RequestOptions
				): Promise<any>;
				delete(
					endpoint: string,
					data?: object,
					options?: RequestOptions
				): Promise<any>;
				clearCache(): void;
				clearCachePattern(pattern: string | RegExp): void;
				invalidateCache(endpoint: string, params?: object): void;
			};
			ApiError: typeof ApiError;
			Events: EventSystem;
			extensions: ExtensionAPI;
		};
	}
}
```

## Deprecated APIs

The following are internal and should not be used in extensions:

- `WPAdminHealth.API` (uppercase) - Use `WPAdminHealth.api` instead
- `WPAdminHealth.Toast` - Internal notification system
- `WPAdminHealth.Modal` - Internal modal system
- `WPAdminHealth.Progress` - Internal progress tracking
- `WPAdminHealth.Storage` - Internal localStorage wrapper
- `WPAdminHealth.ErrorHandler` - Internal error handling
- `WPAdminHealth.utils` - Internal utility functions

These may change or be removed in future versions without notice.

## Localized Data

The plugin provides localized data via `window.wpAdminHealthData`:

```javascript
const data = window.wpAdminHealthData;
// {
//   version: '1.0.0',
//   ajax_url: '/wp-admin/admin-ajax.php',
//   rest_url: '/wp-json/',
//   rest_root: '/wp-json/',
//   rest_nonce: 'abc123...',
//   rest_namespace: 'wpha/v1',
//   screen_id: 'toplevel_page_admin-health',
//   plugin_url: '/wp-content/plugins/wp-admin-health-suite/',
//   features: {
//     restApiEnabled: true,
//     debugMode: false,
//     // ...
//   },
//   i18n: {
//     loading: 'Loading...',
//     error: 'An error occurred.',
//     // ...
//   }
// }
```

## Migration Guide

### From Direct `wp.apiFetch` Usage

Before:

```javascript
wp.apiFetch({
	path: '/wpha/v1/dashboard',
	method: 'GET',
}).then((data) => {
	// handle data
});
```

After:

```javascript
WPAdminHealth.api
	.get('dashboard')
	.then((data) => {
		// handle data
	})
	.catch((error) => {
		console.error(error.getUserMessage());
	});
```

### Benefits of Using the API Client

1. **Automatic namespace handling** - No need to prefix `/wpha/v1/`
2. **Built-in caching** - GET requests are cached by default
3. **Request deduplication** - Prevents duplicate concurrent requests
4. **Automatic retries** - Network errors are retried with exponential backoff
5. **Structured errors** - `ApiError` class with helpful methods
6. **Consistent interface** - Same API across all plugin features
