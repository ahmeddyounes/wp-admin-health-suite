/**
 * Metric Card Component
 *
 * A React component that displays metric information with trend indicators.
 * Used in the dashboard to show key metrics like database size, media library count, etc.
 *
 * @package
 */

import React from 'react';
import PropTypes from 'prop-types';

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
 * @param {Object}        props            - Component props
 * @param {string}        props.title      - Card title (e.g., "Database Size")
 * @param {number|string} props.value      - Main metric value to display
 * @param {string}        props.unit       - Unit label (e.g., "MB", "count")
 * @param {string}        props.trend      - Trend direction: 'up', 'down', or 'neutral'
 * @param {string}        props.trendValue - Percentage change from last scan (e.g., "5.2" for 5.2%)
 * @param {string}        props.icon       - Icon class or dashicon name (e.g., "dashicons-database")
 * @param {boolean}       props.loading    - Whether the component is in loading state
 * @param {string}        props.error      - Error message to display
 * @param {Function}      props.onClick    - Click handler for navigation
 * @return {JSX.Element} Rendered component
 */
const MetricCard = ({
	title,
	value,
	unit = '',
	trend = 'neutral',
	trendValue = null,
	icon = 'dashicons-chart-bar',
	loading = false,
	error = null,
	onClick = null,
}) => {
	// Validate trend direction
	const validTrend = ['up', 'down', 'neutral'].includes(trend)
		? trend
		: 'neutral';
	const trendIndicator = TREND_INDICATORS[validTrend];
	const trendColor = TREND_COLORS[validTrend];

	// Handle null/undefined values with fallback
	const displayValue = value ?? '—';

	// Format trend display
	const trendDisplay =
		trendValue !== null
			? `${trendIndicator} ${trendValue}%`
			: trendIndicator;

	// Build accessibility label based on state
	const getAriaLabel = () => {
		if (loading) {
			return `${title}: Loading`;
		}
		if (error) {
			return `${title}: Error - ${error}`;
		}
		const trendText =
			trendValue !== null
				? `, trend ${validTrend} by ${trendValue} percent`
				: '';
		return `${title}: ${displayValue} ${unit}${trendText}`;
	};
	const ariaLabel = getAriaLabel();

	// Determine if card should be interactive
	const isClickable = typeof onClick === 'function';

	// Handle keyboard navigation (using onKeyDown instead of deprecated onKeyPress)
	const handleKeyDown = (e) => {
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

	// Loading skeleton styles
	const loadingSkeletonStyles = {
		backgroundColor: '#e0e0e0',
		borderRadius: '4px',
		animation: 'pulse 1.5s ease-in-out infinite',
	};

	// Error styles
	const errorStyles = {
		fontSize: '14px',
		fontWeight: '500',
		color: '#d63638',
		lineHeight: '1.4',
	};

	// State management for hover
	const [isHovered, setIsHovered] = React.useState(false);

	// Render loading skeleton content
	const renderLoadingContent = () => (
		<div style={contentStyles}>
			<div
				style={{
					...loadingSkeletonStyles,
					width: '80px',
					height: '16px',
					marginBottom: '8px',
				}}
				aria-hidden="true"
			/>
			<div
				style={{
					...loadingSkeletonStyles,
					width: '60px',
					height: '28px',
				}}
				aria-hidden="true"
			/>
		</div>
	);

	// Render error content
	const renderErrorContent = () => (
		<div style={contentStyles}>
			<h3 style={titleStyles}>{title}</h3>
			<div style={errorStyles}>{error}</div>
		</div>
	);

	// Render normal content
	const renderContent = () => (
		<div style={contentStyles}>
			<h3 style={titleStyles}>{title}</h3>
			<div style={valueContainerStyles}>
				<span style={valueStyles}>{displayValue}</span>
				{unit && <span style={unitStyles}>{unit}</span>}
				{trendValue !== null && !loading && !error && (
					<span
						style={trendStyles}
						aria-label={`Trend ${validTrend} by ${trendValue} percent`}
					>
						{trendDisplay}
					</span>
				)}
			</div>
		</div>
	);

	return (
		<div
			className="metric-card"
			role={isClickable ? 'button' : 'article'}
			tabIndex={isClickable ? 0 : undefined}
			aria-label={ariaLabel}
			aria-busy={loading}
			onClick={isClickable && !loading ? onClick : undefined}
			onKeyDown={handleKeyDown}
			onMouseEnter={() => setIsHovered(true)}
			onMouseLeave={() => setIsHovered(false)}
			style={{
				...cardStyles,
				...(isHovered && isClickable && !loading ? hoverStyles : {}),
				...(loading ? { opacity: 0.7 } : {}),
				...(error ? { borderColor: '#d63638' } : {}),
			}}
		>
			{/* Icon */}
			<div style={iconContainerStyles}>
				<span
					className={`dashicons ${loading ? 'dashicons-update' : icon}`}
					style={{
						...iconStyles,
						...(loading
							? { animation: 'spin 1s linear infinite' }
							: {}),
						...(error ? { color: '#d63638' } : {}),
					}}
					aria-hidden="true"
				/>
			</div>

			{/* Content */}
			{loading && renderLoadingContent()}
			{!loading && error && renderErrorContent()}
			{!loading && !error && renderContent()}
		</div>
	);
};

/**
 * PropTypes for MetricCard
 */
MetricCard.propTypes = {
	title: PropTypes.string.isRequired,
	value: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
	unit: PropTypes.string,
	trend: PropTypes.oneOf(['up', 'down', 'neutral']),
	trendValue: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
	icon: PropTypes.string,
	loading: PropTypes.bool,
	error: PropTypes.string,
	onClick: PropTypes.func,
};

export default MetricCard;
