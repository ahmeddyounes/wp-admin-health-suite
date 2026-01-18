<?php
/**
 * Get Query Analysis Use Case
 *
 * Application service for gathering query analysis data for REST controllers.
 *
 * @package WPAdminHealth\Application\Performance
 */

namespace WPAdminHealth\Application\Performance;

use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\QueryMonitorInterface;
use WPAdminHealth\Contracts\SettingsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class GetQueryAnalysis
 *
 * @since 1.7.0
 */
class GetQueryAnalysis {

	private SettingsInterface $settings;

	private ConnectionInterface $connection;

	private QueryMonitorInterface $query_monitor;

	/**
	 * @since 1.7.0
	 */
	public function __construct(
		SettingsInterface $settings,
		ConnectionInterface $connection,
		QueryMonitorInterface $query_monitor
	) {
		$this->settings      = $settings;
		$this->connection    = $connection;
		$this->query_monitor = $query_monitor;
	}

	/**
	 * Execute the query analysis operation.
	 *
	 * @since 1.7.0
	 *
	 * @return array{
	 *   total_queries:int,
	 *   threshold_ms:float,
	 *   savequeries:bool,
	 *   monitoring:array,
	 *   captured:array
	 * }
	 */
	public function execute(): array {
		$query_count = $this->connection->get_num_queries();

		$threshold_ms = (float) absint( $this->settings->get_setting( 'slow_query_threshold_ms', 50 ) );
		$threshold_ms = (float) max( 10, min( 500, $threshold_ms ) );

		$monitoring_enabled_in_settings = ! empty( $this->settings->get_setting( 'enable_query_monitoring', false ) )
			|| ! empty( $this->settings->get_setting( 'query_logging_enabled', false ) );

		$status      = $this->query_monitor->get_monitoring_status();
		$can_capture = $monitoring_enabled_in_settings
			&& ( isset( $status['monitoring_enabled'] )
				? (bool) $status['monitoring_enabled']
				: ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) );

		$captured = array();
		if ( $can_capture ) {
			$captured = $this->query_monitor->capture_slow_queries( $threshold_ms );
			if ( ! is_array( $captured ) ) {
				$captured = array();
			}
		}

		return array(
			'total_queries' => (int) $query_count,
			'threshold_ms'  => $threshold_ms,
			'savequeries'   => (bool) $can_capture,
			'monitoring'    => is_array( $status ) ? $status : array(),
			'captured'      => $captured,
		);
	}
}
