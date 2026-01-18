<?php
/**
 * Run Optimization Use Case
 *
 * Application service for orchestrating database optimization operations.
 *
 * @package WPAdminHealth\Application\Database
 */

namespace WPAdminHealth\Application\Database;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\OptimizerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Exceptions\DatabaseException;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class RunOptimization
 *
 * Orchestrates database optimization operations including table optimization,
 * defragmentation, and overhead cleanup.
 *
 * This use-case class serves as the application layer between REST controllers
 * and domain services, providing a clean interface for optimization operations.
 *
 * @since 1.4.0
 */
class RunOptimization {

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface
	 */
	private SettingsInterface $settings;

	/**
	 * Optimizer instance.
	 *
	 * @var OptimizerInterface
	 */
	private OptimizerInterface $optimizer;

	/**
	 * Database connection instance.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Activity logger instance.
	 *
	 * @var ActivityLoggerInterface|null
	 */
	private ?ActivityLoggerInterface $activity_logger;

	/**
	 * Constructor.
	 *
	 * @since 1.4.0
	 *
	 * @param SettingsInterface            $settings        Settings instance.
	 * @param OptimizerInterface           $optimizer       Optimizer instance.
	 * @param ConnectionInterface          $connection      Database connection instance.
	 * @param ActivityLoggerInterface|null $activity_logger Optional activity logger instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		OptimizerInterface $optimizer,
		ConnectionInterface $connection,
		?ActivityLoggerInterface $activity_logger = null
	) {
		$this->settings        = $settings;
		$this->optimizer       = $optimizer;
		$this->connection      = $connection;
		$this->activity_logger = $activity_logger;
	}

	/**
	 * Execute the optimization operation.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Optimization options.
	 *                       - tables: array - Specific table names to optimize. If empty, optimizes all tables.
	 * @return array Result of the optimization operation.
	 * @throws DatabaseException If no tables could be optimized.
	 */
	public function execute( array $options = array() ): array {
		$tables = isset( $options['tables'] ) && is_array( $options['tables'] )
			? $options['tables']
			: array();

		$results = array();

		if ( empty( $tables ) ) {
			// Optimize all tables.
			$results = $this->optimizer->optimize_all_tables();
		} else {
			// Optimize specific tables.
			foreach ( $tables as $table ) {
				$result = $this->optimizer->optimize_table( $table );
				if ( $result ) {
					$results[] = $result;
				}
			}
		}

		if ( empty( $results ) ) {
			throw DatabaseException::with_context(
				'No tables were optimized. Please check the table names.',
				DatabaseException::ERROR_QUERY_FAILED,
				array( 'requested_tables' => $tables ),
				400
			);
		}

		// Calculate total bytes freed.
		$total_bytes_freed = 0;
		foreach ( $results as $result ) {
			$total_bytes_freed += isset( $result['size_reduced'] ) ? $result['size_reduced'] : 0;
		}

		$response = array(
			'success'           => true,
			'results'           => $results,
			'tables_optimized'  => count( $results ),
			'total_bytes_freed' => $total_bytes_freed,
		);

		// Log activity.
		$this->log_optimization_activity( $response );

		return $response;
	}

	/**
	 * Optimize all database tables.
	 *
	 * @since 1.4.0
	 *
	 * @return array Optimization results.
	 */
	public function optimize_all(): array {
		return $this->execute( array( 'tables' => array() ) );
	}

	/**
	 * Optimize specific tables.
	 *
	 * @since 1.4.0
	 *
	 * @param array $tables Table names to optimize.
	 * @return array Optimization results.
	 */
	public function optimize_tables( array $tables ): array {
		return $this->execute( array( 'tables' => $tables ) );
	}

	/**
	 * Log optimization activity.
	 *
	 * @since 1.4.0
	 *
	 * @param array $result Optimization result.
	 * @return void
	 */
	private function log_optimization_activity( array $result ): void {
		if ( null === $this->activity_logger ) {
			// Fallback to database logging if no activity logger is available.
			$this->log_to_scan_history( $result );
			return;
		}

		$this->activity_logger->log(
			'database_optimization',
			array(
				'tables_optimized' => $result['tables_optimized'] ?? 0,
				'bytes_freed'      => $result['total_bytes_freed'] ?? 0,
			)
		);
	}

	/**
	 * Log to scan history table.
	 *
	 * @since 1.4.0
	 *
	 * @param array $result Optimization result.
	 * @return void
	 */
	private function log_to_scan_history( array $result ): void {
		$table_name = $this->connection->get_prefix() . 'wpha_scan_history';

		// Check if table exists.
		if ( ! $this->connection->table_exists( $table_name ) ) {
			return;
		}

		$this->connection->insert(
			$table_name,
			array(
				'scan_type'     => 'database_optimization',
				'items_found'   => absint( $result['tables_optimized'] ?? 0 ),
				'items_cleaned' => absint( $result['tables_optimized'] ?? 0 ),
				'bytes_freed'   => absint( $result['total_bytes_freed'] ?? 0 ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s' )
		);
	}
}
