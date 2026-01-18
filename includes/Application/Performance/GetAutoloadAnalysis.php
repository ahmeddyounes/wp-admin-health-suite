<?php
/**
 * Get Autoload Analysis Use Case
 *
 * Application service for autoload options analysis.
 *
 * @package WPAdminHealth\Application\Performance
 */

namespace WPAdminHealth\Application\Performance;

use WPAdminHealth\Contracts\AutoloadAnalyzerInterface;
use WPAdminHealth\Contracts\ConnectionInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class GetAutoloadAnalysis
 *
 * @since 1.7.0
 */
class GetAutoloadAnalysis {

	private ConnectionInterface $connection;

	private AutoloadAnalyzerInterface $autoload_analyzer;

	/**
	 * @since 1.7.0
	 */
	public function __construct( ConnectionInterface $connection, AutoloadAnalyzerInterface $autoload_analyzer ) {
		$this->connection        = $connection;
		$this->autoload_analyzer = $autoload_analyzer;
	}

	/**
	 * Execute autoload analysis.
	 *
	 * @since 1.7.0
	 *
	 * @return array
	 */
	public function execute(): array {
		$options_table = $this->connection->get_options_table();

		$size_stats  = $this->autoload_analyzer->get_autoload_size();
		$total_size  = isset( $size_stats['total_size'] ) ? (int) $size_stats['total_size'] : 0;
		$total_count = isset( $size_stats['count'] ) ? (int) $size_stats['count'] : 0;

		$autoload_options = $this->connection->get_results(
			"SELECT option_name, LENGTH(option_value) as size\n\t\t\tFROM {$options_table}\n\t\t\tWHERE autoload = 'yes'\n\t\t\tORDER BY size DESC\n\t\t\tLIMIT 50"
		);

		$options = array();
		foreach ( $autoload_options as $option ) {
			$options[] = array(
				'name' => $option->option_name,
				'size' => (int) $option->size,
			);
		}

		return array(
			'total_size'    => $total_size,
			'total_size_mb' => isset( $size_stats['total_size_mb'] ) ? (float) $size_stats['total_size_mb'] : round( $total_size / 1024 / 1024, 2 ),
			'options'       => $options,
			'count'         => $total_count,
		);
	}
}
