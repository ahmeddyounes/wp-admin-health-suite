/**
 * Tests for ErrorBoundary Component
 *
 * @package
 */

import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ErrorBoundary from './ErrorBoundary';

// Component that throws an error for testing
const ThrowError = ({ shouldThrow = false }) => {
	if (shouldThrow) {
		throw new Error('Test error');
	}
	return <div>No error</div>;
};

// Component that throws an error on render
const AlwaysThrows = () => {
	throw new Error('Always throws error');
};

describe('ErrorBoundary', () => {
	// Suppress console.error during tests since we expect errors
	const originalError = console.error;

	beforeAll(() => {
		console.error = jest.fn();
	});

	afterAll(() => {
		console.error = originalError;
	});

	beforeEach(() => {
		jest.clearAllMocks();
		// Mock WPAdminHealth.Events
		window.WPAdminHealth = {
			Events: {
				trigger: jest.fn(),
			},
		};
	});

	afterEach(() => {
		delete window.WPAdminHealth;
	});

	it('renders children when there is no error', () => {
		render(
			<ErrorBoundary>
				<div>Test content</div>
			</ErrorBoundary>
		);

		expect(screen.getByText('Test content')).toBeInTheDocument();
	});

	it('renders fallback UI when an error occurs', () => {
		render(
			<ErrorBoundary>
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(screen.getByRole('alert')).toBeInTheDocument();
		expect(screen.getByText('Something went wrong')).toBeInTheDocument();
	});

	it('displays component name in error message when provided', () => {
		render(
			<ErrorBoundary componentName="TestComponent">
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(screen.getByText('Error in TestComponent')).toBeInTheDocument();
	});

	it('calls onError callback when an error occurs', () => {
		const onError = jest.fn();

		render(
			<ErrorBoundary onError={onError}>
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(onError).toHaveBeenCalled();
		expect(onError.mock.calls[0][0]).toBeInstanceOf(Error);
	});

	it('triggers WordPress event when error occurs', () => {
		render(
			<ErrorBoundary componentName="TestWidget">
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(window.WPAdminHealth.Events.trigger).toHaveBeenCalledWith(
			'componentError',
			expect.objectContaining({
				componentName: 'TestWidget',
			})
		);
	});

	it('renders custom fallback when provided as node', () => {
		render(
			<ErrorBoundary fallback={<div>Custom error UI</div>}>
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(screen.getByText('Custom error UI')).toBeInTheDocument();
	});

	it('renders custom fallback function with error and reset', () => {
		const fallbackFn = jest.fn(({ error, reset }) => (
			<div>
				<span>Error: {error.message}</span>
				<button onClick={reset}>Reset</button>
			</div>
		));

		render(
			<ErrorBoundary fallback={fallbackFn}>
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(fallbackFn).toHaveBeenCalled();
		expect(
			screen.getByText('Error: Always throws error')
		).toBeInTheDocument();
	});

	it('resets error state when Try Again button is clicked', () => {
		// Use a controlled component that we can toggle
		let shouldThrow = true;
		const ControlledThrow = () => {
			if (shouldThrow) {
				throw new Error('Test error');
			}
			return <div>No error</div>;
		};

		const { rerender } = render(
			<ErrorBoundary>
				<ControlledThrow />
			</ErrorBoundary>
		);

		// Should show error UI
		expect(screen.getByText('Something went wrong')).toBeInTheDocument();

		// Change the throw flag before clicking Try Again
		shouldThrow = false;

		// Click Try Again - now it will re-render with shouldThrow=false
		fireEvent.click(screen.getByText('Try Again'));

		// Force a rerender to pick up the new shouldThrow value
		rerender(
			<ErrorBoundary>
				<ControlledThrow />
			</ErrorBoundary>
		);

		// Should show content again
		expect(screen.getByText('No error')).toBeInTheDocument();
	});

	it('logs error to console', () => {
		render(
			<ErrorBoundary>
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(console.error).toHaveBeenCalledWith(
			'ErrorBoundary caught an error:',
			expect.any(Error),
			expect.any(Object)
		);
	});

	it('shows error details when showDetails is true', () => {
		render(
			<ErrorBoundary showDetails={true}>
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(screen.getByText('Error details')).toBeInTheDocument();
	});

	it('hides error details when showDetails is false', () => {
		render(
			<ErrorBoundary showDetails={false}>
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(screen.queryByText('Error details')).not.toBeInTheDocument();
	});

	it('has accessible role="alert" on error state', () => {
		render(
			<ErrorBoundary>
				<AlwaysThrows />
			</ErrorBoundary>
		);

		expect(screen.getByRole('alert')).toBeInTheDocument();
	});

	it('renders dashicons warning icon in error state', () => {
		const { container } = render(
			<ErrorBoundary>
				<AlwaysThrows />
			</ErrorBoundary>
		);

		const icon = container.querySelector('.dashicons-warning');
		expect(icon).toBeInTheDocument();
		expect(icon).toHaveAttribute('aria-hidden', 'true');
	});

	it('handles errors gracefully without WPAdminHealth global', () => {
		delete window.WPAdminHealth;

		// Should not throw
		expect(() =>
			render(
				<ErrorBoundary>
					<AlwaysThrows />
				</ErrorBoundary>
			)
		).not.toThrow();

		expect(screen.getByRole('alert')).toBeInTheDocument();
	});
});
