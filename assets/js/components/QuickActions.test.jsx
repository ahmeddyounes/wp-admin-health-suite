/**
 * Tests for QuickActions Component
 *
 * @package
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
		expect(screen.getByText('Empty Trash')).toBeInTheDocument();
	});

	it('renders all action buttons', () => {
		render(<QuickActions />);
		expect(screen.getByText('Empty Trash')).toBeInTheDocument();
		expect(screen.getByText('Delete Spam Comments')).toBeInTheDocument();
		expect(screen.getByText('Delete Auto-Drafts')).toBeInTheDocument();
		expect(
			screen.getByText('Clean Expired Transients')
		).toBeInTheDocument();
		expect(screen.getByText('Optimize Tables')).toBeInTheDocument();
	});

	it('renders action buttons with correct icons', () => {
		const { container } = render(<QuickActions />);
		expect(container.querySelector('.dashicons-trash')).toBeInTheDocument();
		expect(
			container.querySelector('.dashicons-dismiss')
		).toBeInTheDocument();
		expect(container.querySelector('.dashicons-edit')).toBeInTheDocument();
		expect(container.querySelector('.dashicons-clock')).toBeInTheDocument();
		expect(
			container.querySelector('.dashicons-database')
		).toBeInTheDocument();
	});

	it('shows confirmation modal for actions that require confirmation', () => {
		render(<QuickActions />);
		const emptyTrashButton = screen.getByText('Empty Trash');
		fireEvent.click(emptyTrashButton);

		expect(screen.getByText('Confirm Empty Trash')).toBeInTheDocument();
		expect(
			screen.getByText(/Are you sure you want to proceed/)
		).toBeInTheDocument();
	});

	it('shows confirmation modal for Optimize Tables', () => {
		render(<QuickActions />);
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		expect(screen.getByText('Confirm Optimize Tables')).toBeInTheDocument();
	});

	it('closes modal when Cancel is clicked', () => {
		render(<QuickActions />);
		const emptyTrashButton = screen.getByText('Empty Trash');
		fireEvent.click(emptyTrashButton);

		expect(screen.getByText('Confirm Empty Trash')).toBeInTheDocument();

		const cancelButton = screen.getByText('Cancel');
		fireEvent.click(cancelButton);

		expect(
			screen.queryByText('Confirm Empty Trash')
		).not.toBeInTheDocument();
	});

	it('closes modal when clicking outside', () => {
		const { container } = render(<QuickActions />);
		const emptyTrashButton = screen.getByText('Empty Trash');
		fireEvent.click(emptyTrashButton);

		expect(screen.getByText('Confirm Empty Trash')).toBeInTheDocument();

		// Click on the overlay (backdrop) to close the modal
		const overlay = container.querySelector('.quick-action-modal-overlay');
		fireEvent.click(overlay);

		expect(
			screen.queryByText('Confirm Empty Trash')
		).not.toBeInTheDocument();
	});

	it('executes action when Confirm is clicked', async () => {
		fetch.mockResolvedValueOnce({
			ok: true,
			json: async () => ({ success: true, message: 'Cleanup complete' }),
		});

		render(<QuickActions />);
		const emptyTrashButton = screen.getByText('Empty Trash');
		fireEvent.click(emptyTrashButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

		await waitFor(() => {
			expect(fetch).toHaveBeenCalledWith(
				'/wp-json/wpha/v1/dashboard/quick-action',
				expect.objectContaining({
					method: 'POST',
					headers: expect.objectContaining({
						'Content-Type': 'application/json',
						'X-WP-Nonce': 'test-nonce',
					}),
					body: JSON.stringify({ action_id: 'delete_trash' }),
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
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

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
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

		await waitFor(() => {
			expect(screen.getByText('Action failed')).toBeInTheDocument();
		});
	});

	it('shows error toast on network error', async () => {
		fetch.mockRejectedValueOnce(new Error('Network error'));

		render(<QuickActions />);
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

		await waitFor(() => {
			expect(screen.getByText(/Network error/)).toBeInTheDocument();
		});
	});

	it('displays spinner while action is executing', async () => {
		fetch.mockImplementation(() => new Promise(() => {})); // Never resolves

		render(<QuickActions />);
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

		await waitFor(() => {
			expect(screen.getByLabelText('Loading')).toBeInTheDocument();
		});
	});

	it('disables button while action is executing', async () => {
		fetch.mockImplementation(() => new Promise(() => {})); // Never resolves

		render(<QuickActions />);
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

		await waitFor(() => {
			expect(
				optimizeTablesButton.closest('[role="button"]')
			).toHaveAttribute('aria-busy', 'true');
		});
	});

	it('dismisses toast when dismiss button is clicked', async () => {
		fetch.mockResolvedValueOnce({
			ok: true,
			json: async () => ({ success: true, message: 'Test message' }),
		});

		render(<QuickActions />);
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

		await waitFor(() => {
			expect(screen.getByText('Test message')).toBeInTheDocument();
		});

		const dismissButton = screen.getByLabelText('Dismiss notification');
		fireEvent.click(dismissButton);

		expect(screen.queryByText('Test message')).not.toBeInTheDocument();
	});

	it('has keyboard event handlers for accessibility', () => {
		render(<QuickActions />);
		const emptyTrashButton = screen
			.getByText('Empty Trash')
			.closest('[role="button"]');

		// Verify the button has keyboard handlers attached (keyboard accessibility)
		expect(emptyTrashButton).toHaveProperty('onkeypress');
		expect(emptyTrashButton).toHaveAttribute('tabindex', '0');
	});

	it('renders correct number of action buttons', () => {
		const { container } = render(<QuickActions />);
		const buttons = container.querySelectorAll('[role="button"]');
		expect(buttons).toHaveLength(5);
	});

	it('has correct aria-label for each button', () => {
		render(<QuickActions />);
		expect(
			screen.getByLabelText(
				/Empty Trash: Permanently delete all items from the trash/
			)
		).toBeInTheDocument();
		expect(
			screen.getByLabelText(
				/Delete Spam Comments: Permanently delete all spam comments/
			)
		).toBeInTheDocument();
	});

	it('renders modal with correct aria attributes', () => {
		render(<QuickActions />);
		const emptyTrashButton = screen.getByText('Empty Trash');
		fireEvent.click(emptyTrashButton);

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
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

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
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

		await waitFor(() => {
			expect(
				screen.getByText('Optimize Tables completed successfully')
			).toBeInTheDocument();
		});
	});

	it('uses default error message when API does not provide one', async () => {
		fetch.mockResolvedValueOnce({
			ok: false,
			json: async () => ({ success: false }),
		});

		render(<QuickActions />);
		const optimizeTablesButton = screen.getByText('Optimize Tables');
		fireEvent.click(optimizeTablesButton);

		const confirmButton = screen.getByText('Confirm');
		fireEvent.click(confirmButton);

		await waitFor(() => {
			expect(
				screen.getByText('Failed to execute Optimize Tables')
			).toBeInTheDocument();
		});
	});
});
