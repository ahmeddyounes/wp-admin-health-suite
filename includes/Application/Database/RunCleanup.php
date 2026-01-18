<?php
/**
 * Run Cleanup Use Case
 *
 * Application service for orchestrating database cleanup operations.
 *
 * @package WPAdminHealth\Application\Database
 */

namespace WPAdminHealth\Application\Database;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Contracts\AnalyzerInterface;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\ActivityLoggerInterface;
use WPAdminHealth\Exceptions\ValidationException;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class RunCleanup
 *
 * Orchestrates database cleanup operations including revisions, drafts,
 * trashed items, transients, and orphaned metadata.
 *
 * This use-case class serves as the application layer between REST controllers
 * and domain services, providing a clean interface for cleanup operations.
 *
 * @since 1.4.0
 */
class RunCleanup {

	/**
	 * Valid cleanup types.
	 *
	 * @var array
	 */
	const VALID_TYPES = array( 'revisions', 'transients', 'spam', 'trash', 'orphaned' );

	/**
	 * Valid orphaned data types.
	 *
	 * @var array
	 */
	const VALID_ORPHANED_TYPES = array( 'postmeta', 'commentmeta', 'termmeta', 'relationships' );

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface
	 */
	private SettingsInterface $settings;

	/**
	 * Analyzer instance.
	 *
	 * @var AnalyzerInterface
	 */
	private AnalyzerInterface $analyzer;

	/**
	 * Revisions manager instance.
	 *
	 * @var RevisionsManagerInterface
	 */
	private RevisionsManagerInterface $revisions_manager;

	/**
	 * Transients cleaner instance.
	 *
	 * @var TransientsCleanerInterface
	 */
	private TransientsCleanerInterface $transients_cleaner;

	/**
	 * Orphaned cleaner instance.
	 *
	 * @var OrphanedCleanerInterface
	 */
	private OrphanedCleanerInterface $orphaned_cleaner;

	/**
	 * Trash cleaner instance.
	 *
	 * @var TrashCleanerInterface
	 */
	private TrashCleanerInterface $trash_cleaner;

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
	 * @param SettingsInterface           $settings           Settings instance.
	 * @param AnalyzerInterface           $analyzer           Analyzer instance.
	 * @param RevisionsManagerInterface   $revisions_manager  Revisions manager instance.
	 * @param TransientsCleanerInterface  $transients_cleaner Transients cleaner instance.
	 * @param OrphanedCleanerInterface    $orphaned_cleaner   Orphaned cleaner instance.
	 * @param TrashCleanerInterface       $trash_cleaner      Trash cleaner instance.
	 * @param ActivityLoggerInterface|null $activity_logger   Optional activity logger instance.
	 */
	public function __construct(
		SettingsInterface $settings,
		AnalyzerInterface $analyzer,
		RevisionsManagerInterface $revisions_manager,
		TransientsCleanerInterface $transients_cleaner,
		OrphanedCleanerInterface $orphaned_cleaner,
		TrashCleanerInterface $trash_cleaner,
		?ActivityLoggerInterface $activity_logger = null
	) {
		$this->settings           = $settings;
		$this->analyzer           = $analyzer;
		$this->revisions_manager  = $revisions_manager;
		$this->transients_cleaner = $transients_cleaner;
		$this->orphaned_cleaner   = $orphaned_cleaner;
		$this->trash_cleaner      = $trash_cleaner;
		$this->activity_logger    = $activity_logger;
	}

	/**
	 * Execute the cleanup operation.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options Cleanup options.
	 *                       - type: string (required) - One of 'revisions', 'transients', 'spam', 'trash', 'orphaned'.
	 *                       - safe_mode: bool - Override safe mode setting.
	 *                       - options: array - Type-specific options.
	 * @return array Result of the cleanup operation.
	 * @throws ValidationException If the cleanup type is invalid.
	 */
	public function execute( array $options = array() ): array {
		$type = $options['type'] ?? '';

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

		// Execute cleanup based on type.
		$result = $this->execute_cleanup_by_type( $type, $type_options, $safe_mode );

		// Add safe mode indicators if applicable.
		if ( $safe_mode ) {
			$result['safe_mode']    = true;
			$result['preview_only'] = true;
		}

		// Log activity (only if not in safe mode or if logging preview is desired).
		if ( ! $safe_mode ) {
			$this->log_cleanup_activity( $type, $result );
		}

		return $result;
	}

	/**
	 * Execute cleanup for a specific type.
	 *
	 * Convenience method for executing a single cleanup type directly.
	 *
	 * @since 1.4.0
	 *
	 * @param string $type      Cleanup type.
	 * @param array  $options   Type-specific options.
	 * @param bool   $safe_mode Whether to run in safe mode (preview only).
	 * @return array Cleanup result.
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
	 * Execute revisions cleanup.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	public function clean_revisions( array $options = array(), bool $safe_mode = false ): array {
		$keep_per_post = isset( $options['keep_per_post'] )
			? absint( $options['keep_per_post'] )
			: absint( $this->settings->get_setting( 'revisions_to_keep', 0 ) );

		if ( $safe_mode ) {
			$total_revisions = $this->revisions_manager->get_all_revisions_count();
			$size_estimate   = $this->revisions_manager->get_revisions_size_estimate();

			return array(
				'type'          => 'revisions',
				'deleted'       => 0,
				'would_delete'  => $total_revisions,
				'bytes_freed'   => 0,
				'would_free'    => $size_estimate,
				'keep_per_post' => $keep_per_post,
			);
		}

		$result = $this->revisions_manager->delete_all_revisions( $keep_per_post );

		return array(
			'type'          => 'revisions',
			'deleted'       => $result['deleted'],
			'bytes_freed'   => $result['bytes_freed'],
			'keep_per_post' => $keep_per_post,
		);
	}

	/**
	 * Execute transients cleanup.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	public function clean_transients( array $options = array(), bool $safe_mode = false ): array {
		$expired_only = isset( $options['expired_only'] ) ? (bool) $options['expired_only'] : true;

		// Get exclude patterns.
		if ( ! isset( $options['exclude_patterns'] ) || ! is_array( $options['exclude_patterns'] ) ) {
			$excluded_prefixes = $this->settings->get_setting( 'excluded_transient_prefixes', '' );
			$exclude_patterns  = array_filter( array_map( 'trim', explode( "\n", $excluded_prefixes ) ) );
		} else {
			$exclude_patterns = $options['exclude_patterns'];
		}

		if ( $safe_mode ) {
			$total_count = $this->transients_cleaner->count_transients();
			$size        = $this->transients_cleaner->get_transients_size();

			return array(
				'type'             => 'transients',
				'deleted'          => 0,
				'would_delete'     => $total_count,
				'bytes_freed'      => 0,
				'would_free'       => $size,
				'expired_only'     => $expired_only,
				'exclude_patterns' => $exclude_patterns,
			);
		}

		if ( $expired_only ) {
			$result = $this->transients_cleaner->delete_expired_transients( $exclude_patterns );
		} else {
			$result = $this->transients_cleaner->delete_all_transients( $exclude_patterns );
		}

		return array(
			'type'             => 'transients',
			'deleted'          => $result['deleted'],
			'bytes_freed'      => $result['bytes_freed'],
			'expired_only'     => $expired_only,
			'exclude_patterns' => $exclude_patterns,
		);
	}

	/**
	 * Execute spam comments cleanup.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	public function clean_spam( array $options = array(), bool $safe_mode = false ): array {
		$older_than_days = isset( $options['older_than_days'] )
			? absint( $options['older_than_days'] )
			: absint( $this->settings->get_setting( 'auto_clean_spam_days', 0 ) );

		if ( $safe_mode ) {
			$count = $this->analyzer->get_spam_comments_count();

			return array(
				'type'            => 'spam',
				'deleted'         => 0,
				'would_delete'    => $count,
				'errors'          => array(),
				'older_than_days' => $older_than_days,
			);
		}

		$result = $this->trash_cleaner->delete_spam_comments( $older_than_days );

		return array(
			'type'            => 'spam',
			'deleted'         => $result['deleted'],
			'errors'          => $result['errors'],
			'older_than_days' => $older_than_days,
		);
	}

	/**
	 * Execute trash cleanup (posts and comments).
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	public function clean_trash( array $options = array(), bool $safe_mode = false ): array {
		$older_than_days = isset( $options['older_than_days'] )
			? absint( $options['older_than_days'] )
			: absint( $this->settings->get_setting( 'auto_clean_trash_days', 0 ) );

		$post_types = isset( $options['post_types'] ) && is_array( $options['post_types'] )
			? $options['post_types']
			: array();

		if ( $safe_mode ) {
			$posts_count    = $this->analyzer->get_trashed_posts_count();
			$comments_count = $this->analyzer->get_trashed_comments_count();

			return array(
				'type'                  => 'trash',
				'posts_deleted'         => 0,
				'posts_would_delete'    => $posts_count,
				'posts_errors'          => array(),
				'comments_deleted'      => 0,
				'comments_would_delete' => $comments_count,
				'comments_errors'       => array(),
				'older_than_days'       => $older_than_days,
				'post_types'            => $post_types,
			);
		}

		$posts_result    = $this->trash_cleaner->delete_trashed_posts( $post_types, $older_than_days );
		$comments_result = $this->trash_cleaner->delete_trashed_comments( $older_than_days );

		return array(
			'type'             => 'trash',
			'posts_deleted'    => $posts_result['deleted'],
			'posts_errors'     => $posts_result['errors'],
			'comments_deleted' => $comments_result['deleted'],
			'comments_errors'  => $comments_result['errors'],
			'older_than_days'  => $older_than_days,
			'post_types'       => $post_types,
		);
	}

	/**
	 * Execute orphaned data cleanup.
	 *
	 * @since 1.4.0
	 *
	 * @param array $options   Cleanup options.
	 * @param bool  $safe_mode Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	public function clean_orphaned( array $options = array(), bool $safe_mode = false ): array {
		$types = isset( $options['types'] ) && is_array( $options['types'] )
			? array_intersect( $options['types'], self::VALID_ORPHANED_TYPES )
			: self::VALID_ORPHANED_TYPES;

		$results = array(
			'type' => 'orphaned',
		);

		if ( $safe_mode ) {
			if ( in_array( 'postmeta', $types, true ) ) {
				$results['postmeta_deleted']      = 0;
				$results['postmeta_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_postmeta() );
			}

			if ( in_array( 'commentmeta', $types, true ) ) {
				$results['commentmeta_deleted']      = 0;
				$results['commentmeta_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_commentmeta() );
			}

			if ( in_array( 'termmeta', $types, true ) ) {
				$results['termmeta_deleted']      = 0;
				$results['termmeta_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_termmeta() );
			}

			if ( in_array( 'relationships', $types, true ) ) {
				$results['relationships_deleted']      = 0;
				$results['relationships_would_delete'] = count( $this->orphaned_cleaner->find_orphaned_relationships() );
			}

			return $results;
		}

		if ( in_array( 'postmeta', $types, true ) ) {
			$results['postmeta_deleted'] = $this->orphaned_cleaner->delete_orphaned_postmeta();
		}

		if ( in_array( 'commentmeta', $types, true ) ) {
			$results['commentmeta_deleted'] = $this->orphaned_cleaner->delete_orphaned_commentmeta();
		}

		if ( in_array( 'termmeta', $types, true ) ) {
			$results['termmeta_deleted'] = $this->orphaned_cleaner->delete_orphaned_termmeta();
		}

		if ( in_array( 'relationships', $types, true ) ) {
			$results['relationships_deleted'] = $this->orphaned_cleaner->delete_orphaned_relationships();
		}

		return $results;
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
	 * Execute cleanup by type.
	 *
	 * @since 1.4.0
	 *
	 * @param string $type         Cleanup type.
	 * @param array  $type_options Type-specific options.
	 * @param bool   $safe_mode    Whether safe mode is enabled.
	 * @return array Cleanup results.
	 */
	private function execute_cleanup_by_type( string $type, array $type_options, bool $safe_mode ): array {
		switch ( $type ) {
			case 'revisions':
				return $this->clean_revisions( $type_options, $safe_mode );

			case 'transients':
				return $this->clean_transients( $type_options, $safe_mode );

			case 'spam':
				return $this->clean_spam( $type_options, $safe_mode );

			case 'trash':
				return $this->clean_trash( $type_options, $safe_mode );

			case 'orphaned':
				return $this->clean_orphaned( $type_options, $safe_mode );

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
	 * Log cleanup activity.
	 *
	 * @since 1.4.0
	 *
	 * @param string $type   Cleanup type.
	 * @param array  $result Cleanup result.
	 * @return void
	 */
	private function log_cleanup_activity( string $type, array $result ): void {
		if ( null === $this->activity_logger ) {
			return;
		}

		$this->activity_logger->log_database_cleanup( $type, $result );
	}
}
