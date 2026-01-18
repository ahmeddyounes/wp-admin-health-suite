// Jest setup file for additional configuration
// Add any global test setup here

// Import testing library matchers
import '@testing-library/jest-dom';

// Mock WordPress globals
global.wp = {
	apiFetch: jest.fn(),
	i18n: {
		__: (text) => text,
		_x: (text) => text,
		_n: (single, plural, number) => (number === 1 ? single : plural),
	},
	element: {
		createElement: jest.fn(),
	},
	components: {},
};

// Mock the centralized API client for tests
jest.mock('./assets/js/utils/api.js', () => {
	const mockApiClient = {
		get: jest.fn(),
		post: jest.fn(),
		put: jest.fn(),
		patch: jest.fn(),
		delete: jest.fn(),
		request: jest.fn(),
		invalidateCache: jest.fn(),
		clearCache: jest.fn(),
	};
	return {
		__esModule: true,
		default: mockApiClient,
		ApiError: class ApiError extends Error {
			constructor(message, status, data) {
				super(message);
				this.name = 'ApiError';
				this.status = status;
				this.data = data;
			}
		},
	};
});

global.wpAdminHealthData = {
	version: '1.5.0',
	ajax_url: '/wp-admin/admin-ajax.php',
	rest_url: '/wp-json/',
	rest_root: '/wp-json/',
	rest_nonce: 'test-rest-nonce',
	rest_namespace: 'wpha/v1',
	screen_id: 'toplevel_page_admin-health',
	plugin_url: '/wp-content/plugins/wp-admin-health-suite/',
	features: {
		restApiEnabled: true,
		debugMode: false,
		safeMode: false,
		dashboardWidget: true,
		adminBarMenu: true,
		loggingEnabled: false,
		schedulingEnabled: true,
		aiRecommendations: false,
		actionSchedulerAvailable: false,
	},
	i18n: {
		loading: 'Loading...',
		error: 'An error occurred.',
		success: 'Success!',
		confirm: 'Are you sure?',
		save: 'Save',
		cancel: 'Cancel',
		delete: 'Delete',
		refresh: 'Refresh',
		no_data: 'No data available.',
		processing: 'Processing...',
		analyze: 'Analyze',
		clean: 'Clean',
		revisions: 'Post Revisions',
		transients: 'Transients',
		spam: 'Spam Comments',
		trash: 'Trash (Posts & Comments)',
		orphaned: 'Orphaned Data',
		confirmCleanup:
			'Are you sure you want to clean this data? This action cannot be undone.',
	},
};
