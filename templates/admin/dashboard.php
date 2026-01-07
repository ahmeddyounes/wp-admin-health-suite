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

<div class="wrap wpha-dashboard-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="wpha-dashboard">
		<!-- Header Section with Health Score -->
		<div class="wpha-dashboard-header">
			<div class="wpha-health-score">
				<div class="wpha-health-score-circle">
					<svg class="wpha-health-score-svg" viewBox="0 0 200 200">
						<circle class="wpha-health-score-bg" cx="100" cy="100" r="85" />
						<circle class="wpha-health-score-fill" cx="100" cy="100" r="85" />
					</svg>
					<div class="wpha-health-score-content">
						<div class="wpha-health-score-skeleton"></div>
						<span class="wpha-health-score-value">--</span>
						<span class="wpha-health-score-label"><?php esc_html_e( 'Health Score', 'wp-admin-health-suite' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Metrics Cards Row -->
		<div class="wpha-metrics-cards">
			<div class="wpha-metric-card">
				<div class="wpha-metric-card-skeleton"></div>
				<div class="wpha-metric-card-content">
					<div class="wpha-metric-icon">
						<span class="dashicons dashicons-database"></span>
					</div>
					<div class="wpha-metric-data">
						<h3 class="wpha-metric-title"><?php esc_html_e( 'Database Size', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-metric-value">--</p>
						<span class="wpha-metric-label">--</span>
					</div>
				</div>
			</div>

			<div class="wpha-metric-card">
				<div class="wpha-metric-card-skeleton"></div>
				<div class="wpha-metric-card-content">
					<div class="wpha-metric-icon">
						<span class="dashicons dashicons-format-image"></span>
					</div>
					<div class="wpha-metric-data">
						<h3 class="wpha-metric-title"><?php esc_html_e( 'Media Files', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-metric-value">--</p>
						<span class="wpha-metric-label">--</span>
					</div>
				</div>
			</div>

			<div class="wpha-metric-card">
				<div class="wpha-metric-card-skeleton"></div>
				<div class="wpha-metric-card-content">
					<div class="wpha-metric-icon">
						<span class="dashicons dashicons-admin-plugins"></span>
					</div>
					<div class="wpha-metric-data">
						<h3 class="wpha-metric-title"><?php esc_html_e( 'Active Plugins', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-metric-value">--</p>
						<span class="wpha-metric-label">--</span>
					</div>
				</div>
			</div>

			<div class="wpha-metric-card">
				<div class="wpha-metric-card-skeleton"></div>
				<div class="wpha-metric-card-content">
					<div class="wpha-metric-icon">
						<span class="dashicons dashicons-update"></span>
					</div>
					<div class="wpha-metric-data">
						<h3 class="wpha-metric-title"><?php esc_html_e( 'Last Cleanup', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-metric-value">--</p>
						<span class="wpha-metric-label">--</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Main Content Grid -->
		<div class="wpha-dashboard-grid">
			<!-- Recent Activity Section -->
			<div class="wpha-dashboard-section wpha-recent-activity">
				<div class="wpha-section-header">
					<h2><?php esc_html_e( 'Recent Activity', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content">
					<div class="wpha-section-skeleton"></div>
					<div class="wpha-activity-timeline">
						<!-- Timeline items will be populated by React -->
					</div>
				</div>
			</div>

			<!-- Quick Actions Section -->
			<div class="wpha-dashboard-section wpha-quick-actions">
				<div class="wpha-section-header">
					<h2><?php esc_html_e( 'Quick Actions', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content">
					<div class="wpha-section-skeleton"></div>
					<div class="wpha-actions-grid">
						<!-- Action buttons will be populated by React -->
					</div>
				</div>
			</div>
		</div>

		<!-- React Mount Point -->
		<div id="wpha-dashboard-root"></div>
	</div>
</div>
