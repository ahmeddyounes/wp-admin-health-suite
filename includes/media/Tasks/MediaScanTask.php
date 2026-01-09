<?php
/**
 * Media Scan Task
 *
 * Scheduled task for media library scanning and maintenance.
 *
 * @package WPAdminHealth\Media\Tasks
 */

namespace WPAdminHealth\Media\Tasks;

use WPAdminHealth\Scheduler\AbstractScheduledTask;
use WPAdminHealth\Contracts\ScannerInterface;
use WPAdminHealth\Contracts\DuplicateDetectorInterface;
use WPAdminHealth\Contracts\LargeFilesInterface;
use WPAdminHealth\Contracts\AltTextCheckerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class MediaScanTask
 *
 * Performs scheduled media library scans.
 *
 * @since 1.2.0
 */
class MediaScanTask extends AbstractScheduledTask {

	/**
	 * Task identifier.
	 *
	 * @var string
	 */
	protected string $task_id = 'media_scan';

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected string $task_name = 'Media Library Scan';

	/**
	 * Task description.
	 *
	 * @var string
	 */
	protected string $description = 'Scan media library for issues like duplicates, large files, and missing alt text.';

	/**
	 * Default frequency.
	 *
	 * @var string
	 */
	protected string $default_frequency = 'weekly';

	/**
	 * Enabled option key.
	 *
	 * @var string
	 */
	protected string $enabled_option_key = 'enable_scheduled_media_scan';

	/**
	 * Media scanner.
	 *
	 * @var ScannerInterface
	 */
	private ScannerInterface $scanner;

	/**
	 * Duplicate detector.
	 *
	 * @var DuplicateDetectorInterface
	 */
	private DuplicateDetectorInterface $duplicate_detector;

	/**
	 * Large files detector.
	 *
	 * @var LargeFilesInterface
	 */
	private LargeFilesInterface $large_files;

	/**
	 * Alt text checker.
	 *
	 * @var AltTextCheckerInterface
	 */
	private AltTextCheckerInterface $alt_text_checker;

	/**
	 * Constructor.
	 *
	 * @param ScannerInterface           $scanner            Media scanner.
	 * @param DuplicateDetectorInterface $duplicate_detector Duplicate detector.
	 * @param LargeFilesInterface        $large_files        Large files detector.
	 * @param AltTextCheckerInterface    $alt_text_checker   Alt text checker.
	 */
	public function __construct(
		ScannerInterface $scanner,
		DuplicateDetectorInterface $duplicate_detector,
		LargeFilesInterface $large_files,
		AltTextCheckerInterface $alt_text_checker
	) {
		$this->scanner            = $scanner;
		$this->duplicate_detector = $duplicate_detector;
		$this->large_files        = $large_files;
		$this->alt_text_checker   = $alt_text_checker;
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute( array $options = array() ): array {
		$this->log( 'Starting media scan task' );

		$settings     = get_option( 'wpha_settings', array() );
		$scan_results = array(
			'duplicates'     => array(),
			'large_files'    => array(),
			'missing_alt'    => array(),
			'total_issues'   => 0,
			'total_bytes'    => 0,
		);

		// Run full media scan.
		$full_scan = $this->scanner->get_media_summary();
		$this->log( sprintf( 'Full scan completed. Total items: %d', $full_scan['total_count'] ?? 0 ) );

		// Detect duplicates if enabled.
		if ( ! empty( $settings['detect_duplicates'] ) || ! empty( $options['detect_duplicates'] ) ) {
			$duplicates = $this->duplicate_detector->find_duplicates();
			$scan_results['duplicates'] = $duplicates;
			$scan_results['total_issues'] += count( $duplicates );
			$this->log( sprintf( 'Found %d duplicate files', count( $duplicates ) ) );
		}

		// Find large files if enabled.
		if ( ! empty( $settings['detect_large_files'] ) || ! empty( $options['detect_large_files'] ) ) {
			// Get threshold in KB, default 1000KB = ~1MB.
			$threshold_kb = $options['large_file_threshold_kb'] ?? ( $settings['large_file_threshold_kb'] ?? 1000 );
			$large        = $this->large_files->find_large_files( $threshold_kb );
			$scan_results['large_files'] = $large;
			$scan_results['total_issues'] += count( $large );

			// Calculate total bytes.
			foreach ( $large as $file ) {
				$scan_results['total_bytes'] += $file['current_size'] ?? 0;
			}
			$this->log( sprintf( 'Found %d large files', count( $large ) ) );
		}

		// Check for missing alt text if enabled.
		if ( ! empty( $settings['check_alt_text'] ) || ! empty( $options['check_alt_text'] ) ) {
			$missing_alt = $this->alt_text_checker->find_missing_alt_text();
			$scan_results['missing_alt'] = $missing_alt;
			$scan_results['total_issues'] += count( $missing_alt );
			$this->log( sprintf( 'Found %d images missing alt text', count( $missing_alt ) ) );
		}

		// Store scan results.
		$this->store_scan_results( $scan_results );

		$this->log( sprintf( 'Media scan completed. Total issues: %d', $scan_results['total_issues'] ) );

		return $this->create_result(
			$scan_results['total_issues'],
			$scan_results['total_bytes'],
			true
		);
	}

	/**
	 * Store scan results for later review.
	 *
	 * @param array $results Scan results.
	 * @return void
	 */
	private function store_scan_results( array $results ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'wpha_scan_history';

		$wpdb->insert(
			$table,
			array(
				'scan_type'     => 'media',
				'items_found'   => $results['total_issues'],
				'items_cleaned' => 0,
				'bytes_freed'   => 0,
				'metadata'      => wp_json_encode( array(
					'duplicates_count'  => count( $results['duplicates'] ),
					'large_files_count' => count( $results['large_files'] ),
					'missing_alt_count' => count( $results['missing_alt'] ),
					'total_bytes'       => $results['total_bytes'],
				) ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		/**
		 * Fires when media scan results are stored.
		 *
		 * @since 1.2.0
		 *
		 * @hook wpha_media_scan_completed
		 *
		 * @param array $results The scan results.
		 */
		do_action( 'wpha_media_scan_completed', $results );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_schema(): array {
		return array(
			'detect_duplicates'      => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Detect duplicate files', 'wp-admin-health-suite' ),
			),
			'detect_large_files'     => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Detect large files', 'wp-admin-health-suite' ),
			),
			'large_file_threshold_kb' => array(
				'type'        => 'integer',
				'default'     => 1000, // 1000KB = ~1MB.
				'min'         => 100,  // 100KB.
				'max'         => 5000, // 5000KB = ~5MB.
				'description' => __( 'Large file threshold in KB', 'wp-admin-health-suite' ),
			),
			'check_alt_text'         => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Check for missing alt text', 'wp-admin-health-suite' ),
			),
		);
	}
}
