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
	 * @param ConnectionInterface|null $connection Optional database connection.
	 * @param CacheInterface|null      $cache      Optional cache instance.
	 */
	public function __construct(
		?ConnectionInterface $connection = null,
		?CacheInterface $cache = null
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

		return $media_fields;
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

		$prefix            = $this->connection->get_prefix();
		$attachment_id_str = (string) $attachment_id;

		// Check for direct numeric match in meta_value (most common ACF storage).
		$direct_match = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT 1
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE p.post_status NOT IN ('trash', 'auto-draft')
				AND pm.meta_value = %s
				LIMIT 1",
				$attachment_id_str
			)
		);

		if ( $direct_match ) {
			return true;
		}

		// Escape the attachment ID for use in LIKE patterns.
		$escaped_id = $this->connection->esc_like( (string) $attachment_id );

		// Check for serialized PHP array format: "id";i:123; or s:2:"id";i:123;
		// The semicolon provides a natural word boundary in serialized data.
		$serialized_match = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT 1
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE p.post_status NOT IN ('trash', 'auto-draft')
				AND (
					pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
				)
				LIMIT 1",
				'%"id";i:' . $escaped_id . ';%',
				'%"ID";i:' . $escaped_id . ';%'
			)
		);

		if ( $serialized_match ) {
			return true;
		}

		// Check for JSON format: "id":123 followed by word boundary (, } ] or space).
		// This prevents false positives where ID 12 matches in "id":123.
		$json_match = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT 1
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE p.post_status NOT IN ('trash', 'auto-draft')
				AND (
					pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
				)
				LIMIT 1",
				'%"id":' . $escaped_id . ',%',
				'%"id":' . $escaped_id . '}%',
				'%"id":' . $escaped_id . ']%',
				'%"id": ' . $escaped_id . '%',
				'%"ID":' . $escaped_id . ',%',
				'%"ID":' . $escaped_id . '}%',
				'%"ID":' . $escaped_id . ']%',
				'%"ID": ' . $escaped_id . '%'
			)
		);

		return (bool) $json_match;
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

		// Try to decode JSON if it's JSON data.
		if ( is_string( $meta_value ) && '{' === substr( $meta_value, 0, 1 ) ) {
			$decoded = json_decode( $meta_value, true );
			if ( null !== $decoded ) {
				return $this->search_acf_data_structure( $decoded, $attachment_id );
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
		$prefix         = $this->connection->get_prefix();
		$attachment_ids = array();
		$offset         = 0;
		$max_batches    = 100; // Safety limit: max 100k rows total.
		$batches        = 0;

		do {
			// Query for numeric meta values that could be attachment IDs.
			// Also query for serialized/JSON data that might contain IDs.
			$results = $this->connection->get_col(
				$this->connection->prepare(
					"SELECT DISTINCT pm.meta_value
					FROM {$prefix}postmeta pm
					INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
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

		} while ( count( $results ) === $batch_size && $batches < $max_batches );

		// Log warning if we hit the safety limit.
		if ( $batches >= $max_batches && count( $results ) === $batch_size ) {
			$this->log_batch_limit_warning( 'get_used_attachments', $batches, $max_batches, $batch_size );
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
		$prefix            = $this->connection->get_prefix();
		$usages            = array();
		$attachment_id_str = (string) $attachment_id;
		$escaped_id        = $this->connection->esc_like( $attachment_id_str );

		// Search for meta values containing this specific attachment ID.
		// Uses word boundaries to prevent false positives (e.g., ID 12 matching 123).
		$results = $this->connection->get_results(
			$this->connection->prepare(
				"SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_title
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE p.post_status NOT IN ('trash', 'auto-draft')
				AND (
					pm.meta_value = %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
					OR pm.meta_value LIKE %s
				)
				LIMIT %d",
				$attachment_id_str,
				'%"id";i:' . $escaped_id . ';%',
				'%"ID";i:' . $escaped_id . ';%',
				'%"id":' . $escaped_id . ',%',
				'%"id":' . $escaped_id . '}%',
				'%"ID":' . $escaped_id . ',%',
				'%"ID":' . $escaped_id . '}%',
				$limit
			),
			'OBJECT'
		);

		foreach ( $results as $result ) {
			if ( $this->is_attachment_in_acf_value( $result->meta_value, $attachment_id ) ) {
				$context = $this->get_acf_field_context( $result->meta_key, $result->meta_value, $attachment_id );

				$usages[] = array(
					'post_id'    => absint( $result->post_id ),
					'post_title' => $result->post_title,
					'context'    => $context,
				);
			}
		}

		return $usages;
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

		// Try to decode JSON if it's JSON data.
		if ( is_string( $meta_value ) && '{' === substr( $meta_value, 0, 1 ) ) {
			$decoded = json_decode( $meta_value, true );
			if ( null !== $decoded ) {
				return $this->collect_ids_from_acf_structure( $decoded );
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
	private function get_acf_field_context( string $meta_key, $meta_value, int $attachment_id ): string {
		// Try to get field label from ACF.
		if ( function_exists( 'acf_get_field' ) ) {
			// ACF stores field key references with underscore prefix.
			$field_key_meta = get_metadata( 'post', 0, '_' . ltrim( $meta_key, '_' ), true );

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
