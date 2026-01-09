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
	});

	afterEach(() => {
		jest.runOnlyPendingTimers();
		jest.useRealTimers();
	});

	it('renders without crashing', () => {
		render(<HealthScoreCircle score={85} grade="A" />);
		expect(screen.getByRole('img')).toBeInTheDocument();
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
});
