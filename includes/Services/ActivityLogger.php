<?php
/**
 * Activity Logger Service
 *
 * Centralized activity logging service.
 *
 * @package WPAdminHealth\Services
 */

namespace WPAdminHealth\Services;

use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class ActivityLogger
 *
 * Provides centralized activity logging functionality, eliminating
 * the duplicated log_activity methods in controllers.
 *
 * @since 1.3.0
 * @since 1.4.0 Added log rotation and retention management.
 */
class ActivityLogger implements ActivityLoggerInterface {

	/**
	 * Default TTL for activity logs in seconds (30 days).
	 *
	 * @var int
	 */
	const DEFAULT_LOG_TTL = 2592000;

	/**
	 * Default maximum number of rows to keep in the log table.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_LOG_ROWS = 10000;

	/**
	 * Transient key for tracking last prune time.
	 *
	 * @var string
	 */
	const PRUNE_TRANSIENT = 'wpha_activity_log_last_prune';

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface|null
	 */
	private ?SettingsInterface $settings;

	/**
	 * Activity log table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Cached table existence check.
	 *
	 * @var bool|null
	 */
	private ?bool $table_exists_cache = null;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 * @since 1.4.0 Added optional SettingsInterface parameter.
	 *
	 * @param ConnectionInterface    $connection Database connection.
	 * @param SettingsInterface|null $settings   Optional settings instance.
	 */
	public function __construct( ConnectionInterface $connection, ?SettingsInterface $settings = null ) {
		$this->connection = $connection;
		$this->settings   = $settings;
		$this->table_name = $this->connection->get_prefix() . 'wpha_scan_history';
	}

	/**
	 * {@inheritdoc}
	 */
	public function log( string $scan_type, int $items_found, int $items_cleaned = 0, int $bytes_freed = 0 ): bool {
		if ( ! $this->table_exists() ) {
			return false;
		}

		$result = $this->connection->insert(
			$this->table_name,
			array(
				'scan_type'     => sanitize_text_field( $scan_type ),
				'items_found'   => absint( $items_found ),
				'items_cleaned' => absint( $items_cleaned ),
				'bytes_freed'   => absint( $bytes_freed ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s' )
		);

		$success = false !== $result;

		// Trigger auto-pruning on successful insert (throttled to once per day).
		if ( $success ) {
			$this->maybe_auto_prune();
		}

		return $success;
	}

	/**
	 * {@inheritdoc}
	 */
	public function log_database_cleanup( string $type, array $result ): bool {
		$items_found   = 0;
		$items_cleaned = 0;
		$bytes_freed   = 0;

		switch ( $type ) {
			case 'revisions':
			case 'transients':
				$items_found   = isset( $result['deleted'] ) ? (int) $result['deleted'] : 0;
				$items_cleaned = $items_found;
				$bytes_freed   = isset( $result['bytes_freed'] ) ? (int) $result['bytes_freed'] : 0;
				break;

			case 'spam':
				$items_found   = isset( $result['deleted'] ) ? (int) $result['deleted'] : 0;
				$items_cleaned = $items_found;
				break;

			case 'trash':
				$items_found = ( isset( $result['posts_deleted'] ) ? (int) $result['posts_deleted'] : 0 )
					+ ( isset( $result['comments_deleted'] ) ? (int) $result['comments_deleted'] : 0 );
				$items_cleaned = $items_found;
				break;

			case 'orphaned':
				$items_found = ( isset( $result['postmeta_deleted'] ) ? (int) $result['postmeta_deleted'] : 0 )
					+ ( isset( $result['commentmeta_deleted'] ) ? (int) $result['commentmeta_deleted'] : 0 )
					+ ( isset( $result['termmeta_deleted'] ) ? (int) $result['termmeta_deleted'] : 0 )
					+ ( isset( $result['relationships_deleted'] ) ? (int) $result['relationships_deleted'] : 0 );
				$items_cleaned = $items_found;
				break;

			case 'optimization':
				$items_found   = isset( $result['tables_optimized'] ) ? (int) $result['tables_optimized'] : 0;
				$items_cleaned = $items_found;
				$bytes_freed   = isset( $result['bytes_freed'] ) ? (int) $result['bytes_freed'] : 0;
				break;
		}

		return $this->log( 'database_' . $type, $items_found, $items_cleaned, $bytes_freed );
	}

	/**
	 * {@inheritdoc}
	 */
	public function log_media_operation( string $type, array $result ): bool {
		$items_found   = 0;
		$items_cleaned = 0;
		$bytes_freed   = 0;

		switch ( $type ) {
			case 'delete':
				$items_found   = isset( $result['prepared_items'] ) ? count( $result['prepared_items'] ) : 0;
				$items_cleaned = $items_found;
				$bytes_freed   = isset( $result['bytes_freed'] ) ? (int) $result['bytes_freed'] : 0;
				break;

			case 'restore':
				$items_found   = 1;
				$items_cleaned = 1;
				break;

			case 'scan':
				$items_found   = isset( $result['total'] ) ? (int) $result['total'] : 0;
				$items_cleaned = isset( $result['unused'] ) ? (int) $result['unused'] : 0;
				break;

			case 'bulk_delete':
				$items_found   = isset( $result['total'] ) ? (int) $result['total'] : 0;
				$items_cleaned = isset( $result['deleted'] ) ? (int) $result['deleted'] : 0;
				$bytes_freed   = isset( $result['bytes_freed'] ) ? (int) $result['bytes_freed'] : 0;
				break;
		}

		return $this->log( 'media_' . $type, $items_found, $items_cleaned, $bytes_freed );
	}

	/**
	 * {@inheritdoc}
	 */
	public function log_performance_check( string $type, array $result ): bool {
		$items_found   = 0;
		$items_cleaned = 0;

		switch ( $type ) {
			case 'query_analysis':
				$items_found = isset( $result['slow_queries'] ) ? (int) $result['slow_queries'] : 0;
				break;

			case 'cache_check':
				$items_found   = isset( $result['issues'] ) ? count( $result['issues'] ) : 0;
				$items_cleaned = isset( $result['resolved'] ) ? (int) $result['resolved'] : 0;
				break;

			case 'autoload_analysis':
				$items_found = isset( $result['bloated_options'] ) ? (int) $result['bloated_options'] : 0;
				break;
		}

		return $this->log( 'performance_' . $type, $items_found, $items_cleaned, 0 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_recent( int $limit = 10, string $type = '' ): array {
		if ( ! $this->table_exists() ) {
			return array();
		}

		$limit = min( max( 1, $limit ), 100 );

		if ( '' !== $type ) {
			$query = $this->connection->prepare(
				"SELECT * FROM {$this->table_name} WHERE scan_type LIKE %s ORDER BY created_at DESC LIMIT %d",
				$this->connection->esc_like( $type ) . '%',
				$limit
			);
		} else {
			$query = $this->connection->prepare(
				"SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d",
				$limit
			);
		}

		if ( null === $query ) {
			return array();
		}

		return $this->connection->get_results( $query, 'ARRAY_A' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function table_exists(): bool {
		if ( null !== $this->table_exists_cache ) {
			return $this->table_exists_cache;
		}

		$this->table_exists_cache = $this->connection->table_exists( $this->table_name );

		return $this->table_exists_cache;
	}

	/**
	 * {@inheritdoc}
	 */
	public function prune_old_logs(): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		$ttl_seconds = $this->get_log_ttl_seconds();
		$cutoff      = gmdate( 'Y-m-d H:i:s', time() - $ttl_seconds );

		$query = $this->connection->prepare(
			"DELETE FROM {$this->table_name} WHERE created_at < %s",
			$cutoff
		);

		if ( null === $query ) {
			return 0;
		}

		$deleted = $this->connection->query( $query );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_log_count(): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		$count = $this->connection->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

		return absint( $count );
	}

	/**
	 * Automatically prune old logs if needed.
	 *
	 * Runs at most once per day to prevent excessive database operations.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	private function maybe_auto_prune(): void {
		// Check if we've pruned recently (within the last day).
		if ( false !== get_transient( self::PRUNE_TRANSIENT ) ) {
			return;
		}

		// Set transient to prevent running again for 24 hours.
		set_transient( self::PRUNE_TRANSIENT, time(), DAY_IN_SECONDS );

		// Prune old logs by TTL.
		$this->prune_old_logs();

		// Also enforce max rows limit to prevent unbounded growth.
		$this->enforce_max_rows();
	}

	/**
	 * Enforce maximum row limit by deleting oldest entries.
	 *
	 * @since 1.4.0
	 *
	 * @return int Number of rows deleted.
	 */
	private function enforce_max_rows(): int {
		$max_rows = $this->get_max_log_rows();
		$count    = $this->get_log_count();

		if ( $count <= $max_rows ) {
			return 0;
		}

		$excess = $count - $max_rows;

		$query = $this->connection->prepare(
			"DELETE FROM {$this->table_name}
			ORDER BY created_at ASC
			LIMIT %d",
			$excess
		);

		if ( null === $query ) {
			return 0;
		}

		$deleted = $this->connection->query( $query );

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * Get activity log TTL in seconds from settings.
	 *
	 * @since 1.4.0
	 *
	 * @return int TTL in seconds.
	 */
	private function get_log_ttl_seconds(): int {
		$default_days = self::DEFAULT_LOG_TTL / DAY_IN_SECONDS;

		if ( null !== $this->settings ) {
			$days = absint( $this->settings->get_setting( 'log_retention_days', $default_days ) );
		} else {
			$days = $default_days;
		}

		// Clamp to valid range (7-90 days as per CoreSettings).
		$days = max( 7, min( 90, $days ) );

		return $days * DAY_IN_SECONDS;
	}

	/**
	 * Get maximum activity log rows from settings.
	 *
	 * @since 1.4.0
	 *
	 * @return int Maximum rows to retain.
	 */
	private function get_max_log_rows(): int {
		$max_rows = self::DEFAULT_MAX_LOG_ROWS;

		if ( null !== $this->settings ) {
			$max_rows = absint( $this->settings->get_setting( 'activity_log_max_rows', $max_rows ) );
		}

		// Clamp to valid range.
		return max( 1000, min( 100000, $max_rows ) );
	}
}
