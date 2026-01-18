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
use WPAdminHealth\Contracts\ActivityLoggerInterface;
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
	 * Activity logger instance.
	 *
	 * @var ActivityLoggerInterface|null
	 */
	private ?ActivityLoggerInterface $activity_logger;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 * @since 1.4.0 Added ActivityLoggerInterface dependency.
	 *
	 * @param SettingsInterface            $settings        Settings instance.
	 * @param ConnectionInterface          $connection      Database connection instance.
	 * @param OptimizerInterface           $optimizer       Optimizer instance.
	 * @param ActivityLoggerInterface|null $activity_logger Activity logger instance (optional).
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		OptimizerInterface $optimizer,
		?ActivityLoggerInterface $activity_logger = null
	) {
		parent::__construct( $settings, $connection );
		$this->optimizer       = $optimizer;
		$this->activity_logger = $activity_logger;
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
							'sanitize_callback' => array( $this, 'sanitize_table_names' ),
							'validate_callback' => 'rest_validate_request_arg',
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
							'sanitize_callback' => array( $this, 'sanitize_table_names' ),
							'validate_callback' => 'rest_validate_request_arg',
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
			// Back-compat: allow either the newer array format or legacy string list.
			if ( is_array( $table ) ) {
				$table_name = isset( $table['name'] ) ? sanitize_text_field( (string) $table['name'] ) : '';
				if ( '' === $table_name ) {
					continue;
				}

				$table_details[] = array(
					'table_name' => $table_name,
					'overhead'   => isset( $table['overhead'] ) ? absint( $table['overhead'] ) : 0,
					'data_size'  => isset( $table['data_size'] ) ? absint( $table['data_size'] ) : 0,
					'engine'     => isset( $table['engine'] ) ? sanitize_text_field( (string) $table['engine'] ) : '',
					'is_large'   => isset( $table['is_large'] ) ? (bool) $table['is_large'] : false,
				);
				continue;
			}

			$table_name = sanitize_text_field( (string) $table );
			if ( '' === $table_name ) {
				continue;
			}

			$overhead = $this->optimizer->get_table_overhead( $table_name );

			$table_details[] = array(
				'table_name' => $table_name,
				'overhead'   => false !== $overhead ? absint( $overhead ) : 0,
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
		$safe_mode = $this->is_safe_mode_enabled();
		$tables  = $request->get_param( 'tables' );
		$results = array();

		if ( $safe_mode ) {
			$would_optimize = array();

			if ( empty( $tables ) ) {
				foreach ( $this->optimizer->get_tables_needing_optimization() as $table ) {
					$table_name = is_array( $table )
						? ( isset( $table['name'] ) ? sanitize_text_field( (string) $table['name'] ) : '' )
						: sanitize_text_field( (string) $table );

					if ( '' === $table_name ) {
						continue;
					}

					$would_optimize[] = array(
						'table_name' => $table_name,
						'overhead'   => is_array( $table ) && isset( $table['overhead'] ) ? absint( $table['overhead'] ) : 0,
					);
				}
			} else {
				foreach ( $tables as $table_name ) {
					$table_name = sanitize_text_field( (string) $table_name );
					if ( '' === $table_name ) {
						continue;
					}

					$overhead = $this->optimizer->get_table_overhead( $table_name );

					$would_optimize[] = array(
						'table_name' => $table_name,
						'overhead'   => false !== $overhead ? absint( $overhead ) : 0,
					);
				}
			}

			return $this->format_response(
				true,
				array(
					'results'           => array(),
					'tables_optimized'  => 0,
					'total_bytes_freed' => 0,
					'safe_mode'         => true,
					'preview_only'      => true,
					'would_optimize'    => $would_optimize,
				),
				__( 'Safe mode enabled: optimization preview only, no changes were made.', 'wp-admin-health-suite' )
			);
		}

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
		if ( null !== $this->activity_logger ) {
			$this->activity_logger->log_database_cleanup(
				'optimization',
				array(
					'tables_optimized' => count( $results ),
					'bytes_freed'      => $total_bytes_freed,
				)
			);
		}

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
		$safe_mode = $this->is_safe_mode_enabled();
		$tables  = $request->get_param( 'tables' );
		$results = array();

		if ( $safe_mode ) {
			return $this->format_response(
				true,
				array(
					'results'         => array(),
					'tables_repaired' => 0,
					'total_tables'    => is_array( $tables ) ? count( $tables ) : 0,
					'safe_mode'       => true,
					'preview_only'    => true,
					'would_repair'    => is_array( $tables ) ? $tables : array(),
				),
				__( 'Safe mode enabled: repair preview only, no changes were made.', 'wp-admin-health-suite' )
			);
		}

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
		if ( null !== $this->activity_logger ) {
			$this->activity_logger->log_database_cleanup(
				'table_repair',
				array(
					'tables_repaired' => $success_count,
					'total_tables'    => count( $tables ),
				)
			);
		}

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
	 * Sanitize table names array.
	 *
	 * Only allows tables with the WordPress prefix to prevent accessing
	 * arbitrary database tables.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized table names array.
	 */
	public function sanitize_table_names( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$connection = $this->get_connection();
		$prefix     = $connection->get_prefix();

		$sanitized = array();

		foreach ( $value as $table_name ) {
			$table_name = sanitize_text_field( $table_name );

			// Only allow tables with WordPress prefix.
			if ( 0 === strpos( $table_name, $prefix ) ) {
				$sanitized[] = $table_name;
			}
		}

		return $sanitized;
	}
}
