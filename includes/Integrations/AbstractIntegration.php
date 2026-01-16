<?php
/**
 * Abstract Integration Base Class
 *
 * Base class for all third-party plugin integrations.
 *
 * @package WPAdminHealth\Integrations
 */

namespace WPAdminHealth\Integrations;

use WPAdminHealth\Contracts\IntegrationInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Database\WpdbConnection;
use WPAdminHealth\Cache\CacheFactory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class AbstractIntegration
 *
 * Base class providing shared functionality for all integrations.
 * Eliminates code duplication and provides a consistent API.
 *
 * @since 1.1.0
 */
abstract class AbstractIntegration implements IntegrationInterface {

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	protected ConnectionInterface $connection;

	/**
	 * Cache instance.
	 *
	 * @var CacheInterface
	 */
	protected CacheInterface $cache;

	/**
	 * Whether the integration has been initialized.
	 *
	 * @var bool
	 */
	protected bool $initialized = false;

	/**
	 * Registered hooks for cleanup tracking.
	 *
	 * @var array<array{type: string, tag: string, callback: callable, priority: int}>
	 */
	protected array $registered_hooks = array();

	/**
	 * Constructor.
	 *
	 * @param ConnectionInterface|null $connection Optional database connection.
	 * @param CacheInterface|null      $cache      Optional cache instance.
	 */
	public function __construct(
		?ConnectionInterface $connection = null,
		?CacheInterface $cache = null
	) {
		$this->connection = $connection ?? new WpdbConnection();
		$this->cache      = $cache ?? CacheFactory::get_instance();
	}

	/**
	 * {@inheritdoc}
	 */
	abstract public function get_id(): string;

	/**
	 * {@inheritdoc}
	 */
	abstract public function get_name(): string;

	/**
	 * {@inheritdoc}
	 */
	abstract public function is_available(): bool;

	/**
	 * {@inheritdoc}
	 */
	abstract public function get_min_version(): string;

	/**
	 * {@inheritdoc}
	 */
	abstract public function get_current_version(): ?string;

	/**
	 * Register the integration's hooks.
	 *
	 * Subclasses should implement this method to register their specific hooks.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	abstract protected function register_hooks(): void;

	/**
	 * {@inheritdoc}
	 */
	public function is_compatible(): bool {
		$current_version = $this->get_current_version();

		if ( null === $current_version ) {
			return false;
		}

		return version_compare( $current_version, $this->get_min_version(), '>=' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		if ( ! $this->is_available() ) {
			return;
		}

		if ( ! $this->is_compatible() ) {
			$this->log_incompatibility();
			return;
		}

		$this->register_hooks();
		$this->mark_initialized();

		/**
		 * Fires after an integration has been initialized.
		 *
		 * @since 1.1.0
		 *
		 * @param IntegrationInterface $integration The integration instance.
		 */
		do_action( 'wpha_integration_initialized', $this );
	}

	/**
	 * {@inheritdoc}
	 */
	public function deactivate(): void {
		if ( ! $this->initialized ) {
			return;
		}

		$this->remove_registered_hooks();
		$this->initialized = false;

		/**
		 * Fires after an integration has been deactivated.
		 *
		 * @since 1.1.0
		 *
		 * @param IntegrationInterface $integration The integration instance.
		 */
		do_action( 'wpha_integration_deactivated', $this );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_dependencies(): array {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_priority(): int {
		return 10;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_capabilities(): array {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function has_capability( string $capability ): bool {
		return in_array( $capability, $this->get_capabilities(), true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_initialized(): bool {
		return $this->initialized;
	}

	/**
	 * Mark the integration as initialized.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	protected function mark_initialized(): void {
		$this->initialized = true;
	}

	/**
	 * Add a filter hook with tracking.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $tag      The filter hook name.
	 * @param callable $callback The callback function.
	 * @param int      $priority Optional. Priority. Default 10.
	 * @param int      $args     Optional. Number of arguments. Default 1.
	 * @return void
	 */
	protected function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $tag, $callback, $priority, $args );

		$this->registered_hooks[] = array(
			'type'     => 'filter',
			'tag'      => $tag,
			'callback' => $callback,
			'priority' => $priority,
		);
	}

	/**
	 * Add an action hook with tracking.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $tag      The action hook name.
	 * @param callable $callback The callback function.
	 * @param int      $priority Optional. Priority. Default 10.
	 * @param int      $args     Optional. Number of arguments. Default 1.
	 * @return void
	 */
	protected function add_action( string $tag, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_action( $tag, $callback, $priority, $args );

		$this->registered_hooks[] = array(
			'type'     => 'action',
			'tag'      => $tag,
			'callback' => $callback,
			'priority' => $priority,
		);
	}

	/**
	 * Remove all registered hooks.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	protected function remove_registered_hooks(): void {
		foreach ( $this->registered_hooks as $hook ) {
			if ( 'filter' === $hook['type'] ) {
				remove_filter( $hook['tag'], $hook['callback'], $hook['priority'] );
			} else {
				remove_action( $hook['tag'], $hook['callback'], $hook['priority'] );
			}
		}

		$this->registered_hooks = array();
	}

	/**
	 * Log cleanup operation to scan history table.
	 *
	 * @since 1.1.0
	 *
	 * @param string $scan_type     The type of scan/cleanup.
	 * @param int    $items_found   Number of items found.
	 * @param int    $items_cleaned Number of items cleaned.
	 * @param int    $bytes_freed   Bytes freed.
	 * @return bool True on success, false on failure.
	 */
	protected function log_cleanup( string $scan_type, int $items_found, int $items_cleaned, int $bytes_freed ): bool {
		$table_name = $this->connection->get_prefix() . 'wpha_scan_history';

		// Check if table exists.
		if ( ! $this->connection->table_exists( $table_name ) ) {
			return false;
		}

		$result = $this->connection->insert(
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

		return false !== $result;
	}

	/**
	 * Log incompatibility warning.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	protected function log_incompatibility(): void {
		$message = sprintf(
			/* translators: 1: Integration name, 2: Current version, 3: Minimum required version */
			__( 'WP Admin Health Suite: %1$s integration requires version %3$s or higher. Current version: %2$s', 'wp-admin-health-suite' ),
			$this->get_name(),
			$this->get_current_version() ?? __( 'unknown', 'wp-admin-health-suite' ),
			$this->get_min_version()
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $message );
		}
	}

	/**
	 * Log a warning message when batch processing limits are reached.
	 *
	 * This helps administrators identify when scans may be incomplete
	 * due to safety limits being hit.
	 *
	 * @since 1.1.0
	 *
	 * @param string $operation   The operation that hit the limit.
	 * @param int    $batches     Number of batches processed.
	 * @param int    $max_batches Maximum allowed batches.
	 * @param int    $batch_size  Size of each batch.
	 * @return void
	 */
	protected function log_batch_limit_warning( string $operation, int $batches, int $max_batches, int $batch_size ): void {
		$total_rows = $batches * $batch_size;

		$message = sprintf(
			/* translators: 1: Integration name, 2: Operation name, 3: Total rows, 4: Max batches, 5: Batch size */
			__( 'WP Admin Health Suite: %1$s %2$s reached safety limit after processing %3$d rows (%4$d batches of %5$d). Results may be incomplete for very large sites.', 'wp-admin-health-suite' ),
			$this->get_name(),
			$operation,
			$total_rows,
			$max_batches,
			$batch_size
		);

		// Always log this warning as it indicates potentially incomplete results.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message );

		/**
		 * Fires when batch processing limit is reached.
		 *
		 * @since 1.1.0
		 *
		 * @param string $integration_id The integration ID.
		 * @param string $operation      The operation that hit the limit.
		 * @param int    $total_rows     Total rows processed.
		 * @param int    $max_batches    Maximum batches allowed.
		 */
		do_action( 'wpha_batch_limit_reached', $this->get_id(), $operation, $total_rows, $max_batches );
	}

	/**
	 * Get cached value or compute and cache it.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $key      Cache key (will be prefixed with integration ID).
	 * @param callable $callback Callback to compute value if not cached.
	 * @param int      $ttl      Cache TTL in seconds. Default 300 (5 minutes).
	 * @return mixed Cached or computed value.
	 */
	protected function remember( string $key, callable $callback, int $ttl = 300 ) {
		$cache_key = $this->get_cache_key( $key );
		return $this->cache->remember( $cache_key, $callback, $ttl );
	}

	/**
	 * Get integration-specific cache key.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key The base cache key.
	 * @return string Prefixed cache key.
	 */
	protected function get_cache_key( string $key ): string {
		return 'integration_' . $this->get_id() . '_' . $key;
	}

	/**
	 * Clear integration-specific cache.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key Optional specific cache key to clear.
	 * @return bool True on success.
	 */
	protected function clear_cache( string $key = '' ): bool {
		if ( empty( $key ) ) {
			return $this->cache->clear( 'integration_' . $this->get_id() . '_' );
		}

		return $this->cache->delete( $this->get_cache_key( $key ) );
	}

	/**
	 * Check if a table exists.
	 *
	 * @since 1.1.0
	 *
	 * @param string $table Table name (without prefix).
	 * @return bool True if table exists.
	 */
	protected function table_exists( string $table ): bool {
		$full_table = $this->connection->get_prefix() . $table;
		return $this->connection->table_exists( $full_table );
	}

	/**
	 * Get cleanup opportunities for this integration.
	 *
	 * Subclasses should override this method to provide cleanup data.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, int> Array of cleanup item types and counts.
	 */
	public function get_cleanup_data(): array {
		return array();
	}

	/**
	 * Get performance insights for this integration.
	 *
	 * Subclasses should override this method to provide insights.
	 *
	 * @since 1.1.0
	 *
	 * @return array<array{type: string, category: string, title: string, description: string, action: string, severity: string}> Array of insights.
	 */
	public function get_performance_insights(): array {
		return array();
	}
}
