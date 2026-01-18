<?php
/**
 * Media Scan REST Controller
 *
 * Handles media scanning operations.
 *
 * @package WPAdminHealth\REST\Media
 */

namespace WPAdminHealth\REST\Media;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Application\Media\RunScan;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for media scan endpoints.
 *
 * Provides endpoints for triggering media scans.
 *
 * @since 1.3.0
 */
class MediaScanController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'media/scan';

	/**
	 * RunScan use case instance.
	 *
	 * @var RunScan
	 */
	private RunScan $run_scan;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 * @since 1.4.0 Updated to use RunScan application service.
	 *
	 * @param SettingsInterface   $settings   Settings instance.
	 * @param ConnectionInterface $connection Database connection instance.
	 * @param RunScan             $run_scan   RunScan use case instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		RunScan $run_scan
	) {
		parent::__construct( $settings, $connection );
		$this->run_scan = $run_scan;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 * @since 1.4.0 Added detailed scan type endpoints.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /wpha/v1/media/scan - Trigger media scan by type.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'trigger_scan' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'type'    => array(
							'description'       => __( 'Type of scan to perform.', 'wp-admin-health-suite' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => 'summary',
							'enum'              => RunScan::VALID_TYPES,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'options' => array(
							'description'       => __( 'Additional options for the scan.', 'wp-admin-health-suite' ),
							'type'              => 'object',
							'default'           => array(),
							'sanitize_callback' => array( $this, 'sanitize_options' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
						'async'   => array(
							'description'       => __( 'Run scan asynchronously in the background.', 'wp-admin-health-suite' ),
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// GET /wpha/v1/media/scan/summary - Get media summary.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		// POST /wpha/v1/media/scan/duplicates - Scan for duplicates.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/duplicates',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'scan_duplicates' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'method'          => array(
							'description'       => __( 'Detection method: hash, filename, or both.', 'wp-admin-health-suite' ),
							'type'              => 'string',
							'default'           => 'hash',
							'enum'              => RunScan::VALID_DUPLICATE_METHODS,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'include_details' => array(
							'description'       => __( 'Include detailed file information in response.', 'wp-admin-health-suite' ),
							'type'              => 'boolean',
							'default'           => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /wpha/v1/media/scan/large-files - Scan for large files.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/large-files',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'scan_large_files' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'threshold_kb'        => array(
							'description'       => __( 'Size threshold in kilobytes.', 'wp-admin-health-suite' ),
							'type'              => 'integer',
							'default'           => 500,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'include_suggestions' => array(
							'description'       => __( 'Include optimization suggestions.', 'wp-admin-health-suite' ),
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /wpha/v1/media/scan/alt-text - Scan for missing alt text.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/alt-text',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'scan_alt_text' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'limit'               => array(
							'description'       => __( 'Maximum number of results to return.', 'wp-admin-health-suite' ),
							'type'              => 'integer',
							'default'           => 100,
							'minimum'           => 1,
							'maximum'           => 500,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'include_suggestions' => array(
							'description'       => __( 'Include alt text suggestions based on filename.', 'wp-admin-health-suite' ),
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /wpha/v1/media/scan/unused - Scan for unused media.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/unused',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'scan_unused' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'batch_size' => array(
							'description'       => __( 'Number of items to scan per batch.', 'wp-admin-health-suite' ),
							'type'              => 'integer',
							'default'           => 100,
							'minimum'           => 10,
							'maximum'           => 500,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'offset'     => array(
							'description'       => __( 'Offset for pagination.', 'wp-admin-health-suite' ),
							'type'              => 'integer',
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// GET /wpha/v1/media/scan/exclusions - Get exclusions info.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/exclusions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_exclusions' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Trigger media scan.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaScanController.
	 * @since 1.4.0 Updated to use RunScan application service.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function trigger_scan( $request ) {
		$type    = $request->get_param( 'type' );
		$options = $request->get_param( 'options' );
		$async   = $request->get_param( 'async' );

		// Schedule scan in background if async is requested.
		if ( $async && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				'wpha_media_scan',
				array(
					'type'    => $type,
					'options' => $options,
				),
				'wpha_media'
			);

			return $this->format_response(
				true,
				array(
					'status'  => 'scheduled',
					'type'    => $type,
					'message' => __( 'Media scan has been scheduled to run in the background.', 'wp-admin-health-suite' ),
				),
				__( 'Media scan scheduled successfully.', 'wp-admin-health-suite' )
			);
		}

		// Execute scan synchronously.
		$result = $this->run_scan->execute(
			array(
				'type'    => $type,
				'options' => $options,
			)
		);

		// Check for errors.
		if ( isset( $result['success'] ) && false === $result['success'] ) {
			return $this->format_error_response(
				new WP_Error(
					$result['code'] ?? 'scan_error',
					$result['message'] ?? __( 'An error occurred during the scan.', 'wp-admin-health-suite' )
				),
				400
			);
		}

		return $this->format_response(
			true,
			$result,
			sprintf(
				/* translators: %s: scan type */
				__( 'Media %s scan completed successfully.', 'wp-admin-health-suite' ),
				$type
			)
		);
	}

	/**
	 * Get media summary.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_summary( $request ) {
		$result = $this->run_scan->get_summary();

		return $this->format_response(
			true,
			$result,
			__( 'Media summary retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Scan for duplicate media files.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function scan_duplicates( $request ) {
		$options = array(
			'method'          => $request->get_param( 'method' ),
			'include_details' => $request->get_param( 'include_details' ),
		);

		$result = $this->run_scan->execute_by_type( 'duplicates', $options );

		return $this->format_response(
			true,
			$result,
			__( 'Duplicate media scan completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Scan for large media files.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function scan_large_files( $request ) {
		$options = array(
			'threshold_kb'        => $request->get_param( 'threshold_kb' ),
			'include_suggestions' => $request->get_param( 'include_suggestions' ),
		);

		$result = $this->run_scan->execute_by_type( 'large_files', $options );

		return $this->format_response(
			true,
			$result,
			__( 'Large files scan completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Scan for images missing alt text.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function scan_alt_text( $request ) {
		$options = array(
			'limit'               => $request->get_param( 'limit' ),
			'include_suggestions' => $request->get_param( 'include_suggestions' ),
		);

		$result = $this->run_scan->execute_by_type( 'alt_text', $options );

		return $this->format_response(
			true,
			$result,
			__( 'Alt text scan completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Scan for unused media files.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function scan_unused( $request ) {
		$options = array(
			'batch_size' => $request->get_param( 'batch_size' ),
			'offset'     => $request->get_param( 'offset' ),
		);

		$result = $this->run_scan->execute_by_type( 'unused', $options );

		return $this->format_response(
			true,
			$result,
			__( 'Unused media scan completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get exclusions info.
	 *
	 * @since 1.4.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_exclusions( $request ) {
		$result = $this->run_scan->get_exclusions_info();

		return $this->format_response(
			true,
			$result,
			__( 'Exclusions retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Sanitize options parameter.
	 *
	 * @since 1.4.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized options array.
	 */
	public function sanitize_options( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $value as $key => $val ) {
			$key = sanitize_key( $key );

			if ( is_array( $val ) ) {
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $val );
			} elseif ( is_bool( $val ) ) {
				$sanitized[ $key ] = (bool) $val;
			} elseif ( is_numeric( $val ) ) {
				$sanitized[ $key ] = absint( $val );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $val );
			}
		}

		return $sanitized;
	}
}
