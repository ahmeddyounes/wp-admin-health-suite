<?php
/**
 * Settings Template
 *
 * @package WPAdminHealth
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

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

	<form method="post" action="options.php">
		<?php
		settings_fields( 'wpha_settings_group' );
		do_settings_sections( 'wpha_settings' );
		submit_button();
		?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Import/Export Settings', 'wp-admin-health-suite' ); ?></h2>

	<div class="wpha-settings-tools">
		<h3><?php esc_html_e( 'Export Settings', 'wp-admin-health-suite' ); ?></h3>
		<p><?php esc_html_e( 'Export your current settings as a JSON file.', 'wp-admin-health-suite' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wpha_export_settings' ); ?>
			<input type="hidden" name="action" value="wpha_export_settings">
			<?php submit_button( __( 'Export Settings', 'wp-admin-health-suite' ), 'secondary', 'submit', false ); ?>
		</form>

		<h3><?php esc_html_e( 'Import Settings', 'wp-admin-health-suite' ); ?></h3>
		<p><?php esc_html_e( 'Import settings from a JSON file.', 'wp-admin-health-suite' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'wpha_import_settings' ); ?>
			<input type="hidden" name="action" value="wpha_import_settings">
			<input type="file" name="import_file" accept=".json" required>
			<?php submit_button( __( 'Import Settings', 'wp-admin-health-suite' ), 'secondary', 'submit', false ); ?>
		</form>

		<h3><?php esc_html_e( 'Reset Settings', 'wp-admin-health-suite' ); ?></h3>
		<p><?php esc_html_e( 'Reset all settings to their default values.', 'wp-admin-health-suite' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to reset all settings to defaults?', 'wp-admin-health-suite' ) ); ?>');">
			<?php wp_nonce_field( 'wpha_reset_settings' ); ?>
			<input type="hidden" name="action" value="wpha_reset_settings">
			<?php submit_button( __( 'Reset Settings', 'wp-admin-health-suite' ), 'delete', 'submit', false ); ?>
		</form>
	</div>
</div>
