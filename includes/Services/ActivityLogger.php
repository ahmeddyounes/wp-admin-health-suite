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
 */
class ActivityLogger implements ActivityLoggerInterface {

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

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
	 *
	 * @param ConnectionInterface $connection Database connection.
	 */
	public function __construct( ConnectionInterface $connection ) {
		$this->connection = $connection;
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

		return false !== $result;
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
}
