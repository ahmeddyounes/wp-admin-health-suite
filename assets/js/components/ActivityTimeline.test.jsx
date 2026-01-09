/**
 * Tests for ActivityTimeline Component
 *
 * @package
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ActivityTimeline from './ActivityTimeline';

describe('ActivityTimeline', () => {
	const mockActivities = [
		{
			id: 1,
			scan_type: 'database_clean',
			items_cleaned: 10,
			bytes_freed: 1024000,
			created_at: '2024-01-01T12:00:00Z',
		},
		{
			id: 2,
			scan_type: 'media_scan',
			items_cleaned: 5,
			bytes_freed: 2048000,
			created_at: '2024-01-02T14:30:00Z',
		},
		{
			id: 3,
			scan_type: 'optimization',
			items_cleaned: 0,
			bytes_freed: 0,
			created_at: '2024-01-03T10:15:00Z',
		},
	];

	beforeEach(() => {
		// Mock wp.apiFetch
		global.wp = {
			apiFetch: jest.fn(),
		};
	});

	afterEach(() => {
		jest.clearAllMocks();
	});

	it('renders without crashing', () => {
		wp.apiFetch.mockResolvedValue({ success: true, data: [] });
		render(<ActivityTimeline />);
		expect(screen.getByText('Recent Activity')).toBeInTheDocument();
	});

	it('displays loading state initially', () => {
		wp.apiFetch.mockImplementation(() => new Promise(() => {})); // Never resolves
		render(<ActivityTimeline />);
		expect(screen.getByText('Loading activities...')).toBeInTheDocument();
	});

	it('fetches activities on mount', async () => {
		wp.apiFetch.mockResolvedValue({ success: true, data: mockActivities });
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(wp.apiFetch).toHaveBeenCalledWith({
				path: '/wpha/v1/activity',
				method: 'GET',
			});
		});
	});

	it('displays activities after successful fetch', async () => {
		wp.apiFetch.mockResolvedValue({ success: true, data: mockActivities });
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('Database Cleanup')).toBeInTheDocument();
			expect(screen.getByText('Media Library Scan')).toBeInTheDocument();
			expect(screen.getByText('Site Optimization')).toBeInTheDocument();
		});
	});

	it('displays items cleaned count', async () => {
		wp.apiFetch.mockResolvedValue({ success: true, data: mockActivities });
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText(/10 items affected/)).toBeInTheDocument();
			expect(screen.getByText(/5 items affected/)).toBeInTheDocument();
		});
	});

	it('displays bytes freed formatted correctly', async () => {
		wp.apiFetch.mockResolvedValue({ success: true, data: mockActivities });
		render(<ActivityTimeline />);

		await waitFor(() => {
			// 1024000 bytes = 1000 KB
			expect(screen.getByText(/1000 KB freed/i)).toBeInTheDocument();
			// 2048000 bytes = 1.95 MB (formatted as "1.95 MB")
			expect(screen.getByText(/1.95 MB freed/i)).toBeInTheDocument();
		});
	});

	it('does not display items cleaned when count is 0', async () => {
		wp.apiFetch.mockResolvedValue({
			success: true,
			data: [mockActivities[2]], // optimization with 0 items cleaned
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('Site Optimization')).toBeInTheDocument();
		});

		expect(screen.queryByText(/items affected/)).not.toBeInTheDocument();
	});

	it('does not display bytes freed when count is 0', async () => {
		wp.apiFetch.mockResolvedValue({
			success: true,
			data: [mockActivities[2]], // optimization with 0 bytes freed
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('Site Optimization')).toBeInTheDocument();
		});

		expect(screen.queryByText(/freed/)).not.toBeInTheDocument();
	});

	it('displays error state when fetch fails', async () => {
		wp.apiFetch.mockRejectedValue(new Error('Network error'));
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText(/Network error/)).toBeInTheDocument();
		});
	});

	it('shows Try Again button on error', async () => {
		wp.apiFetch.mockRejectedValue(new Error('Network error'));
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('Try Again')).toBeInTheDocument();
		});
	});

	it('retries fetch when Try Again is clicked', async () => {
		wp.apiFetch.mockRejectedValueOnce(new Error('Network error'));
		wp.apiFetch.mockResolvedValueOnce({
			success: true,
			data: mockActivities,
		});

		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('Try Again')).toBeInTheDocument();
		});

		fireEvent.click(screen.getByText('Try Again'));

		await waitFor(() => {
			expect(screen.getByText('Database Cleanup')).toBeInTheDocument();
		});

		expect(wp.apiFetch).toHaveBeenCalledTimes(2);
	});

	it('displays empty state when no activities', async () => {
		wp.apiFetch.mockResolvedValue({ success: true, data: [] });
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('No Activity Yet')).toBeInTheDocument();
			expect(
				screen.getByText(/Get started by running your first scan/)
			).toBeInTheDocument();
		});
	});

	it('shows Run Your First Scan button in empty state', async () => {
		wp.apiFetch.mockResolvedValue({ success: true, data: [] });
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('Run Your First Scan')).toBeInTheDocument();
		});
	});

	it('uses correct icons for activity types', async () => {
		wp.apiFetch.mockResolvedValue({ success: true, data: mockActivities });
		const { container } = render(<ActivityTimeline />);

		await waitFor(() => {
			expect(
				container.querySelector('.dashicons-database')
			).toBeInTheDocument();
			expect(
				container.querySelector('.dashicons-format-image')
			).toBeInTheDocument();
			expect(
				container.querySelector('.dashicons-performance')
			).toBeInTheDocument();
		});
	});

	it('uses default icon for unknown activity type', async () => {
		wp.apiFetch.mockResolvedValue({
			success: true,
			data: [
				{
					id: 99,
					scan_type: 'unknown_type',
					items_cleaned: 1,
					bytes_freed: 100,
					created_at: '2024-01-01T12:00:00Z',
				},
			],
		});
		const { container } = render(<ActivityTimeline />);

		await waitFor(() => {
			expect(
				container.querySelector('.dashicons-admin-generic')
			).toBeInTheDocument();
		});
	});

	it('uses fallback description for unknown activity type', async () => {
		wp.apiFetch.mockResolvedValue({
			success: true,
			data: [
				{
					id: 99,
					scan_type: 'custom_scan',
					items_cleaned: 1,
					bytes_freed: 100,
					created_at: '2024-01-01T12:00:00Z',
				},
			],
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('custom_scan')).toBeInTheDocument();
		});
	});

	it('displays activities as a list', async () => {
		wp.apiFetch.mockResolvedValue({ success: true, data: mockActivities });
		render(<ActivityTimeline />);

		await waitFor(() => {
			const list = screen.getByRole('list');
			expect(list).toBeInTheDocument();
		});
	});

	it('handles API response without success flag', async () => {
		wp.apiFetch.mockResolvedValue({ message: 'Bad response' });
		render(<ActivityTimeline />);

		await waitFor(() => {
			// The component displays the message from the response
			expect(screen.getByText(/Bad response/)).toBeInTheDocument();
		});
	});

	it('uses singular form for 1 item', async () => {
		wp.apiFetch.mockResolvedValue({
			success: true,
			data: [
				{
					id: 1,
					scan_type: 'database_clean',
					items_cleaned: 1,
					bytes_freed: 1024,
					created_at: '2024-01-01T12:00:00Z',
				},
			],
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText(/1 item affected/)).toBeInTheDocument();
		});
	});

	it('uses plural form for multiple items', async () => {
		wp.apiFetch.mockResolvedValue({
			success: true,
			data: [
				{
					id: 1,
					scan_type: 'database_clean',
					items_cleaned: 2,
					bytes_freed: 1024,
					created_at: '2024-01-01T12:00:00Z',
				},
			],
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText(/2 items affected/)).toBeInTheDocument();
		});
	});
});
