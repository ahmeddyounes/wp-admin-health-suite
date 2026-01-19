<?php
/**
 * Observability Event Logger
 *
 * Logs lock contention and rate-limit events for debugging and monitoring.
 *
 * @package WPAdminHealth\Services
 */

namespace WPAdminHealth\Services;

use WPAdminHealth\Contracts\SettingsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class ObservabilityEventLogger
 *
 * Provides safe logging for lock contention and rate-limit events.
 * All logging is gated behind debug mode settings to prevent performance impact
 * and avoid exposing sensitive information in production.
 *
 * Usage:
 * - Events are only logged when debug mode is enabled via settings
 * - No sensitive payloads (IPs, full request data) are logged
 * - Logs include contextual metadata for diagnosing contention issues
 *
 * @since 1.8.0
 */
class ObservabilityEventLogger {

	/**
	 * Log level constants.
	 */
	public const LEVEL_DEBUG = 'debug';
	public const LEVEL_INFO  = 'info';
	public const LEVEL_WARN  = 'warning';
	public const LEVEL_ERROR = 'error';

	/**
	 * Event type constants for lock events.
	 */
	public const EVENT_LOCK_ACQUIRED     = 'lock_acquired';
	public const EVENT_LOCK_CONTENTION   = 'lock_contention';
	public const EVENT_LOCK_RELEASED     = 'lock_released';
	public const EVENT_LOCK_TIMEOUT      = 'lock_timeout';
	public const EVENT_LOCK_STALE_RECOV  = 'lock_stale_recovery';

	/**
	 * Event type constants for rate-limit events.
	 */
	public const EVENT_RATE_LIMIT_HIT       = 'rate_limit_hit';
	public const EVENT_RATE_LIMIT_EXCEEDED  = 'rate_limit_exceeded';
	public const EVENT_RATE_LIMIT_RESET     = 'rate_limit_reset';
	public const EVENT_RATE_LIMIT_UNAVAIL   = 'rate_limit_unavailable';

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface|null
	 */
	private ?SettingsInterface $settings;

	/**
	 * Cached debug mode state to avoid repeated lookups.
	 *
	 * @var bool|null
	 */
	private ?bool $debug_enabled = null;

	/**
	 * In-memory event buffer for batch logging.
	 *
	 * @var array<array>
	 */
	private array $event_buffer = array();

	/**
	 * Maximum events to buffer before flushing.
	 *
	 * @var int
	 */
	private int $buffer_limit = 50;

	/**
	 * Constructor.
	 *
	 * @param SettingsInterface|null $settings Settings instance for checking debug mode.
	 */
	public function __construct( ?SettingsInterface $settings = null ) {
		$this->settings = $settings;
	}

	/**
	 * Register WordPress hooks for observability events.
	 *
	 * @return void
	 */
	public function register(): void {
		// Lock events.
		add_action( 'wpha_lock_acquired', array( $this, 'on_lock_acquired' ), 10, 2 );
		add_action( 'wpha_lock_contention', array( $this, 'on_lock_contention' ), 10, 2 );
		add_action( 'wpha_lock_released', array( $this, 'on_lock_released' ), 10, 2 );
		add_action( 'wpha_lock_timeout', array( $this, 'on_lock_timeout' ), 10, 2 );
		add_action( 'wpha_lock_stale_recovery', array( $this, 'on_lock_stale_recovery' ), 10, 2 );

		// Rate-limit events.
		add_action( 'wpha_rate_limit_hit', array( $this, 'on_rate_limit_hit' ), 10, 3 );
		add_action( 'wpha_rate_limit_exceeded', array( $this, 'on_rate_limit_exceeded' ), 10, 3 );
		add_action( 'wpha_rate_limit_unavailable', array( $this, 'on_rate_limit_unavailable' ), 10, 2 );

		// Flush buffer on shutdown.
		add_action( 'shutdown', array( $this, 'flush_buffer' ) );
	}

	/**
	 * Handle lock acquired event.
	 *
	 * @param string $lock_name Lock identifier (sanitized).
	 * @param array  $context   Additional context (e.g., task_id, method).
	 * @return void
	 */
	public function on_lock_acquired( string $lock_name, array $context = array() ): void {
		$this->log_event(
			self::EVENT_LOCK_ACQUIRED,
			self::LEVEL_DEBUG,
			array(
				'lock_name' => $this->sanitize_lock_name( $lock_name ),
				'method'    => $context['method'] ?? 'unknown',
			)
		);
	}

	/**
	 * Handle lock contention event (lock already held by another process).
	 *
	 * @param string $lock_name Lock identifier (sanitized).
	 * @param array  $context   Additional context (e.g., task_id, wait_time).
	 * @return void
	 */
	public function on_lock_contention( string $lock_name, array $context = array() ): void {
		$this->log_event(
			self::EVENT_LOCK_CONTENTION,
			self::LEVEL_WARN,
			array(
				'lock_name' => $this->sanitize_lock_name( $lock_name ),
				'attempts'  => $context['attempts'] ?? 0,
				'method'    => $context['method'] ?? 'unknown',
			)
		);
	}

	/**
	 * Handle lock released event.
	 *
	 * @param string $lock_name Lock identifier (sanitized).
	 * @param array  $context   Additional context.
	 * @return void
	 */
	public function on_lock_released( string $lock_name, array $context = array() ): void {
		$this->log_event(
			self::EVENT_LOCK_RELEASED,
			self::LEVEL_DEBUG,
			array(
				'lock_name' => $this->sanitize_lock_name( $lock_name ),
				'held_time' => $context['held_time'] ?? null,
			)
		);
	}

	/**
	 * Handle lock timeout event.
	 *
	 * @param string $lock_name Lock identifier (sanitized).
	 * @param array  $context   Additional context.
	 * @return void
	 */
	public function on_lock_timeout( string $lock_name, array $context = array() ): void {
		$this->log_event(
			self::EVENT_LOCK_TIMEOUT,
			self::LEVEL_WARN,
			array(
				'lock_name'    => $this->sanitize_lock_name( $lock_name ),
				'max_attempts' => $context['max_attempts'] ?? 0,
			)
		);
	}

	/**
	 * Handle stale lock recovery event.
	 *
	 * @param string $lock_name Lock identifier (sanitized).
	 * @param array  $context   Additional context.
	 * @return void
	 */
	public function on_lock_stale_recovery( string $lock_name, array $context = array() ): void {
		$this->log_event(
			self::EVENT_LOCK_STALE_RECOV,
			self::LEVEL_INFO,
			array(
				'lock_name' => $this->sanitize_lock_name( $lock_name ),
				'age'       => $context['age'] ?? 0,
			)
		);
	}

	/**
	 * Handle rate limit hit event (incremented but within limit).
	 *
	 * @param int    $user_id     Current user ID.
	 * @param int    $count       Current request count.
	 * @param int    $limit       Rate limit threshold.
	 * @return void
	 */
	public function on_rate_limit_hit( int $user_id, int $count, int $limit ): void {
		// Only log when approaching the limit (80% threshold).
		if ( $count < ( $limit * 0.8 ) ) {
			return;
		}

		$this->log_event(
			self::EVENT_RATE_LIMIT_HIT,
			self::LEVEL_INFO,
			array(
				'user_id'     => $user_id,
				'count'       => $count,
				'limit'       => $limit,
				'utilization' => round( ( $count / $limit ) * 100, 1 ) . '%',
			)
		);
	}

	/**
	 * Handle rate limit exceeded event.
	 *
	 * @param int    $user_id     Current user ID.
	 * @param int    $count       Current request count.
	 * @param int    $limit       Rate limit threshold.
	 * @return void
	 */
	public function on_rate_limit_exceeded( int $user_id, int $count, int $limit ): void {
		$this->log_event(
			self::EVENT_RATE_LIMIT_EXCEEDED,
			self::LEVEL_WARN,
			array(
				'user_id' => $user_id,
				'count'   => $count,
				'limit'   => $limit,
			)
		);
	}

	/**
	 * Handle rate limiter unavailable event.
	 *
	 * @param int   $user_id Current user ID.
	 * @param array $context Additional context.
	 * @return void
	 */
	public function on_rate_limit_unavailable( int $user_id, array $context = array() ): void {
		$this->log_event(
			self::EVENT_RATE_LIMIT_UNAVAIL,
			self::LEVEL_ERROR,
			array(
				'user_id' => $user_id,
				'reason'  => $context['reason'] ?? 'lock_failure',
			)
		);
	}

	/**
	 * Log an observability event.
	 *
	 * @param string $event_type Event type constant.
	 * @param string $level      Log level.
	 * @param array  $data       Event data (must not contain sensitive info).
	 * @return void
	 */
	public function log_event( string $event_type, string $level, array $data ): void {
		if ( ! $this->is_logging_enabled() ) {
			return;
		}

		$event = array(
			'timestamp'  => gmdate( 'Y-m-d H:i:s' ),
			'event_type' => $event_type,
			'level'      => $level,
			'data'       => $data,
		);

		/**
		 * Fires when an observability event is logged.
		 *
		 * @since 1.8.0
		 *
		 * @hook wpha_observability_event
		 *
		 * @param string $event_type Event type identifier.
		 * @param string $level      Log level (debug, info, warning, error).
		 * @param array  $data       Sanitized event data.
		 */
		do_action( 'wpha_observability_event', $event_type, $level, $data );

		$this->event_buffer[] = $event;

		// Flush if buffer is full.
		if ( count( $this->event_buffer ) >= $this->buffer_limit ) {
			$this->flush_buffer();
		}
	}

	/**
	 * Flush buffered events to the log.
	 *
	 * @return void
	 */
	public function flush_buffer(): void {
		if ( empty( $this->event_buffer ) ) {
			return;
		}

		foreach ( $this->event_buffer as $event ) {
			$this->write_log_entry( $event );
		}

		$this->event_buffer = array();
	}

	/**
	 * Write a single log entry.
	 *
	 * Uses error_log for now. In the future, this could be replaced with
	 * a proper logging service or ActivityLoggerInterface extension.
	 *
	 * @param array $event Event data.
	 * @return void
	 */
	private function write_log_entry( array $event ): void {
		$message = sprintf(
			'[WP Admin Health] [%s] %s: %s',
			strtoupper( $event['level'] ),
			$event['event_type'],
			wp_json_encode( $event['data'], JSON_UNESCAPED_SLASHES )
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message );
	}

	/**
	 * Check if observability logging is enabled.
	 *
	 * Logging is enabled when:
	 * 1. Debug mode is enabled in settings, OR
	 * 2. WPHA_DEBUG constant is defined and true, OR
	 * 3. WP_DEBUG is true (fallback for development)
	 *
	 * @return bool True if logging should occur.
	 */
	public function is_logging_enabled(): bool {
		if ( null !== $this->debug_enabled ) {
			return $this->debug_enabled;
		}

		// Check WPHA_DEBUG constant first (explicit override).
		if ( defined( 'WPHA_DEBUG' ) && WPHA_DEBUG ) {
			$this->debug_enabled = true;
			return true;
		}

		// Check settings-based debug mode.
		if ( null !== $this->settings && $this->settings->is_debug_mode_enabled() ) {
			$this->debug_enabled = true;
			return true;
		}

		// Fallback to WP_DEBUG for development environments.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$this->debug_enabled = true;
			return true;
		}

		$this->debug_enabled = false;
		return false;
	}

	/**
	 * Sanitize lock name for logging.
	 *
	 * Removes sensitive information and normalizes the lock name.
	 *
	 * @param string $lock_name Raw lock name.
	 * @return string Sanitized lock name.
	 */
	private function sanitize_lock_name( string $lock_name ): string {
		// Remove any potential user-specific data.
		// Lock names should be task identifiers, not containing sensitive info.
		return sanitize_key( substr( $lock_name, 0, 64 ) );
	}

	/**
	 * Get event statistics for debugging.
	 *
	 * @return array Event counts by type.
	 */
	public function get_event_statistics(): array {
		$stats = array(
			'buffered_count' => count( $this->event_buffer ),
			'logging_enabled' => $this->is_logging_enabled(),
		);

		return $stats;
	}

	/**
	 * Clear the event buffer without flushing.
	 *
	 * Useful for testing or resetting state.
	 *
	 * @return void
	 */
	public function clear_buffer(): void {
		$this->event_buffer = array();
	}

	/**
	 * Reset cached debug mode state.
	 *
	 * @return void
	 */
	public function reset_cache(): void {
		$this->debug_enabled = null;
	}
}
