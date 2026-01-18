/**
 * Tests for Media Audit Entry Point
 *
 * @package
 */

import React from 'react';
import { cleanup } from '@testing-library/react';

// Mock the admin.js and media-audit.js imports
jest.mock('../admin.js', () => ({}));
jest.mock('../media-audit.js', () => ({}));

// Import after mocking
import { __testing__ } from './media-audit.js';

describe('Media Audit Entry Point', () => {
	beforeEach(() => {
		// Reset mocks
		jest.clearAllMocks();

		// Ensure WPAdminHealth namespace exists
		window.WPAdminHealth = window.WPAdminHealth || {};
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
		});

		it('provides registerWidget method', () => {
			expect(typeof window.WPAdminHealth.extensions.registerWidget).toBe(
				'function'
			);
		});
	});

	describe('Internal components (via __testing__)', () => {
		it('has MetricCard component', () => {
			expect(__testing__.Components.MetricCard).toBeDefined();
		});

		it('has React reference', () => {
			expect(__testing__.React).toBe(React);
		});

		it('has createRoot function', () => {
			expect(typeof __testing__.createRoot).toBe('function');
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

	describe('Components are valid React components', () => {
		it('MetricCard is a valid React component', () => {
			const MetricCard = __testing__.Components.MetricCard;
			expect(MetricCard).toBeDefined();
			expect(typeof MetricCard).toBe('function');
		});
	});
});
