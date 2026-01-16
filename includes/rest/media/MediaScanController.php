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
use WPAdminHealth\Contracts\ScannerInterface;
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
	 * Scanner instance.
	 *
	 * @var ScannerInterface
	 */
	private ScannerInterface $scanner;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface   $settings   Settings instance.
	 * @param ConnectionInterface $connection Database connection instance.
	 * @param ScannerInterface    $scanner    Scanner instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		ScannerInterface $scanner
	) {
		parent::__construct( $settings, $connection );
		$this->scanner = $scanner;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /wpha/v1/media/scan - Trigger full media scan.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'trigger_scan' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Trigger full media scan.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaScanController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function trigger_scan( $request ) {
		// Schedule scan in background using action scheduler.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'wpha_media_scan', array(), 'wpha_media' );

			return $this->format_response(
				true,
				array(
					'status'  => 'scheduled',
					'message' => __( 'Media scan has been scheduled to run in the background.', 'wp-admin-health-suite' ),
				),
				__( 'Media scan scheduled successfully.', 'wp-admin-health-suite' )
			);
		}

		// Fallback: run scan immediately if Action Scheduler is not available.
		$results = $this->scanner->get_media_summary();

		return $this->format_response(
			true,
			array(
				'status'  => 'completed',
				'results' => $results,
			),
			__( 'Media scan completed successfully.', 'wp-admin-health-suite' )
		);
	}
}
