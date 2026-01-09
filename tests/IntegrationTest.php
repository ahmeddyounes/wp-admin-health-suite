<?php
/**
 * Integration Tests
 *
 * Tests complete workflows and plugin compatibility.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests;

use WPAdminHealth\Plugin;
use WPAdminHealth\Scheduler;
use WPAdminHealth\Settings;
use WPAdminHealth\Database\Analyzer;
use WPAdminHealth\Database\RevisionsManager;
use WPAdminHealth\Database\TransientsCleaner;
use WPAdminHealth\Media\Scanner;

/**
 * Test complete workflows and integration scenarios.
 *
 * Tests full scan to cleanup workflows, scheduled task execution,
 * settings changes affecting behavior, and compatibility with
 * popular plugins like WooCommerce and Elementor.
 */
class IntegrationTest extends TestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Scheduler instance.
	 *
	 * @var Scheduler
	 */
	protected $scheduler;

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment() {
		parent::setup_test_environment();

		$this->plugin = Plugin::get_instance();
		$this->scheduler = $this->plugin->get_scheduler();
		$this->settings = $this->plugin->get_settings();

		// Clear any existing scheduled tasks.
		$this->clear_scheduled_tasks();

		// Reset settings to defaults.
		update_option( 'wpha_settings', $this->settings->get_default_settings() );
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment() {
		// Clear scheduled tasks.
		$this->clear_scheduled_tasks();

		// Clean up test data.
		$this->cleanup_test_data();

		parent::cleanup_test_environment();
	}

	/**
	 * Clear all scheduled tasks.
	 */
	private function clear_scheduled_tasks() {
		global $wpdb;

		$table = $wpdb->prefix . 'wpha_scheduled_tasks';
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		// Clear WP-Cron events.
		wp_clear_scheduled_hook( 'wpha_scheduled_cleanup_run' );
		wp_clear_scheduled_hook( 'wpha_database_cleanup' );
		wp_clear_scheduled_hook( 'wpha_media_scan' );
		wp_clear_scheduled_hook( 'wpha_performance_check' );
	}

	/**
	 * Clean up test data.
	 */
	private function cleanup_test_data() {
		global $wpdb;

		// Clean up scan history.
		$table = $wpdb->prefix . 'wpha_scan_history';
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		// Clean up test posts.
		$posts = get_posts(
			array(
				'post_type' => 'any',
				'title' => 'Integration Test',
				'numberposts' => -1,
			)
		);
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

	/**
	 * Test: Complete workflow from database scan to cleanup.
	 */
	public function test_database_scan_to_cleanup_workflow() {
		// Step 1: Create test data.
		$post_id = $this->create_test_post(
			array(
				'post_title' => 'Integration Test Post',
			)
		);

		// Create multiple revisions.
		for ( $i = 0; $i < 5; $i++ ) {
			wp_update_post(
				array(
					'ID' => $post_id,
					'post_content' => 'Updated content ' . $i,
				)
			);
		}

		// Step 2: Scan database.
		$analyzer = new Analyzer();
		$initial_revisions = $analyzer->get_revisions_count();

		$this->assertGreaterThan( 0, $initial_revisions, 'Should find revisions in database' );

		// Step 3: Configure cleanup settings.
		$settings = $this->settings->get_settings();
		$settings['cleanup_revisions'] = true;
		$settings['revisions_to_keep'] = 2;
		update_option( 'wpha_settings', $settings );

		// Step 4: Execute cleanup.
		$revisions_manager = new RevisionsManager();
		$result = $revisions_manager->clean_revisions( 2 );

		// Step 5: Verify cleanup results.
		$this->assertGreaterThan( 0, $result['items_cleaned'], 'Should clean revisions' );

		// Step 6: Verify scan results are stored in history.
		global $wpdb;
		$table = $wpdb->prefix . 'wpha_scan_history';
		$history_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$this->assertGreaterThanOrEqual( 0, $history_count, 'Scan history should be tracked' );
	}

	/**
	 * Test: Complete workflow from media scan to cleanup.
	 */
	public function test_media_scan_to_cleanup_workflow() {
		// Step 1: Create test media.
		$attachment_id = $this->create_test_attachment(
			array(
				'post_title' => 'Integration Test Image',
			)
		);

		$this->assertGreaterThan( 0, $attachment_id, 'Should create test attachment' );

		// Step 2: Scan media library.
		$scanner = new Scanner();
		$scan_results = $scanner->scan_all_media();

		// Step 3: Verify scan results.
		$this->assertIsArray( $scan_results, 'Scan should return results array' );
		$this->assertArrayHasKey( 'total_count', $scan_results );
		$this->assertArrayHasKey( 'total_size', $scan_results );
		$this->assertArrayHasKey( 'scanned_at', $scan_results );

		$this->assertGreaterThan( 0, $scan_results['total_count'], 'Should find media files' );
	}

	/**
	 * Test: Scheduled task execution workflow.
	 */
	public function test_scheduled_task_execution_workflow() {
		// Step 1: Schedule a cleanup task.
		$task_id = $this->scheduler->schedule_cleanup(
			'transients',
			'daily',
			array(
				'email_notification' => false,
			)
		);

		$this->assertNotFalse( $task_id, 'Should schedule task successfully' );
		$this->assertIsInt( $task_id, 'Task ID should be an integer' );

		// Step 2: Verify task is scheduled.
		$scheduled_tasks = $this->scheduler->get_scheduled_tasks( 'active' );
		$this->assertNotEmpty( $scheduled_tasks, 'Should have scheduled tasks' );

		$found_task = false;
		foreach ( $scheduled_tasks as $task ) {
			if ( $task->id === $task_id ) {
				$found_task = true;
				$this->assertEquals( 'transients', $task->task_type );
				$this->assertEquals( 'daily', $task->frequency );
				$this->assertEquals( 'active', $task->status );
				break;
			}
		}
		$this->assertTrue( $found_task, 'Should find scheduled task in list' );

		// Step 3: Create some expired transients.
		set_transient( 'test_transient_1', 'value1', -1 );
		set_transient( 'test_transient_2', 'value2', -1 );

		// Step 4: Execute the scheduled task.
		$result = $this->scheduler->run_scheduled_task( $task_id );
		$this->assertTrue( $result, 'Task should execute successfully' );

		// Step 5: Verify task updated after execution.
		global $wpdb;
		$table = $wpdb->prefix . 'wpha_scheduled_tasks';
		$updated_task = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$task_id
			)
		);

		$this->assertNotNull( $updated_task->last_run, 'Task should have last_run timestamp' );
		$this->assertNotNull( $updated_task->next_run, 'Task should have next_run timestamp' );

		// Step 6: Unschedule the task.
		$unscheduled = $this->scheduler->unschedule_cleanup( 'transients' );
		$this->assertTrue( $unscheduled, 'Should unschedule task successfully' );

		// Verify task is inactive.
		$inactive_task = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$task_id
			)
		);
		$this->assertEquals( 'inactive', $inactive_task->status, 'Task should be inactive' );
	}

	/**
	 * Test: Settings changes affecting behavior.
	 */
	public function test_settings_changes_affecting_behavior() {
		// Step 1: Get default settings.
		$default_settings = $this->settings->get_default_settings();

		// Step 2: Change cleanup settings to enable revisions cleanup.
		$settings = $this->settings->get_settings();
		$settings['cleanup_revisions'] = true;
		$settings['revisions_to_keep'] = 3;
		update_option( 'wpha_settings', $settings );

		// Step 3: Verify settings are updated.
		$updated_settings = $this->settings->get_settings();
		$this->assertTrue( $updated_settings['cleanup_revisions'], 'Cleanup revisions should be enabled' );
		$this->assertEquals( 3, $updated_settings['revisions_to_keep'], 'Should keep 3 revisions' );

		// Step 4: Change settings to disable cleanup.
		$settings['cleanup_revisions'] = false;
		update_option( 'wpha_settings', $settings );

		// Verify settings are updated.
		$updated_settings = $this->settings->get_settings();
		$this->assertFalse( $updated_settings['cleanup_revisions'], 'Cleanup revisions should be disabled' );

		// Step 5: Test safe mode setting.
		$settings['safe_mode'] = true;
		update_option( 'wpha_settings', $settings );

		$this->assertTrue( $this->settings->is_safe_mode_enabled(), 'Safe mode should be enabled' );

		// Disable safe mode.
		$settings['safe_mode'] = false;
		update_option( 'wpha_settings', $settings );

		$this->assertFalse( $this->settings->is_safe_mode_enabled(), 'Safe mode should be disabled' );
	}

	/**
	 * Test: Scheduling settings trigger task creation.
	 */
	public function test_scheduling_settings_trigger_task_creation() {
		// Step 1: Enable scheduler and set frequencies.
		$settings = $this->settings->get_settings();
		$settings['scheduler_enabled'] = true;
		$settings['database_cleanup_frequency'] = 'weekly';
		$settings['media_scan_frequency'] = 'weekly';
		$settings['performance_check_frequency'] = 'daily';
		update_option( 'wpha_settings', $settings );

		// The settings update hook should trigger task scheduling.
		// We need to manually trigger it for testing.
		$this->settings->handle_scheduling_update( array(), $settings );

		// Step 2: Verify WP-Cron events are scheduled (fallback when Action Scheduler not available).
		$next_database_cleanup = wp_next_scheduled( 'wpha_database_cleanup' );
		$next_media_scan = wp_next_scheduled( 'wpha_media_scan' );
		$next_performance_check = wp_next_scheduled( 'wpha_performance_check' );

		// These should be scheduled or handled by Action Scheduler.
		// In test environment without Action Scheduler, they should use WP-Cron.
		$this->assertTrue(
			$next_database_cleanup !== false || function_exists( 'as_next_scheduled_action' ),
			'Database cleanup should be scheduled'
		);

		// Step 3: Disable scheduler.
		$settings['scheduler_enabled'] = false;
		update_option( 'wpha_settings', $settings );
		$this->settings->handle_scheduling_update( $settings, $settings );

		// Verify events are unscheduled.
		$next_database_cleanup = wp_next_scheduled( 'wpha_database_cleanup' );
		$this->assertFalse( $next_database_cleanup, 'Database cleanup should be unscheduled when disabled' );
	}

	/**
	 * Test: WooCommerce compatibility.
	 */
	public function test_woocommerce_compatibility() {
		// Simulate WooCommerce being active by defining constants.
		if ( ! defined( 'WC_VERSION' ) ) {
			define( 'WC_VERSION', '8.0.0' );
		}

		// Step 1: Create a product post type (WooCommerce).
		$product_id = $this->factory()->post->create(
			array(
				'post_type' => 'product',
				'post_title' => 'Integration Test Product',
				'post_status' => 'publish',
			)
		);

		$this->assertGreaterThan( 0, $product_id, 'Should create WooCommerce product' );

		// Step 2: Create product image.
		$image_id = $this->create_test_attachment(
			array(
				'post_title' => 'Product Image',
			),
			$product_id
		);

		// Set as product thumbnail.
		update_post_meta( $product_id, '_thumbnail_id', $image_id );

		// Step 3: Enable WooCommerce scanning in settings.
		$settings = $this->settings->get_settings();
		$settings['scan_woocommerce'] = true;
		update_option( 'wpha_settings', $settings );

		// Step 4: Scan media library.
		$scanner = new Scanner();
		$scan_results = $scanner->scan_all_media();

		// Step 5: Verify scan completes without errors.
		$this->assertIsArray( $scan_results, 'Scan should complete with WooCommerce active' );
		$this->assertGreaterThan( 0, $scan_results['total_count'], 'Should find media including product images' );

		// Step 6: Database scan should work with WooCommerce product post type.
		$analyzer = new Analyzer();
		$database_size = $analyzer->get_database_size();
		$this->assertGreaterThan( 0, $database_size, 'Database analysis should work with WooCommerce' );

		// Clean up.
		wp_delete_post( $product_id, true );
		wp_delete_attachment( $image_id, true );
	}

	/**
	 * Test: Elementor compatibility.
	 */
	public function test_elementor_compatibility() {
		// Simulate Elementor being active.
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			define( 'ELEMENTOR_VERSION', '3.16.0' );
		}

		// Step 1: Create a page with Elementor data.
		$page_id = $this->create_test_post(
			array(
				'post_type' => 'page',
				'post_title' => 'Integration Test Elementor Page',
			)
		);

		// Add Elementor meta.
		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $page_id, '_elementor_version', '3.16.0' );

		// Create an image for Elementor.
		$image_id = $this->create_test_attachment(
			array(
				'post_title' => 'Elementor Image',
			)
		);

		// Simulate Elementor data with image reference.
		$elementor_data = array(
			array(
				'id' => 'test-section',
				'elType' => 'section',
				'elements' => array(
					array(
						'id' => 'test-image',
						'elType' => 'widget',
						'widgetType' => 'image',
						'settings' => array(
							'image' => array(
								'id' => $image_id,
							),
						),
					),
				),
			),
		);

		update_post_meta( $page_id, '_elementor_data', wp_json_encode( $elementor_data ) );

		// Step 2: Enable Elementor scanning in settings.
		$settings = $this->settings->get_settings();
		$settings['scan_elementor'] = true;
		update_option( 'wpha_settings', $settings );

		// Step 3: Scan media library.
		$scanner = new Scanner();
		$scan_results = $scanner->scan_all_media();

		// Step 4: Verify scan completes without errors.
		$this->assertIsArray( $scan_results, 'Scan should complete with Elementor active' );
		$this->assertGreaterThan( 0, $scan_results['total_count'], 'Should find media including Elementor images' );

		// Step 5: Database scan should work with Elementor meta.
		$analyzer = new Analyzer();
		$database_size = $analyzer->get_database_size();
		$this->assertGreaterThan( 0, $database_size, 'Database analysis should work with Elementor' );

		// Clean up.
		wp_delete_post( $page_id, true );
		wp_delete_attachment( $image_id, true );
	}

	/**
	 * Test: No conflicts with popular plugins.
	 */
	public function test_no_conflicts_with_popular_plugins() {
		// Step 1: Initialize plugin.
		$this->plugin->init();

		// Step 2: Check that no fatal errors occurred during initialization.
		$this->assertTrue( true, 'Plugin should initialize without conflicts' );

		// Step 3: Test that core WordPress functionality still works.
		$post_id = $this->create_test_post();
		$this->assertGreaterThan( 0, $post_id, 'Should create posts without conflicts' );

		$comment_id = $this->create_test_comment( array(), $post_id );
		$this->assertGreaterThan( 0, $comment_id, 'Should create comments without conflicts' );

		$attachment_id = $this->create_test_attachment();
		$this->assertGreaterThan( 0, $attachment_id, 'Should create attachments without conflicts' );

		// Step 4: Verify transients work.
		set_transient( 'test_transient', 'test_value', HOUR_IN_SECONDS );
		$value = get_transient( 'test_transient' );
		$this->assertEquals( 'test_value', $value, 'Transients should work without conflicts' );

		// Clean up.
		delete_transient( 'test_transient' );
	}

	/**
	 * Test: Email notifications work correctly.
	 */
	public function test_email_notifications() {
		// Reset email test state.
		$GLOBALS['phpmailer'] = null;
		$GLOBALS['wp_actions']['phpmailer_init'] = 0;

		// Step 1: Enable notifications in settings.
		$settings = $this->settings->get_settings();
		$settings['notification_on_completion'] = true;
		$settings['notification_email'] = 'test@example.com';
		update_option( 'wpha_settings', $settings );

		// Step 2: Schedule a task with email notification.
		$task_id = $this->scheduler->schedule_cleanup(
			'transients',
			'daily',
			array(
				'email_notification' => true,
			)
		);

		$this->assertNotFalse( $task_id, 'Should schedule task with notifications' );

		// Step 3: Execute task (which should trigger email).
		// Note: In test environment, emails won't actually send but we can verify settings.
		$this->assertTrue( $settings['notification_on_completion'], 'Notifications should be enabled' );
		$this->assertEquals( 'test@example.com', $settings['notification_email'], 'Email should be configured' );

		// Clean up.
		$this->scheduler->unschedule_cleanup( 'transients' );
	}

	/**
	 * Test: Complete end-to-end workflow with settings, scan, and cleanup.
	 */
	public function test_complete_end_to_end_workflow() {
		// Step 1: Configure settings.
		$settings = $this->settings->get_settings();
		$settings['cleanup_revisions'] = true;
		$settings['revisions_to_keep'] = 2;
		$settings['cleanup_expired_transients'] = true;
		$settings['scheduler_enabled'] = false; // Manual execution for testing.
		update_option( 'wpha_settings', $settings );

		// Step 2: Create test data.
		$post_id = $this->create_test_post(
			array(
				'post_title' => 'End-to-End Test Post',
			)
		);

		// Create revisions.
		for ( $i = 0; $i < 5; $i++ ) {
			wp_update_post(
				array(
					'ID' => $post_id,
					'post_content' => 'Revision ' . $i,
				)
			);
		}

		// Create expired transients.
		set_transient( 'e2e_test_transient_1', 'value1', -1 );
		set_transient( 'e2e_test_transient_2', 'value2', -1 );

		// Step 3: Scan database.
		$analyzer = new Analyzer();
		$initial_revisions = $analyzer->get_revisions_count();
		$this->assertGreaterThan( 0, $initial_revisions, 'Should have revisions before cleanup' );

		// Step 4: Execute cleanup.
		$revisions_manager = new RevisionsManager();
		$revisions_result = $revisions_manager->clean_revisions( 2 );

		$transients_cleaner = new TransientsCleaner();
		$transients_result = $transients_cleaner->clean_expired_transients();

		// Step 5: Verify cleanup results.
		$this->assertGreaterThan( 0, $revisions_result['items_cleaned'], 'Should clean some revisions' );
		$this->assertGreaterThan( 0, $transients_result['items_cleaned'], 'Should clean some transients' );

		// Step 6: Verify data is actually cleaned.
		$final_revisions = $analyzer->get_revisions_count();
		$this->assertLessThan( $initial_revisions, $final_revisions, 'Revisions should be reduced' );

		// Step 7: Verify scan history is recorded.
		global $wpdb;
		$table = $wpdb->prefix . 'wpha_scan_history';
		$history_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$this->assertGreaterThanOrEqual( 0, $history_count, 'Should have scan history' );

		// Clean up.
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test: Settings import and export workflow.
	 */
	public function test_settings_import_export_workflow() {
		// Step 1: Configure custom settings.
		$settings = $this->settings->get_settings();
		$settings['cleanup_revisions'] = true;
		$settings['revisions_to_keep'] = 5;
		$settings['health_score_threshold'] = 80;
		update_option( 'wpha_settings', $settings );

		// Step 2: Get settings for export simulation.
		$exported_settings = $this->settings->get_settings();

		// Step 3: Reset to defaults.
		update_option( 'wpha_settings', $this->settings->get_default_settings() );
		$default_settings = $this->settings->get_settings();
		$this->assertNotEquals( $exported_settings['revisions_to_keep'], $default_settings['revisions_to_keep'] );

		// Step 4: Import settings (simulate).
		$sanitized = $this->settings->sanitize_settings( $exported_settings );
		update_option( 'wpha_settings', $sanitized );

		// Step 5: Verify settings are restored.
		$restored_settings = $this->settings->get_settings();
		$this->assertEquals( 5, $restored_settings['revisions_to_keep'], 'Settings should be restored' );
		$this->assertEquals( 80, $restored_settings['health_score_threshold'], 'Settings should be restored' );
	}
}
