/**
 * Admin JavaScript - Core
 *
 * Core admin JavaScript for WP Admin Health Suite.
 * Provides utilities, API wrapper, notifications, event system, and error handling.
 *
 * @package WPAdminHealth
 */

(function(window, $) {
	'use strict';

	// Ensure wp.apiFetch is available
	const apiFetch = window.wp && window.wp.apiFetch;
	if (!apiFetch) {
		console.error('wp.apiFetch is not available. Admin functionality may be limited.');
	}

	/**
	 * Main WPAdminHealth namespace
	 */
	window.WPAdminHealth = window.WPAdminHealth || {};

	// ============================================================================
	// API WRAPPER
	// ============================================================================

	/**
	 * API wrapper for making REST API calls
	 */
	const API = {
		/**
		 * Base namespace for REST API
		 */
		namespace: 'wp-admin-health/v1',

		/**
		 * Build full API path
		 *
		 * @param {string} endpoint - API endpoint path
		 * @return {string} Full API path
		 */
		buildPath: function(endpoint) {
			// Remove leading slash if present
			endpoint = endpoint.replace(/^\//, '');
			return `/${this.namespace}/${endpoint}`;
		},

		/**
		 * Make GET request
		 *
		 * @param {string} endpoint - API endpoint
		 * @param {Object} params - Query parameters
		 * @return {Promise} API response promise
		 */
		get: function(endpoint, params = {}) {
			return this.request(endpoint, {
				method: 'GET',
				params: params
			});
		},

		/**
		 * Make POST request
		 *
		 * @param {string} endpoint - API endpoint
		 * @param {Object} data - Request body data
		 * @return {Promise} API response promise
		 */
		post: function(endpoint, data = {}) {
			return this.request(endpoint, {
				method: 'POST',
				data: data
			});
		},

		/**
		 * Make PUT request
		 *
		 * @param {string} endpoint - API endpoint
		 * @param {Object} data - Request body data
		 * @return {Promise} API response promise
		 */
		put: function(endpoint, data = {}) {
			return this.request(endpoint, {
				method: 'PUT',
				data: data
			});
		},

		/**
		 * Make DELETE request
		 *
		 * @param {string} endpoint - API endpoint
		 * @param {Object} data - Request body data
		 * @return {Promise} API response promise
		 */
		delete: function(endpoint, data = {}) {
			return this.request(endpoint, {
				method: 'DELETE',
				data: data
			});
		},

		/**
		 * Generic request method with retry logic
		 *
		 * @param {string} endpoint - API endpoint
		 * @param {Object} options - Request options
		 * @param {number} retries - Number of retries attempted
		 * @return {Promise} API response promise
		 */
		request: function(endpoint, options = {}, retries = 0) {
			if (!apiFetch) {
				return Promise.reject(new Error('wp.apiFetch is not available'));
			}

			const path = this.buildPath(endpoint);
			const maxRetries = options.maxRetries || 2;

			// Merge with default options
			const requestOptions = {
				path: path,
				method: options.method || 'GET',
				...options
			};

			return apiFetch(requestOptions)
				.catch(error => {
					// Handle retry for network errors or 5xx errors
					if (retries < maxRetries && this.shouldRetry(error)) {
						const delay = Math.pow(2, retries) * 1000; // Exponential backoff
						return new Promise(resolve => {
							setTimeout(() => {
								resolve(this.request(endpoint, options, retries + 1));
							}, delay);
						});
					}

					// Transform error to user-friendly message
					const errorMessage = ErrorHandler.getUserFriendlyMessage(error);
					throw new Error(errorMessage);
				});
		},

		/**
		 * Check if error should trigger a retry
		 *
		 * @param {Error} error - Error object
		 * @return {boolean} True if should retry
		 */
		shouldRetry: function(error) {
			// Retry on network errors or server errors (5xx)
			if (!error.response) return true;
			const status = error.response?.status || 0;
			return status >= 500 && status < 600;
		}
	};

	// ============================================================================
	// TOAST NOTIFICATION SYSTEM
	// ============================================================================

	/**
	 * Toast notification system
	 */
	const Toast = {
		/**
		 * Container element
		 */
		container: null,

		/**
		 * Initialize toast container
		 */
		init: function() {
			if (this.container) return;

			this.container = $('<div>', {
				'class': 'wpha-toast-container',
				'aria-live': 'polite',
				'aria-atomic': 'true'
			});

			$('body').append(this.container);
		},

		/**
		 * Show toast notification
		 *
		 * @param {string} message - Message to display
		 * @param {string} type - Type: success, error, warning, info
		 * @param {number} duration - Duration in milliseconds (0 = no auto-dismiss)
		 * @return {jQuery} Toast element
		 */
		show: function(message, type = 'info', duration = 5000) {
			this.init();

			const toast = $('<div>', {
				'class': `wpha-toast wpha-toast-${type}`,
				'role': 'alert'
			});

			const icon = this.getIcon(type);
			const content = $('<div>', {
				'class': 'wpha-toast-content',
				'html': `<span class="wpha-toast-icon">${icon}</span><span class="wpha-toast-message">${message}</span>`
			});

			const closeBtn = $('<button>', {
				'class': 'wpha-toast-close',
				'type': 'button',
				'aria-label': 'Close',
				'html': '&times;'
			});

			closeBtn.on('click', () => this.dismiss(toast));

			toast.append(content, closeBtn);
			this.container.append(toast);

			// Animate in
			setTimeout(() => toast.addClass('wpha-toast-show'), 10);

			// Auto dismiss
			if (duration > 0) {
				setTimeout(() => this.dismiss(toast), duration);
			}

			return toast;
		},

		/**
		 * Dismiss toast
		 *
		 * @param {jQuery} toast - Toast element to dismiss
		 */
		dismiss: function(toast) {
			toast.removeClass('wpha-toast-show');
			setTimeout(() => toast.remove(), 300);
		},

		/**
		 * Get icon for toast type
		 *
		 * @param {string} type - Toast type
		 * @return {string} Icon HTML
		 */
		getIcon: function(type) {
			const icons = {
				'success': '&#10004;',
				'error': '&#10006;',
				'warning': '&#9888;',
				'info': '&#8505;'
			};
			return icons[type] || icons.info;
		},

		/**
		 * Convenience methods
		 */
		success: function(message, duration = 5000) {
			return this.show(message, 'success', duration);
		},

		error: function(message, duration = 7000) {
			return this.show(message, 'error', duration);
		},

		warning: function(message, duration = 6000) {
			return this.show(message, 'warning', duration);
		},

		info: function(message, duration = 5000) {
			return this.show(message, 'info', duration);
		}
	};

	// ============================================================================
	// CONFIRMATION MODAL
	// ============================================================================

	/**
	 * Confirmation modal helper
	 */
	const Modal = {
		/**
		 * Show confirmation modal
		 *
		 * @param {Object} options - Modal options
		 * @return {Promise} Promise that resolves to true/false
		 */
		confirm: function(options = {}) {
			const defaults = {
				title: wpAdminHealthData?.i18n?.confirm || 'Confirm',
				message: 'Are you sure?',
				confirmText: wpAdminHealthData?.i18n?.confirm || 'Confirm',
				cancelText: wpAdminHealthData?.i18n?.cancel || 'Cancel',
				confirmClass: 'button-primary',
				cancelClass: 'button-secondary',
				danger: false
			};

			const settings = $.extend({}, defaults, options);

			return new Promise((resolve) => {
				const modal = this.createModal(settings, resolve);
				$('body').append(modal);
				setTimeout(() => modal.addClass('wpha-modal-show'), 10);
			});
		},

		/**
		 * Create modal element
		 *
		 * @param {Object} settings - Modal settings
		 * @param {Function} resolve - Promise resolve function
		 * @return {jQuery} Modal element
		 */
		createModal: function(settings, resolve) {
			const overlay = $('<div>', {
				'class': 'wpha-modal-overlay',
				'role': 'dialog',
				'aria-modal': 'true',
				'aria-labelledby': 'wpha-modal-title'
			});

			const modal = $('<div>', {
				'class': 'wpha-modal'
			});

			const header = $('<div>', {
				'class': 'wpha-modal-header',
				'html': `<h3 id="wpha-modal-title">${settings.title}</h3>`
			});

			const body = $('<div>', {
				'class': 'wpha-modal-body',
				'html': `<p>${settings.message}</p>`
			});

			const footer = $('<div>', {
				'class': 'wpha-modal-footer'
			});

			const confirmBtn = $('<button>', {
				'class': `button ${settings.confirmClass} ${settings.danger ? 'wpha-danger' : ''}`,
				'type': 'button',
				'text': settings.confirmText
			});

			const cancelBtn = $('<button>', {
				'class': `button ${settings.cancelClass}`,
				'type': 'button',
				'text': settings.cancelText
			});

			confirmBtn.on('click', () => {
				this.closeModal(overlay);
				resolve(true);
			});

			cancelBtn.on('click', () => {
				this.closeModal(overlay);
				resolve(false);
			});

			// Close on overlay click
			overlay.on('click', (e) => {
				if (e.target === overlay[0]) {
					this.closeModal(overlay);
					resolve(false);
				}
			});

			// Close on ESC key
			$(document).on('keydown.wpha-modal', (e) => {
				if (e.key === 'Escape') {
					this.closeModal(overlay);
					resolve(false);
				}
			});

			footer.append(cancelBtn, confirmBtn);
			modal.append(header, body, footer);
			overlay.append(modal);

			return overlay;
		},

		/**
		 * Close modal
		 *
		 * @param {jQuery} overlay - Modal overlay element
		 */
		closeModal: function(overlay) {
			overlay.removeClass('wpha-modal-show');
			$(document).off('keydown.wpha-modal');
			setTimeout(() => overlay.remove(), 300);
		}
	};

	// ============================================================================
	// PROGRESS TRACKER
	// ============================================================================

	/**
	 * Progress tracker for long-running operations
	 */
	const Progress = {
		/**
		 * Active progress bars
		 */
		trackers: {},

		/**
		 * Create progress tracker
		 *
		 * @param {string} id - Unique tracker ID
		 * @param {Object} options - Tracker options
		 * @return {Object} Progress tracker instance
		 */
		create: function(id, options = {}) {
			const defaults = {
				title: 'Processing...',
				message: '',
				showPercentage: true,
				container: null
			};

			const settings = $.extend({}, defaults, options);
			const tracker = this.buildTracker(id, settings);

			if (settings.container) {
				$(settings.container).append(tracker.element);
			} else {
				$('body').append(tracker.element);
			}

			this.trackers[id] = tracker;
			return tracker;
		},

		/**
		 * Build tracker element
		 *
		 * @param {string} id - Tracker ID
		 * @param {Object} settings - Tracker settings
		 * @return {Object} Tracker object
		 */
		buildTracker: function(id, settings) {
			const element = $('<div>', {
				'class': 'wpha-progress-tracker',
				'id': `wpha-progress-${id}`,
				'role': 'progressbar',
				'aria-valuenow': '0',
				'aria-valuemin': '0',
				'aria-valuemax': '100'
			});

			const title = $('<div>', {
				'class': 'wpha-progress-title',
				'text': settings.title
			});

			const message = $('<div>', {
				'class': 'wpha-progress-message',
				'text': settings.message
			});

			const barContainer = $('<div>', {
				'class': 'wpha-progress-bar-container'
			});

			const bar = $('<div>', {
				'class': 'wpha-progress-bar',
				'style': 'width: 0%'
			});

			const percentage = $('<div>', {
				'class': 'wpha-progress-percentage',
				'text': '0%'
			});

			barContainer.append(bar);
			element.append(title, message, barContainer);

			if (settings.showPercentage) {
				element.append(percentage);
			}

			return {
				element: element,
				bar: bar,
				message: message,
				percentage: percentage,
				settings: settings,
				update: (progress, msg) => this.update(id, progress, msg),
				complete: (msg) => this.complete(id, msg),
				error: (msg) => this.error(id, msg),
				remove: () => this.remove(id)
			};
		},

		/**
		 * Update progress
		 *
		 * @param {string} id - Tracker ID
		 * @param {number} progress - Progress percentage (0-100)
		 * @param {string} message - Optional message
		 */
		update: function(id, progress, message) {
			const tracker = this.trackers[id];
			if (!tracker) return;

			progress = Math.max(0, Math.min(100, progress));

			tracker.bar.css('width', `${progress}%`);
			tracker.element.attr('aria-valuenow', progress);

			if (tracker.settings.showPercentage) {
				tracker.percentage.text(`${Math.round(progress)}%`);
			}

			if (message) {
				tracker.message.text(message);
			}
		},

		/**
		 * Mark progress as complete
		 *
		 * @param {string} id - Tracker ID
		 * @param {string} message - Completion message
		 */
		complete: function(id, message) {
			const tracker = this.trackers[id];
			if (!tracker) return;

			this.update(id, 100, message || 'Complete!');
			tracker.element.addClass('wpha-progress-complete');

			setTimeout(() => this.remove(id), 2000);
		},

		/**
		 * Mark progress as error
		 *
		 * @param {string} id - Tracker ID
		 * @param {string} message - Error message
		 */
		error: function(id, message) {
			const tracker = this.trackers[id];
			if (!tracker) return;

			tracker.element.addClass('wpha-progress-error');
			tracker.message.text(message || 'An error occurred');
		},

		/**
		 * Remove progress tracker
		 *
		 * @param {string} id - Tracker ID
		 */
		remove: function(id) {
			const tracker = this.trackers[id];
			if (!tracker) return;

			tracker.element.fadeOut(300, function() {
				$(this).remove();
			});

			delete this.trackers[id];
		}
	};

	// ============================================================================
	// LOCAL STORAGE HELPERS
	// ============================================================================

	/**
	 * Local storage wrapper with namespacing and error handling
	 */
	const Storage = {
		/**
		 * Namespace prefix for storage keys
		 */
		prefix: 'wpha_',

		/**
		 * Build namespaced key
		 *
		 * @param {string} key - Storage key
		 * @return {string} Namespaced key
		 */
		buildKey: function(key) {
			return this.prefix + key;
		},

		/**
		 * Check if localStorage is available
		 *
		 * @return {boolean} True if available
		 */
		isAvailable: function() {
			try {
				const test = '__storage_test__';
				localStorage.setItem(test, test);
				localStorage.removeItem(test);
				return true;
			} catch (e) {
				return false;
			}
		},

		/**
		 * Get item from storage
		 *
		 * @param {string} key - Storage key
		 * @param {*} defaultValue - Default value if not found
		 * @return {*} Stored value or default
		 */
		get: function(key, defaultValue = null) {
			if (!this.isAvailable()) return defaultValue;

			try {
				const item = localStorage.getItem(this.buildKey(key));
				return item !== null ? JSON.parse(item) : defaultValue;
			} catch (e) {
				console.error('Storage.get error:', e);
				return defaultValue;
			}
		},

		/**
		 * Set item in storage
		 *
		 * @param {string} key - Storage key
		 * @param {*} value - Value to store
		 * @return {boolean} True if successful
		 */
		set: function(key, value) {
			if (!this.isAvailable()) return false;

			try {
				localStorage.setItem(this.buildKey(key), JSON.stringify(value));
				return true;
			} catch (e) {
				console.error('Storage.set error:', e);
				return false;
			}
		},

		/**
		 * Remove item from storage
		 *
		 * @param {string} key - Storage key
		 * @return {boolean} True if successful
		 */
		remove: function(key) {
			if (!this.isAvailable()) return false;

			try {
				localStorage.removeItem(this.buildKey(key));
				return true;
			} catch (e) {
				console.error('Storage.remove error:', e);
				return false;
			}
		},

		/**
		 * Clear all namespaced items
		 *
		 * @return {boolean} True if successful
		 */
		clear: function() {
			if (!this.isAvailable()) return false;

			try {
				const keys = Object.keys(localStorage);
				keys.forEach(key => {
					if (key.startsWith(this.prefix)) {
						localStorage.removeItem(key);
					}
				});
				return true;
			} catch (e) {
				console.error('Storage.clear error:', e);
				return false;
			}
		},

		/**
		 * Get all namespaced items
		 *
		 * @return {Object} All stored items
		 */
		getAll: function() {
			if (!this.isAvailable()) return {};

			try {
				const items = {};
				const keys = Object.keys(localStorage);
				keys.forEach(key => {
					if (key.startsWith(this.prefix)) {
						const shortKey = key.substring(this.prefix.length);
						items[shortKey] = this.get(shortKey);
					}
				});
				return items;
			} catch (e) {
				console.error('Storage.getAll error:', e);
				return {};
			}
		}
	};

	// ============================================================================
	// EVENT SYSTEM
	// ============================================================================

	/**
	 * Custom event system for plugin communication
	 */
	const Events = {
		/**
		 * Event listeners registry
		 */
		listeners: {},

		/**
		 * Register event listener
		 *
		 * @param {string} eventName - Event name
		 * @param {Function} callback - Callback function
		 * @return {Function} Unsubscribe function
		 */
		on: function(eventName, callback) {
			if (!this.listeners[eventName]) {
				this.listeners[eventName] = [];
			}

			this.listeners[eventName].push(callback);

			// Return unsubscribe function
			return () => this.off(eventName, callback);
		},

		/**
		 * Remove event listener
		 *
		 * @param {string} eventName - Event name
		 * @param {Function} callback - Callback function
		 */
		off: function(eventName, callback) {
			if (!this.listeners[eventName]) return;

			this.listeners[eventName] = this.listeners[eventName].filter(
				cb => cb !== callback
			);
		},

		/**
		 * Trigger event
		 *
		 * @param {string} eventName - Event name
		 * @param {*} data - Event data
		 */
		trigger: function(eventName, data = {}) {
			if (!this.listeners[eventName]) return;

			this.listeners[eventName].forEach(callback => {
				try {
					callback(data);
				} catch (e) {
					console.error(`Error in event listener for ${eventName}:`, e);
				}
			});

			// Also trigger native custom event
			const event = new CustomEvent(`wpha:${eventName}`, {
				detail: data,
				bubbles: true
			});
			document.dispatchEvent(event);
		},

		/**
		 * One-time event listener
		 *
		 * @param {string} eventName - Event name
		 * @param {Function} callback - Callback function
		 */
		once: function(eventName, callback) {
			const wrapper = (data) => {
				callback(data);
				this.off(eventName, wrapper);
			};

			this.on(eventName, wrapper);
		}
	};

	// ============================================================================
	// ERROR HANDLER
	// ============================================================================

	/**
	 * Global error handling and user-friendly messages
	 */
	const ErrorHandler = {
		/**
		 * Error message mappings
		 */
		messages: {
			'network_error': 'Network error. Please check your connection and try again.',
			'server_error': 'Server error. Please try again later.',
			'permission_error': 'You do not have permission to perform this action.',
			'validation_error': 'Please check your input and try again.',
			'timeout_error': 'Request timed out. Please try again.',
			'unknown_error': 'An unexpected error occurred. Please try again.'
		},

		/**
		 * Get user-friendly error message
		 *
		 * @param {Error|Object} error - Error object
		 * @return {string} User-friendly message
		 */
		getUserFriendlyMessage: function(error) {
			// Handle string errors
			if (typeof error === 'string') {
				return error;
			}

			// Handle API error responses
			if (error.response) {
				const status = error.response.status;

				if (status === 403 || status === 401) {
					return this.messages.permission_error;
				}

				if (status >= 400 && status < 500) {
					return error.response.message || this.messages.validation_error;
				}

				if (status >= 500) {
					return this.messages.server_error;
				}
			}

			// Handle network errors
			if (error.message && error.message.includes('NetworkError')) {
				return this.messages.network_error;
			}

			// Handle timeout errors
			if (error.message && error.message.includes('timeout')) {
				return this.messages.timeout_error;
			}

			// Return error message or default
			return error.message || this.messages.unknown_error;
		},

		/**
		 * Handle error globally
		 *
		 * @param {Error} error - Error object
		 * @param {Object} options - Handler options
		 */
		handle: function(error, options = {}) {
			const defaults = {
				showToast: true,
				logToConsole: true,
				triggerEvent: true
			};

			const settings = $.extend({}, defaults, options);
			const message = this.getUserFriendlyMessage(error);

			if (settings.logToConsole) {
				console.error('WPAdminHealth Error:', error);
			}

			if (settings.showToast) {
				Toast.error(message);
			}

			if (settings.triggerEvent) {
				Events.trigger('error', { error, message });
			}

			return message;
		}
	};

	// ============================================================================
	// PUBLIC API
	// ============================================================================

	// Expose public API
	$.extend(window.WPAdminHealth, {
		API: API,
		Toast: Toast,
		Modal: Modal,
		Progress: Progress,
		Storage: Storage,
		Events: Events,
		ErrorHandler: ErrorHandler,

		// Utility methods
		utils: {
			/**
			 * Debounce function
			 *
			 * @param {Function} func - Function to debounce
			 * @param {number} wait - Wait time in milliseconds
			 * @return {Function} Debounced function
			 */
			debounce: function(func, wait) {
				let timeout;
				return function(...args) {
					clearTimeout(timeout);
					timeout = setTimeout(() => func.apply(this, args), wait);
				};
			},

			/**
			 * Throttle function
			 *
			 * @param {Function} func - Function to throttle
			 * @param {number} limit - Time limit in milliseconds
			 * @return {Function} Throttled function
			 */
			throttle: function(func, limit) {
				let inThrottle;
				return function(...args) {
					if (!inThrottle) {
						func.apply(this, args);
						inThrottle = true;
						setTimeout(() => inThrottle = false, limit);
					}
				};
			},

			/**
			 * Format bytes to human-readable string
			 *
			 * @param {number} bytes - Bytes value
			 * @param {number} decimals - Decimal places
			 * @return {string} Formatted string
			 */
			formatBytes: function(bytes, decimals = 2) {
				if (bytes === 0) return '0 Bytes';

				const k = 1024;
				const dm = decimals < 0 ? 0 : decimals;
				const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
				const i = Math.floor(Math.log(bytes) / Math.log(k));

				return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
			},

			/**
			 * Format number with separators
			 *
			 * @param {number} num - Number to format
			 * @return {string} Formatted number
			 */
			formatNumber: function(num) {
				return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
			}
		}
	});

	// ============================================================================
	// INITIALIZATION
	// ============================================================================

	$(document).ready(function() {
		// Initialize toast system
		Toast.init();

		// Set up global error boundary
		window.addEventListener('error', function(event) {
			ErrorHandler.handle(event.error, { showToast: false });
		});

		// Set up unhandled promise rejection handler
		window.addEventListener('unhandledrejection', function(event) {
			ErrorHandler.handle(event.reason, { showToast: false });
		});

		// Log initialization
		if (typeof wpAdminHealthData !== 'undefined') {
			console.log('WP Admin Health Suite initialized');
			Events.trigger('ready', { version: wpAdminHealthData.version });
		}
	});

})(window, jQuery);
