/**
 * Tests for Settings Entry Point
 *
 * @package
 */

import React from 'react';
import { cleanup } from '@testing-library/react';

// Mock the admin.js import
jest.mock('../admin.js', () => ({}));

// Import after mocking
import { __testing__ } from './settings.js';

describe('Settings Entry Point', () => {
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

		it('provides hook constants', () => {
			expect(window.WPAdminHealth.extensions.hooks).toBeDefined();
		});
	});

	describe('Internal utilities (via __testing__)', () => {
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
	});

	describe('React availability for settings page', () => {
		it('React is available via __testing__ export', () => {
			expect(__testing__.React).toBe(React);
			expect(__testing__.React.createElement).toBe(React.createElement);
		});

		it('createRoot is available for mounting settings UI components', () => {
			const { createRoot } = __testing__;
			expect(createRoot).toBeDefined();
			expect(typeof createRoot).toBe('function');
		});
	});

	describe('React hooks are available', () => {
		it('provides useState hook', () => {
			expect(__testing__.React.useState).toBeDefined();
			expect(typeof __testing__.React.useState).toBe('function');
		});

		it('provides useEffect hook', () => {
			expect(__testing__.React.useEffect).toBeDefined();
			expect(typeof __testing__.React.useEffect).toBe('function');
		});

		it('provides useRef hook', () => {
			expect(__testing__.React.useRef).toBeDefined();
			expect(typeof __testing__.React.useRef).toBe('function');
		});

		it('provides useCallback hook', () => {
			expect(__testing__.React.useCallback).toBeDefined();
			expect(typeof __testing__.React.useCallback).toBe('function');
		});

		it('provides memo for component optimization', () => {
			expect(__testing__.React.memo).toBeDefined();
			expect(typeof __testing__.React.memo).toBe('function');
		});

		it('provides Fragment for grouping elements', () => {
			expect(__testing__.React.Fragment).toBeDefined();
		});
	});
});
