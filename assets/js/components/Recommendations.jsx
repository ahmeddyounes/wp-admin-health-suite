/**
 * Recommendations Component
 *
 * A React component that displays prioritized, actionable recommendations
 * for improving WordPress site health. Features include:
 * - Severity-based prioritization (critical/warning/info)
 * - Category filtering and sorting
 * - Expandable details for each recommendation
 * - Action buttons: Fix Now, Preview, Dismiss, Learn More
 * - Smooth animations for completion
 * - localStorage persistence for dismissed items
 *
 * @package
 */

import React, { useState, useEffect } from 'react';
import apiClient from '../utils/api.js';

/**
 * Severity level to color mapping
 */
const SEVERITY_COLORS = {
	critical: '#d63638',
	high: '#d63638',
	medium: '#dba617',
	low: '#2271b1',
};

/**
 * Severity level to icon mapping
 */
const SEVERITY_ICONS = {
	critical: 'dashicons-warning',
	high: 'dashicons-warning',
	medium: 'dashicons-info',
	low: 'dashicons-info',
};

/**
 * Category to icon mapping
 */
const CATEGORY_ICONS = {
	database: 'dashicons-database',
	media: 'dashicons-format-image',
	performance: 'dashicons-performance',
	security: 'dashicons-shield',
};

/**
 * Category display names
 */
const CATEGORY_NAMES = {
	database: 'Database',
	media: 'Media',
	performance: 'Performance',
	security: 'Security',
};

/**
 * Recommendations Component
 *
 * @param {Object}   props                 - Component props
 * @param {Array}    props.recommendations - Array of recommendation objects from API
 * @param {boolean}  props.loading         - Loading state
 * @param {Function} props.onRefresh       - Callback to refresh recommendations
 * @return {JSX.Element} Rendered component
 */
const Recommendations = ({
	recommendations = [],
	loading = false,
	onRefresh = null,
}) => {
	const [expandedItems, setExpandedItems] = useState(new Set());
	const [dismissedItems, setDismissedItems] = useState(new Set());
	const [filterCategory, setFilterCategory] = useState('all');
	const [sortBy, setSortBy] = useState('priority');
	const [executingAction, setExecutingAction] = useState(null);
	const [completedActions, setCompletedActions] = useState(new Set());

	// Load dismissed items from localStorage on mount
	useEffect(() => {
		try {
			const dismissed = localStorage.getItem(
				'wpha_dismissed_recommendations'
			);
			if (dismissed) {
				setDismissedItems(new Set(JSON.parse(dismissed)));
			}
		} catch (error) {
			console.error('Error loading dismissed recommendations:', error);
		}
	}, []);

	// Save dismissed items to localStorage
	const saveDismissedItems = (items) => {
		try {
			localStorage.setItem(
				'wpha_dismissed_recommendations',
				JSON.stringify([...items])
			);
		} catch (error) {
			console.error('Error saving dismissed recommendations:', error);
		}
	};

	/**
	 * Toggle expanded state for a recommendation
	 * @param id
	 */
	const toggleExpanded = (id) => {
		const newExpanded = new Set(expandedItems);
		if (newExpanded.has(id)) {
			newExpanded.delete(id);
		} else {
			newExpanded.add(id);
		}
		setExpandedItems(newExpanded);
	};

	/**
	 * Dismiss a recommendation
	 * @param id
	 */
	const handleDismiss = async (id) => {
		const newDismissed = new Set(dismissedItems);
		newDismissed.add(id);
		setDismissedItems(newDismissed);
		saveDismissedItems(newDismissed);

		// Also call API to dismiss on server
		try {
			await apiClient.post(`recommendations/${id}/dismiss`);
		} catch (error) {
			console.error('Error dismissing recommendation:', error);
			window.WPAdminHealth?.Toast?.error(
				error.message || 'Failed to dismiss recommendation'
			);
		}
	};

	/**
	 * Execute a recommendation action
	 * @param recommendation
	 */
	const handleFixNow = async (recommendation) => {
		if (
			!recommendation.action_params ||
			!recommendation.action_params.endpoint
		) {
			console.error('No action endpoint defined for this recommendation');
			return;
		}

		setExecutingAction(recommendation.id);

		try {
			// Extract the endpoint path, removing the /wpha/v1/ prefix if present
			let endpoint = recommendation.action_params.endpoint;
			if (endpoint.startsWith('/wpha/v1/')) {
				endpoint = endpoint.replace('/wpha/v1/', '');
			}

			const response = await apiClient.post(
				endpoint,
				recommendation.action_params
			);

			if (response.success) {
				// Mark as completed with animation
				const newCompleted = new Set(completedActions);
				newCompleted.add(recommendation.id);
				setCompletedActions(newCompleted);

				// After animation, dismiss the item
				setTimeout(() => {
					handleDismiss(recommendation.id);
					if (onRefresh) {
						onRefresh();
					}
				}, 1500);
			}
		} catch (error) {
			console.error('Error executing action:', error);
			window.WPAdminHealth?.Toast?.error(
				error.message || 'Failed to execute action'
			);
			alert(
				`Failed to execute action: ${error.message || 'Unknown error'}`
			);
		} finally {
			setExecutingAction(null);
		}
	};

	/**
	 * Show preview/impact of action
	 * @param recommendation
	 */
	const handlePreview = (recommendation) => {
		// Show modal with steps and impact
		alert(
			`Preview for: ${recommendation.title}\n\nSteps:\n${recommendation.steps?.join('\n') || 'No steps available'}`
		);
	};

	/**
	 * Open learn more link
	 * @param recommendation
	 */
	const handleLearnMore = (recommendation) => {
		// For now, we'll open WordPress docs or similar
		const searchTerm = encodeURIComponent(recommendation.title);
		window.open(`https://wordpress.org/support/?s=${searchTerm}`, '_blank');
	};

	/**
	 * Filter and sort recommendations
	 */
	const getFilteredAndSortedRecommendations = () => {
		let filtered = recommendations.filter(
			(rec) => !dismissedItems.has(rec.id)
		);

		// Apply category filter
		if (filterCategory !== 'all') {
			filtered = filtered.filter(
				(rec) => rec.category === filterCategory
			);
		}

		// Apply sorting
		if (sortBy === 'priority') {
			filtered.sort((a, b) => (b.priority || 0) - (a.priority || 0));
		} else if (sortBy === 'category') {
			filtered.sort((a, b) => a.category.localeCompare(b.category));
		}

		return filtered;
	};

	const filteredRecommendations = getFilteredAndSortedRecommendations();

	// Get unique categories from recommendations for filter dropdown
	const categories = [
		'all',
		...new Set(recommendations.map((rec) => rec.category)),
	];

	// Styles
	const containerStyles = {
		backgroundColor: '#fff',
		border: '1px solid #c3c4c7',
		borderRadius: '4px',
		padding: '20px',
	};

	const headerStyles = {
		display: 'flex',
		justifyContent: 'space-between',
		alignItems: 'center',
		marginBottom: '20px',
		paddingBottom: '12px',
		borderBottom: '1px solid #dcdcde',
	};

	const titleStyles = {
		margin: 0,
		fontSize: '18px',
		fontWeight: '600',
		color: '#1d2327',
	};

	const controlsStyles = {
		display: 'flex',
		gap: '12px',
		flexWrap: 'wrap',
		marginBottom: '20px',
	};

	const filterStyles = {
		display: 'flex',
		gap: '8px',
		alignItems: 'center',
	};

	const selectStyles = {
		padding: '6px 12px',
		border: '1px solid #c3c4c7',
		borderRadius: '4px',
		backgroundColor: '#fff',
		fontSize: '13px',
		cursor: 'pointer',
	};

	const listStyles = {
		listStyle: 'none',
		margin: 0,
		padding: 0,
	};

	const emptyStateStyles = {
		textAlign: 'center',
		padding: '60px 20px',
	};

	const emptyIconStyles = {
		fontSize: '64px',
		color: '#00a32a',
		marginBottom: '16px',
	};

	// CSS animations
	const styleSheet = `
		@keyframes slideIn {
			from {
				opacity: 0;
				transform: translateY(-10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		@keyframes slideOut {
			from {
				opacity: 1;
				transform: translateX(0);
			}
			to {
				opacity: 0;
				transform: translateX(100%);
			}
		}
		@keyframes checkmark {
			0% {
				transform: scale(0);
			}
			50% {
				transform: scale(1.2);
			}
			100% {
				transform: scale(1);
			}
		}
		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
	`;

	// Loading state
	if (loading) {
		return (
			<div style={containerStyles}>
				<style>{styleSheet}</style>
				<div style={{ textAlign: 'center', padding: '40px 20px' }}>
					<span
						className="dashicons dashicons-update"
						style={{
							fontSize: '48px',
							color: '#2271b1',
							animation: 'spin 1s linear infinite',
						}}
					/>
					<p style={{ marginTop: '16px', color: '#646970' }}>
						Loading recommendations...
					</p>
				</div>
			</div>
		);
	}

	// Empty state
	if (filteredRecommendations.length === 0) {
		const allDismissed =
			recommendations.length > 0 &&
			dismissedItems.size === recommendations.length;

		return (
			<div style={containerStyles}>
				<style>{styleSheet}</style>
				<h2 style={titleStyles}>Recommendations</h2>
				<div style={emptyStateStyles}>
					<div
						className="dashicons dashicons-yes-alt"
						style={emptyIconStyles}
						aria-hidden="true"
					/>
					<h3
						style={{
							margin: '0 0 8px 0',
							fontSize: '18px',
							fontWeight: '600',
							color: '#1d2327',
						}}
					>
						{allDismissed
							? 'All recommendations dismissed'
							: 'All clear!'}
					</h3>
					<p
						style={{
							margin: '0 0 20px 0',
							fontSize: '14px',
							color: '#646970',
						}}
					>
						{allDismissed
							? 'You have dismissed all recommendations. They will reappear on the next scan if issues persist.'
							: 'Your site health is excellent! No immediate actions needed.'}
					</p>
					{onRefresh && (
						<button
							onClick={onRefresh}
							style={{
								padding: '10px 20px',
								backgroundColor: '#2271b1',
								color: '#fff',
								border: 'none',
								borderRadius: '4px',
								cursor: 'pointer',
								fontSize: '14px',
								fontWeight: '500',
							}}
							onMouseEnter={(e) =>
								(e.target.style.backgroundColor = '#135e96')
							}
							onMouseLeave={(e) =>
								(e.target.style.backgroundColor = '#2271b1')
							}
						>
							Run New Scan
						</button>
					)}
				</div>
			</div>
		);
	}

	return (
		<div style={containerStyles}>
			<style>{styleSheet}</style>

			{/* Header */}
			<div style={headerStyles}>
				<h2 style={titleStyles}>
					Recommendations ({filteredRecommendations.length})
				</h2>
				{onRefresh && (
					<button
						onClick={onRefresh}
						style={{
							padding: '6px 12px',
							backgroundColor: '#fff',
							color: '#2271b1',
							border: '1px solid #2271b1',
							borderRadius: '4px',
							cursor: 'pointer',
							fontSize: '13px',
							fontWeight: '500',
							display: 'flex',
							alignItems: 'center',
							gap: '4px',
						}}
						onMouseEnter={(e) =>
							(e.target.style.backgroundColor = '#f0f6fc')
						}
						onMouseLeave={(e) =>
							(e.target.style.backgroundColor = '#fff')
						}
					>
						<span
							className="dashicons dashicons-update"
							style={{
								fontSize: '16px',
								width: '16px',
								height: '16px',
							}}
						/>
						Refresh
					</button>
				)}
			</div>

			{/* Controls */}
			<div style={controlsStyles}>
				<div style={filterStyles}>
					<label
						htmlFor="category-filter"
						style={{
							fontSize: '13px',
							fontWeight: '500',
							color: '#1d2327',
						}}
					>
						Filter:
					</label>
					<select
						id="category-filter"
						value={filterCategory}
						onChange={(e) => setFilterCategory(e.target.value)}
						style={selectStyles}
					>
						{categories.map((cat) => (
							<option key={cat} value={cat}>
								{cat === 'all'
									? 'All Categories'
									: CATEGORY_NAMES[cat] || cat}
							</option>
						))}
					</select>
				</div>

				<div style={filterStyles}>
					<label
						htmlFor="sort-by"
						style={{
							fontSize: '13px',
							fontWeight: '500',
							color: '#1d2327',
						}}
					>
						Sort by:
					</label>
					<select
						id="sort-by"
						value={sortBy}
						onChange={(e) => setSortBy(e.target.value)}
						style={selectStyles}
					>
						<option value="priority">Priority</option>
						<option value="category">Category</option>
					</select>
				</div>
			</div>

			{/* Recommendations List */}
			<ul style={listStyles}>
				{filteredRecommendations.map((recommendation, index) => {
					const isExpanded = expandedItems.has(recommendation.id);
					const isExecuting = executingAction === recommendation.id;
					const isCompleted = completedActions.has(recommendation.id);
					const severity = recommendation.impact_estimate || 'low';
					const severityColor = SEVERITY_COLORS[severity];
					const severityIcon = SEVERITY_ICONS[severity];
					const categoryIcon =
						CATEGORY_ICONS[recommendation.category] ||
						'dashicons-admin-generic';

					return (
						<RecommendationItem
							key={recommendation.id}
							recommendation={recommendation}
							isExpanded={isExpanded}
							isExecuting={isExecuting}
							isCompleted={isCompleted}
							severityColor={severityColor}
							severityIcon={severityIcon}
							categoryIcon={categoryIcon}
							onToggleExpanded={() =>
								toggleExpanded(recommendation.id)
							}
							onFixNow={() => handleFixNow(recommendation)}
							onPreview={() => handlePreview(recommendation)}
							onDismiss={() => handleDismiss(recommendation.id)}
							onLearnMore={() => handleLearnMore(recommendation)}
							isLast={
								index === filteredRecommendations.length - 1
							}
						/>
					);
				})}
			</ul>
		</div>
	);
};

/**
 * Individual Recommendation Item Component
 * @param root0
 * @param root0.recommendation
 * @param root0.isExpanded
 * @param root0.isExecuting
 * @param root0.isCompleted
 * @param root0.severityColor
 * @param root0.severityIcon
 * @param root0.categoryIcon
 * @param root0.onToggleExpanded
 * @param root0.onFixNow
 * @param root0.onPreview
 * @param root0.onDismiss
 * @param root0.onLearnMore
 * @param root0.isLast
 */
const RecommendationItem = ({
	recommendation,
	isExpanded,
	isExecuting,
	isCompleted,
	severityColor,
	severityIcon,
	categoryIcon,
	onToggleExpanded,
	onFixNow,
	onPreview,
	onDismiss,
	onLearnMore,
	isLast,
}) => {
	const itemStyles = {
		padding: '20px',
		borderBottom: isLast ? 'none' : '1px solid #f0f0f1',
		animation: isCompleted
			? 'slideOut 0.5s ease-out forwards'
			: 'slideIn 0.3s ease-out',
		position: 'relative',
	};

	const headerStyles = {
		display: 'flex',
		alignItems: 'flex-start',
		gap: '16px',
		cursor: 'pointer',
	};

	const iconContainerStyles = {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		width: '40px',
		height: '40px',
		borderRadius: '50%',
		backgroundColor: `${severityColor}15`,
		flexShrink: 0,
	};

	const contentStyles = {
		flex: 1,
		minWidth: 0,
	};

	const titleRowStyles = {
		display: 'flex',
		alignItems: 'center',
		gap: '12px',
		marginBottom: '8px',
	};

	const titleStyles = {
		margin: 0,
		fontSize: '15px',
		fontWeight: '600',
		color: '#1d2327',
		flex: 1,
	};

	const badgeStyles = {
		padding: '2px 8px',
		borderRadius: '12px',
		fontSize: '11px',
		fontWeight: '600',
		textTransform: 'uppercase',
		backgroundColor: `${severityColor}20`,
		color: severityColor,
	};

	const categoryBadgeStyles = {
		padding: '2px 8px',
		borderRadius: '12px',
		fontSize: '11px',
		fontWeight: '500',
		backgroundColor: '#f0f0f1',
		color: '#50575e',
		display: 'flex',
		alignItems: 'center',
		gap: '4px',
	};

	const descriptionStyles = {
		margin: '0 0 12px 0',
		fontSize: '13px',
		color: '#646970',
		lineHeight: '1.6',
	};

	const actionsStyles = {
		display: 'flex',
		gap: '8px',
		flexWrap: 'wrap',
		marginTop: '16px',
	};

	const buttonBaseStyles = {
		padding: '8px 14px',
		fontSize: '13px',
		fontWeight: '500',
		borderRadius: '4px',
		border: 'none',
		cursor: 'pointer',
		transition: 'all 0.2s ease',
		display: 'flex',
		alignItems: 'center',
		gap: '6px',
	};

	const primaryButtonStyles = {
		...buttonBaseStyles,
		backgroundColor: '#2271b1',
		color: '#fff',
	};

	const secondaryButtonStyles = {
		...buttonBaseStyles,
		backgroundColor: '#fff',
		color: '#2271b1',
		border: '1px solid #2271b1',
	};

	const dismissButtonStyles = {
		...buttonBaseStyles,
		backgroundColor: '#fff',
		color: '#646970',
		border: '1px solid #c3c4c7',
	};

	const expandIconStyles = {
		fontSize: '20px',
		color: '#646970',
		transition: 'transform 0.2s ease',
		transform: isExpanded ? 'rotate(180deg)' : 'rotate(0deg)',
	};

	const detailsStyles = {
		marginTop: '16px',
		paddingTop: '16px',
		borderTop: '1px solid #f0f0f1',
		animation: 'slideIn 0.3s ease-out',
	};

	const stepsStyles = {
		margin: '12px 0 0 0',
		paddingLeft: '24px',
		fontSize: '13px',
		color: '#646970',
		lineHeight: '1.8',
	};

	const completedOverlayStyles = {
		position: 'absolute',
		top: 0,
		left: 0,
		right: 0,
		bottom: 0,
		backgroundColor: 'rgba(0, 163, 42, 0.1)',
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		animation: 'slideIn 0.3s ease-out',
	};

	const checkmarkStyles = {
		fontSize: '48px',
		color: '#00a32a',
		animation: 'checkmark 0.5s ease-out',
	};

	return (
		<li style={itemStyles}>
			{/* Completed overlay */}
			{isCompleted && (
				<div style={completedOverlayStyles}>
					<span
						className="dashicons dashicons-yes-alt"
						style={checkmarkStyles}
					/>
				</div>
			)}

			{/* Header (clickable to expand) */}
			<div
				style={headerStyles}
				onClick={onToggleExpanded}
				onKeyDown={(e) => {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						onToggleExpanded();
					}
				}}
				role="button"
				tabIndex={0}
				aria-expanded={isExpanded}
			>
				{/* Category Icon */}
				<div style={iconContainerStyles}>
					<span
						className={`dashicons ${categoryIcon}`}
						style={{ fontSize: '20px', color: severityColor }}
						aria-hidden="true"
					/>
				</div>

				{/* Content */}
				<div style={contentStyles}>
					<div style={titleRowStyles}>
						<h3 style={titleStyles}>{recommendation.title}</h3>
						<span style={badgeStyles}>
							{recommendation.impact_estimate}
						</span>
						<span style={categoryBadgeStyles}>
							<span
								className={`dashicons ${severityIcon}`}
								style={{ fontSize: '12px' }}
							/>
							{CATEGORY_NAMES[recommendation.category] ||
								recommendation.category}
						</span>
						<span
							className="dashicons dashicons-arrow-down"
							style={expandIconStyles}
						/>
					</div>
					<p style={descriptionStyles}>
						{recommendation.description}
					</p>
				</div>
			</div>

			{/* Expanded Details */}
			{isExpanded && (
				<div style={detailsStyles}>
					{/* Steps */}
					{recommendation.steps &&
						recommendation.steps.length > 0 && (
							<div>
								<h4
									style={{
										margin: '0 0 8px 0',
										fontSize: '13px',
										fontWeight: '600',
										color: '#1d2327',
									}}
								>
									Steps to resolve:
								</h4>
								<ol style={stepsStyles}>
									{recommendation.steps.map((step, idx) => (
										<li key={idx}>{step}</li>
									))}
								</ol>
							</div>
						)}

					{/* Action Buttons */}
					<div style={actionsStyles}>
						{recommendation.action_type === 'cleanup' ||
						recommendation.action_type === 'optimize' ? (
							<button
								onClick={(e) => {
									e.stopPropagation();
									onFixNow();
								}}
								disabled={isExecuting}
								style={primaryButtonStyles}
								onMouseEnter={(e) =>
									!isExecuting &&
									(e.target.style.backgroundColor = '#135e96')
								}
								onMouseLeave={(e) =>
									!isExecuting &&
									(e.target.style.backgroundColor = '#2271b1')
								}
							>
								{isExecuting ? (
									<>
										<span
											className="dashicons dashicons-update"
											style={{
												fontSize: '16px',
												animation:
													'spin 1s linear infinite',
											}}
										/>
										Executing...
									</>
								) : (
									<>
										<span
											className="dashicons dashicons-controls-play"
											style={{ fontSize: '16px' }}
										/>
										Fix Now
									</>
								)}
							</button>
						) : null}

						<button
							onClick={(e) => {
								e.stopPropagation();
								onPreview();
							}}
							style={secondaryButtonStyles}
							onMouseEnter={(e) =>
								(e.target.style.backgroundColor = '#f0f6fc')
							}
							onMouseLeave={(e) =>
								(e.target.style.backgroundColor = '#fff')
							}
						>
							<span
								className="dashicons dashicons-visibility"
								style={{ fontSize: '16px' }}
							/>
							Preview
						</button>

						<button
							onClick={(e) => {
								e.stopPropagation();
								onLearnMore();
							}}
							style={secondaryButtonStyles}
							onMouseEnter={(e) =>
								(e.target.style.backgroundColor = '#f0f6fc')
							}
							onMouseLeave={(e) =>
								(e.target.style.backgroundColor = '#fff')
							}
						>
							<span
								className="dashicons dashicons-external"
								style={{ fontSize: '16px' }}
							/>
							Learn More
						</button>

						<button
							onClick={(e) => {
								e.stopPropagation();
								onDismiss();
							}}
							style={dismissButtonStyles}
							onMouseEnter={(e) =>
								(e.target.style.backgroundColor = '#f0f0f1')
							}
							onMouseLeave={(e) =>
								(e.target.style.backgroundColor = '#fff')
							}
						>
							<span
								className="dashicons dashicons-dismiss"
								style={{ fontSize: '16px' }}
							/>
							Dismiss
						</button>
					</div>
				</div>
			)}
		</li>
	);
};

export default Recommendations;
