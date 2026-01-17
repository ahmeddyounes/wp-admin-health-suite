/**
 * Tests for API Client Utilities
 *
 * @package
 */

import { apiClient, ApiError, RequestCache } from './api.js';

describe('RequestCache', () => {
	let cache;

	beforeEach(() => {
		cache = new RequestCache(1000); // 1 second TTL
	});

	describe('generateKey', () => {
		it('generates key from path and empty params', () => {
			const key = cache.generateKey('/test/endpoint', {});
			expect(key).toBe('/test/endpoint?');
		});

		it('generates key from path and params', () => {
			const key = cache.generateKey('/test/endpoint', { a: 1, b: 'two' });
			expect(key).toContain('/test/endpoint?');
			expect(key).toContain('a=1');
			expect(key).toContain('b="two"');
		});

		it('sorts params alphabetically for consistent keys', () => {
			const key1 = cache.generateKey('/test', { z: 1, a: 2 });
			const key2 = cache.generateKey('/test', { a: 2, z: 1 });
			expect(key1).toBe(key2);
		});
	});

	describe('set and get', () => {
		it('stores and retrieves data', () => {
			cache.set('test-key', { data: 'value' });
			expect(cache.get('test-key')).toEqual({ data: 'value' });
		});

		it('returns null for non-existent key', () => {
			expect(cache.get('non-existent')).toBeNull();
		});

		it('returns null for expired entry', async () => {
			cache.set('test-key', { data: 'value' }, 50); // 50ms TTL

			// Wait for expiry
			await new Promise((resolve) => setTimeout(resolve, 100));

			expect(cache.get('test-key')).toBeNull();
		});

		it('uses default TTL when not specified', () => {
			cache.set('test-key', { data: 'value' });
			expect(cache.get('test-key')).toEqual({ data: 'value' });
		});
	});

	describe('delete', () => {
		it('removes specific key from cache', () => {
			cache.set('key1', 'value1');
			cache.set('key2', 'value2');

			cache.delete('key1');

			expect(cache.get('key1')).toBeNull();
			expect(cache.get('key2')).toBe('value2');
		});
	});

	describe('clear', () => {
		it('removes all entries from cache', () => {
			cache.set('key1', 'value1');
			cache.set('key2', 'value2');

			cache.clear();

			expect(cache.get('key1')).toBeNull();
			expect(cache.get('key2')).toBeNull();
			expect(cache.size).toBe(0);
		});
	});

	describe('clearPattern', () => {
		it('clears entries matching string pattern', () => {
			cache.set('/api/users', 'users');
			cache.set('/api/posts', 'posts');
			cache.set('/other/data', 'data');

			cache.clearPattern('/api/');

			expect(cache.get('/api/users')).toBeNull();
			expect(cache.get('/api/posts')).toBeNull();
			expect(cache.get('/other/data')).toBe('data');
		});

		it('clears entries matching regex pattern', () => {
			cache.set('/api/users/1', 'user1');
			cache.set('/api/users/2', 'user2');
			cache.set('/api/posts', 'posts');

			cache.clearPattern(/\/api\/users\/\d+/);

			expect(cache.get('/api/users/1')).toBeNull();
			expect(cache.get('/api/users/2')).toBeNull();
			expect(cache.get('/api/posts')).toBe('posts');
		});
	});

	describe('size', () => {
		it('returns correct number of entries', () => {
			expect(cache.size).toBe(0);

			cache.set('key1', 'value1');
			expect(cache.size).toBe(1);

			cache.set('key2', 'value2');
			expect(cache.size).toBe(2);

			cache.delete('key1');
			expect(cache.size).toBe(1);
		});
	});
});

describe('ApiError', () => {
	describe('constructor', () => {
		it('creates error with all properties', () => {
			const error = new ApiError(
				'Test error',
				404,
				'not_found',
				{ extra: 'data' },
				new Error('inner')
			);

			expect(error.message).toBe('Test error');
			expect(error.status).toBe(404);
			expect(error.code).toBe('not_found');
			expect(error.data).toEqual({ extra: 'data' });
			expect(error.innerError).toBeInstanceOf(Error);
			expect(error.name).toBe('ApiError');
		});

		it('creates error with defaults', () => {
			const error = new ApiError('Test error');

			expect(error.message).toBe('Test error');
			expect(error.status).toBe(0);
			expect(error.code).toBe('');
			expect(error.data).toBeNull();
			expect(error.innerError).toBeNull();
		});
	});

	describe('isNetworkError', () => {
		it('returns true for status 0', () => {
			const error = new ApiError('Network error', 0);
			expect(error.isNetworkError()).toBe(true);
		});

		it('returns true for network_error code', () => {
			const error = new ApiError('Network error', 500, 'network_error');
			expect(error.isNetworkError()).toBe(true);
		});

		it('returns false for other errors', () => {
			const error = new ApiError('Server error', 500);
			expect(error.isNetworkError()).toBe(false);
		});
	});

	describe('isTimeoutError', () => {
		it('returns true for timeout_error code', () => {
			const error = new ApiError('Timeout', 0, 'timeout_error');
			expect(error.isTimeoutError()).toBe(true);
		});

		it('returns false for other errors', () => {
			const error = new ApiError('Error', 500);
			expect(error.isTimeoutError()).toBe(false);
		});
	});

	describe('isAuthError', () => {
		it('returns true for status 401', () => {
			const error = new ApiError('Unauthorized', 401);
			expect(error.isAuthError()).toBe(true);
		});

		it('returns true for status 403', () => {
			const error = new ApiError('Forbidden', 403);
			expect(error.isAuthError()).toBe(true);
		});

		it('returns false for other statuses', () => {
			const error = new ApiError('Not found', 404);
			expect(error.isAuthError()).toBe(false);
		});
	});

	describe('isValidationError', () => {
		it('returns true for status 400', () => {
			const error = new ApiError('Bad request', 400);
			expect(error.isValidationError()).toBe(true);
		});

		it('returns true for validation_error code', () => {
			const error = new ApiError('Invalid', 0, 'validation_error');
			expect(error.isValidationError()).toBe(true);
		});

		it('returns false for other errors', () => {
			const error = new ApiError('Error', 500);
			expect(error.isValidationError()).toBe(false);
		});
	});

	describe('isServerError', () => {
		it('returns true for 5xx status codes', () => {
			expect(new ApiError('Error', 500).isServerError()).toBe(true);
			expect(new ApiError('Error', 502).isServerError()).toBe(true);
			expect(new ApiError('Error', 503).isServerError()).toBe(true);
			expect(new ApiError('Error', 599).isServerError()).toBe(true);
		});

		it('returns false for non-5xx status codes', () => {
			expect(new ApiError('Error', 400).isServerError()).toBe(false);
			expect(new ApiError('Error', 404).isServerError()).toBe(false);
			expect(new ApiError('Error', 0).isServerError()).toBe(false);
		});
	});

	describe('shouldRetry', () => {
		it('returns true for network errors', () => {
			const error = new ApiError('Network error', 0);
			expect(error.shouldRetry()).toBe(true);
		});

		it('returns true for server errors', () => {
			const error = new ApiError('Server error', 500);
			expect(error.shouldRetry()).toBe(true);
		});

		it('returns false for client errors', () => {
			const error = new ApiError('Bad request', 400);
			expect(error.shouldRetry()).toBe(false);
		});

		it('returns false for auth errors', () => {
			const error = new ApiError('Forbidden', 403);
			expect(error.shouldRetry()).toBe(false);
		});
	});

	describe('getUserMessage', () => {
		it('returns network error message', () => {
			const error = new ApiError('Error', 0);
			expect(error.getUserMessage()).toContain('Network error');
		});

		it('returns timeout error message', () => {
			const error = new ApiError('Error', 0, 'timeout_error');
			expect(error.getUserMessage()).toContain('timed out');
		});

		it('returns auth error message', () => {
			const error = new ApiError('Error', 403);
			expect(error.getUserMessage()).toContain('permission');
		});

		it('returns validation error message', () => {
			const error = new ApiError('Custom validation error', 400);
			expect(error.getUserMessage()).toBe('Custom validation error');
		});

		it('returns server error message', () => {
			const error = new ApiError('Error', 500);
			expect(error.getUserMessage()).toContain('Server error');
		});

		it('returns original message for unknown errors', () => {
			const error = new ApiError('Something went wrong', 404);
			expect(error.getUserMessage()).toBe('Something went wrong');
		});
	});
});

describe('ApiClient', () => {
	let mockApiFetch;

	beforeEach(() => {
		// Mock wp.apiFetch
		mockApiFetch = jest.fn();
		window.wp = {
			apiFetch: mockApiFetch,
		};

		// Clear the client's cache before each test
		apiClient.clearCache();
	});

	afterEach(() => {
		delete window.wp;
		jest.clearAllMocks();
	});

	describe('buildPath', () => {
		it('builds path with namespace', () => {
			const path = apiClient.buildPath('test/endpoint');
			expect(path).toBe('/wp-admin-health/v1/test/endpoint');
		});

		it('removes leading slash from endpoint', () => {
			const path = apiClient.buildPath('/test/endpoint');
			expect(path).toBe('/wp-admin-health/v1/test/endpoint');
		});
	});

	describe('get', () => {
		it('makes GET request', async () => {
			mockApiFetch.mockResolvedValue({ data: 'success' });

			const result = await apiClient.get('test', { cache: false });

			expect(mockApiFetch).toHaveBeenCalledWith(
				expect.objectContaining({
					path: '/wp-admin-health/v1/test',
					method: 'GET',
				})
			);
			expect(result).toEqual({ data: 'success' });
		});

		it('adds query params to path', async () => {
			mockApiFetch.mockResolvedValue({ data: 'success' });

			await apiClient.get('test', {
				params: { foo: 'bar' },
				cache: false,
			});

			expect(mockApiFetch).toHaveBeenCalledWith(
				expect.objectContaining({
					path: '/wp-admin-health/v1/test?foo=bar',
				})
			);
		});

		it('caches GET responses by default', async () => {
			mockApiFetch.mockResolvedValue({ data: 'cached' });

			await apiClient.get('cached-endpoint');
			await apiClient.get('cached-endpoint');

			expect(mockApiFetch).toHaveBeenCalledTimes(1);
		});

		it('skips cache when cache option is false', async () => {
			mockApiFetch.mockResolvedValue({ data: 'not-cached' });

			await apiClient.get('endpoint', { cache: false });
			await apiClient.get('endpoint', { cache: false });

			expect(mockApiFetch).toHaveBeenCalledTimes(2);
		});
	});

	describe('post', () => {
		it('makes POST request with data', async () => {
			mockApiFetch.mockResolvedValue({ success: true });

			const result = await apiClient.post('test', { name: 'value' });

			expect(mockApiFetch).toHaveBeenCalledWith(
				expect.objectContaining({
					path: '/wp-admin-health/v1/test',
					method: 'POST',
					data: { name: 'value' },
				})
			);
			expect(result).toEqual({ success: true });
		});

		it('does not cache POST responses', async () => {
			mockApiFetch.mockResolvedValue({ success: true });

			await apiClient.post('endpoint', { data: 1 });
			await apiClient.post('endpoint', { data: 1 });

			expect(mockApiFetch).toHaveBeenCalledTimes(2);
		});
	});

	describe('put', () => {
		it('makes PUT request with data', async () => {
			mockApiFetch.mockResolvedValue({ success: true });

			await apiClient.put('test/1', { name: 'updated' });

			expect(mockApiFetch).toHaveBeenCalledWith(
				expect.objectContaining({
					path: '/wp-admin-health/v1/test/1',
					method: 'PUT',
					data: { name: 'updated' },
				})
			);
		});
	});

	describe('patch', () => {
		it('makes PATCH request with data', async () => {
			mockApiFetch.mockResolvedValue({ success: true });

			await apiClient.patch('test/1', { name: 'patched' });

			expect(mockApiFetch).toHaveBeenCalledWith(
				expect.objectContaining({
					path: '/wp-admin-health/v1/test/1',
					method: 'PATCH',
					data: { name: 'patched' },
				})
			);
		});
	});

	describe('delete', () => {
		it('makes DELETE request', async () => {
			mockApiFetch.mockResolvedValue({ success: true });

			await apiClient.delete('test/1');

			expect(mockApiFetch).toHaveBeenCalledWith(
				expect.objectContaining({
					path: '/wp-admin-health/v1/test/1',
					method: 'DELETE',
				})
			);
		});
	});

	describe('error handling', () => {
		it('throws ApiError when wp.apiFetch is not available', async () => {
			delete window.wp;

			await expect(
				apiClient.get('test', { cache: false })
			).rejects.toThrow(ApiError);
		});

		it('normalizes WordPress API errors', async () => {
			mockApiFetch.mockRejectedValue({
				code: 'rest_forbidden',
				message: 'Access denied',
			});

			await expect(
				apiClient.get('test', { cache: false })
			).rejects.toMatchObject({
				status: 403,
				code: 'rest_forbidden',
			});
		});

		it('normalizes network errors', async () => {
			mockApiFetch.mockRejectedValue(new TypeError('NetworkError'));

			await expect(
				apiClient.get('test', { cache: false })
			).rejects.toMatchObject({
				code: 'network_error',
			});
		});
	});

	describe('retry logic', () => {
		it('retries on server error', async () => {
			mockApiFetch
				.mockRejectedValueOnce({
					code: 'server_error',
					message: 'Server error',
					status: 500,
				})
				.mockResolvedValueOnce({ data: 'success' });

			const result = await apiClient.get('test', { cache: false });

			expect(mockApiFetch).toHaveBeenCalledTimes(2);
			expect(result).toEqual({ data: 'success' });
		});

		it('does not retry on client error', async () => {
			mockApiFetch.mockRejectedValue({
				code: 'rest_invalid_param',
				message: 'Invalid parameter',
			});

			await expect(
				apiClient.get('test', { cache: false })
			).rejects.toThrow();
			expect(mockApiFetch).toHaveBeenCalledTimes(1);
		});

		it('respects max retries', async () => {
			mockApiFetch.mockRejectedValue(new TypeError('NetworkError'));

			await expect(
				apiClient.get('test', { cache: false })
			).rejects.toThrow();

			// Initial request + 2 retries = 3 total calls
			expect(mockApiFetch).toHaveBeenCalledTimes(3);
		});
	});

	describe('request deduplication', () => {
		it('deduplicates concurrent identical GET requests', async () => {
			mockApiFetch.mockImplementation(
				() =>
					new Promise((resolve) =>
						setTimeout(() => resolve({ data: 'result' }), 50)
					)
			);

			const [result1, result2] = await Promise.all([
				apiClient.get('dedup-test', { cache: false }),
				apiClient.get('dedup-test', { cache: false }),
			]);

			expect(mockApiFetch).toHaveBeenCalledTimes(1);
			expect(result1).toEqual(result2);
		});

		it('does not deduplicate when skipDeduplication is true', async () => {
			mockApiFetch.mockImplementation(
				() =>
					new Promise((resolve) =>
						setTimeout(() => resolve({ data: 'result' }), 50)
					)
			);

			await Promise.all([
				apiClient.get('dedup-test2', {
					cache: false,
					skipDeduplication: true,
				}),
				apiClient.get('dedup-test2', {
					cache: false,
					skipDeduplication: true,
				}),
			]);

			expect(mockApiFetch).toHaveBeenCalledTimes(2);
		});
	});

	describe('cache management', () => {
		it('clears all cache', async () => {
			mockApiFetch.mockResolvedValue({ data: 'value' });

			await apiClient.get('endpoint1');
			await apiClient.get('endpoint2');

			apiClient.clearCache();

			await apiClient.get('endpoint1');
			await apiClient.get('endpoint2');

			expect(mockApiFetch).toHaveBeenCalledTimes(4);
		});

		it('clears cache by pattern', async () => {
			mockApiFetch.mockResolvedValue({ data: 'value' });

			await apiClient.get('users/1');
			await apiClient.get('posts/1');

			apiClient.clearCachePattern(/users/);

			await apiClient.get('users/1');
			await apiClient.get('posts/1');

			// users/1 called twice, posts/1 called once (cached)
			expect(mockApiFetch).toHaveBeenCalledTimes(3);
		});

		it('invalidates specific cache entry', async () => {
			mockApiFetch.mockResolvedValue({ data: 'value' });

			await apiClient.get('endpoint');

			apiClient.invalidateCache('endpoint');

			await apiClient.get('endpoint');

			expect(mockApiFetch).toHaveBeenCalledTimes(2);
		});
	});
});
