<?php
/**
 * Database Analyzer Tests
 *
 * Tests for database analyzer functionality including revisions, transients,
 * orphaned meta, and table optimization.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests;

use WPAdminHealth\Database\Analyzer;
use WPAdminHealth\Database\Revisions_Manager;
use WPAdminHealth\Database\Transients_Cleaner;
use WPAdminHealth\Database\Orphaned_Cleaner;
use WPAdminHealth\Database\Optimizer;

/**
 * Test case for Database Analyzer and related database operations
 */
class Test_Database_Analyzer extends Test_Case {

	/**
	 * Analyzer instance
	 *
	 * @var Analyzer
	 */
	private $analyzer;

	/**
	 * Revisions Manager instance
	 *
	 * @var Revisions_Manager
	 */
	private $revisions_manager;

	/**
	 * Transients Cleaner instance
	 *
	 * @var Transients_Cleaner
	 */
	private $transients_cleaner;

	/**
	 * Orphaned Cleaner instance
	 *
	 * @var Orphaned_Cleaner
	 */
	private $orphaned_cleaner;

	/**
	 * Optimizer instance
	 *
	 * @var Optimizer
	 */
	private $optimizer;

	/**
	 * Set up test environment before each test
	 */
	protected function set_up() {
		parent::set_up();
		$this->analyzer = new Analyzer();
		$this->revisions_manager = new Revisions_Manager();
		$this->transients_cleaner = new Transients_Cleaner();
		$this->orphaned_cleaner = new Orphaned_Cleaner();
		$this->optimizer = new Optimizer();
	}

	/**
	 * Test get_revisions_count returns correct count
	 */
	public function test_get_revisions_count_returns_correct_count() {
		// Create a post with revisions.
		$post_id = $this->create_test_post(
			array(
				'post_title' => 'Test Post for Revisions',
				'post_content' => 'Initial content',
			)
		);

		// Get initial count.
		$initial_count = $this->analyzer->get_revisions_count();

		// Create revisions by updating the post.
		wp_update_post(
			array(
				'ID' => $post_id,
				'post_content' => 'Updated content 1',
			)
		);

		wp_update_post(
			array(
				'ID' => $post_id,
				'post_content' => 'Updated content 2',
			)
		);

		wp_update_post(
			array(
				'ID' => $post_id,
				'post_content' => 'Updated content 3',
			)
		);

		// Get count after updates.
		$after_count = $this->analyzer->get_revisions_count();

		// Should have 3 more revisions.
		$this->assertEquals( $initial_count + 3, $after_count, 'Revisions count should increase by 3' );
	}

	/**
	 * Test get_revisions_count with no revisions
	 */
	public function test_get_revisions_count_with_no_revisions() {
		// Clear all posts (this is a fresh test database).
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );

		// Get count.
		$count = $this->analyzer->get_revisions_count();

		// Should be 0.
		$this->assertEquals( 0, $count, 'Revisions count should be 0 when no revisions exist' );
	}

	/**
	 * Test delete_revisions removes correct number
	 */
	public function test_delete_revisions_removes_correct_number() {
		// Create a post with revisions.
		$post_id = $this->create_test_post(
			array(
				'post_title' => 'Test Post for Deletion',
				'post_content' => 'Initial content',
			)
		);

		// Create 5 revisions.
		for ( $i = 1; $i <= 5; $i++ ) {
			wp_update_post(
				array(
					'ID' => $post_id,
					'post_content' => "Updated content $i",
				)
			);
		}

		// Get initial count.
		$initial_count = $this->analyzer->get_revisions_count();
		$this->assertGreaterThanOrEqual( 5, $initial_count, 'Should have at least 5 revisions' );

		// Delete all revisions for this post.
		$result = $this->revisions_manager->delete_revisions_for_post( $post_id );

		// Verify result.
		$this->assertIsArray( $result, 'delete_revisions_for_post should return an array' );
		$this->assertArrayHasKey( 'deleted', $result, 'Result should have deleted key' );
		$this->assertArrayHasKey( 'bytes_freed', $result, 'Result should have bytes_freed key' );
		$this->assertEquals( 5, $result['deleted'], 'Should have deleted 5 revisions' );

		// Verify count decreased.
		$after_count = $this->analyzer->get_revisions_count();
		$this->assertEquals( $initial_count - 5, $after_count, 'Revisions count should decrease by 5' );
	}

	/**
	 * Test delete_revisions with keep count
	 */
	public function test_delete_revisions_with_keep_count() {
		// Create a post with revisions.
		$post_id = $this->create_test_post(
			array(
				'post_title' => 'Test Post for Partial Deletion',
				'post_content' => 'Initial content',
			)
		);

		// Create 5 revisions.
		for ( $i = 1; $i <= 5; $i++ ) {
			wp_update_post(
				array(
					'ID' => $post_id,
					'post_content' => "Updated content $i",
				)
			);
		}

		// Delete all but keep 2 most recent.
		$result = $this->revisions_manager->delete_revisions_for_post( $post_id, 2 );

		// Should have deleted 3 revisions (5 - 2 kept).
		$this->assertEquals( 3, $result['deleted'], 'Should have deleted 3 revisions, keeping 2' );

		// Verify 2 revisions remain for this post.
		$remaining_revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 2, $remaining_revisions, 'Should have 2 revisions remaining' );
	}

	/**
	 * Test transient cleanup only removes expired transients
	 */
	public function test_transient_cleanup_only_removes_expired() {
		// Skip if using external object cache.
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'External object cache is enabled' );
		}

		// Create a non-expired transient (expires in 1 hour).
		set_transient( 'wpha_test_active', 'active_value', 3600 );

		// Create an expired transient.
		global $wpdb;
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name' => '_transient_wpha_test_expired',
				'option_value' => 'expired_value',
				'autoload' => 'no',
			)
		);
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name' => '_transient_timeout_wpha_test_expired',
				'option_value' => time() - 3600, // Expired 1 hour ago.
				'autoload' => 'no',
			)
		);

		// Get initial expired count.
		$expired_transients = $this->transients_cleaner->get_expired_transients();
		$initial_expired_count = count( $expired_transients );
		$this->assertGreaterThanOrEqual( 1, $initial_expired_count, 'Should have at least 1 expired transient' );

		// Delete expired transients.
		$result = $this->transients_cleaner->delete_expired_transients();

		// Verify result.
		$this->assertIsArray( $result, 'delete_expired_transients should return an array' );
		$this->assertArrayHasKey( 'deleted', $result, 'Result should have deleted key' );
		$this->assertGreaterThanOrEqual( 1, $result['deleted'], 'Should have deleted at least 1 expired transient' );

		// Verify non-expired transient still exists.
		$active_value = get_transient( 'wpha_test_active' );
		$this->assertEquals( 'active_value', $active_value, 'Non-expired transient should still exist' );

		// Verify expired transient is gone.
		$expired_value = get_transient( 'wpha_test_expired' );
		$this->assertFalse( $expired_value, 'Expired transient should be deleted' );

		// Clean up.
		delete_transient( 'wpha_test_active' );
	}

	/**
	 * Test transient cleanup with exclusion patterns
	 */
	public function test_transient_cleanup_respects_exclusion_patterns() {
		// Skip if using external object cache.
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'External object cache is enabled' );
		}

		global $wpdb;

		// Create an expired transient with exclusion pattern.
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name' => '_transient_wpha_protected',
				'option_value' => 'protected_value',
				'autoload' => 'no',
			)
		);
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name' => '_transient_timeout_wpha_protected',
				'option_value' => time() - 3600, // Expired.
				'autoload' => 'no',
			)
		);

		// Create an expired transient without exclusion pattern.
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name' => '_transient_test_deletable',
				'option_value' => 'deletable_value',
				'autoload' => 'no',
			)
		);
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name' => '_transient_timeout_test_deletable',
				'option_value' => time() - 3600, // Expired.
				'autoload' => 'no',
			)
		);

		// Delete expired transients with exclusion pattern.
		$result = $this->transients_cleaner->delete_expired_transients( array( 'wpha_' ) );

		// Verify protected transient still exists.
		$protected = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				'_transient_wpha_protected'
			)
		);
		$this->assertEquals( 'protected_value', $protected, 'Protected transient should not be deleted' );

		// Clean up.
		delete_transient( 'wpha_protected' );
		delete_transient( 'test_deletable' );
	}

	/**
	 * Test orphaned postmeta detection is accurate
	 */
	public function test_orphaned_postmeta_detection_is_accurate() {
		global $wpdb;

		// Create a valid post with meta.
		$post_id = $this->create_test_post();
		add_post_meta( $post_id, 'test_meta_key', 'test_value' );

		// Get initial orphaned count.
		$initial_orphaned = $this->orphaned_cleaner->find_orphaned_postmeta();
		$initial_count = count( $initial_orphaned );

		// Create orphaned postmeta by directly inserting.
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id' => 999999, // Non-existent post ID.
				'meta_key' => 'orphaned_key',
				'meta_value' => 'orphaned_value',
			)
		);

		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id' => 999998, // Another non-existent post ID.
				'meta_key' => 'orphaned_key_2',
				'meta_value' => 'orphaned_value_2',
			)
		);

		// Get count via analyzer.
		$count_from_analyzer = $this->analyzer->get_orphaned_postmeta_count();
		$this->assertEquals( $initial_count + 2, $count_from_analyzer, 'Analyzer should detect 2 new orphaned postmeta' );

		// Find orphaned postmeta.
		$orphaned = $this->orphaned_cleaner->find_orphaned_postmeta();
		$this->assertCount( $initial_count + 2, $orphaned, 'Should find 2 new orphaned postmeta records' );

		// Verify valid postmeta is not detected as orphaned.
		$valid_meta = get_post_meta( $post_id, 'test_meta_key', true );
		$this->assertEquals( 'test_value', $valid_meta, 'Valid postmeta should still exist' );
	}

	/**
	 * Test orphaned commentmeta detection is accurate
	 */
	public function test_orphaned_commentmeta_detection_is_accurate() {
		global $wpdb;

		// Create a valid comment with meta.
		$post_id = $this->create_test_post();
		$comment_id = $this->create_test_comment( array(), $post_id );
		add_comment_meta( $comment_id, 'test_comment_meta', 'test_value' );

		// Get initial orphaned count.
		$initial_orphaned = $this->orphaned_cleaner->find_orphaned_commentmeta();
		$initial_count = count( $initial_orphaned );

		// Create orphaned commentmeta.
		$wpdb->insert(
			$wpdb->commentmeta,
			array(
				'comment_id' => 999999, // Non-existent comment ID.
				'meta_key' => 'orphaned_comment_key',
				'meta_value' => 'orphaned_comment_value',
			)
		);

		// Get count via analyzer.
		$count_from_analyzer = $this->analyzer->get_orphaned_commentmeta_count();
		$this->assertEquals( $initial_count + 1, $count_from_analyzer, 'Analyzer should detect 1 new orphaned commentmeta' );

		// Find orphaned commentmeta.
		$orphaned = $this->orphaned_cleaner->find_orphaned_commentmeta();
		$this->assertCount( $initial_count + 1, $orphaned, 'Should find 1 new orphaned commentmeta record' );
	}

	/**
	 * Test orphaned termmeta detection is accurate
	 */
	public function test_orphaned_termmeta_detection_is_accurate() {
		global $wpdb;

		// Create a valid term with meta.
		$term = wp_insert_term( 'Test Term', 'category' );
		if ( ! is_wp_error( $term ) ) {
			add_term_meta( $term['term_id'], 'test_term_meta', 'test_value' );
		}

		// Get initial orphaned count.
		$initial_orphaned = $this->orphaned_cleaner->find_orphaned_termmeta();
		$initial_count = count( $initial_orphaned );

		// Create orphaned termmeta.
		$wpdb->insert(
			$wpdb->termmeta,
			array(
				'term_id' => 999999, // Non-existent term ID.
				'meta_key' => 'orphaned_term_key',
				'meta_value' => 'orphaned_term_value',
			)
		);

		// Get count via analyzer.
		$count_from_analyzer = $this->analyzer->get_orphaned_termmeta_count();
		$this->assertEquals( $initial_count + 1, $count_from_analyzer, 'Analyzer should detect 1 new orphaned termmeta' );

		// Find orphaned termmeta.
		$orphaned = $this->orphaned_cleaner->find_orphaned_termmeta();
		$this->assertCount( $initial_count + 1, $orphaned, 'Should find 1 new orphaned termmeta record' );
	}

	/**
	 * Test delete orphaned postmeta
	 */
	public function test_delete_orphaned_postmeta() {
		global $wpdb;

		// Create orphaned postmeta.
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id' => 999999,
				'meta_key' => 'delete_test_orphaned',
				'meta_value' => 'delete_test_value',
			)
		);

		$orphaned_meta_id = $wpdb->insert_id;

		// Get initial count.
		$initial_count = $this->analyzer->get_orphaned_postmeta_count();
		$this->assertGreaterThan( 0, $initial_count, 'Should have orphaned postmeta' );

		// Delete orphaned postmeta.
		$deleted_count = $this->orphaned_cleaner->delete_orphaned_postmeta();

		// Verify deletion.
		$this->assertGreaterThan( 0, $deleted_count, 'Should have deleted orphaned postmeta' );

		// Verify it's gone.
		$meta_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_id = %d",
				$orphaned_meta_id
			)
		);
		$this->assertEquals( 0, $meta_exists, 'Orphaned postmeta should be deleted' );
	}

	/**
	 * Test table optimization runs without error
	 */
	public function test_table_optimization_runs_without_error() {
		global $wpdb;

		// Get a WordPress table to optimize.
		$table_name = $wpdb->posts;

		// Optimize the table.
		$result = $this->optimizer->optimize_table( $table_name );

		// Verify result.
		$this->assertIsArray( $result, 'optimize_table should return an array' );
		$this->assertArrayHasKey( 'table', $result, 'Result should have table key' );
		$this->assertArrayHasKey( 'engine', $result, 'Result should have engine key' );
		$this->assertArrayHasKey( 'size_before', $result, 'Result should have size_before key' );
		$this->assertArrayHasKey( 'size_after', $result, 'Result should have size_after key' );
		$this->assertArrayHasKey( 'command', $result, 'Result should have command key' );
		$this->assertEquals( $table_name, $result['table'], 'Should optimize the correct table' );
	}

	/**
	 * Test table optimization for non-WordPress table fails
	 */
	public function test_table_optimization_for_non_wordpress_table_fails() {
		// Try to optimize a non-WordPress table.
		$result = $this->optimizer->optimize_table( 'some_random_table' );

		// Should return false.
		$this->assertFalse( $result, 'Should not optimize non-WordPress tables' );
	}

	/**
	 * Test optimize all tables
	 */
	public function test_optimize_all_tables() {
		// Optimize all tables.
		$results = $this->optimizer->optimize_all_tables();

		// Verify results.
		$this->assertIsArray( $results, 'optimize_all_tables should return an array' );
		$this->assertNotEmpty( $results, 'Should optimize at least one table' );

		// Verify each result has required keys.
		foreach ( $results as $result ) {
			$this->assertArrayHasKey( 'table', $result, 'Each result should have table key' );
			$this->assertArrayHasKey( 'engine', $result, 'Each result should have engine key' );
		}
	}

	/**
	 * Test get tables needing optimization
	 */
	public function test_get_tables_needing_optimization() {
		$tables = $this->optimizer->get_tables_needing_optimization();

		// Verify result.
		$this->assertIsArray( $tables, 'get_tables_needing_optimization should return an array' );

		// If there are tables with overhead, verify structure.
		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				$this->assertArrayHasKey( 'name', $table, 'Table should have name key' );
				$this->assertArrayHasKey( 'overhead', $table, 'Table should have overhead key' );
				$this->assertArrayHasKey( 'engine', $table, 'Table should have engine key' );
				$this->assertGreaterThan( 0, $table['overhead'], 'Overhead should be greater than 0' );
			}
		}
	}

	/**
	 * Test edge case: delete revisions with no revisions
	 */
	public function test_delete_revisions_with_no_revisions() {
		// Create a post without any updates (no revisions).
		$post_id = $this->create_test_post();

		// Try to delete revisions.
		$result = $this->revisions_manager->delete_revisions_for_post( $post_id );

		// Should return zero deleted.
		$this->assertEquals( 0, $result['deleted'], 'Should delete 0 revisions when none exist' );
		$this->assertEquals( 0, $result['bytes_freed'], 'Should free 0 bytes when no revisions deleted' );
	}

	/**
	 * Test edge case: expired transient count when no expired transients
	 */
	public function test_expired_transient_count_when_none_expired() {
		// Skip if using external object cache.
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'External object cache is enabled' );
		}

		// Clear any expired transients first.
		$this->transients_cleaner->delete_expired_transients();

		// Create only active transients.
		set_transient( 'wpha_test_active_1', 'value1', 3600 );
		set_transient( 'wpha_test_active_2', 'value2', 3600 );

		// Try to delete expired transients.
		$result = $this->transients_cleaner->delete_expired_transients();

		// Should not delete any active transients.
		$this->assertTrue( $result['deleted'] >= 0, 'Should return non-negative deleted count' );

		// Verify active transients still exist.
		$this->assertEquals( 'value1', get_transient( 'wpha_test_active_1' ) );
		$this->assertEquals( 'value2', get_transient( 'wpha_test_active_2' ) );

		// Clean up.
		delete_transient( 'wpha_test_active_1' );
		delete_transient( 'wpha_test_active_2' );
	}

	/**
	 * Test delete all revisions
	 */
	public function test_delete_all_revisions() {
		// Create multiple posts with revisions.
		$post_id_1 = $this->create_test_post( array( 'post_title' => 'Post 1' ) );
		$post_id_2 = $this->create_test_post( array( 'post_title' => 'Post 2' ) );

		// Create revisions for both posts.
		for ( $i = 1; $i <= 3; $i++ ) {
			wp_update_post(
				array(
					'ID' => $post_id_1,
					'post_content' => "Updated content $i",
				)
			);
			wp_update_post(
				array(
					'ID' => $post_id_2,
					'post_content' => "Updated content $i",
				)
			);
		}

		// Get initial count.
		$initial_count = $this->analyzer->get_revisions_count();
		$this->assertGreaterThanOrEqual( 6, $initial_count, 'Should have at least 6 revisions' );

		// Delete all revisions.
		$result = $this->revisions_manager->delete_all_revisions();

		// Verify result.
		$this->assertGreaterThanOrEqual( 6, $result['deleted'], 'Should delete at least 6 revisions' );

		// Verify count is reduced.
		$after_count = $this->analyzer->get_revisions_count();
		$this->assertLessThan( $initial_count, $after_count, 'Revision count should be reduced' );
	}
}
