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
	 * Constructor.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		$this->config = $this->get_default_config();
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
	 * {@inheritdoc}
	 */
	public function get( string $key, $default = null ) {
		$keys  = explode( '.', $key );
		$value = $this->config;

		foreach ( $keys as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has( string $key ): bool {
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
}
