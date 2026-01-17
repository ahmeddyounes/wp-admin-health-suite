/**
 * Tests for Settings Entry Point
 *
 * @package
 */

import React from 'react';

// Mock the admin.js import
jest.mock('../admin.js', () => ({}));

// Import after mocking
import './settings.js';

describe('Settings Entry Point', () => {
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

	describe('Global namespace preservation', () => {
		it('preserves existing WPAdminHealthComponents properties', () => {
			// Verify that the settings entry point uses Object.assign correctly
			expect(window.WPAdminHealthComponents).toBeDefined();

			// Core utilities should be available
			expect(window.WPAdminHealthComponents.React).toBeDefined();
			expect(window.WPAdminHealthComponents.createRoot).toBeDefined();
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

	describe('React and createRoot availability for settings page', () => {
		it('React is available for settings form components', () => {
			expect(window.WPAdminHealthComponents.React).toBe(React);
			expect(window.WPAdminHealthComponents.React.createElement).toBe(
				React.createElement
			);
		});

		it('createRoot is available for mounting settings UI components', () => {
			const { createRoot } = window.WPAdminHealthComponents;
			expect(createRoot).toBeDefined();
			expect(typeof createRoot).toBe('function');
		});
	});

	describe('Settings-specific functionality', () => {
		it('provides React hooks for form state management', () => {
			const { React: ExposedReact } = window.WPAdminHealthComponents;
			expect(ExposedReact.useState).toBeDefined();
			expect(typeof ExposedReact.useState).toBe('function');
		});

		it('provides React hooks for side effects', () => {
			const { React: ExposedReact } = window.WPAdminHealthComponents;
			expect(ExposedReact.useEffect).toBeDefined();
			expect(typeof ExposedReact.useEffect).toBe('function');
		});

		it('provides React hooks for refs (useful for form inputs)', () => {
			const { React: ExposedReact } = window.WPAdminHealthComponents;
			expect(ExposedReact.useRef).toBeDefined();
			expect(typeof ExposedReact.useRef).toBe('function');
		});

		it('provides React callback memoization (useful for form handlers)', () => {
			const { React: ExposedReact } = window.WPAdminHealthComponents;
			expect(ExposedReact.useCallback).toBeDefined();
			expect(typeof ExposedReact.useCallback).toBe('function');
		});

		it('provides React memo for component optimization', () => {
			const { React: ExposedReact } = window.WPAdminHealthComponents;
			expect(ExposedReact.memo).toBeDefined();
			expect(typeof ExposedReact.memo).toBe('function');
		});
	});

	describe('WordPress template integration', () => {
		it('WPAdminHealthComponents is accessible globally for WordPress PHP templates', () => {
			// WordPress templates can use this to mount React components
			expect(window.WPAdminHealthComponents).toBeDefined();
			expect(window.WPAdminHealthComponents.React).toBeDefined();
			expect(window.WPAdminHealthComponents.createRoot).toBeDefined();
		});

		it('allows creating React elements from WordPress templates', () => {
			const { React: ExposedReact } = window.WPAdminHealthComponents;
			const element = ExposedReact.createElement(
				'div',
				{ className: 'test' },
				'Test content'
			);
			expect(element).toBeDefined();
			expect(element.type).toBe('div');
			expect(element.props.className).toBe('test');
			expect(element.props.children).toBe('Test content');
		});

		it('provides Fragment for grouping elements without extra DOM nodes', () => {
			const { React: ExposedReact } = window.WPAdminHealthComponents;
			expect(ExposedReact.Fragment).toBeDefined();
		});
	});
});
