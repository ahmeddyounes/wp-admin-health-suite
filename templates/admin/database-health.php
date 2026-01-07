<?php
/**
 * Database Health Template
 *
 * @package WPAdminHealth
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}
?>

<div class="wrap wpha-database-health-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="wpha-database-health">
		<!-- Overview Cards -->
		<div class="wpha-overview-cards">
			<div class="wpha-overview-card">
				<div class="wpha-overview-card-skeleton"></div>
				<div class="wpha-overview-card-content">
					<div class="wpha-overview-icon">
						<span class="dashicons dashicons-database"></span>
					</div>
					<div class="wpha-overview-data">
						<h3 class="wpha-overview-title"><?php esc_html_e( 'Total Database Size', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-overview-value">--</p>
					</div>
				</div>
			</div>

			<div class="wpha-overview-card">
				<div class="wpha-overview-card-skeleton"></div>
				<div class="wpha-overview-card-content">
					<div class="wpha-overview-icon">
						<span class="dashicons dashicons-admin-generic"></span>
					</div>
					<div class="wpha-overview-data">
						<h3 class="wpha-overview-title"><?php esc_html_e( 'Tables Count', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-overview-value">--</p>
					</div>
				</div>
			</div>

			<div class="wpha-overview-card">
				<div class="wpha-overview-card-skeleton"></div>
				<div class="wpha-overview-card-content">
					<div class="wpha-overview-icon">
						<span class="dashicons dashicons-chart-area"></span>
					</div>
					<div class="wpha-overview-data">
						<h3 class="wpha-overview-title"><?php esc_html_e( 'Potential Savings', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-overview-value">--</p>
					</div>
				</div>
			</div>
		</div>

		<!-- Cleanup Modules Accordion -->
		<div class="wpha-cleanup-modules">
			<div class="wpha-section-header">
				<h2><?php esc_html_e( 'Database Cleanup', 'wp-admin-health-suite' ); ?></h2>
			</div>
			<div class="wpha-cleanup-accordion">
				<!-- Accordion items will be populated by JavaScript -->
				<div class="wpha-cleanup-skeleton"></div>
			</div>
		</div>

		<!-- Table Browser -->
		<div class="wpha-table-browser">
			<div class="wpha-section-header">
				<h2><?php esc_html_e( 'Database Tables', 'wp-admin-health-suite' ); ?></h2>
			</div>
			<div class="wpha-table-list">
				<!-- Table list will be populated by JavaScript -->
				<div class="wpha-table-skeleton"></div>
			</div>
		</div>

		<!-- React Mount Point -->
		<div id="wpha-database-health-root"></div>
	</div>
</div>
