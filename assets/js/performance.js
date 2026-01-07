/**
 * Performance JavaScript
 *
 * React app for performance monitoring and optimization.
 *
 * @package WPAdminHealth
 */

(function($) {
	'use strict';

	const { useState, useEffect } = wp.element;
	const { apiFetch } = wp;

	/**
	 * Performance Score Circle Component
	 */
	function PerformanceScoreCircle({ score, grade }) {
		useEffect(() => {
			const circle = document.querySelector('.wpha-performance-score-fill');
			if (circle && score !== null) {
				const circumference = 2 * Math.PI * 85;
				const offset = circumference - (score / 100) * circumference;
				circle.style.strokeDasharray = circumference;
				circle.style.strokeDashoffset = offset;

				// Hide skeleton and show value.
				const skeleton = document.querySelector('.wpha-performance-score-skeleton');
				const valueEl = document.querySelector('.wpha-performance-score-value');
				if (skeleton) skeleton.style.display = 'none';
				if (valueEl) {
					valueEl.textContent = score;
					valueEl.style.display = 'block';
				}
			}
		}, [score]);

		return null;
	}

	/**
	 * Plugin Impact Table Component
	 */
	function PluginImpactTable({ plugins, sortBy, onSort }) {
		if (!plugins || plugins.length === 0) {
			return wp.element.createElement('p', null, 'No plugin data available.');
		}

		const sortedPlugins = [...plugins].sort((a, b) => {
			if (sortBy === 'load_time') return b.load_time - a.load_time;
			if (sortBy === 'memory') return b.memory - a.memory;
			if (sortBy === 'queries') return b.queries - a.queries;
			return 0;
		});

		return wp.element.createElement('div', { className: 'wpha-plugin-table-wrapper' },
			wp.element.createElement('table', { className: 'wpha-plugin-table' },
				wp.element.createElement('thead', null,
					wp.element.createElement('tr', null,
						wp.element.createElement('th', null, 'Plugin Name'),
						wp.element.createElement('th', {
							className: 'sortable',
							onClick: () => onSort('load_time')
						},
							'Load Time (ms) ',
							sortBy === 'load_time' && wp.element.createElement('span', { className: 'dashicons dashicons-arrow-down' })
						),
						wp.element.createElement('th', {
							className: 'sortable',
							onClick: () => onSort('memory')
						},
							'Memory (KB) ',
							sortBy === 'memory' && wp.element.createElement('span', { className: 'dashicons dashicons-arrow-down' })
						),
						wp.element.createElement('th', {
							className: 'sortable',
							onClick: () => onSort('queries')
						},
							'Queries ',
							sortBy === 'queries' && wp.element.createElement('span', { className: 'dashicons dashicons-arrow-down' })
						)
					)
				),
				wp.element.createElement('tbody', null,
					sortedPlugins.map((plugin, index) =>
						wp.element.createElement('tr', { key: index },
							wp.element.createElement('td', null, plugin.name),
							wp.element.createElement('td', null,
								wp.element.createElement('div', { className: 'wpha-impact-bar' },
									wp.element.createElement('div', {
										className: 'wpha-impact-bar-fill',
										style: { width: `${Math.min(100, plugin.load_time)}%` }
									}),
									wp.element.createElement('span', { className: 'wpha-impact-value' },
										plugin.load_time.toFixed(1)
									)
								)
							),
							wp.element.createElement('td', null,
								wp.element.createElement('div', { className: 'wpha-impact-bar' },
									wp.element.createElement('div', {
										className: 'wpha-impact-bar-fill',
										style: { width: `${Math.min(100, (plugin.memory / 1024) * 100)}%` }
									}),
									wp.element.createElement('span', { className: 'wpha-impact-value' },
										plugin.memory.toLocaleString()
									)
								)
							),
							wp.element.createElement('td', null, plugin.queries)
						)
					)
				)
			)
		);
	}

	/**
	 * Query Analysis Component
	 */
	function QueryAnalysis({ data }) {
		if (!data) {
			return wp.element.createElement('p', null, 'Loading query analysis...');
		}

		return wp.element.createElement('div', { className: 'wpha-query-stats' },
			wp.element.createElement('div', { className: 'wpha-stat-item' },
				wp.element.createElement('span', { className: 'wpha-stat-label' }, 'Total Queries:'),
				wp.element.createElement('span', { className: 'wpha-stat-value' }, data.total_queries)
			),
			data.savequeries ?
				wp.element.createElement('div', null,
					wp.element.createElement('h3', null, 'Slow Queries (>50ms)'),
					data.slow_queries && data.slow_queries.length > 0 ?
						wp.element.createElement('div', { className: 'wpha-slow-queries' },
							data.slow_queries.map((query, index) =>
								wp.element.createElement('div', { key: index, className: 'wpha-slow-query-item' },
									wp.element.createElement('div', { className: 'wpha-query-time' },
										`${(query.time * 1000).toFixed(2)}ms`
									),
									wp.element.createElement('div', { className: 'wpha-query-text' }, query.query),
									wp.element.createElement('div', { className: 'wpha-query-caller' }, query.caller)
								)
							)
						) :
						wp.element.createElement('p', null, 'No slow queries detected.')
				) :
				wp.element.createElement('div', { className: 'notice notice-info' },
					wp.element.createElement('p', null,
						'Enable SAVEQUERIES in wp-config.php to see detailed query analysis.'
					)
				)
		);
	}

	/**
	 * Heartbeat Control Component
	 */
	function HeartbeatControl({ settings, onUpdate }) {
		const [localSettings, setLocalSettings] = useState(settings);

		const handleToggle = async (location) => {
			const newEnabled = !localSettings[location].enabled;
			const newSettings = {
				...localSettings,
				[location]: { ...localSettings[location], enabled: newEnabled }
			};
			setLocalSettings(newSettings);

			try {
				await apiFetch({
					path: '/wpha/v1/performance/heartbeat',
					method: 'POST',
					data: {
						location: location,
						enabled: newEnabled,
						interval: localSettings[location].interval
					}
				});
				onUpdate(newSettings);
			} catch (error) {
				console.error('Error updating heartbeat:', error);
			}
		};

		const handleIntervalChange = async (location, interval) => {
			const newSettings = {
				...localSettings,
				[location]: { ...localSettings[location], interval: parseInt(interval) }
			};
			setLocalSettings(newSettings);

			try {
				await apiFetch({
					path: '/wpha/v1/performance/heartbeat',
					method: 'POST',
					data: {
						location: location,
						enabled: localSettings[location].enabled,
						interval: parseInt(interval)
					}
				});
				onUpdate(newSettings);
			} catch (error) {
				console.error('Error updating heartbeat:', error);
			}
		};

		if (!localSettings) {
			return wp.element.createElement('p', null, 'Loading heartbeat settings...');
		}

		return wp.element.createElement('div', { className: 'wpha-heartbeat-settings' },
			Object.entries(localSettings).map(([location, config]) =>
				wp.element.createElement('div', { key: location, className: 'wpha-heartbeat-item' },
					wp.element.createElement('div', { className: 'wpha-heartbeat-header' },
						wp.element.createElement('h4', null, location.charAt(0).toUpperCase() + location.slice(1)),
						wp.element.createElement('label', { className: 'wpha-toggle' },
							wp.element.createElement('input', {
								type: 'checkbox',
								checked: config.enabled,
								onChange: () => handleToggle(location)
							}),
							wp.element.createElement('span', { className: 'wpha-toggle-slider' })
						)
					),
					config.enabled && wp.element.createElement('div', { className: 'wpha-heartbeat-interval' },
						wp.element.createElement('label', null, 'Interval (seconds):'),
						wp.element.createElement('input', {
							type: 'range',
							min: '15',
							max: '120',
							step: '15',
							value: config.interval,
							onChange: (e) => handleIntervalChange(location, e.target.value)
						}),
						wp.element.createElement('span', { className: 'wpha-interval-value' },
							`${config.interval}s`
						)
					)
				)
			)
		);
	}

	/**
	 * Cache Status Component
	 */
	function CacheStatus({ data }) {
		if (!data) {
			return wp.element.createElement('p', null, 'Loading cache status...');
		}

		return wp.element.createElement('div', { className: 'wpha-cache-info' },
			wp.element.createElement('div', { className: 'wpha-cache-item' },
				wp.element.createElement('span', { className: 'wpha-cache-label' }, 'Object Cache:'),
				wp.element.createElement('span', {
					className: `wpha-cache-status ${data.object_cache_enabled ? 'enabled' : 'disabled'}`
				},
					data.object_cache_enabled ?
						`Enabled (${data.cache_type})` :
						'Disabled'
				)
			),
			wp.element.createElement('div', { className: 'wpha-cache-item' },
				wp.element.createElement('span', { className: 'wpha-cache-label' }, 'OPcache:'),
				wp.element.createElement('span', {
					className: `wpha-cache-status ${data.opcache_enabled ? 'enabled' : 'disabled'}`
				},
					data.opcache_enabled ? 'Enabled' : 'Disabled'
				)
			),
			data.opcache_stats && wp.element.createElement('div', { className: 'wpha-opcache-stats' },
				wp.element.createElement('h4', null, 'OPcache Statistics'),
				wp.element.createElement('div', { className: 'wpha-stat-item' },
					wp.element.createElement('span', null, 'Hit Rate:'),
					wp.element.createElement('span', null, `${data.opcache_stats.hit_rate.toFixed(2)}%`)
				),
				wp.element.createElement('div', { className: 'wpha-stat-item' },
					wp.element.createElement('span', null, 'Memory Usage:'),
					wp.element.createElement('span', null,
						`${(data.opcache_stats.memory_usage / 1024 / 1024).toFixed(2)} MB`
					)
				),
				wp.element.createElement('div', { className: 'wpha-stat-item' },
					wp.element.createElement('span', null, 'Cached Scripts:'),
					wp.element.createElement('span', null, data.opcache_stats.cached_scripts)
				)
			)
		);
	}

	/**
	 * Autoload Analysis Component
	 */
	function AutoloadAnalysis({ data }) {
		if (!data) {
			return wp.element.createElement('p', null, 'Loading autoload analysis...');
		}

		return wp.element.createElement('div', { className: 'wpha-autoload-info' },
			wp.element.createElement('div', { className: 'wpha-autoload-summary' },
				wp.element.createElement('h3', null, 'Total Autoload Size'),
				wp.element.createElement('p', { className: 'wpha-autoload-size' },
					`${data.total_size_mb} MB`
				),
				wp.element.createElement('p', { className: 'wpha-autoload-count' },
					`${data.count} options`
				)
			),
			data.options && data.options.length > 0 &&
				wp.element.createElement('div', { className: 'wpha-autoload-options' },
					wp.element.createElement('h4', null, 'Largest Autoloaded Options'),
					wp.element.createElement('table', { className: 'wpha-autoload-table' },
						wp.element.createElement('thead', null,
							wp.element.createElement('tr', null,
								wp.element.createElement('th', null, 'Option Name'),
								wp.element.createElement('th', null, 'Size')
							)
						),
						wp.element.createElement('tbody', null,
							data.options.slice(0, 10).map((option, index) =>
								wp.element.createElement('tr', { key: index },
									wp.element.createElement('td', null, option.name),
									wp.element.createElement('td', null,
										`${(option.size / 1024).toFixed(2)} KB`
									)
								)
							)
						)
					)
				)
		);
	}

	/**
	 * Recommendations Component
	 */
	function Recommendations({ recommendations }) {
		if (!recommendations || recommendations.length === 0) {
			return wp.element.createElement('div', { className: 'wpha-no-recommendations' },
				wp.element.createElement('p', null, 'No recommendations at this time. Great job!')
			);
		}

		return wp.element.createElement('div', { className: 'wpha-recommendations-items' },
			recommendations.map((rec, index) =>
				wp.element.createElement('div', {
					key: index,
					className: `wpha-recommendation wpha-recommendation-${rec.type}`
				},
					wp.element.createElement('div', { className: 'wpha-recommendation-icon' },
						rec.type === 'warning' ?
							wp.element.createElement('span', { className: 'dashicons dashicons-warning' }) :
							wp.element.createElement('span', { className: 'dashicons dashicons-info' })
					),
					wp.element.createElement('div', { className: 'wpha-recommendation-content' },
						wp.element.createElement('h4', null, rec.title),
						wp.element.createElement('p', null, rec.description)
					)
				)
			)
		);
	}

	/**
	 * Main Performance App Component
	 */
	function PerformanceApp() {
		const [scoreData, setScoreData] = useState(null);
		const [pluginData, setPluginData] = useState(null);
		const [queryData, setQueryData] = useState(null);
		const [heartbeatSettings, setHeartbeatSettings] = useState(null);
		const [cacheData, setCacheData] = useState(null);
		const [autoloadData, setAutoloadData] = useState(null);
		const [recommendations, setRecommendations] = useState(null);
		const [sortBy, setSortBy] = useState('load_time');
		const [loading, setLoading] = useState(true);

		useEffect(() => {
			fetchPerformanceData();
		}, []);

		const fetchPerformanceData = async () => {
			try {
				const [score, plugins, queries, heartbeat, cache, autoload, recs] = await Promise.all([
					apiFetch({ path: '/wpha/v1/performance/score' }),
					apiFetch({ path: '/wpha/v1/performance/plugins' }),
					apiFetch({ path: '/wpha/v1/performance/queries' }),
					apiFetch({ path: '/wpha/v1/performance/heartbeat' }),
					apiFetch({ path: '/wpha/v1/performance/cache' }),
					apiFetch({ path: '/wpha/v1/performance/autoload' }),
					apiFetch({ path: '/wpha/v1/performance/recommendations' })
				]);

				setScoreData(score.data);
				setPluginData(plugins.data.plugins);
				setQueryData(queries.data);
				setHeartbeatSettings(heartbeat.data);
				setCacheData(cache.data);
				setAutoloadData(autoload.data);
				setRecommendations(recs.data.recommendations);
				setLoading(false);
			} catch (error) {
				console.error('Error fetching performance data:', error);
				setLoading(false);
			}
		};

		if (loading) {
			return wp.element.createElement('div', { className: 'wpha-loading' }, 'Loading...');
		}

		return wp.element.createElement('div', null,
			wp.element.createElement(PerformanceScoreCircle, {
				score: scoreData?.score,
				grade: scoreData?.grade
			}),
			wp.element.createElement('div', { className: 'wpha-plugin-impact-table' },
				wp.element.createElement(PluginImpactTable, {
					plugins: pluginData,
					sortBy: sortBy,
					onSort: setSortBy
				})
			),
			wp.element.createElement('div', { className: 'wpha-query-analysis-data' },
				wp.element.createElement(QueryAnalysis, { data: queryData })
			),
			wp.element.createElement('div', { className: 'wpha-heartbeat-controls' },
				wp.element.createElement(HeartbeatControl, {
					settings: heartbeatSettings,
					onUpdate: setHeartbeatSettings
				})
			),
			wp.element.createElement('div', { className: 'wpha-cache-status-data' },
				wp.element.createElement(CacheStatus, { data: cacheData })
			),
			wp.element.createElement('div', { className: 'wpha-autoload-data' },
				wp.element.createElement(AutoloadAnalysis, { data: autoloadData })
			),
			wp.element.createElement('div', { className: 'wpha-recommendations-list' },
				wp.element.createElement(Recommendations, { recommendations: recommendations })
			)
		);
	}

	// Initialize the app when DOM is ready.
	$(document).ready(function() {
		const rootElement = document.getElementById('wpha-performance-root');
		if (rootElement && wp.element && apiFetch) {
			wp.element.render(
				wp.element.createElement(PerformanceApp),
				rootElement
			);

			// Hide all skeleton loaders.
			$('.wpha-section-skeleton').hide();
		}
	});

})(jQuery);
