<?php
/**
 * WooCommerce Integration Class
 *
 * Provides WooCommerce-specific optimizations and health checks.
 * Only loads when WooCommerce is active.
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
 * WooCommerce Integration class for WooCommerce-specific optimizations.
 *
 * @since 1.0.0
 */
class WooCommerce extends AbstractIntegration implements MediaAwareIntegrationInterface {

	/**
	 * Batch size for processing operations.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Slow query threshold for WooCommerce queries (ms).
	 *
	 * @var float
	 */
	const WC_SLOW_QUERY_THRESHOLD = 100.0;

	/**
	 * Minimum supported WooCommerce version.
	 *
	 * @var string
	 */
	const MIN_WC_VERSION = '5.0.0';

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
		return 'woocommerce';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'WooCommerce';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_min_version(): string {
		return self::MIN_WC_VERSION;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_current_version(): ?string {
		if ( ! $this->is_available() ) {
			return null;
		}

		if ( defined( 'WC_VERSION' ) ) {
			return WC_VERSION;
		}

		// Fallback for edge cases where WC_VERSION is not defined.
		if ( function_exists( 'WC' ) ) {
			$wc = WC();
			if ( is_object( $wc ) && isset( $wc->version ) ) {
				return (string) $wc->version;
			}
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
		// Hook into media scanner to protect product images.
		$this->add_filter( 'wpha_media_is_attachment_used', array( $this, 'check_product_image_usage' ), 10, 2 );

		// Hook into performance monitor for WooCommerce-specific queries.
		$this->add_filter( 'wpha_slow_query_threshold', array( $this, 'adjust_slow_query_threshold' ), 10, 2 );
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use is_available() instead.
	 *
	 * @return bool True if WooCommerce is active.
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get WooCommerce-specific database cleanup opportunities.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int> Array of cleanup data.
	 */
	public function get_cleanup_data(): array {
		return array(
			'expired_sessions'        => $this->count_expired_sessions(),
			'orphaned_variation_meta' => $this->count_orphaned_variation_meta(),
			'wc_transients'           => $this->count_wc_transients(),
		);
	}

	/**
	 * Count expired WooCommerce sessions.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of expired sessions.
	 */
	public function count_expired_sessions(): int {
		$table_name = $this->connection->get_prefix() . 'woocommerce_sessions';

		// Check if table exists.
		if ( ! $this->connection->table_exists( $table_name ) ) {
			return 0;
		}

		$count = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE session_expiry < %d",
				time()
			)
		);

		return absint( $count );
	}

	/**
	 * Clean expired WooCommerce sessions.
	 *
	 * @since 1.0.0
	 *
	 * @return array{deleted: int, bytes_freed: int} Array with 'deleted' count and 'bytes_freed' estimate.
	 */
	public function clean_expired_sessions(): array {
		$table_name = $this->connection->get_prefix() . 'woocommerce_sessions';

		// Check if table exists.
		if ( ! $this->connection->table_exists( $table_name ) ) {
			return array(
				'deleted'     => 0,
				'bytes_freed' => 0,
			);
		}

		// Estimate size before deletion.
		$size_query = $this->connection->prepare(
			"SELECT SUM(LENGTH(session_key) + LENGTH(session_value)) as size
			FROM {$table_name}
			WHERE session_expiry < %d",
			time()
		);
		$bytes_freed = absint( $this->connection->get_var( $size_query ) );

		// Delete expired sessions.
		$deleted = $this->connection->query(
			$this->connection->prepare(
				"DELETE FROM {$table_name} WHERE session_expiry < %d",
				time()
			)
		);

		// Log to scan history.
		$this->log_cleanup(
			'woocommerce_sessions_cleanup',
			absint( $deleted ),
			absint( $deleted ),
			$bytes_freed
		);

		return array(
			'deleted'     => absint( $deleted ),
			'bytes_freed' => $bytes_freed,
		);
	}

	/**
	 * Count orphaned product variation meta.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of orphaned meta rows.
	 */
	public function count_orphaned_variation_meta(): int {
		$prefix = $this->connection->get_prefix();

		$count = $this->connection->get_var(
			"SELECT COUNT(*)
			FROM {$prefix}postmeta pm
			LEFT JOIN {$prefix}posts p ON pm.post_id = p.ID
			WHERE p.ID IS NULL
			AND pm.post_id IN (
				SELECT DISTINCT post_id
				FROM {$prefix}postmeta
				WHERE meta_key = '_product_attributes'
			)"
		);

		return absint( $count );
	}

	/**
	 * Clean orphaned product variation meta.
	 *
	 * @since 1.0.0
	 *
	 * @return array{deleted: int, bytes_freed: int} Array with 'deleted' count and 'bytes_freed' estimate.
	 */
	public function clean_orphaned_variation_meta(): array {
		$prefix = $this->connection->get_prefix();

		// Get orphaned meta IDs in batches.
		$orphaned_ids = $this->connection->get_col(
			"SELECT pm.meta_id
			FROM {$prefix}postmeta pm
			LEFT JOIN {$prefix}posts p ON pm.post_id = p.ID
			WHERE p.ID IS NULL
			AND pm.post_id IN (
				SELECT DISTINCT post_id
				FROM {$prefix}postmeta
				WHERE meta_key = '_product_attributes'
			)
			LIMIT " . self::BATCH_SIZE
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
			'woocommerce_orphaned_meta_cleanup',
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
	 * Count WooCommerce transients.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of WooCommerce transients.
	 */
	public function count_wc_transients(): int {
		// If using external object cache, we can't count transients.
		if ( wp_using_ext_object_cache() ) {
			return 0;
		}

		$prefix = $this->connection->get_prefix();

		$count = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(*) FROM {$prefix}options
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$this->connection->esc_like( '_transient_wc_' ) . '%',
				$this->connection->esc_like( '_site_transient_wc_' ) . '%'
			)
		);

		// Divide by 2 because each transient has a value and a timeout option.
		return intval( absint( $count ) / 2 );
	}

	/**
	 * Get WooCommerce transient names.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $expired_only Whether to return only expired transients.
	 * @return array<string> Array of transient names.
	 */
	public function get_wc_transients( bool $expired_only = false ): array {
		// If using external object cache, we can't get transients.
		if ( wp_using_ext_object_cache() ) {
			return array();
		}

		$prefix = $this->connection->get_prefix();

		if ( $expired_only ) {
			// Get expired WooCommerce transients.
			$query = $this->connection->prepare(
				"SELECT option_name FROM {$prefix}options
				WHERE (option_name LIKE %s OR option_name LIKE %s)
				AND option_value < %d
				ORDER BY option_name ASC",
				$this->connection->esc_like( '_transient_timeout_wc_' ) . '%',
				$this->connection->esc_like( '_site_transient_timeout_wc_' ) . '%',
				time()
			);
		} else {
			// Get all WooCommerce transients.
			$query = $this->connection->prepare(
				"SELECT option_name FROM {$prefix}options
				WHERE option_name LIKE %s
				OR option_name LIKE %s
				ORDER BY option_name ASC",
				$this->connection->esc_like( '_transient_timeout_wc_' ) . '%',
				$this->connection->esc_like( '_site_transient_timeout_wc_' ) . '%'
			);
		}

		$results = $this->connection->get_col( $query );

		if ( empty( $results ) ) {
			return array();
		}

		$transients = array();

		foreach ( $results as $option_name ) {
			// Extract the transient name from the timeout option name.
			$transient_name = str_replace(
				array( '_transient_timeout_', '_site_transient_timeout_' ),
				'',
				$option_name
			);

			$transients[] = $transient_name;
		}

		return $transients;
	}

	/**
	 * Check if an attachment is used in WooCommerce product galleries.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $is_used       Whether the attachment is used.
	 * @param int  $attachment_id The attachment ID.
	 * @return bool True if used in WooCommerce.
	 */
	public function check_product_image_usage( bool $is_used, int $attachment_id ): bool {
		if ( $is_used ) {
			return $is_used;
		}

		$prefix = $this->connection->get_prefix();

		// Check if it's a product featured image.
		$product_thumbnail = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(*) FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE p.post_type = 'product'
				AND pm.meta_key = '_thumbnail_id'
				AND pm.meta_value = %d",
				$attachment_id
			)
		);

		if ( $product_thumbnail > 0 ) {
			return true;
		}

		// Check if it's in a product gallery using FIND_IN_SET for accurate matching.
		// FIND_IN_SET properly handles comma-separated lists without false positives.
		$gallery_check = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(*) FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE p.post_type = 'product'
				AND pm.meta_key = '_product_image_gallery'
				AND FIND_IN_SET(%d, pm.meta_value) > 0",
				$attachment_id
			)
		);

		if ( $gallery_check > 0 ) {
			return true;
		}

		// Check if it's a variation image.
		$variation_check = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(*) FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE p.post_type = 'product_variation'
				AND pm.meta_key = '_thumbnail_id'
				AND pm.meta_value = %d",
				$attachment_id
			)
		);

		if ( $variation_check > 0 ) {
			return true;
		}

		// Check if it's used as a product category thumbnail (term meta).
		if ( $this->is_product_category_thumbnail( $attachment_id ) ) {
			return true;
		}

		return $is_used;
	}

	/**
	 * Check whether an attachment is used as a WooCommerce product category thumbnail.
	 *
	 * WooCommerce stores product category thumbnails in term meta under the key
	 * "thumbnail_id". Very old WooCommerce installs may still have a legacy
	 * "woocommerce_termmeta" table.
	 *
	 * @since 1.7.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if used as a product category thumbnail.
	 */
	private function is_product_category_thumbnail( int $attachment_id ): bool {
		$prefix              = $this->connection->get_prefix();
		$term_taxonomy_table = $prefix . 'term_taxonomy';
		$termmeta_table      = $this->connection->get_termmeta_table();

		// Prefer core termmeta table.
		if ( $this->connection->table_exists( $termmeta_table ) ) {
			$query = $this->connection->prepare(
				"SELECT COUNT(*) FROM {$termmeta_table} tm
				INNER JOIN {$term_taxonomy_table} tt ON tm.term_id = tt.term_id
				WHERE tt.taxonomy = %s
				AND tm.meta_key = %s
				AND tm.meta_value = %d",
				'product_cat',
				'thumbnail_id',
				$attachment_id
			);

			if ( null !== $query && $this->connection->get_var( $query ) > 0 ) {
				return true;
			}
		}

		// Legacy WooCommerce term meta table (pre WordPress termmeta).
		$legacy_termmeta_table = $prefix . 'woocommerce_termmeta';
		if ( $this->connection->table_exists( $legacy_termmeta_table ) ) {
			$query = $this->connection->prepare(
				"SELECT COUNT(*) FROM {$legacy_termmeta_table} tm
				INNER JOIN {$term_taxonomy_table} tt ON tm.woocommerce_term_id = tt.term_id
				WHERE tt.taxonomy = %s
				AND tm.meta_key = %s
				AND tm.meta_value = %d",
				'product_cat',
				'thumbnail_id',
				$attachment_id
			);

			if ( null !== $query && $this->connection->get_var( $query ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all attachment IDs used as WooCommerce product category thumbnails.
	 *
	 * @since 1.7.0
	 *
	 * @return array<int> Attachment IDs.
	 */
	private function get_product_category_thumbnail_ids(): array {
		$ids                 = array();
		$prefix              = $this->connection->get_prefix();
		$term_taxonomy_table = $prefix . 'term_taxonomy';
		$termmeta_table      = $this->connection->get_termmeta_table();

		if ( $this->connection->table_exists( $termmeta_table ) ) {
			$query = $this->connection->prepare(
				"SELECT DISTINCT CAST(tm.meta_value AS UNSIGNED) FROM {$termmeta_table} tm
				INNER JOIN {$term_taxonomy_table} tt ON tm.term_id = tt.term_id
				WHERE tt.taxonomy = %s
				AND tm.meta_key = %s
				AND tm.meta_value != ''
				AND tm.meta_value != '0'",
				'product_cat',
				'thumbnail_id'
			);

			if ( null !== $query ) {
				$ids = array_merge( $ids, $this->connection->get_col( $query ) );
			}
		}

		$legacy_termmeta_table = $prefix . 'woocommerce_termmeta';
		if ( $this->connection->table_exists( $legacy_termmeta_table ) ) {
			$query = $this->connection->prepare(
				"SELECT DISTINCT CAST(tm.meta_value AS UNSIGNED) FROM {$legacy_termmeta_table} tm
				INNER JOIN {$term_taxonomy_table} tt ON tm.woocommerce_term_id = tt.term_id
				WHERE tt.taxonomy = %s
				AND tm.meta_key = %s
				AND tm.meta_value != ''
				AND tm.meta_value != '0'",
				'product_cat',
				'thumbnail_id'
			);

			if ( null !== $query ) {
				$ids = array_merge( $ids, $this->connection->get_col( $query ) );
			}
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Adjust slow query threshold for WooCommerce queries.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $threshold The current threshold in milliseconds.
	 * @param string $sql       The SQL query.
	 * @return float Adjusted threshold.
	 */
	public function adjust_slow_query_threshold( float $threshold, string $sql ): float {
		// Check if this is a WooCommerce query.
		$is_wc_query = (
			strpos( $sql, 'woocommerce_' ) !== false ||
			strpos( $sql, "post_type = 'product'" ) !== false ||
			strpos( $sql, "post_type = 'shop_order'" ) !== false ||
			strpos( $sql, "post_type = 'product_variation'" ) !== false
		);

		if ( $is_wc_query ) {
			// Use stricter threshold for WooCommerce queries.
			return self::WC_SLOW_QUERY_THRESHOLD;
		}

		return $threshold;
	}

	/**
	 * Get WooCommerce-specific slow queries.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Number of queries to return.
	 * @param int $days  Number of days to look back.
	 * @return array<array{sql: string, time_ms: float, caller: string, component: string, created_at: string, occurrence_count: int}> Array of slow queries.
	 */
	public function get_slow_wc_queries( int $limit = 20, int $days = 7 ): array {
		$table_name = $this->connection->get_prefix() . 'wpha_query_log';

		// Check if table exists.
		if ( ! $this->connection->table_exists( $table_name ) ) {
			return array();
		}

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$results = $this->connection->get_results(
			$this->connection->prepare(
				"SELECT
					sql,
					time_ms,
					caller,
					component,
					created_at,
					COUNT(*) as occurrence_count
				FROM {$table_name}
				WHERE created_at >= %s
				AND (
					sql LIKE %s
					OR sql LIKE %s
					OR sql LIKE %s
					OR sql LIKE %s
				)
				GROUP BY sql
				ORDER BY time_ms DESC
				LIMIT %d",
				$since,
				'%woocommerce_%',
				"%post_type = 'product'%",
				"%post_type = 'shop_order'%",
				"%post_type = 'product_variation'%",
				$limit
			),
			'ARRAY_A'
		);

		return $results;
	}

	/**
	 * Get WooCommerce performance insights.
	 *
	 * @since 1.0.0
	 *
	 * @return array<array{type: string, category: string, title: string, description: string, action: string, severity: string}> Array of performance insights.
	 */
	public function get_performance_insights(): array {
		$insights = array();

		// Check for expired sessions.
		$expired_sessions = $this->count_expired_sessions();
		if ( $expired_sessions > 100 ) {
			$insights[] = array(
				'type'        => 'warning',
				'category'    => 'database',
				'title'       => 'Expired WooCommerce Sessions',
				'description' => sprintf(
					/* translators: %d: Number of expired sessions */
					__( 'Found %d expired WooCommerce sessions. Consider cleaning them up to improve database performance.', 'wp-admin-health-suite' ),
					$expired_sessions
				),
				'action'      => 'clean_wc_sessions',
				'severity'    => $expired_sessions > 1000 ? 'high' : 'medium',
			);
		}

		// Check for orphaned variation meta.
		$orphaned_meta = $this->count_orphaned_variation_meta();
		if ( $orphaned_meta > 0 ) {
			$insights[] = array(
				'type'        => 'warning',
				'category'    => 'database',
				'title'       => 'Orphaned Product Variation Meta',
				'description' => sprintf(
					/* translators: %d: Number of orphaned meta entries */
					__( 'Found %d orphaned product variation meta entries. These can be safely removed.', 'wp-admin-health-suite' ),
					$orphaned_meta
				),
				'action'      => 'clean_orphaned_variation_meta',
				'severity'    => 'low',
			);
		}

		// Check for WooCommerce transients.
		$wc_transients = $this->count_wc_transients();
		if ( $wc_transients > 500 ) {
			$insights[] = array(
				'type'        => 'info',
				'category'    => 'database',
				'title'       => 'WooCommerce Transients',
				'description' => sprintf(
					/* translators: %d: Number of transients */
					__( 'Found %d WooCommerce transients. Consider cleaning expired ones to free up database space.', 'wp-admin-health-suite' ),
					$wc_transients
				),
				'action'      => 'clean_wc_transients',
				'severity'    => 'low',
			);
		}

		return $insights;
	}

	/**
	 * Get WooCommerce version.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use get_current_version() instead.
	 *
	 * @return string WooCommerce version or empty string if not active.
	 */
	public function get_wc_version(): string {
		return $this->get_current_version() ?? '';
	}

	/**
	 * Get WooCommerce database tables.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string> Array of WooCommerce table names.
	 */
	public function get_wc_tables(): array {
		$prefix = $this->connection->get_prefix();

		$tables = array(
			$prefix . 'woocommerce_sessions',
			$prefix . 'woocommerce_api_keys',
			$prefix . 'woocommerce_attribute_taxonomies',
			$prefix . 'woocommerce_downloadable_product_permissions',
			$prefix . 'woocommerce_order_items',
			$prefix . 'woocommerce_order_itemmeta',
			$prefix . 'woocommerce_tax_rates',
			$prefix . 'woocommerce_tax_rate_locations',
			$prefix . 'woocommerce_shipping_zones',
			$prefix . 'woocommerce_shipping_zone_locations',
			$prefix . 'woocommerce_shipping_zone_methods',
			$prefix . 'woocommerce_payment_tokens',
			$prefix . 'woocommerce_payment_tokenmeta',
		);

		// Filter to only existing tables.
		$existing_tables = array();
		foreach ( $tables as $table ) {
			if ( $this->connection->table_exists( $table ) ) {
				$existing_tables[] = $table;
			}
		}

		return $existing_tables;
	}

	/**
	 * Check if an attachment is used in WooCommerce content.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID to check.
	 * @return bool True if the attachment is used.
	 */
	public function is_attachment_used( int $attachment_id ): bool {
		return $this->check_product_image_usage( false, $attachment_id );
	}

	/**
	 * Get all attachment IDs used by WooCommerce products.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int> Array of attachment IDs.
	 */
	public function get_used_attachments(): array {
		$prefix = $this->connection->get_prefix();

		// Get product thumbnails.
		$thumbnails = $this->connection->get_col(
			"SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED) FROM {$prefix}postmeta pm
			INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
			WHERE p.post_type IN ('product', 'product_variation')
			AND pm.meta_key = '_thumbnail_id'
			AND pm.meta_value != ''
			AND pm.meta_value != '0'"
		);

		// Get product gallery images.
		$gallery_rows = $this->connection->get_col(
			"SELECT pm.meta_value FROM {$prefix}postmeta pm
			INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
			WHERE p.post_type = 'product'
			AND pm.meta_key = '_product_image_gallery'
			AND pm.meta_value != ''"
		);

		$gallery_ids = array();
		foreach ( $gallery_rows as $gallery_value ) {
			if ( ! empty( $gallery_value ) ) {
				$ids = array_map( 'absint', explode( ',', $gallery_value ) );
				$gallery_ids = array_merge( $gallery_ids, $ids );
			}
		}

		// Combine and deduplicate.
		$all_ids = array_merge(
			array_map( 'absint', $thumbnails ),
			$gallery_ids,
			$this->get_product_category_thumbnail_ids()
		);

		return array_values( array_unique( array_filter( $all_ids ) ) );
	}

	/**
	 * Get attachment usage locations in WooCommerce.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return array<array{post_id: int, post_title: string, context: string}> Usage locations.
	 */
	public function get_attachment_usage( int $attachment_id ): array {
		$prefix = $this->connection->get_prefix();
		$usage  = array();

		// Check product thumbnails.
		$thumbnail_products = $this->connection->get_results(
			$this->connection->prepare(
				"SELECT p.ID, p.post_title FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE p.post_type = 'product'
				AND pm.meta_key = '_thumbnail_id'
				AND pm.meta_value = %d",
				$attachment_id
			)
		);

		foreach ( $thumbnail_products as $product ) {
			$usage[] = array(
				'post_id'    => (int) $product->ID,
				'post_title' => $product->post_title,
				'context'    => __( 'Product featured image', 'wp-admin-health-suite' ),
			);
		}

		// Check product galleries using FIND_IN_SET for accurate matching.
		$gallery_products = $this->connection->get_results(
			$this->connection->prepare(
				"SELECT p.ID, p.post_title FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE p.post_type = 'product'
				AND pm.meta_key = '_product_image_gallery'
				AND FIND_IN_SET(%d, pm.meta_value) > 0",
				$attachment_id
			)
		);

		foreach ( $gallery_products as $product ) {
			$usage[] = array(
				'post_id'    => (int) $product->ID,
				'post_title' => $product->post_title,
				'context'    => __( 'Product gallery', 'wp-admin-health-suite' ),
			);
		}

		// Check variation thumbnails.
		$variation_products = $this->connection->get_results(
			$this->connection->prepare(
				"SELECT v.ID, v.post_title, p.post_title as parent_title, p.ID as parent_id
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts v ON pm.post_id = v.ID
				INNER JOIN {$prefix}posts p ON v.post_parent = p.ID
				WHERE v.post_type = 'product_variation'
				AND pm.meta_key = '_thumbnail_id'
				AND pm.meta_value = %d",
				$attachment_id
			)
		);

		foreach ( $variation_products as $variation ) {
			$usage[] = array(
				'post_id'    => (int) $variation->parent_id,
				'post_title' => $variation->parent_title,
				'context'    => sprintf(
					/* translators: %s: variation title */
					__( 'Product variation: %s', 'wp-admin-health-suite' ),
					$variation->post_title
				),
			);
		}

		// Check product category thumbnails.
		$termmeta_table      = $this->connection->get_termmeta_table();
		$term_taxonomy_table = $prefix . 'term_taxonomy';
		$terms_table         = $this->connection->get_terms_table();

		if ( $this->connection->table_exists( $termmeta_table ) ) {
			$query = $this->connection->prepare(
				"SELECT t.name FROM {$termmeta_table} tm
				INNER JOIN {$term_taxonomy_table} tt ON tm.term_id = tt.term_id
				INNER JOIN {$terms_table} t ON tm.term_id = t.term_id
				WHERE tt.taxonomy = %s
				AND tm.meta_key = %s
				AND tm.meta_value = %d",
				'product_cat',
				'thumbnail_id',
				$attachment_id
			);

			if ( null !== $query ) {
				$category_terms = $this->connection->get_results( $query );

				foreach ( $category_terms as $term ) {
					$usage[] = array(
						'post_id'    => 0,
						'post_title' => $term->name,
						'context'    => sprintf(
							/* translators: %s: product category name */
							__( 'Product category thumbnail: %s', 'wp-admin-health-suite' ),
							$term->name
						),
					);
				}
			}
		}

		return $usage;
	}
}
