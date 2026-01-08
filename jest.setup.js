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

global.wpAdminHealthData = {
	ajax_url: '/wp-admin/admin-ajax.php',
	nonce: 'test-nonce',
	rest_url: '/wp-json/',
	plugin_url: '/wp-content/plugins/wp-admin-health-suite/',
	i18n: {
		loading: 'Loading...',
		error: 'An error occurred.',
		success: 'Success!',
	},
};
