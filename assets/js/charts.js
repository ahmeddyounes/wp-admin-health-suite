/**
 * Charts JavaScript
 *
 * Chart.js wrapper for WP Admin Health Suite.
 * Provides standardized chart creation with WordPress admin theme integration.
 *
 * @param window
 * @param $
 * @package
 */

(function (window, $) {
	'use strict';

	/**
	 * WPAdminHealthCharts namespace
	 */
	window.WPAdminHealthCharts = {
		/**
		 * Chart instances registry
		 */
		instances: {},

		/**
		 * WordPress admin color scheme
		 */
		colors: {
			primary: '#2271b1',
			secondary: '#72aee6',
			success: '#00a32a',
			warning: '#dba617',
			error: '#d63638',
			info: '#72aee6',
			gray: '#646970',
			lightGray: '#dcdcde',
			darkGray: '#3c434a',
			// Accessible color palette for charts
			palette: [
				'#2271b1', // WP Blue
				'#00a32a', // Green
				'#dba617', // Yellow/Orange
				'#d63638', // Red
				'#7c3aed', // Purple
				'#0891b2', // Cyan
				'#ea580c', // Deep Orange
				'#65a30d', // Lime
				'#dc2626', // Bright Red
				'#4f46e5', // Indigo
			],
		},

		/**
		 * Default chart options
		 */
		defaultOptions: {
			responsive: true,
			maintainAspectRatio: true,
			plugins: {
				legend: {
					display: true,
					position: 'bottom',
					labels: {
						padding: 15,
						usePointStyle: true,
						font: {
							family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
							size: 12,
						},
					},
				},
				tooltip: {
					enabled: true,
					backgroundColor: 'rgba(0, 0, 0, 0.8)',
					titleColor: '#fff',
					bodyColor: '#fff',
					borderColor: '#646970',
					borderWidth: 1,
					padding: 12,
					displayColors: true,
					callbacks: {},
				},
			},
		},

		/**
		 * Initialize charts module
		 */
		init() {
			if (typeof Chart === 'undefined') {
				console.error(
					'Chart.js library is not loaded. Charts will not be available.'
				);
				return;
			}

			// Set global Chart.js defaults
			Chart.defaults.color = this.colors.gray;
			Chart.defaults.font.family =
				'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

			console.log('WPAdminHealthCharts initialized');
		},

		/**
		 * Create a doughnut chart for health score breakdown
		 *
		 * @param {string|HTMLElement} canvas  - Canvas element or selector
		 * @param {Object}             data    - Chart data
		 * @param {Object}             options - Additional chart options
		 * @return {Chart} Chart instance
		 */
		createDoughnutChart(canvas, data, options = {}) {
			const element = this.getCanvasElement(canvas);
			if (!element) return null;

			const chartData = {
				labels: data.labels || [],
				datasets: [
					{
						data: data.values || [],
						backgroundColor:
							data.colors ||
							this.colors.palette.slice(0, data.values.length),
						borderWidth: 2,
						borderColor: '#fff',
					},
				],
			};

			const chartOptions = this.mergeOptions(
				{
					cutout: '65%',
					plugins: {
						legend: {
							position: options.legendPosition || 'right',
						},
						tooltip: {
							callbacks: {
								label(context) {
									const label = context.label || '';
									const value = context.parsed || 0;
									const total = context.dataset.data.reduce(
										(a, b) => a + b,
										0
									);
									const percentage = (
										(value / total) *
										100
									).toFixed(1);
									return `${label}: ${value} (${percentage}%)`;
								},
							},
						},
					},
				},
				options
			);

			return this.createChart(
				element,
				'doughnut',
				chartData,
				chartOptions
			);
		},

		/**
		 * Create a bar chart for plugin/theme comparison
		 *
		 * @param {string|HTMLElement} canvas  - Canvas element or selector
		 * @param {Object}             data    - Chart data
		 * @param {Object}             options - Additional chart options
		 * @return {Chart} Chart instance
		 */
		createBarChart(canvas, data, options = {}) {
			const element = this.getCanvasElement(canvas);
			if (!element) return null;

			const datasets = (data.datasets || []).map((dataset, index) => ({
				label: dataset.label || '',
				data: dataset.values || [],
				backgroundColor:
					dataset.color ||
					this.colors.palette[index % this.colors.palette.length],
				borderColor:
					dataset.borderColor ||
					this.colors.palette[index % this.colors.palette.length],
				borderWidth: 1,
			}));

			const chartData = {
				labels: data.labels || [],
				datasets,
			};

			const chartOptions = this.mergeOptions(
				{
					scales: {
						y: {
							beginAtZero: true,
							grid: {
								color: this.colors.lightGray,
							},
							ticks: {
								callback:
									options.formatValue ||
									function (value) {
										return value;
									},
							},
						},
						x: {
							grid: {
								display: false,
							},
						},
					},
				},
				options
			);

			return this.createChart(element, 'bar', chartData, chartOptions);
		},

		/**
		 * Create a horizontal bar chart for size comparisons
		 *
		 * @param {string|HTMLElement} canvas  - Canvas element or selector
		 * @param {Object}             data    - Chart data
		 * @param {Object}             options - Additional chart options
		 * @return {Chart} Chart instance
		 */
		createHorizontalBarChart(canvas, data, options = {}) {
			const element = this.getCanvasElement(canvas);
			if (!element) return null;

			const chartData = {
				labels: data.labels || [],
				datasets: [
					{
						label: data.label || '',
						data: data.values || [],
						backgroundColor:
							data.colors ||
							this.colors.palette.slice(0, data.values.length),
						borderWidth: 1,
						borderColor: '#fff',
					},
				],
			};

			const chartOptions = this.mergeOptions(
				{
					indexAxis: 'y',
					scales: {
						x: {
							beginAtZero: true,
							grid: {
								color: this.colors.lightGray,
							},
							ticks: {
								callback:
									options.formatValue || this.formatBytes,
							},
						},
						y: {
							grid: {
								display: false,
							},
						},
					},
					plugins: {
						tooltip: {
							callbacks: {
								label(context) {
									const label = context.dataset.label || '';
									const value = context.parsed.x || 0;
									const formatted = options.formatValue
										? options.formatValue(value)
										: window.WPAdminHealthCharts.formatBytes(
												value
											);
									return `${label}: ${formatted}`;
								},
							},
						},
					},
				},
				options
			);

			return this.createChart(element, 'bar', chartData, chartOptions);
		},

		/**
		 * Create a line chart for history over time
		 *
		 * @param {string|HTMLElement} canvas  - Canvas element or selector
		 * @param {Object}             data    - Chart data
		 * @param {Object}             options - Additional chart options
		 * @return {Chart} Chart instance
		 */
		createLineChart(canvas, data, options = {}) {
			const element = this.getCanvasElement(canvas);
			if (!element) return null;

			const datasets = (data.datasets || []).map((dataset, index) => {
				const color =
					dataset.color ||
					this.colors.palette[index % this.colors.palette.length];
				return {
					label: dataset.label || '',
					data: dataset.values || [],
					borderColor: color,
					backgroundColor: this.hexToRgba(color, 0.1),
					borderWidth: 2,
					fill: dataset.fill !== undefined ? dataset.fill : true,
					tension: 0.4,
					pointRadius: 3,
					pointHoverRadius: 5,
					pointBackgroundColor: color,
					pointBorderColor: '#fff',
					pointBorderWidth: 2,
				};
			});

			const chartData = {
				labels: data.labels || [],
				datasets,
			};

			const chartOptions = this.mergeOptions(
				{
					scales: {
						y: {
							beginAtZero: true,
							grid: {
								color: this.colors.lightGray,
							},
							ticks: {
								callback:
									options.formatValue ||
									function (value) {
										return value;
									},
							},
						},
						x: {
							grid: {
								display: false,
							},
						},
					},
					interaction: {
						mode: 'index',
						intersect: false,
					},
				},
				options
			);

			return this.createChart(element, 'line', chartData, chartOptions);
		},

		/**
		 * Create a chart instance
		 *
		 * @param {HTMLElement} element - Canvas element
		 * @param {string}      type    - Chart type
		 * @param {Object}      data    - Chart data
		 * @param {Object}      options - Chart options
		 * @return {Chart} Chart instance
		 */
		createChart(element, type, data, options) {
			if (!element) return null;

			// Destroy existing chart if present
			const canvasId =
				element.id || element.getAttribute('data-chart-id');
			if (canvasId && this.instances[canvasId]) {
				this.destroyChart(canvasId);
			}

			// Create new chart
			try {
				const chart = new Chart(element, {
					type,
					data,
					options,
				});

				// Store instance
				if (canvasId) {
					this.instances[canvasId] = chart;
				}

				return chart;
			} catch (error) {
				console.error('Error creating chart:', error);
				return null;
			}
		},

		/**
		 * Update chart data
		 *
		 * @param {string|Chart} chart   - Chart ID or instance
		 * @param {Object}       newData - New chart data
		 */
		updateChart(chart, newData) {
			const instance =
				typeof chart === 'string' ? this.instances[chart] : chart;

			if (!instance) {
				console.error('Chart instance not found');
				return;
			}

			// Update data
			if (newData.labels) {
				instance.data.labels = newData.labels;
			}

			if (newData.datasets) {
				instance.data.datasets = newData.datasets;
			} else if (newData.values) {
				// Single dataset update
				if (instance.data.datasets[0]) {
					instance.data.datasets[0].data = newData.values;
				}
			}

			// Update the chart
			instance.update('active');
		},

		/**
		 * Destroy a chart instance
		 *
		 * @param {string|Chart} chart - Chart ID or instance
		 */
		destroyChart(chart) {
			const instance =
				typeof chart === 'string' ? this.instances[chart] : chart;

			if (instance) {
				instance.destroy();

				// Remove from registry if it's a string ID
				if (typeof chart === 'string') {
					delete this.instances[chart];
				} else {
					// Find and remove by instance
					for (const id in this.instances) {
						if (this.instances[id] === instance) {
							delete this.instances[id];
							break;
						}
					}
				}
			}
		},

		/**
		 * Destroy all chart instances
		 */
		destroyAll() {
			for (const id in this.instances) {
				if (this.instances[id]) {
					this.instances[id].destroy();
				}
			}
			this.instances = {};
		},

		/**
		 * Get canvas element
		 *
		 * @param {string|HTMLElement} canvas - Canvas element or selector
		 * @return {HTMLElement|null} Canvas element
		 */
		getCanvasElement(canvas) {
			if (typeof canvas === 'string') {
				return document.querySelector(canvas);
			}
			return canvas instanceof HTMLElement ? canvas : null;
		},

		/**
		 * Merge chart options with defaults
		 *
		 * @param {Object} defaults - Default options
		 * @param {Object} custom   - Custom options
		 * @return {Object} Merged options
		 */
		mergeOptions(defaults, custom) {
			// Deep merge helper
			const merge = (target, source) => {
				for (const key in source) {
					if (
						source[key] &&
						typeof source[key] === 'object' &&
						!Array.isArray(source[key])
					) {
						target[key] = target[key] || {};
						merge(target[key], source[key]);
					} else {
						target[key] = source[key];
					}
				}
				return target;
			};

			// Start with global defaults
			const baseOptions = JSON.parse(JSON.stringify(this.defaultOptions));

			// Merge with type-specific defaults
			const merged = merge(baseOptions, defaults);

			// Merge with custom options
			return merge(merged, custom);
		},

		/**
		 * Format bytes to human-readable format
		 *
		 * @param {number} bytes    - Bytes value
		 * @param {number} decimals - Number of decimal places
		 * @return {string} Formatted string
		 */
		formatBytes(bytes, decimals = 2) {
			if (bytes === 0 || isNaN(bytes)) return '0 Bytes';

			const k = 1024;
			const dm = decimals < 0 ? 0 : decimals;
			const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
			const i = Math.floor(Math.log(Math.abs(bytes)) / Math.log(k));

			return (
				parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) +
				' ' +
				sizes[i]
			);
		},

		/**
		 * Format number with thousands separator
		 *
		 * @param {number} num      - Number to format
		 * @param {number} decimals - Number of decimal places
		 * @return {string} Formatted number
		 */
		formatNumber(num, decimals = 0) {
			if (isNaN(num)) return '0';

			return num.toLocaleString('en-US', {
				minimumFractionDigits: decimals,
				maximumFractionDigits: decimals,
			});
		},

		/**
		 * Create gradient for canvas
		 *
		 * @param {CanvasRenderingContext2D} ctx        - Canvas context
		 * @param {string}                   colorStart - Start color (hex)
		 * @param {string}                   colorEnd   - End color (hex)
		 * @param {number}                   height     - Canvas height
		 * @return {CanvasGradient} Gradient object
		 */
		createGradient(ctx, colorStart, colorEnd, height) {
			if (!ctx) return null;

			const gradient = ctx.createLinearGradient(0, 0, 0, height || 400);
			gradient.addColorStop(0, colorStart);
			gradient.addColorStop(1, colorEnd);

			return gradient;
		},

		/**
		 * Convert hex color to rgba
		 *
		 * @param {string} hex   - Hex color code
		 * @param {number} alpha - Alpha value (0-1)
		 * @return {string} RGBA color string
		 */
		hexToRgba(hex, alpha = 1) {
			// Remove # if present
			hex = hex.replace(/^#/, '');

			// Parse hex values
			const r = parseInt(hex.substring(0, 2), 16);
			const g = parseInt(hex.substring(2, 4), 16);
			const b = parseInt(hex.substring(4, 6), 16);

			return `rgba(${r}, ${g}, ${b}, ${alpha})`;
		},

		/**
		 * Get color from palette by index
		 *
		 * @param {number} index - Color index
		 * @return {string} Color hex code
		 */
		getColor(index) {
			return this.colors.palette[index % this.colors.palette.length];
		},

		/**
		 * Get multiple colors from palette
		 *
		 * @param {number} count - Number of colors needed
		 * @return {Array} Array of color hex codes
		 */
		getColors(count) {
			const colors = [];
			for (let i = 0; i < count; i++) {
				colors.push(this.getColor(i));
			}
			return colors;
		},
	};

	// Auto-initialize on DOM ready
	$(document).ready(function () {
		window.WPAdminHealthCharts.init();
	});

	// Cleanup on page unload to prevent memory leaks
	$(window).on('unload', function () {
		window.WPAdminHealthCharts.destroyAll();
	});
})(window, jQuery);
