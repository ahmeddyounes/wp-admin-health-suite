<?php
/**
 * Settings Template
 *
 * Server-rendered settings form with accessibility scaffolding.
 * Uses WordPress Settings API for form handling and nonce protection.
 *
 * @package WPAdminHealth
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Use injected SettingsViewModel if available (via PageRenderer), otherwise fall back
// to the Settings facade for backward compatibility. The SettingsViewModel is preferred
// as it eliminates service location in templates.
if ( ! isset( $settings_obj ) || ! $settings_obj instanceof \WPAdminHealth\Admin\SettingsViewModel ) {
	// Legacy fallback: instantiate Settings facade directly (edge adapter pattern).
	// This path is deprecated and will be removed in a future version.
	$settings_obj = new \WPAdminHealth\Settings();
}
$sections = $settings_obj->get_sections();
$settings = $settings_obj->get_settings();

// Get current tab.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only check, no data modification.
$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

// Validate tab exists.
if ( ! isset( $sections[ $current_tab ] ) ) {
	$current_tab = 'general';
}
?>

<!-- Skip Links for Keyboard Navigation -->
<div class="wpha-skip-links">
	<a href="#wpha-main-content" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to main content', 'wp-admin-health-suite' ); ?></a>
	<a href="#wpha-tab-content" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to settings form', 'wp-admin-health-suite' ); ?></a>
</div>

<div class="wrap wpha-settings-wrap" role="main" aria-labelledby="wpha-page-title">
	<h1 id="wpha-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php
	// Display success messages.
	if ( isset( $_GET['message'] ) ) {
		$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		switch ( $message ) {
			case 'imported':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings imported successfully.', 'wp-admin-health-suite' ) . '</p></div>';
				break;
			case 'reset':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings reset to defaults.', 'wp-admin-health-suite' ) . '</p></div>';
				break;
		}
	}

	settings_errors();
	?>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
		<?php
		foreach ( $sections as $section_id => $section ) {
			$tab_url = add_query_arg(
				array(
					'page' => 'admin-health-settings',
					'tab'  => $section_id,
				),
				admin_url( 'admin.php' )
			);

			printf(
				'<a href="%s" class="nav-tab %s">%s</a>',
				esc_url( $tab_url ),
				( $current_tab === $section_id ) ? 'nav-tab-active' : '',
				esc_html( $section['title'] )
			);
		}
		?>
	</nav>

	<!-- Main Content Container -->
	<div id="wpha-main-content" class="wpha-settings">
	<!-- Tab Content -->
	<div id="wpha-tab-content" class="wpha-tab-content">
		<?php if ( 'advanced' === $current_tab ) : ?>
			<!-- Advanced Tab: Import/Export and Reset -->
			<form method="post" action="options.php" class="wpha-settings-form">
				<?php
				settings_fields( 'wpha_settings_group' );
				?>
				<table class="form-table" role="presentation">
					<?php
					// Output fields for advanced section.
					$fields = $settings_obj->get_fields();
					foreach ( $fields as $field_id => $field ) {
						if ( $field['section'] === 'advanced' ) {
							?>
							<tr>
								<th scope="row">
									<label for="wpha_<?php echo esc_attr( $field_id ); ?>">
										<?php echo esc_html( $field['title'] ); ?>
									</label>
								</th>
								<td>
									<?php
									$settings_obj->render_field(
										array(
											'id'    => $field_id,
											'field' => $field,
										)
									);
									?>
								</td>
							</tr>
							<?php
						}
					}
					?>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>

			<!-- Import/Export Section -->
			<div class="wpha-settings-tools">
				<h2><?php esc_html_e( 'Import/Export Settings', 'wp-admin-health-suite' ); ?></h2>

				<div class="wpha-tools-grid">
					<div class="wpha-tool-card">
						<h3><?php esc_html_e( 'Export Settings', 'wp-admin-health-suite' ); ?></h3>
						<p><?php esc_html_e( 'Export your current settings as a JSON file for backup or migration.', 'wp-admin-health-suite' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'wpha_export_settings' ); ?>
							<input type="hidden" name="action" value="wpha_export_settings">
							<?php submit_button( __( 'Export Settings', 'wp-admin-health-suite' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>

					<div class="wpha-tool-card">
						<h3><?php esc_html_e( 'Import Settings', 'wp-admin-health-suite' ); ?></h3>
						<p><?php esc_html_e( 'Import settings from a previously exported JSON file.', 'wp-admin-health-suite' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<?php wp_nonce_field( 'wpha_import_settings' ); ?>
							<input type="hidden" name="action" value="wpha_import_settings">
							<input type="file" name="import_file" accept=".json" required>
							<?php submit_button( __( 'Import Settings', 'wp-admin-health-suite' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>

					<div class="wpha-tool-card wpha-tool-card-danger">
						<h3><?php esc_html_e( 'Reset Settings', 'wp-admin-health-suite' ); ?></h3>
						<p><?php esc_html_e( 'Reset all settings to their default values. This action cannot be undone.', 'wp-admin-health-suite' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset all settings to defaults? This action cannot be undone.', 'wp-admin-health-suite' ) ); ?>');">
							<?php wp_nonce_field( 'wpha_reset_settings' ); ?>
							<input type="hidden" name="action" value="wpha_reset_settings">
							<?php submit_button( __( 'Reset All Settings', 'wp-admin-health-suite' ), 'delete', 'submit', false ); ?>
						</form>
					</div>
				</div>
			</div>

		<?php elseif ( 'scheduling' === $current_tab ) : ?>
			<!-- Scheduling Tab: Settings + Preview -->
			<form method="post" action="options.php" class="wpha-settings-form">
				<?php
				settings_fields( 'wpha_settings_group' );
				?>
				<table class="form-table" role="presentation">
					<?php
					// Output fields for scheduling section.
					$fields = $settings_obj->get_fields();
					foreach ( $fields as $field_id => $field ) {
						if ( $field['section'] === 'scheduling' ) {
							?>
							<tr>
								<th scope="row">
									<label for="wpha_<?php echo esc_attr( $field_id ); ?>">
										<?php echo esc_html( $field['title'] ); ?>
									</label>
								</th>
								<td>
									<?php
									$settings_obj->render_field(
										array(
											'id'    => $field_id,
											'field' => $field,
										)
									);
									?>
								</td>
							</tr>
							<?php
						}
					}
					?>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>

			<!-- Scheduled Tasks Preview -->
			<div class="wpha-scheduled-tasks-preview">
				<h2><?php esc_html_e( 'Scheduled Tasks Preview', 'wp-admin-health-suite' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Below are the currently scheduled tasks and their next run times.', 'wp-admin-health-suite' ); ?>
				</p>

				<?php
				// Get scheduled task information.
				$scheduled_tasks = array(
					'wpha_database_cleanup'   => __( 'Database Cleanup', 'wp-admin-health-suite' ),
					'wpha_media_scan'         => __( 'Media Scan', 'wp-admin-health-suite' ),
					'wpha_performance_check'  => __( 'Performance Check', 'wp-admin-health-suite' ),
				);

				$scheduler_enabled = ! empty( $settings['scheduler_enabled'] );
				?>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Task Name', 'wp-admin-health-suite' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Frequency', 'wp-admin-health-suite' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Next Run', 'wp-admin-health-suite' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'wp-admin-health-suite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $scheduled_tasks as $hook => $task_name ) : ?>
							<?php
							// Determine frequency based on hook.
							$frequency_key = '';
							if ( 'wpha_database_cleanup' === $hook ) {
								$frequency_key = 'database_cleanup_frequency';
							} elseif ( 'wpha_media_scan' === $hook ) {
								$frequency_key = 'media_scan_frequency';
							} elseif ( 'wpha_performance_check' === $hook ) {
								$frequency_key = 'performance_check_frequency';
							}

							$frequency = isset( $settings[ $frequency_key ] ) ? $settings[ $frequency_key ] : 'disabled';
							$is_disabled = ! $scheduler_enabled || 'disabled' === $frequency;

							// Get next run time.
							$next_run = null;
							if ( function_exists( 'as_next_scheduled_action' ) ) {
								$next_run = as_next_scheduled_action( $hook, array(), 'wpha_scheduling' );
							} else {
								$next_run = wp_next_scheduled( $hook );
							}

							$next_run_display = $is_disabled
								? __( 'Disabled', 'wp-admin-health-suite' )
								: ( $next_run
									? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run )
									: __( 'Not Scheduled', 'wp-admin-health-suite' ) );

							$status = $is_disabled
								? '<span class="wpha-status-badge wpha-status-disabled">' . esc_html__( 'Disabled', 'wp-admin-health-suite' ) . '</span>'
								: ( $next_run
									? '<span class="wpha-status-badge wpha-status-scheduled">' . esc_html__( 'Scheduled', 'wp-admin-health-suite' ) . '</span>'
									: '<span class="wpha-status-badge wpha-status-not-scheduled">' . esc_html__( 'Not Scheduled', 'wp-admin-health-suite' ) . '</span>' );
							?>
							<tr>
								<td><strong><?php echo esc_html( $task_name ); ?></strong></td>
								<td><?php echo esc_html( ucfirst( $frequency ) ); ?></td>
								<td><?php echo esc_html( $next_run_display ); ?></td>
								<td><?php echo wp_kses_post( $status ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( ! $scheduler_enabled ) : ?>
					<div class="notice notice-warning inline">
						<p>
							<?php esc_html_e( 'Scheduler is currently disabled. Enable it above to start scheduling tasks.', 'wp-admin-health-suite' ); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>

		<?php else : ?>
			<!-- Standard Settings Tab -->
			<form method="post" action="options.php" class="wpha-settings-form">
				<?php
				settings_fields( 'wpha_settings_group' );
				?>
				<table class="form-table" role="presentation">
					<?php
					// Output fields for current section.
					$fields = $settings_obj->get_fields();
					foreach ( $fields as $field_id => $field ) {
						if ( $field['section'] === $current_tab ) {
							?>
							<tr>
								<th scope="row">
									<label for="wpha_<?php echo esc_attr( $field_id ); ?>">
										<?php echo esc_html( $field['title'] ); ?>
									</label>
								</th>
								<td>
									<?php
									$settings_obj->render_field(
										array(
											'id'    => $field_id,
											'field' => $field,
										)
									);
									?>
								</td>
							</tr>
							<?php
						}
					}
					?>
				</table>
				<?php submit_button(); ?>
			</form>

			<!-- Reset Section Button -->
			<div class="wpha-section-reset">
				<h3><?php esc_html_e( 'Reset This Section', 'wp-admin-health-suite' ); ?></h3>
				<p><?php esc_html_e( 'Reset only the settings in this section to their default values.', 'wp-admin-health-suite' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset these settings to defaults?', 'wp-admin-health-suite' ) ); ?>');">
					<?php wp_nonce_field( 'wpha_reset_section' ); ?>
					<input type="hidden" name="action" value="wpha_reset_section">
					<input type="hidden" name="section" value="<?php echo esc_attr( $current_tab ); ?>">
					<input type="hidden" name="redirect" value="<?php echo esc_url( add_query_arg( array( 'page' => 'admin-health-settings', 'tab' => $current_tab ), admin_url( 'admin.php' ) ) ); ?>">
					<?php submit_button( __( 'Reset Section Settings', 'wp-admin-health-suite' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

		<?php endif; ?>
	</div>
	</div>
</div>

<style>
.wpha-settings-wrap .nav-tab-wrapper {
	margin-bottom: 20px;
}

.wpha-tab-content {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-top: none;
	padding: 20px;
}

.wpha-settings-form {
	margin-bottom: 20px;
}

.wpha-settings-tools {
	margin-top: 30px;
}

.wpha-tools-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.wpha-tool-card {
	border: 1px solid #ccd0d4;
	padding: 20px;
	background: #f9f9f9;
	border-radius: 4px;
}

.wpha-tool-card h3 {
	margin-top: 0;
	font-size: 16px;
}

.wpha-tool-card p {
	color: #646970;
	font-size: 13px;
	line-height: 1.5;
}

.wpha-tool-card form {
	margin-top: 15px;
}

.wpha-tool-card input[type="file"] {
	margin-bottom: 10px;
	display: block;
	width: 100%;
}

.wpha-tool-card-danger {
	border-color: #d63638;
}

.wpha-tool-card-danger h3 {
	color: #d63638;
}

.wpha-scheduled-tasks-preview {
	margin-top: 30px;
}

.wpha-scheduled-tasks-preview h2 {
	margin-bottom: 10px;
}

.wpha-scheduled-tasks-preview .description {
	margin-bottom: 15px;
}

.wpha-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
}

.wpha-status-scheduled {
	background: #d4edda;
	color: #155724;
}

.wpha-status-disabled {
	background: #e2e3e5;
	color: #383d41;
}

.wpha-status-not-scheduled {
	background: #fff3cd;
	color: #856404;
}

.wpha-section-reset {
	margin-top: 40px;
	padding-top: 20px;
	border-top: 1px solid #ccd0d4;
}

.wpha-section-reset h3 {
	font-size: 16px;
}

.wpha-section-reset p {
	color: #646970;
	font-size: 13px;
}
</style>
