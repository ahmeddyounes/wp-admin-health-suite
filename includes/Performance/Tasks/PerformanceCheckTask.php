<?php
/**
 * Performance Check Task
 *
 * Scheduled task for performance monitoring and analysis.
 *
 * @package WPAdminHealth\Performance\Tasks
 */

namespace WPAdminHealth\Performance\Tasks;

use WPAdminHealth\Scheduler\AbstractScheduledTask;
use WPAdminHealth\Application\Performance\RunHealthCheck;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class PerformanceCheckTask
 *
 * Performs scheduled performance analysis.
 *
 * @since 1.2.0
 * @since 1.4.0 Refactored to use RunHealthCheck application service.
 * @since 1.7.0 Updated to use TaskResult DTO.
 */
class PerformanceCheckTask extends AbstractScheduledTask {

	/**
	 * Task identifier.
	 *
	 * @var string
	 */
	protected string $task_id = 'performance_check';

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected string $task_name = 'Performance Check';

	/**
	 * Task description.
	 *
	 * @var string
	 */
	protected string $description = 'Analyze autoload options, slow queries, and plugin performance.';

	/**
	 * Default frequency.
	 *
	 * @var string
	 */
	protected string $default_frequency = 'daily';

	/**
	 * Enabled option key.
	 *
	 * @var string
	 */
	protected string $enabled_option_key = 'enable_scheduled_performance_check';

	/**
	 * Health check application service.
	 *
	 * @since 1.4.0
	 * @var RunHealthCheck
	 */
	private RunHealthCheck $health_check;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @since 1.4.0 Simplified to use RunHealthCheck application service.
	 *
	 * @param RunHealthCheck $health_check Health check application service.
	 */
	public function __construct( RunHealthCheck $health_check ) {
		$this->health_check = $health_check;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since 1.2.0
	 * @since 1.4.0 Delegates to RunHealthCheck application service.
	 */
	public function execute( array $options = array() ): array {
		$this->log( 'Starting performance check task' );

		// Merge task options with settings.
		$settings   = get_option( 'wpha_settings', array() );
		$run_options = array(
			'type'            => 'full',
			'check_autoload'  => ! empty( $settings['check_autoload'] ) || ! empty( $options['check_autoload'] ),
			'monitor_queries' => ! empty( $settings['monitor_queries'] ) || ! empty( $options['monitor_queries'] ),
			'profile_plugins' => ! empty( $settings['profile_plugins'] ) || ! empty( $options['profile_plugins'] ),
			'check_cache'     => ! empty( $settings['check_cache'] ) || ! empty( $options['check_cache'] ),
		);

		// Execute the health check via the application service.
		$check_results = $this->health_check->execute( $run_options );

		// Log results.
		if ( ! empty( $check_results['autoload'] ) ) {
			$this->log(
				sprintf(
					'Autoload analysis: %d large options found',
					$check_results['autoload']['large_options_count'] ?? 0
				)
			);
		}

		if ( ! empty( $check_results['queries'] ) ) {
			$this->log(
				sprintf(
					'Query analysis: %d slow queries found',
					$check_results['queries']['slow_query_count'] ?? 0
				)
			);
		}

		if ( ! empty( $check_results['plugins'] ) ) {
			$this->log(
				sprintf(
					'Plugin analysis: %d slow plugins found',
					$check_results['plugins']['slow_plugin_count'] ?? 0
				)
			);
		}

		$this->log(
			sprintf(
				'Performance check completed. Score: %d, Issues: %d',
				$check_results['score'] ?? 0,
				$check_results['total_issues'] ?? 0
			)
		);

		return $this->create_result(
			$check_results['total_issues'] ?? 0,
			0, // No bytes freed for performance checks.
			true
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_schema(): array {
		return array(
			'check_autoload'   => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Analyze autoload options', 'wp-admin-health-suite' ),
			),
			'monitor_queries'  => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Monitor slow queries', 'wp-admin-health-suite' ),
			),
			'profile_plugins'  => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Profile plugin performance', 'wp-admin-health-suite' ),
			),
		);
	}
}
