/**
 * Error Boundary Component
 *
 * A React Error Boundary that catches JavaScript errors in child components,
 * logs the error, and displays a fallback UI instead of crashing the entire app.
 *
 * @package
 */

import React, { Component } from 'react';
import PropTypes from 'prop-types';

/**
 * Error Boundary Component
 *
 * Catches JavaScript errors anywhere in the child component tree,
 * logs the error, and displays a fallback UI.
 */
class ErrorBoundary extends Component {
	constructor(props) {
		super(props);
		this.state = {
			hasError: false,
			error: null,
			errorInfo: null,
		};
	}

	/**
	 * Update state when an error is caught
	 *
	 * @param {Error} error - The error that was thrown
	 * @return {Object} New state
	 */
	static getDerivedStateFromError(error) {
		return {
			hasError: true,
			error,
		};
	}

	/**
	 * Log error information when an error is caught
	 *
	 * @param {Error}  error     - The error that was thrown
	 * @param {Object} errorInfo - Additional error information
	 */
	componentDidCatch(error, errorInfo) {
		// Log to console in development
		console.error('ErrorBoundary caught an error:', error, errorInfo);

		// Update state with error info
		this.setState({
			errorInfo,
		});

		// Call optional onError callback
		if (this.props.onError) {
			this.props.onError(error, errorInfo);
		}

		// Trigger WordPress admin notification if available
		if (window.WPAdminHealth && window.WPAdminHealth.Events) {
			window.WPAdminHealth.Events.trigger('componentError', {
				error,
				errorInfo,
				componentName: this.props.componentName || 'Unknown',
			});
		}
	}

	/**
	 * Reset the error state
	 */
	handleReset = () => {
		this.setState({
			hasError: false,
			error: null,
			errorInfo: null,
		});
	};

	/**
	 * Render the component
	 *
	 * @return {JSX.Element} Rendered component
	 */
	render() {
		const { hasError, error } = this.state;
		const { children, fallback, componentName, showDetails } = this.props;

		if (hasError) {
			// Use custom fallback if provided
			if (fallback) {
				return typeof fallback === 'function'
					? fallback({ error, reset: this.handleReset })
					: fallback;
			}

			// Default fallback UI
			return (
				<div
					className="wpha-error-boundary"
					style={{
						padding: '20px',
						backgroundColor: '#fff',
						border: '1px solid #d63638',
						borderRadius: '4px',
						textAlign: 'center',
					}}
					role="alert"
				>
					<span
						className="dashicons dashicons-warning"
						style={{
							fontSize: '48px',
							color: '#d63638',
							marginBottom: '12px',
							display: 'block',
						}}
						aria-hidden="true"
					/>
					<h3
						style={{
							margin: '0 0 8px 0',
							fontSize: '16px',
							fontWeight: '600',
							color: '#1d2327',
						}}
					>
						{componentName
							? `Error in ${componentName}`
							: 'Something went wrong'}
					</h3>
					<p
						style={{
							margin: '0 0 16px 0',
							fontSize: '14px',
							color: '#646970',
						}}
					>
						An error occurred while rendering this component.
					</p>
					{showDetails && error && (
						<details
							style={{
								marginBottom: '16px',
								textAlign: 'left',
								fontSize: '12px',
								color: '#646970',
							}}
						>
							<summary
								style={{
									cursor: 'pointer',
									marginBottom: '8px',
								}}
							>
								Error details
							</summary>
							<pre
								style={{
									padding: '12px',
									backgroundColor: '#f0f0f1',
									borderRadius: '4px',
									overflow: 'auto',
									maxHeight: '200px',
									fontSize: '11px',
									lineHeight: '1.4',
								}}
							>
								{error.toString()}
								{this.state.errorInfo?.componentStack}
							</pre>
						</details>
					)}
					<button
						onClick={this.handleReset}
						style={{
							padding: '8px 16px',
							backgroundColor: '#2271b1',
							color: '#fff',
							border: 'none',
							borderRadius: '4px',
							cursor: 'pointer',
							fontSize: '14px',
							fontWeight: '500',
						}}
					>
						Try Again
					</button>
				</div>
			);
		}

		return children;
	}
}

ErrorBoundary.propTypes = {
	children: PropTypes.node.isRequired,
	fallback: PropTypes.oneOfType([PropTypes.node, PropTypes.func]),
	onError: PropTypes.func,
	componentName: PropTypes.string,
	showDetails: PropTypes.bool,
};

ErrorBoundary.defaultProps = {
	fallback: null,
	onError: null,
	componentName: '',
	showDetails: process.env.NODE_ENV === 'development',
};

export default ErrorBoundary;
