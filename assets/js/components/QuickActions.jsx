/**
 * Quick Actions Component
 *
 * A React component that displays a grid of quick action buttons for common
 * administrative tasks like cleaning revisions, clearing transients, etc.
 * Includes confirmation modals for destructive actions, progress indicators,
 * and toast notifications.
 *
 * @package WPAdminHealth
 */

import React, { useState } from 'react';

/**
 * Actions configuration array
 * Each action includes:
 * - id: unique identifier
 * - label: button text
 * - icon: dashicon class name
 * - description: tooltip and modal description
 * - action: the API endpoint or action to trigger
 * - confirmRequired: whether to show confirmation modal before execution
 */
const ACTIONS = [
	{
		id: 'clean-revisions',
		label: 'Clean Revisions',
		icon: 'dashicons-backup',
		description: 'Remove old post revisions to reduce database size and improve performance',
		action: 'clean_revisions',
		confirmRequired: true,
	},
	{
		id: 'clear-transients',
		label: 'Clear Transients',
		icon: 'dashicons-trash',
		description: 'Delete expired and orphaned transients from the database',
		action: 'clear_transients',
		confirmRequired: true,
	},
	{
		id: 'find-unused-media',
		label: 'Find Unused Media',
		icon: 'dashicons-images-alt2',
		description: 'Scan for media files that are not attached to any posts or pages',
		action: 'find_unused_media',
		confirmRequired: false,
	},
	{
		id: 'optimize-tables',
		label: 'Optimize Tables',
		icon: 'dashicons-database',
		description: 'Optimize database tables to reclaim unused space and improve query performance',
		action: 'optimize_tables',
		confirmRequired: true,
	},
	{
		id: 'full-scan',
		label: 'Full Scan',
		icon: 'dashicons-search',
		description: 'Run a comprehensive health scan to identify all potential issues',
		action: 'full_scan',
		confirmRequired: false,
	},
];

/**
 * QuickActions Component
 *
 * @returns {JSX.Element} Rendered component
 */
const QuickActions = () => {
	const [activeAction, setActiveAction] = useState(null);
	const [showConfirmModal, setShowConfirmModal] = useState(false);
	const [executingAction, setExecutingAction] = useState(null);
	const [toasts, setToasts] = useState([]);

	/**
	 * Handle action button click
	 *
	 * @param {Object} action - The action object from ACTIONS array
	 */
	const handleActionClick = (action) => {
		if (action.confirmRequired) {
			setActiveAction(action);
			setShowConfirmModal(true);
		} else {
			executeAction(action);
		}
	};

	/**
	 * Execute the action by calling the API
	 *
	 * @param {Object} action - The action object from ACTIONS array
	 */
	const executeAction = async (action) => {
		setShowConfirmModal(false);
		setExecutingAction(action.id);

		try {
			// Use WordPress REST API
			const response = await fetch(
				`${window.wpApiSettings?.root || '/wp-json/'}wpha/v1/actions/${action.action}`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.wpApiSettings?.nonce || '',
					},
				}
			);

			const data = await response.json();

			if (response.ok && data.success) {
				showToast('success', data.message || `${action.label} completed successfully`);
			} else {
				showToast('error', data.message || `Failed to execute ${action.label}`);
			}
		} catch (error) {
			showToast('error', `Error executing ${action.label}: ${error.message}`);
		} finally {
			setExecutingAction(null);
		}
	};

	/**
	 * Show a toast notification
	 *
	 * @param {string} type - 'success' or 'error'
	 * @param {string} message - The message to display
	 */
	const showToast = (type, message) => {
		const toastId = Date.now();
		const newToast = { id: toastId, type, message };

		setToasts((prev) => [...prev, newToast]);

		// Auto-dismiss after 5 seconds
		setTimeout(() => {
			setToasts((prev) => prev.filter((toast) => toast.id !== toastId));
		}, 5000);
	};

	/**
	 * Close the confirmation modal
	 */
	const handleCloseModal = () => {
		setShowConfirmModal(false);
		setActiveAction(null);
	};

	/**
	 * Confirm and execute the action
	 */
	const handleConfirmAction = () => {
		if (activeAction) {
			executeAction(activeAction);
		}
	};

	/**
	 * Handle keyboard navigation for action buttons
	 */
	const handleKeyPress = (e, action) => {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			handleActionClick(action);
		}
	};

	/**
	 * Dismiss a toast notification
	 */
	const dismissToast = (toastId) => {
		setToasts((prev) => prev.filter((toast) => toast.id !== toastId));
	};

	// Grid container styles
	const gridStyles = {
		display: 'grid',
		gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))',
		gap: '16px',
		padding: '20px',
	};

	// Action button styles
	const buttonStyles = {
		display: 'flex',
		flexDirection: 'column',
		alignItems: 'center',
		justifyContent: 'center',
		padding: '24px 16px',
		backgroundColor: '#fff',
		border: '1px solid #c3c4c7',
		borderRadius: '4px',
		cursor: 'pointer',
		transition: 'all 0.2s ease',
		outline: 'none',
		minHeight: '140px',
		position: 'relative',
	};

	const buttonDisabledStyles = {
		opacity: 0.6,
		cursor: 'not-allowed',
		pointerEvents: 'none',
	};

	// Icon styles
	const iconStyles = {
		fontSize: '32px',
		width: '32px',
		height: '32px',
		color: '#2271b1',
		marginBottom: '12px',
	};

	// Label styles
	const labelStyles = {
		fontSize: '14px',
		fontWeight: '600',
		color: '#1d2327',
		textAlign: 'center',
		lineHeight: '1.4',
	};

	// Spinner styles for executing state
	const spinnerStyles = {
		position: 'absolute',
		top: '50%',
		left: '50%',
		transform: 'translate(-50%, -50%)',
		width: '24px',
		height: '24px',
		border: '3px solid #f3f3f3',
		borderTop: '3px solid #2271b1',
		borderRadius: '50%',
		animation: 'spin 1s linear infinite',
	};

	// Modal overlay styles
	const modalOverlayStyles = {
		position: 'fixed',
		top: 0,
		left: 0,
		right: 0,
		bottom: 0,
		backgroundColor: 'rgba(0, 0, 0, 0.5)',
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'center',
		zIndex: 100000,
	};

	// Modal content styles
	const modalStyles = {
		backgroundColor: '#fff',
		borderRadius: '4px',
		padding: '24px',
		maxWidth: '500px',
		width: '90%',
		boxShadow: '0 4px 16px rgba(0, 0, 0, 0.2)',
	};

	// Modal header styles
	const modalHeaderStyles = {
		fontSize: '18px',
		fontWeight: '600',
		color: '#1d2327',
		marginBottom: '16px',
	};

	// Modal body styles
	const modalBodyStyles = {
		fontSize: '14px',
		color: '#50575e',
		marginBottom: '24px',
		lineHeight: '1.6',
	};

	// Modal footer styles
	const modalFooterStyles = {
		display: 'flex',
		justifyContent: 'flex-end',
		gap: '12px',
	};

	// Button primary styles
	const buttonPrimaryStyles = {
		padding: '8px 16px',
		backgroundColor: '#2271b1',
		color: '#fff',
		border: 'none',
		borderRadius: '4px',
		cursor: 'pointer',
		fontSize: '14px',
		fontWeight: '500',
		transition: 'background-color 0.2s ease',
	};

	// Button secondary styles
	const buttonSecondaryStyles = {
		padding: '8px 16px',
		backgroundColor: '#fff',
		color: '#2271b1',
		border: '1px solid #2271b1',
		borderRadius: '4px',
		cursor: 'pointer',
		fontSize: '14px',
		fontWeight: '500',
		transition: 'all 0.2s ease',
	};

	// Toast container styles
	const toastContainerStyles = {
		position: 'fixed',
		top: '32px',
		right: '32px',
		zIndex: 100001,
		display: 'flex',
		flexDirection: 'column',
		gap: '12px',
		maxWidth: '400px',
	};

	// Toast styles
	const toastStyles = {
		padding: '16px',
		backgroundColor: '#fff',
		border: '1px solid #c3c4c7',
		borderRadius: '4px',
		boxShadow: '0 4px 8px rgba(0, 0, 0, 0.1)',
		display: 'flex',
		alignItems: 'flex-start',
		gap: '12px',
		animation: 'slideIn 0.3s ease-out',
	};

	const toastSuccessStyles = {
		borderLeftColor: '#00a32a',
		borderLeftWidth: '4px',
	};

	const toastErrorStyles = {
		borderLeftColor: '#d63638',
		borderLeftWidth: '4px',
	};

	// Add CSS animation for spinner, toast, and hover effects
	const styleSheet = `
		@keyframes spin {
			0% { transform: translate(-50%, -50%) rotate(0deg); }
			100% { transform: translate(-50%, -50%) rotate(360deg); }
		}
		@keyframes slideIn {
			from {
				transform: translateX(100%);
				opacity: 0;
			}
			to {
				transform: translateX(0);
				opacity: 1;
			}
		}
		.quick-action-button:hover:not([aria-busy="true"]) {
			transform: translateY(-2px);
			box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
		}
	`;

	return (
		<div className="quick-actions">
			<style>{styleSheet}</style>

			{/* Actions Grid */}
			<div style={gridStyles}>
				{ACTIONS.map((action) => {
					const isExecuting = executingAction === action.id;

					return (
						<div
							key={action.id}
							className="quick-action-button"
							role="button"
							tabIndex={isExecuting ? -1 : 0}
							title={action.description}
							aria-label={`${action.label}: ${action.description}`}
							aria-busy={isExecuting}
							onClick={() => !isExecuting && handleActionClick(action)}
							onKeyPress={(e) => !isExecuting && handleKeyPress(e, action)}
							style={{
								...buttonStyles,
								...(isExecuting ? buttonDisabledStyles : {}),
							}}
						>
							{isExecuting && <div style={spinnerStyles} aria-label="Loading" />}

							<span
								className={`dashicons ${action.icon}`}
								style={{
									...iconStyles,
									...(isExecuting ? { opacity: 0.3 } : {}),
								}}
								aria-hidden="true"
							/>

							<span
								style={{
									...labelStyles,
									...(isExecuting ? { opacity: 0.3 } : {}),
								}}
							>
								{action.label}
							</span>
						</div>
					);
				})}
			</div>

			{/* Confirmation Modal */}
			{showConfirmModal && activeAction && (
				<div
					className="quick-action-modal-overlay"
					style={modalOverlayStyles}
					onClick={handleCloseModal}
					role="dialog"
					aria-modal="true"
					aria-labelledby="modal-title"
					aria-describedby="modal-description"
				>
					<div
						className="quick-action-modal"
						style={modalStyles}
						onClick={(e) => e.stopPropagation()}
					>
						<h2 id="modal-title" style={modalHeaderStyles}>
							Confirm {activeAction.label}
						</h2>
						<p id="modal-description" style={modalBodyStyles}>
							{activeAction.description}
							<br />
							<br />
							Are you sure you want to proceed with this action?
						</p>
						<div style={modalFooterStyles}>
							<button
								style={buttonSecondaryStyles}
								onClick={handleCloseModal}
								onMouseEnter={(e) => {
									e.target.style.backgroundColor = '#f0f0f1';
								}}
								onMouseLeave={(e) => {
									e.target.style.backgroundColor = '#fff';
								}}
							>
								Cancel
							</button>
							<button
								style={buttonPrimaryStyles}
								onClick={handleConfirmAction}
								onMouseEnter={(e) => {
									e.target.style.backgroundColor = '#135e96';
								}}
								onMouseLeave={(e) => {
									e.target.style.backgroundColor = '#2271b1';
								}}
							>
								Confirm
							</button>
						</div>
					</div>
				</div>
			)}

			{/* Toast Notifications */}
			{toasts.length > 0 && (
				<div style={toastContainerStyles}>
					{toasts.map((toast) => (
						<div
							key={toast.id}
							className="quick-action-toast"
							style={{
								...toastStyles,
								...(toast.type === 'success' ? toastSuccessStyles : toastErrorStyles),
							}}
							role="alert"
							aria-live="polite"
						>
							<span
								className={`dashicons ${
									toast.type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning'
								}`}
								style={{
									fontSize: '20px',
									width: '20px',
									height: '20px',
									color: toast.type === 'success' ? '#00a32a' : '#d63638',
									flexShrink: 0,
								}}
								aria-hidden="true"
							/>
							<div style={{ flex: 1, fontSize: '14px', color: '#1d2327' }}>
								{toast.message}
							</div>
							<button
								onClick={() => dismissToast(toast.id)}
								style={{
									background: 'none',
									border: 'none',
									cursor: 'pointer',
									padding: '0',
									color: '#646970',
									fontSize: '18px',
									lineHeight: '1',
									flexShrink: 0,
								}}
								aria-label="Dismiss notification"
							>
								×
							</button>
						</div>
					))}
				</div>
			)}
		</div>
	);
};

export default QuickActions;
