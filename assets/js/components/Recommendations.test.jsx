/**
 * Tests for Recommendations Component
 *
 * @package WPAdminHealth
 */

import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import Recommendations from './Recommendations';

// Mock localStorage
const localStorageMock = (() => {
	let store = {};
	return {
		getItem: (key) => store[key] || null,
		setItem: (key, value) => {
			store[key] = value.toString();
		},
		clear: () => {
			store = {};
		},
	};
})();

Object.defineProperty(window, 'localStorage', {
	value: localStorageMock,
});

// Mock window.open
global.open = jest.fn();

// Mock alert
global.alert = jest.fn();

describe('Recommendations', () => {
	const mockRecommendations = [
		{
			id: 'rec-1',
			title: 'Clean up database',
			description: 'Remove old revisions to improve performance',
			category: 'database',
			impact_estimate: 'high',
			priority: 90,
			action_type: 'cleanup',
			action_params: {
				endpoint: '/wpha/v1/cleanup/revisions',
			},
			steps: ['Backup database', 'Run cleanup script', 'Verify results'],
		},
		{
			id: 'rec-2',
			title: 'Optimize images',
			description: 'Compress large media files',
			category: 'media',
			impact_estimate: 'medium',
			priority: 60,
			action_type: 'optimize',
			action_params: {
				endpoint: '/wpha/v1/optimize/images',
			},
			steps: ['Scan for large images', 'Compress images', 'Update references'],
		},
		{
			id: 'rec-3',
			title: 'Update security settings',
			description: 'Enable two-factor authentication',
			category: 'security',
			impact_estimate: 'critical',
			priority: 95,
			action_type: 'configure',
			steps: ['Install 2FA plugin', 'Configure settings', 'Test login'],
		},
	];

	beforeEach(() => {
		localStorageMock.clear();
		jest.clearAllMocks();
		// Ensure wp.apiFetch is defined
		if (!global.wp) {
			global.wp = {};
		}
		global.wp.apiFetch = jest.fn().mockResolvedValue({ success: true });
	});

	it('renders without crashing', () => {
		render(<Recommendations recommendations={[]} />);
		expect(screen.getByText('Recommendations')).toBeInTheDocument();
	});

	it('displays loading state correctly', () => {
		render(<Recommendations recommendations={[]} loading={true} />);
		expect(screen.getByText('Loading recommendations...')).toBeInTheDocument();
	});

	it('displays empty state when no recommendations', () => {
		render(<Recommendations recommendations={[]} />);
		expect(screen.getByText('All clear!')).toBeInTheDocument();
		expect(screen.getByText(/Your site health is excellent/)).toBeInTheDocument();
	});

	it('displays correct count of recommendations', () => {
		render(<Recommendations recommendations={mockRecommendations} />);
		expect(screen.getByText('Recommendations (3)')).toBeInTheDocument();
	});

	it('renders all recommendations', () => {
		render(<Recommendations recommendations={mockRecommendations} />);
		expect(screen.getByText('Clean up database')).toBeInTheDocument();
		expect(screen.getByText('Optimize images')).toBeInTheDocument();
		expect(screen.getByText('Update security settings')).toBeInTheDocument();
	});

	it('displays recommendation descriptions', () => {
		render(<Recommendations recommendations={mockRecommendations} />);
		expect(screen.getByText('Remove old revisions to improve performance')).toBeInTheDocument();
	});

	it('expands recommendation when clicked', () => {
		render(<Recommendations recommendations={mockRecommendations} />);
		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);
		expect(screen.getByText('Steps to resolve:')).toBeInTheDocument();
		expect(screen.getByText('Backup database')).toBeInTheDocument();
	});

	it('collapses recommendation when clicked again', () => {
		render(<Recommendations recommendations={mockRecommendations} />);
		const firstRecommendation = screen.getByText('Clean up database').closest('div');

		// Expand
		fireEvent.click(firstRecommendation);
		expect(screen.getByText('Steps to resolve:')).toBeInTheDocument();

		// Collapse
		fireEvent.click(firstRecommendation);
		expect(screen.queryByText('Steps to resolve:')).not.toBeInTheDocument();
	});

	it('filters recommendations by category', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const categoryFilter = screen.getByLabelText('Filter:');
		fireEvent.change(categoryFilter, { target: { value: 'database' } });

		expect(screen.getByText('Clean up database')).toBeInTheDocument();
		expect(screen.queryByText('Optimize images')).not.toBeInTheDocument();
	});

	it('sorts recommendations by priority', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const sortSelect = screen.getByLabelText('Sort by:');
		fireEvent.change(sortSelect, { target: { value: 'priority' } });

		const recommendations = screen.getAllByRole('heading', { level: 3 });
		expect(recommendations[0]).toHaveTextContent('Update security settings'); // priority 95
		expect(recommendations[1]).toHaveTextContent('Clean up database'); // priority 90
	});

	it('sorts recommendations by category', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const sortSelect = screen.getByLabelText('Sort by:');
		fireEvent.change(sortSelect, { target: { value: 'category' } });

		const recommendations = screen.getAllByRole('heading', { level: 3 });
		expect(recommendations[0]).toHaveTextContent('Clean up database'); // database
		expect(recommendations[1]).toHaveTextContent('Optimize images'); // media
		expect(recommendations[2]).toHaveTextContent('Update security settings'); // security
	});

	it('calls onRefresh when refresh button is clicked', () => {
		const onRefresh = jest.fn();
		render(<Recommendations recommendations={mockRecommendations} onRefresh={onRefresh} />);

		const refreshButton = screen.getByText('Refresh');
		fireEvent.click(refreshButton);

		expect(onRefresh).toHaveBeenCalledTimes(1);
	});

	it('displays dismiss button when recommendation is expanded', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);

		expect(screen.getByText('Dismiss')).toBeInTheDocument();
	});

	it('dismisses recommendation when dismiss button is clicked', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		// Expand first recommendation
		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);

		// Click dismiss
		const dismissButton = screen.getByText('Dismiss');
		fireEvent.click(dismissButton);

		// Recommendation should be removed
		expect(screen.queryByText('Clean up database')).not.toBeInTheDocument();
		expect(screen.getByText('Recommendations (2)')).toBeInTheDocument();
	});

	it('saves dismissed recommendations to localStorage', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		// Expand and dismiss first recommendation
		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);
		const dismissButton = screen.getByText('Dismiss');
		fireEvent.click(dismissButton);

		// Check localStorage
		const dismissed = JSON.parse(localStorageMock.getItem('wpha_dismissed_recommendations'));
		expect(dismissed).toContain('rec-1');
	});

	it('loads dismissed recommendations from localStorage on mount', () => {
		// Pre-populate localStorage
		localStorageMock.setItem('wpha_dismissed_recommendations', JSON.stringify(['rec-1']));

		render(<Recommendations recommendations={mockRecommendations} />);

		// First recommendation should not be visible
		expect(screen.queryByText('Clean up database')).not.toBeInTheDocument();
		expect(screen.getByText('Recommendations (2)')).toBeInTheDocument();
	});

	it('shows Fix Now button for cleanup actions', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);

		expect(screen.getByText('Fix Now')).toBeInTheDocument();
	});

	it('shows Preview button when recommendation is expanded', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);

		expect(screen.getByText('Preview')).toBeInTheDocument();
	});

	it('shows Learn More button when recommendation is expanded', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);

		expect(screen.getByText('Learn More')).toBeInTheDocument();
	});

	it('opens WordPress support when Learn More is clicked', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);

		const learnMoreButton = screen.getByText('Learn More');
		fireEvent.click(learnMoreButton);

		expect(window.open).toHaveBeenCalledWith(
			expect.stringContaining('wordpress.org/support'),
			'_blank'
		);
	});

	it('shows alert when Preview is clicked', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);

		const previewButton = screen.getByText('Preview');
		fireEvent.click(previewButton);

		expect(window.alert).toHaveBeenCalled();
	});

	it('displays impact estimate badges', () => {
		render(<Recommendations recommendations={mockRecommendations} />);
		expect(screen.getByText('high')).toBeInTheDocument();
		expect(screen.getByText('medium')).toBeInTheDocument();
		expect(screen.getByText('critical')).toBeInTheDocument();
	});

	it('displays category badges', () => {
		render(<Recommendations recommendations={mockRecommendations} />);
		// Use getAllByText since category names appear both in filter dropdown and badges
		expect(screen.getAllByText('Database').length).toBeGreaterThan(0);
		expect(screen.getAllByText('Media').length).toBeGreaterThan(0);
		expect(screen.getAllByText('Security').length).toBeGreaterThan(0);
	});

	it('renders steps when recommendation is expanded', () => {
		render(<Recommendations recommendations={mockRecommendations} />);

		const firstRecommendation = screen.getByText('Clean up database').closest('div');
		fireEvent.click(firstRecommendation);

		expect(screen.getByText('Backup database')).toBeInTheDocument();
		expect(screen.getByText('Run cleanup script')).toBeInTheDocument();
		expect(screen.getByText('Verify results')).toBeInTheDocument();
	});

	it('displays "all dismissed" message when all recommendations are dismissed', () => {
		// Dismiss all recommendations
		localStorageMock.setItem('wpha_dismissed_recommendations', JSON.stringify(['rec-1', 'rec-2', 'rec-3']));

		render(<Recommendations recommendations={mockRecommendations} />);

		expect(screen.getByText('All recommendations dismissed')).toBeInTheDocument();
	});

	it('shows Run New Scan button in empty state when onRefresh is provided', () => {
		const onRefresh = jest.fn();
		render(<Recommendations recommendations={[]} onRefresh={onRefresh} />);

		const runScanButton = screen.getByText('Run New Scan');
		expect(runScanButton).toBeInTheDocument();

		fireEvent.click(runScanButton);
		expect(onRefresh).toHaveBeenCalledTimes(1);
	});
});
