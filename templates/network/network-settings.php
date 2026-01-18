<?php
/**
 * Network Settings Template
 *
 * Server-rendered settings form with accessibility scaffolding.
 * Uses WordPress nonce protection for form submissions.
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

$settings = $multisite->get_network_settings();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only check, no data modification.
$updated = isset( $_GET['updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['updated'] ) );
?>

<!-- Skip Links for Keyboard Navigation -->
<div class="wpha-skip-links">
	<a href="#wpha-main-content" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to main content', 'wp-admin-health-suite' ); ?></a>
	<a href="#wpha-settings-form" class="screen-reader-shortcut"><?php esc_html_e( 'Skip to settings form', 'wp-admin-health-suite' ); ?></a>
</div>

<div class="wrap wpha-network-settings-wrap" role="main" aria-labelledby="wpha-page-title">
	<h1 id="wpha-page-title"><?php esc_html_e( 'Admin Health Network Settings', 'wp-admin-health-suite' ); ?></h1>

	<?php if ( $updated ) : ?>
		<div class="notice notice-success is-dismissible" role="alert">
			<p><?php esc_html_e( 'Network settings updated successfully.', 'wp-admin-health-suite' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Main Content Container -->
	<div id="wpha-main-content" class="wpha-network-settings">
		<form id="wpha-settings-form" method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=wpha_update_network_settings' ) ); ?>" aria-label="<?php esc_attr_e( 'Network settings form', 'wp-admin-health-suite' ); ?>">
			<?php wp_nonce_field( 'wpha_network_settings_update' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="enable_network_wide">
								<?php esc_html_e( 'Enable Network-Wide Mode', 'wp-admin-health-suite' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="enable_network_wide" name="wpha_network_settings[enable_network_wide]" value="1" <?php checked( $settings['enable_network_wide'] ); ?> />
							<p class="description">
								<?php esc_html_e( 'Enable network-wide plugin functionality. When enabled, settings and scans can be managed centrally.', 'wp-admin-health-suite' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="network_scan_mode">
								<?php esc_html_e( 'Default Scan Mode', 'wp-admin-health-suite' ); ?>
							</label>
						</th>
						<td>
							<select id="network_scan_mode" name="wpha_network_settings[network_scan_mode]">
								<option value="current_site" <?php selected( $settings['network_scan_mode'], 'current_site' ); ?>>
									<?php esc_html_e( 'Current Site Only', 'wp-admin-health-suite' ); ?>
								</option>
								<option value="network_wide" <?php selected( $settings['network_scan_mode'], 'network_wide' ); ?>>
									<?php esc_html_e( 'Network-Wide', 'wp-admin-health-suite' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Choose the default scope for database and media scans.', 'wp-admin-health-suite' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="shared_scan_results">
								<?php esc_html_e( 'Shared Scan Results', 'wp-admin-health-suite' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="shared_scan_results" name="wpha_network_settings[shared_scan_results]" value="1" <?php checked( $settings['shared_scan_results'] ); ?> />
							<p class="description">
								<?php esc_html_e( 'Store scan results in a shared location accessible to all sites in the network.', 'wp-admin-health-suite' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="allow_site_override">
								<?php esc_html_e( 'Allow Site Override', 'wp-admin-health-suite' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="allow_site_override" name="wpha_network_settings[allow_site_override]" value="1" <?php checked( $settings['allow_site_override'] ); ?> />
							<p class="description">
								<?php esc_html_e( 'Allow individual sites to override network settings with their own configurations.', 'wp-admin-health-suite' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="network_admin_only_scans">
								<?php esc_html_e( 'Network Admin Only Scans', 'wp-admin-health-suite' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="network_admin_only_scans" name="wpha_network_settings[network_admin_only_scans]" value="1" <?php checked( $settings['network_admin_only_scans'] ); ?> />
							<p class="description">
								<?php esc_html_e( 'Only super admins can run network-wide scans. When disabled, site administrators can also run scans.', 'wp-admin-health-suite' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Network Settings', 'wp-admin-health-suite' ) ); ?>
		</form>
	</div>
</div>
