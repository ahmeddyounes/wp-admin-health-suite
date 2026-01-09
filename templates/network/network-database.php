<?php
/**
 * Network Database Health Template
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
	<h1><?php esc_html_e( 'Network Database Health', 'wp-admin-health-suite' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Monitor and manage database health across all sites in the network.', 'wp-admin-health-suite' ); ?>
	</p>

	<div id="wpha-network-database-root"></div>

	<h2><?php esc_html_e( 'Site Database Status', 'wp-admin-health-suite' ); ?></h2>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Site', 'wp-admin-health-suite' ); ?></th>
				<th><?php esc_html_e( 'Database Size', 'wp-admin-health-suite' ); ?></th>
				<th><?php esc_html_e( 'Table Count', 'wp-admin-health-suite' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-admin-health-suite' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $sites ) ) : ?>
				<?php foreach ( $sites as $site ) : ?>
					<?php
					global $wpdb;
					switch_to_blog( $site->blog_id );

					$table_prefix = $wpdb->prefix;
					$tables       = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT TABLE_NAME, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb
							FROM information_schema.TABLES
							WHERE TABLE_SCHEMA = %s
							AND TABLE_NAME LIKE %s",
							DB_NAME,
							$wpdb->esc_like( $table_prefix ) . '%'
						)
					);

					$total_size = 0;
					$table_count = 0;

					if ( ! empty( $tables ) ) {
						foreach ( $tables as $table ) {
							$total_size += (float) $table->size_mb;
							$table_count++;
						}
					}

					restore_current_blog();
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( get_blog_option( $site->blog_id, 'blogname' ) ); ?></strong><br>
							<small><?php echo esc_html( get_site_url( $site->blog_id ) ); ?></small>
						</td>
						<td><?php echo esc_html( number_format( $total_size, 2 ) ); ?> MB</td>
						<td><?php echo esc_html( $table_count ); ?></td>
						<td>
							<a href="<?php echo esc_url( get_admin_url( $site->blog_id, 'admin.php?page=admin-health-database' ) ); ?>" class="button button-small">
								<?php esc_html_e( 'View Details', 'wp-admin-health-suite' ); ?>
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
