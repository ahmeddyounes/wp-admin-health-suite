<?php
/**
 * Media Exclusions Manager Class
 *
 * Manages media items excluded from cleanup suggestions.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Media Exclusions manager for handling items excluded from scans and cleanup.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements ExclusionsInterface.
 */
class Exclusions implements ExclusionsInterface {

	/**
	 * Option key for storing exclusions.
	 *
	 * @var string
	 */
	private $option_key = 'wp_admin_health_media_exclusions';

	/**
	 * Settings instance (optional).
	 *
	 * @var SettingsInterface|null
	 */
	private ?SettingsInterface $settings = null;

	/**
	 * Cached settings-based exclusions.
	 *
	 * @var array|null
	 */
	private ?array $cached_settings_exclusions = null;

	/**
	 * Cached uploads base directory (normalized with forward slashes).
	 *
	 * @var string|null
	 */
	private ?string $uploads_basedir = null;

	/**
	 * Constructor.
	 *
	 * @since 1.6.0
	 *
	 * @param SettingsInterface|null $settings Optional settings instance.
	 */
	public function __construct( ?SettingsInterface $settings = null ) {
		$this->settings = $settings;
	}

	/**
	 * Get the settings instance if available.
	 *
	 * Returns the settings instance injected via constructor, or null if not provided.
	 * This class is container-managed via MediaServiceProvider, which always injects
	 * the SettingsInterface dependency.
	 *
	 * @return SettingsInterface|null Settings instance or null if unavailable.
	 */
	private function get_settings(): ?SettingsInterface {
		return $this->settings;
	}

	/**
	 * Get and cache uploads base directory (normalized).
	 *
	 * @return string Uploads base directory path.
	 */
	private function get_uploads_basedir(): string {
		if ( null !== $this->uploads_basedir ) {
			return $this->uploads_basedir;
		}

		$upload_dir            = wp_upload_dir();
		$basedir               = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
		$this->uploads_basedir = str_replace( '\\', '/', rtrim( $basedir, '/' ) );

		return $this->uploads_basedir;
	}

	/**
	 * Parse a list of integers from settings value.
	 *
	 * Accepts comma and/or newline separated values.
	 *
	 * @param string $raw Raw value.
	 * @return array<int, bool> Map of excluded IDs.
	 */
	private function parse_excluded_ids( string $raw ): array {
		$raw = wp_strip_all_tags( $raw );

		preg_match_all( '/\d+/', $raw, $matches );

		$ids = array();
		if ( isset( $matches[0] ) && is_array( $matches[0] ) ) {
			foreach ( $matches[0] as $match ) {
				$id = absint( $match );
				if ( $id <= 0 ) {
					continue;
				}
				$ids[ $id ] = true;
			}
		}

		return $ids;
	}

	/**
	 * Parse patterns from settings value.
	 *
	 * Accepts comma and/or newline separated values.
	 *
	 * @param string $raw Raw value.
	 * @return array<int, string> Patterns.
	 */
	private function parse_patterns( string $raw ): array {
		$raw = wp_strip_all_tags( $raw );
		$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );

		$parts = preg_split( '/[\n,]+/', $raw );
		if ( false === $parts || empty( $parts ) ) {
			return array();
		}

		$patterns = array();
		foreach ( $parts as $part ) {
			$pattern = trim( (string) $part );
			if ( '' === $pattern ) {
				continue;
			}

			$patterns[] = sanitize_text_field( $pattern );
		}

		$patterns = array_values( array_unique( array_filter( $patterns, 'strlen' ) ) );

		return $patterns;
	}

	/**
	 * Get settings-based exclusions (cached).
	 *
	 * @return array{
	 *     excluded_ids: array<int, bool>,
	 *     mime_exact: array<string, bool>,
	 *     mime_patterns: array<int, string>,
	 *     file_patterns: array<int, string>
	 * }
	 */
	private function get_settings_exclusions(): array {
		if ( null !== $this->cached_settings_exclusions ) {
			return $this->cached_settings_exclusions;
		}

		$excluded_ids_raw     = '';
		$excluded_mime_raw    = '';
		$excluded_files_raw   = '';
		$settings             = $this->get_settings();

		if ( $settings ) {
			$excluded_ids_raw   = (string) $settings->get_setting( 'excluded_media_ids', '' );
			$excluded_mime_raw  = (string) $settings->get_setting( 'exclude_media_types', '' );
			$excluded_files_raw = (string) $settings->get_setting( 'exclude_media_file_patterns', '' );
		} else {
			$raw_settings = get_option( 'wpha_settings', array() );
			if ( is_array( $raw_settings ) ) {
				$excluded_ids_raw   = isset( $raw_settings['excluded_media_ids'] ) ? (string) $raw_settings['excluded_media_ids'] : '';
				$excluded_mime_raw  = isset( $raw_settings['exclude_media_types'] ) ? (string) $raw_settings['exclude_media_types'] : '';
				$excluded_files_raw = isset( $raw_settings['exclude_media_file_patterns'] ) ? (string) $raw_settings['exclude_media_file_patterns'] : '';
			}
		}

		$excluded_ids = $this->parse_excluded_ids( $excluded_ids_raw );

		$mime_exact    = array();
		$mime_patterns = array();
		foreach ( $this->parse_patterns( $excluded_mime_raw ) as $pattern ) {
			$pattern = strtolower( preg_replace( '/\s+/', '', $pattern ) );
			if ( '' === $pattern ) {
				continue;
			}

			if ( strpbrk( $pattern, '*?[' ) !== false ) {
				$mime_patterns[] = $pattern;
			} else {
				$mime_exact[ $pattern ] = true;
			}
		}

		$file_patterns = $this->parse_patterns( $excluded_files_raw );

		$this->cached_settings_exclusions = array(
			'excluded_ids'   => $excluded_ids,
			'mime_exact'     => $mime_exact,
			'mime_patterns'  => $mime_patterns,
			'file_patterns'  => $file_patterns,
		);

		return $this->cached_settings_exclusions;
	}

	/**
	 * Check if a MIME type matches configured exclusion patterns.
	 *
	 * @param string $mime_type MIME type.
	 * @param array  $mime_exact Exact matches (lowercase => true).
	 * @param array  $mime_patterns Wildcard patterns (lowercase).
	 * @return bool True if excluded by MIME patterns.
	 */
	private function is_mime_type_excluded( string $mime_type, array $mime_exact, array $mime_patterns ): bool {
		$mime_type = strtolower( trim( $mime_type ) );

		if ( '' === $mime_type ) {
			return false;
		}

		if ( isset( $mime_exact[ $mime_type ] ) ) {
			return true;
		}

		foreach ( $mime_patterns as $pattern ) {
			if ( '' === $pattern ) {
				continue;
			}

			if ( function_exists( 'fnmatch' ) && fnmatch( $pattern, $mime_type ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a file path matches configured exclusion patterns.
	 *
	 * Supports both glob patterns (fnmatch) and plain substring matches.
	 *
	 * @param string $file_path File path.
	 * @param array  $patterns Patterns.
	 * @return bool True if excluded by file patterns.
	 */
	private function is_file_excluded( string $file_path, array $patterns ): bool {
		$file_path = str_replace( '\\', '/', $file_path );

		if ( '' === $file_path || empty( $patterns ) ) {
			return false;
		}

		$basedir         = $this->get_uploads_basedir();
		$relative_path   = $file_path;
		$basedir_prefix  = '' !== $basedir ? rtrim( $basedir, '/' ) . '/' : '';

		if ( '' !== $basedir_prefix && 0 === strpos( $relative_path, $basedir_prefix ) ) {
			$relative_path = substr( $relative_path, strlen( $basedir_prefix ) );
		}

		$basename      = basename( $relative_path );
		$relative_path = ltrim( $relative_path, '/' );

		$relative_lower = strtolower( $relative_path );
		$basename_lower = strtolower( $basename );

		foreach ( $patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}

			$pattern_lower = strtolower( $pattern );
			$is_glob       = strpbrk( $pattern_lower, '*?[' ) !== false;
			$has_slash     = strpos( $pattern_lower, '/' ) !== false;

			if ( $is_glob && function_exists( 'fnmatch' ) ) {
				if ( $has_slash ) {
					if ( fnmatch( $pattern_lower, $relative_lower ) ) {
						return true;
					}
				} elseif ( fnmatch( $pattern_lower, $basename_lower ) ) {
					return true;
				}

				continue;
			}

			// Plain substring match fallback.
			if ( $has_slash ) {
				if ( false !== strpos( $relative_lower, $pattern_lower ) ) {
					return true;
				}
			} elseif ( false !== strpos( $basename_lower, $pattern_lower ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add an exclusion for an attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $attachment_id Attachment ID to exclude.
	 * @param string $reason        Reason for exclusion.
	 * @return bool True on success, false on failure.
	 */
	public function add_exclusion( int $attachment_id, string $reason = '' ): bool {
		$attachment_id = absint( $attachment_id );

		if ( $attachment_id <= 0 ) {
			return false;
		}

		// Check if attachment exists.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		$exclusions = $this->get_all_exclusions();

		// Check if already excluded.
		if ( isset( $exclusions[ $attachment_id ] ) ) {
			return false;
		}

		// Get current user ID.
		$user_id = get_current_user_id();

		$exclusions[ $attachment_id ] = array(
			'attachment_id' => $attachment_id,
			'excluded_at'   => current_time( 'mysql' ),
			'reason'        => sanitize_text_field( $reason ),
			'excluded_by'   => $user_id,
		);

		return update_option( $this->option_key, $exclusions );
	}

	/**
	 * Remove an exclusion for an attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment ID to remove from exclusions.
	 * @return bool True on success, false on failure.
	 */
	public function remove_exclusion( int $attachment_id ): bool {
		$attachment_id = absint( $attachment_id );

		if ( $attachment_id <= 0 ) {
			return false;
		}

		$exclusions = $this->get_all_exclusions();

		if ( ! isset( $exclusions[ $attachment_id ] ) ) {
			return false;
		}

		unset( $exclusions[ $attachment_id ] );

		return update_option( $this->option_key, $exclusions );
	}

	/**
	 * Get all exclusions.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of exclusions with metadata.
	 */
	public function get_exclusions(): array {
		return array_values( $this->get_all_exclusions() );
	}

	/**
	 * Check if an attachment is excluded.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment ID to check.
	 * @return bool True if excluded, false otherwise.
	 */
	public function is_excluded( int $attachment_id ): bool {
		$attachment_id = absint( $attachment_id );

		if ( $attachment_id <= 0 ) {
			return false;
		}

		// Manual exclusions (via exclusions manager).
		$exclusions = $this->get_all_exclusions();
		if ( isset( $exclusions[ $attachment_id ] ) ) {
			return true;
		}

		// Settings-based exclusions.
		$settings_exclusions = $this->get_settings_exclusions();

		if ( isset( $settings_exclusions['excluded_ids'][ $attachment_id ] ) ) {
			return true;
		}

		// MIME type exclusions.
		if ( ! empty( $settings_exclusions['mime_exact'] ) || ! empty( $settings_exclusions['mime_patterns'] ) ) {
			$mime_type = get_post_mime_type( $attachment_id );
			if ( $mime_type && $this->is_mime_type_excluded( (string) $mime_type, $settings_exclusions['mime_exact'], $settings_exclusions['mime_patterns'] ) ) {
				return true;
			}
		}

		// File pattern exclusions.
		if ( ! empty( $settings_exclusions['file_patterns'] ) ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && $this->is_file_excluded( (string) $file_path, $settings_exclusions['file_patterns'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Bulk add exclusions for multiple attachments.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $attachment_ids Array of attachment IDs to exclude.
	 * @param string $reason         Reason for exclusion.
	 * @return array Result with success count and failed IDs.
	 */
	public function bulk_add_exclusions( array $attachment_ids, string $reason = '' ): array {
		if ( empty( $attachment_ids ) ) {
			return array(
				'success' => 0,
				'failed'  => array(),
			);
		}

		$success_count = 0;
		$failed_ids = array();

		foreach ( $attachment_ids as $attachment_id ) {
			if ( $this->add_exclusion( $attachment_id, $reason ) ) {
				$success_count++;
			} else {
				$failed_ids[] = $attachment_id;
			}
		}

		return array(
			'success' => $success_count,
			'failed'  => $failed_ids,
		);
	}

	/**
	 * Clear all exclusions.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_exclusions(): bool {
		return delete_option( $this->option_key );
	}

	/**
	 * Get all exclusions as associative array (keyed by attachment ID).
	 *
	 * @return array Array of exclusions.
	 */
	private function get_all_exclusions() {
		$exclusions = get_option( $this->option_key, array() );

		if ( ! is_array( $exclusions ) ) {
			return array();
		}

		return $exclusions;
	}

	/**
	 * Filter out excluded items from an array of attachment IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attachment_ids Array of attachment IDs.
	 * @return array Filtered array with excluded items removed.
	 */
	public function filter_excluded( array $attachment_ids ): array {
		if ( empty( $attachment_ids ) ) {
			return array();
		}

		return array_filter(
			$attachment_ids,
			function ( $attachment_id ) {
				return ! $this->is_excluded( (int) $attachment_id );
			}
		);
	}
}
