<?php
/**
 * Autoload REST Controller
 *
 * Handles autoload options analysis and management.
 *
 * @package WPAdminHealth\REST\Performance
 */

namespace WPAdminHealth\REST\Performance;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for autoload options endpoints.
 *
 * Provides endpoints for analyzing and managing autoloaded options.
 *
 * @since 1.3.0
 */
class AutoloadController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'performance/autoload';

	/**
	 * Autoload analyzer instance.
	 *
	 * @var AutoloadAnalyzerInterface
	 */
	private AutoloadAnalyzerInterface $autoload_analyzer;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface          $settings          Settings instance.
	 * @param ConnectionInterface        $connection        Database connection instance.
	 * @param AutoloadAnalyzerInterface  $autoload_analyzer Autoload analyzer instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		AutoloadAnalyzerInterface $autoload_analyzer
	) {
		parent::__construct( $settings, $connection );
		$this->autoload_analyzer = $autoload_analyzer;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/performance/autoload.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_autoload_analysis' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_autoload' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'option_name' => array(
							'description'       => __( 'The option name to update.', 'wp-admin-health-suite' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'autoload'    => array(
							'description'       => __( 'Whether to autoload the option.', 'wp-admin-health-suite' ),
							'type'              => 'boolean',
							'required'          => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Get autoload analysis.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to AutoloadController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_autoload_analysis( $request ) {
		$connection    = $this->get_connection();
		$options_table = $connection->get_options_table();

		$size_stats  = $this->autoload_analyzer->get_autoload_size();
		$total_size  = isset( $size_stats['total_size'] ) ? (int) $size_stats['total_size'] : 0;
		$total_count = isset( $size_stats['count'] ) ? (int) $size_stats['count'] : 0;

		$autoload_options = $connection->get_results(
			"SELECT option_name, LENGTH(option_value) as size
			FROM {$options_table}
			WHERE autoload = 'yes'
			ORDER BY size DESC
			LIMIT 50"
		);

		$options    = array();

		foreach ( $autoload_options as $option ) {
			$options[] = array(
				'name' => $option->option_name,
				'size' => (int) $option->size,
			);
		}

		$response_data = array(
			'total_size'    => $total_size,
			'total_size_mb' => isset( $size_stats['total_size_mb'] ) ? (float) $size_stats['total_size_mb'] : round( $total_size / 1024 / 1024, 2 ),
			'options'       => $options,
			'count'         => $total_count,
		);

		return $this->format_response(
			true,
			$response_data,
			__( 'Autoload analysis retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Update autoload setting for an option.
	 *
	 * Security: Protected options cannot have their autoload setting changed
	 * to prevent accidental or malicious disruption of WordPress core functionality.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Added protection for core WordPress options.
	 * @since 1.3.0 Moved to AutoloadController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_autoload( $request ) {
		$connection    = $this->get_connection();
		$options_table = $connection->get_options_table();

		$option_name = $request->get_param( 'option_name' );
		$autoload    = (bool) $request->get_param( 'autoload' );

		// Security: Check if the option is protected from modification.
		if ( $this->is_protected_option( $option_name ) ) {
			return $this->format_error_response(
				new \WP_Error(
					'protected_option',
					__( 'This option is protected and cannot be modified for security reasons.', 'wp-admin-health-suite' )
				),
				403
			);
		}

		// Check if option exists.
		$query = $connection->prepare(
			"SELECT COUNT(*) FROM {$options_table} WHERE option_name = %s",
			$option_name
		);
		$option_exists = $query ? $connection->get_var( $query ) : 0;

		if ( ! $option_exists ) {
			return $this->format_error_response(
				new \WP_Error(
					'option_not_found',
					__( 'The specified option does not exist.', 'wp-admin-health-suite' )
				),
				404
			);
		}

		// Update the autoload setting.
		$autoload_value = $autoload ? 'yes' : 'no';
		$result = $connection->update(
			$options_table,
			array( 'autoload' => $autoload_value ),
			array( 'option_name' => $option_name ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return $this->format_error_response(
				new \WP_Error(
					'update_failed',
					__( 'Failed to update autoload setting.', 'wp-admin-health-suite' )
				),
				500
			);
		}

			// Clear the alloptions cache to ensure changes take effect.
			wp_cache_delete( $option_name, 'options' );
			wp_cache_delete( 'alloptions', 'options' );

		return $this->format_response(
			true,
			array(
				'option_name' => $option_name,
				'autoload'    => $autoload,
			),
			__( 'Autoload setting updated successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get total autoload size.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to AutoloadController.
	 *
	 * @return int Autoload size in bytes.
	 */
	private function get_autoload_size(): int {
		$connection    = $this->get_connection();
		$options_table = $connection->get_options_table();

		$result = $connection->get_var(
			"SELECT SUM(LENGTH(option_value))
			FROM {$options_table}
			WHERE autoload = 'yes'"
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Check if an option is protected from autoload modification.
	 *
	 * WordPress core options and critical plugin options should not have
	 * their autoload setting changed, as this could break site functionality.
	 *
	 * @since 1.2.0
	 * @since 1.3.0 Moved to AutoloadController.
	 *
	 * @param string $option_name The option name to check.
	 * @return bool True if the option is protected, false otherwise.
	 */
	private function is_protected_option( string $option_name ): bool {
		// Core WordPress options that must remain autoloaded for WordPress to function.
		$protected_options = array(
			// Essential WordPress settings.
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
			// Multisite specific.
			'site_admins',
			'network_admins',
			// User capabilities (critical for security).
			'wp_user_roles',
			// Cron and scheduling (must be autoloaded).
			'cron',
			// Site transients.
			'can_compress_scripts',
		);

		// Check exact match.
		if ( in_array( $option_name, $protected_options, true ) ) {
			return true;
		}

		// Protect options starting with critical prefixes.
		$protected_prefixes = array(
			'_site_transient_',      // Site transients.
			'_transient_timeout_',   // Transient timeouts.
			'wp_user_roles',         // User roles.
			'user_roles',            // Multisite user roles.
			'auto_core_update_',     // Core update settings.
			'auto_plugin_',          // Auto-update settings.
			'auto_theme_',           // Theme auto-update settings.
		);

		foreach ( $protected_prefixes as $prefix ) {
			if ( 0 === strpos( $option_name, $prefix ) ) {
				return true;
			}
		}

		/**
		 * Filter whether an option is protected from autoload modification.
		 *
		 * @since 1.2.0
		 *
		 * @param bool   $is_protected Whether the option is protected.
		 * @param string $option_name  The option name being checked.
		 */
		return apply_filters( 'wpha_is_protected_autoload_option', false, $option_name );
	}
}
