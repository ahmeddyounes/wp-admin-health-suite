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

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="wpha-media-audit">
		<p><?php esc_html_e( 'Media library audit and optimization tools.', 'wp-admin-health-suite' ); ?></p>
	</div>
</div>
