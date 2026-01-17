/**
 * Dashboard Entry Point
 *
 * Main entry point for the dashboard admin page.
 * Imports and initializes React components and page-specific functionality.
 *
 * @package
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

// Import React components
import ErrorBoundary from '../components/ErrorBoundary.jsx';
import HealthScoreCircle from '../components/HealthScoreCircle.jsx';
import MetricCard from '../components/MetricCard.jsx';
import ActivityTimeline from '../components/ActivityTimeline.jsx';
import QuickActions from '../components/QuickActions.jsx';
import Recommendations from '../components/Recommendations.jsx';

// Import core admin utilities
import '../admin.js';
import '../charts.js';

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
 * Helper function to safely mount a React component to a DOM element
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
 * Unmount a React component from a DOM element
 *
 * @param {Object} root - React root instance returned from mountComponent
 */
const unmountComponent = (root) => {
	if (root && typeof root.unmount === 'function') {
		root.unmount();
	}
};

// Make components globally available for use in WordPress templates
window.WPAdminHealthComponents = {
	// Components
	ErrorBoundary,
	HealthScoreCircle,
	MetricCard,
	ActivityTimeline,
	QuickActions,
	Recommendations,

	// React exports
	React,
	createRoot,

	// Utility functions
	withErrorBoundary,
	mountComponent,
	unmountComponent,
};

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	// Trigger initialization event for extensions
	if (window.WPAdminHealth && window.WPAdminHealth.Events) {
		window.WPAdminHealth.Events.trigger('dashboardInit', {
			components: window.WPAdminHealthComponents,
		});
	}
});
