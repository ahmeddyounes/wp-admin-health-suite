<?php
/**
 * Generate Recommendations Use Case
 *
 * Application service for orchestrating AI-powered recommendation generation.
 *
 * @package WPAdminHealth\Application\AI
 */

namespace WPAdminHealth\Application\AI;

use WPAdminHealth\AI\Recommendations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class GenerateRecommendations
 *
 * Orchestrates AI-powered recommendation generation based on database,
 * media, and performance analysis results.
 *
 * This use-case class serves as the application layer between REST controllers
 * and domain services, providing a clean interface for recommendation generation.
 *
 * @since 1.4.0
 */
class GenerateRecommendations {

	/**
	 * Recommendations engine.
	 *
	 * @var Recommendations
	 */
	private Recommendations $recommendations;

	/**
	 * Constructor.
	 *
	 * @since 1.4.0
	 *
	 * @param Recommendations $recommendations Recommendations engine.
	 */
	public function __construct( Recommendations $recommendations ) {
		$this->recommendations = $recommendations;
	}

	/**
	 * Execute the recommendation generation operation.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Generation options.
	 *                       - force_refresh: bool Whether to bypass cached recommendations.
	 * @return array Operation result.
	 */
	public function execute( array $options = array() ): array {
		$options       = wp_parse_args( $options, array( 'force_refresh' => false ) );
		$force_refresh = (bool) ( $options['force_refresh'] ?? false );

		try {
			$recommendations = $this->recommendations->generate_recommendations( $force_refresh );
			if ( ! is_array( $recommendations ) ) {
				$recommendations = array();
			}

			return array(
				'success' => true,
				'data'    => array(
					'recommendations' => array_values( $recommendations ),
				),
				'message' => empty( $recommendations ) ? 'No recommendations available.' : 'Recommendations generated.',
			);
		} catch ( \Throwable $e ) {
			return array(
				'success' => false,
				'data'    => array(
					'recommendations' => array(),
				),
				'message' => $e->getMessage(),
			);
		}
	}
}
