/**
 * Tests for ActivityTimeline Component
 *
 * @package
 */

import React from 'react';
import {
	render,
	screen,
	fireEvent,
	waitFor,
	act,
} from '@testing-library/react';
import ActivityTimeline, {
	formatBytes,
	getRelativeTime,
} from './ActivityTimeline';
import apiClient from '../utils/api.js';

// Mock the API client
jest.mock('../utils/api.js');

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
		// Reset mocks
		jest.clearAllMocks();
		jest.useFakeTimers();
		// Mock global wp for any remaining usages
		global.wp = {
			apiFetch: jest.fn(),
		};
	});

	afterEach(() => {
		jest.clearAllMocks();
		jest.useRealTimers();
	});

	it('renders without crashing', () => {
		apiClient.get.mockResolvedValue({ success: true, data: [] });
		render(<ActivityTimeline />);
		expect(screen.getByText('Recent Activity')).toBeInTheDocument();
	});

	it('displays loading state initially', () => {
		apiClient.get.mockImplementation(() => new Promise(() => {})); // Never resolves
		render(<ActivityTimeline />);
		expect(screen.getByText('Loading activities...')).toBeInTheDocument();
	});

	it('fetches activities on mount', async () => {
		apiClient.get.mockResolvedValue({
			success: true,
			data: mockActivities,
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(apiClient.get).toHaveBeenCalledWith('activity', {
				page: 1,
				per_page: 10,
			});
		});
	});

	it('displays activities after successful fetch', async () => {
		apiClient.get.mockResolvedValue({
			success: true,
			data: mockActivities,
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('Database Cleanup')).toBeInTheDocument();
			expect(screen.getByText('Media Library Scan')).toBeInTheDocument();
			expect(screen.getByText('Site Optimization')).toBeInTheDocument();
		});
	});

	it('displays items cleaned count', async () => {
		apiClient.get.mockResolvedValue({
			success: true,
			data: mockActivities,
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText(/10 items affected/)).toBeInTheDocument();
			expect(screen.getByText(/5 items affected/)).toBeInTheDocument();
		});
	});

	it('displays bytes freed formatted correctly', async () => {
		apiClient.get.mockResolvedValue({
			success: true,
			data: mockActivities,
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			// 1024000 bytes = 1000 KB
			expect(screen.getByText(/1000 KB freed/i)).toBeInTheDocument();
			// 2048000 bytes = 1.95 MB (formatted as "1.95 MB")
			expect(screen.getByText(/1.95 MB freed/i)).toBeInTheDocument();
		});
	});

	it('does not display items cleaned when count is 0', async () => {
		apiClient.get.mockResolvedValue({
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
		apiClient.get.mockResolvedValue({
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
		apiClient.get.mockRejectedValue(new Error('Network error'));
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText(/Network error/)).toBeInTheDocument();
		});
	});

	it('shows Try Again button on error', async () => {
		apiClient.get.mockRejectedValue(new Error('Network error'));
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('Try Again')).toBeInTheDocument();
		});
	});

	it('retries fetch when Try Again is clicked', async () => {
		apiClient.get.mockRejectedValueOnce(new Error('Network error'));
		apiClient.get.mockResolvedValueOnce({
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

		expect(apiClient.get).toHaveBeenCalledTimes(2);
	});

	it('displays empty state when no activities', async () => {
		apiClient.get.mockResolvedValue({ success: true, data: [] });
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('No Activity Yet')).toBeInTheDocument();
			expect(
				screen.getByText(/Get started by running your first scan/)
			).toBeInTheDocument();
		});
	});

	it('shows Run Your First Scan button in empty state', async () => {
		apiClient.get.mockResolvedValue({ success: true, data: [] });
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByText('Run Your First Scan')).toBeInTheDocument();
		});
	});

	it('uses correct icons for activity types', async () => {
		apiClient.get.mockResolvedValue({
			success: true,
			data: mockActivities,
		});
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
		apiClient.get.mockResolvedValue({
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
		apiClient.get.mockResolvedValue({
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
		apiClient.get.mockResolvedValue({
			success: true,
			data: mockActivities,
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			const list = screen.getByRole('list');
			expect(list).toBeInTheDocument();
		});
	});

	it('handles API response without success flag', async () => {
		apiClient.get.mockResolvedValue({ message: 'Bad response' });
		render(<ActivityTimeline />);

		await waitFor(() => {
			// The component displays the message from the response
			expect(screen.getByText(/Bad response/)).toBeInTheDocument();
		});
	});

	it('uses singular form for 1 item', async () => {
		apiClient.get.mockResolvedValue({
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
		apiClient.get.mockResolvedValue({
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

	describe('Pagination', () => {
		it('shows pagination when totalPages > 1', async () => {
			apiClient.get.mockResolvedValue({
				success: true,
				data: {
					items: mockActivities,
					total: 25,
				},
			});
			render(<ActivityTimeline pageSize={10} />);

			await waitFor(() => {
				expect(screen.getByText('Page 1 of 3')).toBeInTheDocument();
			});
		});

		it('hides pagination when totalPages <= 1', async () => {
			apiClient.get.mockResolvedValue({
				success: true,
				data: mockActivities,
			});
			render(<ActivityTimeline />);

			await waitFor(() => {
				expect(
					screen.getByText('Database Cleanup')
				).toBeInTheDocument();
			});

			expect(
				screen.queryByText(/Page \d+ of \d+/)
			).not.toBeInTheDocument();
		});

		it('navigates to next page', async () => {
			apiClient.get.mockResolvedValue({
				success: true,
				data: {
					items: mockActivities,
					total: 25,
				},
			});
			render(<ActivityTimeline pageSize={10} />);

			await waitFor(() => {
				expect(screen.getByText('Page 1 of 3')).toBeInTheDocument();
			});

			const nextButton = screen.getByLabelText('Next page');
			fireEvent.click(nextButton);

			await waitFor(() => {
				expect(apiClient.get).toHaveBeenCalledWith('activity', {
					page: 2,
					per_page: 10,
				});
			});
		});

		it('navigates to previous page', async () => {
			apiClient.get.mockResolvedValueOnce({
				success: true,
				data: {
					items: mockActivities,
					total: 25,
				},
			});

			render(<ActivityTimeline pageSize={10} />);

			await waitFor(() => {
				expect(screen.getByText('Page 1 of 3')).toBeInTheDocument();
			});

			// Go to page 2 first
			apiClient.get.mockResolvedValueOnce({
				success: true,
				data: {
					items: mockActivities,
					total: 25,
				},
			});
			fireEvent.click(screen.getByLabelText('Next page'));

			await waitFor(() => {
				expect(screen.getByText('Page 2 of 3')).toBeInTheDocument();
			});

			// Go back to page 1
			apiClient.get.mockResolvedValueOnce({
				success: true,
				data: {
					items: mockActivities,
					total: 25,
				},
			});
			fireEvent.click(screen.getByLabelText('Previous page'));

			await waitFor(() => {
				expect(apiClient.get).toHaveBeenLastCalledWith('activity', {
					page: 1,
					per_page: 10,
				});
			});
		});

		it('disables previous button on first page', async () => {
			apiClient.get.mockResolvedValue({
				success: true,
				data: {
					items: mockActivities,
					total: 25,
				},
			});
			render(<ActivityTimeline pageSize={10} />);

			await waitFor(() => {
				const prevButton = screen.getByLabelText('Previous page');
				expect(prevButton).toBeDisabled();
			});
		});

		it('disables next button on last page', async () => {
			apiClient.get.mockResolvedValueOnce({
				success: true,
				data: {
					items: mockActivities,
					total: 25,
				},
			});
			render(<ActivityTimeline pageSize={10} />);

			await waitFor(() => {
				expect(screen.getByText('Page 1 of 3')).toBeInTheDocument();
			});

			// Go to page 3
			apiClient.get.mockResolvedValueOnce({
				success: true,
				data: {
					items: mockActivities,
					total: 25,
				},
			});
			fireEvent.click(screen.getByLabelText('Next page'));

			await waitFor(() => {
				expect(screen.getByText('Page 2 of 3')).toBeInTheDocument();
			});

			apiClient.get.mockResolvedValueOnce({
				success: true,
				data: {
					items: mockActivities,
					total: 25,
				},
			});
			fireEvent.click(screen.getByLabelText('Next page'));

			await waitFor(() => {
				expect(screen.getByText('Page 3 of 3')).toBeInTheDocument();
				const nextButton = screen.getByLabelText('Next page');
				expect(nextButton).toBeDisabled();
			});
		});
	});

	describe('Auto-refresh', () => {
		it('auto-refreshes at the specified interval', async () => {
			apiClient.get.mockResolvedValue({
				success: true,
				data: mockActivities,
			});
			render(
				<ActivityTimeline autoRefresh={true} refreshInterval={5000} />
			);

			await waitFor(() => {
				expect(
					screen.getByText('Database Cleanup')
				).toBeInTheDocument();
			});

			expect(apiClient.get).toHaveBeenCalledTimes(1);

			// Advance timer
			act(() => {
				jest.advanceTimersByTime(5000);
			});

			await waitFor(() => {
				expect(apiClient.get).toHaveBeenCalledTimes(2);
			});
		});

		it('does not auto-refresh when autoRefresh is false', async () => {
			apiClient.get.mockResolvedValue({
				success: true,
				data: mockActivities,
			});
			render(
				<ActivityTimeline autoRefresh={false} refreshInterval={5000} />
			);

			await waitFor(() => {
				expect(
					screen.getByText('Database Cleanup')
				).toBeInTheDocument();
			});

			expect(apiClient.get).toHaveBeenCalledTimes(1);

			act(() => {
				jest.advanceTimersByTime(10000);
			});

			// Should still be 1 call
			expect(apiClient.get).toHaveBeenCalledTimes(1);
		});

		it('shows refresh button in header when activities exist', async () => {
			apiClient.get.mockResolvedValue({
				success: true,
				data: mockActivities,
			});
			render(<ActivityTimeline />);

			await waitFor(() => {
				expect(
					screen.getByLabelText('Refresh activities')
				).toBeInTheDocument();
			});
		});

		it('manual refresh works', async () => {
			apiClient.get.mockResolvedValue({
				success: true,
				data: mockActivities,
			});
			render(<ActivityTimeline autoRefresh={false} />);

			await waitFor(() => {
				expect(
					screen.getByText('Database Cleanup')
				).toBeInTheDocument();
			});

			expect(apiClient.get).toHaveBeenCalledTimes(1);

			fireEvent.click(screen.getByLabelText('Refresh activities'));

			await waitFor(() => {
				expect(apiClient.get).toHaveBeenCalledTimes(2);
			});
		});
	});

	describe('Custom pageSize', () => {
		it('uses custom pageSize in API call', async () => {
			apiClient.get.mockResolvedValue({
				success: true,
				data: mockActivities,
			});
			render(<ActivityTimeline pageSize={5} />);

			await waitFor(() => {
				expect(apiClient.get).toHaveBeenCalledWith('activity', {
					page: 1,
					per_page: 5,
				});
			});
		});
	});
});

describe('formatBytes', () => {
	it('returns "0 Bytes" for 0', () => {
		expect(formatBytes(0)).toBe('0 Bytes');
	});

	it('formats bytes correctly', () => {
		expect(formatBytes(500)).toBe('500 Bytes');
	});

	it('formats KB correctly', () => {
		expect(formatBytes(1024)).toBe('1 KB');
		expect(formatBytes(1536)).toBe('1.5 KB');
	});

	it('formats MB correctly', () => {
		expect(formatBytes(1048576)).toBe('1 MB');
		expect(formatBytes(1572864)).toBe('1.5 MB');
	});

	it('formats GB correctly', () => {
		expect(formatBytes(1073741824)).toBe('1 GB');
	});

	it('formats TB correctly', () => {
		expect(formatBytes(1099511627776)).toBe('1 TB');
	});

	it('handles negative numbers', () => {
		expect(formatBytes(-100)).toBe('0 Bytes');
	});

	it('handles NaN', () => {
		expect(formatBytes(NaN)).toBe('0 Bytes');
	});

	it('handles non-number input', () => {
		expect(formatBytes('invalid')).toBe('0 Bytes');
		expect(formatBytes(null)).toBe('0 Bytes');
		expect(formatBytes(undefined)).toBe('0 Bytes');
	});
});

describe('getRelativeTime', () => {
	beforeEach(() => {
		jest.useFakeTimers();
		jest.setSystemTime(new Date('2024-01-15T12:00:00Z'));
	});

	afterEach(() => {
		jest.useRealTimers();
	});

	it('returns "Unknown time" for null/undefined', () => {
		expect(getRelativeTime(null)).toBe('Unknown time');
		expect(getRelativeTime(undefined)).toBe('Unknown time');
		expect(getRelativeTime('')).toBe('Unknown time');
	});

	it('returns "Invalid date" for invalid date strings', () => {
		expect(getRelativeTime('not-a-date')).toBe('Invalid date');
	});

	it('returns "In the future" for future dates', () => {
		expect(getRelativeTime('2024-01-16T12:00:00Z')).toBe('In the future');
	});

	it('formats seconds ago correctly', () => {
		expect(getRelativeTime('2024-01-15T11:59:59Z')).toBe('1 second ago');
		expect(getRelativeTime('2024-01-15T11:59:30Z')).toBe('30 seconds ago');
	});

	it('formats minutes ago correctly', () => {
		expect(getRelativeTime('2024-01-15T11:59:00Z')).toBe('1 minute ago');
		expect(getRelativeTime('2024-01-15T11:30:00Z')).toBe('30 minutes ago');
	});

	it('formats hours ago correctly', () => {
		expect(getRelativeTime('2024-01-15T11:00:00Z')).toBe('1 hour ago');
		expect(getRelativeTime('2024-01-15T06:00:00Z')).toBe('6 hours ago');
	});

	it('formats days ago correctly', () => {
		expect(getRelativeTime('2024-01-14T12:00:00Z')).toBe('1 day ago');
		expect(getRelativeTime('2024-01-08T12:00:00Z')).toBe('7 days ago');
	});

	it('formats months ago correctly', () => {
		expect(getRelativeTime('2023-12-15T12:00:00Z')).toBe('1 month ago');
		expect(getRelativeTime('2023-07-15T12:00:00Z')).toBe('6 months ago');
	});

	it('formats years ago correctly', () => {
		expect(getRelativeTime('2023-01-15T12:00:00Z')).toBe('1 year ago');
		expect(getRelativeTime('2022-01-15T12:00:00Z')).toBe('2 years ago');
	});
});

describe('Accessibility', () => {
	beforeEach(() => {
		jest.clearAllMocks();
	});

	afterEach(() => {
		jest.clearAllMocks();
	});

	it('has proper ARIA labels on timeline list', async () => {
		apiClient.get.mockResolvedValue({
			success: true,
			data: [
				{
					id: 1,
					scan_type: 'database_clean',
					items_cleaned: 10,
					bytes_freed: 1024,
					created_at: '2024-01-01T12:00:00Z',
				},
			],
		});
		render(<ActivityTimeline />);

		await waitFor(() => {
			const list = screen.getByRole('list', {
				name: 'Activity timeline',
			});
			expect(list).toBeInTheDocument();
		});
	});

	it('has proper ARIA labels on pagination', async () => {
		apiClient.get.mockResolvedValue({
			success: true,
			data: {
				items: [
					{
						id: 1,
						scan_type: 'database_clean',
						items_cleaned: 10,
						bytes_freed: 1024,
						created_at: '2024-01-01T12:00:00Z',
					},
				],
				total: 25,
			},
		});
		render(<ActivityTimeline pageSize={10} />);

		await waitFor(() => {
			const nav = screen.getByRole('navigation', {
				name: 'Activity pagination',
			});
			expect(nav).toBeInTheDocument();
		});
	});

	it('has role="status" on loading state', async () => {
		apiClient.get.mockImplementation(() => new Promise(() => {}));
		render(<ActivityTimeline />);

		expect(screen.getByRole('status')).toBeInTheDocument();
	});

	it('has role="alert" on error state', async () => {
		apiClient.get.mockRejectedValue(new Error('Network error'));
		render(<ActivityTimeline />);

		await waitFor(() => {
			expect(screen.getByRole('alert')).toBeInTheDocument();
		});
	});
});
