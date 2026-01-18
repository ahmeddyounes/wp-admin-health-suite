<?php
/**
 * Database Health Template
 *
 * Provides accessibility scaffolding and React mount point for database health page.
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
	<a href="#wpha-cleanup-modules" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to cleanup modules', 'wp-admin-health-suite' ); ?></a>
</div>

<div class="wrap wpha-database-health-wrap" role="main" aria-labelledby="wpha-page-title">
	<h1 id="wpha-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Main Content Container -->
	<div id="wpha-main-content" class="wpha-database-health">
		<!-- Database Health Root (React mounts here) -->
		<div id="wpha-database-health-root" aria-live="polite"></div>

		<!-- Cleanup Modules Container -->
		<div id="wpha-cleanup-modules" aria-label="<?php esc_attr_e( 'Database cleanup modules', 'wp-admin-health-suite' ); ?>"></div>

		<!-- Table Browser Container -->
		<div id="wpha-table-browser" aria-label="<?php esc_attr_e( 'Database tables browser', 'wp-admin-health-suite' ); ?>"></div>
	</div>
</div>
