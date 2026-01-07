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

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="wpha-performance">
		<p><?php esc_html_e( 'Performance monitoring and optimization tools.', 'wp-admin-health-suite' ); ?></p>
	</div>
</div>
