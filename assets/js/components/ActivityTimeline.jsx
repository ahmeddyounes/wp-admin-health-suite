/**
 * Activity Timeline Component
 *
 * A React component that displays recent activity from the scan history.
 * Fetches from REST endpoint and shows activities with icons,
 * descriptions, and formatted data. Supports pagination and auto-refresh.
 *
 * @package
 */

import React, { useState, useEffect, useCallback, useMemo, memo } from 'react';
import PropTypes from 'prop-types';

/**
 * Activity type to icon mapping
 */
const ACTIVITY_ICONS = {
	database_clean: 'dashicons-database',
	media_scan: 'dashicons-format-image',
	optimization: 'dashicons-performance',
	scheduled_task: 'dashicons-clock',
};

/**
 * Activity type to action description mapping
 */
const ACTIVITY_DESCRIPTIONS = {
	database_clean: 'Database Cleanup',
	media_scan: 'Media Library Scan',
	optimization: 'Site Optimization',
	scheduled_task: 'Scheduled Task',
};

/**
 * Default configuration
 */
const DEFAULT_CONFIG = {
	pageSize: 10,
	refreshInterval: 30000, // 30 seconds
	maxItems: 100, // Maximum items to display for performance
};

/**
 * Styles defined outside component to prevent recreation on each render
 */
const styles = {
	container: {
		backgroundColor: '#fff',
		border: '1px solid #c3c4c7',
		borderRadius: '4px',
		padding: '20px',
	},
	header: {
		margin: '0 0 20px 0',
		fontSize: '18px',
		fontWeight: '600',
		color: '#1d2327',
		borderBottom: '1px solid #dcdcde',
		paddingBottom: '12px',
		display: 'flex',
		justifyContent: 'space-between',
		alignItems: 'center',
	},
	headerTitle: {
		margin: 0,
	},
	headerActions: {
		display: 'flex',
		alignItems: 'center',
		gap: '8px',
	},
	refreshButton: {
		background: 'none',
		border: 'none',
		cursor: 'pointer',
		padding: '4px',
		borderRadius: '4px',
		color: '#2271b1',
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
	},
	refreshButtonDisabled: {
		color: '#a7aaad',
		cursor: 'not-allowed',
	},
	timeline: {
		listStyle: 'none',
		margin: 0,
		padding: 0,
	},
	activityItem: {
		display: 'flex',
		alignItems: 'flex-start',
		padding: '16px 0',
		borderBottom: '1px solid #f0f0f1',
	},
	activityItemLast: {
		borderBottom: 'none',
	},
	iconContainer: {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: '40px',
		height: '40px',
		borderRadius: '50%',
		backgroundColor: '#f0f6fc',
		marginRight: '16px',
		flexShrink: 0,
	},
	icon: {
		fontSize: '20px',
		color: '#2271b1',
	},
	content: {
		flex: 1,
		minWidth: 0,
	},
	title: {
		margin: '0 0 4px 0',
		fontSize: '14px',
		fontWeight: '600',
		color: '#1d2327',
		lineHeight: '1.4',
	},
	details: {
		display: 'flex',
		flexWrap: 'wrap',
		gap: '12px',
		margin: '4px 0',
		fontSize: '13px',
		color: '#646970',
	},
	detailItem: {
		display: 'flex',
		alignItems: 'center',
		gap: '4px',
	},
	timestamp: {
		fontSize: '12px',
		color: '#787c82',
		marginTop: '4px',
	},
	emptyState: {
		textAlign: 'center',
		padding: '40px 20px',
	},
	emptyIcon: {
		fontSize: '64px',
		color: '#dcdcde',
		marginBottom: '16px',
	},
	emptyTitle: {
		margin: '0 0 8px 0',
		fontSize: '18px',
		fontWeight: '600',
		color: '#1d2327',
	},
	emptyMessage: {
		margin: '0 0 20px 0',
		fontSize: '14px',
		color: '#646970',
	},
	ctaButton: {
		display: 'inline-block',
		padding: '10px 20px',
		backgroundColor: '#2271b1',
		color: '#fff',
		fontSize: '14px',
		fontWeight: '500',
		borderRadius: '4px',
		textDecoration: 'none',
		cursor: 'pointer',
		border: 'none',
		transition: 'background-color 0.2s ease',
	},
	ctaButtonHover: {
		backgroundColor: '#135e96',
	},
	ctaButtonError: {
		backgroundColor: '#d63638',
	},
	ctaButtonErrorHover: {
		backgroundColor: '#b32d2e',
	},
	loading: {
		textAlign: 'center',
		padding: '40px 20px',
		fontSize: '14px',
		color: '#646970',
	},
	error: {
		textAlign: 'center',
		padding: '40px 20px',
		fontSize: '14px',
		color: '#d63638',
	},
	pagination: {
		display: 'flex',
		justifyContent: 'center',
		alignItems: 'center',
		gap: '8px',
		marginTop: '20px',
		paddingTop: '16px',
		borderTop: '1px solid #dcdcde',
	},
	pageButton: {
		padding: '6px 12px',
		backgroundColor: '#f0f0f1',
		border: '1px solid #c3c4c7',
		borderRadius: '4px',
		cursor: 'pointer',
		fontSize: '13px',
		color: '#2271b1',
		transition: 'background-color 0.2s ease',
	},
	pageButtonDisabled: {
		backgroundColor: '#f0f0f1',
		color: '#a7aaad',
		cursor: 'not-allowed',
	},
	pageButtonActive: {
		backgroundColor: '#2271b1',
		color: '#fff',
		borderColor: '#2271b1',
	},
	pageInfo: {
		fontSize: '13px',
		color: '#646970',
		padding: '0 8px',
	},
	spinner: {
		animation: 'wpha-spin 1s linear infinite',
	},
};

/**
 * Format bytes to human-readable size
 *
 * @param {number} bytes - Number of bytes
 * @return {string} Formatted size (e.g., "1.5 MB")
 */
const formatBytes = (bytes) => {
	if (typeof bytes !== 'number' || isNaN(bytes) || bytes < 0) {
		return '0 Bytes';
	}

	if (bytes === 0) return '0 Bytes';

	const k = 1024;
	const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
	const i = Math.floor(Math.log(bytes) / Math.log(k));
	const safeIndex = Math.min(i, sizes.length - 1);

	return (
		parseFloat((bytes / Math.pow(k, safeIndex)).toFixed(2)) +
		' ' +
		sizes[safeIndex]
	);
};

/**
 * Get relative time from a date string
 *
 * @param {string} dateString - Date string in ISO format
 * @return {string} Relative time (e.g., "2 hours ago")
 */
const getRelativeTime = (dateString) => {
	if (!dateString) {
		return 'Unknown time';
	}

	const date = new Date(dateString);

	// Check for invalid date
	if (isNaN(date.getTime())) {
		return 'Invalid date';
	}

	const now = new Date();
	const diffInSeconds = Math.floor((now - date) / 1000);

	// Handle future dates
	if (diffInSeconds < 0) {
		return 'In the future';
	}

	if (diffInSeconds < 60) {
		return diffInSeconds === 1
			? '1 second ago'
			: `${diffInSeconds} seconds ago`;
	}

	const diffInMinutes = Math.floor(diffInSeconds / 60);
	if (diffInMinutes < 60) {
		return diffInMinutes === 1
			? '1 minute ago'
			: `${diffInMinutes} minutes ago`;
	}

	const diffInHours = Math.floor(diffInMinutes / 60);
	if (diffInHours < 24) {
		return diffInHours === 1 ? '1 hour ago' : `${diffInHours} hours ago`;
	}

	const diffInDays = Math.floor(diffInHours / 24);
	if (diffInDays < 30) {
		return diffInDays === 1 ? '1 day ago' : `${diffInDays} days ago`;
	}

	const diffInMonths = Math.floor(diffInDays / 30);
	if (diffInMonths < 12) {
		return diffInMonths === 1
			? '1 month ago'
			: `${diffInMonths} months ago`;
	}

	const diffInYears = Math.floor(diffInMonths / 12);
	return diffInYears === 1 ? '1 year ago' : `${diffInYears} years ago`;
};

/**
 * ActivityItem Component - Memoized for performance
 *
 * @param {Object}  props          - Component props
 * @param {Object}  props.activity - Activity data
 * @param {boolean} props.isLast   - Whether this is the last item
 * @return {JSX.Element} Rendered component
 */
const ActivityItem = memo(function ActivityItem({ activity, isLast }) {
	const icon =
		ACTIVITY_ICONS[activity.scan_type] || 'dashicons-admin-generic';
	const description =
		ACTIVITY_DESCRIPTIONS[activity.scan_type] || activity.scan_type;

	const itemStyle = isLast
		? { ...styles.activityItem, ...styles.activityItemLast }
		: styles.activityItem;

	return (
		<li style={itemStyle}>
			{/* Icon */}
			<div style={styles.iconContainer}>
				<span
					className={`dashicons ${icon}`}
					style={styles.icon}
					aria-hidden="true"
				/>
			</div>

			{/* Content */}
			<div style={styles.content}>
				<h3 style={styles.title}>{description}</h3>
				<div style={styles.details}>
					{activity.items_cleaned > 0 && (
						<span style={styles.detailItem}>
							<span
								className="dashicons dashicons-yes-alt"
								style={{
									fontSize: '14px',
									color: '#00a32a',
								}}
								aria-hidden="true"
							/>
							{activity.items_cleaned}{' '}
							{activity.items_cleaned === 1 ? 'item' : 'items'}{' '}
							affected
						</span>
					)}
					{activity.bytes_freed > 0 && (
						<span style={styles.detailItem}>
							<span
								className="dashicons dashicons-cloud"
								style={{
									fontSize: '14px',
									color: '#2271b1',
								}}
								aria-hidden="true"
							/>
							{formatBytes(activity.bytes_freed)} freed
						</span>
					)}
				</div>
				<time style={styles.timestamp} dateTime={activity.created_at}>
					{getRelativeTime(activity.created_at)}
				</time>
			</div>
		</li>
	);
});

ActivityItem.propTypes = {
	activity: PropTypes.shape({
		id: PropTypes.oneOfType([PropTypes.string, PropTypes.number])
			.isRequired,
		scan_type: PropTypes.string.isRequired,
		items_cleaned: PropTypes.number,
		bytes_freed: PropTypes.number,
		created_at: PropTypes.string.isRequired,
	}).isRequired,
	isLast: PropTypes.bool,
};

ActivityItem.defaultProps = {
	isLast: false,
};

/**
 * Pagination Component
 *
 * @param {Object}   props              - Component props
 * @param {number}   props.currentPage  - Current page number
 * @param {number}   props.totalPages   - Total number of pages
 * @param {Function} props.onPageChange - Callback when page changes
 * @param {boolean}  props.disabled     - Whether pagination is disabled
 * @return {JSX.Element|null} Rendered component
 */
const Pagination = memo(function Pagination({
	currentPage,
	totalPages,
	onPageChange,
	disabled,
}) {
	const handlePrevious = useCallback(() => {
		if (currentPage > 1 && !disabled) {
			onPageChange(currentPage - 1);
		}
	}, [currentPage, disabled, onPageChange]);

	const handleNext = useCallback(() => {
		if (currentPage < totalPages && !disabled) {
			onPageChange(currentPage + 1);
		}
	}, [currentPage, totalPages, disabled, onPageChange]);

	if (totalPages <= 1) {
		return null;
	}

	const prevDisabled = currentPage <= 1 || disabled;
	const nextDisabled = currentPage >= totalPages || disabled;

	return (
		<nav style={styles.pagination} aria-label="Activity pagination">
			<button
				style={{
					...styles.pageButton,
					...(prevDisabled ? styles.pageButtonDisabled : {}),
				}}
				onClick={handlePrevious}
				disabled={prevDisabled}
				aria-label="Previous page"
			>
				<span
					className="dashicons dashicons-arrow-left-alt2"
					aria-hidden="true"
				/>
			</button>
			<span style={styles.pageInfo}>
				Page {currentPage} of {totalPages}
			</span>
			<button
				style={{
					...styles.pageButton,
					...(nextDisabled ? styles.pageButtonDisabled : {}),
				}}
				onClick={handleNext}
				disabled={nextDisabled}
				aria-label="Next page"
			>
				<span
					className="dashicons dashicons-arrow-right-alt2"
					aria-hidden="true"
				/>
			</button>
		</nav>
	);
});

Pagination.propTypes = {
	currentPage: PropTypes.number.isRequired,
	totalPages: PropTypes.number.isRequired,
	onPageChange: PropTypes.func.isRequired,
	disabled: PropTypes.bool,
};

Pagination.defaultProps = {
	disabled: false,
};

/**
 * ActivityTimeline Component
 *
 * @param {Object}  props                 - Component props
 * @param {number}  props.pageSize        - Number of items per page
 * @param {number}  props.refreshInterval - Auto-refresh interval in ms
 * @param {boolean} props.autoRefresh     - Whether to auto-refresh
 * @return {JSX.Element} Rendered component
 */
const ActivityTimeline = ({
	pageSize = DEFAULT_CONFIG.pageSize,
	refreshInterval = DEFAULT_CONFIG.refreshInterval,
	autoRefresh = true,
}) => {
	const [activities, setActivities] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [currentPage, setCurrentPage] = useState(1);
	const [totalItems, setTotalItems] = useState(0);
	const [refreshing, setRefreshing] = useState(false);
	const [buttonHover, setButtonHover] = useState(null);

	/**
	 * Fetch activities from REST API
	 */
	const fetchActivities = useCallback(
		async (page = 1, isRefresh = false) => {
			try {
				if (isRefresh) {
					setRefreshing(true);
				} else {
					setLoading(true);
				}
				setError(null);

				const response = await wp.apiFetch({
					path: `/wpha/v1/activity?page=${page}&per_page=${pageSize}`,
					method: 'GET',
				});

				if (response.success) {
					let items = [];
					let total = 0;

					if (Array.isArray(response.data)) {
						items = response.data;
						total = response.data.length;
					} else if (
						response.data &&
						Array.isArray(response.data.items)
					) {
						items = response.data.items;
						total =
							response.data.total || response.data.items.length;
					} else {
						throw new Error(
							response.message || 'Failed to fetch activities'
						);
					}

					// Limit items for performance
					const limitedItems = items.slice(
						0,
						DEFAULT_CONFIG.maxItems
					);
					setActivities(limitedItems);
					setTotalItems(Math.min(total, DEFAULT_CONFIG.maxItems));
					setCurrentPage(page);
				} else {
					throw new Error(
						response.message || 'Failed to fetch activities'
					);
				}
			} catch (err) {
				console.error('Error fetching activities:', err);
				setError(err.message || 'Failed to load activities');
			} finally {
				setLoading(false);
				setRefreshing(false);
			}
		},
		[pageSize]
	);

	// Initial fetch
	useEffect(() => {
		fetchActivities();
	}, [fetchActivities]);

	// Auto-refresh interval
	useEffect(() => {
		if (!autoRefresh || refreshInterval <= 0) {
			return;
		}

		const intervalId = setInterval(() => {
			fetchActivities(currentPage, true);
		}, refreshInterval);

		return () => clearInterval(intervalId);
	}, [autoRefresh, refreshInterval, currentPage, fetchActivities]);

	/**
	 * Handle page change
	 */
	const handlePageChange = useCallback(
		(page) => {
			fetchActivities(page);
		},
		[fetchActivities]
	);

	/**
	 * Handle manual refresh
	 */
	const handleRefresh = useCallback(() => {
		if (!refreshing && !loading) {
			fetchActivities(currentPage, true);
		}
	}, [refreshing, loading, currentPage, fetchActivities]);

	// Calculate total pages
	const totalPages = useMemo(() => {
		return Math.ceil(totalItems / pageSize);
	}, [totalItems, pageSize]);

	// Memoize displayed activities (current page slice for client-side pagination)
	const displayedActivities = useMemo(() => {
		// If server handles pagination, return all activities
		// Otherwise, slice for client-side pagination
		return activities;
	}, [activities]);

	// Render loading state
	if (loading) {
		return (
			<div style={styles.container}>
				<div style={styles.header}>
					<h2 style={styles.headerTitle}>Recent Activity</h2>
				</div>
				<div style={styles.loading} role="status" aria-live="polite">
					<span
						className="dashicons dashicons-update"
						style={styles.spinner}
						aria-hidden="true"
					/>
					<p>Loading activities...</p>
				</div>
			</div>
		);
	}

	// Render error state
	if (error) {
		return (
			<div style={styles.container}>
				<div style={styles.header}>
					<h2 style={styles.headerTitle}>Recent Activity</h2>
				</div>
				<div style={styles.error} role="alert">
					<span
						className="dashicons dashicons-warning"
						style={{ fontSize: '48px', marginBottom: '12px' }}
						aria-hidden="true"
					/>
					<p>{error}</p>
					<button
						onClick={handleRefresh}
						style={{
							...styles.ctaButton,
							...styles.ctaButtonError,
							...(buttonHover === 'error'
								? styles.ctaButtonErrorHover
								: {}),
						}}
						onMouseEnter={() => setButtonHover('error')}
						onMouseLeave={() => setButtonHover(null)}
					>
						Try Again
					</button>
				</div>
			</div>
		);
	}

	// Render empty state
	if (!displayedActivities || displayedActivities.length === 0) {
		return (
			<div style={styles.container}>
				<div style={styles.header}>
					<h2 style={styles.headerTitle}>Recent Activity</h2>
				</div>
				<div style={styles.emptyState}>
					<div
						className="dashicons dashicons-chart-line"
						style={styles.emptyIcon}
						aria-hidden="true"
					/>
					<h3 style={styles.emptyTitle}>No Activity Yet</h3>
					<p style={styles.emptyMessage}>
						Get started by running your first scan to see your
						site&apos;s health metrics.
					</p>
					<button
						onClick={handleRefresh}
						style={{
							...styles.ctaButton,
							...(buttonHover === 'cta'
								? styles.ctaButtonHover
								: {}),
						}}
						onMouseEnter={() => setButtonHover('cta')}
						onMouseLeave={() => setButtonHover(null)}
					>
						Run Your First Scan
					</button>
				</div>
			</div>
		);
	}

	// Render timeline with activities
	return (
		<div style={styles.container}>
			<div style={styles.header}>
				<h2 style={styles.headerTitle}>Recent Activity</h2>
				<div style={styles.headerActions}>
					<button
						style={{
							...styles.refreshButton,
							...(refreshing || loading
								? styles.refreshButtonDisabled
								: {}),
						}}
						onClick={handleRefresh}
						disabled={refreshing || loading}
						aria-label="Refresh activities"
						title="Refresh activities"
					>
						<span
							className="dashicons dashicons-update"
							style={refreshing ? styles.spinner : {}}
							aria-hidden="true"
						/>
					</button>
				</div>
			</div>
			<ul style={styles.timeline} aria-label="Activity timeline">
				{displayedActivities.map((activity, index) => (
					<ActivityItem
						key={activity.id}
						activity={activity}
						isLast={index === displayedActivities.length - 1}
					/>
				))}
			</ul>
			<Pagination
				currentPage={currentPage}
				totalPages={totalPages}
				onPageChange={handlePageChange}
				disabled={loading || refreshing}
			/>
		</div>
	);
};

ActivityTimeline.propTypes = {
	pageSize: PropTypes.number,
	refreshInterval: PropTypes.number,
	autoRefresh: PropTypes.bool,
};

ActivityTimeline.defaultProps = {
	pageSize: DEFAULT_CONFIG.pageSize,
	refreshInterval: DEFAULT_CONFIG.refreshInterval,
	autoRefresh: true,
};

export default ActivityTimeline;

// Export utilities for testing
export { formatBytes, getRelativeTime };
