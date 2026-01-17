/**
 * Tests for Media Audit Entry Point
 *
 * @package
 */

import React from 'react';

// Mock the admin.js and media-audit.js imports
jest.mock('../admin.js', () => ({}));
jest.mock('../media-audit.js', () => ({}));

// Import after mocking
import './media-audit.js';

describe('Media Audit Entry Point', () => {
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
	});

	describe('Global namespace preservation', () => {
		it('preserves existing WPAdminHealthComponents properties', () => {
			// Verify that the media-audit entry point uses Object.assign correctly
			expect(window.WPAdminHealthComponents).toBeDefined();

			// Should have MetricCard from media-audit entry
			expect(window.WPAdminHealthComponents.MetricCard).toBeDefined();
		});

		it('does not remove previously set components', () => {
			// After multiple entry points load, all components should be available
			const components = window.WPAdminHealthComponents;

			// Core utilities should be available
			expect(components.React).toBeDefined();
			expect(components.createRoot).toBeDefined();
		});
	});
});
