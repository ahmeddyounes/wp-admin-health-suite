/**
 * Tests for HealthScoreCircle Component
 *
 * @package
 */

import React from 'react';
import { render, screen } from '@testing-library/react';
import HealthScoreCircle from './HealthScoreCircle';

describe('HealthScoreCircle', () => {
	beforeEach(() => {
		jest.useFakeTimers();
		// Mock matchMedia for reduced motion tests
		Object.defineProperty(window, 'matchMedia', {
			writable: true,
			value: jest.fn().mockImplementation((query) => ({
				matches: false,
				media: query,
				onchange: null,
				addListener: jest.fn(),
				removeListener: jest.fn(),
				addEventListener: jest.fn(),
				removeEventListener: jest.fn(),
				dispatchEvent: jest.fn(),
			})),
		});
	});

	afterEach(() => {
		jest.runOnlyPendingTimers();
		jest.useRealTimers();
	});

	it('renders without crashing', () => {
		render(<HealthScoreCircle score={85} grade="A" />);
		expect(screen.getByRole('progressbar')).toBeInTheDocument();
	});

	it('displays the correct grade', () => {
		render(<HealthScoreCircle score={85} grade="A" />);
		expect(screen.getByText('A')).toBeInTheDocument();
	});

	it('displays loading state correctly', () => {
		render(<HealthScoreCircle score={85} grade="A" loading={true} />);
		expect(screen.getByText('Loading...')).toBeInTheDocument();
		expect(screen.queryByText('A')).not.toBeInTheDocument();
	});

	it('has correct aria-label when loaded', () => {
		render(<HealthScoreCircle score={85} grade="A" />);
		expect(
			screen.getByLabelText('Health score: 85 out of 100, Grade A')
		).toBeInTheDocument();
	});

	it('has correct aria-label when loading', () => {
		render(<HealthScoreCircle score={85} grade="A" loading={true} />);
		expect(
			screen.getByLabelText('Loading health score')
		).toBeInTheDocument();
	});

	it('has correct aria-valuenow attribute when loaded', () => {
		render(<HealthScoreCircle score={85} grade="A" />);
		const progressbar = screen.getByRole('progressbar');
		expect(progressbar).toHaveAttribute('aria-valuenow', '85');
	});

	it('has aria-valuemin and aria-valuemax attributes', () => {
		render(<HealthScoreCircle score={85} grade="A" />);
		const progressbar = screen.getByRole('progressbar');
		expect(progressbar).toHaveAttribute('aria-valuemin', '0');
		expect(progressbar).toHaveAttribute('aria-valuemax', '100');
	});

	it('has aria-busy attribute when loading', () => {
		render(<HealthScoreCircle score={85} grade="A" loading={true} />);
		const progressbar = screen.getByRole('progressbar');
		expect(progressbar).toHaveAttribute('aria-busy', 'true');
	});

	it('uses default values when props are not provided', () => {
		render(<HealthScoreCircle />);
		expect(screen.getByText('F')).toBeInTheDocument();
	});

	it('renders SVG circles correctly', () => {
		const { container } = render(
			<HealthScoreCircle score={75} grade="B" />
		);
		const circles = container.querySelectorAll('circle');
		expect(circles).toHaveLength(2); // Background and progress circles
	});

	it('does not render progress circle when loading', () => {
		const { container } = render(
			<HealthScoreCircle score={75} grade="B" loading={true} />
		);
		const circles = container.querySelectorAll('circle');
		expect(circles).toHaveLength(1); // Only background circle
	});

	it('applies correct color for grade A', () => {
		const { container } = render(
			<HealthScoreCircle score={95} grade="A" />
		);
		const progressCircle = container.querySelectorAll('circle')[1];
		expect(progressCircle).toHaveAttribute('stroke', '#00a32a');
	});

	it('applies correct color for grade B', () => {
		const { container } = render(
			<HealthScoreCircle score={85} grade="B" />
		);
		const progressCircle = container.querySelectorAll('circle')[1];
		expect(progressCircle).toHaveAttribute('stroke', '#2271b1');
	});

	it('applies correct color for grade C', () => {
		const { container } = render(
			<HealthScoreCircle score={75} grade="C" />
		);
		const progressCircle = container.querySelectorAll('circle')[1];
		expect(progressCircle).toHaveAttribute('stroke', '#dba617');
	});

	it('applies correct color for grade D', () => {
		const { container } = render(
			<HealthScoreCircle score={65} grade="D" />
		);
		const progressCircle = container.querySelectorAll('circle')[1];
		expect(progressCircle).toHaveAttribute('stroke', '#d63638');
	});

	it('applies correct color for grade F', () => {
		const { container } = render(
			<HealthScoreCircle score={45} grade="F" />
		);
		const progressCircle = container.querySelectorAll('circle')[1];
		expect(progressCircle).toHaveAttribute('stroke', '#b32d2e');
	});

	it('uses fallback color for invalid grade', () => {
		const { container } = render(
			<HealthScoreCircle score={50} grade="X" />
		);
		const progressCircle = container.querySelectorAll('circle')[1];
		expect(progressCircle).toHaveAttribute('stroke', '#b32d2e'); // Fallback to F grade color
	});

	it('accepts custom size prop', () => {
		const { container } = render(
			<HealthScoreCircle score={75} grade="B" size={150} />
		);
		const wrapper = container.querySelector('.health-score-circle');
		expect(wrapper).toHaveStyle({ width: '150px', height: '150px' });
	});

	it('accepts custom strokeWidth prop', () => {
		const { container } = render(
			<HealthScoreCircle score={75} grade="B" strokeWidth={10} />
		);
		const circles = container.querySelectorAll('circle');
		expect(circles[0]).toHaveAttribute('stroke-width', '10');
		expect(circles[1]).toHaveAttribute('stroke-width', '10');
	});

	it('respects reduced motion preference', () => {
		// Mock matchMedia to return true for reduced motion
		window.matchMedia = jest.fn().mockImplementation((query) => ({
			matches: query === '(prefers-reduced-motion: reduce)',
			media: query,
			onchange: null,
			addListener: jest.fn(),
			removeListener: jest.fn(),
			addEventListener: jest.fn(),
			removeEventListener: jest.fn(),
			dispatchEvent: jest.fn(),
		}));

		render(<HealthScoreCircle score={75} grade="B" />);
		// When reduced motion is preferred, score should be set immediately
		expect(screen.getByText('75')).toBeInTheDocument();
	});

	it('marks SVG as aria-hidden', () => {
		const { container } = render(
			<HealthScoreCircle score={75} grade="B" />
		);
		const svg = container.querySelector('svg');
		expect(svg).toHaveAttribute('aria-hidden', 'true');
		expect(svg).toHaveAttribute('focusable', 'false');
	});
});
