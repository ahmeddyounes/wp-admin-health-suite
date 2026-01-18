<?php
/**
 * Update Autoload Option Use Case
 *
 * Application service for updating an option's autoload flag.
 *
 * @package WPAdminHealth\Application\Performance
 */

namespace WPAdminHealth\Application\Performance;

use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class UpdateAutoloadOption
 *
 * @since 1.7.0
 */
class UpdateAutoloadOption {

	private ConnectionInterface $connection;

	/**
	 * @since 1.7.0
	 */
	public function __construct( ConnectionInterface $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Execute update autoload.
	 *
	 * @since 1.7.0
	 *
	 * @param string $option_name Option name.
	 * @param bool   $autoload    Whether to autoload.
	 * @return array|WP_Error
	 */
	public function execute( string $option_name, bool $autoload ) {
		$option_name = sanitize_text_field( $option_name );
		$options_table = $this->connection->get_options_table();

		if ( $this->is_protected_option( $option_name ) ) {
			return new WP_Error(
				'protected_option',
				__( 'This option is protected and cannot be modified for security reasons.', 'wp-admin-health-suite' ),
				array( 'status' => 403 )
			);
		}

		$query = $this->connection->prepare(
			"SELECT COUNT(*) FROM {$options_table} WHERE option_name = %s",
			$option_name
		);
		$option_exists = $query ? $this->connection->get_var( $query ) : 0;

		if ( ! $option_exists ) {
			return new WP_Error(
				'option_not_found',
				__( 'The specified option does not exist.', 'wp-admin-health-suite' ),
				array( 'status' => 404 )
			);
		}

		$autoload_value = $autoload ? 'yes' : 'no';
		$result         = $this->connection->update(
			$options_table,
			array( 'autoload' => $autoload_value ),
			array( 'option_name' => $option_name ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				__( 'Failed to update autoload setting.', 'wp-admin-health-suite' ),
				array( 'status' => 500 )
			);
		}

		// Clear caches to ensure changes take effect.
		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		return array(
			'option_name' => $option_name,
			'autoload'    => $autoload,
		);
	}

	/**
	 * Check if an option is protected from autoload modification.
	 *
	 * @since 1.7.0
	 */
	private function is_protected_option( string $option_name ): bool {
		$protected_options = array(
			'siteurl',
			'home',
			'blogname',
			'blogdescription',
			'admin_email',
			'users_can_register',
			'start_of_week',
			'use_balanceTags',
			'use_smilies',
			'require_name_email',
			'comments_notify',
			'posts_per_rss',
			'rss_use_excerpt',
			'mailserver_url',
			'mailserver_login',
			'mailserver_pass',
			'mailserver_port',
			'default_category',
			'default_comment_status',
			'default_ping_status',
			'default_pingback_flag',
			'posts_per_page',
			'date_format',
			'time_format',
			'links_updated_date_format',
			'comment_moderation',
			'moderation_notify',
			'permalink_structure',
			'hack_file',
			'blog_charset',
			'moderation_keys',
			'active_plugins',
			'category_base',
			'ping_sites',
			'comment_max_links',
			'gmt_offset',
			'default_email_category',
			'recently_edited',
			'template',
			'stylesheet',
			'comment_whitelist',
			'comment_registration',
			'html_type',
			'default_role',
			'db_version',
			'uploads_use_yearmonth_folders',
			'upload_path',
			'blog_public',
			'default_link_category',
			'show_on_front',
			'tag_base',
			'show_avatars',
			'avatar_rating',
			'upload_url_path',
			'thumbnail_size_w',
			'thumbnail_size_h',
			'thumbnail_crop',
			'medium_size_w',
			'medium_size_h',
			'avatar_default',
			'large_size_w',
			'large_size_h',
			'image_default_link_type',
			'image_default_size',
			'image_default_align',
			'close_comments_for_old_posts',
			'close_comments_days_old',
			'thread_comments',
			'thread_comments_depth',
			'page_comments',
			'comments_per_page',
			'default_comments_page',
			'comment_order',
			'sticky_posts',
			'widget_categories',
			'widget_text',
			'widget_rss',
			'timezone_string',
			'page_for_posts',
			'page_on_front',
			'default_post_format',
			'link_manager_enabled',
			'finished_splitting_shared_terms',
			'site_icon',
			'medium_large_size_w',
			'medium_large_size_h',
			'wp_page_for_privacy_policy',
			'show_comments_cookies_opt_in',
			'initial_db_version',
			'current_theme',
			'WPLANG',
			'site_admins',
			'network_admins',
			'wp_user_roles',
			'cron',
			'can_compress_scripts',
		);

		if ( in_array( $option_name, $protected_options, true ) ) {
			return true;
		}

		$protected_prefixes = array(
			'_site_transient_',
			'_transient_timeout_',
			'wp_user_roles',
			'user_roles',
			'auto_core_update_',
			'auto_plugin_',
			'auto_theme_',
		);

		foreach ( $protected_prefixes as $prefix ) {
			if ( 0 === strpos( $option_name, $prefix ) ) {
				return true;
			}
		}

		return (bool) apply_filters( 'wpha_is_protected_autoload_option', false, $option_name );
	}
}
