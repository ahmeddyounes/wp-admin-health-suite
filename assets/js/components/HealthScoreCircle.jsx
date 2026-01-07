/**
 * Health Score Circle Component
 *
 * A React component that displays a health score as an animated circular progress indicator.
 *
 * @package WPAdminHealth
 */

import React, { useEffect, useRef, useState } from 'react';

/**
 * Grade color mapping based on spec requirements
 */
const GRADE_COLORS = {
	A: '#00a32a', // green
	B: '#2271b1', // blue
	C: '#dba617', // yellow
	D: '#d63638', // orange
	F: '#b32d2e', // red
};

/**
 * HealthScoreCircle Component
 *
 * @param {Object} props - Component props
 * @param {number} props.score - Health score from 0 to 100
 * @param {string} props.grade - Letter grade (A, B, C, D, or F)
 * @param {boolean} props.loading - Whether the component is in loading state
 * @returns {JSX.Element} Rendered component
 */
const HealthScoreCircle = ({ score = 0, grade = 'F', loading = false }) => {
	const [animatedScore, setAnimatedScore] = useState(0);
	const animationRef = useRef(null);
	const startTimeRef = useRef(null);

	// Constants for SVG circle
	const size = 200;
	const strokeWidth = 20;
	const radius = (size - strokeWidth) / 2;
	const circumference = 2 * Math.PI * radius;
	const center = size / 2;

	// Animation duration in milliseconds
	const ANIMATION_DURATION = 1500;

	useEffect(() => {
		// Reset animation when score changes
		setAnimatedScore(0);
		startTimeRef.current = null;

		if (loading) {
			return;
		}

		/**
		 * Animate the score count up using requestAnimationFrame
		 */
		const animate = (timestamp) => {
			if (!startTimeRef.current) {
				startTimeRef.current = timestamp;
			}

			const elapsed = timestamp - startTimeRef.current;
			const progress = Math.min(elapsed / ANIMATION_DURATION, 1);

			// Easing function for smooth animation (easeOutCubic)
			const easedProgress = 1 - Math.pow(1 - progress, 3);
			const currentScore = Math.round(easedProgress * score);

			setAnimatedScore(currentScore);

			if (progress < 1) {
				animationRef.current = requestAnimationFrame(animate);
			}
		};

		animationRef.current = requestAnimationFrame(animate);

		// Cleanup function
		return () => {
			if (animationRef.current) {
				cancelAnimationFrame(animationRef.current);
			}
		};
	}, [score, loading]);

	// Calculate stroke dash offset for progress circle
	const dashOffset = circumference - (animatedScore / 100) * circumference;

	// Get color for the current grade
	const color = GRADE_COLORS[grade] || GRADE_COLORS.F;

	// Accessible label
	const ariaLabel = loading
		? 'Loading health score'
		: `Health score: ${score} out of 100, Grade ${grade}`;

	return (
		<div
			className="health-score-circle"
			role="img"
			aria-label={ariaLabel}
			style={{
				display: 'inline-block',
				position: 'relative',
			}}
		>
			<svg
				width={size}
				height={size}
				viewBox={`0 0 ${size} ${size}`}
				style={{ transform: 'rotate(-90deg)' }}
			>
				{/* Background circle */}
				<circle
					cx={center}
					cy={center}
					r={radius}
					fill="none"
					stroke="#e0e0e0"
					strokeWidth={strokeWidth}
				/>

				{/* Progress circle */}
				{!loading && (
					<circle
						cx={center}
						cy={center}
						r={radius}
						fill="none"
						stroke={color}
						strokeWidth={strokeWidth}
						strokeDasharray={circumference}
						strokeDashoffset={dashOffset}
						strokeLinecap="round"
						style={{
							transition: 'stroke-dashoffset 0.1s ease-out',
						}}
					/>
				)}
			</svg>

			{/* Center content - grade letter and score */}
			<div
				style={{
					position: 'absolute',
					top: '50%',
					left: '50%',
					transform: 'translate(-50%, -50%)',
					textAlign: 'center',
					pointerEvents: 'none',
				}}
			>
				{loading ? (
					<div
						style={{
							fontSize: '16px',
							color: '#666',
							fontWeight: '500',
						}}
					>
						Loading...
					</div>
				) : (
					<>
						<div
							style={{
								fontSize: '48px',
								fontWeight: 'bold',
								color: color,
								lineHeight: '1',
								marginBottom: '4px',
							}}
						>
							{grade}
						</div>
						<div
							style={{
								fontSize: '24px',
								fontWeight: '600',
								color: '#333',
								lineHeight: '1',
							}}
						>
							{animatedScore}
						</div>
					</>
				)}
			</div>
		</div>
	);
};

export default HealthScoreCircle;
