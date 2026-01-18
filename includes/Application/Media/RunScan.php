<?php
/**
 * Run Scan Use Case
 *
 * Application service for orchestrating media library scan operations.
 *
 * @package WPAdminHealth\Application\Media
 */

namespace WPAdminHealth\Application\Media;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;
use WPAdminHealth\Contracts\ReferenceFinderInterface;
use WPAdminHealth\Contracts\ExclusionsInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Exceptions\ValidationException;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class RunScan
 *
 * Orchestrates media library scan operations including duplicate detection,
 * unused media identification, large file detection, alt text checking,
 * and broken reference detection.
 *
 * This use-case class serves as the application layer between REST controllers
 * and domain services, providing a clean interface for media scan operations.
 *
 * @since 1.4.0
 */
class RunScan {

	/**
	 * Valid scan types.
	 *
	 * @var array
	 */
	const VALID_TYPES = array( 'full', 'duplicates', 'large_files', 'alt_text', 'unused', 'summary' );

	/**
	 * Valid duplicate detection methods.
	 *
	 * @var array
	 */
	const VALID_DUPLICATE_METHODS = array( 'hash', 'filename', 'both' );

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface
	 */
	private SettingsInterface $settings;

	/**
	 * Scanner instance.
	 *
	 * @var ScannerInterface
	 */
	private ScannerInterface $scanner;

	/**
	 * Duplicate detector instance.
	 *
	 * @var DuplicateDetectorInterface
	 */
	private DuplicateDetectorInterface $duplicate_detector;

	/**
	 * Large files detector instance.
	 *
	 * @var LargeFilesInterface
	 */
	private LargeFilesInterface $large_files;

	/**
	 * Alt text checker instance.
	 *
	 * @var AltTextCheckerInterface
	 */
	private AltTextCheckerInterface $alt_text_checker;

	/**
	 * Reference finder instance.
	 *
	 * @var ReferenceFinderInterface
	 */
	private ReferenceFinderInterface $reference_finder;

	/**
	 * Exclusions manager instance.
	 *
	 * @var ExclusionsInterface
	 */
	private ExclusionsInterface $exclusions;

	/**
	 * Activity logger instance.
	 *
	 * @var ActivityLoggerInterface|null
	 */
	private ?ActivityLoggerInterface $activity_logger;

	/**
	 * Constructor.
	 *
	 * @since 1.4.0
	 *
	 * @param SettingsInterface            $settings           Settings instance.
	 * @param ScannerInterface             $scanner            Scanner instance.
	 * @param DuplicateDetectorInterface   $duplicate_detector Duplicate detector instance.
	 * @param LargeFilesInterface          $large_files        Large files detector instance.
	 * @param AltTextCheckerInterface      $alt_text_checker   Alt text checker instance.
	 * @param ReferenceFinderInterface     $reference_finder   Reference finder instance.
	 * @param ExclusionsInterface          $exclusions         Exclusions manager instance.
	 * @param ActivityLoggerInterface|null $activity_logger    Optional activity logger instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		ScannerInterface $scanner,
		DuplicateDetectorInterface $duplicate_detector,
		LargeFilesInterface $large_files,
		AltTextCheckerInterface $alt_text_checker,
		ReferenceFinderInterface $reference_finder,
		ExclusionsInterface $exclusions,
		?ActivityLoggerInterface $activity_logger = null
	) {
		$this->settings           = $settings;
		$this->scanner            = $scanner;
		$this->duplicate_detector = $duplicate_detector;
		$this->large_files        = $large_files;
		$this->alt_text_checker   = $alt_text_checker;
		$this->reference_finder   = $reference_finder;
		$this->exclusions         = $exclusions;
		$this->activity_logger    = $activity_logger;
	}

	/**
	 * Execute the media scan operation.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Scan options.
	 *                       - type: string (required) - One of 'full', 'duplicates', 'large_files', 'alt_text', 'unused', 'summary'.
	 *                       - safe_mode: bool - Override safe mode setting (preview only).
	 *                       - options: array - Type-specific options.
	 * @return array Result of the scan operation.
	 * @throws ValidationException If the scan type is invalid.
	 */
	public function execute( array $options = array() ): array {
		$type = $options['type'] ?? 'summary';

		if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
			throw ValidationException::invalid_param(
				'type',
				$type,
				sprintf(
					'Must be one of: %s',
					implode( ', ', self::VALID_TYPES )
				)
			);
		}

		// Determine safe mode - options override takes precedence.
		$safe_mode = $this->determine_safe_mode( $options );

		// Get type-specific options.
		$type_options = $options['options'] ?? array();
		if ( ! is_array( $type_options ) ) {
			$type_options = array();
		}

		// Record start time for performance tracking.
		$start_time = microtime( true );

		// Execute scan based on type.
		$result = $this->execute_scan_by_type( $type, $type_options, $safe_mode );

		// Add execution time.
		$result['scan_time_ms'] = round( ( microtime( true ) - $start_time ) * 1000 );

		// Add safe mode indicators if applicable.
		if ( $safe_mode ) {
			$result['safe_mode']    = true;
			$result['preview_only'] = true;
		}

		// Log activity (only if not in safe mode or if logging preview is desired).
		if ( ! $safe_mode ) {
			$this->log_scan_activity( $type, $result );
		}

		return $result;
	}

	/**
	 * Execute scan for a specific type.
	 *
	 * Convenience method for executing a single scan type directly.
	 *
	 * @since 1.4.0
	 *
	 * @param string $type      Scan type.
	 * @param array  $options   Type-specific options.
	 * @param bool   $safe_mode Whether to run in safe mode (preview only).
	 * @return array Scan result.
	 */
	public function execute_by_type( string $type, array $options = array(), ?bool $safe_mode = null ): array {
		return $this->execute(
			array(
				'type'      => $type,
				'options'   => $options,
				'safe_mode' => $safe_mode,
			)
		);
	}

	/**
	 * Get media library summary.
	 *
	 * @since 1.4.0
	 *
	 * @return array Media summary statistics.
	 */
	public function get_summary(): array {
		$summary = $this->scanner->get_media_summary();

		return array(
			'type'            => 'summary',
			'total_count'     => $summary['total_count'] ?? 0,
			'total_size'      => $summary['total_size'] ?? 0,
			'unused_count'    => $summary['unused_count'] ?? 0,
			'unused_size'     => $summary['unused_size'] ?? 0,
			'duplicate_count' => $summary['duplicate_count'] ?? 0,
			'large_count'     => $summary['large_count'] ?? 0,
		);
	}

	/**
	 * Scan for duplicate media files.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Scan options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Scan results.
	 */
	public function scan_duplicates( array $options = array(), bool $safe_mode = false ): array {
		$method = isset( $options['method'] ) && in_array( $options['method'], self::VALID_DUPLICATE_METHODS, true )
			? $options['method']
			: 'hash';

		$include_details = isset( $options['include_details'] ) ? (bool) $options['include_details'] : true;

		// Find duplicates.
		$duplicates = $this->duplicate_detector->find_duplicates( array( 'method' => $method ) );

		// Apply exclusions filter to each group.
		$filtered_duplicates = array();
		$total_items         = 0;
		$excluded_count      = 0;

		foreach ( $duplicates as $key => $group ) {
			$filtered_group = $this->exclusions->filter_excluded( $group );
			$excluded_count += count( $group ) - count( $filtered_group );

			// Only include groups with 2+ items after filtering.
			if ( count( $filtered_group ) >= 2 ) {
				$filtered_duplicates[ $key ] = $filtered_group;
				$total_items                += count( $filtered_group );
			}
		}

		// Get potential savings.
		$savings = $this->duplicate_detector->get_potential_savings();

		$result = array(
			'type'              => 'duplicates',
			'method'            => $method,
			'groups_count'      => count( $filtered_duplicates ),
			'total_items'       => $total_items,
			'excluded_count'    => $excluded_count,
			'potential_savings' => $savings,
		);

		// Include detailed groups if requested.
		if ( $include_details ) {
			$result['groups'] = $this->duplicate_detector->get_duplicate_groups();
		}

		return $result;
	}

	/**
	 * Scan for large media files.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Scan options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Scan results.
	 */
	public function scan_large_files( array $options = array(), bool $safe_mode = false ): array {
		$threshold_kb = isset( $options['threshold_kb'] )
			? absint( $options['threshold_kb'] )
			: absint( $this->settings->get_setting( 'large_file_threshold', 500 ) );

		$include_suggestions = isset( $options['include_suggestions'] ) ? (bool) $options['include_suggestions'] : false;

		// Find large files.
		$large_files = $this->large_files->find_large_files( $threshold_kb );

		// Apply exclusions filter.
		$large_file_ids = array_column( $large_files, 'id' );
		$filtered_ids   = $this->exclusions->filter_excluded( $large_file_ids );

		// Filter the results.
		$filtered_files = array_filter(
			$large_files,
			function ( $file ) use ( $filtered_ids ) {
				return in_array( $file['id'], $filtered_ids, true );
			}
		);
		$filtered_files = array_values( $filtered_files );

		// Calculate total size.
		$total_size = array_sum( array_column( $filtered_files, 'size' ) );

		$result = array(
			'type'           => 'large_files',
			'threshold_kb'   => $threshold_kb,
			'count'          => count( $filtered_files ),
			'excluded_count' => count( $large_files ) - count( $filtered_files ),
			'total_size'     => $total_size,
			'files'          => $filtered_files,
		);

		// Include optimization suggestions if requested.
		if ( $include_suggestions ) {
			$result['suggestions']  = $this->large_files->get_optimization_suggestions();
			$result['distribution'] = $this->large_files->get_size_distribution();
		}

		return $result;
	}

	/**
	 * Scan for images missing alt text.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Scan options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Scan results.
	 */
	public function scan_alt_text( array $options = array(), bool $safe_mode = false ): array {
		$limit               = isset( $options['limit'] ) ? absint( $options['limit'] ) : 100;
		$include_suggestions = isset( $options['include_suggestions'] ) ? (bool) $options['include_suggestions'] : false;

		// Get coverage statistics.
		$coverage = $this->alt_text_checker->get_alt_text_coverage();

		// Find images missing alt text.
		$missing = $this->alt_text_checker->find_missing_alt_text( $limit );

		// Apply exclusions filter.
		$missing_ids  = array_column( $missing, 'id' );
		$filtered_ids = $this->exclusions->filter_excluded( $missing_ids );

		// Filter the results.
		$filtered_missing = array_filter(
			$missing,
			function ( $item ) use ( $filtered_ids ) {
				return in_array( $item['id'], $filtered_ids, true );
			}
		);
		$filtered_missing = array_values( $filtered_missing );

		$result = array(
			'type'           => 'alt_text',
			'coverage'       => $coverage,
			'missing_count'  => count( $filtered_missing ),
			'excluded_count' => count( $missing ) - count( $filtered_missing ),
			'missing'        => $filtered_missing,
		);

		// Include suggestions if requested.
		if ( $include_suggestions && ! empty( $filtered_ids ) ) {
			$result['suggestions'] = $this->alt_text_checker->bulk_suggest_alt_text( $filtered_ids );
		}

		return $result;
	}

	/**
	 * Scan for unused media files.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Scan options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Scan results.
	 */
	public function scan_unused( array $options = array(), bool $safe_mode = false ): array {
		$batch_size = isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 100;
		$offset     = isset( $options['offset'] ) ? absint( $options['offset'] ) : 0;

		// Scan for unused media.
		$scan_result = $this->scanner->scan_unused_media( $batch_size, $offset );

		$unused_ids = $scan_result['unused'] ?? array();

		// Apply exclusions filter.
		$filtered_ids = $this->exclusions->filter_excluded( $unused_ids );

		$result = array(
			'type'           => 'unused',
			'scanned'        => $scan_result['scanned'] ?? 0,
			'total'          => $scan_result['total'] ?? 0,
			'has_more'       => $scan_result['has_more'] ?? false,
			'unused_count'   => count( $filtered_ids ),
			'excluded_count' => count( $unused_ids ) - count( $filtered_ids ),
			'unused_ids'     => $filtered_ids,
			'batch_size'     => $batch_size,
			'offset'         => $offset,
			'next_offset'    => $offset + $batch_size,
		);

		return $result;
	}

	/**
	 * Run a full media scan (all types).
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Scan options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Combined scan results.
	 */
	public function scan_full( array $options = array(), bool $safe_mode = false ): array {
		$summary    = $this->get_summary();
		$duplicates = $this->scan_duplicates( $options['duplicates'] ?? array(), $safe_mode );
		$large      = $this->scan_large_files( $options['large_files'] ?? array(), $safe_mode );
		$alt_text   = $this->scan_alt_text( $options['alt_text'] ?? array(), $safe_mode );

		return array(
			'type'        => 'full',
			'summary'     => $summary,
			'duplicates'  => array(
				'groups_count'      => $duplicates['groups_count'],
				'total_items'       => $duplicates['total_items'],
				'potential_savings' => $duplicates['potential_savings'],
			),
			'large_files' => array(
				'count'      => $large['count'],
				'total_size' => $large['total_size'],
			),
			'alt_text'    => array(
				'coverage'      => $alt_text['coverage'],
				'missing_count' => $alt_text['missing_count'],
			),
		);
	}

	/**
	 * Get exclusions count and list.
	 *
	 * @since 1.4.0
	 *
	 * @return array Exclusions data.
	 */
	public function get_exclusions_info(): array {
		$exclusions = $this->exclusions->get_exclusions();

		return array(
			'count'      => count( $exclusions ),
			'exclusions' => $exclusions,
		);
	}

	/**
	 * Check if safe mode is enabled.
	 *
	 * @since 1.4.0
	 *
	 * @return bool True if safe mode is enabled.
	 */
	public function is_safe_mode_enabled(): bool {
		return $this->settings->is_safe_mode_enabled();
	}

	/**
	 * Determine safe mode from options or settings.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Execution options.
	 * @return bool Whether to run in safe mode.
	 */
	private function determine_safe_mode( array $options ): bool {
		// Options override takes precedence if explicitly set.
		if ( isset( $options['safe_mode'] ) ) {
			return (bool) $options['safe_mode'];
		}

		// Fall back to settings.
		return $this->is_safe_mode_enabled();
	}

	/**
	 * Execute scan by type.
	 *
	 * @since 1.4.0
	 *
	 * @param string $type         Scan type.
	 * @param array  $type_options Type-specific options.
	 * @param bool   $safe_mode    Whether safe mode is enabled.
	 * @return array Scan results.
	 */
	private function execute_scan_by_type( string $type, array $type_options, bool $safe_mode ): array {
		switch ( $type ) {
			case 'full':
				return $this->scan_full( $type_options, $safe_mode );

			case 'duplicates':
				return $this->scan_duplicates( $type_options, $safe_mode );

			case 'large_files':
				return $this->scan_large_files( $type_options, $safe_mode );

			case 'alt_text':
				return $this->scan_alt_text( $type_options, $safe_mode );

			case 'unused':
				return $this->scan_unused( $type_options, $safe_mode );

			case 'summary':
				return $this->get_summary();

			default:
				// This should never be reached due to validation in execute(),
				// but is kept for defensive programming.
				throw ValidationException::invalid_param(
					'type',
					$type,
					sprintf(
						'Must be one of: %s',
						implode( ', ', self::VALID_TYPES )
					)
				);
		}
	}

	/**
	 * Log scan activity.
	 *
	 * @since 1.4.0
	 *
	 * @param string $type   Scan type.
	 * @param array  $result Scan result.
	 * @return void
	 */
	private function log_scan_activity( string $type, array $result ): void {
		if ( null === $this->activity_logger ) {
			return;
		}

		$this->activity_logger->log_media_operation( 'scan_' . $type, $result );
	}
}
