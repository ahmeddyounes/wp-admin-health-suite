/**
 * API Client Utilities
 *
 * Provides a centralized API client for making REST API calls with
 * error handling, request caching, and proper WordPress REST nonce usage.
 *
 * @package
 */

/**
 * Simple in-memory cache for API responses
 */
class RequestCache {
	/**
	 * Create a new RequestCache instance
	 *
	 * @param {number} defaultTTL - Default time-to-live in milliseconds
	 */
	constructor(defaultTTL = 60000) {
		this.cache = new Map();
		this.defaultTTL = defaultTTL;
	}

	/**
	 * Generate a cache key from request options
	 *
	 * @param {string} path   - API path
	 * @param {Object} params - Query parameters
	 * @return {string} Cache key
	 */
	generateKey(path, params = {}) {
		const sortedParams = Object.keys(params)
			.sort()
			.map((key) => `${key}=${JSON.stringify(params[key])}`)
			.join('&');
		return `${path}?${sortedParams}`;
	}

	/**
	 * Get cached response if valid
	 *
	 * @param {string} key - Cache key
	 * @return {*|null} Cached data or null
	 */
	get(key) {
		const entry = this.cache.get(key);
		if (!entry) {
			return null;
		}

		if (Date.now() > entry.expiry) {
			this.cache.delete(key);
			return null;
		}

		return entry.data;
	}

	/**
	 * Set cached response
	 *
	 * @param {string} key  - Cache key
	 * @param {*}      data - Data to cache
	 * @param {number} ttl  - Time-to-live in milliseconds
	 */
	set(key, data, ttl = this.defaultTTL) {
		this.cache.set(key, {
			data,
			expiry: Date.now() + ttl,
		});
	}

	/**
	 * Clear specific key from cache
	 *
	 * @param {string} key - Cache key to clear
	 */
	delete(key) {
		this.cache.delete(key);
	}

	/**
	 * Clear all cached responses
	 */
	clear() {
		this.cache.clear();
	}

	/**
	 * Clear cached responses matching a pattern
	 *
	 * @param {string|RegExp} pattern - Pattern to match cache keys
	 */
	clearPattern(pattern) {
		const regex = pattern instanceof RegExp ? pattern : new RegExp(pattern);
		for (const key of this.cache.keys()) {
			if (regex.test(key)) {
				this.cache.delete(key);
			}
		}
	}

	/**
	 * Get cache size
	 *
	 * @return {number} Number of cached entries
	 */
	get size() {
		return this.cache.size;
	}
}

/**
 * API Error class for structured error handling
 */
export class ApiError extends Error {
	/**
	 * Create a new ApiError instance
	 *
	 * @param {string} message    - Error message
	 * @param {number} status     - HTTP status code
	 * @param {string} code       - Error code
	 * @param {*}      data       - Additional error data
	 * @param {Error}  innerError - Original error if any
	 */
	constructor(
		message,
		status = 0,
		code = '',
		data = null,
		innerError = null
	) {
		super(message);
		this.name = 'ApiError';
		this.status = status;
		this.code = code;
		this.data = data;
		this.innerError = innerError;
	}

	/**
	 * Check if error is a network error
	 *
	 * @return {boolean} True if network error
	 */
	isNetworkError() {
		return this.status === 0 || this.code === 'network_error';
	}

	/**
	 * Check if error is a timeout error
	 *
	 * @return {boolean} True if timeout error
	 */
	isTimeoutError() {
		return this.code === 'timeout_error';
	}

	/**
	 * Check if error is an authentication error
	 *
	 * @return {boolean} True if authentication error
	 */
	isAuthError() {
		return this.status === 401 || this.status === 403;
	}

	/**
	 * Check if error is a validation error
	 *
	 * @return {boolean} True if validation error
	 */
	isValidationError() {
		return this.status === 400 || this.code === 'validation_error';
	}

	/**
	 * Check if error is a server error
	 *
	 * @return {boolean} True if server error (5xx)
	 */
	isServerError() {
		return this.status >= 500 && this.status < 600;
	}

	/**
	 * Check if the error should be retried
	 *
	 * @return {boolean} True if the request should be retried
	 */
	shouldRetry() {
		return this.isNetworkError() || this.isServerError();
	}

	/**
	 * Get a user-friendly error message
	 *
	 * @return {string} User-friendly message
	 */
	getUserMessage() {
		// Check timeout errors first since they also have status 0
		if (this.isTimeoutError()) {
			return 'Request timed out. Please try again.';
		}
		if (this.isNetworkError()) {
			return 'Network error. Please check your connection and try again.';
		}
		if (this.isAuthError()) {
			return 'You do not have permission to perform this action.';
		}
		if (this.isValidationError()) {
			return this.message || 'Please check your input and try again.';
		}
		if (this.isServerError()) {
			return 'Server error. Please try again later.';
		}
		return this.message || 'An unexpected error occurred.';
	}
}

/**
 * Get the REST namespace from localized data.
 *
 * @return {string} REST namespace
 */
function getRestNamespace() {
	if (typeof window !== 'undefined' && window.wpAdminHealthData) {
		return window.wpAdminHealthData.rest_namespace || 'wpha/v1';
	}
	return 'wpha/v1';
}

/**
 * API Client for making REST API calls
 */
class ApiClient {
	/**
	 * Create a new ApiClient instance
	 *
	 * @param {Object} options - Client options
	 */
	constructor(options = {}) {
		this.namespace = options.namespace || getRestNamespace();
		this.cache = new RequestCache(options.cacheTTL || 60000);
		this.maxRetries = options.maxRetries || 2;
		this.timeout = options.timeout || 30000;
		this.pendingRequests = new Map();
	}

	/**
	 * Get the wp.apiFetch function
	 *
	 * @return {Function|null} apiFetch function or null
	 */
	getApiFetch() {
		return window.wp && window.wp.apiFetch;
	}

	/**
	 * Build full API path
	 *
	 * @param {string} endpoint - API endpoint path
	 * @return {string} Full API path
	 */
	buildPath(endpoint) {
		// Remove leading slash if present
		const cleanEndpoint = endpoint.replace(/^\//, '');
		return `/${this.namespace}/${cleanEndpoint}`;
	}

	/**
	 * Make a GET request
	 *
	 * @param {string} endpoint - API endpoint
	 * @param {Object} options  - Request options
	 * @return {Promise} API response promise
	 */
	get(endpoint, options = {}) {
		return this.request(endpoint, {
			...options,
			method: 'GET',
		});
	}

	/**
	 * Make a POST request
	 *
	 * @param {string} endpoint - API endpoint
	 * @param {Object} data     - Request body data
	 * @param {Object} options  - Request options
	 * @return {Promise} API response promise
	 */
	post(endpoint, data = {}, options = {}) {
		return this.request(endpoint, {
			...options,
			method: 'POST',
			data,
		});
	}

	/**
	 * Make a PUT request
	 *
	 * @param {string} endpoint - API endpoint
	 * @param {Object} data     - Request body data
	 * @param {Object} options  - Request options
	 * @return {Promise} API response promise
	 */
	put(endpoint, data = {}, options = {}) {
		return this.request(endpoint, {
			...options,
			method: 'PUT',
			data,
		});
	}

	/**
	 * Make a PATCH request
	 *
	 * @param {string} endpoint - API endpoint
	 * @param {Object} data     - Request body data
	 * @param {Object} options  - Request options
	 * @return {Promise} API response promise
	 */
	patch(endpoint, data = {}, options = {}) {
		return this.request(endpoint, {
			...options,
			method: 'PATCH',
			data,
		});
	}

	/**
	 * Make a DELETE request
	 *
	 * @param {string} endpoint - API endpoint
	 * @param {Object} data     - Request body data
	 * @param {Object} options  - Request options
	 * @return {Promise} API response promise
	 */
	delete(endpoint, data = {}, options = {}) {
		return this.request(endpoint, {
			...options,
			method: 'DELETE',
			data,
		});
	}

	/**
	 * Generic request method with retry logic and caching
	 *
	 * @param {string} endpoint - API endpoint
	 * @param {Object} options  - Request options
	 * @return {Promise} API response promise
	 */
	async request(endpoint, options = {}) {
		const apiFetch = this.getApiFetch();
		if (!apiFetch) {
			throw new ApiError(
				'wp.apiFetch is not available',
				0,
				'api_unavailable'
			);
		}

		const {
			method = 'GET',
			data,
			params,
			cache: useCache = method === 'GET',
			cacheTTL,
			skipDeduplication = false,
			signal,
			...restOptions
		} = options;

		const path = this.buildPath(endpoint);
		const cacheKey = this.cache.generateKey(path, params);

		// Check cache for GET requests
		if (useCache && method === 'GET') {
			const cachedData = this.cache.get(cacheKey);
			if (cachedData !== null) {
				return cachedData;
			}
		}

		// Request deduplication for GET requests
		if (!skipDeduplication && method === 'GET') {
			const pendingRequest = this.pendingRequests.get(cacheKey);
			if (pendingRequest) {
				return pendingRequest;
			}
		}

		// Build request options
		const requestOptions = {
			path,
			method,
			...restOptions,
		};

		// Add query parameters for GET requests
		if (params && method === 'GET') {
			const queryString = new URLSearchParams(params).toString();
			requestOptions.path = queryString ? `${path}?${queryString}` : path;
		}

		// Add body data for non-GET requests
		if (data && method !== 'GET') {
			requestOptions.data = data;
		}

		// Create the request promise with retry logic
		const requestPromise = this.executeWithRetry(requestOptions, signal);

		// Track pending request for deduplication
		if (!skipDeduplication && method === 'GET') {
			this.pendingRequests.set(cacheKey, requestPromise);
		}

		try {
			const response = await requestPromise;

			// Cache successful GET responses
			if (useCache && method === 'GET') {
				this.cache.set(cacheKey, response, cacheTTL);
			}

			return response;
		} finally {
			// Clean up pending request tracking
			if (!skipDeduplication && method === 'GET') {
				this.pendingRequests.delete(cacheKey);
			}
		}
	}

	/**
	 * Execute request with retry logic
	 *
	 * @param {Object}      requestOptions - Request options for apiFetch
	 * @param {AbortSignal} signal         - Optional abort signal
	 * @param {number}      retryCount     - Current retry count
	 * @return {Promise} API response promise
	 */
	async executeWithRetry(requestOptions, signal, retryCount = 0) {
		const apiFetch = this.getApiFetch();

		try {
			return await apiFetch(requestOptions);
		} catch (error) {
			const apiError = this.normalizeError(error);

			// Check if we should retry
			if (retryCount < this.maxRetries && apiError.shouldRetry()) {
				// Exponential backoff delay
				const delay = Math.pow(2, retryCount) * 1000;
				await this.delay(delay);

				// Check if request was aborted
				if (signal && signal.aborted) {
					throw new ApiError(
						'Request was cancelled',
						0,
						'request_cancelled'
					);
				}

				return this.executeWithRetry(
					requestOptions,
					signal,
					retryCount + 1
				);
			}

			throw apiError;
		}
	}

	/**
	 * Normalize error to ApiError
	 *
	 * @param {Error|Object} error - Error object
	 * @return {ApiError} Normalized error
	 */
	normalizeError(error) {
		// Already an ApiError
		if (error instanceof ApiError) {
			return error;
		}

		// Handle wp.apiFetch error format
		if (error.code && error.message) {
			const status = this.getStatusFromCode(error.code);
			return new ApiError(
				error.message,
				status,
				error.code,
				error.data,
				error
			);
		}

		// Handle fetch Response errors
		if (error.status) {
			return new ApiError(
				error.statusText || 'Request failed',
				error.status,
				'http_error',
				null,
				error
			);
		}

		// Handle network errors
		if (
			error.name === 'TypeError' ||
			error.message?.includes('NetworkError')
		) {
			return new ApiError(
				'Network error',
				0,
				'network_error',
				null,
				error
			);
		}

		// Handle timeout errors
		if (error.name === 'AbortError' || error.message?.includes('timeout')) {
			return new ApiError(
				'Request timed out',
				0,
				'timeout_error',
				null,
				error
			);
		}

		// Generic error
		return new ApiError(
			error.message || 'Unknown error',
			0,
			'unknown_error',
			null,
			error
		);
	}

	/**
	 * Get HTTP status from WordPress error code
	 *
	 * @param {string} code - WordPress error code
	 * @return {number} HTTP status code
	 */
	getStatusFromCode(code) {
		const codeStatusMap = {
			rest_forbidden: 403,
			rest_no_route: 404,
			rest_invalid_param: 400,
			rest_missing_callback_param: 400,
			rest_unauthorized: 401,
			rest_cookie_invalid_nonce: 403,
		};
		return codeStatusMap[code] || 0;
	}

	/**
	 * Promise-based delay helper
	 *
	 * @param {number} ms - Delay in milliseconds
	 * @return {Promise} Promise that resolves after delay
	 */
	delay(ms) {
		return new Promise((resolve) => setTimeout(resolve, ms));
	}

	/**
	 * Clear all cached responses
	 */
	clearCache() {
		this.cache.clear();
	}

	/**
	 * Clear cached responses matching a pattern
	 *
	 * @param {string|RegExp} pattern - Pattern to match
	 */
	clearCachePattern(pattern) {
		this.cache.clearPattern(pattern);
	}

	/**
	 * Invalidate cache for a specific endpoint
	 *
	 * @param {string} endpoint - API endpoint
	 * @param {Object} params   - Query parameters
	 */
	invalidateCache(endpoint, params = {}) {
		const path = this.buildPath(endpoint);
		const cacheKey = this.cache.generateKey(path, params);
		this.cache.delete(cacheKey);
	}
}

// Create singleton instance
const apiClient = new ApiClient();

// Export the singleton instance
export { apiClient, RequestCache };

// Export default with commonly used methods
export default apiClient;
