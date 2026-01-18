<?php
/**
 * Tests for TrashCleaner Class
 *
 * Tests for counting trashed posts/comments, deletion paths with various filters,
 * age-based filtering, and post type filtering.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Database
 */

namespace WPAdminHealth\Tests\UnitStandalone\Database;

use WPAdminHealth\Database\TrashCleaner;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test cases for TrashCleaner functionality.
 *
 * Note: wp_delete_post and wp_delete_comment stubs are defined in
 * tests/bootstrap-standalone.php and use global arrays to track calls:
 * - $GLOBALS['wpha_test_delete_post_calls']
 * - $GLOBALS['wpha_test_delete_comment_calls']
 * - $GLOBALS['wpha_test_delete_post_results']
 * - $GLOBALS['wpha_test_delete_comment_results']
 */
class TrashCleanerTest extends StandaloneTestCase {

	/**
	 * Mock connection instance.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * TrashCleaner instance.
	 *
	 * @var TrashCleaner
	 */
	private TrashCleaner $cleaner;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->cleaner    = new TrashCleaner( $this->connection );

		// Reset global test trackers.
		$GLOBALS['wpha_test_delete_post_calls']      = array();
		$GLOBALS['wpha_test_delete_comment_calls']   = array();
		$GLOBALS['wpha_test_delete_post_results']    = array();
		$GLOBALS['wpha_test_delete_comment_results'] = array();
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment(): void {
		$this->connection->reset();
		unset( $GLOBALS['wpha_test_delete_post_calls'] );
		unset( $GLOBALS['wpha_test_delete_comment_calls'] );
		unset( $GLOBALS['wpha_test_delete_post_results'] );
		unset( $GLOBALS['wpha_test_delete_comment_results'] );
	}

	// =========================================================================
	// Count Methods Tests
	// =========================================================================

	/**
	 * Test count_trashed_posts returns zero when no trashed posts.
	 */
	public function test_count_trashed_posts_returns_zero(): void {
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_status = 'trash'",
			'0'
		);

		$count = $this->cleaner->count_trashed_posts();

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test count_trashed_posts returns correct count.
	 */
	public function test_count_trashed_posts_returns_correct_count(): void {
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_posts\n\t\t\tWHERE post_status = 'trash'",
			'42'
		);

		$count = $this->cleaner->count_trashed_posts();

		$this->assertEquals( 42, $count );
	}

	/**
	 * Test count_trashed_posts uses correct table.
	 */
	public function test_count_trashed_posts_uses_correct_table(): void {
		$this->connection->set_prefix( 'custom_' );
		$this->cleaner = new TrashCleaner( $this->connection );

		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM custom_posts\n\t\t\tWHERE post_status = 'trash'",
			'10'
		);

		$count = $this->cleaner->count_trashed_posts();

		$this->assertEquals( 10, $count );
	}

	/**
	 * Test count_spam_comments returns zero when no spam.
	 */
	public function test_count_spam_comments_returns_zero(): void {
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_comments\n\t\t\tWHERE comment_approved = 'spam'",
			'0'
		);

		$count = $this->cleaner->count_spam_comments();

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test count_spam_comments returns correct count.
	 */
	public function test_count_spam_comments_returns_correct_count(): void {
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_comments\n\t\t\tWHERE comment_approved = 'spam'",
			'150'
		);

		$count = $this->cleaner->count_spam_comments();

		$this->assertEquals( 150, $count );
	}

	/**
	 * Test count_trashed_comments returns zero when no trashed comments.
	 */
	public function test_count_trashed_comments_returns_zero(): void {
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_comments\n\t\t\tWHERE comment_approved = 'trash'",
			'0'
		);

		$count = $this->cleaner->count_trashed_comments();

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test count_trashed_comments returns correct count.
	 */
	public function test_count_trashed_comments_returns_correct_count(): void {
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM wp_comments\n\t\t\tWHERE comment_approved = 'trash'",
			'25'
		);

		$count = $this->cleaner->count_trashed_comments();

		$this->assertEquals( 25, $count );
	}

	/**
	 * Test count_trashed_comments uses correct table.
	 */
	public function test_count_trashed_comments_uses_correct_table(): void {
		$this->connection->set_prefix( 'test_' );
		$this->cleaner = new TrashCleaner( $this->connection );

		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM test_comments\n\t\t\tWHERE comment_approved = 'trash'",
			'5'
		);

		$count = $this->cleaner->count_trashed_comments();

		$this->assertEquals( 5, $count );
	}

	/**
	 * Test count methods handle null result gracefully.
	 */
	public function test_count_methods_handle_null_result(): void {
		// Don't set expected result - will return null by default.
		$count = $this->cleaner->count_trashed_posts();

		$this->assertEquals( 0, $count );
	}

	// =========================================================================
	// Get Trashed Posts Tests
	// =========================================================================

	/**
	 * Test get_trashed_posts returns empty array when none exist.
	 */
	public function test_get_trashed_posts_returns_empty_array(): void {
		$this->connection->set_default_result( array() );

		$posts = $this->cleaner->get_trashed_posts();

		$this->assertIsArray( $posts );
		$this->assertEmpty( $posts );
	}

	/**
	 * Test get_trashed_posts returns all trashed posts without filter.
	 */
	public function test_get_trashed_posts_returns_all_without_filter(): void {
		$expected = array(
			array(
				'ID'            => 1,
				'post_title'    => 'Test Post 1',
				'post_type'     => 'post',
				'post_modified' => '2024-01-15 10:00:00',
			),
			array(
				'ID'            => 2,
				'post_title'    => 'Test Page 1',
				'post_type'     => 'page',
				'post_modified' => '2024-01-14 09:00:00',
			),
		);

		$this->connection->set_expected_result(
			"SELECT ID, post_title, post_type, post_modified\n\t\t\t\tFROM wp_posts\n\t\t\t\tWHERE post_status = 'trash'\n\t\t\t\tORDER BY post_modified DESC",
			$expected
		);

		$posts = $this->cleaner->get_trashed_posts();

		$this->assertCount( 2, $posts );
		$this->assertEquals( 1, $posts[0]['ID'] );
		$this->assertEquals( 'post', $posts[0]['post_type'] );
	}

	/**
	 * Test get_trashed_posts filters by post types.
	 */
	public function test_get_trashed_posts_filters_by_post_types(): void {
		$expected = array(
			array(
				'ID'            => 1,
				'post_title'    => 'Test Post 1',
				'post_type'     => 'post',
				'post_modified' => '2024-01-15 10:00:00',
			),
		);

		// Use exact query format with tabs and newlines as produced by TrashCleaner.
		$this->connection->set_expected_result(
			"SELECT ID, post_title, post_type, post_modified\n\t\t\t\tFROM wp_posts\n\t\t\t\tWHERE post_status = 'trash'\n\t\t\t\tAND post_type IN ('post')\n\t\t\t\tORDER BY post_modified DESC",
			$expected
		);

		$posts = $this->cleaner->get_trashed_posts( array( 'post' ) );

		$this->assertCount( 1, $posts );
	}

	/**
	 * Test get_trashed_posts handles multiple post types.
	 */
	public function test_get_trashed_posts_handles_multiple_post_types(): void {
		$expected = array(
			array(
				'ID'            => 1,
				'post_title'    => 'Test Post',
				'post_type'     => 'post',
				'post_modified' => '2024-01-15 10:00:00',
			),
			array(
				'ID'            => 2,
				'post_title'    => 'Test Page',
				'post_type'     => 'page',
				'post_modified' => '2024-01-14 09:00:00',
			),
		);

		// Use exact query format with tabs and newlines as produced by TrashCleaner.
		// Note: implode produces no spaces between items.
		$this->connection->set_expected_result(
			"SELECT ID, post_title, post_type, post_modified\n\t\t\t\tFROM wp_posts\n\t\t\t\tWHERE post_status = 'trash'\n\t\t\t\tAND post_type IN ('post','page')\n\t\t\t\tORDER BY post_modified DESC",
			$expected
		);

		$posts = $this->cleaner->get_trashed_posts( array( 'post', 'page' ) );

		$this->assertCount( 2, $posts );
	}

	/**
	 * Test get_trashed_posts sanitizes post types.
	 */
	public function test_get_trashed_posts_sanitizes_post_types(): void {
		$this->connection->set_expected_result(
			"%%AND post_type IN ('malicious-type')%%",
			array()
		);

		// Pass malicious input - should be sanitized.
		$posts = $this->cleaner->get_trashed_posts( array( 'malicious<script>type' ) );

		$this->assertIsArray( $posts );
	}

	// =========================================================================
	// Delete Trashed Posts Tests
	// =========================================================================

	/**
	 * Test delete_trashed_posts returns zero when no posts to delete.
	 */
	public function test_delete_trashed_posts_returns_zero_when_empty(): void {
		$this->connection->set_expected_result(
			"%%SELECT ID FROM wp_posts WHERE post_status = 'trash'%%",
			array()
		);

		$result = $this->cleaner->delete_trashed_posts();

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test delete_trashed_posts deletes all trashed posts.
	 */
	public function test_delete_trashed_posts_deletes_all(): void {
		$this->connection->set_expected_result(
			"%%SELECT ID FROM wp_posts WHERE post_status = 'trash'%%",
			array( '1', '2', '3' )
		);

		$result = $this->cleaner->delete_trashed_posts();

		$this->assertEquals( 3, $result['deleted'] );
		$this->assertEquals( 0, $result['errors'] );

		// Verify wp_delete_post was called for each post.
		$this->assertCount( 3, $GLOBALS['wpha_test_delete_post_calls'] );
		$this->assertEquals( 1, $GLOBALS['wpha_test_delete_post_calls'][0]['post_id'] );
		$this->assertTrue( $GLOBALS['wpha_test_delete_post_calls'][0]['force_delete'] );
	}

	/**
	 * Test delete_trashed_posts counts errors correctly.
	 */
	public function test_delete_trashed_posts_counts_errors(): void {
		$this->connection->set_expected_result(
			"%%SELECT ID FROM wp_posts WHERE post_status = 'trash'%%",
			array( '1', '2', '3' )
		);

		// Configure post 2 to fail deletion.
		$GLOBALS['wpha_test_delete_post_results'] = array(
			1 => (object) array( 'ID' => 1 ), // Success.
			2 => false,                        // Failure.
			3 => (object) array( 'ID' => 3 ), // Success.
		);

		$result = $this->cleaner->delete_trashed_posts();

		$this->assertEquals( 2, $result['deleted'] );
		$this->assertEquals( 1, $result['errors'] );
	}

	/**
	 * Test delete_trashed_posts filters by post types.
	 */
	public function test_delete_trashed_posts_filters_by_post_types(): void {
		$this->connection->set_expected_result(
			"%%post_type IN ('post')%%",
			array( '1', '2' )
		);

		$result = $this->cleaner->delete_trashed_posts( array( 'post' ) );

		$this->assertEquals( 2, $result['deleted'] );
	}

	/**
	 * Test delete_trashed_posts filters by age.
	 */
	public function test_delete_trashed_posts_filters_by_age(): void {
		$this->connection->set_expected_result(
			"%%post_modified < %%",
			array( '5', '6' )
		);

		$result = $this->cleaner->delete_trashed_posts( array(), 30 );

		$this->assertEquals( 2, $result['deleted'] );
	}

	/**
	 * Test delete_trashed_posts combines post type and age filters.
	 */
	public function test_delete_trashed_posts_combines_filters(): void {
		$this->connection->set_expected_result(
			"%%post_type IN ('page')%%post_modified < %%",
			array( '10' )
		);

		$result = $this->cleaner->delete_trashed_posts( array( 'page' ), 7 );

		$this->assertEquals( 1, $result['deleted'] );
	}

	/**
	 * Test delete_trashed_posts handles prepare returning null.
	 */
	public function test_delete_trashed_posts_handles_null_prepare(): void {
		// When using filters, prepare() is called. Test empty result gracefully.
		$this->connection->set_default_result( array() );

		$result = $this->cleaner->delete_trashed_posts( array( 'post' ), 30 );

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	// =========================================================================
	// Delete Spam Comments Tests
	// =========================================================================

	/**
	 * Test delete_spam_comments returns zero when none exist.
	 */
	public function test_delete_spam_comments_returns_zero_when_empty(): void {
		$this->connection->set_expected_result(
			"%%comment_approved = 'spam'%%",
			array()
		);

		$result = $this->cleaner->delete_spam_comments();

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test delete_spam_comments deletes all spam.
	 */
	public function test_delete_spam_comments_deletes_all(): void {
		$this->connection->set_expected_result(
			"%%SELECT comment_ID FROM wp_comments WHERE comment_approved = 'spam'%%ORDER BY comment_ID ASC",
			array( '100', '101', '102' )
		);

		$result = $this->cleaner->delete_spam_comments();

		$this->assertEquals( 3, $result['deleted'] );
		$this->assertEquals( 0, $result['errors'] );

		// Verify wp_delete_comment was called for each comment.
		$this->assertCount( 3, $GLOBALS['wpha_test_delete_comment_calls'] );
		$this->assertEquals( 100, $GLOBALS['wpha_test_delete_comment_calls'][0]['comment_id'] );
		$this->assertTrue( $GLOBALS['wpha_test_delete_comment_calls'][0]['force_delete'] );
	}

	/**
	 * Test delete_spam_comments filters by age.
	 */
	public function test_delete_spam_comments_filters_by_age(): void {
		$this->connection->set_expected_result(
			"%%comment_approved = 'spam'%%comment_date < %%",
			array( '200', '201' )
		);

		$result = $this->cleaner->delete_spam_comments( 14 );

		$this->assertEquals( 2, $result['deleted'] );
	}

	/**
	 * Test delete_spam_comments counts errors correctly.
	 */
	public function test_delete_spam_comments_counts_errors(): void {
		$this->connection->set_expected_result(
			"%%comment_approved = 'spam'%%",
			array( '100', '101' )
		);

		$GLOBALS['wpha_test_delete_comment_results'] = array(
			100 => true,  // Success.
			101 => false, // Failure.
		);

		$result = $this->cleaner->delete_spam_comments();

		$this->assertEquals( 1, $result['deleted'] );
		$this->assertEquals( 1, $result['errors'] );
	}

	// =========================================================================
	// Delete Trashed Comments Tests
	// =========================================================================

	/**
	 * Test delete_trashed_comments returns zero when none exist.
	 */
	public function test_delete_trashed_comments_returns_zero_when_empty(): void {
		$this->connection->set_expected_result(
			"%%comment_approved = 'trash'%%",
			array()
		);

		$result = $this->cleaner->delete_trashed_comments();

		$this->assertEquals( 0, $result['deleted'] );
		$this->assertEquals( 0, $result['errors'] );
	}

	/**
	 * Test delete_trashed_comments deletes all trashed.
	 */
	public function test_delete_trashed_comments_deletes_all(): void {
		$this->connection->set_expected_result(
			"%%SELECT comment_ID FROM wp_comments WHERE comment_approved = 'trash'%%ORDER by comment_ID ASC",
			array( '300', '301' )
		);

		$result = $this->cleaner->delete_trashed_comments();

		$this->assertEquals( 2, $result['deleted'] );
	}

	/**
	 * Test delete_trashed_comments filters by age.
	 */
	public function test_delete_trashed_comments_filters_by_age(): void {
		$this->connection->set_expected_result(
			"%%comment_approved = 'trash'%%comment_date < %%",
			array( '400' )
		);

		$result = $this->cleaner->delete_trashed_comments( 60 );

		$this->assertEquals( 1, $result['deleted'] );
	}

	// =========================================================================
	// Empty All Trash Tests
	// =========================================================================

	/**
	 * Test empty_all_trash deletes posts and comments.
	 */
	public function test_empty_all_trash_deletes_posts_and_comments(): void {
		// Setup posts to delete.
		$this->connection->set_expected_result(
			"%%SELECT ID FROM wp_posts WHERE post_status = 'trash'%%",
			array( '1', '2' )
		);

		// Setup comments to delete.
		$this->connection->set_expected_result(
			"%%SELECT comment_ID FROM wp_comments WHERE comment_approved = 'trash'%%",
			array( '100', '101', '102' )
		);

		$result = $this->cleaner->empty_all_trash();

		$this->assertEquals( 2, $result['posts_deleted'] );
		$this->assertEquals( 0, $result['posts_errors'] );
		$this->assertEquals( 3, $result['comments_deleted'] );
		$this->assertEquals( 0, $result['comments_errors'] );
	}

	/**
	 * Test empty_all_trash returns correct structure with zeros.
	 */
	public function test_empty_all_trash_returns_zeros_when_empty(): void {
		$this->connection->set_default_result( array() );

		$result = $this->cleaner->empty_all_trash();

		$this->assertArrayHasKey( 'posts_deleted', $result );
		$this->assertArrayHasKey( 'posts_errors', $result );
		$this->assertArrayHasKey( 'comments_deleted', $result );
		$this->assertArrayHasKey( 'comments_errors', $result );
		$this->assertEquals( 0, $result['posts_deleted'] );
		$this->assertEquals( 0, $result['comments_deleted'] );
	}

	/**
	 * Test empty_all_trash counts errors separately.
	 */
	public function test_empty_all_trash_counts_errors_separately(): void {
		$this->connection->set_expected_result(
			"%%SELECT ID FROM wp_posts WHERE post_status = 'trash'%%",
			array( '1', '2' )
		);
		$this->connection->set_expected_result(
			"%%SELECT comment_ID FROM wp_comments WHERE comment_approved = 'trash'%%",
			array( '100' )
		);

		$GLOBALS['wpha_test_delete_post_results'] = array(
			1 => (object) array( 'ID' => 1 ),
			2 => false,
		);
		$GLOBALS['wpha_test_delete_comment_results'] = array(
			100 => false,
		);

		$result = $this->cleaner->empty_all_trash();

		$this->assertEquals( 1, $result['posts_deleted'] );
		$this->assertEquals( 1, $result['posts_errors'] );
		$this->assertEquals( 0, $result['comments_deleted'] );
		$this->assertEquals( 1, $result['comments_errors'] );
	}

	// =========================================================================
	// Batch Processing Tests
	// =========================================================================

	/**
	 * Test batch size constant is defined.
	 */
	public function test_batch_size_constant_is_defined(): void {
		$this->assertEquals( 100, TrashCleaner::BATCH_SIZE );
	}

	/**
	 * Test delete processes large sets in batches.
	 */
	public function test_delete_processes_in_batches(): void {
		// Create 150 post IDs to test batching (BATCH_SIZE is 100).
		$post_ids = array();
		for ( $i = 1; $i <= 150; $i++ ) {
			$post_ids[] = (string) $i;
		}

		$this->connection->set_expected_result(
			"%%SELECT ID FROM wp_posts WHERE post_status = 'trash'%%",
			$post_ids
		);

		$result = $this->cleaner->delete_trashed_posts();

		$this->assertEquals( 150, $result['deleted'] );
		$this->assertCount( 150, $GLOBALS['wpha_test_delete_post_calls'] );
	}

	// =========================================================================
	// Edge Cases Tests
	// =========================================================================

	/**
	 * Test methods handle custom table prefix.
	 */
	public function test_methods_handle_custom_prefix(): void {
		$this->connection->set_prefix( 'mysite_' );
		$this->cleaner = new TrashCleaner( $this->connection );

		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM mysite_posts\n\t\t\tWHERE post_status = 'trash'",
			'5'
		);
		$this->connection->set_expected_result(
			"SELECT COUNT(*) FROM mysite_comments\n\t\t\tWHERE comment_approved = 'spam'",
			'10'
		);

		$this->assertEquals( 5, $this->cleaner->count_trashed_posts() );
		$this->assertEquals( 10, $this->cleaner->count_spam_comments() );
	}

	/**
	 * Test count methods return integer type.
	 */
	public function test_count_methods_return_integer_type(): void {
		$this->connection->set_expected_result(
			"%%FROM wp_posts%%WHERE post_status = 'trash'%%",
			'42'
		);

		$count = $this->cleaner->count_trashed_posts();

		$this->assertIsInt( $count );
	}

	/**
	 * Test delete methods return proper array structure.
	 */
	public function test_delete_methods_return_proper_structure(): void {
		$this->connection->set_default_result( array() );

		$result = $this->cleaner->delete_trashed_posts();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertIsInt( $result['deleted'] );
		$this->assertIsInt( $result['errors'] );
	}

	/**
	 * Test get_trashed_posts with empty post_types array acts like no filter.
	 */
	public function test_get_trashed_posts_empty_array_is_no_filter(): void {
		$expected = array(
			array(
				'ID'            => 1,
				'post_title'    => 'Test',
				'post_type'     => 'post',
				'post_modified' => '2024-01-15',
			),
		);

		$this->connection->set_expected_result(
			"SELECT ID, post_title, post_type, post_modified\n\t\t\t\tFROM wp_posts\n\t\t\t\tWHERE post_status = 'trash'\n\t\t\t\tORDER BY post_modified DESC",
			$expected
		);

		$posts = $this->cleaner->get_trashed_posts( array() );

		$this->assertCount( 1, $posts );
	}

	/**
	 * Test age filter with zero days means no age filter.
	 */
	public function test_age_filter_zero_means_no_filter(): void {
		$this->connection->set_expected_result(
			"SELECT ID FROM wp_posts WHERE post_status = 'trash' ORDER BY ID ASC",
			array( '1' )
		);

		$result = $this->cleaner->delete_trashed_posts( array(), 0 );

		$this->assertEquals( 1, $result['deleted'] );
	}

	/**
	 * Test age filter generates correct date threshold.
	 */
	public function test_age_filter_generates_date_threshold(): void {
		$this->connection->set_expected_result(
			"%%post_modified < '%%'%%",
			array( '1' )
		);

		$result = $this->cleaner->delete_trashed_posts( array(), 30 );

		// Verify the query was built with a date parameter.
		$queries = $this->connection->get_queries();
		$found   = false;
		foreach ( $queries as $query_info ) {
			if ( strpos( $query_info['query'], 'post_modified <' ) !== false ) {
				$found = true;
				// The query should contain a date string.
				$this->assertMatchesRegularExpression( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $query_info['query'] );
				break;
			}
		}
		$this->assertTrue( $found, 'Query with date threshold was not found' );
	}
}
