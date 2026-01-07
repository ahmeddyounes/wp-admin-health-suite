/**
 * Admin JavaScript
 *
 * Core admin JavaScript for WP Admin Health Suite.
 *
 * @package WPAdminHealth
 */

(function($) {
	'use strict';

	// Wait for DOM ready
	$(document).ready(function() {
		// Access localized data
		if (typeof wpAdminHealthData !== 'undefined') {
			console.log('WP Admin Health Suite initialized');
		}
	});

})(jQuery);
