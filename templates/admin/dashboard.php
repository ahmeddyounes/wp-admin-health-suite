<?php
/**
 * Admin Health Dashboard Template
 *
 * Provides accessibility scaffolding and React mount point for the dashboard.
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
	<a href="#wpha-quick-actions" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to quick actions', 'wp-admin-health-suite' ); ?></a>
</div>

<div class="wrap wpha-dashboard-wrap" role="main" aria-labelledby="wpha-page-title">
	<h1 id="wpha-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Main Content Container -->
	<div id="wpha-main-content" class="wpha-dashboard">
		<!-- Extension Zone: Top -->
		<div id="wpha-extension-zone-top" aria-label="<?php esc_attr_e( 'Extension widgets area', 'wp-admin-health-suite' ); ?>"></div>

		<!-- Health Score Container -->
		<div id="wpha-health-score-container" aria-label="<?php esc_attr_e( 'Health score', 'wp-admin-health-suite' ); ?>"></div>

		<!-- Quick Actions Container -->
		<div id="wpha-quick-actions" aria-label="<?php esc_attr_e( 'Quick actions', 'wp-admin-health-suite' ); ?>"></div>

		<!-- Main Dashboard Root (React mounts here) -->
		<div id="wpha-dashboard-root" aria-live="polite"></div>

		<!-- Extension Zone: Bottom -->
		<div id="wpha-extension-zone-bottom" aria-label="<?php esc_attr_e( 'Additional extension widgets', 'wp-admin-health-suite' ); ?>"></div>
	</div>
</div>
