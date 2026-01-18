<?php
/**
 * Network Dashboard Template
 *
 * Provides accessibility scaffolding and React mount point for network dashboard.
 * All dynamic content is rendered by React components.
 *
 * @package WPAdminHealth
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Verify multisite support is available.
$plugin    = \WPAdminHealth\Plugin::get_instance();
$multisite = $plugin->get_multisite();

if ( ! $multisite ) {
	wp_die( esc_html__( 'Multisite support not available.', 'wp-admin-health-suite' ) );
}
?>

<!-- Skip Links for Keyboard Navigation -->
<div class="wpha-skip-links">
	<a href="#wpha-main-content" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to main content', 'wp-admin-health-suite' ); ?></a>
	<a href="#wpha-sites-list" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to sites list', 'wp-admin-health-suite' ); ?></a>
</div>

<div class="wrap wpha-network-dashboard-wrap" role="main" aria-labelledby="wpha-page-title">
	<h1 id="wpha-page-title"><?php esc_html_e( 'Admin Health Network Dashboard', 'wp-admin-health-suite' ); ?></h1>

	<!-- Main Content Container -->
	<div id="wpha-main-content" class="wpha-network-dashboard">
		<!-- Network Dashboard Root (React mounts here) -->
		<div id="wpha-network-dashboard-root" aria-live="polite"></div>

		<!-- Sites List Container -->
		<div id="wpha-sites-list" aria-label="<?php esc_attr_e( 'Network sites', 'wp-admin-health-suite' ); ?>"></div>
	</div>
</div>
