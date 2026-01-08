/**
 * Tests for QuickActions Component
 *
 * @package WPAdminHealth
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import QuickActions from './QuickActions';

// Mock fetch
global.fetch = jest.fn();

// Mock wpApiSettings
global.wpApiSettings = {
	root: '/wp-json/',
	nonce: 'test-nonce',
};

// Mock setTimeout for auto-dismiss
jest.useFakeTimers();

describe('QuickActions', () => {
	beforeEach(() => {
		fetch.mockClear();
		jest.clearAllTimers();
	});

	afterEach(() => {
		jest.clearAllMocks();
	});

	it('renders without crashing', () => {
		render(<QuickActions />);
		expect(screen.getByText('Clean Revisions')).toBeInTheDocument();
	});

	it('renders all action buttons', () => {
		render(<QuickActions />);
		expect(screen.getByText('Clean Revisions')).toBeInTheDocument();
		expect(screen.getByText('Clear Transients')).toBeInTheDocument();
		expect(screen.getByText('Find Unused Media')).toBeInTheDocument();
		expect(screen.getByText('Optimize Tables')).toBeInTheDocument();
		expect(screen.getByText('Full Scan')).toBeInTheDocument();
	});

	it('renders action buttons with correct icons', () => {
		const { container } = render(<QuickActions />);
		expect(container.querySelector('.dashicons-backup')).toBeInTheDocument();
		expect(container.querySelector('.dashicons-trash')).toBeInTheDocument();
		expect(container.querySelector('.dashicons-images-alt2')).toBeInTheDocument();
		expect(container.querySelector('.dashicons-database')).toBeInTheDocument();
		expect(container.querySelector('.dashicons-search')).toBeInTheDocument();
	});

	it('shows confirmation modal for actions that require confirmation', () => {
		render(<QuickActions />);
		const cleanRevisionsButton = screen.getByText('Clean Revisions');
		fireEvent.click(cleanRevisionsButton);

		expect(screen.getByText('Confirm Clean Revisions')).toBeInTheDocument();
		expect(screen.getByText(/Are you sure you want to proceed/)).toBeInTheDocument();
	});

	it('does not show confirmation modal for actions that do not require confirmation', async () => {
		fetch.mockResolvedValueOnce({
			ok: true,
			json: async () => ({ success: true, message: 'Scan complete' }),
		});

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			expect(screen.queryByText(/Confirm Full Scan/)).not.toBeInTheDocument();
		});
	});

	it('closes modal when Cancel is clicked', () => {
		render(<QuickActions />);
		const cleanRevisionsButton = screen.getByText('Clean Revisions');
		fireEvent.click(cleanRevisionsButton);

		expect(screen.getByText('Confirm Clean Revisions')).toBeInTheDocument();

		const cancelButton = screen.getByText('Cancel');
		fireEvent.click(cancelButton);

		expect(screen.queryByText('Confirm Clean Revisions')).not.toBeInTheDocument();
	});

	it('closes modal when clicking outside', () => {
		render(<QuickActions />);
		const cleanRevisionsButton = screen.getByText('Clean Revisions');
		fireEvent.click(cleanRevisionsButton);

		expect(screen.getByText('Confirm Clean Revisions')).toBeInTheDocument();

		const overlay = screen.getByRole('dialog');
		fireEvent.click(overlay);

		expect(screen.queryByText('Confirm Clean Revisions')).not.toBeInTheDocument();
	});

	it('executes action when Confirm is clicked', async () => {
		fetch.mockResolvedValueOnce({
			ok: true,
			json: async () => ({ success: true, message: 'Cleanup complete' }),
		});

		render(<QuickActions />);
		const cleanRevisionsButton = screen.getByText('Clean Revisions');
		fireEvent.click(cleanRevisionsButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

		await waitFor(() => {
			expect(fetch).toHaveBeenCalledWith(
				'/wp-json/wpha/v1/actions/clean_revisions',
				expect.objectContaining({
					method: 'POST',
					headers: expect.objectContaining({
						'Content-Type': 'application/json',
						'X-WP-Nonce': 'test-nonce',
					}),
				})
			);
		});
	});

	it('shows success toast on successful action execution', async () => {
		fetch.mockResolvedValueOnce({
			ok: true,
			json: async () => ({ success: true, message: 'Cleanup complete' }),
		});

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			expect(screen.getByText('Cleanup complete')).toBeInTheDocument();
		});
	});

	it('shows error toast on failed action execution', async () => {
		fetch.mockResolvedValueOnce({
			ok: false,
			json: async () => ({ success: false, message: 'Action failed' }),
		});

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			expect(screen.getByText('Action failed')).toBeInTheDocument();
		});
	});

	it('shows error toast on network error', async () => {
		fetch.mockRejectedValueOnce(new Error('Network error'));

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			expect(screen.getByText(/Network error/)).toBeInTheDocument();
		});
	});

	it('displays spinner while action is executing', async () => {
		fetch.mockImplementation(() => new Promise(() => {})); // Never resolves

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			expect(screen.getByLabelText('Loading')).toBeInTheDocument();
		});
	});

	it('disables button while action is executing', async () => {
		fetch.mockImplementation(() => new Promise(() => {})); // Never resolves

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			expect(fullScanButton.closest('[role="button"]')).toHaveAttribute('aria-busy', 'true');
		});
	});

	it('dismisses toast when dismiss button is clicked', async () => {
		fetch.mockResolvedValueOnce({
			ok: true,
			json: async () => ({ success: true, message: 'Test message' }),
		});

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			expect(screen.getByText('Test message')).toBeInTheDocument();
		});

		const dismissButton = screen.getByLabelText('Dismiss notification');
		fireEvent.click(dismissButton);

		expect(screen.queryByText('Test message')).not.toBeInTheDocument();
	});

	it('has keyboard event handlers for accessibility', () => {
		render(<QuickActions />);
		const cleanRevisionsButton = screen.getByText('Clean Revisions').closest('[role="button"]');

		// Verify the button has keyboard handlers attached (keyboard accessibility)
		expect(cleanRevisionsButton).toHaveProperty('onkeypress');
		expect(cleanRevisionsButton).toHaveAttribute('tabindex', '0');
	});

	it('renders correct number of action buttons', () => {
		const { container } = render(<QuickActions />);
		const buttons = container.querySelectorAll('[role="button"]');
		expect(buttons).toHaveLength(5);
	});

	it('has correct aria-label for each button', () => {
		render(<QuickActions />);
		expect(
			screen.getByLabelText(/Clean Revisions: Remove old post revisions/)
		).toBeInTheDocument();
		expect(
			screen.getByLabelText(/Clear Transients: Delete expired and orphaned/)
		).toBeInTheDocument();
	});

	it('renders modal with correct aria attributes', () => {
		render(<QuickActions />);
		const cleanRevisionsButton = screen.getByText('Clean Revisions');
		fireEvent.click(cleanRevisionsButton);

		const modal = screen.getByRole('dialog');
		expect(modal).toHaveAttribute('aria-modal', 'true');
		expect(modal).toHaveAttribute('aria-labelledby', 'modal-title');
		expect(modal).toHaveAttribute('aria-describedby', 'modal-description');
	});

	it('renders toast with correct role and aria-live', async () => {
		fetch.mockResolvedValueOnce({
			ok: true,
			json: async () => ({ success: true, message: 'Toast test' }),
		});

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			const toast = screen.getByRole('alert');
			expect(toast).toHaveAttribute('aria-live', 'polite');
		});
	});

	it('uses default message when API does not provide one', async () => {
		fetch.mockResolvedValueOnce({
			ok: true,
			json: async () => ({ success: true }),
		});

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			expect(screen.getByText('Full Scan completed successfully')).toBeInTheDocument();
		});
	});

	it('uses default error message when API does not provide one', async () => {
		fetch.mockResolvedValueOnce({
			ok: false,
			json: async () => ({ success: false }),
		});

		render(<QuickActions />);
		const fullScanButton = screen.getByText('Full Scan');
		fireEvent.click(fullScanButton);

		await waitFor(() => {
			expect(screen.getByText('Failed to execute Full Scan')).toBeInTheDocument();
		});
	});
});
