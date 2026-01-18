/**
 * Extension API for WP Admin Health Suite
 *
 * Provides a documented, minimal extension API for third-party integrations.
 * Extensions can register custom widgets, add dashboard cards, and hook into
 * plugin lifecycle events without directly accessing internal components.
 *
 * @package
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Extension Registry - Manages registered extensions and their widgets
 * @type {Object}
 */
const ExtensionRegistry = {
	/**
	 * Registered widgets by zone
	 * @type {Map<string, Array>}
	 */
	widgets: new Map(),

	/**
	 * Active React roots for cleanup
	 * @type {Map<string, Object>}
	 */
	roots: new Map(),

	/**
	 * Register a widget to be rendered in a specific zone
	 *
	 * @param {string}   zone                 - Zone identifier (e.g., 'dashboard-top', 'dashboard-bottom')
	 * @param {Object}   config               - Widget configuration
	 * @param {string}   config.id            - Unique widget identifier
	 * @param {Function} config.render        - Render function that returns React element or DOM manipulation
	 * @param {number}   [config.priority=10] - Render priority (lower = earlier)
	 */
	registerWidget(zone, config) {
		if (!config.id || !config.render) {
			console.error(
				'Extension API: Widget must have id and render function'
			);
			return;
		}

		if (!this.widgets.has(zone)) {
			this.widgets.set(zone, []);
		}

		const widgets = this.widgets.get(zone);

		// Check for duplicate
		if (widgets.some((w) => w.id === config.id)) {
			console.warn(
				`Extension API: Widget "${config.id}" already registered in zone "${zone}"`
			);
			return;
		}

		widgets.push({
			id: config.id,
			render: config.render,
			priority: config.priority || 10,
		});

		// Sort by priority
		widgets.sort((a, b) => a.priority - b.priority);
	},

	/**
	 * Unregister a widget
	 *
	 * @param {string} zone - Zone identifier
	 * @param {string} id   - Widget identifier
	 */
	unregisterWidget(zone, id) {
		if (!this.widgets.has(zone)) return;

		const widgets = this.widgets.get(zone);
		const index = widgets.findIndex((w) => w.id === id);

		if (index !== -1) {
			widgets.splice(index, 1);
		}

		// Cleanup any mounted root
		const rootKey = `${zone}:${id}`;
		if (this.roots.has(rootKey)) {
			const root = this.roots.get(rootKey);
			if (root && typeof root.unmount === 'function') {
				root.unmount();
			}
			this.roots.delete(rootKey);
		}
	},

	/**
	 * Get widgets for a zone
	 *
	 * @param {string} zone - Zone identifier
	 * @return {Array} Array of widget configs
	 */
	getWidgets(zone) {
		return this.widgets.get(zone) || [];
	},

	/**
	 * Render all widgets in a zone to a container
	 *
	 * @param {string}      zone      - Zone identifier
	 * @param {HTMLElement} container - Container element
	 */
	renderZone(zone, container) {
		const widgets = this.getWidgets(zone);

		widgets.forEach((widget) => {
			try {
				const widgetContainer = document.createElement('div');
				widgetContainer.className = 'wpha-extension-widget';
				widgetContainer.dataset.widgetId = widget.id;
				container.appendChild(widgetContainer);

				const result = widget.render(widgetContainer);

				// If render returns a React element, mount it
				if (React.isValidElement(result)) {
					const root = createRoot(widgetContainer);
					root.render(result);
					this.roots.set(`${zone}:${widget.id}`, root);
				}
			} catch (error) {
				console.error(
					`Extension API: Error rendering widget "${widget.id}":`,
					error
				);
			}
		});
	},

	/**
	 * Clear all registrations (for testing)
	 */
	clear() {
		// Unmount all roots
		this.roots.forEach((root) => {
			if (root && typeof root.unmount === 'function') {
				root.unmount();
			}
		});
		this.roots.clear();
		this.widgets.clear();
	},
};

/**
 * Extension Hooks - Event-based extension points
 * @type {Object}
 */
const ExtensionHooks = {
	/**
	 * Available hook names with descriptions
	 * @type {Object}
	 */
	HOOKS: {
		// Lifecycle hooks
		READY: 'ready', // Plugin is ready
		PAGE_INIT: 'pageInit', // Page-specific initialization

		// Dashboard hooks
		DASHBOARD_INIT: 'dashboardInit', // Dashboard loaded
		DASHBOARD_REFRESH: 'dashboardRefresh', // Dashboard data refreshed

		// Database hooks
		DATABASE_CLEANUP_START: 'databaseCleanupStart',
		DATABASE_CLEANUP_COMPLETE: 'databaseCleanupComplete',

		// Media hooks
		MEDIA_SCAN_START: 'mediaScanStart',
		MEDIA_SCAN_COMPLETE: 'mediaScanComplete',

		// Performance hooks
		PERFORMANCE_CHECK_START: 'performanceCheckStart',
		PERFORMANCE_CHECK_COMPLETE: 'performanceCheckComplete',
	},

	/**
	 * Filter callbacks registry
	 * @type {Map<string, Array>}
	 */
	filters: new Map(),

	/**
	 * Add a filter callback
	 *
	 * @param {string}   filterName    - Filter name
	 * @param {Function} callback      - Filter callback (receives value, returns modified value)
	 * @param {number}   [priority=10] - Priority (lower = earlier)
	 * @return {Function} Remove function
	 */
	addFilter(filterName, callback, priority = 10) {
		if (!this.filters.has(filterName)) {
			this.filters.set(filterName, []);
		}

		const filters = this.filters.get(filterName);
		const entry = { callback, priority };
		filters.push(entry);
		filters.sort((a, b) => a.priority - b.priority);

		// Return remove function
		return () => {
			const index = filters.indexOf(entry);
			if (index !== -1) {
				filters.splice(index, 1);
			}
		};
	},

	/**
	 * Apply filters to a value
	 *
	 * @param {string} filterName - Filter name
	 * @param {*}      value      - Value to filter
	 * @param {...*}   args       - Additional arguments passed to filters
	 * @return {*} Filtered value
	 */
	applyFilters(filterName, value, ...args) {
		const filters = this.filters.get(filterName) || [];

		return filters.reduce((currentValue, { callback }) => {
			try {
				return callback(currentValue, ...args);
			} catch (error) {
				console.error(
					`Extension API: Error in filter "${filterName}":`,
					error
				);
				return currentValue;
			}
		}, value);
	},

	/**
	 * Clear all filters (for testing)
	 */
	clearFilters() {
		this.filters.clear();
	},
};

/**
 * Public Extension API
 *
 * This is the only API exposed to extensions. It provides a stable,
 * documented interface that doesn't expose internal React components.
 *
 * @type {Object}
 */
const ExtensionAPI = {
	/**
	 * API Version
	 * @type {string}
	 */
	version: '1.0.0',

	/**
	 * Available hook names
	 * @type {Object}
	 */
	hooks: ExtensionHooks.HOOKS,

	/**
	 * Register a widget in a zone
	 *
	 * @example
	 * // Register a simple DOM widget
	 * WPAdminHealth.extensions.registerWidget('dashboard-bottom', {
	 *   id: 'my-custom-widget',
	 *   render: (container) => {
	 *     container.innerHTML = '<div class="my-widget">Hello!</div>';
	 *   },
	 *   priority: 20
	 * });
	 *
	 * @param {string} zone   - Zone identifier
	 * @param {Object} config - Widget configuration
	 */
	registerWidget(zone, config) {
		ExtensionRegistry.registerWidget(zone, config);
	},

	/**
	 * Unregister a widget
	 *
	 * @param {string} zone - Zone identifier
	 * @param {string} id   - Widget identifier
	 */
	unregisterWidget(zone, id) {
		ExtensionRegistry.unregisterWidget(zone, id);
	},

	/**
	 * Add a filter to modify data
	 *
	 * @example
	 * // Modify dashboard data before display
	 * WPAdminHealth.extensions.addFilter('dashboardData', (data) => {
	 *   data.customMetric = calculateCustomMetric();
	 *   return data;
	 * });
	 *
	 * @param {string}   filterName - Filter name
	 * @param {Function} callback   - Filter callback
	 * @param {number}   [priority] - Priority
	 * @return {Function} Remove function
	 */
	addFilter(filterName, callback, priority) {
		return ExtensionHooks.addFilter(filterName, callback, priority);
	},

	/**
	 * Subscribe to a hook/event
	 *
	 * @example
	 * // React to dashboard initialization
	 * WPAdminHealth.extensions.on('dashboardInit', (data) => {
	 *   console.log('Dashboard ready!', data);
	 * });
	 *
	 * @param {string}   hookName - Hook name (use ExtensionAPI.hooks constants)
	 * @param {Function} callback - Callback function
	 * @return {Function} Unsubscribe function
	 */
	on(hookName, callback) {
		// Delegate to the Events system in WPAdminHealth
		if (
			typeof window !== 'undefined' &&
			window.WPAdminHealth &&
			window.WPAdminHealth.Events
		) {
			return window.WPAdminHealth.Events.on(hookName, callback);
		}

		console.warn('Extension API: Event system not available yet');
		return () => {};
	},

	/**
	 * One-time hook subscription
	 *
	 * @param {string}   hookName - Hook name
	 * @param {Function} callback - Callback function
	 */
	once(hookName, callback) {
		if (
			typeof window !== 'undefined' &&
			window.WPAdminHealth &&
			window.WPAdminHealth.Events
		) {
			window.WPAdminHealth.Events.once(hookName, callback);
		}
	},
};

/**
 * Internal API for plugin use only (not exposed globally)
 * @type {Object}
 */
export const InternalExtensionAPI = {
	/**
	 * Render widgets in a zone
	 *
	 * @param {string}      zone      - Zone identifier
	 * @param {HTMLElement} container - Container element
	 */
	renderZone(zone, container) {
		ExtensionRegistry.renderZone(zone, container);
	},

	/**
	 * Apply filters
	 *
	 * @param {string} filterName - Filter name
	 * @param {*}      value      - Value to filter
	 * @param {...*}   args       - Additional arguments
	 * @return {*} Filtered value
	 */
	applyFilters(filterName, value, ...args) {
		return ExtensionHooks.applyFilters(filterName, value, ...args);
	},

	/**
	 * Clear all registrations (for testing)
	 */
	clear() {
		ExtensionRegistry.clear();
		ExtensionHooks.clearFilters();
	},
};

export default ExtensionAPI;
