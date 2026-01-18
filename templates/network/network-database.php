<?php
/**
 * Network Database Health Template
 *
 * Provides accessibility scaffolding and React mount point for network database health.
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
	<a href="#wpha-site-databases" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to site databases', 'wp-admin-health-suite' ); ?></a>
</div>

<div class="wrap wpha-network-database-wrap" role="main" aria-labelledby="wpha-page-title">
	<h1 id="wpha-page-title"><?php esc_html_e( 'Network Database Health', 'wp-admin-health-suite' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Monitor and manage database health across all sites in the network.', 'wp-admin-health-suite' ); ?>
	</p>

	<!-- Main Content Container -->
	<div id="wpha-main-content" class="wpha-network-database">
		<!-- Network Database Root (React mounts here) -->
		<div id="wpha-network-database-root" aria-live="polite"></div>

		<!-- Site Databases Container -->
		<div id="wpha-site-databases" aria-label="<?php esc_attr_e( 'Site database status', 'wp-admin-health-suite' ); ?>"></div>
	</div>
</div>
