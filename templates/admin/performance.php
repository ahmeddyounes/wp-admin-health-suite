<?php
/**
 * Performance Template
 *
 * Provides accessibility scaffolding and React mount point for performance page.
 * All dynamic content is rendered by React components.
 *
 * @package WPAdminHealth
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}
?>

<!-- Skip Links for Keyboard Navigation -->
<div class="wpha-skip-links">
	<a href="#wpha-main-content" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to main content', 'wp-admin-health-suite' ); ?></a>
	<a href="#wpha-recommendations" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to recommendations', 'wp-admin-health-suite' ); ?></a>
</div>

<div class="wrap wpha-performance-wrap" role="main" aria-labelledby="wpha-page-title">
	<h1 id="wpha-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Main Content Container -->
	<div id="wpha-main-content" class="wpha-performance">
		<!-- Performance Root (React mounts here) -->
		<div id="wpha-performance-root" aria-live="polite"></div>

		<!-- Recommendations Container -->
		<div id="wpha-recommendations" aria-label="<?php esc_attr_e( 'Performance recommendations', 'wp-admin-health-suite' ); ?>"></div>
	</div>
</div>
