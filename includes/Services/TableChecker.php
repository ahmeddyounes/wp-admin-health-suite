<?php
/**
 * Table Checker Service
 *
 * Provides cached table existence checking.
 *
 * @package WPAdminHealth\Services
 */

namespace WPAdminHealth\Services;

use WPAdminHealth\Contracts\TableCheckerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class TableChecker
 *
 * Provides cached table existence checking to avoid repeated database queries.
 *
 * @since 1.3.0
 */
class TableChecker implements TableCheckerInterface {

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Cached existence results.
	 *
	 * @var array<string, bool>
	 */
	private array $cache = array();

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param ConnectionInterface $connection Database connection.
	 */
	public function __construct( ConnectionInterface $connection ) {
		$this->connection = $connection;
		$this->prefix     = $this->connection->get_prefix();
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists( string $table_name ): bool {
		if ( isset( $this->cache[ $table_name ] ) ) {
			return $this->cache[ $table_name ];
		}

		$this->cache[ $table_name ] = $this->connection->table_exists( $table_name );

		return $this->cache[ $table_name ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists_multiple( array $table_names ): array {
		$results = array();

		foreach ( $table_names as $table_name ) {
			$results[ $table_name ] = $this->exists( $table_name );
		}

		return $results;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_scan_history_table(): string {
		return $this->prefix . 'wpha_scan_history';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_query_log_table(): string {
		return $this->prefix . 'wpha_query_log';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_ajax_log_table(): string {
		return $this->prefix . 'wpha_ajax_log';
	}

	/**
	 * {@inheritdoc}
	 */
	public function scan_history_exists(): bool {
		return $this->exists( $this->get_scan_history_table() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function query_log_exists(): bool {
		return $this->exists( $this->get_query_log_table() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function ajax_log_exists(): bool {
		return $this->exists( $this->get_ajax_log_table() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear_cache( ?string $table_name = null ): void {
		if ( null === $table_name ) {
			$this->cache = array();
		} elseif ( isset( $this->cache[ $table_name ] ) ) {
			unset( $this->cache[ $table_name ] );
		}
	}
}
