/**
 * Tests for Extension API
 *
 * @package
 */

import React from 'react';
import { act } from '@testing-library/react';

import ExtensionAPI, { InternalExtensionAPI } from './extension-api.js';

describe('Extension API', () => {
	beforeEach(() => {
		// Reset mocks and clear registry
		jest.clearAllMocks();
		InternalExtensionAPI.clear();

		// Setup WPAdminHealth.Events mock
		window.WPAdminHealth = {
			Events: {
				on: jest.fn(() => jest.fn()),
				once: jest.fn(),
				trigger: jest.fn(),
			},
		};
	});

	afterEach(() => {
		act(() => {
			InternalExtensionAPI.clear();
		});
		delete window.WPAdminHealth;
	});

	describe('ExtensionAPI.version', () => {
		it('exposes version string', () => {
			expect(ExtensionAPI.version).toBeDefined();
			expect(typeof ExtensionAPI.version).toBe('string');
		});
	});

	describe('ExtensionAPI.hooks', () => {
		it('exposes hook constants', () => {
			expect(ExtensionAPI.hooks).toBeDefined();
			expect(ExtensionAPI.hooks.READY).toBe('ready');
			expect(ExtensionAPI.hooks.DASHBOARD_INIT).toBe('dashboardInit');
		});

		it('includes all expected hook names', () => {
			const expectedHooks = [
				'READY',
				'PAGE_INIT',
				'DASHBOARD_INIT',
				'DASHBOARD_REFRESH',
				'DATABASE_CLEANUP_START',
				'DATABASE_CLEANUP_COMPLETE',
				'MEDIA_SCAN_START',
				'MEDIA_SCAN_COMPLETE',
				'PERFORMANCE_CHECK_START',
				'PERFORMANCE_CHECK_COMPLETE',
			];

			expectedHooks.forEach((hook) => {
				expect(ExtensionAPI.hooks[hook]).toBeDefined();
			});
		});
	});

	describe('ExtensionAPI.registerWidget', () => {
		it('registers a widget in a zone', () => {
			const widget = {
				id: 'test-widget',
				render: jest.fn(),
			};

			ExtensionAPI.registerWidget('dashboard-top', widget);

			const container = document.createElement('div');
			InternalExtensionAPI.renderZone('dashboard-top', container);

			expect(widget.render).toHaveBeenCalled();
		});

		it('warns on duplicate widget registration', () => {
			const consoleSpy = jest
				.spyOn(console, 'warn')
				.mockImplementation(() => {});

			const widget = {
				id: 'duplicate-widget',
				render: jest.fn(),
			};

			ExtensionAPI.registerWidget('dashboard-top', widget);
			ExtensionAPI.registerWidget('dashboard-top', widget);

			expect(consoleSpy).toHaveBeenCalledWith(
				expect.stringContaining('already registered')
			);

			consoleSpy.mockRestore();
		});

		it('errors on missing id or render function', () => {
			const consoleSpy = jest
				.spyOn(console, 'error')
				.mockImplementation(() => {});

			ExtensionAPI.registerWidget('dashboard-top', { render: jest.fn() });
			ExtensionAPI.registerWidget('dashboard-top', { id: 'test' });
			ExtensionAPI.registerWidget('dashboard-top', {});

			expect(consoleSpy).toHaveBeenCalledTimes(3);

			consoleSpy.mockRestore();
		});

		it('respects priority ordering', () => {
			const callOrder = [];

			ExtensionAPI.registerWidget('dashboard-bottom', {
				id: 'widget-low',
				render: () => callOrder.push('low'),
				priority: 20,
			});

			ExtensionAPI.registerWidget('dashboard-bottom', {
				id: 'widget-high',
				render: () => callOrder.push('high'),
				priority: 5,
			});

			ExtensionAPI.registerWidget('dashboard-bottom', {
				id: 'widget-medium',
				render: () => callOrder.push('medium'),
				priority: 10,
			});

			const container = document.createElement('div');
			InternalExtensionAPI.renderZone('dashboard-bottom', container);

			expect(callOrder).toEqual(['high', 'medium', 'low']);
		});
	});

	describe('ExtensionAPI.unregisterWidget', () => {
		it('removes a registered widget', () => {
			const widget = {
				id: 'removable-widget',
				render: jest.fn(),
			};

			ExtensionAPI.registerWidget('dashboard-top', widget);
			ExtensionAPI.unregisterWidget('dashboard-top', 'removable-widget');

			const container = document.createElement('div');
			InternalExtensionAPI.renderZone('dashboard-top', container);

			expect(widget.render).not.toHaveBeenCalled();
		});

		it('handles unregistering non-existent widget gracefully', () => {
			expect(() => {
				ExtensionAPI.unregisterWidget(
					'non-existent-zone',
					'non-existent-widget'
				);
			}).not.toThrow();
		});
	});

	describe('ExtensionAPI.addFilter', () => {
		it('adds a filter callback', () => {
			const callback = jest.fn((value) => value + ' filtered');

			ExtensionAPI.addFilter('testFilter', callback);

			const result = InternalExtensionAPI.applyFilters(
				'testFilter',
				'original'
			);
			expect(result).toBe('original filtered');
			expect(callback).toHaveBeenCalledWith('original');
		});

		it('returns unsubscribe function', () => {
			const callback = jest.fn((value) => value + ' filtered');

			const unsubscribe = ExtensionAPI.addFilter('testFilter', callback);
			unsubscribe();

			const result = InternalExtensionAPI.applyFilters(
				'testFilter',
				'original'
			);
			expect(result).toBe('original');
			expect(callback).not.toHaveBeenCalled();
		});

		it('chains multiple filters by priority', () => {
			ExtensionAPI.addFilter(
				'chainFilter',
				(value) => value + ' [second]',
				20
			);
			ExtensionAPI.addFilter(
				'chainFilter',
				(value) => value + ' [first]',
				10
			);
			ExtensionAPI.addFilter(
				'chainFilter',
				(value) => value + ' [third]',
				30
			);

			const result = InternalExtensionAPI.applyFilters(
				'chainFilter',
				'start'
			);
			expect(result).toBe('start [first] [second] [third]');
		});

		it('handles filter errors gracefully', () => {
			const consoleSpy = jest
				.spyOn(console, 'error')
				.mockImplementation(() => {});

			ExtensionAPI.addFilter('errorFilter', () => {
				throw new Error('Filter error');
			});
			ExtensionAPI.addFilter(
				'errorFilter',
				(value) => value + ' success',
				20
			);

			const result = InternalExtensionAPI.applyFilters(
				'errorFilter',
				'start'
			);
			expect(result).toBe('start success');
			expect(consoleSpy).toHaveBeenCalled();

			consoleSpy.mockRestore();
		});
	});

	describe('ExtensionAPI.on', () => {
		it('subscribes to events via WPAdminHealth.Events', () => {
			const callback = jest.fn();
			ExtensionAPI.on('dashboardInit', callback);

			expect(window.WPAdminHealth.Events.on).toHaveBeenCalledWith(
				'dashboardInit',
				callback
			);
		});

		it('returns unsubscribe function', () => {
			const unsubscribe = jest.fn();
			window.WPAdminHealth.Events.on.mockReturnValue(unsubscribe);

			const result = ExtensionAPI.on('dashboardInit', jest.fn());
			expect(result).toBe(unsubscribe);
		});

		it('handles missing Events gracefully', () => {
			delete window.WPAdminHealth;

			const consoleSpy = jest
				.spyOn(console, 'warn')
				.mockImplementation(() => {});

			const result = ExtensionAPI.on('dashboardInit', jest.fn());
			expect(typeof result).toBe('function');

			consoleSpy.mockRestore();
		});
	});

	describe('ExtensionAPI.once', () => {
		it('subscribes to one-time events', () => {
			const callback = jest.fn();
			ExtensionAPI.once('dashboardInit', callback);

			expect(window.WPAdminHealth.Events.once).toHaveBeenCalledWith(
				'dashboardInit',
				callback
			);
		});
	});

	describe('InternalExtensionAPI.renderZone', () => {
		it('renders widgets to a container', () => {
			ExtensionAPI.registerWidget('test-zone', {
				id: 'dom-widget',
				render: (container) => {
					container.innerHTML = '<div class="test">Hello</div>';
				},
			});

			const container = document.createElement('div');
			InternalExtensionAPI.renderZone('test-zone', container);

			expect(container.querySelector('.test')).not.toBeNull();
			expect(container.textContent).toContain('Hello');
		});

		it('mounts React elements returned from render', () => {
			ExtensionAPI.registerWidget('react-zone', {
				id: 'react-widget',
				render: () => React.createElement('div', null, 'React Widget'),
			});

			const container = document.createElement('div');
			document.body.appendChild(container);

			act(() => {
				InternalExtensionAPI.renderZone('react-zone', container);
			});

			expect(container.textContent).toContain('React Widget');

			document.body.removeChild(container);
		});

		it('handles render errors gracefully', () => {
			const consoleSpy = jest
				.spyOn(console, 'error')
				.mockImplementation(() => {});

			ExtensionAPI.registerWidget('error-zone', {
				id: 'error-widget',
				render: () => {
					throw new Error('Render error');
				},
			});

			ExtensionAPI.registerWidget('error-zone', {
				id: 'success-widget',
				render: (container) => {
					container.innerHTML = '<div>Success</div>';
				},
				priority: 20,
			});

			const container = document.createElement('div');
			InternalExtensionAPI.renderZone('error-zone', container);

			// Second widget should still render despite first failing
			expect(container.textContent).toContain('Success');
			expect(consoleSpy).toHaveBeenCalled();

			consoleSpy.mockRestore();
		});

		it('creates widget containers with data attributes', () => {
			ExtensionAPI.registerWidget('attr-zone', {
				id: 'attr-widget',
				render: () => {},
			});

			const container = document.createElement('div');
			InternalExtensionAPI.renderZone('attr-zone', container);

			const widgetContainer = container.querySelector(
				'.wpha-extension-widget'
			);
			expect(widgetContainer).not.toBeNull();
			expect(widgetContainer.dataset.widgetId).toBe('attr-widget');
		});
	});

	describe('InternalExtensionAPI.clear', () => {
		it('clears all registrations', () => {
			const widget = {
				id: 'clearable-widget',
				render: jest.fn(),
			};

			ExtensionAPI.registerWidget('clear-zone', widget);
			ExtensionAPI.addFilter('clearFilter', (v) => v);

			InternalExtensionAPI.clear();

			const container = document.createElement('div');
			InternalExtensionAPI.renderZone('clear-zone', container);
			const result = InternalExtensionAPI.applyFilters(
				'clearFilter',
				'original'
			);

			expect(widget.render).not.toHaveBeenCalled();
			expect(result).toBe('original');
		});
	});
});
