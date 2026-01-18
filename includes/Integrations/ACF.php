<?php
/**
 * Advanced Custom Fields (ACF) Integration Class
 *
 * Provides ACF-specific media reference detection for image, gallery, and file fields.
 * Handles repeater and flexible content fields with nested images.
 * Only loads when ACF is active.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Integrations;

use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\MediaAwareIntegrationInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * ACF Integration class for ACF-specific media detection.
 *
 * @since 1.0.0
 */
class ACF extends AbstractIntegration implements MediaAwareIntegrationInterface {

	/**
	 * ACF field types that store media attachments.
	 *
	 * @var array<string>
	 */
	const MEDIA_FIELD_TYPES = array(
		'image',
		'gallery',
		'file',
	);

	/**
	 * ACF field types that can contain nested fields.
	 *
	 * @var array<string>
	 */
	const NESTED_FIELD_TYPES = array(
		'repeater',
		'flexible_content',
		'group',
	);

	/**
	 * Minimum supported ACF version.
	 *
	 * @var string
	 */
	const MIN_ACF_VERSION = '5.0.0';

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param ConnectionInterface $connection Database connection.
	 * @param CacheInterface      $cache      Cache instance.
	 */
	public function __construct(
		ConnectionInterface $connection,
		CacheInterface $cache
	) {
		parent::__construct( $connection, $cache );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'acf';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'Advanced Custom Fields';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return class_exists( 'ACF' ) || function_exists( 'acf_get_field_groups' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_min_version(): string {
		return self::MIN_ACF_VERSION;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_current_version(): ?string {
		if ( ! $this->is_available() ) {
			return null;
		}

		if ( defined( 'ACF_VERSION' ) ) {
			return ACF_VERSION;
		}

		if ( defined( 'ACF_PRO_VERSION' ) ) {
			return ACF_PRO_VERSION;
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_capabilities(): array {
		return array(
			'media_detection',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function register_hooks(): void {
		// Hook into media scanner to detect ACF image usage.
		$this->add_filter( 'wpha_media_is_attachment_used', array( $this, 'check_acf_image_usage' ), 10, 2 );
	}

	/**
	 * Check if ACF is active.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use is_available() instead.
	 *
	 * @return bool True if ACF is active.
	 */
	public static function is_active(): bool {
		return class_exists( 'ACF' ) || function_exists( 'acf_get_field_groups' );
	}

	/**
	 * Get all ACF field groups.
	 *
	 * @since 1.0.0
	 *
	 * @return array<array{key: string, title: string}> Array of field groups.
	 */
	public function get_field_groups(): array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return array();
		}

		return acf_get_field_groups();
	}

	/**
	 * Get all media fields from ACF field groups.
	 *
	 * Parses field groups and returns all fields that can contain media.
	 * Handles nested fields in repeaters, flexible content, and groups.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string> Array of media field keys.
	 */
	public function get_media_field_keys(): array {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return array();
		}

		return $this->remember(
			'media_field_keys',
			function (): array {
				$media_fields = array();
				$field_groups = acf_get_field_groups();

				foreach ( $field_groups as $field_group ) {
					$fields = acf_get_fields( $field_group['key'] );

					if ( $fields ) {
						$media_fields = array_merge(
							$media_fields,
							$this->extract_media_fields( $fields )
						);
					}
				}

				return array_values( array_unique( $media_fields ) );
			},
			300
		);
	}

	/**
	 * Extract media fields from a field array, including nested fields.
	 *
	 * Recursively processes fields to find all media-type fields,
	 * including those nested in repeaters, flexible content, and groups.
	 *
	 * @since 1.0.0
	 *
	 * @param array<array{type?: string, key?: string, sub_fields?: array, layouts?: array}> $fields Array of ACF fields.
	 * @return array<string> Array of media field keys.
	 */
	private function extract_media_fields( array $fields ): array {
		$media_fields = array();

		foreach ( $fields as $field ) {
			// Check if this field is a media type.
			if ( isset( $field['type'] ) && in_array( $field['type'], self::MEDIA_FIELD_TYPES, true ) ) {
				$media_fields[] = $field['key'];
			}

			// Check for nested fields in repeater, flexible content, or group.
			if ( isset( $field['type'] ) && in_array( $field['type'], self::NESTED_FIELD_TYPES, true ) ) {
				$sub_fields = array();

				// Handle repeater and group fields.
				if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
					$sub_fields = $field['sub_fields'];
				}

				// Handle flexible content layouts.
				if ( 'flexible_content' === $field['type'] && isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
					foreach ( $field['layouts'] as $layout ) {
						if ( isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
							$sub_fields = array_merge( $sub_fields, $layout['sub_fields'] );
						}
					}
				}

				// Recursively process sub-fields.
				if ( ! empty( $sub_fields ) ) {
					$media_fields = array_merge(
						$media_fields,
						$this->extract_media_fields( $sub_fields )
					);
				}
			}
		}

		return $media_fields;
	}

	/**
	 * Check if an attachment is used in ACF fields.
	 *
	 * Searches ACF meta values for the attachment ID using targeted queries.
	 * Handles various ACF storage formats including arrays and serialized data.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $is_used       Whether the attachment is used.
	 * @param int  $attachment_id The attachment ID.
	 * @return bool True if used in ACF fields.
	 */
	public function check_acf_image_usage( bool $is_used, int $attachment_id ): bool {
		if ( $is_used ) {
			return $is_used;
		}

		$attachment_id = absint( $attachment_id );

		if ( $attachment_id <= 0 ) {
			return false;
		}

		$attachment_id_str = (string) $attachment_id;
		$postmeta_table    = $this->connection->get_postmeta_table();
		$posts_table       = $this->connection->get_posts_table();

		$media_field_keys = $this->get_media_field_keys();

		// If we can't determine media field keys, fall back to broad pattern matching.
		// This is less precise but avoids false negatives when ACF field definitions
		// aren't available (e.g., early bootstrapping or partial installs).
		if ( empty( $media_field_keys ) ) {
			return $this->check_acf_image_usage_legacy( $attachment_id );
		}

		$like_patterns     = $this->build_acf_attachment_like_patterns( $attachment_id );
		$like_placeholders = implode( ' OR ', array_fill( 0, count( $like_patterns ), 'pm.meta_value LIKE %s' ) );
		$in_placeholders   = implode( ',', array_fill( 0, count( $media_field_keys ), '%s' ) );

		$query = "SELECT 1
			FROM {$postmeta_table} pm
			INNER JOIN {$postmeta_table} fk ON fk.post_id = pm.post_id AND fk.meta_key = CONCAT('_', pm.meta_key)
			INNER JOIN {$posts_table} p ON pm.post_id = p.ID
			WHERE p.post_status NOT IN ('trash', 'auto-draft')
			AND fk.meta_value IN ({$in_placeholders})
			AND (
				pm.meta_value = %s
				OR {$like_placeholders}
			)
			LIMIT 1";

		$args  = array_merge( $media_field_keys, array( $attachment_id_str ), $like_patterns );
		$match = $this->connection->get_var(
			$this->connection->prepare(
				$query,
				...$args
			)
		);

		if ( $match ) {
			return true;
		}

		// Some installs may have missing `_field_name` reference rows. Fall back to
		// a broader scan to avoid false negatives.
		return $this->check_acf_image_usage_legacy( $attachment_id );
	}

	/**
	 * Legacy ACF attachment usage check (broad scan).
	 *
	 * Used as a fallback when field definitions or `_field_name` key references
	 * aren't available. This is less precise but avoids false negatives.
	 *
	 * @since 1.1.1
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if found.
	 */
	private function check_acf_image_usage_legacy( int $attachment_id ): bool {
		$postmeta_table = $this->connection->get_postmeta_table();
		$posts_table    = $this->connection->get_posts_table();

		$attachment_id_str = (string) absint( $attachment_id );
		$like_patterns     = $this->build_acf_attachment_like_patterns( $attachment_id );
		$like_placeholders = implode( ' OR ', array_fill( 0, count( $like_patterns ), 'pm.meta_value LIKE %s' ) );

		$query = "SELECT 1
			FROM {$postmeta_table} pm
			INNER JOIN {$posts_table} p ON pm.post_id = p.ID
			WHERE p.post_status NOT IN ('trash', 'auto-draft')
			AND (
				pm.meta_value = %s
				OR {$like_placeholders}
			)
			LIMIT 1";

		$args  = array_merge( array( $attachment_id_str ), $like_patterns );
		$match = $this->connection->get_var(
			$this->connection->prepare(
				$query,
				...$args
			)
		);

		return (bool) $match;
	}

	/**
	 * Build LIKE patterns to locate an attachment ID in ACF meta blobs.
	 *
	 * ACF can store attachment IDs as:
	 * - Plain ID strings/ints
	 * - Serialized arrays (gallery) and objects
	 * - JSON arrays/objects (newer usage patterns)
	 *
	 * Patterns are intentionally broad and should be paired with
	 * {@see is_attachment_in_acf_value()} for verification.
	 *
	 * @since 1.1.1
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string> LIKE patterns.
	 */
	private function build_acf_attachment_like_patterns( int $attachment_id ): array {
		$id      = (string) absint( $attachment_id );
		$escaped = $this->connection->esc_like( $id );

		$patterns = array();

		// Serialized:
		// - Integers: i:123;
		// - Strings: s:N:"123"; (we match the stable suffix: :"123";)
		$patterns[] = '%' . $this->connection->esc_like( 'i:' . $id . ';' ) . '%';
		$patterns[] = '%' . $this->connection->esc_like( ':"' . $id . '";' ) . '%';

		// JSON objects (common ACF object formats).
		foreach ( array( '', ' ' ) as $space ) {
			$patterns[] = '%"id":' . $space . $escaped . ',%';
			$patterns[] = '%"id":' . $space . $escaped . '}%';
			$patterns[] = '%"id":' . $space . $escaped . ']%';
			$patterns[] = '%"id":' . $space . '"' . $escaped . '",%';
			$patterns[] = '%"id":' . $space . '"' . $escaped . '"}%';
			$patterns[] = '%"id":' . $space . '"' . $escaped . '"]%';

			$patterns[] = '%"ID":' . $space . $escaped . ',%';
			$patterns[] = '%"ID":' . $space . $escaped . '}%';
			$patterns[] = '%"ID":' . $space . $escaped . ']%';
			$patterns[] = '%"ID":' . $space . '"' . $escaped . '",%';
			$patterns[] = '%"ID":' . $space . '"' . $escaped . '"}%';
			$patterns[] = '%"ID":' . $space . '"' . $escaped . '"]%';
		}

		// JSON arrays: [123,456], [123], ["123","456"].
		foreach ( array( '', ' ' ) as $space ) {
			$patterns[] = '%' . $this->connection->esc_like( '[' . $space . $id . ',' ) . '%';
			$patterns[] = '%' . $this->connection->esc_like( ',' . $space . $id . ',' ) . '%';
			$patterns[] = '%' . $this->connection->esc_like( ',' . $space . $id . ']' ) . '%';
			$patterns[] = '%' . $this->connection->esc_like( '[' . $space . $id . ']' ) . '%';

			$patterns[] = '%' . $this->connection->esc_like( '["' . $id . '",' ) . '%';
			$patterns[] = '%' . $this->connection->esc_like( ',"' . $id . '",' ) . '%';
			$patterns[] = '%' . $this->connection->esc_like( ',"' . $id . '"]' ) . '%';
			$patterns[] = '%' . $this->connection->esc_like( '["' . $id . '"]' ) . '%';
		}

		return array_values( array_unique( $patterns ) );
	}

	/**
	 * Check if an attachment ID is present in ACF field value.
	 *
	 * Handles various ACF storage formats:
	 * - Direct ID storage (integer or string)
	 * - Array storage (image object with 'id' or 'ID' key)
	 * - Gallery storage (array of IDs or image objects)
	 * - Serialized data (repeater/flexible content)
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $meta_value    The ACF field value.
	 * @param int   $attachment_id Attachment ID to search for.
	 * @return bool True if attachment is found in the value.
	 */
	private function is_attachment_in_acf_value( $meta_value, int $attachment_id ): bool {
		// Direct ID match (stored as string or int).
		if ( absint( $meta_value ) === absint( $attachment_id ) ) {
			return true;
		}

		// Try to unserialize if it's serialized data.
		$unserialized = maybe_unserialize( $meta_value );

		// Handle unserialized data recursively.
		if ( $unserialized !== $meta_value ) {
			return $this->search_acf_data_structure( $unserialized, $attachment_id );
		}

		// Try to decode JSON if it's JSON data (object or array).
		if ( is_string( $meta_value ) ) {
			$trimmed = ltrim( $meta_value );
			if ( '' !== $trimmed && ( '{' === $trimmed[0] || '[' === $trimmed[0] ) ) {
				$decoded = json_decode( $trimmed, true );
				if ( null !== $decoded ) {
					return $this->search_acf_data_structure( $decoded, $attachment_id );
				}
			}
		}

		return false;
	}

	/**
	 * Recursively search ACF data structure for attachment ID.
	 *
	 * Handles:
	 * - Simple ID values
	 * - Image objects with 'id' or 'ID' keys
	 * - Gallery arrays
	 * - Repeater field arrays
	 * - Flexible content layouts
	 * - Nested structures
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $data          ACF data to search.
	 * @param int   $attachment_id Attachment ID to search for.
	 * @return bool True if attachment is found.
	 */
	private function search_acf_data_structure( $data, int $attachment_id ): bool {
		// Direct ID match.
		if ( is_numeric( $data ) && absint( $data ) === absint( $attachment_id ) ) {
			return true;
		}

		if ( ! is_array( $data ) ) {
			return false;
		}

		// Check for ACF image object format (has 'id' or 'ID' key).
		if ( isset( $data['id'] ) && absint( $data['id'] ) === absint( $attachment_id ) ) {
			return true;
		}

		if ( isset( $data['ID'] ) && absint( $data['ID'] ) === absint( $attachment_id ) ) {
			return true;
		}

		// Recursively search array values.
		foreach ( $data as $value ) {
			if ( $this->search_acf_data_structure( $value, $attachment_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get ACF field statistics.
	 *
	 * Returns information about ACF field groups and field types.
	 *
	 * @since 1.0.0
	 *
	 * @return array{total_field_groups: int, total_media_fields: int, field_types: array<string, int>} Array of statistics.
	 */
	public function get_field_statistics(): array {
		if ( ! $this->is_available() ) {
			return array(
				'total_field_groups' => 0,
				'total_media_fields' => 0,
				'field_types'        => array(),
			);
		}

		$field_groups = $this->get_field_groups();
		$media_fields = $this->get_media_field_keys();
		$field_types  = array();

		foreach ( $field_groups as $field_group ) {
			if ( ! function_exists( 'acf_get_fields' ) ) {
				continue;
			}

			$fields = acf_get_fields( $field_group['key'] );
			if ( $fields ) {
				$this->count_field_types( $fields, $field_types );
			}
		}

		return array(
			'total_field_groups' => count( $field_groups ),
			'total_media_fields' => count( $media_fields ),
			'field_types'        => $field_types,
		);
	}

	/**
	 * Count field types recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param array<array{type?: string, sub_fields?: array, layouts?: array}> $fields     Array of fields to count.
	 * @param array<string, int>                                                $field_types Reference to field types array.
	 * @return void
	 */
	private function count_field_types( array $fields, array &$field_types ): void {
		foreach ( $fields as $field ) {
			if ( ! isset( $field['type'] ) ) {
				continue;
			}

			$type = $field['type'];

			if ( ! isset( $field_types[ $type ] ) ) {
				$field_types[ $type ] = 0;
			}

			++$field_types[ $type ];

			// Count nested fields.
			if ( in_array( $type, self::NESTED_FIELD_TYPES, true ) ) {
				$sub_fields = array();

				if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
					$sub_fields = $field['sub_fields'];
				}

				if ( 'flexible_content' === $type && isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
					foreach ( $field['layouts'] as $layout ) {
						if ( isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
							$sub_fields = array_merge( $sub_fields, $layout['sub_fields'] );
						}
					}
				}

				if ( ! empty( $sub_fields ) ) {
					$this->count_field_types( $sub_fields, $field_types );
				}
			}
		}
	}

	/**
	 * Get posts using ACF fields.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Maximum number of posts to return.
	 * @return array<int> Array of post IDs using ACF fields.
	 */
	public function get_posts_with_acf_fields( int $limit = 100 ): array {
		$prefix = $this->connection->get_prefix();

		$results = $this->connection->get_col(
			$this->connection->prepare(
				"SELECT DISTINCT pm.post_id
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE pm.meta_key LIKE %s
				AND p.post_status NOT IN ('trash', 'auto-draft')
				ORDER BY p.post_modified DESC
				LIMIT %d",
				$this->connection->esc_like( 'field_' ) . '%',
				$limit
			)
		);

		return array_map( 'absint', $results );
	}

	/**
	 * Count posts using ACF fields.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of posts using ACF fields.
	 */
	public function count_posts_with_acf_fields(): int {
		$prefix = $this->connection->get_prefix();

		$count = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(DISTINCT pm.post_id)
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE pm.meta_key LIKE %s
				AND p.post_status NOT IN ('trash', 'auto-draft')",
				$this->connection->esc_like( 'field_' ) . '%'
			)
		);

		return absint( $count );
	}

	/**
	 * Get ACF version.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use get_current_version() instead.
	 *
	 * @return string ACF version or empty string if not active.
	 */
	public function get_acf_version(): string {
		return $this->get_current_version() ?? '';
	}

	/**
	 * Detect potential false positives.
	 *
	 * Validates that detected media references are legitimate.
	 * Prevents false positives from numeric values that aren't attachment IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id The attachment ID to validate.
	 * @return bool True if the attachment ID is valid.
	 */
	private function validate_attachment_id( int $attachment_id ): bool {
		// Ensure it's a positive integer.
		$attachment_id = absint( $attachment_id );

		if ( $attachment_id <= 0 ) {
			return false;
		}

		// Verify the post exists and is an attachment.
		$post = get_post( $attachment_id );

		return $post && 'attachment' === $post->post_type;
	}

	/**
	 * Check if an attachment is used by ACF fields.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return bool True if the attachment is used in ACF fields.
	 */
	public function is_attachment_used( int $attachment_id ): bool {
		return $this->check_acf_image_usage( false, $attachment_id );
	}

	/**
	 * Get all attachment IDs used in ACF fields.
	 *
	 * Scans ACF field data for image/gallery/file references using batch processing.
	 *
	 * @since 1.1.0
	 *
	 * @param int $batch_size Maximum rows to process per batch. Default 1000.
	 * @return array<int> Array of attachment IDs.
	 */
	public function get_used_attachments( int $batch_size = 1000 ): array {
		$postmeta_table = $this->connection->get_postmeta_table();
		$posts_table    = $this->connection->get_posts_table();
		$attachment_ids = array();
		$offset         = 0;
		$max_batches    = 100; // Safety limit: max 100k rows total.
		$batches        = 0;
		$results_count  = 0;

		$media_field_keys = $this->get_media_field_keys();

		// If we can't identify media field keys, fall back to legacy broad scan.
		if ( empty( $media_field_keys ) ) {
			return $this->get_used_attachments_legacy( $batch_size );
		}

		$in_placeholders = implode( ',', array_fill( 0, count( $media_field_keys ), '%s' ) );

		do {
			$results = $this->connection->get_col(
				$this->connection->prepare(
					"SELECT pm.meta_value
					FROM {$postmeta_table} pm
					INNER JOIN {$postmeta_table} fk ON fk.post_id = pm.post_id AND fk.meta_key = CONCAT('_', pm.meta_key)
					INNER JOIN {$posts_table} p ON pm.post_id = p.ID
					WHERE p.post_status NOT IN ('trash', 'auto-draft')
					AND fk.meta_value IN ({$in_placeholders})
					ORDER BY pm.meta_id
					LIMIT %d OFFSET %d",
					...array_merge( $media_field_keys, array( $batch_size, $offset ) )
				)
			);

			if ( empty( $results ) ) {
				break;
			}

			foreach ( $results as $meta_value ) {
				$ids            = $this->extract_attachment_ids_from_acf_value( $meta_value );
				$attachment_ids = array_merge( $attachment_ids, $ids );
			}

			$offset += $batch_size;
			++$batches;
			$results_count = count( $results );

		} while ( $results_count === $batch_size && $batches < $max_batches );

		// Log warning if we hit the safety limit.
		if ( $batches >= $max_batches && $results_count === $batch_size ) {
			$this->log_batch_limit_warning( 'get_used_attachments', $batches, $max_batches, $batch_size );
		}

		return array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) );
	}

	/**
	 * Legacy implementation for scanning used attachments in ACF fields.
	 *
	 * Falls back to a broad scan across postmeta values when field definitions
	 * aren't available.
	 *
	 * @since 1.1.1
	 *
	 * @param int $batch_size Batch size.
	 * @return array<int> Attachment IDs.
	 */
	private function get_used_attachments_legacy( int $batch_size = 1000 ): array {
		$postmeta_table = $this->connection->get_postmeta_table();
		$posts_table    = $this->connection->get_posts_table();

		$attachment_ids = array();
		$offset         = 0;
		$max_batches    = 100; // Safety limit: max 100k rows total.
		$batches        = 0;
		$results_count  = 0;

		do {
			$results = $this->connection->get_col(
				$this->connection->prepare(
					"SELECT DISTINCT pm.meta_value
					FROM {$postmeta_table} pm
					INNER JOIN {$posts_table} p ON pm.post_id = p.ID
					WHERE p.post_status NOT IN ('trash', 'auto-draft')
					AND (
						pm.meta_value REGEXP %s
						OR pm.meta_value LIKE %s
						OR pm.meta_value LIKE %s
					)
					LIMIT %d OFFSET %d",
					'^[0-9]+$',
					'%"id";i:%',
					'%"id":%',
					$batch_size,
					$offset
				)
			);

			if ( empty( $results ) ) {
				break;
			}

			foreach ( $results as $meta_value ) {
				$ids            = $this->extract_attachment_ids_from_acf_value( $meta_value );
				$attachment_ids = array_merge( $attachment_ids, $ids );
			}

			$offset += $batch_size;
			++$batches;
			$results_count = count( $results );

		} while ( $results_count === $batch_size && $batches < $max_batches );

		// Log warning if we hit the safety limit.
		if ( $batches >= $max_batches && $results_count === $batch_size ) {
			$this->log_batch_limit_warning( 'get_used_attachments_legacy', $batches, $max_batches, $batch_size );
		}

		return array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) );
	}

	/**
	 * Get usage locations for a specific attachment in ACF fields.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $limit         Maximum number of results. Default 100.
	 * @return array<array{post_id: int, post_title: string, context: string}> Array of usage locations.
	 */
	public function get_attachment_usage( int $attachment_id, int $limit = 100 ): array {
		$postmeta_table    = $this->connection->get_postmeta_table();
		$posts_table       = $this->connection->get_posts_table();
		$usages            = array();
		$attachment_id_str = (string) $attachment_id;

		$media_field_keys = $this->get_media_field_keys();
		$like_patterns    = $this->build_acf_attachment_like_patterns( $attachment_id );

		// If we can't identify media field keys, fall back to the legacy broad query.
		if ( empty( $media_field_keys ) ) {
			$results = $this->get_attachment_usage_legacy_results( $attachment_id, $limit );
		} else {
			$in_placeholders   = implode( ',', array_fill( 0, count( $media_field_keys ), '%s' ) );
			$like_placeholders = implode( ' OR ', array_fill( 0, count( $like_patterns ), 'pm.meta_value LIKE %s' ) );

			// Pull more than the requested limit to account for possible false positives from LIKE patterns.
			$query_limit = min( $limit * 5, 500 );

			$results = $this->connection->get_results(
				$this->connection->prepare(
					"SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_title
					FROM {$postmeta_table} pm
					INNER JOIN {$postmeta_table} fk ON fk.post_id = pm.post_id AND fk.meta_key = CONCAT('_', pm.meta_key)
					INNER JOIN {$posts_table} p ON pm.post_id = p.ID
					WHERE p.post_status NOT IN ('trash', 'auto-draft')
					AND fk.meta_value IN ({$in_placeholders})
					AND (
						pm.meta_value = %s
						OR {$like_placeholders}
					)
					LIMIT %d",
					...array_merge(
						$media_field_keys,
						array( $attachment_id_str ),
						$like_patterns,
						array( $query_limit )
					)
				),
				'OBJECT'
			);
		}

		foreach ( $results as $result ) {
			if ( $this->is_attachment_in_acf_value( $result->meta_value, $attachment_id ) ) {
				$context = $this->get_acf_field_context( absint( $result->post_id ), $result->meta_key, $result->meta_value, $attachment_id );

				$usages[] = array(
					'post_id'    => absint( $result->post_id ),
					'post_title' => $result->post_title,
					'context'    => $context,
				);

				if ( count( $usages ) >= $limit ) {
					break;
				}
			}
		}

		return $usages;
	}

	/**
	 * Legacy results fetch for attachment usage in ACF fields (broad scan).
	 *
	 * @since 1.1.1
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $limit         Limit.
	 * @return array<int, object> Results (OBJECT rows).
	 */
	private function get_attachment_usage_legacy_results( int $attachment_id, int $limit ): array {
		$postmeta_table = $this->connection->get_postmeta_table();
		$posts_table    = $this->connection->get_posts_table();

		$attachment_id_str = (string) absint( $attachment_id );
		$like_patterns     = $this->build_acf_attachment_like_patterns( $attachment_id );
		$like_placeholders = implode( ' OR ', array_fill( 0, count( $like_patterns ), 'pm.meta_value LIKE %s' ) );

		return $this->connection->get_results(
			$this->connection->prepare(
				"SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_title
				FROM {$postmeta_table} pm
				INNER JOIN {$posts_table} p ON pm.post_id = p.ID
				WHERE p.post_status NOT IN ('trash', 'auto-draft')
				AND (
					pm.meta_value = %s
					OR {$like_placeholders}
				)
				LIMIT %d",
				...array_merge( array( $attachment_id_str ), $like_patterns, array( min( $limit * 5, 500 ) ) )
			),
			'OBJECT'
		);
	}

	/**
	 * Extract all attachment IDs from an ACF field value.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $meta_value The ACF field value.
	 * @return array<int> Array of attachment IDs found.
	 */
	private function extract_attachment_ids_from_acf_value( $meta_value ): array {
		$ids = array();

		// Direct ID match (stored as string or int).
		if ( is_numeric( $meta_value ) && absint( $meta_value ) > 0 ) {
			$ids[] = absint( $meta_value );
			return $ids;
		}

		// Try to unserialize if it's serialized data.
		$unserialized = maybe_unserialize( $meta_value );

		if ( $unserialized !== $meta_value ) {
			return $this->collect_ids_from_acf_structure( $unserialized );
		}

		// Try to decode JSON if it's JSON data (object or array).
		if ( is_string( $meta_value ) ) {
			$trimmed = ltrim( $meta_value );
			if ( '' !== $trimmed && ( '{' === $trimmed[0] || '[' === $trimmed[0] ) ) {
				$decoded = json_decode( $trimmed, true );
				if ( null !== $decoded ) {
					return $this->collect_ids_from_acf_structure( $decoded );
				}
			}
		}

		return $ids;
	}

	/**
	 * Recursively collect all attachment IDs from ACF data structure.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $data ACF data to search.
	 * @return array<int> Array of attachment IDs.
	 */
	private function collect_ids_from_acf_structure( $data ): array {
		$ids = array();

		// Direct ID.
		if ( is_numeric( $data ) && absint( $data ) > 0 ) {
			$ids[] = absint( $data );
			return $ids;
		}

		if ( ! is_array( $data ) ) {
			return $ids;
		}

		// Check for ACF image object format.
		if ( isset( $data['id'] ) && is_numeric( $data['id'] ) ) {
			$ids[] = absint( $data['id'] );
		}

		if ( isset( $data['ID'] ) && is_numeric( $data['ID'] ) ) {
			$ids[] = absint( $data['ID'] );
		}

		// Recursively search array values.
		foreach ( $data as $value ) {
			$ids = array_merge( $ids, $this->collect_ids_from_acf_structure( $value ) );
		}

		return $ids;
	}

	/**
	 * Get the context description for an ACF field usage.
	 *
	 * @since 1.1.0
	 *
	 * @param string $meta_key      The meta key.
	 * @param mixed  $meta_value    The meta value.
	 * @param int    $attachment_id The attachment ID.
	 * @return string Context description.
	 */
	private function get_acf_field_context( int $post_id, string $meta_key, $meta_value, int $attachment_id ): string {
		// Try to get field label from ACF.
		if ( function_exists( 'acf_get_field' ) ) {
			// ACF stores field key references with underscore prefix.
			$field_key_meta = get_post_meta( $post_id, '_' . ltrim( $meta_key, '_' ), true );

			if ( $field_key_meta && is_string( $field_key_meta ) && strpos( $field_key_meta, 'field_' ) === 0 ) {
				$field = acf_get_field( $field_key_meta );

				if ( $field && isset( $field['label'] ) ) {
					return sprintf( 'ACF %s field: %s', $field['type'] ?? 'custom', $field['label'] );
				}
			}
		}

		// Determine type from value structure.
		$unserialized = maybe_unserialize( $meta_value );

		if ( is_array( $unserialized ) ) {
			// Gallery field (array of images).
			if ( isset( $unserialized[0] ) && ( is_numeric( $unserialized[0] ) || ( is_array( $unserialized[0] ) && isset( $unserialized[0]['id'] ) ) ) ) {
				return 'ACF gallery field';
			}

			// Image object.
			if ( isset( $unserialized['id'] ) || isset( $unserialized['ID'] ) ) {
				return 'ACF image field';
			}

			// Nested in repeater/flexible content.
			return 'ACF repeater/flexible content field';
		}

		// Direct ID.
		if ( is_numeric( $unserialized ) && absint( $unserialized ) === $attachment_id ) {
			return 'ACF image/file field';
		}

		return 'ACF custom field';
	}
}
