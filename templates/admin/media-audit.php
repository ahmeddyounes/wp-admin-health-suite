<?php
/**
 * Media Audit Template
 *
 * Provides accessibility scaffolding and React mount point for media audit page.
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
	<a href="#wpha-media-results" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to scan results', 'wp-admin-health-suite' ); ?></a>
</div>

<div class="wrap wpha-media-audit-wrap" role="main" aria-labelledby="wpha-page-title">
	<h1 id="wpha-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Main Content Container -->
	<div id="wpha-main-content" class="wpha-media-audit">
		<!-- Media Audit Root (React mounts here) -->
		<div id="wpha-media-audit-root" aria-live="polite"></div>

		<!-- Results Container -->
		<div id="wpha-media-results" aria-label="<?php esc_attr_e( 'Media scan results', 'wp-admin-health-suite' ); ?>"></div>
	</div>
</div>
