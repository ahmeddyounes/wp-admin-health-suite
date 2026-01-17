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
import './dashboard.js';

describe('Dashboard Entry Point', () => {
	beforeEach(() => {
		// Reset mocks
		jest.clearAllMocks();

		// Mock WPAdminHealth.Events
		window.WPAdminHealth = {
			Events: {
				trigger: jest.fn(),
			},
		};
	});

	afterEach(() => {
		cleanup();
		delete window.WPAdminHealth;
	});

	describe('WPAdminHealthComponents global', () => {
		it('exposes components on window.WPAdminHealthComponents', () => {
			expect(window.WPAdminHealthComponents).toBeDefined();
		});

		it('exposes ErrorBoundary component', () => {
			expect(window.WPAdminHealthComponents.ErrorBoundary).toBeDefined();
		});

		it('exposes HealthScoreCircle component', () => {
			expect(
				window.WPAdminHealthComponents.HealthScoreCircle
			).toBeDefined();
		});

		it('exposes MetricCard component', () => {
			expect(window.WPAdminHealthComponents.MetricCard).toBeDefined();
		});

		it('exposes ActivityTimeline component', () => {
			expect(
				window.WPAdminHealthComponents.ActivityTimeline
			).toBeDefined();
		});

		it('exposes QuickActions component', () => {
			expect(window.WPAdminHealthComponents.QuickActions).toBeDefined();
		});

		it('exposes Recommendations component', () => {
			expect(
				window.WPAdminHealthComponents.Recommendations
			).toBeDefined();
		});

		it('exposes React', () => {
			expect(window.WPAdminHealthComponents.React).toBeDefined();
			expect(window.WPAdminHealthComponents.React).toBe(React);
		});

		it('exposes createRoot', () => {
			expect(window.WPAdminHealthComponents.createRoot).toBeDefined();
			expect(typeof window.WPAdminHealthComponents.createRoot).toBe(
				'function'
			);
		});

		it('exposes withErrorBoundary utility', () => {
			expect(
				window.WPAdminHealthComponents.withErrorBoundary
			).toBeDefined();
			expect(
				typeof window.WPAdminHealthComponents.withErrorBoundary
			).toBe('function');
		});

		it('exposes mountComponent utility', () => {
			expect(window.WPAdminHealthComponents.mountComponent).toBeDefined();
			expect(typeof window.WPAdminHealthComponents.mountComponent).toBe(
				'function'
			);
		});

		it('exposes unmountComponent utility', () => {
			expect(
				window.WPAdminHealthComponents.unmountComponent
			).toBeDefined();
			expect(typeof window.WPAdminHealthComponents.unmountComponent).toBe(
				'function'
			);
		});
	});

	describe('withErrorBoundary utility', () => {
		it('wraps component with error boundary', () => {
			const TestComponent = () => <div>Test</div>;
			const WrappedComponent =
				window.WPAdminHealthComponents.withErrorBoundary(
					TestComponent,
					'TestComponent'
				);

			expect(WrappedComponent.displayName).toBe(
				'WithErrorBoundary(TestComponent)'
			);
		});

		it('renders wrapped component correctly', () => {
			const TestComponent = () => <div>Wrapped content</div>;
			const WrappedComponent =
				window.WPAdminHealthComponents.withErrorBoundary(
					TestComponent,
					'TestComponent'
				);

			render(<WrappedComponent />);
			expect(screen.getByText('Wrapped content')).toBeInTheDocument();
		});

		it('passes props to wrapped component', () => {
			const TestComponent = ({ message }) => <div>{message}</div>;
			const WrappedComponent =
				window.WPAdminHealthComponents.withErrorBoundary(
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
				root = window.WPAdminHealthComponents.mountComponent(
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
				root = window.WPAdminHealthComponents.mountComponent(
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
			const root = window.WPAdminHealthComponents.mountComponent(
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
				window.WPAdminHealthComponents.mountComponent(
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
				window.WPAdminHealthComponents.mountComponent(
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
				root = window.WPAdminHealthComponents.mountComponent(
					testContainer,
					TestComponent,
					{},
					'TestComponent'
				);
			});

			expect(testContainer.textContent).toContain('Test');

			act(() => {
				window.WPAdminHealthComponents.unmountComponent(root);
			});

			// Content should be removed after unmount
			expect(testContainer.textContent).toBe('');

			document.body.removeChild(testContainer);
		});

		it('handles null root gracefully', () => {
			// Should not throw
			expect(() =>
				window.WPAdminHealthComponents.unmountComponent(null)
			).not.toThrow();
		});

		it('handles undefined root gracefully', () => {
			// Should not throw
			expect(() =>
				window.WPAdminHealthComponents.unmountComponent(undefined)
			).not.toThrow();
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
					components: window.WPAdminHealthComponents,
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
});
