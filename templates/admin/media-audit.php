<?php
/**
 * Media Audit Template
 *
 * @package WPAdminHealth
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}
?>

<div class="wrap wpha-media-audit-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="wpha-media-audit">
		<!-- Scan Status Banner -->
		<div class="wpha-scan-status-banner">
			<div class="wpha-scan-status-banner-skeleton"></div>
			<div class="wpha-scan-status-content">
				<div class="wpha-scan-status-info">
					<span class="dashicons dashicons-update"></span>
					<div class="wpha-scan-status-text">
						<p class="wpha-scan-status-label"><?php esc_html_e( 'Last Scan:', 'wp-admin-health-suite' ); ?></p>
						<p class="wpha-scan-status-value">--</p>
					</div>
				</div>
				<button class="button button-primary wpha-rescan-btn" disabled>
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'Rescan Media', 'wp-admin-health-suite' ); ?>
				</button>
			</div>
		</div>

		<!-- Stats Overview Cards -->
		<div class="wpha-stats-overview">
			<div class="wpha-stat-card">
				<div class="wpha-stat-card-skeleton"></div>
				<div class="wpha-stat-card-content">
					<div class="wpha-stat-icon">
						<span class="dashicons dashicons-format-image"></span>
					</div>
					<div class="wpha-stat-data">
						<h3 class="wpha-stat-title"><?php esc_html_e( 'Total Media Files', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-stat-value">--</p>
					</div>
				</div>
			</div>

			<div class="wpha-stat-card">
				<div class="wpha-stat-card-skeleton"></div>
				<div class="wpha-stat-card-content">
					<div class="wpha-stat-icon wpha-icon-warning">
						<span class="dashicons dashicons-dismiss"></span>
					</div>
					<div class="wpha-stat-data">
						<h3 class="wpha-stat-title"><?php esc_html_e( 'Unused Files', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-stat-value">--</p>
					</div>
				</div>
			</div>

			<div class="wpha-stat-card">
				<div class="wpha-stat-card-skeleton"></div>
				<div class="wpha-stat-card-content">
					<div class="wpha-stat-icon wpha-icon-info">
						<span class="dashicons dashicons-admin-page"></span>
					</div>
					<div class="wpha-stat-data">
						<h3 class="wpha-stat-title"><?php esc_html_e( 'Duplicate Files', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-stat-value">--</p>
					</div>
				</div>
			</div>

			<div class="wpha-stat-card">
				<div class="wpha-stat-card-skeleton"></div>
				<div class="wpha-stat-card-content">
					<div class="wpha-stat-icon wpha-icon-success">
						<span class="dashicons dashicons-chart-area"></span>
					</div>
					<div class="wpha-stat-data">
						<h3 class="wpha-stat-title"><?php esc_html_e( 'Potential Savings', 'wp-admin-health-suite' ); ?></h3>
						<p class="wpha-stat-value">--</p>
					</div>
				</div>
			</div>
		</div>

		<!-- Tabbed Results View -->
		<div class="wpha-results-section">
			<div class="wpha-tabs-navigation">
				<button class="wpha-tab-btn wpha-tab-active" data-tab="unused">
					<span class="dashicons dashicons-dismiss"></span>
					<?php esc_html_e( 'Unused Files', 'wp-admin-health-suite' ); ?>
					<span class="wpha-tab-badge">0</span>
				</button>
				<button class="wpha-tab-btn" data-tab="duplicates">
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'Duplicates', 'wp-admin-health-suite' ); ?>
					<span class="wpha-tab-badge">0</span>
				</button>
				<button class="wpha-tab-btn" data-tab="large-files">
					<span class="dashicons dashicons-chart-area"></span>
					<?php esc_html_e( 'Large Files', 'wp-admin-health-suite' ); ?>
					<span class="wpha-tab-badge">0</span>
				</button>
				<button class="wpha-tab-btn" data-tab="missing-alt">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Missing Alt Text', 'wp-admin-health-suite' ); ?>
					<span class="wpha-tab-badge">0</span>
				</button>
			</div>

			<div class="wpha-tabs-content">
				<!-- Unused Files Tab -->
				<div class="wpha-tab-panel wpha-tab-active" data-tab="unused">
					<div class="wpha-tab-panel-skeleton"></div>
					<div class="wpha-tab-panel-content">
						<div class="wpha-table-controls">
							<div class="wpha-bulk-actions">
								<select class="wpha-bulk-action-select" disabled>
									<option value=""><?php esc_html_e( 'Bulk Actions', 'wp-admin-health-suite' ); ?></option>
									<option value="delete"><?php esc_html_e( 'Delete Selected', 'wp-admin-health-suite' ); ?></option>
									<option value="ignore"><?php esc_html_e( 'Ignore Selected', 'wp-admin-health-suite' ); ?></option>
								</select>
								<button class="button wpha-bulk-apply-btn" disabled><?php esc_html_e( 'Apply', 'wp-admin-health-suite' ); ?></button>
							</div>
							<div class="wpha-table-filters">
								<input type="text" class="wpha-search-input" placeholder="<?php esc_attr_e( 'Search files...', 'wp-admin-health-suite' ); ?>" />
								<select class="wpha-filter-select">
									<option value=""><?php esc_html_e( 'All Types', 'wp-admin-health-suite' ); ?></option>
									<option value="image"><?php esc_html_e( 'Images', 'wp-admin-health-suite' ); ?></option>
									<option value="video"><?php esc_html_e( 'Videos', 'wp-admin-health-suite' ); ?></option>
									<option value="document"><?php esc_html_e( 'Documents', 'wp-admin-health-suite' ); ?></option>
								</select>
							</div>
						</div>
						<div class="wpha-table-container">
							<table class="wp-list-table widefat fixed striped wpha-media-table">
								<thead>
									<tr>
										<th class="wpha-col-checkbox"><input type="checkbox" class="wpha-select-all" /></th>
										<th class="wpha-col-preview"><?php esc_html_e( 'Preview', 'wp-admin-health-suite' ); ?></th>
										<th class="wpha-col-filename wpha-sortable" data-sort="filename">
											<?php esc_html_e( 'Filename', 'wp-admin-health-suite' ); ?>
											<span class="dashicons dashicons-sort"></span>
										</th>
										<th class="wpha-col-size wpha-sortable" data-sort="size">
											<?php esc_html_e( 'Size', 'wp-admin-health-suite' ); ?>
											<span class="dashicons dashicons-sort"></span>
										</th>
										<th class="wpha-col-date wpha-sortable" data-sort="date">
											<?php esc_html_e( 'Upload Date', 'wp-admin-health-suite' ); ?>
											<span class="dashicons dashicons-sort"></span>
										</th>
										<th class="wpha-col-actions"><?php esc_html_e( 'Actions', 'wp-admin-health-suite' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<!-- Content will be populated by JavaScript -->
								</tbody>
							</table>
						</div>
						<div class="wpha-table-pagination">
							<div class="wpha-pagination-info">
								<span class="wpha-showing-text"><?php esc_html_e( 'Showing 0 of 0 items', 'wp-admin-health-suite' ); ?></span>
							</div>
							<div class="wpha-pagination-controls">
								<!-- Pagination will be populated by JavaScript -->
							</div>
						</div>
					</div>
				</div>

				<!-- Duplicates Tab -->
				<div class="wpha-tab-panel" data-tab="duplicates">
					<div class="wpha-tab-panel-skeleton"></div>
					<div class="wpha-tab-panel-content">
						<div class="wpha-table-controls">
							<div class="wpha-bulk-actions">
								<select class="wpha-bulk-action-select" disabled>
									<option value=""><?php esc_html_e( 'Bulk Actions', 'wp-admin-health-suite' ); ?></option>
									<option value="delete"><?php esc_html_e( 'Delete Selected', 'wp-admin-health-suite' ); ?></option>
									<option value="ignore"><?php esc_html_e( 'Ignore Selected', 'wp-admin-health-suite' ); ?></option>
								</select>
								<button class="button wpha-bulk-apply-btn" disabled><?php esc_html_e( 'Apply', 'wp-admin-health-suite' ); ?></button>
							</div>
							<div class="wpha-table-filters">
								<input type="text" class="wpha-search-input" placeholder="<?php esc_attr_e( 'Search files...', 'wp-admin-health-suite' ); ?>" />
							</div>
						</div>
						<div class="wpha-duplicates-container">
							<!-- Duplicate groups will be populated by JavaScript -->
						</div>
						<div class="wpha-table-pagination">
							<div class="wpha-pagination-info">
								<span class="wpha-showing-text"><?php esc_html_e( 'Showing 0 of 0 groups', 'wp-admin-health-suite' ); ?></span>
							</div>
							<div class="wpha-pagination-controls">
								<!-- Pagination will be populated by JavaScript -->
							</div>
						</div>
					</div>
				</div>

				<!-- Large Files Tab -->
				<div class="wpha-tab-panel" data-tab="large-files">
					<div class="wpha-tab-panel-skeleton"></div>
					<div class="wpha-tab-panel-content">
						<div class="wpha-table-controls">
							<div class="wpha-bulk-actions">
								<select class="wpha-bulk-action-select" disabled>
									<option value=""><?php esc_html_e( 'Bulk Actions', 'wp-admin-health-suite' ); ?></option>
									<option value="delete"><?php esc_html_e( 'Delete Selected', 'wp-admin-health-suite' ); ?></option>
									<option value="ignore"><?php esc_html_e( 'Ignore Selected', 'wp-admin-health-suite' ); ?></option>
								</select>
								<button class="button wpha-bulk-apply-btn" disabled><?php esc_html_e( 'Apply', 'wp-admin-health-suite' ); ?></button>
							</div>
							<div class="wpha-table-filters">
								<input type="text" class="wpha-search-input" placeholder="<?php esc_attr_e( 'Search files...', 'wp-admin-health-suite' ); ?>" />
								<select class="wpha-size-filter-select">
									<option value=""><?php esc_html_e( 'All Sizes', 'wp-admin-health-suite' ); ?></option>
									<option value="1mb"><?php esc_html_e( '> 1 MB', 'wp-admin-health-suite' ); ?></option>
									<option value="5mb"><?php esc_html_e( '> 5 MB', 'wp-admin-health-suite' ); ?></option>
									<option value="10mb"><?php esc_html_e( '> 10 MB', 'wp-admin-health-suite' ); ?></option>
								</select>
							</div>
						</div>
						<div class="wpha-table-container">
							<table class="wp-list-table widefat fixed striped wpha-media-table">
								<thead>
									<tr>
										<th class="wpha-col-checkbox"><input type="checkbox" class="wpha-select-all" /></th>
										<th class="wpha-col-preview"><?php esc_html_e( 'Preview', 'wp-admin-health-suite' ); ?></th>
										<th class="wpha-col-filename wpha-sortable" data-sort="filename">
											<?php esc_html_e( 'Filename', 'wp-admin-health-suite' ); ?>
											<span class="dashicons dashicons-sort"></span>
										</th>
										<th class="wpha-col-size wpha-sortable wpha-sorted-desc" data-sort="size">
											<?php esc_html_e( 'Size', 'wp-admin-health-suite' ); ?>
											<span class="dashicons dashicons-arrow-down"></span>
										</th>
										<th class="wpha-col-dimensions"><?php esc_html_e( 'Dimensions', 'wp-admin-health-suite' ); ?></th>
										<th class="wpha-col-actions"><?php esc_html_e( 'Actions', 'wp-admin-health-suite' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<!-- Content will be populated by JavaScript -->
								</tbody>
							</table>
						</div>
						<div class="wpha-table-pagination">
							<div class="wpha-pagination-info">
								<span class="wpha-showing-text"><?php esc_html_e( 'Showing 0 of 0 items', 'wp-admin-health-suite' ); ?></span>
							</div>
							<div class="wpha-pagination-controls">
								<!-- Pagination will be populated by JavaScript -->
							</div>
						</div>
					</div>
				</div>

				<!-- Missing Alt Text Tab -->
				<div class="wpha-tab-panel" data-tab="missing-alt">
					<div class="wpha-tab-panel-skeleton"></div>
					<div class="wpha-tab-panel-content">
						<div class="wpha-table-controls">
							<div class="wpha-bulk-actions">
								<select class="wpha-bulk-action-select" disabled>
									<option value=""><?php esc_html_e( 'Bulk Actions', 'wp-admin-health-suite' ); ?></option>
									<option value="ignore"><?php esc_html_e( 'Ignore Selected', 'wp-admin-health-suite' ); ?></option>
								</select>
								<button class="button wpha-bulk-apply-btn" disabled><?php esc_html_e( 'Apply', 'wp-admin-health-suite' ); ?></button>
							</div>
							<div class="wpha-table-filters">
								<input type="text" class="wpha-search-input" placeholder="<?php esc_attr_e( 'Search files...', 'wp-admin-health-suite' ); ?>" />
							</div>
						</div>
						<div class="wpha-table-container">
							<table class="wp-list-table widefat fixed striped wpha-media-table">
								<thead>
									<tr>
										<th class="wpha-col-checkbox"><input type="checkbox" class="wpha-select-all" /></th>
										<th class="wpha-col-preview"><?php esc_html_e( 'Preview', 'wp-admin-health-suite' ); ?></th>
										<th class="wpha-col-filename wpha-sortable" data-sort="filename">
											<?php esc_html_e( 'Filename', 'wp-admin-health-suite' ); ?>
											<span class="dashicons dashicons-sort"></span>
										</th>
										<th class="wpha-col-date wpha-sortable" data-sort="date">
											<?php esc_html_e( 'Upload Date', 'wp-admin-health-suite' ); ?>
											<span class="dashicons dashicons-sort"></span>
										</th>
										<th class="wpha-col-actions"><?php esc_html_e( 'Actions', 'wp-admin-health-suite' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<!-- Content will be populated by JavaScript -->
								</tbody>
							</table>
						</div>
						<div class="wpha-table-pagination">
							<div class="wpha-pagination-info">
								<span class="wpha-showing-text"><?php esc_html_e( 'Showing 0 of 0 items', 'wp-admin-health-suite' ); ?></span>
							</div>
							<div class="wpha-pagination-controls">
								<!-- Pagination will be populated by JavaScript -->
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- React Mount Point (for future React integration) -->
		<div id="wpha-media-audit-root"></div>
	</div>
</div>
