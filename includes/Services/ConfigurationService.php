<?php
/**
 * Configuration Service
 *
 * Centralized configuration management service.
 *
 * @package WPAdminHealth\Services
 */

namespace WPAdminHealth\Services;

use WPAdminHealth\Contracts\ConfigurationInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class ConfigurationService
 *
 * Provides centralized access to configuration values, eliminating
 * hardcoded magic numbers and strings scattered throughout the codebase.
 *
 * Supports:
 * - Environment-specific settings (production, staging, development, local)
 * - Runtime configuration changes via set()
 * - WordPress filters for extensibility
 * - Constant overrides via WPHA_CONFIG_* constants
 *
 * @since 1.3.0
 */
class ConfigurationService implements ConfigurationInterface {

	/**
	 * Configuration values.
	 *
	 * @var array
	 */
	private array $config;

	/**
	 * Runtime overrides.
	 *
	 * @var array
	 */
	private array $runtime_overrides = array();

	/**
	 * Current environment.
	 *
	 * @var string
	 */
	private string $environment;

	/**
	 * Valid environments.
	 *
	 * @var array<string>
	 */
	private const VALID_ENVIRONMENTS = array( 'production', 'staging', 'development', 'local' );

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		$this->environment = $this->detect_environment();
		$this->config      = $this->build_config();
	}

	/**
	 * Detect the current environment.
	 *
	 * Checks in order:
	 * 1. WPHA_ENVIRONMENT constant
	 * 2. WP_ENVIRONMENT_TYPE constant (WordPress 5.5+)
	 * 3. Defaults to 'production'
	 *
	 * @since 1.3.0
	 *
	 * @return string Environment name.
	 */
	private function detect_environment(): string {
		// Check for plugin-specific constant first.
		if ( defined( 'WPHA_ENVIRONMENT' ) ) {
			$env = strtolower( (string) WPHA_ENVIRONMENT );
			if ( in_array( $env, self::VALID_ENVIRONMENTS, true ) ) {
				return $env;
			}
		}

		// Check WordPress environment type (5.5+).
		if ( function_exists( 'wp_get_environment_type' ) ) {
			$wp_env = wp_get_environment_type();
			if ( in_array( $wp_env, self::VALID_ENVIRONMENTS, true ) ) {
				return $wp_env;
			}
		}

		// Default to production for safety.
		return 'production';
	}

	/**
	 * Build configuration by merging defaults with environment-specific overrides.
	 *
	 * @since 1.3.0
	 *
	 * @return array Merged configuration.
	 */
	private function build_config(): array {
		$defaults = $this->get_default_config();
		$env_overrides = $this->get_environment_overrides();
		$constant_overrides = $this->get_constant_overrides();

		// Merge: defaults <- env overrides <- constant overrides.
		$config = $this->array_merge_recursive_distinct( $defaults, $env_overrides );
		$config = $this->array_merge_recursive_distinct( $config, $constant_overrides );

		/**
		 * Filter the entire configuration array.
		 *
		 * @since 1.3.0
		 *
		 * @param array  $config      Configuration values.
		 * @param string $environment Current environment.
		 */
		return apply_filters( 'wpha_configuration', $config, $this->environment );
	}

	/**
	 * Get default configuration values.
	 *
	 * @since 1.3.0
	 *
	 * @return array Default configuration.
	 */
	private function get_default_config(): array {
		return array(
			'media'       => array(
				'batch_size'           => 50,
				'large_file_threshold' => 1024 * 1024, // 1MB in bytes.
				'scan_progress_ttl'    => HOUR_IN_SECONDS,
				'scan_results_ttl'     => DAY_IN_SECONDS,
				'retention_days'       => 30,
			),
			'database'    => array(
				'batch_size'       => 100,
				'analysis_ttl'     => 5 * MINUTE_IN_SECONDS,
				'optimization_ttl' => HOUR_IN_SECONDS,
				'log_ttl_days'     => 30,
			),
			'performance' => array(
				'cache_ttl'            => 5 * MINUTE_IN_SECONDS,
				'profiler_ttl'         => DAY_IN_SECONDS,
				'query_log_ttl_days'   => 7,
				'slow_query_threshold' => 0.05, // 50ms in seconds.
			),
			'cache'       => array(
				'default_ttl'      => HOUR_IN_SECONDS,
				'health_score_ttl' => HOUR_IN_SECONDS,
				'ai_cache_ttl'     => DAY_IN_SECONDS,
			),
			'api'         => array(
				'rate_limit_ttl'   => MINUTE_IN_SECONDS,
				'default_per_page' => 20,
				'max_per_page'     => 100,
			),
			'scheduler'   => array(
				'daily_interval'   => DAY_IN_SECONDS,
				'weekly_interval'  => 7 * DAY_IN_SECONDS,
				'monthly_interval' => 30 * DAY_IN_SECONDS,
			),
		);
	}

	/**
	 * Get environment-specific configuration overrides.
	 *
	 * @since 1.3.0
	 *
	 * @return array Environment overrides.
	 */
	private function get_environment_overrides(): array {
		$overrides = array(
			'development' => array(
				'performance' => array(
					'cache_ttl'            => MINUTE_IN_SECONDS, // Shorter cache for dev.
					'slow_query_threshold' => 0.1, // More lenient in dev.
				),
				'cache'       => array(
					'default_ttl'      => MINUTE_IN_SECONDS,
					'health_score_ttl' => MINUTE_IN_SECONDS,
				),
			),
			'local'       => array(
				'performance' => array(
					'cache_ttl'            => 30, // 30 seconds.
					'slow_query_threshold' => 0.2, // Very lenient locally.
				),
				'cache'       => array(
					'default_ttl'      => 30,
					'health_score_ttl' => 30,
				),
			),
			'staging'     => array(
				'performance' => array(
					'slow_query_threshold' => 0.075, // Slightly more lenient.
				),
			),
			'production'  => array(), // Use defaults.
		);

		/**
		 * Filter environment-specific overrides.
		 *
		 * @since 1.3.0
		 *
		 * @param array  $overrides   Environment overrides.
		 * @param string $environment Current environment.
		 */
		$overrides = apply_filters( 'wpha_configuration_environment_overrides', $overrides, $this->environment );

		return $overrides[ $this->environment ] ?? array();
	}

	/**
	 * Get configuration overrides from constants.
	 *
	 * Supports constants in format: WPHA_CONFIG_SECTION_KEY
	 * Example: WPHA_CONFIG_MEDIA_BATCH_SIZE = 100
	 *
	 * @since 1.3.0
	 *
	 * @return array Constant overrides.
	 */
	private function get_constant_overrides(): array {
		$overrides = array();

		$constant_map = array(
			'WPHA_CONFIG_MEDIA_BATCH_SIZE'           => array( 'media', 'batch_size' ),
			'WPHA_CONFIG_MEDIA_LARGE_FILE_THRESHOLD' => array( 'media', 'large_file_threshold' ),
			'WPHA_CONFIG_MEDIA_RETENTION_DAYS'       => array( 'media', 'retention_days' ),
			'WPHA_CONFIG_DATABASE_BATCH_SIZE'        => array( 'database', 'batch_size' ),
			'WPHA_CONFIG_DATABASE_LOG_TTL_DAYS'      => array( 'database', 'log_ttl_days' ),
			'WPHA_CONFIG_PERFORMANCE_CACHE_TTL'      => array( 'performance', 'cache_ttl' ),
			'WPHA_CONFIG_PERFORMANCE_SLOW_QUERY'     => array( 'performance', 'slow_query_threshold' ),
			'WPHA_CONFIG_CACHE_DEFAULT_TTL'          => array( 'cache', 'default_ttl' ),
			'WPHA_CONFIG_API_RATE_LIMIT_TTL'         => array( 'api', 'rate_limit_ttl' ),
			'WPHA_CONFIG_API_DEFAULT_PER_PAGE'       => array( 'api', 'default_per_page' ),
			'WPHA_CONFIG_API_MAX_PER_PAGE'           => array( 'api', 'max_per_page' ),
		);

		foreach ( $constant_map as $constant => $path ) {
			if ( defined( $constant ) ) {
				$section = $path[0];
				$key     = $path[1];

				if ( ! isset( $overrides[ $section ] ) ) {
					$overrides[ $section ] = array();
				}

				$overrides[ $section ][ $key ] = constant( $constant );
			}
		}

		return $overrides;
	}

	/**
	 * Recursively merge arrays, replacing values instead of appending.
	 *
	 * @since 1.3.0
	 *
	 * @param array $array1 Base array.
	 * @param array $array2 Array to merge.
	 * @return array Merged array.
	 */
	private function array_merge_recursive_distinct( array $array1, array $array2 ): array {
		$merged = $array1;

		foreach ( $array2 as $key => $value ) {
			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = $this->array_merge_recursive_distinct( $merged[ $key ], $value );
			} else {
				$merged[ $key ] = $value;
			}
		}

		return $merged;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( string $key, $default = null ) {
		// Check runtime overrides first.
		if ( $this->has_runtime_override( $key ) ) {
			return $this->get_runtime_override( $key );
		}

		$keys  = explode( '.', $key );
		$value = $this->config;

		foreach ( $keys as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}
			$value = $value[ $segment ];
		}

		/**
		 * Filter individual configuration value.
		 *
		 * @since 1.3.0
		 *
		 * @param mixed  $value       Configuration value.
		 * @param string $key         Configuration key.
		 * @param string $environment Current environment.
		 */
		return apply_filters( 'wpha_configuration_value', $value, $key, $this->environment );
	}

	/**
	 * {@inheritdoc}
	 */
	public function set( string $key, $value ): void {
		$this->runtime_overrides[ $key ] = $value;

		/**
		 * Action fired when a configuration value is set at runtime.
		 *
		 * @since 1.3.0
		 *
		 * @param string $key   Configuration key.
		 * @param mixed  $value New value.
		 */
		do_action( 'wpha_configuration_set', $key, $value );
	}

	/**
	 * Check if a runtime override exists for a key.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key Configuration key.
	 * @return bool True if override exists.
	 */
	private function has_runtime_override( string $key ): bool {
		return array_key_exists( $key, $this->runtime_overrides );
	}

	/**
	 * Get a runtime override value.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key Configuration key.
	 * @return mixed Override value.
	 */
	private function get_runtime_override( string $key ) {
		return $this->runtime_overrides[ $key ];
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( string $key ): bool {
		// Check runtime overrides first.
		if ( $this->has_runtime_override( $key ) ) {
			return true;
		}

		$keys  = explode( '.', $key );
		$value = $this->config;

		foreach ( $keys as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return false;
			}
			$value = $value[ $segment ];
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function all(): array {
		return $this->config;
	}

	/**
	 * {@inheritdoc}
	 */
	public function media(): array {
		return $this->config['media'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function database(): array {
		return $this->config['database'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function performance(): array {
		return $this->config['performance'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function cache(): array {
		return $this->config['cache'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_environment(): string {
		return $this->environment;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_environment( string $environment ): bool {
		return $this->environment === strtolower( $environment );
	}
}
