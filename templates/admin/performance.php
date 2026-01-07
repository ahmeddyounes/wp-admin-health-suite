<?php
/**
 * Performance Template
 *
 * @package WPAdminHealth
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}
?>

<div class="wrap wpha-performance-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="wpha-performance">
		<!-- Performance Score Card -->
		<div class="wpha-performance-header">
			<div class="wpha-performance-score">
				<div class="wpha-performance-score-circle">
					<svg class="wpha-performance-score-svg" viewBox="0 0 200 200">
						<circle class="wpha-performance-score-bg" cx="100" cy="100" r="85" />
						<circle class="wpha-performance-score-fill" cx="100" cy="100" r="85" />
					</svg>
					<div class="wpha-performance-score-content">
						<div class="wpha-performance-score-skeleton"></div>
						<span class="wpha-performance-score-value">--</span>
						<span class="wpha-performance-score-label"><?php esc_html_e( 'Performance Score', 'wp-admin-health-suite' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Main Content Grid -->
		<div class="wpha-performance-grid">
			<!-- Plugin Impact Section -->
			<div class="wpha-performance-section wpha-plugin-impact">
				<div class="wpha-section-header">
					<h2><?php esc_html_e( 'Plugin Impact', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content">
					<div class="wpha-section-skeleton"></div>
					<div class="wpha-plugin-impact-table">
						<!-- Plugin impact table will be populated by React -->
					</div>
				</div>
			</div>

			<!-- Query Analysis Section -->
			<div class="wpha-performance-section wpha-query-analysis">
				<div class="wpha-section-header">
					<h2><?php esc_html_e( 'Query Analysis', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content">
					<div class="wpha-section-skeleton"></div>
					<div class="wpha-query-analysis-data">
						<!-- Query analysis will be populated by React -->
					</div>
				</div>
			</div>

			<!-- Heartbeat Control Section -->
			<div class="wpha-performance-section wpha-heartbeat-control">
				<div class="wpha-section-header">
					<h2><?php esc_html_e( 'Heartbeat Control', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content">
					<div class="wpha-section-skeleton"></div>
					<div class="wpha-heartbeat-controls">
						<!-- Heartbeat controls will be populated by React -->
					</div>
				</div>
			</div>

			<!-- Object Cache Status Section -->
			<div class="wpha-performance-section wpha-cache-status">
				<div class="wpha-section-header">
					<h2><?php esc_html_e( 'Object Cache Status', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content">
					<div class="wpha-section-skeleton"></div>
					<div class="wpha-cache-status-data">
						<!-- Cache status will be populated by React -->
					</div>
				</div>
			</div>

			<!-- Autoload Analysis Section -->
			<div class="wpha-performance-section wpha-autoload-analysis">
				<div class="wpha-section-header">
					<h2><?php esc_html_e( 'Autoload Analysis', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content">
					<div class="wpha-section-skeleton"></div>
					<div class="wpha-autoload-data">
						<!-- Autoload analysis will be populated by React -->
					</div>
				</div>
			</div>

			<!-- Recommendations Sidebar -->
			<div class="wpha-performance-section wpha-recommendations">
				<div class="wpha-section-header">
					<h2><?php esc_html_e( 'Recommendations', 'wp-admin-health-suite' ); ?></h2>
				</div>
				<div class="wpha-section-content">
					<div class="wpha-section-skeleton"></div>
					<div class="wpha-recommendations-list">
						<!-- Recommendations will be populated by React -->
					</div>
				</div>
			</div>
		</div>

		<!-- React Mount Point -->
		<div id="wpha-performance-root"></div>
	</div>
</div>
