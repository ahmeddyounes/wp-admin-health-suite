/**
 * Charts JavaScript
 *
 * Chart.js wrapper for WP Admin Health Suite.
 *
 * @package WPAdminHealth
 */

(function($) {
	'use strict';

	window.WPAdminHealthCharts = {
		/**
		 * Initialize charts
		 */
		init: function() {
			console.log('Charts initialized');
		}
	};

	// Auto-initialize on DOM ready
	$(document).ready(function() {
		if (typeof wpAdminHealthData !== 'undefined') {
			window.WPAdminHealthCharts.init();
		}
	});

})(jQuery);
