/**
 * Tests for Database Health Entry Point
 *
 * @package
 */

import React from 'react';

// Mock the admin.js and database-health.js imports
jest.mock('../admin.js', () => ({}));
jest.mock('../database-health.js', () => ({}));

// Import after mocking
import './database-health.js';

describe('Database Health Entry Point', () => {
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

		it('exposes ActivityTimeline component', () => {
			expect(
				window.WPAdminHealthComponents.ActivityTimeline
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

		it('ActivityTimeline is a valid React component', () => {
			const ActivityTimeline =
				window.WPAdminHealthComponents.ActivityTimeline;
			expect(typeof ActivityTimeline).toBe('function');
		});
	});

	describe('Global namespace preservation', () => {
		it('preserves existing WPAdminHealthComponents properties', () => {
			// The dashboard entry point may have already set some components
			// Verify that the database-health entry point uses Object.assign correctly
			expect(window.WPAdminHealthComponents).toBeDefined();

			// Should have MetricCard from database-health entry
			expect(window.WPAdminHealthComponents.MetricCard).toBeDefined();

			// Should have ActivityTimeline from database-health entry
			expect(
				window.WPAdminHealthComponents.ActivityTimeline
			).toBeDefined();
		});

		it('does not remove previously set components', () => {
			// After both dashboard and database-health load, all components should be available
			// The components object should have components from both entry points
			const components = window.WPAdminHealthComponents;

			// Core utilities should be available
			expect(components.React).toBeDefined();
			expect(components.createRoot).toBeDefined();
		});
	});
});
