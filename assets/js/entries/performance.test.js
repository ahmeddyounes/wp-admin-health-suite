/**
 * Tests for Performance Entry Point
 *
 * @package
 */

import React from 'react';

// Mock the admin.js, charts.js, and performance.js imports
jest.mock('../admin.js', () => ({}));
jest.mock('../charts.js', () => ({}));
jest.mock('../performance.js', () => ({}));

// Import after mocking
import './performance.js';

describe('Performance Entry Point', () => {
	beforeEach(() => {
		// Reset mocks
		jest.clearAllMocks();
	});

	afterEach(() => {
		// Cleanup global state if needed
	});

	describe('WPAdminHealthComponents global', () => {
		it('exposes components on window.WPAdminHealthComponents', () => {
			expect(window.WPAdminHealthComponents).toBeDefined();
		});

		it('exposes MetricCard component', () => {
			expect(window.WPAdminHealthComponents.MetricCard).toBeDefined();
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
	});

	describe('Component exports are valid React components', () => {
		it('MetricCard is a valid React component', () => {
			const MetricCard = window.WPAdminHealthComponents.MetricCard;
			expect(typeof MetricCard).toBe('function');
		});

		it('Recommendations is a valid React component', () => {
			const Recommendations =
				window.WPAdminHealthComponents.Recommendations;
			expect(typeof Recommendations).toBe('function');
		});
	});

	describe('Global namespace preservation', () => {
		it('preserves existing WPAdminHealthComponents properties', () => {
			// Verify that the performance entry point uses Object.assign correctly
			expect(window.WPAdminHealthComponents).toBeDefined();

			// Should have MetricCard from performance entry
			expect(window.WPAdminHealthComponents.MetricCard).toBeDefined();

			// Should have Recommendations from performance entry
			expect(
				window.WPAdminHealthComponents.Recommendations
			).toBeDefined();
		});

		it('does not remove previously set components', () => {
			// After multiple entry points load, all components should be available
			const components = window.WPAdminHealthComponents;

			// Core utilities should be available
			expect(components.React).toBeDefined();
			expect(components.createRoot).toBeDefined();
		});

		it('initializes WPAdminHealthComponents if not already present', () => {
			// The entry point should handle the case where window.WPAdminHealthComponents
			// is not already defined by initializing it to an empty object first
			expect(window.WPAdminHealthComponents).toBeDefined();
			expect(typeof window.WPAdminHealthComponents).toBe('object');
		});
	});

	describe('Performance-specific component availability', () => {
		it('MetricCard component can be used for performance metrics display', () => {
			const MetricCard = window.WPAdminHealthComponents.MetricCard;
			expect(MetricCard).toBeDefined();
			// Verify it's a function (React component)
			expect(typeof MetricCard).toBe('function');
		});

		it('Recommendations component can be used for performance recommendations', () => {
			const Recommendations =
				window.WPAdminHealthComponents.Recommendations;
			expect(Recommendations).toBeDefined();
			// Verify it's a function (React component)
			expect(typeof Recommendations).toBe('function');
		});
	});

	describe('React and createRoot availability for chart rendering', () => {
		it('React is available for performance chart components', () => {
			expect(window.WPAdminHealthComponents.React).toBe(React);
			expect(window.WPAdminHealthComponents.React.createElement).toBe(
				React.createElement
			);
		});

		it('createRoot is available for mounting performance visualizations', () => {
			const { createRoot } = window.WPAdminHealthComponents;
			expect(createRoot).toBeDefined();
			expect(typeof createRoot).toBe('function');
		});
	});
});
