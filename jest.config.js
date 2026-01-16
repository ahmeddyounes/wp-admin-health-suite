module.exports = {
	testEnvironment: 'jsdom',
	setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],
	moduleNameMapper: {
		'\\.(css|less|scss|sass)$': 'identity-obj-proxy',
	},
	transform: {
		'^.+\\.(js|jsx)$': 'babel-jest',
	},
	testMatch: [
		'**/__tests__/**/*.(test|spec).(js|jsx)',
		'**/*.(test|spec).(js|jsx)',
	],
	testPathIgnorePatterns: ['/node_modules/', '/vendor/', '/tests/'],
	collectCoverageFrom: [
		'assets/js/**/*.{js,jsx}',
		'!assets/js/dist/**',
		'!assets/js/entries/**',
		'!**/*.test.{js,jsx}',
		'!**/node_modules/**',
	],
	coverageDirectory: 'coverage',
	coverageReporters: ['text', 'lcov', 'html'],
	coverageThreshold: {
		global: {
			branches: 70,
			functions: 70,
			lines: 70,
			statements: 70,
		},
	},
};
