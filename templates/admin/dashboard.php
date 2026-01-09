<?php
/**
 * Admin Health Dashboard Template
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

	<div id="wpha-main-content" class="wpha-dashboard">
		<!-- Header Section with Health Score -->
		<section class="wpha-dashboard-header" aria-labelledby="wpha-health-score-heading">
			<div class="wpha-health-score">
				<div class="wpha-health-score-circle" role="img" aria-label="<?php esc_attr_e( 'Loading health score', 'wp-admin-health-suite' ); ?>">
					<svg class="wpha-health-score-svg" viewBox="0 0 200 200" aria-hidden="true">
						<circle class="wpha-health-score-bg" cx="100" cy="100" r="85" />
						<circle class="wpha-health-score-fill" cx="100" cy="100" r="85" />
					</svg>
					<div class="wpha-health-score-content">
						<div class="wpha-health-score-skeleton" aria-hidden="true"></div>
						<span class="wpha-health-score-value" aria-live="polite" aria-atomic="true">--</span>
						<span id="wpha-health-score-heading" class="wpha-health-score-label"><?php esc_html_e( 'Health Score', 'wp-admin-health-suite' ); ?></span>
					</div>
				</div>
			</div>
		</section>

		<!-- Metrics Cards Row -->
		<section class="wpha-metrics-cards" aria-label="<?php esc_attr_e( 'Health Metrics', 'wp-admin-health-suite' ); ?>">
			<article class="wpha-metric-card" aria-labelledby="wpha-metric-database">
				<div class="wpha-metric-card-skeleton" aria-hidden="true"></div>
				<div class="wpha-metric-card-content">
					<div class="wpha-metric-icon" aria-hidden="true">
						<span class="dashicons dashicons-database"></span>
					</div>
					<div class="wpha-metric-data">
						<h3 id="wpha-metric-database" class="wpha-metric-title"><?php esc_html_e( 'Database Size', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-metric-value" aria-live="polite">--</p>
						<span class="wpha-metric-label" aria-live="polite">--</span>
					</div>
				</div>
			</article>

			<article class="wpha-metric-card" aria-labelledby="wpha-metric-media">
				<div class="wpha-metric-card-skeleton" aria-hidden="true"></div>
				<div class="wpha-metric-card-content">
					<div class="wpha-metric-icon" aria-hidden="true">
						<span class="dashicons dashicons-format-image"></span>
					</div>
					<div class="wpha-metric-data">
						<h3 id="wpha-metric-media" class="wpha-metric-title"><?php esc_html_e( 'Media Files', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-metric-value" aria-live="polite">--</p>
						<span class="wpha-metric-label" aria-live="polite">--</span>
					</div>
				</div>
			</article>

			<article class="wpha-metric-card" aria-labelledby="wpha-metric-plugins">
				<div class="wpha-metric-card-skeleton" aria-hidden="true"></div>
				<div class="wpha-metric-card-content">
					<div class="wpha-metric-icon" aria-hidden="true">
						<span class="dashicons dashicons-admin-plugins"></span>
					</div>
					<div class="wpha-metric-data">
						<h3 id="wpha-metric-plugins" class="wpha-metric-title"><?php esc_html_e( 'Active Plugins', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-metric-value" aria-live="polite">--</p>
						<span class="wpha-metric-label" aria-live="polite">--</span>
					</div>
				</div>
			</article>

			<article class="wpha-metric-card" aria-labelledby="wpha-metric-cleanup">
				<div class="wpha-metric-card-skeleton" aria-hidden="true"></div>
				<div class="wpha-metric-card-content">
					<div class="wpha-metric-icon" aria-hidden="true">
						<span class="dashicons dashicons-update"></span>
					</div>
					<div class="wpha-metric-data">
						<h3 id="wpha-metric-cleanup" class="wpha-metric-title"><?php esc_html_e( 'Last Cleanup', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-metric-value" aria-live="polite">--</p>
						<span class="wpha-metric-label" aria-live="polite">--</span>
					</div>
				</div>
			</article>
		</section>

		<!-- Main Content Grid -->
		<div class="wpha-dashboard-grid">
			<!-- Recent Activity Section -->
			<section class="wpha-dashboard-section wpha-recent-activity" aria-labelledby="wpha-activity-heading">
				<div class="wpha-section-header">
					<h2 id="wpha-activity-heading"><?php esc_html_e( 'Recent Activity', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content" aria-live="polite" aria-busy="true">
					<div class="wpha-section-skeleton" aria-hidden="true"></div>
					<div class="wpha-activity-timeline">
						<!-- Timeline items will be populated by React -->
					</div>
				</div>
			</section>

			<!-- Quick Actions Section -->
			<section id="wpha-quick-actions" class="wpha-dashboard-section wpha-quick-actions" aria-labelledby="wpha-actions-heading">
				<div class="wpha-section-header">
					<h2 id="wpha-actions-heading"><?php esc_html_e( 'Quick Actions', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content">
					<div class="wpha-section-skeleton" aria-hidden="true"></div>
					<div class="wpha-actions-grid">
						<!-- Action buttons will be populated by React -->
					</div>
				</div>
			</section>
		</div>

		<!-- React Mount Point -->
		<div id="wpha-dashboard-root"></div>
	</div>
</div>
