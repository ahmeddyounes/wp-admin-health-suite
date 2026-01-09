/**
 * Activity Timeline Component
 *
 * A React component that displays recent activity from the scan history.
 * Fetches from REST endpoint and shows the last 10 activities with icons,
 * descriptions, and formatted data.
 *
 * @package
 */

import React, { useState, useEffect } from 'react';

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
 * Format bytes to human-readable size
 *
 * @param {number} bytes - Number of bytes
 * @return {string} Formatted size (e.g., "1.5 MB")
 */
const formatBytes = (bytes) => {
	if (bytes === 0) return '0 Bytes';

	const k = 1024;
	const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
	const i = Math.floor(Math.log(bytes) / Math.log(k));

	return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

/**
 * Get relative time using wp.date
 *
 * @param {string} dateString - Date string in ISO format
 * @return {string} Relative time (e.g., "2 hours ago")
 */
const getRelativeTime = (dateString) => {
	if (typeof wp !== 'undefined' && wp.date && wp.date.dateI18n) {
		// Use WordPress date formatting if available
		const date = new Date(dateString);
		const now = new Date();
		const diffInSeconds = Math.floor((now - date) / 1000);

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
			return diffInHours === 1
				? '1 hour ago'
				: `${diffInHours} hours ago`;
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
	}

	// Fallback if wp.date is not available
	return new Date(dateString).toLocaleString();
};

/**
 * ActivityTimeline Component
 *
 * @return {JSX.Element} Rendered component
 */
const ActivityTimeline = () => {
	const [activities, setActivities] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	useEffect(() => {
		fetchActivities();
	}, []);

	/**
	 * Fetch activities from REST API
	 */
	const fetchActivities = async () => {
		try {
			setLoading(true);
			setError(null);

			const response = await wp.apiFetch({
				path: '/wpha/v1/activity',
				method: 'GET',
			});

			if (response.success && Array.isArray(response.data)) {
				setActivities(response.data);
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
		}
	};

	// Styles
	const containerStyles = {
		backgroundColor: '#fff',
		border: '1px solid #c3c4c7',
		borderRadius: '4px',
		padding: '20px',
	};

	const headerStyles = {
		margin: '0 0 20px 0',
		fontSize: '18px',
		fontWeight: '600',
		color: '#1d2327',
		borderBottom: '1px solid #dcdcde',
		paddingBottom: '12px',
	};

	const timelineStyles = {
		listStyle: 'none',
		margin: 0,
		padding: 0,
	};

	const activityItemStyles = {
		display: 'flex',
		alignItems: 'flex-start',
		padding: '16px 0',
		borderBottom: '1px solid #f0f0f1',
	};

	const lastItemStyles = {
		borderBottom: 'none',
	};

	const iconContainerStyles = {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: '40px',
		height: '40px',
		borderRadius: '50%',
		backgroundColor: '#f0f6fc',
		marginRight: '16px',
		flexShrink: 0,
	};

	const iconStyles = {
		fontSize: '20px',
		color: '#2271b1',
	};

	const contentStyles = {
		flex: 1,
		minWidth: 0,
	};

	const titleStyles = {
		margin: '0 0 4px 0',
		fontSize: '14px',
		fontWeight: '600',
		color: '#1d2327',
		lineHeight: '1.4',
	};

	const detailsStyles = {
		display: 'flex',
		flexWrap: 'wrap',
		gap: '12px',
		margin: '4px 0',
		fontSize: '13px',
		color: '#646970',
	};

	const detailItemStyles = {
		display: 'flex',
		alignItems: 'center',
		gap: '4px',
	};

	const timestampStyles = {
		fontSize: '12px',
		color: '#787c82',
		marginTop: '4px',
	};

	const emptyStateStyles = {
		textAlign: 'center',
		padding: '40px 20px',
	};

	const emptyIconStyles = {
		fontSize: '64px',
		color: '#dcdcde',
		marginBottom: '16px',
	};

	const emptyTitleStyles = {
		margin: '0 0 8px 0',
		fontSize: '18px',
		fontWeight: '600',
		color: '#1d2327',
	};

	const emptyMessageStyles = {
		margin: '0 0 20px 0',
		fontSize: '14px',
		color: '#646970',
	};

	const ctaButtonStyles = {
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
	};

	const loadingStyles = {
		textAlign: 'center',
		padding: '40px 20px',
		fontSize: '14px',
		color: '#646970',
	};

	const errorStyles = {
		textAlign: 'center',
		padding: '40px 20px',
		fontSize: '14px',
		color: '#d63638',
	};

	// Render loading state
	if (loading) {
		return (
			<div style={containerStyles}>
				<h2 style={headerStyles}>Recent Activity</h2>
				<div style={loadingStyles}>
					<span
						className="dashicons dashicons-update"
						style={{ animation: 'rotation 2s infinite linear' }}
					/>
					<p>Loading activities...</p>
				</div>
			</div>
		);
	}

	// Render error state
	if (error) {
		return (
			<div style={containerStyles}>
				<h2 style={headerStyles}>Recent Activity</h2>
				<div style={errorStyles}>
					<span
						className="dashicons dashicons-warning"
						style={{ fontSize: '48px', marginBottom: '12px' }}
					/>
					<p>{error}</p>
					<button
						onClick={fetchActivities}
						style={{
							...ctaButtonStyles,
							backgroundColor: '#d63638',
						}}
						onMouseEnter={(e) =>
							(e.target.style.backgroundColor = '#b32d2e')
						}
						onMouseLeave={(e) =>
							(e.target.style.backgroundColor = '#d63638')
						}
					>
						Try Again
					</button>
				</div>
			</div>
		);
	}

	// Render empty state
	if (!activities || activities.length === 0) {
		return (
			<div style={containerStyles}>
				<h2 style={headerStyles}>Recent Activity</h2>
				<div style={emptyStateStyles}>
					<div
						className="dashicons dashicons-chart-line"
						style={emptyIconStyles}
						aria-hidden="true"
					/>
					<h3 style={emptyTitleStyles}>No Activity Yet</h3>
					<p style={emptyMessageStyles}>
						Get started by running your first scan to see your
						site&apos;s health metrics.
					</p>
					<button
						onClick={() => {
							// This would typically trigger a scan
							// For now, we'll just refresh
							fetchActivities();
						}}
						style={ctaButtonStyles}
						onMouseEnter={(e) =>
							(e.target.style.backgroundColor = '#135e96')
						}
						onMouseLeave={(e) =>
							(e.target.style.backgroundColor = '#2271b1')
						}
					>
						Run Your First Scan
					</button>
				</div>
			</div>
		);
	}

	// Render timeline with activities
	return (
		<div style={containerStyles}>
			<h2 style={headerStyles}>Recent Activity</h2>
			<ul style={timelineStyles}>
				{activities.map((activity, index) => {
					const icon =
						ACTIVITY_ICONS[activity.scan_type] ||
						'dashicons-admin-generic';
					const description =
						ACTIVITY_DESCRIPTIONS[activity.scan_type] ||
						activity.scan_type;
					const isLast = index === activities.length - 1;

					return (
						<li
							key={activity.id}
							style={{
								...activityItemStyles,
								...(isLast ? lastItemStyles : {}),
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
								<h3 style={titleStyles}>{description}</h3>
								<div style={detailsStyles}>
									{activity.items_cleaned > 0 && (
										<span style={detailItemStyles}>
											<span
												className="dashicons dashicons-yes-alt"
												style={{
													fontSize: '14px',
													color: '#00a32a',
												}}
											/>
											{activity.items_cleaned}{' '}
											{activity.items_cleaned === 1
												? 'item'
												: 'items'}{' '}
											affected
										</span>
									)}
									{activity.bytes_freed > 0 && (
										<span style={detailItemStyles}>
											<span
												className="dashicons dashicons-cloud"
												style={{
													fontSize: '14px',
													color: '#2271b1',
												}}
											/>
											{formatBytes(activity.bytes_freed)}{' '}
											freed
										</span>
									)}
								</div>
								<time
									style={timestampStyles}
									dateTime={activity.created_at}
								>
									{getRelativeTime(activity.created_at)}
								</time>
							</div>
						</li>
					);
				})}
			</ul>
		</div>
	);
};

export default ActivityTimeline;
