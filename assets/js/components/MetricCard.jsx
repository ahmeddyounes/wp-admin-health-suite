/**
 * Metric Card Component
 *
 * A React component that displays metric information with trend indicators.
 * Used in the dashboard to show key metrics like database size, media library count, etc.
 *
 * @package WPAdminHealth
 */

import React from 'react';

/**
 * Trend direction types and their corresponding arrow symbols
 */
const TREND_INDICATORS = {
	up: '↑',
	down: '↓',
	neutral: '→',
};

/**
 * Trend color mapping
 */
const TREND_COLORS = {
	up: '#d63638', // red (usually negative for metrics like size)
	down: '#00a32a', // green (usually positive for metrics like size reduction)
	neutral: '#646970', // gray
};

/**
 * MetricCard Component
 *
 * @param {Object} props - Component props
 * @param {string} props.title - Card title (e.g., "Database Size")
 * @param {number|string} props.value - Main metric value to display
 * @param {string} props.unit - Unit label (e.g., "MB", "count")
 * @param {string} props.trend - Trend direction: 'up', 'down', or 'neutral'
 * @param {string} props.trendValue - Percentage change from last scan (e.g., "5.2" for 5.2%)
 * @param {string} props.icon - Icon class or dashicon name (e.g., "dashicons-database")
 * @param {Function} props.onClick - Click handler for navigation
 * @returns {JSX.Element} Rendered component
 */
const MetricCard = ({
	title,
	value,
	unit = '',
	trend = 'neutral',
	trendValue = null,
	icon = 'dashicons-chart-bar',
	onClick = null,
}) => {
	// Validate trend direction
	const validTrend = ['up', 'down', 'neutral'].includes(trend) ? trend : 'neutral';
	const trendIndicator = TREND_INDICATORS[validTrend];
	const trendColor = TREND_COLORS[validTrend];

	// Format trend display
	const trendDisplay = trendValue !== null ? `${trendIndicator} ${trendValue}%` : trendIndicator;

	// Accessibility label
	const ariaLabel = `${title}: ${value} ${unit}${
		trendValue !== null ? `, trend ${validTrend} by ${trendValue} percent` : ''
	}`;

	// Determine if card should be interactive
	const isClickable = typeof onClick === 'function';

	// Handle keyboard navigation
	const handleKeyPress = (e) => {
		if (isClickable && (e.key === 'Enter' || e.key === ' ')) {
			e.preventDefault();
			onClick();
		}
	};

	// Base styles
	const cardStyles = {
		display: 'flex',
		alignItems: 'center',
		padding: '20px',
		backgroundColor: '#fff',
		border: '1px solid #c3c4c7',
		borderRadius: '4px',
		cursor: isClickable ? 'pointer' : 'default',
		transition: 'all 0.2s ease',
		outline: 'none',
		minHeight: '100px',
	};

	// Hover state styles
	const hoverStyles = {
		boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)',
		transform: 'translateY(-2px)',
		borderColor: '#2271b1',
	};

	// Icon container styles
	const iconContainerStyles = {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: '48px',
		height: '48px',
		marginRight: '16px',
		flexShrink: 0,
	};

	// Icon styles (using WordPress dashicons)
	const iconStyles = {
		fontSize: '32px',
		width: '32px',
		height: '32px',
		color: '#2271b1',
	};

	// Content container styles
	const contentStyles = {
		flex: 1,
		minWidth: 0, // Allow text truncation
	};

	// Title styles
	const titleStyles = {
		margin: '0 0 8px 0',
		fontSize: '14px',
		fontWeight: '600',
		color: '#1d2327',
		lineHeight: '1.4',
	};

	// Value container styles
	const valueContainerStyles = {
		display: 'flex',
		alignItems: 'baseline',
		gap: '8px',
		flexWrap: 'wrap',
	};

	// Value styles
	const valueStyles = {
		fontSize: '28px',
		fontWeight: '700',
		color: '#1d2327',
		lineHeight: '1.2',
	};

	// Unit styles
	const unitStyles = {
		fontSize: '14px',
		fontWeight: '400',
		color: '#646970',
		lineHeight: '1.2',
	};

	// Trend styles
	const trendStyles = {
		fontSize: '14px',
		fontWeight: '600',
		color: trendColor,
		marginLeft: '4px',
	};

	// State management for hover
	const [isHovered, setIsHovered] = React.useState(false);

	return (
		<div
			className="metric-card"
			role={isClickable ? 'button' : 'article'}
			tabIndex={isClickable ? 0 : undefined}
			aria-label={ariaLabel}
			onClick={isClickable ? onClick : undefined}
			onKeyPress={handleKeyPress}
			onMouseEnter={() => setIsHovered(true)}
			onMouseLeave={() => setIsHovered(false)}
			style={{
				...cardStyles,
				...(isHovered && isClickable ? hoverStyles : {}),
			}}
		>
			{/* Icon */}
			<div style={iconContainerStyles}>
				<span
					className={`dashicons ${icon}`}
					style={iconStyles}
					aria-hidden="true"
				/>
			</div>

			{/* Content */}
			<div style={contentStyles}>
				<h3 style={titleStyles}>{title}</h3>
				<div style={valueContainerStyles}>
					<span style={valueStyles}>{value}</span>
					{unit && <span style={unitStyles}>{unit}</span>}
					{trendValue !== null && (
						<span style={trendStyles} aria-label={`Trend ${validTrend} by ${trendValue} percent`}>
							{trendDisplay}
						</span>
					)}
				</div>
			</div>
		</div>
	);
};

export default MetricCard;
