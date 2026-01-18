<?php
/**
 * Get Dashboard Health Score Use Case
 *
 * Application service for dashboard health score endpoint.
 *
 * @package WPAdminHealth\Application\Dashboard
 */

namespace WPAdminHealth\Application\Dashboard;

use WPAdminHealth\HealthCalculator;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class GetHealthScore
 *
 * @since 1.7.0
 */
class GetHealthScore {

	private HealthCalculator $health_calculator;

	/**
	 * @since 1.7.0
	 */
	public function __construct( HealthCalculator $health_calculator ) {
		$this->health_calculator = $health_calculator;
	}

	/**
	 * Execute health score retrieval.
	 *
	 * @since 1.7.0
	 *
	 * @param bool $force_refresh Whether to bypass cache.
	 * @return array
	 */
	public function execute( bool $force_refresh = false ): array {
		$health_data     = $this->health_calculator->calculate_overall_score( $force_refresh );
		$recommendations = $this->health_calculator->get_recommendations();

		return array(
			'score'           => $health_data['score'],
			'grade'           => $health_data['grade'],
			'factors'         => $health_data['factor_scores'],
			'recommendations' => $recommendations,
			'timestamp'       => $health_data['timestamp'],
		);
	}
}
