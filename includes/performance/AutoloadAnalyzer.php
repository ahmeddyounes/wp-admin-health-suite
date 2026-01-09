<?php
/**
 * Autoload Options Analyzer Class
 *
 * Analyzes WordPress autoloaded options to identify performance issues.
 * Detects large autoloaded options, calculates total autoload size,
 * and provides recommendations for optimizing autoload behavior.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Performance;

use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Autoload Analyzer class for analyzing and optimizing autoloaded options.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements AutoloadAnalyzerInterface.
 */
class AutoloadAnalyzer implements AutoloadAnalyzerInterface {

	/**
	 * Threshold for large autoloaded options (in bytes).
	 *
	 * @var int
	 */
	const LARGE_OPTION_THRESHOLD = 10240; // 10KB

	/**
	 * Get all autoloaded options from the database.
	 *
 * @since 1.0.0
 *
	 * @return array Array of autoloaded options with details.
	 */
	public function get_autoloaded_options() {
		global $wpdb;

		// Query all options where autoload is 'yes'.
		$results = $wpdb->get_results(
			"SELECT option_name, option_value, autoload
			FROM {$wpdb->options}
			WHERE autoload = 'yes'
			ORDER BY LENGTH(option_value) DESC",
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array();
		}

		$options = array();

		foreach ( $results as $row ) {
			$option_name = $row['option_name'];
			$option_value = $row['option_value'];
			$size = strlen( $option_value );

			$options[] = array(
				'name'   => $option_name,
				'size'   => $size,
				'source' => $this->detect_option_source( $option_name ),
				'value'  => $option_value,
			);
		}

		return $options;
	}

	/**
	 * Get the total size of all autoloaded options.
	 *
 * @since 1.0.0
 *
	 * @return array Array with total size and count of autoloaded options.
	 */
	public function get_autoload_size() {
		global $wpdb;

		// Get total size and count of autoloaded options.
		$result = $wpdb->get_row(
			"SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as total_size
			FROM {$wpdb->options}
			WHERE autoload = 'yes'",
			ARRAY_A
		);

		$total_size = isset( $result['total_size'] ) ? (int) $result['total_size'] : 0;
		$count = isset( $result['count'] ) ? (int) $result['count'] : 0;

		return array(
			'total_size'       => $total_size,
			'total_size_kb'    => round( $total_size / 1024, 2 ),
			'total_size_mb'    => round( $total_size / ( 1024 * 1024 ), 2 ),
			'count'            => $count,
			'average_size'     => $count > 0 ? round( $total_size / $count, 2 ) : 0,
			'average_size_kb'  => $count > 0 ? round( $total_size / $count / 1024, 2 ) : 0,
		);
	}

	/**
	 * Find large autoloaded options exceeding a threshold.
	 *
 * @since 1.0.0
 *
	 * @param int $threshold Size threshold in bytes (default: 10KB).
	 * @return array Array of large autoloaded options.
	 */
	public function find_large_autoloads( $threshold = null ) {
		if ( null === $threshold ) {
			$threshold = self::LARGE_OPTION_THRESHOLD;
		}

		$all_options = $this->get_autoloaded_options();
		$large_options = array();

		foreach ( $all_options as $option ) {
			if ( $option['size'] >= $threshold ) {
				$large_options[] = array(
					'name'   => $option['name'],
					'size'   => $option['size'],
					'size_kb' => round( $option['size'] / 1024, 2 ),
					'source' => $option['source'],
				);
			}
		}

		return $large_options;
	}

	/**
	 * Recommend autoload changes based on analysis.
	 *
 * @since 1.0.0
 *
	 * @return array Array of recommendations with option details and suggested actions.
	 */
	public function recommend_autoload_changes() {
		$recommendations = array();
		$large_options = $this->find_large_autoloads();
		$autoload_stats = $this->get_autoload_size();

		// Overall assessment.
		if ( $autoload_stats['total_size_kb'] > 1000 ) {
			$recommendations[] = array(
				'type'     => 'critical',
				'title'    => 'Excessive Autoload Size',
				'message'  => sprintf(
					'Total autoloaded options size is %.2f KB (%.2f MB), which is very large and will impact every page load.',
					$autoload_stats['total_size_kb'],
					$autoload_stats['total_size_mb']
				),
				'priority' => 'high',
			);
		} elseif ( $autoload_stats['total_size_kb'] > 500 ) {
			$recommendations[] = array(
				'type'     => 'warning',
				'title'    => 'High Autoload Size',
				'message'  => sprintf(
					'Total autoloaded options size is %.2f KB. Consider optimizing to improve performance.',
					$autoload_stats['total_size_kb']
				),
				'priority' => 'medium',
			);
		} else {
			$recommendations[] = array(
				'type'     => 'success',
				'title'    => 'Autoload Size Acceptable',
				'message'  => sprintf(
					'Total autoloaded options size is %.2f KB, which is within acceptable limits.',
					$autoload_stats['total_size_kb']
				),
				'priority' => 'low',
			);
		}

		// Analyze large options.
		if ( ! empty( $large_options ) ) {
			foreach ( $large_options as $option ) {
				$severity = 'warning';
				$priority = 'medium';

				if ( $option['size_kb'] > 100 ) {
					$severity = 'critical';
					$priority = 'high';
				}

				$recommendation = array(
					'type'     => $severity,
					'title'    => sprintf( 'Large Autoloaded Option: %s', $option['name'] ),
					'message'  => sprintf(
						'Option "%s" is %.2f KB and autoloaded on every request. Source: %s.',
						$option['name'],
						$option['size_kb'],
						$option['source']
					),
					'priority' => $priority,
					'option'   => $option['name'],
					'size'     => $option['size'],
					'size_kb'  => $option['size_kb'],
					'source'   => $option['source'],
				);

				// Add specific recommendation based on source and common patterns.
				$action = $this->get_option_recommendation( $option['name'], $option['source'], $option['size'] );
				if ( $action ) {
					$recommendation['action'] = $action;
				}

				$recommendations[] = $recommendation;
			}
		}

		// Check for known problematic options.
		$all_options = $this->get_autoloaded_options();
		$problematic = $this->identify_problematic_options( $all_options );

		foreach ( $problematic as $issue ) {
			$recommendations[] = $issue;
		}

		return $recommendations;
	}

	/**
	 * Change the autoload status of an option.
	 *
 * @since 1.0.0
 *
	 * @param string $option_name The name of the option to change.
	 * @param string $new_autoload New autoload value ('yes' or 'no').
	 * @return bool|array True on success, array with error details on failure.
	 */
	public function change_autoload_status( $option_name, $new_autoload ) {
		// Validate autoload value.
		if ( ! in_array( $new_autoload, array( 'yes', 'no' ), true ) ) {
			return array(
				'success' => false,
				'error'   => 'Invalid autoload value. Must be "yes" or "no".',
			);
		}

		// Get current option value.
		$option_value = get_option( $option_name );

		if ( false === $option_value ) {
			return array(
				'success' => false,
				'error'   => 'Option does not exist.',
			);
		}

		global $wpdb;

		// Update the autoload field directly in the database.
		$result = $wpdb->update(
			$wpdb->options,
			array( 'autoload' => $new_autoload ),
			array( 'option_name' => $option_name ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return array(
				'success' => false,
				'error'   => 'Database update failed.',
			);
		}

		// Clear the options cache to ensure the change takes effect.
		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		return array(
			'success' => true,
			'message' => sprintf(
				'Successfully changed autoload status for "%s" to "%s".',
				$option_name,
				$new_autoload
			),
		);
	}

	/**
	 * Detect the source of an option (core, plugin, or theme).
	 *
	 * @param string $option_name The option name.
	 * @return string The detected source (core, plugin name, theme name, or 'unknown').
	 */
	private function detect_option_source( $option_name ) {
		// WordPress core options.
		$core_options = array(
			'siteurl', 'home', 'blogname', 'blogdescription', 'users_can_register',
			'admin_email', 'start_of_week', 'use_balanceTags', 'use_smilies',
			'require_name_email', 'comments_notify', 'posts_per_rss', 'rss_use_excerpt',
			'mailserver_url', 'mailserver_login', 'mailserver_pass', 'mailserver_port',
			'default_category', 'default_comment_status', 'default_ping_status',
			'default_pingback_flag', 'posts_per_page', 'date_format', 'time_format',
			'links_updated_date_format', 'comment_moderation', 'moderation_notify',
			'permalink_structure', 'rewrite_rules', 'hack_file', 'upload_path',
			'blog_public', 'default_link_category', 'show_on_front', 'tag_base',
			'show_avatars', 'avatar_rating', 'upload_url_path', 'thumbnail_size_w',
			'thumbnail_size_h', 'thumbnail_crop', 'medium_size_w', 'medium_size_h',
			'avatar_default', 'large_size_w', 'large_size_h', 'image_default_link_type',
			'image_default_size', 'image_default_align', 'close_comments_for_old_posts',
			'close_comments_days_old', 'thread_comments', 'thread_comments_depth',
			'page_comments', 'comments_per_page', 'default_comments_page',
			'comment_order', 'sticky_posts', 'widget_categories', 'widget_text',
			'widget_rss', 'uninstall_plugins', 'timezone_string', 'page_for_posts',
			'page_on_front', 'default_post_format', 'link_manager_enabled',
			'finished_splitting_shared_terms', 'site_icon', 'medium_large_size_w',
			'medium_large_size_h', 'wp_page_for_privacy_policy', 'show_comments_cookies_opt_in',
			'admin_email_lifespan', 'disallowed_keys', 'comment_previously_approved',
			'auto_plugin_theme_update_emails', 'auto_update_core_dev', 'auto_update_core_minor',
			'auto_update_core_major', 'wp_force_deactivated_plugins', 'initial_db_version',
			'db_version', 'db_upgraded', 'can_compress_scripts', 'recently_activated',
			'template', 'stylesheet', 'cron', 'active_plugins', 'category_base',
		);

		if ( in_array( $option_name, $core_options, true ) ) {
			return 'core';
		}

		// Check for common patterns.
		if ( strpos( $option_name, 'theme_mods_' ) === 0 || strpos( $option_name, 'widget_' ) === 0 ) {
			return 'core';
		}

		// Try to detect plugin/theme by prefix.
		// Common plugin prefixes.
		$plugin_patterns = array(
			'woocommerce' => 'WooCommerce',
			'yoast'       => 'Yoast SEO',
			'wordfence'   => 'Wordfence',
			'jetpack'     => 'Jetpack',
			'akismet'     => 'Akismet',
			'elementor'   => 'Elementor',
			'wpforms'     => 'WPForms',
			'gravityforms' => 'Gravity Forms',
			'acf'         => 'Advanced Custom Fields',
			'edd'         => 'Easy Digital Downloads',
			'llms'        => 'LifterLMS',
			'bbpress'     => 'bbPress',
			'buddypress'  => 'BuddyPress',
			'wp_rocket'   => 'WP Rocket',
		);

		foreach ( $plugin_patterns as $pattern => $name ) {
			if ( stripos( $option_name, $pattern ) !== false ) {
				return $name;
			}
		}

		// Check if it's a theme option.
		$current_theme = wp_get_theme();
		$theme_slug = $current_theme->get_stylesheet();

		if ( stripos( $option_name, $theme_slug ) !== false ) {
			return 'Theme: ' . $current_theme->get( 'Name' );
		}

		return 'Unknown';
	}

	/**
	 * Get specific recommendation for an option.
	 *
	 * @param string $option_name The option name.
	 * @param string $source The option source.
	 * @param int    $size The option size in bytes.
	 * @return string|null Recommendation text or null.
	 */
	private function get_option_recommendation( $option_name, $source, $size ) {
		// Recommendations for known options.
		$recommendations = array(
			'rewrite_rules' => 'This is a WordPress core option that can be large. Ensure permalinks are properly flushed. Consider using transients instead if frequently regenerated.',
			'cron'          => 'WordPress cron array. If very large, consider cleaning up old scheduled events or using a real cron job.',
		);

		if ( isset( $recommendations[ $option_name ] ) ) {
			return $recommendations[ $option_name ];
		}

		// Generic recommendations based on size.
		if ( $size > 102400 ) { // 100KB
			return 'This option is extremely large. Consider storing data in a custom table or file, or breaking it into smaller options with autoload=no.';
		} elseif ( $size > 51200 ) { // 50KB
			return 'This option is very large. Consider disabling autoload if this data is not needed on every page load.';
		} elseif ( $size > 10240 ) { // 10KB
			return 'Consider disabling autoload if this option is not frequently accessed.';
		}

		return null;
	}

	/**
	 * Identify problematic options based on common patterns.
	 *
	 * @param array $options Array of all autoloaded options.
	 * @return array Array of issues found.
	 */
	private function identify_problematic_options( $options ) {
		$issues = array();

		// Check for transient options (should typically not be autoloaded).
		foreach ( $options as $option ) {
			if ( strpos( $option['name'], '_transient_' ) === 0 ) {
				$issues[] = array(
					'type'     => 'warning',
					'title'    => 'Transient with Autoload Enabled',
					'message'  => sprintf(
						'Option "%s" appears to be a transient but has autoload enabled. Transients should not be autoloaded.',
						$option['name']
					),
					'priority' => 'medium',
					'option'   => $option['name'],
					'action'   => 'Disable autoload for this transient option.',
				);
			}

			// Check for session data.
			if ( strpos( $option['name'], '_session_' ) !== false || strpos( $option['name'], 'session_tokens' ) !== false ) {
				$issues[] = array(
					'type'     => 'warning',
					'title'    => 'Session Data with Autoload Enabled',
					'message'  => sprintf(
						'Option "%s" appears to contain session data but has autoload enabled.',
						$option['name']
					),
					'priority' => 'medium',
					'option'   => $option['name'],
					'action'   => 'Session data should not be autoloaded.',
				);
			}

			// Check for cache options.
			if ( strpos( $option['name'], '_cache' ) !== false || strpos( $option['name'], 'cache_' ) !== false ) {
				$issues[] = array(
					'type'     => 'warning',
					'title'    => 'Cache Data with Autoload Enabled',
					'message'  => sprintf(
						'Option "%s" appears to be cache data but has autoload enabled. Cache should use transients or object cache.',
						$option['name']
					),
					'priority' => 'medium',
					'option'   => $option['name'],
					'action'   => 'Disable autoload for cache options.',
				);
			}
		}

		return $issues;
	}
}
