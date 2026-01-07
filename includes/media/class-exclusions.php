<?php
/**
 * Media Exclusions Manager Class
 *
 * Manages media items excluded from cleanup suggestions.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Media Exclusions manager for handling items excluded from scans and cleanup.
 */
class Exclusions {

	/**
	 * Option key for storing exclusions.
	 *
	 * @var string
	 */
	private $option_key = 'wp_admin_health_media_exclusions';

	/**
	 * Add an exclusion for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID to exclude.
	 * @param string $reason        Reason for exclusion.
	 * @return bool True on success, false on failure.
	 */
	public function add_exclusion( $attachment_id, $reason = '' ) {
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
	 * @param int $attachment_id Attachment ID to remove from exclusions.
	 * @return bool True on success, false on failure.
	 */
	public function remove_exclusion( $attachment_id ) {
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
	 * @return array Array of exclusions with metadata.
	 */
	public function get_exclusions() {
		return array_values( $this->get_all_exclusions() );
	}

	/**
	 * Check if an attachment is excluded.
	 *
	 * @param int $attachment_id Attachment ID to check.
	 * @return bool True if excluded, false otherwise.
	 */
	public function is_excluded( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		$exclusions = $this->get_all_exclusions();

		return isset( $exclusions[ $attachment_id ] );
	}

	/**
	 * Bulk add exclusions for multiple attachments.
	 *
	 * @param array  $ids    Array of attachment IDs to exclude.
	 * @param string $reason Reason for exclusion.
	 * @return array Result with success count and failed IDs.
	 */
	public function bulk_add_exclusions( $ids, $reason = '' ) {
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return array(
				'success' => 0,
				'failed'  => array(),
			);
		}

		$success_count = 0;
		$failed_ids = array();

		foreach ( $ids as $attachment_id ) {
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
	 * @return bool True on success, false on failure.
	 */
	public function clear_exclusions() {
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
	 * @param array $attachment_ids Array of attachment IDs.
	 * @return array Filtered array with excluded items removed.
	 */
	public function filter_excluded( $attachment_ids ) {
		if ( ! is_array( $attachment_ids ) || empty( $attachment_ids ) ) {
			return array();
		}

		$exclusions = $this->get_all_exclusions();

		return array_filter(
			$attachment_ids,
			function ( $attachment_id ) use ( $exclusions ) {
				return ! isset( $exclusions[ $attachment_id ] );
			}
		);
	}
}
