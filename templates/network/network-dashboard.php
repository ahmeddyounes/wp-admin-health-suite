<?php
/**
 * Network Dashboard Template
 *
 * @package WPAdminHealth
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

$plugin    = \WPAdminHealth\Plugin::get_instance();
$multisite = $plugin->get_multisite();

if ( ! $multisite ) {
	wp_die( esc_html__( 'Multisite support not available.', 'wp-admin-health-suite' ) );
}

$sites    = $multisite->get_network_sites();
$settings = $multisite->get_network_settings();
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Admin Health Network Dashboard', 'wp-admin-health-suite' ); ?></h1>

	<div class="wpha-network-overview">
		<h2><?php esc_html_e( 'Network Overview', 'wp-admin-health-suite' ); ?></h2>

		<div class="wpha-stats-grid">
			<div class="wpha-stat-card">
				<h3><?php esc_html_e( 'Total Sites', 'wp-admin-health-suite' ); ?></h3>
				<p class="wpha-stat-value"><?php echo esc_html( count( $sites ) ); ?></p>
			</div>

			<div class="wpha-stat-card">
				<h3><?php esc_html_e( 'Network Mode', 'wp-admin-health-suite' ); ?></h3>
				<p class="wpha-stat-value">
					<?php echo $settings['enable_network_wide'] ? esc_html__( 'Enabled', 'wp-admin-health-suite' ) : esc_html__( 'Disabled', 'wp-admin-health-suite' ); ?>
				</p>
			</div>

			<div class="wpha-stat-card">
				<h3><?php esc_html_e( 'Default Scan Mode', 'wp-admin-health-suite' ); ?></h3>
				<p class="wpha-stat-value">
					<?php
					if ( 'network_wide' === $settings['network_scan_mode'] ) {
						esc_html_e( 'Network-Wide', 'wp-admin-health-suite' );
					} else {
						esc_html_e( 'Current Site Only', 'wp-admin-health-suite' );
					}
					?>
				</p>
			</div>
		</div>

		<h2><?php esc_html_e( 'Network Sites', 'wp-admin-health-suite' ); ?></h2>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Site ID', 'wp-admin-health-suite' ); ?></th>
					<th><?php esc_html_e( 'Site URL', 'wp-admin-health-suite' ); ?></th>
					<th><?php esc_html_e( 'Blog Name', 'wp-admin-health-suite' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-admin-health-suite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $sites ) ) : ?>
					<?php foreach ( $sites as $site ) : ?>
						<tr>
							<td><?php echo esc_html( $site->blog_id ); ?></td>
							<td>
								<a href="<?php echo esc_url( get_site_url( $site->blog_id ) ); ?>" target="_blank">
									<?php echo esc_html( get_site_url( $site->blog_id ) ); ?>
								</a>
							</td>
							<td><?php echo esc_html( get_blog_option( $site->blog_id, 'blogname' ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( get_admin_url( $site->blog_id, 'admin.php?page=admin-health' ) ); ?>" class="button button-small">
									<?php esc_html_e( 'View Dashboard', 'wp-admin-health-suite' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'No sites found.', 'wp-admin-health-suite' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<style>
.wpha-stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin: 20px 0;
}

.wpha-stat-card {
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
	padding: 20px;
	text-align: center;
}

.wpha-stat-card h3 {
	margin: 0 0 10px 0;
	font-size: 14px;
	color: #666;
	font-weight: 600;
}

.wpha-stat-value {
	font-size: 32px;
	font-weight: bold;
	color: #2271b1;
	margin: 0;
}
</style>
