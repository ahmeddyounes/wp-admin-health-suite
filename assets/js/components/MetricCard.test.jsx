/**
 * Tests for MetricCard Component
 *
 * @package
 */

import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import MetricCard from './MetricCard';

describe('MetricCard', () => {
	it('renders without crashing', () => {
		render(<MetricCard title="Test Metric" value={100} />);
		expect(screen.getByText('Test Metric')).toBeInTheDocument();
	});

	it('displays the title correctly', () => {
		render(<MetricCard title="Database Size" value={250} />);
		expect(screen.getByText('Database Size')).toBeInTheDocument();
	});

	it('displays the value correctly', () => {
		render(<MetricCard title="Test" value={500} />);
		expect(screen.getByText('500')).toBeInTheDocument();
	});

	it('displays the unit when provided', () => {
		render(<MetricCard title="Test" value={250} unit="MB" />);
		expect(screen.getByText('MB')).toBeInTheDocument();
	});

	it('does not display unit when not provided', () => {
		const { container } = render(<MetricCard title="Test" value={250} />);
		expect(container.textContent).not.toMatch(/MB|KB|GB/);
	});

	it('displays trend indicator for up trend', () => {
		render(
			<MetricCard title="Test" value={100} trend="up" trendValue="5.2" />
		);
		expect(screen.getByText(/↑ 5.2%/)).toBeInTheDocument();
	});

	it('displays trend indicator for down trend', () => {
		render(
			<MetricCard
				title="Test"
				value={100}
				trend="down"
				trendValue="3.1"
			/>
		);
		expect(screen.getByText(/↓ 3.1%/)).toBeInTheDocument();
	});

	it('displays trend indicator for neutral trend', () => {
		render(
			<MetricCard
				title="Test"
				value={100}
				trend="neutral"
				trendValue="0"
			/>
		);
		expect(screen.getByText(/→ 0%/)).toBeInTheDocument();
	});

	it('handles invalid trend by defaulting to neutral', () => {
		render(
			<MetricCard
				title="Test"
				value={100}
				trend="invalid"
				trendValue="5"
			/>
		);
		expect(screen.getByText(/→ 5%/)).toBeInTheDocument();
	});

	it('does not display trend value when null', () => {
		const { container } = render(
			<MetricCard title="Test" value={100} trend="up" trendValue={null} />
		);
		expect(container.textContent).not.toMatch(/%/);
	});

	it('renders with correct aria-label', () => {
		render(
			<MetricCard
				title="Database Size"
				value={250}
				unit="MB"
				trendValue="5.2"
				trend="up"
			/>
		);
		expect(
			screen.getByLabelText(
				/Database Size: 250 MB, trend up by 5.2 percent/
			)
		).toBeInTheDocument();
	});

	it('renders as button role when onClick is provided', () => {
		const handleClick = jest.fn();
		render(<MetricCard title="Test" value={100} onClick={handleClick} />);
		expect(screen.getByRole('button')).toBeInTheDocument();
	});

	it('renders as article role when onClick is not provided', () => {
		render(<MetricCard title="Test" value={100} />);
		expect(screen.getByRole('article')).toBeInTheDocument();
	});

	it('calls onClick when clicked', () => {
		const handleClick = jest.fn();
		render(<MetricCard title="Test" value={100} onClick={handleClick} />);
		fireEvent.click(screen.getByRole('button'));
		expect(handleClick).toHaveBeenCalledTimes(1);
	});

	it('calls onClick when Enter key is pressed', () => {
		const handleClick = jest.fn();
		render(<MetricCard title="Test" value={100} onClick={handleClick} />);
		const button = screen.getByRole('button');
		fireEvent.keyDown(button, { key: 'Enter' });
		expect(handleClick).toHaveBeenCalledTimes(1);
	});

	it('calls onClick when Space key is pressed', () => {
		const handleClick = jest.fn();
		render(<MetricCard title="Test" value={100} onClick={handleClick} />);
		const button = screen.getByRole('button');
		fireEvent.keyDown(button, { key: ' ' });
		expect(handleClick).toHaveBeenCalledTimes(1);
	});

	it('does not call onClick for other keys', () => {
		const handleClick = jest.fn();
		render(<MetricCard title="Test" value={100} onClick={handleClick} />);
		const button = screen.getByRole('button');
		fireEvent.keyDown(button, { key: 'Tab' });
		expect(handleClick).not.toHaveBeenCalled();
	});

	it('has tabIndex 0 when clickable', () => {
		const handleClick = jest.fn();
		render(<MetricCard title="Test" value={100} onClick={handleClick} />);
		expect(screen.getByRole('button')).toHaveAttribute('tabIndex', '0');
	});

	it('does not have tabIndex when not clickable', () => {
		render(<MetricCard title="Test" value={100} />);
		expect(screen.getByRole('article')).not.toHaveAttribute('tabIndex');
	});

	it('uses default icon when not specified', () => {
		const { container } = render(<MetricCard title="Test" value={100} />);
		expect(
			container.querySelector('.dashicons-chart-bar')
		).toBeInTheDocument();
	});

	it('uses custom icon when specified', () => {
		const { container } = render(
			<MetricCard title="Test" value={100} icon="dashicons-database" />
		);
		expect(
			container.querySelector('.dashicons-database')
		).toBeInTheDocument();
	});

	it('renders icon with aria-hidden', () => {
		const { container } = render(<MetricCard title="Test" value={100} />);
		const icon = container.querySelector('.dashicons');
		expect(icon).toHaveAttribute('aria-hidden', 'true');
	});

	describe('loading state', () => {
		it('shows loading skeleton when loading is true', () => {
			const { container } = render(
				<MetricCard title="Test" value={100} loading={true} />
			);
			// Should show loading icon
			expect(
				container.querySelector('.dashicons-update')
			).toBeInTheDocument();
		});

		it('has aria-busy attribute when loading', () => {
			render(<MetricCard title="Test" value={100} loading={true} />);
			expect(screen.getByRole('article')).toHaveAttribute(
				'aria-busy',
				'true'
			);
		});

		it('does not display value content when loading', () => {
			render(
				<MetricCard title="Test" value={100} unit="MB" loading={true} />
			);
			expect(screen.queryByText('100')).not.toBeInTheDocument();
		});

		it('prevents click when loading', () => {
			const handleClick = jest.fn();
			render(
				<MetricCard
					title="Test"
					value={100}
					onClick={handleClick}
					loading={true}
				/>
			);
			fireEvent.click(screen.getByRole('button'));
			expect(handleClick).not.toHaveBeenCalled();
		});

		it('has correct aria-label when loading', () => {
			render(<MetricCard title="Database Size" loading={true} />);
			expect(
				screen.getByLabelText('Database Size: Loading')
			).toBeInTheDocument();
		});
	});

	describe('error state', () => {
		it('displays error message when error prop is provided', () => {
			render(
				<MetricCard
					title="Test"
					value={100}
					error="Failed to load data"
				/>
			);
			expect(screen.getByText('Failed to load data')).toBeInTheDocument();
		});

		it('still displays title when error occurs', () => {
			render(
				<MetricCard
					title="Database Size"
					value={100}
					error="Failed to load data"
				/>
			);
			expect(screen.getByText('Database Size')).toBeInTheDocument();
		});

		it('has error-styled border when error prop is provided', () => {
			const { container } = render(
				<MetricCard title="Test" value={100} error="Error occurred" />
			);
			const card = container.querySelector('.metric-card');
			expect(card).toHaveStyle({ borderColor: '#d63638' });
		});

		it('has correct aria-label when error occurs', () => {
			render(
				<MetricCard title="Database Size" error="Connection failed" />
			);
			expect(
				screen.getByLabelText(
					'Database Size: Error - Connection failed'
				)
			).toBeInTheDocument();
		});
	});

	describe('null/undefined value handling', () => {
		it('displays placeholder when value is null', () => {
			render(<MetricCard title="Test" value={null} />);
			expect(screen.getByText('—')).toBeInTheDocument();
		});

		it('displays placeholder when value is undefined', () => {
			render(<MetricCard title="Test" />);
			expect(screen.getByText('—')).toBeInTheDocument();
		});

		it('displays 0 when value is 0', () => {
			render(<MetricCard title="Test" value={0} />);
			expect(screen.getByText('0')).toBeInTheDocument();
		});

		it('displays string values correctly', () => {
			render(<MetricCard title="Test" value="N/A" />);
			expect(screen.getByText('N/A')).toBeInTheDocument();
		});
	});
});
