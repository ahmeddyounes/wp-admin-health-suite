<?php
/**
 * Media Alt Text REST Controller
 *
 * Handles alt text checking operations.
 *
 * @package WPAdminHealth\REST\Media
 */

namespace WPAdminHealth\REST\Media;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for media alt text endpoints.
 *
 * Provides endpoints for checking and managing alt text on images.
 *
 * @since 1.3.0
 */
class MediaAltTextController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'media/alt-text';

	/**
	 * Items per page for pagination.
	 *
	 * @var int
	 */
	private int $per_page = 50;

	/**
	 * Alt text checker instance.
	 *
	 * @var AltTextCheckerInterface
	 */
	private AltTextCheckerInterface $alt_text_checker;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SettingsInterface         $settings         Settings instance.
	 * @param ConnectionInterface       $connection       Database connection instance.
	 * @param AltTextCheckerInterface   $alt_text_checker Alt text checker instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		AltTextCheckerInterface $alt_text_checker
	) {
		parent::__construct( $settings, $connection );
		$this->alt_text_checker = $alt_text_checker;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wpha/v1/media/alt-text - Get images missing alt text.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_missing_alt_text' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_pagination_params(),
				),
			)
		);

		// GET /wpha/v1/media/missing-alt - Alias for missing alt text endpoint.
		register_rest_route(
			$this->namespace,
			'/media/missing-alt',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_missing_alt_text' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_pagination_params(),
				),
			)
		);
	}

	/**
	 * Get images missing alt text.
	 *
	 * @since 1.0.0
	 * @since 1.3.0 Moved to MediaAltTextController.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_missing_alt_text( $request ) {
		$cursor   = $request->get_param( 'cursor' );
		$per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $this->per_page;

		// Apply cursor-based pagination.
		$start_index = $cursor ? absint( $cursor ) : 0;

		// AltTextChecker is limit-based; request enough items to serve this page.
		$all_missing = $this->alt_text_checker->find_missing_alt_text( $start_index + $per_page );
		$page_items  = array_slice( $all_missing, $start_index, $per_page );

		$alt_coverage = $this->alt_text_checker->get_alt_text_coverage();
		$total        = isset( $alt_coverage['images_without_alt'] ) ? absint( $alt_coverage['images_without_alt'] ) : count( $all_missing );

		$items = array();
		foreach ( $page_items as $item ) {
			$attachment_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			if ( ! $attachment_id ) {
				continue;
			}
			$items[] = array_merge( MediaHelper::get_attachment_details( $attachment_id ), $item );
		}

		$has_more    = ( $start_index + $per_page ) < $total;
		$next_cursor = $has_more ? ( $start_index + $per_page ) : null;

		return $this->format_response(
			true,
			array(
				'items'    => $items,
				'total'    => $total,
				'cursor'   => $next_cursor,
				'has_more' => $has_more,
			),
			__( 'Images missing alt text retrieved successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Get pagination parameters.
	 *
	 * @since 1.3.0
	 *
	 * @return array Pagination parameters.
	 */
	private function get_pagination_params(): array {
		return array(
			'cursor' => array(
				'description'       => __( 'Cursor for pagination (offset).', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => 0,
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'description'       => __( 'Number of items per page.', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => $this->per_page,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
