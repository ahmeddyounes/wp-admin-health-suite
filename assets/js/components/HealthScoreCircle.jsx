/**
 * Health Score Circle Component
 *
 * A React component that displays a health score as an animated circular progress indicator.
 * Supports accessibility features, reduced motion preferences, and responsive sizing.
 *
 * @package
 */

import React, {
	useEffect,
	useRef,
	useState,
	useCallback,
	useMemo,
} from 'react';

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
 * Default size configuration
 */
const DEFAULT_SIZE = 200;
const DEFAULT_STROKE_WIDTH = 20;
const ANIMATION_DURATION = 1500;

/**
 * Check if user prefers reduced motion
 * @return {boolean} True if reduced motion is preferred
 */
const prefersReducedMotion = () => {
	if (typeof window === 'undefined') {
		return false;
	}
	const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
	return mediaQuery.matches;
};

/**
 * HealthScoreCircle Component
 *
 * @param {Object}  props             - Component props
 * @param {number}  props.score       - Health score from 0 to 100
 * @param {string}  props.grade       - Letter grade (A, B, C, D, or F)
 * @param {boolean} props.loading     - Whether the component is in loading state
 * @param {number}  props.size        - Circle size in pixels (default: 200)
 * @param {number}  props.strokeWidth - Stroke width in pixels (default: 20)
 * @return {JSX.Element} Rendered component
 */
const HealthScoreCircle = ({
	score = 0,
	grade = 'F',
	loading = false,
	size = DEFAULT_SIZE,
	strokeWidth = DEFAULT_STROKE_WIDTH,
}) => {
	const [animatedScore, setAnimatedScore] = useState(0);
	const animationRef = useRef(null);
	const startTimeRef = useRef(null);
	const prevScoreRef = useRef(score);

	// Memoize circle calculations to avoid recalculating on every render
	const circleConfig = useMemo(() => {
		const radius = (size - strokeWidth) / 2;
		return {
			radius,
			circumference: 2 * Math.PI * radius,
			center: size / 2,
		};
	}, [size, strokeWidth]);

	const { radius, circumference, center } = circleConfig;

	// Memoize the animate function to prevent recreation on every render
	const animate = useCallback(
		(timestamp) => {
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
		},
		[score]
	);

	useEffect(() => {
		// Only reset animation when score actually changes
		if (prevScoreRef.current !== score) {
			prevScoreRef.current = score;
			startTimeRef.current = null;

			// If reduced motion is preferred, skip animation
			if (prefersReducedMotion()) {
				setAnimatedScore(score);
				return;
			}

			setAnimatedScore(0);
		}

		if (loading) {
			return;
		}

		// Skip animation if reduced motion is preferred
		if (prefersReducedMotion()) {
			setAnimatedScore(score);
			return;
		}

		animationRef.current = requestAnimationFrame(animate);

		// Cleanup function
		return () => {
			if (animationRef.current) {
				cancelAnimationFrame(animationRef.current);
			}
		};
	}, [score, loading, animate]);

	// Calculate stroke dash offset for progress circle
	const dashOffset = circumference - (animatedScore / 100) * circumference;

	// Get color for the current grade
	const color = GRADE_COLORS[grade] || GRADE_COLORS.F;

	// Calculate font sizes relative to circle size
	const gradeFontSize = Math.round(size * 0.24);
	const scoreFontSize = Math.round(size * 0.12);
	const loadingFontSize = Math.round(size * 0.08);

	// Accessible label
	const ariaLabel = loading
		? 'Loading health score'
		: `Health score: ${score} out of 100, Grade ${grade}`;

	return (
		<div
			className="health-score-circle"
			role="progressbar"
			aria-label={ariaLabel}
			aria-valuenow={loading ? undefined : score}
			aria-valuemin={0}
			aria-valuemax={100}
			aria-busy={loading}
			style={{
				display: 'inline-block',
				position: 'relative',
				width: size,
				height: size,
			}}
		>
			<svg
				width="100%"
				height="100%"
				viewBox={`0 0 ${size} ${size}`}
				style={{ transform: 'rotate(-90deg)' }}
				aria-hidden="true"
				focusable="false"
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
				aria-hidden="true"
			>
				{loading ? (
					<div
						style={{
							fontSize: `${loadingFontSize}px`,
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
								fontSize: `${gradeFontSize}px`,
								fontWeight: 'bold',
								color,
								lineHeight: '1',
								marginBottom: '4px',
							}}
						>
							{grade}
						</div>
						<div
							style={{
								fontSize: `${scoreFontSize}px`,
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
