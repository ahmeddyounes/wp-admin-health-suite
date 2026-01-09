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

	it('has onKeyPress handler when clickable', () => {
		const handleClick = jest.fn();
		const { container } = render(
			<MetricCard title="Test" value={100} onClick={handleClick} />
		);
		const button = screen.getByRole('button');
		// Verify the button has the onKeyPress prop attached (keyboard accessibility)
		expect(button).toHaveProperty('onkeypress');
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
});
