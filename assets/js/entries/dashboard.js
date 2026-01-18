/**
 * Dashboard Entry Point
 *
 * Main entry point for the dashboard admin page.
 * Imports and initializes React components and page-specific functionality.
 *
 * Components are mounted internally - extensions should use the Extension API
 * rather than directly accessing components.
 *
 * @package
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

// Import React components (internal - not exposed globally)
import ErrorBoundary from '../components/ErrorBoundary.jsx';
import HealthScoreCircle from '../components/HealthScoreCircle.jsx';
import MetricCard from '../components/MetricCard.jsx';
import ActivityTimeline from '../components/ActivityTimeline.jsx';
import QuickActions from '../components/QuickActions.jsx';
import Recommendations from '../components/Recommendations.jsx';

// Import centralized API client
import apiClient, { ApiError } from '../utils/api.js';

// Import extension API
import ExtensionAPI, { InternalExtensionAPI } from '../utils/extension-api.js';

// Import core admin utilities
import '../admin.js';
import '../charts.js';

// Expose API client globally for IIFE-based scripts
window.WPAdminHealth = window.WPAdminHealth || {};
window.WPAdminHealth.api = apiClient;
window.WPAdminHealth.ApiError = ApiError;

// Expose extension API (the only public interface for extensions)
window.WPAdminHealth.extensions = ExtensionAPI;

/**
 * Internal component registry (not exposed globally)
 * @type {Object}
 */
const Components = {
	ErrorBoundary,
	HealthScoreCircle,
	MetricCard,
	ActivityTimeline,
	QuickActions,
	Recommendations,
};

/**
 * Active React roots for cleanup
 * @type {Map<HTMLElement, Object>}
 */
const activeRoots = new Map();

/**
 * Wraps a component with an ErrorBoundary for safe rendering
 *
 * @param {React.Component} Component     - The component to wrap
 * @param {string}          componentName - Name of the component for error messages
 * @return {React.Component} Wrapped component
 */
const withErrorBoundary = (Component, componentName) => {
	const WrappedComponent = (props) => (
		<ErrorBoundary componentName={componentName}>
			<Component {...props} />
		</ErrorBoundary>
	);
	WrappedComponent.displayName = `WithErrorBoundary(${componentName})`;
	return WrappedComponent;
};

/**
 * Internal helper function to safely mount a React component to a DOM element
 *
 * @param {string|HTMLElement} container     - Container element or selector
 * @param {React.Component}    Component     - React component to render
 * @param {Object}             props         - Props to pass to the component
 * @param {string}             componentName - Name of the component for error messages
 * @return {Object|null} React root instance or null if mounting failed
 */
const mountComponent = (
	container,
	Component,
	props = {},
	componentName = 'Component'
) => {
	try {
		const element =
			typeof container === 'string'
				? document.querySelector(container)
				: container;

		if (!element) {
			console.warn(
				`WP Admin Health: Container element not found for ${componentName}`
			);
			return null;
		}

		const root = createRoot(element);
		root.render(
			<ErrorBoundary componentName={componentName}>
				<Component {...props} />
			</ErrorBoundary>
		);

		activeRoots.set(element, root);
		return root;
	} catch (error) {
		console.error(
			`WP Admin Health: Failed to mount ${componentName}:`,
			error
		);
		return null;
	}
};

/**
 * Internal helper to unmount a React component from a DOM element
 *
 * @param {Object} root - React root instance returned from mountComponent
 */
const unmountComponent = (root) => {
	if (root && typeof root.unmount === 'function') {
		root.unmount();
	}
};

/**
 * Mount dashboard components to their containers
 */
const initializeDashboardComponents = () => {
	// Mount health score if container exists
	const healthScoreContainer = document.getElementById(
		'wpha-health-score-container'
	);
	if (healthScoreContainer) {
		mountComponent(
			healthScoreContainer,
			HealthScoreCircle,
			{},
			'HealthScoreCircle'
		);
	}

	// Render extension widgets in dashboard zones
	const topZone = document.getElementById('wpha-extension-zone-top');
	if (topZone) {
		InternalExtensionAPI.renderZone('dashboard-top', topZone);
	}

	const bottomZone = document.getElementById('wpha-extension-zone-bottom');
	if (bottomZone) {
		InternalExtensionAPI.renderZone('dashboard-bottom', bottomZone);
	}
};

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	initializeDashboardComponents();

	// Trigger initialization event for extensions
	if (window.WPAdminHealth && window.WPAdminHealth.Events) {
		window.WPAdminHealth.Events.trigger('dashboardInit', {
			// Provide extension API reference for convenience
			extensions: window.WPAdminHealth.extensions,
		});
	}
});

// Export internal utilities for testing only
export const __testing__ = {
	Components,
	withErrorBoundary,
	mountComponent,
	unmountComponent,
	activeRoots,
};
