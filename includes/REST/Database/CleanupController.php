<?php
/**
 * Database Cleanup REST Controller
 *
 * Handles database cleanup operations.
 *
 * @package WPAdminHealth\REST\Database
 */

namespace WPAdminHealth\REST\Database;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\REST\RestController;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * REST API controller for database cleanup endpoints.
 *
 * Handles cleanup operations for revisions, transients, spam comments,
 * trash, and orphaned data.
 *
 * @since 1.3.0
 */
class CleanupController extends RestController {

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'database/cleanup';

	/**
	 * Analyzer instance.
	 *
	 * @var AnalyzerInterface
	 */
	private AnalyzerInterface $analyzer;

	/**
	 * Revisions manager instance.
	 *
	 * @var RevisionsManagerInterface
	 */
	private RevisionsManagerInterface $revisions_manager;

	/**
	 * Transients cleaner instance.
	 *
	 * @var TransientsCleanerInterface
	 */
	private TransientsCleanerInterface $transients_cleaner;

	/**
	 * Orphaned cleaner instance.
	 *
	 * @var OrphanedCleanerInterface
	 */
	private OrphanedCleanerInterface $orphaned_cleaner;

	/**
	 * Trash cleaner instance.
	 *
	 * @var TrashCleanerInterface
	 */
	private TrashCleanerInterface $trash_cleaner;

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
	 * @param SettingsInterface            $settings           Settings instance.
	 * @param ConnectionInterface          $connection         Database connection instance.
	 * @param AnalyzerInterface            $analyzer           Analyzer instance.
	 * @param RevisionsManagerInterface    $revisions_manager  Revisions manager instance.
	 * @param TransientsCleanerInterface   $transients_cleaner Transients cleaner instance.
	 * @param OrphanedCleanerInterface     $orphaned_cleaner   Orphaned cleaner instance.
	 * @param TrashCleanerInterface        $trash_cleaner      Trash cleaner instance.
	 * @param ActivityLoggerInterface|null $activity_logger    Activity logger instance (optional).
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		AnalyzerInterface $analyzer,
		RevisionsManagerInterface $revisions_manager,
		TransientsCleanerInterface $transients_cleaner,
		OrphanedCleanerInterface $orphaned_cleaner,
		TrashCleanerInterface $trash_cleaner,
		?ActivityLoggerInterface $activity_logger = null
	) {
		parent::__construct( $settings, $connection );
		$this->analyzer           = $analyzer;
		$this->revisions_manager  = $revisions_manager;
		$this->transients_cleaner = $transients_cleaner;
		$this->orphaned_cleaner   = $orphaned_cleaner;
		$this->trash_cleaner      = $trash_cleaner;
		$this->activity_logger    = $activity_logger;
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /wpha/v1/database/cleanup - Execute cleanup by type.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clean' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'type'    => array(
							'description'       => __( 'Type of cleanup to perform.', 'wp-admin-health-suite' ),
							'type'              => 'string',
							'required'          => true,
							'enum'              => array( 'revisions', 'transients', 'spam', 'trash', 'orphaned' ),
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'options' => array(
							'description'       => __( 'Additional options for cleanup.', 'wp-admin-health-suite' ),
							'type'              => 'object',
							'default'           => array(),
							'sanitize_callback' => array( $this, 'sanitize_options' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /wpha/v1/database/cleanup/revisions - Clean revisions.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/revisions',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clean_revisions' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'keep_per_post' => array(
							'description'       => __( 'Number of revisions to keep per post.', 'wp-admin-health-suite' ),
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

		// POST /wpha/v1/database/cleanup/transients - Clean transients.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/transients',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clean_transients' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'expired_only' => array(
							'description'       => __( 'Only delete expired transients.', 'wp-admin-health-suite' ),
							'type'              => 'boolean',
							'default'           => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'exclude_patterns' => array(
							'description'       => __( 'Transient prefixes to exclude from cleanup.', 'wp-admin-health-suite' ),
							'type'              => 'array',
							'items'             => array( 'type' => 'string' ),
							'default'           => array(),
							'sanitize_callback' => array( $this, 'sanitize_string_array' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /wpha/v1/database/cleanup/spam - Clean spam comments.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/spam',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clean_spam' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'older_than_days' => array(
							'description'       => __( 'Only delete spam older than this many days.', 'wp-admin-health-suite' ),
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

		// POST /wpha/v1/database/cleanup/trash - Clean trash.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/trash',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clean_trash' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'older_than_days' => array(
							'description'       => __( 'Only delete trash older than this many days.', 'wp-admin-health-suite' ),
							'type'              => 'integer',
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'post_types'      => array(
							'description'       => __( 'Specific post types to clean.', 'wp-admin-health-suite' ),
							'type'              => 'array',
							'items'             => array( 'type' => 'string' ),
							'default'           => array(),
							'sanitize_callback' => array( $this, 'sanitize_post_types' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// POST /wpha/v1/database/cleanup/orphaned - Clean orphaned data.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/orphaned',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clean_orphaned' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'types' => array(
							'description'       => __( 'Types of orphaned data to clean.', 'wp-admin-health-suite' ),
							'type'              => 'array',
							'items'             => array(
								'type' => 'string',
								'enum' => array( 'postmeta', 'commentmeta', 'termmeta', 'relationships' ),
							),
							'default'           => array( 'postmeta', 'commentmeta', 'termmeta', 'relationships' ),
							'sanitize_callback' => array( $this, 'sanitize_orphaned_types' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Execute cleanup by type.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clean( $request ) {
		$type    = $request->get_param( 'type' );
		$options = $request->get_param( 'options' );

		if ( empty( $options ) || ! is_array( $options ) ) {
			$options = array();
		}

		// Check if safe mode is enabled.
		$safe_mode = $this->is_safe_mode_enabled();

		$result = null;

		switch ( $type ) {
			case 'revisions':
				$result = $this->execute_revisions_cleanup( $options, $safe_mode );
				break;

			case 'transients':
				$result = $this->execute_transients_cleanup( $options, $safe_mode );
				break;

			case 'spam':
				$result = $this->execute_spam_cleanup( $options, $safe_mode );
				break;

			case 'trash':
				$result = $this->execute_trash_cleanup( $options, $safe_mode );
				break;

			case 'orphaned':
				$result = $this->execute_orphaned_cleanup( $options, $safe_mode );
				break;

			default:
				return $this->format_error_response(
					new WP_Error(
						'invalid_type',
						__( 'Invalid cleanup type specified.', 'wp-admin-health-suite' )
					),
					400
				);
		}

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, 400 );
		}

		// Add safe mode indicator to result.
		if ( $safe_mode ) {
			$result['safe_mode'] = true;
			$result['preview_only'] = true;
		}

		// Log to activity.
		if ( null !== $this->activity_logger ) {
			$this->activity_logger->log_database_cleanup( $type, $result );
		}

		return $this->format_response(
			true,
			$result,
			sprintf(
				/* translators: %s: cleanup type */
				__( '%s cleanup completed successfully.', 'wp-admin-health-suite' ),
				ucfirst( $type )
			)
		);
	}

	/**
	 * Clean revisions.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clean_revisions( $request ) {
		$options = array(
			'keep_per_post' => $request->get_param( 'keep_per_post' ),
		);

		$safe_mode = $this->is_safe_mode_enabled();
		$result    = $this->execute_revisions_cleanup( $options, $safe_mode );

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, 400 );
		}

		if ( $safe_mode ) {
			$result['safe_mode']   = true;
			$result['preview_only'] = true;
		}

		if ( null !== $this->activity_logger ) {
			$this->activity_logger->log_database_cleanup( 'revisions', $result );
		}

		return $this->format_response(
			true,
			$result,
			__( 'Revisions cleanup completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Clean transients.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clean_transients( $request ) {
		$options = array(
			'expired_only'    => $request->get_param( 'expired_only' ),
			'exclude_patterns' => $request->get_param( 'exclude_patterns' ),
		);

		$safe_mode = $this->is_safe_mode_enabled();
		$result    = $this->execute_transients_cleanup( $options, $safe_mode );

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, 400 );
		}

		if ( $safe_mode ) {
			$result['safe_mode']   = true;
			$result['preview_only'] = true;
		}

		if ( null !== $this->activity_logger ) {
			$this->activity_logger->log_database_cleanup( 'transients', $result );
		}

		return $this->format_response(
			true,
			$result,
			__( 'Transients cleanup completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Clean spam comments.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clean_spam( $request ) {
		$options = array(
			'older_than_days' => $request->get_param( 'older_than_days' ),
		);

		$safe_mode = $this->is_safe_mode_enabled();
		$result    = $this->execute_spam_cleanup( $options, $safe_mode );

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, 400 );
		}

		if ( $safe_mode ) {
			$result['safe_mode']   = true;
			$result['preview_only'] = true;
		}

		if ( null !== $this->activity_logger ) {
			$this->activity_logger->log_database_cleanup( 'spam', $result );
		}

		return $this->format_response(
			true,
			$result,
			__( 'Spam comments cleanup completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Clean trash.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clean_trash( $request ) {
		$options = array(
			'older_than_days' => $request->get_param( 'older_than_days' ),
			'post_types'      => $request->get_param( 'post_types' ),
		);

		$safe_mode = $this->is_safe_mode_enabled();
		$result    = $this->execute_trash_cleanup( $options, $safe_mode );

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, 400 );
		}

		if ( $safe_mode ) {
			$result['safe_mode']   = true;
			$result['preview_only'] = true;
		}

		if ( null !== $this->activity_logger ) {
			$this->activity_logger->log_database_cleanup( 'trash', $result );
		}

		return $this->format_response(
			true,
			$result,
			__( 'Trash cleanup completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Clean orphaned data.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function clean_orphaned( $request ) {
		$options = array(
			'types' => $request->get_param( 'types' ),
		);

		$safe_mode = $this->is_safe_mode_enabled();
		$result    = $this->execute_orphaned_cleanup( $options, $safe_mode );

		if ( is_wp_error( $result ) ) {
			return $this->format_error_response( $result, 400 );
		}

		if ( $safe_mode ) {
			$result['safe_mode']   = true;
			$result['preview_only'] = true;
		}

		if ( null !== $this->activity_logger ) {
			$this->activity_logger->log_database_cleanup( 'orphaned', $result );
		}

		return $this->format_response(
			true,
			$result,
			__( 'Orphaned data cleanup completed successfully.', 'wp-admin-health-suite' )
		);
	}

	/**
	 * Execute revisions cleanup.
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	private function execute_revisions_cleanup( $options, $safe_mode = false ) {
		$settings      = $this->get_settings();
		$keep_per_post = isset( $options['keep_per_post'] ) ? absint( $options['keep_per_post'] ) : absint( $settings->get_setting( 'revisions_to_keep', 0 ) );

		if ( $safe_mode ) {
			$total_revisions = $this->revisions_manager->get_all_revisions_count();
			$size_estimate   = $this->revisions_manager->get_revisions_size_estimate();

			return array(
				'type'         => 'revisions',
				'deleted'      => 0,
				'would_delete' => $total_revisions,
				'bytes_freed'  => 0,
				'would_free'   => $size_estimate,
				'keep_per_post' => $keep_per_post,
			);
		}

		$result = $this->revisions_manager->delete_all_revisions( $keep_per_post );

		return array(
			'type'         => 'revisions',
			'deleted'      => $result['deleted'],
			'bytes_freed'  => $result['bytes_freed'],
			'keep_per_post' => $keep_per_post,
		);
	}

	/**
	 * Execute transients cleanup.
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	private function execute_transients_cleanup( $options, $safe_mode = false ) {
		$settings     = $this->get_settings();
		$expired_only = isset( $options['expired_only'] ) ? (bool) $options['expired_only'] : true;

		if ( ! isset( $options['exclude_patterns'] ) || ! is_array( $options['exclude_patterns'] ) ) {
			$excluded_prefixes = $settings->get_setting( 'excluded_transient_prefixes', '' );
			$exclude_patterns  = array_filter( array_map( 'trim', explode( "\n", $excluded_prefixes ) ) );
		} else {
			$exclude_patterns = $options['exclude_patterns'];
		}

		if ( $safe_mode ) {
			$total_count = $this->transients_cleaner->count_transients();
			$size        = $this->transients_cleaner->get_transients_size();

			return array(
				'type'             => 'transients',
				'deleted'          => 0,
				'would_delete'     => $total_count,
				'bytes_freed'      => 0,
				'would_free'       => $size,
				'expired_only'     => $expired_only,
				'exclude_patterns' => $exclude_patterns,
			);
		}

		if ( $expired_only ) {
			$result = $this->transients_cleaner->delete_expired_transients( $exclude_patterns );
		} else {
			$result = $this->transients_cleaner->delete_all_transients( $exclude_patterns );
		}

		return array(
			'type'            => 'transients',
			'deleted'         => $result['deleted'],
			'bytes_freed'     => $result['bytes_freed'],
			'expired_only'    => $expired_only,
			'exclude_patterns' => $exclude_patterns,
		);
	}

	/**
	 * Execute spam cleanup.
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	private function execute_spam_cleanup( $options, $safe_mode = false ) {
		$settings        = $this->get_settings();
		$older_than_days = isset( $options['older_than_days'] ) ? absint( $options['older_than_days'] ) : absint( $settings->get_setting( 'auto_clean_spam_days', 0 ) );

		if ( $safe_mode ) {
			$count = $this->analyzer->get_spam_comments_count();

			return array(
				'type'            => 'spam',
				'deleted'         => 0,
				'would_delete'    => $count,
				'errors'          => array(),
				'older_than_days' => $older_than_days,
			);
		}

		$result = $this->trash_cleaner->delete_spam_comments( $older_than_days );

		return array(
			'type'            => 'spam',
			'deleted'         => $result['deleted'],
			'errors'          => $result['errors'],
			'older_than_days' => $older_than_days,
		);
	}

	/**
	 * Execute trash cleanup.
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	private function execute_trash_cleanup( $options, $safe_mode = false ) {
		$settings        = $this->get_settings();
		$older_than_days = isset( $options['older_than_days'] ) ? absint( $options['older_than_days'] ) : absint( $settings->get_setting( 'auto_clean_trash_days', 0 ) );
		$post_types      = isset( $options['post_types'] ) && is_array( $options['post_types'] ) ? $options['post_types'] : array();

		if ( $safe_mode ) {
			$posts_count    = $this->analyzer->get_trashed_posts_count();
			$comments_count = $this->analyzer->get_trashed_comments_count();

			return array(
				'type'                 => 'trash',
				'posts_deleted'        => 0,
				'posts_would_delete'   => $posts_count,
				'posts_errors'         => array(),
				'comments_deleted'     => 0,
				'comments_would_delete' => $comments_count,
				'comments_errors'      => array(),
				'older_than_days'      => $older_than_days,
				'post_types'           => $post_types,
			);
		}

		$posts_result    = $this->trash_cleaner->delete_trashed_posts( $post_types, $older_than_days );
		$comments_result = $this->trash_cleaner->delete_trashed_comments( $older_than_days );

		return array(
			'type'             => 'trash',
			'posts_deleted'    => $posts_result['deleted'],
			'posts_errors'     => $posts_result['errors'],
			'comments_deleted' => $comments_result['deleted'],
			'comments_errors'  => $comments_result['errors'],
			'older_than_days'  => $older_than_days,
			'post_types'       => $post_types,
		);
	}

	/**
	 * Execute orphaned data cleanup.
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	private function execute_orphaned_cleanup( $options, $safe_mode = false ) {
		$types = isset( $options['types'] ) && is_array( $options['types'] ) ? $options['types'] : array( 'postmeta', 'commentmeta', 'termmeta', 'relationships' );

		$results = array(
			'type' => 'orphaned',
		);

		if ( $safe_mode ) {
			if ( in_array( 'postmeta', $types, true ) ) {
				$results['postmeta_deleted']      = 0;
				$results['postmeta_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_postmeta() );
			}

			if ( in_array( 'commentmeta', $types, true ) ) {
				$results['commentmeta_deleted']      = 0;
				$results['commentmeta_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_commentmeta() );
			}

			if ( in_array( 'termmeta', $types, true ) ) {
				$results['termmeta_deleted']      = 0;
				$results['termmeta_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_termmeta() );
			}

			if ( in_array( 'relationships', $types, true ) ) {
				$results['relationships_deleted']      = 0;
				$results['relationships_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_relationships() );
			}

			return $results;
		}

		if ( in_array( 'postmeta', $types, true ) ) {
			$results['postmeta_deleted'] = $this->orphaned_cleaner->delete_orphaned_postmeta();
		}

		if ( in_array( 'commentmeta', $types, true ) ) {
			$results['commentmeta_deleted'] = $this->orphaned_cleaner->delete_orphaned_commentmeta();
		}

		if ( in_array( 'termmeta', $types, true ) ) {
			$results['termmeta_deleted'] = $this->orphaned_cleaner->delete_orphaned_termmeta();
		}

		if ( in_array( 'relationships', $types, true ) ) {
			$results['relationships_deleted'] = $this->orphaned_cleaner->delete_orphaned_relationships();
		}

		return $results;
	}

	/**
	 * Sanitize options parameter.
	 *
	 * @since 1.3.0
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

	/**
	 * Sanitize string array parameter.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized string array.
	 */
	public function sanitize_string_array( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $value );
	}

	/**
	 * Sanitize post types array parameter.
	 *
	 * Only allows registered post types to prevent injection.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized post types array.
	 */
	public function sanitize_post_types( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$registered_post_types = get_post_types();
		$sanitized             = array();

		foreach ( $value as $post_type ) {
			$post_type = sanitize_key( $post_type );
			if ( in_array( $post_type, $registered_post_types, true ) ) {
				$sanitized[] = $post_type;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize orphaned types array parameter.
	 *
	 * Only allows valid orphaned data types.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return array Sanitized orphaned types array.
	 */
	public function sanitize_orphaned_types( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid_types = array( 'postmeta', 'commentmeta', 'termmeta', 'relationships' );
		$sanitized   = array();

		foreach ( $value as $type ) {
			$type = sanitize_key( $type );
			if ( in_array( $type, $valid_types, true ) ) {
				$sanitized[] = $type;
			}
		}

		return $sanitized;
	}
}
