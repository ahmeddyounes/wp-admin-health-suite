<?php
/**
 * Optimization REST Controller
 *
 * Handles database and table optimization operations.
 *
 * @package WPAdminHealth\REST\Database
 */

namespace WPAdminHealth\REST\Database;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\OptimizerInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for database optimization endpoints.
 *
 * Provides endpoints for optimizing and repairing database tables.
 *
 * @since 1.3.0
 */
class OptimizationController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'database/optimization';

	/**
	 * Optimizer instance.
	 *
	 * @var OptimizerInterface
	 */
	private OptimizerInterface $optimizer;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface    $settings Settings instance.
	 * @param ConnectionInterface  $connection Database connection instance.
	 * @param OptimizerInterface   $optimizer Optimizer instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		OptimizerInterface $optimizer
	) {
		parent::__construct( $settings, $connection );
		$this->optimizer = $optimizer;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/database/optimization/tables - Get tables needing optimization.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tables',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tables_needing_optimization' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// POST /wpha/v1/database/optimization/optimize - Run optimization.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/optimize',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'optimize_tables' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'tables' => array(
							'description'       => __( 'Specific tables to optimize. If empty, optimizes all tables needing optimization.', 'wp-admin-health-suite' ),
							'type'              => 'array',
							'items'             => array( 'type' => 'string' ),
							'default'           => array(),
							'sanitize_callback' => function ( $value ) {
								if ( ! is_array( $value ) ) {
									return array();
								}
								return array_map( 'sanitize_text_field', $value );
							},
						),
					),
				),
			)
		);

		// POST /wpha/v1/database/optimization/repair - Repair tables.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/repair',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'repair_tables' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'tables' => array(
							'description'       => __( 'Tables to repair. Required.', 'wp-admin-health-suite' ),
							'type'              => 'array',
							'items'             => array( 'type' => 'string' ),
							'required'          => true,
							'sanitize_callback' => function ( $value ) {
								if ( ! is_array( $value ) ) {
									return array();
								}
								return array_map( 'sanitize_text_field', $value );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Get tables that need optimization.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_tables_needing_optimization( $request ) {
		$tables = $this->optimizer->get_tables_needing_optimization();

		$table_details = array();
		foreach ( $tables as $table ) {
			$overhead = $this->optimizer->get_table_overhead( $table );
			$table_details[] = array(
				'table_name' => $table,
				'overhead'   => $overhead ? $overhead : 0,
			);
		}

		return $this->format_response(
			true,
			array(
				'tables' => $table_details,
				'count'  => count( $table_details ),
			),
			__( 'Tables needing optimization retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Optimize database tables.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function optimize_tables( $request ) {
		$tables  = $request->get_param( 'tables' );
		$results = array();

		if ( empty( $tables ) ) {
			// Optimize all tables that need optimization.
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
			return $this->format_error_response(
				new WP_Error(
					'optimization_failed',
					__( 'No tables were optimized. Please check the table names.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		// Calculate total bytes freed.
		$total_bytes_freed = 0;
		foreach ( $results as $result ) {
			$total_bytes_freed += isset( $result['size_reduced'] ) ? $result['size_reduced'] : 0;
		}

		// Log to activity.
		$this->log_activity(
			'optimization',
			array(
				'tables_optimized' => count( $results ),
				'bytes_freed'      => $total_bytes_freed,
			)
		);

		return $this->format_response(
			true,
			array(
				'results'           => $results,
				'tables_optimized'  => count( $results ),
				'total_bytes_freed' => $total_bytes_freed,
			),
			__( 'Database optimization completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Repair database tables.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function repair_tables( $request ) {
		$tables  = $request->get_param( 'tables' );
		$results = array();

		if ( empty( $tables ) ) {
			return $this->format_error_response(
				new WP_Error(
					'repair_failed',
					__( 'No tables specified for repair.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		foreach ( $tables as $table ) {
			$result = $this->optimizer->repair_table( $table );
			if ( $result && isset( $result['success'] ) ) {
				$results[ $table ] = $result;
			}
		}

		if ( empty( $results ) ) {
			return $this->format_error_response(
				new WP_Error(
					'repair_failed',
					__( 'No tables were repaired. Please check the table names.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		$success_count = 0;
		foreach ( $results as $result ) {
			if ( ! empty( $result['success'] ) ) {
				$success_count++;
			}
		}

		// Log to activity.
		$this->log_activity(
			'table_repair',
			array(
				'tables_repaired' => $success_count,
				'total_tables'    => count( $tables ),
			)
		);

		return $this->format_response(
			true,
			array(
				'results'        => $results,
				'tables_repaired' => $success_count,
				'total_tables'   => count( $tables ),
			),
			__( 'Table repair completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Log activity to scan history.
	 *
	 * @since 1.3.0
	 *
	 * @param string $type   The operation type.
	 * @param array  $result The result data.
	 * @return void
	 */
	private function log_activity( string $type, array $result ): void {
		$connection = $this->get_connection();

		$table_name = $connection->get_prefix() . 'wpha_scan_history';

		// Check if table exists.
		if ( ! $connection->table_exists( $table_name ) ) {
			return;
		}

		// Determine items found and cleaned.
		$items_found   = 0;
		$items_cleaned = 0;
		$bytes_freed   = 0;

		switch ( $type ) {
			case 'optimization':
				$items_found   = isset( $result['tables_optimized'] ) ? $result['tables_optimized'] : 0;
				$items_cleaned = $items_found;
				$bytes_freed   = isset( $result['bytes_freed'] ) ? $result['bytes_freed'] : 0;
				break;

			case 'table_repair':
				$items_found   = isset( $result['tables_repaired'] ) ? $result['tables_repaired'] : 0;
				$items_cleaned = $items_found;
				break;
		}

		$scan_type = 'database_' . $type;

		$connection->insert(
			$table_name,
			array(
				'scan_type'     => sanitize_text_field( $scan_type ),
				'items_found'   => absint( $items_found ),
				'items_cleaned' => absint( $items_cleaned ),
				'bytes_freed'   => absint( $bytes_freed ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s' )
		);
	}
}
