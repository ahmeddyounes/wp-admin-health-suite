/**
 * Tests for Dashboard Entry Point
 *
 * @package
 */

import React from 'react';
import { render, screen, cleanup, act } from '@testing-library/react';

// Mock the admin.js and charts.js imports
jest.mock('../admin.js', () => ({}));
jest.mock('../charts.js', () => ({}));

// Import after mocking
import { __testing__ } from './dashboard.js';
import ExtensionAPI from '../utils/extension-api.js';
import apiClient, { ApiError } from '../utils/api.js';

describe('Dashboard Entry Point', () => {
	beforeEach(() => {
		// Reset mocks
		jest.clearAllMocks();

		// Setup WPAdminHealth namespace with mocked Events
		// The dashboard.js module sets extensions, api, and ApiError when imported
		window.WPAdminHealth = window.WPAdminHealth || {};
		window.WPAdminHealth.Events = {
			trigger: jest.fn(),
			on: jest.fn(() => jest.fn()),
			once: jest.fn(),
		};
		// Ensure extensions is set (may have been cleared by previous test)
		window.WPAdminHealth.extensions = ExtensionAPI;
		// Ensure API client is set
		window.WPAdminHealth.api = apiClient;
		window.WPAdminHealth.ApiError = ApiError;
	});

	afterEach(() => {
		cleanup();
	});

	describe('Extension API', () => {
		it('exposes extension API on window.WPAdminHealth.extensions', () => {
			expect(window.WPAdminHealth.extensions).toBeDefined();
		});

		it('provides version information', () => {
			expect(window.WPAdminHealth.extensions.version).toBeDefined();
			expect(typeof window.WPAdminHealth.extensions.version).toBe(
				'string'
			);
		});

		it('provides hook constants', () => {
			expect(window.WPAdminHealth.extensions.hooks).toBeDefined();
			expect(window.WPAdminHealth.extensions.hooks.DASHBOARD_INIT).toBe(
				'dashboardInit'
			);
		});

		it('provides registerWidget method', () => {
			expect(typeof window.WPAdminHealth.extensions.registerWidget).toBe(
				'function'
			);
		});

		it('provides unregisterWidget method', () => {
			expect(
				typeof window.WPAdminHealth.extensions.unregisterWidget
			).toBe('function');
		});

		it('provides addFilter method', () => {
			expect(typeof window.WPAdminHealth.extensions.addFilter).toBe(
				'function'
			);
		});

		it('provides on method for event subscription', () => {
			expect(typeof window.WPAdminHealth.extensions.on).toBe('function');
		});

		it('provides once method for one-time subscription', () => {
			expect(typeof window.WPAdminHealth.extensions.once).toBe(
				'function'
			);
		});
	});

	describe('Internal components (via __testing__)', () => {
		it('has ErrorBoundary component', () => {
			expect(__testing__.Components.ErrorBoundary).toBeDefined();
		});

		it('has HealthScoreCircle component', () => {
			expect(__testing__.Components.HealthScoreCircle).toBeDefined();
		});

		it('has MetricCard component', () => {
			expect(__testing__.Components.MetricCard).toBeDefined();
		});

		it('has ActivityTimeline component', () => {
			expect(__testing__.Components.ActivityTimeline).toBeDefined();
		});

		it('has QuickActions component', () => {
			expect(__testing__.Components.QuickActions).toBeDefined();
		});

		it('has Recommendations component', () => {
			expect(__testing__.Components.Recommendations).toBeDefined();
		});
	});

	describe('withErrorBoundary utility', () => {
		it('wraps component with error boundary', () => {
			const TestComponent = () => <div>Test</div>;
			const WrappedComponent = __testing__.withErrorBoundary(
				TestComponent,
				'TestComponent'
			);

			expect(WrappedComponent.displayName).toBe(
				'WithErrorBoundary(TestComponent)'
			);
		});

		it('renders wrapped component correctly', () => {
			const TestComponent = () => <div>Wrapped content</div>;
			const WrappedComponent = __testing__.withErrorBoundary(
				TestComponent,
				'TestComponent'
			);

			render(<WrappedComponent />);
			expect(screen.getByText('Wrapped content')).toBeInTheDocument();
		});

		it('passes props to wrapped component', () => {
			const TestComponent = ({ message }) => <div>{message}</div>;
			const WrappedComponent = __testing__.withErrorBoundary(
				TestComponent,
				'TestComponent'
			);

			render(<WrappedComponent message="Hello World" />);
			expect(screen.getByText('Hello World')).toBeInTheDocument();
		});
	});

	describe('mountComponent utility', () => {
		let container;

		beforeEach(() => {
			container = document.createElement('div');
			container.id = 'test-container';
			document.body.appendChild(container);
		});

		afterEach(() => {
			if (container && container.parentNode) {
				container.parentNode.removeChild(container);
			}
		});

		it('mounts component to DOM element', async () => {
			const TestComponent = () => <div>Mounted content</div>;

			let root;
			await act(async () => {
				root = __testing__.mountComponent(
					container,
					TestComponent,
					{},
					'TestComponent'
				);
			});

			expect(root).not.toBeNull();
			expect(container.textContent).toContain('Mounted content');
		});

		it('mounts component using selector string', async () => {
			const TestComponent = () => <div>Selector mounted</div>;

			let root;
			await act(async () => {
				root = __testing__.mountComponent(
					'#test-container',
					TestComponent,
					{},
					'TestComponent'
				);
			});

			expect(root).not.toBeNull();
			expect(container.textContent).toContain('Selector mounted');
		});

		it('returns null when container not found', () => {
			const consoleSpy = jest
				.spyOn(console, 'warn')
				.mockImplementation(() => {});
			const TestComponent = () => <div>Test</div>;
			const root = __testing__.mountComponent(
				'#non-existent',
				TestComponent,
				{},
				'TestComponent'
			);

			expect(root).toBeNull();
			expect(consoleSpy).toHaveBeenCalledWith(
				'WP Admin Health: Container element not found for TestComponent'
			);

			consoleSpy.mockRestore();
		});

		it('passes props to mounted component', async () => {
			const TestComponent = ({ name }) => <div>Hello {name}</div>;

			await act(async () => {
				__testing__.mountComponent(
					container,
					TestComponent,
					{ name: 'World' },
					'TestComponent'
				);
			});

			expect(container.textContent).toContain('Hello World');
		});

		it('wraps component with ErrorBoundary', async () => {
			// Suppress console.error for this test
			const consoleSpy = jest
				.spyOn(console, 'error')
				.mockImplementation(() => {});

			const ThrowingComponent = () => {
				throw new Error('Test error');
			};

			await act(async () => {
				__testing__.mountComponent(
					container,
					ThrowingComponent,
					{},
					'ThrowingComponent'
				);
			});

			// Should show error boundary fallback instead of crashing
			expect(container.textContent).toContain(
				'Error in ThrowingComponent'
			);

			consoleSpy.mockRestore();
		});
	});

	describe('unmountComponent utility', () => {
		it('unmounts a mounted component', async () => {
			const testContainer = document.createElement('div');
			document.body.appendChild(testContainer);

			const TestComponent = () => <div>Test</div>;

			let root;
			await act(async () => {
				root = __testing__.mountComponent(
					testContainer,
					TestComponent,
					{},
					'TestComponent'
				);
			});

			expect(testContainer.textContent).toContain('Test');

			act(() => {
				__testing__.unmountComponent(root);
			});

			// Content should be removed after unmount
			expect(testContainer.textContent).toBe('');

			document.body.removeChild(testContainer);
		});

		it('handles null root gracefully', () => {
			// Should not throw
			expect(() => __testing__.unmountComponent(null)).not.toThrow();
		});

		it('handles undefined root gracefully', () => {
			// Should not throw
			expect(() => __testing__.unmountComponent(undefined)).not.toThrow();
		});
	});

	describe('DOMContentLoaded event', () => {
		it('triggers dashboardInit event when DOM is ready', () => {
			// Manually dispatch DOMContentLoaded
			const event = new Event('DOMContentLoaded');
			document.dispatchEvent(event);

			expect(window.WPAdminHealth.Events.trigger).toHaveBeenCalledWith(
				'dashboardInit',
				expect.objectContaining({
					extensions: window.WPAdminHealth.extensions,
				})
			);
		});

		it('handles missing WPAdminHealth gracefully', () => {
			delete window.WPAdminHealth;

			// Should not throw
			expect(() => {
				const event = new Event('DOMContentLoaded');
				document.dispatchEvent(event);
			}).not.toThrow();
		});
	});

	describe('Global namespace changes', () => {
		it('does NOT expose WPAdminHealthComponents global', () => {
			// The old API has been removed
			expect(window.WPAdminHealthComponents).toBeUndefined();
		});

		it('exposes API on WPAdminHealth namespace', () => {
			expect(window.WPAdminHealth.api).toBeDefined();
			expect(window.WPAdminHealth.ApiError).toBeDefined();
		});
	});
});
