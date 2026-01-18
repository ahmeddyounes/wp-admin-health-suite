<?php
/**
 * Elementor Integration Class
 *
 * Provides Elementor-specific optimizations and media reference detection.
 * Only loads when Elementor is active.
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
 * Elementor Integration class for Elementor-specific optimizations.
 *
 * @since 1.0.0
 */
class Elementor extends AbstractIntegration implements MediaAwareIntegrationInterface {

	/**
	 * Batch size for processing operations.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Elementor meta keys that store builder data and related settings.
	 *
	 * @var array<string>
	 */
	const ELEMENTOR_META_KEYS = array(
		'_elementor_data',
		'_elementor_draft',
		'_elementor_page_settings',
	);

	/**
	 * Minimum supported Elementor version.
	 *
	 * @var string
	 */
	const MIN_ELEMENTOR_VERSION = '3.0.0';

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
		return 'elementor';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'Elementor';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_min_version(): string {
		return self::MIN_ELEMENTOR_VERSION;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_current_version(): ?string {
		if ( ! $this->is_available() ) {
			return null;
		}

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			return ELEMENTOR_VERSION;
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_capabilities(): array {
		return array(
			'media_detection',
			'database_cleanup',
			'performance_insights',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function register_hooks(): void {
		// Hook into media scanner to detect Elementor image usage.
		$this->add_filter( 'wpha_media_is_attachment_used', array( $this, 'check_elementor_image_usage' ), 10, 2 );
	}

	/**
	 * Check if Elementor is active.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use is_available() instead.
	 *
	 * @return bool True if Elementor is active.
	 */
	public static function is_active(): bool {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Get Elementor-specific database cleanup opportunities.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int> Array of cleanup data.
	 */
	public function get_cleanup_data(): array {
		return array(
			'elementor_css_cache'     => $this->count_elementor_css_cache(),
			'elementor_orphaned_meta' => $this->count_orphaned_elementor_meta(),
		);
	}

	/**
	 * Count Elementor CSS cache files.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of CSS cache files.
	 */
	public function count_elementor_css_cache(): int {
		// If using external object cache, we can't count post meta easily.
		if ( wp_using_ext_object_cache() ) {
			return 0;
		}

		$prefix = $this->connection->get_prefix();

		$count = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(*) FROM {$prefix}postmeta
				WHERE meta_key = %s",
				'_elementor_css'
			)
		);

		return absint( $count );
	}

	/**
	 * Count orphaned Elementor meta data.
	 *
	 * Finds Elementor meta where the parent post no longer exists.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of orphaned meta rows.
	 */
	public function count_orphaned_elementor_meta(): int {
		$prefix = $this->connection->get_prefix();

		$meta_keys_placeholders = implode( ',', array_fill( 0, count( self::ELEMENTOR_META_KEYS ), '%s' ) );

		$query = "SELECT COUNT(*)
			FROM {$prefix}postmeta pm
			LEFT JOIN {$prefix}posts p ON pm.post_id = p.ID
			WHERE p.ID IS NULL
			AND pm.meta_key IN ($meta_keys_placeholders)";

		$count = $this->connection->get_var(
			$this->connection->prepare(
				$query,
				...self::ELEMENTOR_META_KEYS
			)
		);

		return absint( $count );
	}

	/**
	 * Clean Elementor CSS cache.
	 *
	 * @since 1.0.0
	 *
	 * @return array{deleted: int, bytes_freed: int} Array with 'deleted' count and 'bytes_freed' estimate.
	 */
	public function clean_elementor_css_cache(): array {
		$prefix = $this->connection->get_prefix();

		// Estimate size before deletion.
		$size_query = $this->connection->prepare(
			"SELECT SUM(LENGTH(meta_key) + LENGTH(meta_value)) as size
			FROM {$prefix}postmeta
			WHERE meta_key = %s",
			'_elementor_css'
		);
		$bytes_freed = absint( $this->connection->get_var( $size_query ) );

		// Delete CSS cache meta.
		$deleted = $this->connection->query(
			$this->connection->prepare(
				"DELETE FROM {$prefix}postmeta
				WHERE meta_key = %s",
				'_elementor_css'
			)
		);

		// Log to scan history.
		$this->log_cleanup(
			'elementor_css_cache_cleanup',
			absint( $deleted ),
			absint( $deleted ),
			$bytes_freed
		);

		// Trigger Elementor to regenerate CSS if available.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return array(
			'deleted'     => absint( $deleted ),
			'bytes_freed' => $bytes_freed,
		);
	}

	/**
	 * Clean orphaned Elementor meta data.
	 *
	 * @since 1.0.0
	 *
	 * @return array{deleted: int, bytes_freed: int} Array with 'deleted' count and 'bytes_freed' estimate.
	 */
	public function clean_orphaned_elementor_meta(): array {
		$prefix = $this->connection->get_prefix();

		$meta_keys_placeholders = implode( ',', array_fill( 0, count( self::ELEMENTOR_META_KEYS ), '%s' ) );

		// Get orphaned meta IDs in batches.
		$query = "SELECT pm.meta_id
			FROM {$prefix}postmeta pm
			LEFT JOIN {$prefix}posts p ON pm.post_id = p.ID
			WHERE p.ID IS NULL
			AND pm.meta_key IN ($meta_keys_placeholders)
			LIMIT " . self::BATCH_SIZE;

		$orphaned_ids = $this->connection->get_col(
			$this->connection->prepare(
				$query,
				...self::ELEMENTOR_META_KEYS
			)
		);

		if ( empty( $orphaned_ids ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		// Estimate size.
		$placeholders = implode( ',', array_fill( 0, count( $orphaned_ids ), '%d' ) );
		$size_query   = "SELECT SUM(LENGTH(meta_key) + LENGTH(meta_value)) as size
			FROM {$prefix}postmeta
			WHERE meta_id IN ($placeholders)";
		$bytes_freed  = absint( $this->connection->get_var( $this->connection->prepare( $size_query, ...$orphaned_ids ) ) );

		// Delete orphaned meta.
		$delete_query = "DELETE FROM {$prefix}postmeta WHERE meta_id IN ($placeholders)";
		$deleted      = $this->connection->query( $this->connection->prepare( $delete_query, ...$orphaned_ids ) );

		// Log to scan history.
		$this->log_cleanup(
			'elementor_orphaned_meta_cleanup',
			count( $orphaned_ids ),
			absint( $deleted ),
			$bytes_freed
		);

		return array(
			'deleted'     => absint( $deleted ),
			'bytes_freed' => $bytes_freed,
		);
	}

	/**
	 * Check if an attachment is used in Elementor content.
	 *
	 * Parses Elementor builder data and page settings for media IDs, including
	 * dynamic-tag values that may embed JSON inside strings.
	 * Uses targeted search with LIKE patterns to avoid loading all Elementor data.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $is_used       Whether the attachment is used.
	 * @param int  $attachment_id The attachment ID.
	 * @return bool True if used in Elementor.
	 */
	public function check_elementor_image_usage( bool $is_used, int $attachment_id ): bool {
		if ( $is_used ) {
			return $is_used;
		}

		$prefix     = $this->connection->get_prefix();

		// Search for the attachment ID pattern in Elementor builder/page-settings data.
		$meta_keys_placeholders = implode( ',', array_fill( 0, count( self::ELEMENTOR_META_KEYS ), '%s' ) );

		$like_patterns     = $this->build_attachment_like_patterns_strict( $attachment_id );
		$like_placeholders = implode( ' OR ', array_fill( 0, count( $like_patterns ), 'pm.meta_value LIKE %s' ) );

		// Use LIKE pre-filtering to avoid loading all Elementor data for every attachment.
		// We still verify via parsing to prevent false positives.
		$query = "SELECT pm.meta_value
			FROM {$prefix}postmeta pm
			INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
			WHERE pm.meta_key IN ($meta_keys_placeholders)
			AND p.post_status NOT IN ('trash', 'auto-draft')
			AND (
				{$like_placeholders}
			)
			LIMIT %d";

		$results = $this->connection->get_col(
			$this->connection->prepare(
				$query,
				...array_merge(
					self::ELEMENTOR_META_KEYS,
					$like_patterns,
					array( 25 ) // Small batch to allow verification without missing true matches.
				)
			)
		);

		foreach ( $results as $meta_value ) {
			if ( $this->is_attachment_in_elementor_data( (string) $meta_value, $attachment_id ) ) {
				return true;
			}
		}

		// Fallback: Elementor dynamic tags can store JSON blobs as escaped strings (e.g. __dynamic__),
		// which won't match the strict LIKE patterns above. Do a targeted scan for those cases.
		if ( $this->is_attachment_in_elementor_dynamic_data( $attachment_id ) ) {
			return true;
		}

		return $is_used;
	}

	/**
	 * Check if an attachment ID is present in Elementor JSON data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $elementor_data JSON string containing Elementor data.
	 * @param int    $attachment_id  Attachment ID to search for.
	 * @return bool True if attachment is found in the data.
	 */
	private function is_attachment_in_elementor_data( string $elementor_data, int $attachment_id ): bool {
		// Handle both JSON string and serialized array formats.
		$data = json_decode( $elementor_data, true );

		// If JSON decode failed, try unserialize (older Elementor versions).
		if ( null === $data ) {
			$data = maybe_unserialize( $elementor_data );
		}

		// If still not an array, bail.
		if ( ! is_array( $data ) ) {
			return false;
		}

		// Recursively search for attachment ID in the data structure.
		return $this->search_elementor_structure( $data, $attachment_id );
	}

	/**
	 * Recursively search Elementor data structure for attachment ID.
	 *
	 * Searches for:
	 * - Direct image ID references
	 * - Background image IDs
	 * - Gallery widget images
	 * - Image widget settings
	 * - Carousel widgets
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $data          Elementor data array.
	 * @param int          $attachment_id Attachment ID to search for.
	 * @return bool True if attachment is found.
	 */
	private function search_elementor_structure( array $data, int $attachment_id ): bool {
		foreach ( $data as $key => $value ) {
			// Check for direct ID match in various Elementor fields.
			if ( in_array( $key, array( 'id', 'image_id', 'background_image', 'thumbnail_id', 'attachment_id', 'media_id' ), true ) ) {
				if ( absint( $value ) === absint( $attachment_id ) ) {
					return true;
				}

				// Handle ID stored in array format.
				if ( is_array( $value ) && isset( $value['id'] ) && absint( $value['id'] ) === absint( $attachment_id ) ) {
					return true;
				}
			}

			// Check image settings (used by image widgets).
			if ( 'image' === $key && is_array( $value ) ) {
				if ( isset( $value['id'] ) && absint( $value['id'] ) === absint( $attachment_id ) ) {
					return true;
				}
			}

			// Check background settings.
			if ( 'background_image' === $key && is_array( $value ) ) {
				if ( isset( $value['id'] ) && absint( $value['id'] ) === absint( $attachment_id ) ) {
					return true;
				}
			}

			// Check gallery widget images (array of image objects).
			if ( 'gallery' === $key && is_array( $value ) ) {
				foreach ( $value as $gallery_item ) {
					if ( is_array( $gallery_item ) && isset( $gallery_item['id'] ) && absint( $gallery_item['id'] ) === absint( $attachment_id ) ) {
						return true;
					}
				}
			}

			// Check carousel/slider widgets.
			if ( 'slides' === $key && is_array( $value ) ) {
				foreach ( $value as $slide ) {
					if ( is_array( $slide ) ) {
						// Check slide background.
						if ( isset( $slide['background_image'] ) && is_array( $slide['background_image'] ) ) {
							if ( isset( $slide['background_image']['id'] ) && absint( $slide['background_image']['id'] ) === absint( $attachment_id ) ) {
								return true;
							}
						}
						// Check slide image.
						if ( isset( $slide['image'] ) && is_array( $slide['image'] ) ) {
							if ( isset( $slide['image']['id'] ) && absint( $slide['image']['id'] ) === absint( $attachment_id ) ) {
								return true;
							}
						}
					}
				}
			}

			// Recursively search nested arrays.
			if ( is_array( $value ) ) {
				if ( $this->search_elementor_structure( $value, $attachment_id ) ) {
					return true;
				}
			}

			// Elementor dynamic fields can store JSON/serialized data inside strings (e.g. __dynamic__).
			if ( is_string( $value ) ) {
				$decoded = $this->maybe_decode_nested_string( $value );
				if ( is_array( $decoded ) && $this->search_elementor_structure( $decoded, $attachment_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get Elementor performance insights.
	 *
	 * @since 1.0.0
	 *
	 * @return array<array{type: string, category: string, title: string, description: string, action: string, severity: string}> Array of performance insights.
	 */
	public function get_performance_insights(): array {
		$insights = array();

		// Check for CSS cache.
		$css_cache = $this->count_elementor_css_cache();
		if ( $css_cache > 500 ) {
			$insights[] = array(
				'type'        => 'info',
				'category'    => 'database',
				'title'       => 'Elementor CSS Cache',
				'description' => sprintf(
					/* translators: %d: Number of CSS cache entries */
					__( 'Found %d Elementor CSS cache entries. Clearing and regenerating may improve performance.', 'wp-admin-health-suite' ),
					$css_cache
				),
				'action'      => 'clean_elementor_css_cache',
				'severity'    => 'low',
			);
		}

		// Check for orphaned Elementor meta.
		$orphaned_meta = $this->count_orphaned_elementor_meta();
		if ( $orphaned_meta > 0 ) {
			$insights[] = array(
				'type'        => 'warning',
				'category'    => 'database',
				'title'       => 'Orphaned Elementor Meta',
				'description' => sprintf(
					/* translators: %d: Number of orphaned meta entries */
					__( 'Found %d orphaned Elementor meta entries. These can be safely removed.', 'wp-admin-health-suite' ),
					$orphaned_meta
				),
				'action'      => 'clean_orphaned_elementor_meta',
				'severity'    => 'medium',
			);
		}

		return $insights;
	}

	/**
	 * Get list of posts using Elementor.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Maximum number of posts to return.
	 * @return array<int> Array of post IDs using Elementor.
	 */
	public function get_elementor_posts( int $limit = 100 ): array {
		$prefix = $this->connection->get_prefix();

		$results = $this->connection->get_col(
			$this->connection->prepare(
				"SELECT DISTINCT pm.post_id
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE pm.meta_key = %s
				AND p.post_status NOT IN ('trash', 'auto-draft')
				ORDER BY p.post_modified DESC
				LIMIT %d",
				'_elementor_data',
				$limit
			)
		);

		return array_map( 'absint', $results );
	}

	/**
	 * Count posts using Elementor.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of posts using Elementor.
	 */
	public function count_elementor_posts(): int {
		$prefix = $this->connection->get_prefix();

		$count = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(DISTINCT pm.post_id)
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE pm.meta_key = %s
				AND p.post_status NOT IN ('trash', 'auto-draft')",
				'_elementor_data'
			)
		);

		return absint( $count );
	}

	/**
	 * Get Elementor version.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use get_current_version() instead.
	 *
	 * @return string Elementor version or empty string if not active.
	 */
	public function get_elementor_version(): string {
		return $this->get_current_version() ?? '';
	}

	/**
	 * Clear Elementor cache programmatically.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_elementor_cache(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		try {
			// Clear CSS cache via Elementor's files manager.
			\Elementor\Plugin::$instance->files_manager->clear_cache();

			// Also delete cached CSS meta to ensure complete cache clear.
			delete_post_meta_by_key( '_elementor_css' );

			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Check if an attachment is used by Elementor.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return bool True if the attachment is used in Elementor content.
	 */
	public function is_attachment_used( int $attachment_id ): bool {
		return $this->check_elementor_image_usage( false, $attachment_id );
	}

	/**
	 * Get all attachment IDs used in Elementor content.
	 *
	 * Scans Elementor page/post data for image references using batch processing.
	 *
	 * @since 1.1.0
	 *
	 * @param int $batch_size Maximum rows to process per batch. Default 50.
	 * @return array<int> Array of attachment IDs.
	 */
	public function get_used_attachments( int $batch_size = 50 ): array {
		$prefix         = $this->connection->get_prefix();
		$attachment_ids = array();
		$offset         = 0;
		$max_batches    = 200; // Safety limit for very large sites.
		$batches        = 0;
		$results_count  = 0;

		$meta_keys_placeholders = implode( ',', array_fill( 0, count( self::ELEMENTOR_META_KEYS ), '%s' ) );

		do {
			// Get Elementor data in batches.
			$query = "SELECT pm.meta_value
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE pm.meta_key IN ($meta_keys_placeholders)
				AND p.post_status NOT IN ('trash', 'auto-draft')
				ORDER BY pm.meta_id
				LIMIT %d OFFSET %d";

			$results = $this->connection->get_col(
				$this->connection->prepare(
					$query,
					...array_merge( self::ELEMENTOR_META_KEYS, array( $batch_size, $offset ) )
				)
			);

			if ( empty( $results ) ) {
				break;
			}

			foreach ( $results as $meta_value ) {
				$ids            = $this->extract_attachment_ids_from_elementor_data( $meta_value );
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
	 * Get usage locations for a specific attachment in Elementor.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $limit         Maximum number of results. Default 100.
	 * @return array<array{post_id: int, post_title: string, context: string}> Array of usage locations.
	 */
	public function get_attachment_usage( int $attachment_id, int $limit = 100 ): array {
		$prefix     = $this->connection->get_prefix();
		$usages     = array();

		$meta_keys_placeholders = implode( ',', array_fill( 0, count( self::ELEMENTOR_META_KEYS ), '%s' ) );
		$like_patterns          = $this->build_attachment_like_patterns_strict( $attachment_id );
		$like_placeholders      = implode( ' OR ', array_fill( 0, count( $like_patterns ), 'pm.meta_value LIKE %s' ) );

		// Search for posts containing this specific attachment ID in Elementor data.
		$query = "SELECT pm.post_id, p.post_title, pm.meta_value
			FROM {$prefix}postmeta pm
			INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
			WHERE pm.meta_key IN ($meta_keys_placeholders)
			AND p.post_status NOT IN ('trash', 'auto-draft')
			AND ({$like_placeholders})
			LIMIT %d";

		$results = $this->connection->get_results(
			$this->connection->prepare(
				$query,
				...array_merge(
					self::ELEMENTOR_META_KEYS,
					$like_patterns,
					array( $limit )
				)
			),
			'OBJECT'
		);

		foreach ( $results as $result ) {
			$contexts = $this->find_attachment_contexts_in_elementor_data( $result->meta_value, $attachment_id );

			if ( empty( $contexts ) ) {
				continue;
			}

			foreach ( $contexts as $context ) {
				$usages[] = array(
					'post_id'    => absint( $result->post_id ),
					'post_title' => $result->post_title,
					'context'    => $context,
				);
			}
		}

		// Add dynamic-tag usages (escaped JSON blobs stored in strings).
		if ( count( $usages ) < $limit ) {
			$dynamic_usages = $this->get_dynamic_attachment_usage( $attachment_id, $limit - count( $usages ) );
			if ( ! empty( $dynamic_usages ) ) {
				$usages = array_merge( $usages, $dynamic_usages );
			}
		}

		return $usages;
	}

	/**
	 * Extract all attachment IDs from Elementor JSON data.
	 *
	 * @since 1.1.0
	 *
	 * @param string $elementor_data JSON string containing Elementor data.
	 * @return array<int> Array of attachment IDs found.
	 */
	private function extract_attachment_ids_from_elementor_data( string $elementor_data ): array {
		// Handle both JSON string and serialized array formats.
		$data = json_decode( $elementor_data, true );

		// If JSON decode failed, try unserialize (older Elementor versions).
		if ( null === $data ) {
			$data = maybe_unserialize( $elementor_data );
		}

		// If still not an array, bail.
		if ( ! is_array( $data ) ) {
			return array();
		}

		return $this->collect_attachment_ids_from_structure( $data );
	}

	/**
	 * Recursively collect all attachment IDs from Elementor data structure.
	 *
	 * @since 1.1.0
	 *
	 * @param array<mixed> $data Elementor data array.
	 * @return array<int> Array of attachment IDs.
	 */
	private function collect_attachment_ids_from_structure( array $data ): array {
		$ids = array();

		foreach ( $data as $key => $value ) {
			// Check for direct ID match in various Elementor fields.
			if ( in_array( $key, array( 'id', 'image_id', 'background_image', 'thumbnail_id', 'attachment_id', 'media_id' ), true ) ) {
				if ( is_numeric( $value ) && absint( $value ) > 0 ) {
					$ids[] = absint( $value );
				}

				// Handle ID stored in array format.
				if ( is_array( $value ) && isset( $value['id'] ) && is_numeric( $value['id'] ) ) {
					$ids[] = absint( $value['id'] );
				}
			}

			// Check image settings (used by image widgets).
			if ( 'image' === $key && is_array( $value ) && isset( $value['id'] ) ) {
				$ids[] = absint( $value['id'] );
			}

			// Check background settings.
			if ( 'background_image' === $key && is_array( $value ) && isset( $value['id'] ) ) {
				$ids[] = absint( $value['id'] );
			}

			// Check gallery widget images.
			if ( 'gallery' === $key && is_array( $value ) ) {
				foreach ( $value as $gallery_item ) {
					if ( is_array( $gallery_item ) && isset( $gallery_item['id'] ) ) {
						$ids[] = absint( $gallery_item['id'] );
					}
				}
			}

			// Check carousel/slider widgets.
			if ( 'slides' === $key && is_array( $value ) ) {
				foreach ( $value as $slide ) {
					if ( is_array( $slide ) ) {
						if ( isset( $slide['background_image']['id'] ) ) {
							$ids[] = absint( $slide['background_image']['id'] );
						}
						if ( isset( $slide['image']['id'] ) ) {
							$ids[] = absint( $slide['image']['id'] );
						}
					}
				}
			}

			// Recursively search nested arrays.
			if ( is_array( $value ) ) {
				$ids = array_merge( $ids, $this->collect_attachment_ids_from_structure( $value ) );
			}

			// Elementor dynamic fields can store JSON/serialized data inside strings (e.g. __dynamic__).
			if ( is_string( $value ) ) {
				$decoded = $this->maybe_decode_nested_string( $value );
				if ( is_array( $decoded ) ) {
					$ids = array_merge( $ids, $this->collect_attachment_ids_from_structure( $decoded ) );
				}
			}
		}

		return $ids;
	}

	/**
	 * Find the contexts where an attachment is used in Elementor data.
	 *
	 * @since 1.1.0
	 *
	 * @param string $elementor_data JSON string containing Elementor data.
	 * @param int    $attachment_id  Attachment ID to search for.
	 * @return array<string> Array of context descriptions.
	 */
	private function find_attachment_contexts_in_elementor_data( string $elementor_data, int $attachment_id ): array {
		// Handle both JSON string and serialized array formats.
		$data = json_decode( $elementor_data, true );

		// If JSON decode failed, try unserialize (older Elementor versions).
		if ( null === $data ) {
			$data = maybe_unserialize( $elementor_data );
		}

		// If still not an array, bail.
		if ( ! is_array( $data ) ) {
			return array();
		}

		return $this->find_contexts_in_structure( $data, $attachment_id );
	}

	/**
	 * Recursively find contexts for an attachment in Elementor structure.
	 *
	 * @since 1.1.0
	 *
	 * @param array<mixed> $data          Elementor data array.
	 * @param int          $attachment_id Attachment ID to search for.
	 * @param string       $widget_type   Current widget type context.
	 * @return array<string> Array of context descriptions.
	 */
	private function find_contexts_in_structure( array $data, int $attachment_id, string $widget_type = '' ): array {
		$contexts = array();

		// Track widget type if present.
		if ( isset( $data['widgetType'] ) ) {
			$widget_type = $data['widgetType'];
		} elseif ( isset( $data['elType'] ) && 'widget' !== $data['elType'] ) {
			$widget_type = $data['elType'];
		}

		foreach ( $data as $key => $value ) {
			// Check image widget.
			if ( 'image' === $key && is_array( $value ) && isset( $value['id'] ) && absint( $value['id'] ) === $attachment_id ) {
				$widget_label = $widget_type ? ucfirst( $widget_type ) : 'Image';
				$contexts[]   = sprintf( 'Elementor %s widget', $widget_label );
			}

			// Check background image.
			if ( 'background_image' === $key ) {
				$id_match = false;
				if ( is_array( $value ) && isset( $value['id'] ) && absint( $value['id'] ) === $attachment_id ) {
					$id_match = true;
				} elseif ( is_numeric( $value ) && absint( $value ) === $attachment_id ) {
					$id_match = true;
				}

				if ( $id_match ) {
					$contexts[] = 'Elementor background image';
				}
			}

			// Check gallery.
			if ( 'gallery' === $key && is_array( $value ) ) {
				foreach ( $value as $gallery_item ) {
					if ( is_array( $gallery_item ) && isset( $gallery_item['id'] ) && absint( $gallery_item['id'] ) === $attachment_id ) {
						$contexts[] = 'Elementor gallery';
						break;
					}
				}
			}

			// Check slides/carousel.
			if ( 'slides' === $key && is_array( $value ) ) {
				foreach ( $value as $slide ) {
					if ( is_array( $slide ) ) {
						if ( isset( $slide['background_image']['id'] ) && absint( $slide['background_image']['id'] ) === $attachment_id ) {
							$contexts[] = 'Elementor slider/carousel background';
						}
						if ( isset( $slide['image']['id'] ) && absint( $slide['image']['id'] ) === $attachment_id ) {
							$contexts[] = 'Elementor slider/carousel image';
						}
					}
				}
			}

			// Check direct ID references.
			if ( in_array( $key, array( 'id', 'image_id', 'thumbnail_id' ), true ) && is_numeric( $value ) && absint( $value ) === $attachment_id ) {
				if ( ! in_array( 'Elementor content', $contexts, true ) ) {
					$widget_label = $widget_type ? ucfirst( $widget_type ) : 'content';
					$contexts[]   = sprintf( 'Elementor %s', $widget_label );
				}
			}

			// Recursively search nested arrays.
			if ( is_array( $value ) ) {
				$contexts = array_merge( $contexts, $this->find_contexts_in_structure( $value, $attachment_id, $widget_type ) );
			}

			// Elementor dynamic fields can store JSON/serialized data inside strings (e.g. __dynamic__).
			if ( is_string( $value ) ) {
				$decoded = $this->maybe_decode_nested_string( $value );
				if ( is_array( $decoded ) ) {
					$contexts = array_merge( $contexts, $this->find_contexts_in_structure( $decoded, $attachment_id, $widget_type ) );
				}
			}
		}

		return array_unique( $contexts );
	}

	/**
	 * Build LIKE patterns to locate an attachment ID in Elementor meta blobs.
	 *
	 * Elementor data can be stored as JSON, serialized PHP arrays (page settings),
	 * and other formats depending on Elementor version and feature usage.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string> LIKE patterns.
	 */
	private function build_attachment_like_patterns_strict( int $attachment_id ): array {
		$id      = (string) $attachment_id;
		$escaped = $this->connection->esc_like( $id );

		$patterns = array();

		// JSON: "id":123, "id": 123, "id":"123", "id": "123" (with common JSON delimiters).
		foreach ( array( '', ' ' ) as $space ) {
			$patterns[] = '%"id":' . $space . $escaped . ',%';
			$patterns[] = '%"id":' . $space . $escaped . '}%';
			$patterns[] = '%"id":' . $space . $escaped . ']%';
			$patterns[] = '%"id":' . $space . '"' . $escaped . '",%';
			$patterns[] = '%"id":' . $space . '"' . $escaped . '"}%';
			$patterns[] = '%"id":' . $space . '"' . $escaped . '"]%';
		}

		// Serialized: s:2:"id";i:123; and s:2:"id";s:N:"123";
		$patterns[] = '%' . $this->connection->esc_like( 's:2:"id";i:' . $id . ';' ) . '%';
		$patterns[] = '%' . $this->connection->esc_like( 's:2:"id";s:' ) . '%' . $this->connection->esc_like( ':"' . $id . '";' ) . '%';

		return array_values( array_unique( $patterns ) );
	}

	/**
	 * Detect attachment usage in Elementor dynamic-tag data.
	 *
	 * Dynamic tags can embed JSON blobs inside string fields (escaped within the main JSON),
	 * which means the attachment ID may not match strict `"id":123` LIKE patterns.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if found.
	 */
	private function is_attachment_in_elementor_dynamic_data( int $attachment_id ): bool {
		$prefix                 = $this->connection->get_prefix();
		$meta_keys_placeholders = implode( ',', array_fill( 0, count( self::ELEMENTOR_META_KEYS ), '%s' ) );

		$dynamic_marker = '%' . $this->connection->esc_like( '__dynamic__' ) . '%';
		$id_str         = (string) $attachment_id;
		$escaped_id     = $this->connection->esc_like( $id_str );

		$id_patterns = array(
			'%:' . $escaped_id . ',%',
			'%:' . $escaped_id . '}%',
			'%:' . $escaped_id . ']%',
			'%: ' . $escaped_id . ',%',
			'%: ' . $escaped_id . '}%',
			'%: ' . $escaped_id . ']%',
		);
		$id_placeholders = implode( ' OR ', array_fill( 0, count( $id_patterns ), 'pm.meta_value LIKE %s' ) );

		$batch_size  = 50;
		$offset      = 0;
		$max_batches = 20;
		$batches     = 0;

		do {
			$query = "SELECT pm.meta_value
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE pm.meta_key IN ($meta_keys_placeholders)
				AND p.post_status NOT IN ('trash', 'auto-draft')
				AND pm.meta_value LIKE %s
				AND (
					{$id_placeholders}
				)
				ORDER BY pm.meta_id
				LIMIT %d OFFSET %d";

			$results = $this->connection->get_col(
				$this->connection->prepare(
					$query,
					...array_merge(
						self::ELEMENTOR_META_KEYS,
						array_merge( array( $dynamic_marker ), $id_patterns, array( $batch_size, $offset ) )
					)
				)
			);

			if ( empty( $results ) ) {
				break;
			}

			foreach ( $results as $meta_value ) {
				if ( $this->is_attachment_in_elementor_data( (string) $meta_value, $attachment_id ) ) {
					return true;
				}
			}

			$offset += $batch_size;
			++$batches;
		} while ( count( $results ) === $batch_size && $batches < $max_batches );

		if ( $batches >= $max_batches && ! empty( $results ) && count( $results ) === $batch_size ) {
			$this->log_batch_limit_warning( 'is_attachment_in_elementor_dynamic_data', $batches, $max_batches, $batch_size );
		}

		return false;
	}

	/**
	 * Get attachment usage locations from Elementor dynamic-tag data.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $limit         Maximum number of results.
	 * @return array<array{post_id: int, post_title: string, context: string}> Usage locations.
	 */
	private function get_dynamic_attachment_usage( int $attachment_id, int $limit ): array {
		if ( $limit <= 0 ) {
			return array();
		}

		$prefix                 = $this->connection->get_prefix();
		$meta_keys_placeholders = implode( ',', array_fill( 0, count( self::ELEMENTOR_META_KEYS ), '%s' ) );

		$dynamic_marker = '%' . $this->connection->esc_like( '__dynamic__' ) . '%';
		$id_str         = (string) $attachment_id;
		$escaped_id     = $this->connection->esc_like( $id_str );

		$id_patterns = array(
			'%:' . $escaped_id . ',%',
			'%:' . $escaped_id . '}%',
			'%:' . $escaped_id . ']%',
			'%: ' . $escaped_id . ',%',
			'%: ' . $escaped_id . '}%',
			'%: ' . $escaped_id . ']%',
		);
		$id_placeholders = implode( ' OR ', array_fill( 0, count( $id_patterns ), 'pm.meta_value LIKE %s' ) );

		$query = "SELECT pm.post_id, p.post_title, pm.meta_value
			FROM {$prefix}postmeta pm
			INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
			WHERE pm.meta_key IN ($meta_keys_placeholders)
			AND p.post_status NOT IN ('trash', 'auto-draft')
			AND pm.meta_value LIKE %s
			AND (
				{$id_placeholders}
			)
			ORDER BY p.post_modified DESC
			LIMIT %d";

		$results = $this->connection->get_results(
			$this->connection->prepare(
				$query,
				...array_merge(
					self::ELEMENTOR_META_KEYS,
					array_merge( array( $dynamic_marker ), $id_patterns, array( $limit ) )
				)
			),
			'OBJECT'
		);

		$usages = array();
		foreach ( $results as $result ) {
			$contexts = $this->find_attachment_contexts_in_elementor_data( $result->meta_value, $attachment_id );
			if ( empty( $contexts ) ) {
				continue;
			}

			foreach ( $contexts as $context ) {
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
	 * Attempt to decode nested JSON/serialized strings in Elementor data.
	 *
	 * Elementor dynamic fields (e.g. __dynamic__) can store JSON blobs as strings
	 * inside the main JSON structure.
	 *
	 * @since 1.1.0
	 *
	 * @param string $value Potentially encoded value.
	 * @return array<mixed>|null Decoded array, or null if not decodable.
	 */
	private function maybe_decode_nested_string( string $value ): ?array {
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return null;
		}

		// Prevent excessive work on very large strings.
		if ( strlen( $trimmed ) > 200000 ) {
			return null;
		}

		$first_char = $trimmed[0];
		if ( '{' === $first_char || '[' === $first_char ) {
			$decoded = json_decode( $trimmed, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		$decoded = maybe_unserialize( $trimmed );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		return null;
	}
}
