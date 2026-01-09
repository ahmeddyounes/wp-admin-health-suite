<?php
/**
 * Database Cleanup Task
 *
 * Scheduled task for database maintenance operations.
 *
 * @package WPAdminHealth\Database\Tasks
 */

namespace WPAdminHealth\Database\Tasks;

use WPAdminHealth\Scheduler\AbstractScheduledTask;
use WPAdminHealth\Contracts\RevisionsManagerInterface;
use WPAdminHealth\Contracts\TransientsCleanerInterface;
use WPAdminHealth\Contracts\OrphanedCleanerInterface;
use WPAdminHealth\Contracts\TrashCleanerInterface;
use WPAdminHealth\Contracts\OptimizerInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class DatabaseCleanupTask
 *
 * Performs scheduled database cleanup operations.
 *
 * @since 1.2.0
 */
class DatabaseCleanupTask extends AbstractScheduledTask {

	/**
	 * Task identifier.
	 *
	 * @var string
	 */
	protected string $task_id = 'database_cleanup';

	/**
	 * Task name.
	 *
	 * @var string
	 */
	protected string $task_name = 'Database Cleanup';

	/**
	 * Task description.
	 *
	 * @var string
	 */
	protected string $description = 'Clean up revisions, transients, orphaned metadata, and trash items.';

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
	protected string $enabled_option_key = 'enable_scheduled_db_cleanup';

	/**
	 * Revisions manager.
	 *
	 * @var RevisionsManagerInterface
	 */
	private RevisionsManagerInterface $revisions_manager;

	/**
	 * Transients cleaner.
	 *
	 * @var TransientsCleanerInterface
	 */
	private TransientsCleanerInterface $transients_cleaner;

	/**
	 * Orphaned cleaner.
	 *
	 * @var OrphanedCleanerInterface
	 */
	private OrphanedCleanerInterface $orphaned_cleaner;

	/**
	 * Trash cleaner.
	 *
	 * @var TrashCleanerInterface
	 */
	private TrashCleanerInterface $trash_cleaner;

	/**
	 * Database optimizer.
	 *
	 * @var OptimizerInterface
	 */
	private OptimizerInterface $optimizer;

	/**
	 * Constructor.
	 *
	 * @param RevisionsManagerInterface  $revisions_manager  Revisions manager.
	 * @param TransientsCleanerInterface $transients_cleaner Transients cleaner.
	 * @param OrphanedCleanerInterface   $orphaned_cleaner   Orphaned cleaner.
	 * @param TrashCleanerInterface      $trash_cleaner      Trash cleaner.
	 * @param OptimizerInterface         $optimizer          Database optimizer.
	 */
	public function __construct(
		RevisionsManagerInterface $revisions_manager,
		TransientsCleanerInterface $transients_cleaner,
		OrphanedCleanerInterface $orphaned_cleaner,
		TrashCleanerInterface $trash_cleaner,
		OptimizerInterface $optimizer
	) {
		$this->revisions_manager  = $revisions_manager;
		$this->transients_cleaner = $transients_cleaner;
		$this->orphaned_cleaner   = $orphaned_cleaner;
		$this->trash_cleaner      = $trash_cleaner;
		$this->optimizer          = $optimizer;
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute( array $options = array() ): array {
		$this->log( 'Starting database cleanup task' );

		$total_items   = 0;
		$total_bytes   = 0;
		$settings      = get_option( 'wpha_settings', array() );
		$cleanup_tasks = array();

		// Determine which cleanup tasks to run based on settings.
		if ( ! empty( $settings['cleanup_revisions'] ) || ! empty( $options['clean_revisions'] ) ) {
			$cleanup_tasks[] = 'revisions';
		}
		if ( ! empty( $settings['cleanup_expired_transients'] ) || ! empty( $options['clean_transients'] ) ) {
			$cleanup_tasks[] = 'transients';
		}
		if ( ! empty( $settings['cleanup_orphaned_metadata'] ) || ! empty( $options['clean_orphaned'] ) ) {
			$cleanup_tasks[] = 'orphaned';
		}
		if ( ! empty( $settings['cleanup_trashed_posts'] ) || ! empty( $options['clean_trash'] ) ) {
			$cleanup_tasks[] = 'trash';
		}

		// Execute each cleanup task.
		foreach ( $cleanup_tasks as $task ) {
			$result = $this->execute_subtask( $task, $options );
			$total_items += $result['items'];
			$total_bytes += $result['bytes'];
		}

		// Optionally optimize tables.
		if ( ! empty( $settings['optimize_tables_weekly'] ) || ! empty( $options['optimize_tables'] ) ) {
			$this->optimizer->optimize_all_tables();
			$this->log( 'Database tables optimized' );
		}

		$this->log( sprintf( 'Database cleanup completed. Items: %d, Bytes: %d', $total_items, $total_bytes ) );

		return $this->create_result( $total_items, $total_bytes, true );
	}

	/**
	 * Execute a specific cleanup subtask.
	 *
	 * @param string $task    Task name.
	 * @param array  $options Task options.
	 * @return array Result with 'items' and 'bytes' keys.
	 */
	private function execute_subtask( string $task, array $options ): array {
		$result = array(
			'items' => 0,
			'bytes' => 0,
		);

		switch ( $task ) {
			case 'revisions':
				$settings      = get_option( 'wpha_settings', array() );
				$max_revisions = $options['max_revisions'] ?? ( $settings['revisions_to_keep'] ?? 5 );
				$cleaned       = $this->revisions_manager->delete_all_revisions( $max_revisions );
				$result['items'] = $cleaned['deleted'] ?? 0;
				$result['bytes'] = $cleaned['bytes_freed'] ?? 0;
				$this->log( sprintf( 'Cleaned %d revisions', $result['items'] ) );
				break;

			case 'transients':
				$cleaned = $this->transients_cleaner->delete_expired_transients();
				$result['items'] = $cleaned['deleted'] ?? 0;
				$result['bytes'] = $cleaned['bytes_freed'] ?? 0;
				$this->log( sprintf( 'Cleaned %d transients', $result['items'] ) );
				break;

			case 'orphaned':
				// Clean all orphaned data types.
				$postmeta_count      = $this->orphaned_cleaner->delete_orphaned_postmeta();
				$commentmeta_count   = $this->orphaned_cleaner->delete_orphaned_commentmeta();
				$termmeta_count      = $this->orphaned_cleaner->delete_orphaned_termmeta();
				$relationships_count = $this->orphaned_cleaner->delete_orphaned_relationships();
				$result['items'] = $postmeta_count + $commentmeta_count + $termmeta_count + $relationships_count;
				$result['bytes'] = 0; // Orphaned cleaner doesn't track bytes.
				$this->log( sprintf( 'Cleaned %d orphaned items', $result['items'] ) );
				break;

			case 'trash':
				$cleaned = $this->trash_cleaner->empty_all_trash();
				$result['items'] = ( $cleaned['posts_deleted'] ?? 0 ) + ( $cleaned['comments_deleted'] ?? 0 );
				$result['bytes'] = 0; // Trash cleaner doesn't track bytes.
				$this->log( sprintf( 'Cleaned %d trash items', $result['items'] ) );
				break;
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_schema(): array {
		return array(
			'clean_revisions'    => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Clean post revisions', 'wp-admin-health-suite' ),
			),
			'max_revisions'      => array(
				'type'        => 'integer',
				'default'     => 5,
				'min'         => 0,
				'max'         => 100,
				'description' => __( 'Maximum revisions to keep per post', 'wp-admin-health-suite' ),
			),
			'clean_transients'   => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Clean expired transients', 'wp-admin-health-suite' ),
			),
			'clean_orphaned'     => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Clean orphaned metadata', 'wp-admin-health-suite' ),
			),
			'clean_trash'        => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Empty trash', 'wp-admin-health-suite' ),
			),
			'optimize_tables'    => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Optimize database tables after cleanup', 'wp-admin-health-suite' ),
			),
		);
	}
}
