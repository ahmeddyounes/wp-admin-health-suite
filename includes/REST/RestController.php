<?php
/**
 * REST Controller Base Class
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\REST;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\TableCheckerInterface;
use WPAdminHealth\Exceptions\WPAdminHealthException;
use WPAdminHealth\Exceptions\ValidationException;
use WPAdminHealth\Exceptions\NotFoundException;
use WPAdminHealth\Exceptions\DatabaseException;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Base REST API controller class.
 *
 * Provides common functionality for REST API endpoints including:
 * - Authentication and permission checks
 * - Nonce verification
 * - Rate limiting
 * - Standard response formatting
 * - Error handling
 *
 * @since 1.0.0
 */
class RestController extends WP_REST_Controller {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wpha/v1';

	/**
	 * REST base for the controller.
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Rate limit: maximum requests per minute.
	 *
	 * @var int
	 */
	protected $rate_limit = 60;

	/**
	 * Settings instance.
	 *
	 * @since 1.1.0
	 * @var SettingsInterface|null
	 */
	protected ?SettingsInterface $settings = null;

	/**
	 * Database connection.
	 *
	 * @since 1.3.0
	 * @var ConnectionInterface|null
	 */
	protected ?ConnectionInterface $connection = null;

	/**
	 * Table checker instance.
	 *
	 * @since 1.4.0
	 * @var TableCheckerInterface|null
	 */
	protected ?TableCheckerInterface $table_checker = null;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 * @since 1.3.0 Added optional connection parameter.
	 * @since 1.4.0 Added optional table_checker parameter.
	 *
	 * @param SettingsInterface|null     $settings      Optional settings instance for dependency injection.
	 * @param ConnectionInterface|null   $connection    Optional database connection for dependency injection.
	 * @param TableCheckerInterface|null $table_checker Optional table checker for dependency injection.
	 */
	public function __construct(
		?SettingsInterface $settings = null,
		?ConnectionInterface $connection = null,
		?TableCheckerInterface $table_checker = null
	) {
		$this->settings      = $settings;
		$this->connection    = $connection;
		$this->table_checker = $table_checker;
	}

	/**
	 * Get the settings instance.
	 *
	 * All REST controllers are container-managed via RESTServiceProvider,
	 * which injects the SettingsInterface dependency via constructor.
	 *
	 * @since 1.1.0
	 *
	 * @return SettingsInterface The settings instance.
	 * @throws \RuntimeException If settings instance is not set (should never happen in production).
	 */
	protected function get_settings(): SettingsInterface {
		if ( null === $this->settings ) {
			throw new \RuntimeException( 'SettingsInterface not injected. REST controllers must be instantiated via RESTServiceProvider.' );
		}

		return $this->settings;
	}

	/**
	 * Get the database connection instance.
	 *
	 * All REST controllers are container-managed via RESTServiceProvider,
	 * which injects the ConnectionInterface dependency via constructor.
	 *
	 * @since 1.3.0
	 *
	 * @return ConnectionInterface The database connection instance.
	 * @throws \RuntimeException If connection instance is not set (should never happen in production).
	 */
	protected function get_connection(): ConnectionInterface {
		if ( null === $this->connection ) {
			throw new \RuntimeException( 'ConnectionInterface not injected. REST controllers must be instantiated via RESTServiceProvider.' );
		}

		return $this->connection;
	}

	/**
	 * Get the table checker instance.
	 *
	 * Provides cached table existence checks. Falls back to direct connection
	 * check if TableCheckerInterface is not injected.
	 *
	 * @since 1.4.0
	 *
	 * @return TableCheckerInterface|null The table checker instance, or null if not available.
	 */
	protected function get_table_checker(): ?TableCheckerInterface {
		return $this->table_checker;
	}

	/**
	 * Check if a table exists using the cached TableChecker.
	 *
	 * Falls back to direct ConnectionInterface::table_exists() if
	 * TableCheckerInterface is not injected.
	 *
	 * @since 1.4.0
	 *
	 * @param string $table_name Full table name to check.
	 * @return bool True if table exists, false otherwise.
	 */
	protected function table_exists( string $table_name ): bool {
		if ( null !== $this->table_checker ) {
			return $this->table_checker->exists( $table_name );
		}

		// Fallback to direct connection check.
		return $this->get_connection()->table_exists( $table_name );
	}

	/**
	 * Register routes for the controller.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the object.', 'wp-admin-health-suite' ),
							'type'        => 'integer',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the object.', 'wp-admin-health-suite' ),
							'type'        => 'integer',
						),
					),
				),
			)
		);
	}

	/**
	 * Get a collection of items.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		return $this->format_response( true, array(), __( 'Items retrieved successfully.', 'wp-admin-health-suite' ) );
	}

	/**
	 * Get a single item.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$id = $request->get_param( 'id' );
		return $this->format_response( true, array( 'id' => $id ), __( 'Item retrieved successfully.', 'wp-admin-health-suite' ) );
	}

	/**
	 * Create a single item.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		return $this->format_response( true, array(), __( 'Item created successfully.', 'wp-admin-health-suite' ), 201 );
	}

	/**
	 * Delete a single item.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$id = $request->get_param( 'id' );
		return $this->format_response( true, array( 'id' => $id ), __( 'Item deleted successfully.', 'wp-admin-health-suite' ) );
	}

	/**
	 * Check permissions for the request.
	 *
	 * Verifies:
	 * - REST API is enabled
	 * - User authentication
	 * - manage_options capability
	 * - Nonce verification via X-WP-Nonce header
	 * - Rate limiting
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
	 */
	public function check_permissions( $request ) {
		// Check if REST API is enabled.
		$settings = $this->get_settings();
		if ( ! $settings->is_rest_api_enabled() ) {
			return new WP_Error(
				'rest_api_disabled',
				__( 'REST API is currently disabled.', 'wp-admin-health-suite' ),
				array( 'status' => 403 )
			);
		}

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You are not currently logged in.', 'wp-admin-health-suite' ),
				array( 'status' => 401 )
			);
		}

		// Check if user has manage_options capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to do that.', 'wp-admin-health-suite' ),
				array( 'status' => 403 )
			);
		}

		// Verify nonce.
		$nonce_check = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		// Check rate limiting.
		$rate_limit_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}

		return true;
	}

	/**
	 * Verify nonce from X-WP-Nonce header.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if nonce is valid, WP_Error otherwise.
	 */
	protected function verify_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			// Back-compat: allow `_wpnonce` param for clients that can't set headers.
			$nonce = $request->get_param( '_wpnonce' );
		}

		$nonce = is_string( $nonce ) ? sanitize_text_field( $nonce ) : '';

		if ( empty( $nonce ) ) {
			return new WP_Error(
				'rest_missing_nonce',
				__( 'Missing security nonce.', 'wp-admin-health-suite' ),
				array( 'status' => 403 )
			);
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_invalid_nonce',
				__( 'Invalid security nonce.', 'wp-admin-health-suite' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check rate limiting for the current user.
	 *
	 * Limits requests per minute per user using transients.
	 * Rate limit is configurable via settings.
	 * Uses atomic operations to prevent race condition bypasses.
	 *
	 * @return bool|WP_Error True if within rate limit, WP_Error otherwise.
	 */
	protected function check_rate_limit() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return true;
		}

		// Get rate limit from settings.
		$settings   = $this->get_settings();
		$rate_limit = $settings->get_rest_api_rate_limit();

		$cache_key = 'wpha_rate_limit_' . $user_id;

		// Try to use wp_cache for atomic operations if object cache is available.
		if ( wp_using_ext_object_cache() ) {
			return $this->check_rate_limit_with_cache( $cache_key, $rate_limit );
		}

		// Fall back to transient-based rate limiting with locking.
		return $this->check_rate_limit_with_lock( $cache_key, $rate_limit, $user_id );
	}

	/**
	 * Check rate limit using object cache atomic operations.
	 *
	 * @since 1.2.0
	 *
	 * @param string $cache_key  Cache key.
	 * @param int    $rate_limit Maximum requests per minute.
	 * @return bool|WP_Error True if within rate limit, WP_Error otherwise.
	 */
	private function check_rate_limit_with_cache( string $cache_key, int $rate_limit ) {
		$cache_group = 'wpha_rate_limits';

		// Try to increment atomically. Returns false if key doesn't exist.
		$count = wp_cache_incr( $cache_key, 1, $cache_group );

		if ( false === $count ) {
			// Key doesn't exist, set initial value.
			wp_cache_set( $cache_key, 1, $cache_group, MINUTE_IN_SECONDS );
			return true;
		}

		if ( $count > $rate_limit ) {
			return new WP_Error(
				'rest_rate_limit_exceeded',
				sprintf(
					/* translators: %d: rate limit */
					__( 'Rate limit exceeded. Maximum %d requests per minute allowed.', 'wp-admin-health-suite' ),
					$rate_limit
				),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Check rate limit using transients with database locking.
	 *
	 * This is a fallback for sites without persistent object cache.
	 * Uses a brief lock to prevent race conditions.
	 *
	 * @since 1.2.0
	 *
	 * @param string $cache_key  Cache key.
	 * @param int    $rate_limit Maximum requests per minute.
	 * @param int    $user_id    Current user ID.
	 * @return bool|WP_Error True if within rate limit, WP_Error otherwise.
	 */
	private function check_rate_limit_with_lock( string $cache_key, int $rate_limit, int $user_id ) {
		$lock_key = 'wpha_rl_lock_' . $user_id;

		// Acquire a brief lock using transient (5 second timeout).
		$lock_acquired = false;
		$attempts      = 0;
		$max_attempts  = 5;

		while ( ! $lock_acquired && $attempts < $max_attempts ) {
			// Try to set lock. Will fail if lock already exists.
			$lock_acquired = $this->acquire_transient_lock( $lock_key, 5 );
			if ( ! $lock_acquired ) {
				usleep( 50000 ); // Wait 50ms before retry.
				$attempts++;
			}
		}

		if ( ! $lock_acquired ) {
			// Security: Fail closed - deny request when rate limiter is unavailable.
			// This prevents attackers from bypassing rate limits via lock flooding.
			return new WP_Error(
				'rest_rate_limit_unavailable',
				__( 'Rate limiter temporarily unavailable. Please try again.', 'wp-admin-health-suite' ),
				array( 'status' => 503 )
			);
		}

		try {
			$requests = get_transient( $cache_key );

			if ( false === $requests ) {
				set_transient( $cache_key, 1, MINUTE_IN_SECONDS );
				return true;
			}

			if ( $requests >= $rate_limit ) {
				return new WP_Error(
					'rest_rate_limit_exceeded',
					sprintf(
						/* translators: %d: rate limit */
						__( 'Rate limit exceeded. Maximum %d requests per minute allowed.', 'wp-admin-health-suite' ),
						$rate_limit
					),
					array( 'status' => 429 )
				);
			}

			set_transient( $cache_key, $requests + 1, MINUTE_IN_SECONDS );
			return true;
		} finally {
			// Always release lock.
			delete_transient( $lock_key );
		}
	}

	/**
	 * Attempt to acquire a transient-based lock.
	 *
	 * @since 1.2.0
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param string $lock_key Lock key name.
	 * @param int    $timeout  Lock timeout in seconds.
	 * @return bool True if lock acquired, false otherwise.
	 */
	private function acquire_transient_lock( string $lock_key, int $timeout ): bool {
		$connection = $this->get_connection();

		// Use direct database insert to make this atomic.
		// set_transient is not atomic - it checks then sets.
		$option_name  = '_transient_' . $lock_key;
		$timeout_name = '_transient_timeout_' . $lock_key;
		$expiration   = time() + $timeout;
		$options_table = $connection->get_options_table();

		// Try to insert. Will fail if option already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$query = $connection->prepare(
			"INSERT IGNORE INTO {$options_table} (option_name, option_value, autoload) VALUES (%s, %s, 'no'), (%s, %s, 'no')",
			$timeout_name,
			$expiration,
			$option_name,
			'1'
		);

		$result = $query ? $connection->query( $query ) : false;

		// If 2 rows inserted, we got the lock.
		if ( 2 === $result ) {
			return true;
		}

		// Check if existing lock has expired.
		$existing_timeout = get_option( $timeout_name );
		if ( $existing_timeout && (int) $existing_timeout < time() ) {
			// Lock expired, clean it up and try again.
			delete_option( $timeout_name );
			delete_option( $option_name );
			return $this->acquire_transient_lock( $lock_key, $timeout );
		}

		return false;
	}

	/**
	 * Format response in standard format.
	 *
	 * @since 1.3.0 Uses ConnectionInterface instead of global $wpdb.
	 *
	 * @param bool   $success Whether the request was successful.
	 * @param mixed  $data    The response data.
	 * @param string $message The response message.
	 * @param int    $status  HTTP status code (default: 200).
	 * @return WP_REST_Response The formatted response.
	 */
	protected function format_response( $success, $data = null, $message = '', $status = 200 ) {
		$response = array(
			'success' => $success,
			'data'    => $data,
			'message' => $message,
		);

		// Add debug information if debug mode is enabled.
		// Security: Only include basic metrics, never expose full queries or stack traces.
		if ( $this->is_debug_mode_enabled() ) {
			$connection = $this->get_connection();

			$response['debug'] = array(
				'queries'       => $connection->get_num_queries(),
				'memory_usage'  => size_format( memory_get_usage() ),
				'memory_peak'   => size_format( memory_get_peak_usage() ),
				'time_elapsed'  => timer_stop( 0, 3 ),
			);

			// Security: Only expose query timing for super admins with WPHA_DEBUG explicitly set.
			// Never expose full query text or stack traces via REST API.
			$query_log = $connection->get_query_log();
			if ( $this->can_view_detailed_debug() && defined( 'SAVEQUERIES' ) && SAVEQUERIES && ! empty( $query_log ) ) {
				$response['debug']['query_timing'] = array_map(
					function ( $query ) {
						return array(
							'query_type' => $this->get_query_type( $query[0] ),
							'time'       => round( $query[1], 5 ) . 's',
						);
					},
					array_slice( $query_log, -10 ) // Last 10 queries.
				);
			}
		}

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Check if current user can view detailed debug information.
	 *
	 * Only super admins (in multisite) or admins with WPHA_DEBUG constant
	 * should see detailed query information.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True if user can view detailed debug info.
	 */
	protected function can_view_detailed_debug(): bool {
		// Require explicit WPHA_DEBUG constant for detailed debug output.
		if ( ! defined( 'WPHA_DEBUG' ) || ! WPHA_DEBUG ) {
			return false;
		}

		// In multisite, require super admin capability.
		if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Extract the query type from a SQL query without exposing sensitive data.
	 *
	 * @since 1.2.0
	 *
	 * @param string $query The SQL query.
	 * @return string The query type (SELECT, INSERT, UPDATE, DELETE, or OTHER).
	 */
	protected function get_query_type( string $query ): string {
		$query = trim( strtoupper( $query ) );

		$types = array( 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'SHOW', 'DESCRIBE' );

		foreach ( $types as $type ) {
			if ( 0 === strpos( $query, $type ) ) {
				return $type;
			}
		}

		return 'OTHER';
	}

	/**
	 * Format error response.
	 *
	 * @param WP_Error $error   The error object.
	 * @param int      $status  HTTP status code (default: 400).
	 * @return WP_REST_Response The formatted error response.
	 */
	protected function format_error_response( $error, $status = 400 ) {
		$response = array(
			'success' => false,
			'data'    => null,
			'message' => $error->get_error_message(),
		);

		// Include error code if available.
		if ( $error->get_error_code() ) {
			$response['code'] = $error->get_error_code();
		}

		// Include error data if available.
		if ( $error->get_error_data() ) {
			$response['error_data'] = $error->get_error_data();
		}

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Handle an exception and convert it to a REST response.
	 *
	 * Converts WPAdminHealthException instances to appropriate REST error responses
	 * with consistent formatting and appropriate HTTP status codes.
	 *
	 * @since 1.4.0
	 *
	 * @param \Throwable $exception The exception to handle.
	 * @return WP_REST_Response The formatted error response.
	 */
	protected function handle_exception( \Throwable $exception ): WP_REST_Response {
		// Handle our custom exceptions with full context.
		if ( $exception instanceof WPAdminHealthException ) {
			return $this->format_exception_response( $exception );
		}

		// Handle standard exceptions with limited info for security.
		// Log the full error but only return safe message to client.
		if ( $this->is_debug_mode_enabled() && $this->can_view_detailed_debug() ) {
			// In debug mode, include more details for development.
			return $this->format_generic_exception_response( $exception, true );
		}

		// Production: generic error message.
		return $this->format_generic_exception_response( $exception, false );
	}

	/**
	 * Format a WPAdminHealthException into a REST response.
	 *
	 * @since 1.4.0
	 *
	 * @param WPAdminHealthException $exception The exception to format.
	 * @return WP_REST_Response The formatted error response.
	 */
	protected function format_exception_response( WPAdminHealthException $exception ): WP_REST_Response {
		$response = array(
			'success' => false,
			'data'    => null,
			'message' => $exception->getMessage(),
			'code'    => $exception->getCode(),
		);

		// Include context data if available (already sanitized in exception classes).
		$context = $exception->get_context();
		if ( ! empty( $context ) ) {
			$response['error_data'] = $context;
		}

		// Add debug info if enabled.
		if ( $this->is_debug_mode_enabled() && $this->can_view_detailed_debug() ) {
			$response['debug'] = array(
				'exception_class' => get_class( $exception ),
				'file'            => basename( $exception->getFile() ),
				'line'            => $exception->getLine(),
			);
		}

		return new WP_REST_Response( $response, $exception->get_http_status() );
	}

	/**
	 * Format a generic exception into a REST response.
	 *
	 * @since 1.4.0
	 *
	 * @param \Throwable $exception     The exception to format.
	 * @param bool       $include_debug Whether to include debug information.
	 * @return WP_REST_Response The formatted error response.
	 */
	protected function format_generic_exception_response( \Throwable $exception, bool $include_debug ): WP_REST_Response {
		$response = array(
			'success' => false,
			'data'    => null,
			'message' => $include_debug ? $exception->getMessage() : __( 'An unexpected error occurred.', 'wp-admin-health-suite' ),
			'code'    => 'internal_error',
		);

		if ( $include_debug ) {
			$response['debug'] = array(
				'exception_class' => get_class( $exception ),
				'file'            => basename( $exception->getFile() ),
				'line'            => $exception->getLine(),
			);
		}

		return new WP_REST_Response( $response, 500 );
	}

	/**
	 * Execute a callback with exception handling.
	 *
	 * Wraps callback execution in try-catch to convert exceptions to REST responses.
	 * Use this in controller methods to get automatic exception handling.
	 *
	 * Example usage:
	 * ```php
	 * public function get_item( $request ) {
	 *     return $this->execute_with_exception_handling( function() use ( $request ) {
	 *         $id = $request->get_param( 'id' );
	 *         $item = $this->service->find( $id );
	 *         if ( ! $item ) {
	 *             throw NotFoundException::resource( 'item', $id );
	 *         }
	 *         return $this->format_response( true, $item, 'Item retrieved.' );
	 *     } );
	 * }
	 * ```
	 *
	 * @since 1.4.0
	 *
	 * @param callable $callback The callback to execute.
	 * @return WP_REST_Response The response from callback or error response.
	 */
	protected function execute_with_exception_handling( callable $callback ): WP_REST_Response {
		try {
			$result = $callback();

			// If callback returns WP_Error, convert it.
			if ( is_wp_error( $result ) ) {
				$error_data = $result->get_error_data();
				$status     = isset( $error_data['status'] ) ? (int) $error_data['status'] : 400;
				return $this->format_error_response( $result, $status );
			}

			// If callback returns a WP_REST_Response, use it directly.
			if ( $result instanceof WP_REST_Response ) {
				return $result;
			}

			// Otherwise, wrap the result in a success response.
			return $this->format_response( true, $result );
		} catch ( WPAdminHealthException $e ) {
			// Log the exception for debugging.
			$this->log_exception( $e );
			return $this->handle_exception( $e );
		} catch ( \Throwable $e ) {
			// Log unexpected exceptions.
			$this->log_exception( $e, 'error' );
			return $this->handle_exception( $e );
		}
	}

	/**
	 * Log an exception.
	 *
	 * @since 1.4.0
	 *
	 * @param \Throwable $exception The exception to log.
	 * @param string     $level     Log level ('debug' or 'error').
	 * @return void
	 */
	protected function log_exception( \Throwable $exception, string $level = 'debug' ): void {
		// Only log if debug mode is enabled or it's an error.
		if ( 'error' !== $level && ! $this->is_debug_mode_enabled() ) {
			return;
		}

		$context = array(
			'exception' => get_class( $exception ),
			'message'   => $exception->getMessage(),
			'file'      => $exception->getFile(),
			'line'      => $exception->getLine(),
		);

		if ( $exception instanceof WPAdminHealthException ) {
			$context['code']        = $exception->getCode();
			$context['http_status'] = $exception->get_http_status();
			$context['context']     = $exception->get_context();
		}

		// Use error_log for now. Can be replaced with proper logging system.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[WP Admin Health] %s exception: %s in %s:%d',
				ucfirst( $level ),
				$exception->getMessage(),
				basename( $exception->getFile() ),
				$exception->getLine()
			)
		);
	}

	/**
	 * Get collection parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
				'minimum'           => 1,
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to be returned in result set.', 'wp-admin-health-suite' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Check if safe mode is enabled.
	 *
	 * When safe mode is enabled, all destructive operations should return
	 * preview data only without actually modifying anything.
	 *
	 * @return bool True if safe mode is enabled, false otherwise.
	 */
	protected function is_safe_mode_enabled() {
		return $this->get_settings()->is_safe_mode_enabled();
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * When debug mode is enabled, extra logging and query time information
	 * should be included in responses.
	 *
	 * @return bool True if debug mode is enabled, false otherwise.
	 */
	protected function is_debug_mode_enabled() {
		return $this->get_settings()->is_debug_mode_enabled();
	}
}
