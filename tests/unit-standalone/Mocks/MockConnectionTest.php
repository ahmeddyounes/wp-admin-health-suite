<?php
/**
 * Mock Connection Unit Tests (Standalone)
 *
 * Tests for the mock database connection used in testing.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Mocks
 */

namespace WPAdminHealth\Tests\UnitStandalone\Mocks;

use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Mock Connection test class.
 */
class MockConnectionTest extends StandaloneTestCase {

	/**
	 * Mock connection instance.
	 *
	 * @var MockConnection
	 */
	protected MockConnection $connection;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
	}

	/**
	 * Test MockConnection implements ConnectionInterface.
	 */
	public function test_implements_connection_interface(): void {
		$this->assertInstanceOf( ConnectionInterface::class, $this->connection );
	}

	/**
	 * Test get prefix.
	 */
	public function test_get_prefix(): void {
		$this->assertEquals( 'wp_', $this->connection->get_prefix() );

		$this->connection->set_prefix( 'custom_' );
		$this->assertEquals( 'custom_', $this->connection->get_prefix() );
	}

	/**
	 * Test recording queries.
	 */
	public function test_records_queries(): void {
		$this->connection->get_var( 'SELECT 1' );
		$this->connection->get_results( 'SELECT * FROM posts' );

		$queries = $this->connection->get_queries();

		$this->assertCount( 2, $queries );
		$this->assertEquals( 'SELECT 1', $queries[0]['query'] );
		$this->assertEquals( 'SELECT * FROM posts', $queries[1]['query'] );
	}

	/**
	 * Test get last query.
	 */
	public function test_get_last_query(): void {
		$this->assertNull( $this->connection->get_last_query() );

		$this->connection->get_var( 'SELECT 1' );
		$this->connection->get_var( 'SELECT 2' );

		$last = $this->connection->get_last_query();
		$this->assertEquals( 'SELECT 2', $last['query'] );
	}

	/**
	 * Test reset queries.
	 */
	public function test_reset_queries(): void {
		$this->connection->get_var( 'SELECT 1' );
		$this->connection->get_var( 'SELECT 2' );

		$this->assertCount( 2, $this->connection->get_queries() );

		$this->connection->reset_queries();

		$this->assertCount( 0, $this->connection->get_queries() );
	}

	/**
	 * Test set expected result exact match.
	 */
	public function test_expected_result_exact_match(): void {
		$this->connection->set_expected_result( 'SELECT COUNT(*) FROM posts', 42 );

		$result = $this->connection->get_var( 'SELECT COUNT(*) FROM posts' );

		$this->assertEquals( 42, $result );
	}

	/**
	 * Test set expected result for get_results.
	 */
	public function test_expected_result_get_results(): void {
		$expected_data = array( (object) array( 'ID' => 1, 'title' => 'Test' ) );
		$this->connection->set_expected_result( 'SELECT * FROM posts', $expected_data );

		$result = $this->connection->get_results( 'SELECT * FROM posts' );

		$this->assertCount( 1, $result );
		$this->assertEquals( 1, $result[0]->ID );
		$this->assertEquals( 'Test', $result[0]->title );
	}

	/**
	 * Test default result.
	 */
	public function test_default_result(): void {
		$this->assertNull( $this->connection->get_var( 'SELECT something' ) );

		$this->connection->set_default_result( 'default_value' );

		$this->assertEquals( 'default_value', $this->connection->get_var( 'SELECT something' ) );
	}

	/**
	 * Test prepare method.
	 */
	public function test_prepare(): void {
		$query = $this->connection->prepare(
			'SELECT * FROM wp_posts WHERE ID = %d AND post_type = %s',
			123,
			'page'
		);

		$this->assertEquals( "SELECT * FROM wp_posts WHERE ID = 123 AND post_type = 'page'", $query );
	}

	/**
	 * Test insert method.
	 */
	public function test_insert(): void {
		$result = $this->connection->insert(
			'wp_posts',
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			)
		);

		$this->assertEquals( 1, $result );

		$queries = $this->connection->get_queries();
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'INSERT INTO wp_posts', $queries[0]['query'] );
	}

	/**
	 * Test update method.
	 */
	public function test_update(): void {
		$result = $this->connection->update(
			'wp_posts',
			array( 'post_status' => 'draft' ),
			array( 'ID' => 1 )
		);

		$this->assertEquals( 1, $result );

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'UPDATE wp_posts', $queries[0]['query'] );
		$this->assertStringContainsString( "post_status = 'draft'", $queries[0]['query'] );
	}

	/**
	 * Test delete method.
	 */
	public function test_delete(): void {
		$result = $this->connection->delete(
			'wp_posts',
			array( 'ID' => 1 )
		);

		$this->assertEquals( 1, $result );

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'DELETE FROM wp_posts', $queries[0]['query'] );
	}

	/**
	 * Test get_col returns array.
	 */
	public function test_get_col_returns_array(): void {
		$this->connection->set_expected_result( 'SELECT ID FROM posts', array( 1, 2, 3 ) );

		$result = $this->connection->get_col( 'SELECT ID FROM posts' );

		$this->assertEquals( array( 1, 2, 3 ), $result );
	}

	/**
	 * Test get_col returns empty array on no result.
	 */
	public function test_get_col_returns_empty_array_on_no_result(): void {
		$result = $this->connection->get_col( 'SELECT ID FROM posts' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test insert_id.
	 */
	public function test_insert_id(): void {
		$this->assertEquals( 0, $this->connection->get_insert_id() );

		$this->connection->set_insert_id( 42 );

		$this->assertEquals( 42, $this->connection->get_insert_id() );
	}

	/**
	 * Test last error.
	 */
	public function test_last_error(): void {
		$this->assertEquals( '', $this->connection->get_last_error() );

		$this->connection->set_last_error( 'Something went wrong' );

		$this->assertEquals( 'Something went wrong', $this->connection->get_last_error() );
	}

	/**
	 * Test esc_like.
	 */
	public function test_esc_like(): void {
		$this->assertEquals( '100\%', $this->connection->esc_like( '100%' ) );
		$this->assertEquals( 'test\_value', $this->connection->esc_like( 'test_value' ) );
	}

	/**
	 * Test full reset.
	 */
	public function test_reset(): void {
		$this->connection->set_expected_result( 'SELECT 1', 1 );
		$this->connection->set_default_result( 'default' );
		$this->connection->set_insert_id( 42 );
		$this->connection->set_last_error( 'error' );
		$this->connection->set_rows_affected( 5 );
		$this->connection->get_var( 'SELECT 1' );

		$this->connection->reset();

		$this->assertCount( 0, $this->connection->get_queries() );
		$this->assertNull( $this->connection->get_var( 'SELECT 1' ) );
		$this->assertEquals( 0, $this->connection->get_insert_id() );
		$this->assertEquals( '', $this->connection->get_last_error() );
		$this->assertEquals( 1, $this->connection->get_rows_affected() );
	}

	/**
	 * Test rows_affected.
	 */
	public function test_rows_affected(): void {
		$this->assertEquals( 1, $this->connection->get_rows_affected() );

		$this->connection->set_rows_affected( 10 );

		$this->assertEquals( 10, $this->connection->get_rows_affected() );
	}

	/**
	 * Test pattern matching with multi-char wildcard (%%).
	 */
	public function test_pattern_matching_with_multi_char_wildcard(): void {
		$this->connection->set_expected_result( 'SELECT * FROM wp_posts WHERE ID = %%', 'matched' );

		$result = $this->connection->get_var( 'SELECT * FROM wp_posts WHERE ID = 123' );

		$this->assertEquals( 'matched', $result );
	}

	/**
	 * Test pattern matching with single-char wildcard (__).
	 */
	public function test_pattern_matching_with_single_char_wildcard(): void {
		// __ matches exactly one character, so wp_posts__ matches wp_postsx
		$this->connection->set_expected_result( 'SELECT * FROM wp_posts__', array( (object) array( 'table' => 'posts' ) ) );

		$result = $this->connection->get_results( 'SELECT * FROM wp_postsx' );

		$this->assertCount( 1, $result );
	}

	/**
	 * Test pattern matching prefers exact match over wildcard.
	 */
	public function test_exact_match_over_wildcard(): void {
		$this->connection->set_expected_result( 'SELECT * FROM wp_posts', 'exact' );
		$this->connection->set_expected_result( 'SELECT * FROM %%', 'wildcard' );

		$result = $this->connection->get_var( 'SELECT * FROM wp_posts' );

		$this->assertEquals( 'exact', $result );
	}

	/**
	 * Test table name helper methods.
	 */
	public function test_table_name_helpers(): void {
		$this->assertEquals( 'wp_posts', $this->connection->get_posts_table() );
		$this->assertEquals( 'wp_postmeta', $this->connection->get_postmeta_table() );
		$this->assertEquals( 'wp_comments', $this->connection->get_comments_table() );
		$this->assertEquals( 'wp_commentmeta', $this->connection->get_commentmeta_table() );
		$this->assertEquals( 'wp_options', $this->connection->get_options_table() );
		$this->assertEquals( 'wp_terms', $this->connection->get_terms_table() );
		$this->assertEquals( 'wp_termmeta', $this->connection->get_termmeta_table() );

		$this->connection->set_prefix( 'custom_' );

		$this->assertEquals( 'custom_posts', $this->connection->get_posts_table() );
		$this->assertEquals( 'custom_options', $this->connection->get_options_table() );
	}

	/**
	 * Test get_row returns expected result.
	 */
	public function test_get_row(): void {
		$expected = (object) array( 'ID' => 1, 'title' => 'Test Post' );
		$this->connection->set_expected_result( 'SELECT * FROM wp_posts WHERE ID = 1', $expected );

		$result = $this->connection->get_row( 'SELECT * FROM wp_posts WHERE ID = 1' );

		$this->assertEquals( $expected, $result );
		$this->assertEquals( 1, $result->ID );
	}

	/**
	 * Test get_row returns null by default.
	 */
	public function test_get_row_returns_null_by_default(): void {
		$result = $this->connection->get_row( 'SELECT * FROM wp_posts WHERE ID = 999' );

		$this->assertNull( $result );
	}

	/**
	 * Test query method executes and records query.
	 */
	public function test_query(): void {
		$result = $this->connection->query( 'CREATE TABLE test (id INT)' );

		$this->assertTrue( $result );

		$queries = $this->connection->get_queries();
		$this->assertCount( 1, $queries );
		$this->assertEquals( 'CREATE TABLE test (id INT)', $queries[0]['query'] );
	}

	/**
	 * Test query method with expected result.
	 */
	public function test_query_with_expected_result(): void {
		$this->connection->set_expected_result( 'DROP TABLE test', false );

		$result = $this->connection->query( 'DROP TABLE test' );

		$this->assertFalse( $result );
	}

	/**
	 * Test insert uses rows_affected.
	 */
	public function test_insert_uses_rows_affected(): void {
		$this->connection->set_rows_affected( 5 );

		$result = $this->connection->insert(
			'wp_posts',
			array( 'post_title' => 'Test' )
		);

		$this->assertEquals( 5, $result );
	}

	/**
	 * Test update uses rows_affected.
	 */
	public function test_update_uses_rows_affected(): void {
		$this->connection->set_rows_affected( 3 );

		$result = $this->connection->update(
			'wp_posts',
			array( 'post_status' => 'draft' ),
			array( 'post_type' => 'post' )
		);

		$this->assertEquals( 3, $result );
	}

	/**
	 * Test delete uses rows_affected.
	 */
	public function test_delete_uses_rows_affected(): void {
		$this->connection->set_rows_affected( 7 );

		$result = $this->connection->delete(
			'wp_posts',
			array( 'post_status' => 'trash' )
		);

		$this->assertEquals( 7, $result );
	}

	/**
	 * Test prepare with float placeholder.
	 */
	public function test_prepare_with_float(): void {
		$query = $this->connection->prepare(
			'SELECT * FROM wp_posts WHERE price = %f',
			19.99
		);

		$this->assertEquals( 'SELECT * FROM wp_posts WHERE price = 19.99', $query );
	}

	/**
	 * Test insert with numeric values.
	 */
	public function test_insert_with_numeric_values(): void {
		$this->connection->insert(
			'wp_posts',
			array(
				'post_title' => 'Test',
				'post_parent' => 5,
				'menu_order' => 0,
			)
		);

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'post_parent, menu_order', $queries[0]['query'] );
		$this->assertStringContainsString( '5, 0', $queries[0]['query'] );
	}

	/**
	 * Test update with multiple where conditions.
	 */
	public function test_update_with_multiple_where_conditions(): void {
		$this->connection->update(
			'wp_posts',
			array( 'post_status' => 'publish' ),
			array(
				'post_type' => 'post',
				'post_author' => 1,
			)
		);

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( "post_type = 'post' AND post_author = 1", $queries[0]['query'] );
	}

	/**
	 * Test insert handles NULL values correctly.
	 */
	public function test_insert_handles_null_values(): void {
		$this->connection->insert(
			'wp_posts',
			array(
				'post_title'  => 'Test',
				'post_parent' => null,
			)
		);

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'NULL', $queries[0]['query'] );
		$this->assertStringNotContainsString( "''", $queries[0]['query'] );
	}

	/**
	 * Test insert handles boolean values correctly.
	 */
	public function test_insert_handles_boolean_values(): void {
		$this->connection->insert(
			'wp_options',
			array(
				'option_name'  => 'test_option',
				'autoload'     => true,
				'disabled'     => false,
			)
		);

		$queries = $this->connection->get_queries();
		// true should become '1', false should become '0'
		$this->assertStringContainsString( '1', $queries[0]['query'] );
		$this->assertStringContainsString( '0', $queries[0]['query'] );
	}

	/**
	 * Test update handles NULL values correctly.
	 */
	public function test_update_handles_null_values(): void {
		$this->connection->update(
			'wp_posts',
			array( 'post_parent' => null ),
			array( 'ID' => 1 )
		);

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'post_parent = NULL', $queries[0]['query'] );
	}

	/**
	 * Test update handles boolean values correctly.
	 */
	public function test_update_handles_boolean_values(): void {
		$this->connection->update(
			'wp_options',
			array( 'autoload' => false ),
			array( 'option_name' => 'test' )
		);

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'autoload = 0', $queries[0]['query'] );
	}

	/**
	 * Test delete handles various value types correctly.
	 */
	public function test_delete_handles_various_value_types(): void {
		$this->connection->delete(
			'wp_posts',
			array(
				'post_status' => 'trash',
				'post_parent' => 0,
			)
		);

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( "post_status = 'trash'", $queries[0]['query'] );
		$this->assertStringContainsString( 'post_parent = 0', $queries[0]['query'] );
	}

	/**
	 * Test insert escapes string values properly.
	 */
	public function test_insert_escapes_strings(): void {
		$this->connection->insert(
			'wp_posts',
			array(
				'post_title' => "Test's \"Post\"",
			)
		);

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( "Test\\'s \\\"Post\\\"", $queries[0]['query'] );
	}

	/**
	 * Test query with empty string returns expected result.
	 */
	public function test_query_empty_string(): void {
		$this->connection->set_expected_result( '', 'empty_result' );

		$result = $this->connection->query( '' );

		$this->assertEquals( 'empty_result', $result );
	}

	/**
	 * Test insert with empty data array.
	 */
	public function test_insert_empty_data(): void {
		$result = $this->connection->insert( 'wp_posts', array() );

		// insert() returns number of rows affected (1 by default) or null/false on failure.
		$this->assertEquals( 1, $result );
		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'INSERT INTO wp_posts ()', $queries[0]['query'] );
	}

	/**
	 * Test update with empty data array.
	 */
	public function test_update_empty_data(): void {
		$result = $this->connection->update(
			'wp_posts',
			array(),
			array( 'ID' => 1 )
		);

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'UPDATE wp_posts SET', $queries[0]['query'] );
	}

	/**
	 * Test delete with empty where array.
	 */
	public function test_delete_empty_where(): void {
		$result = $this->connection->delete( 'wp_posts', array() );

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'DELETE FROM wp_posts WHERE', $queries[0]['query'] );
	}

	/**
	 * Test prepare with special SQL characters.
	 *
	 * Note: MockConnection's prepare() does basic substitution for testing.
	 * It escapes single quotes but doesn't provide full SQL injection protection.
	 * In production, the real WPDB prepare() handles this.
	 */
	public function test_prepare_special_sql_chars(): void {
		$result = $this->connection->prepare(
			"SELECT * FROM posts WHERE title = %s",
			"test'; DROP TABLE users;--"
		);

		// Should escape single quotes.
		$this->assertStringContainsString( "\\'", $result );
	}

	/**
	 * Test multiple sequential queries maintain order.
	 */
	public function test_query_order_preserved(): void {
		$this->connection->query( 'SELECT 1' );
		$this->connection->query( 'SELECT 2' );
		$this->connection->query( 'SELECT 3' );

		$queries = $this->connection->get_queries();

		$this->assertCount( 3, $queries );
		$this->assertEquals( 'SELECT 1', $queries[0]['query'] );
		$this->assertEquals( 'SELECT 2', $queries[1]['query'] );
		$this->assertEquals( 'SELECT 3', $queries[2]['query'] );
	}

	/**
	 * Test query pattern with percent sign in value.
	 */
	public function test_pattern_with_percent_in_value(): void {
		$this->connection->set_expected_result( 'SELECT %% FROM table', 'result' );

		$result = $this->connection->query( 'SELECT 100% FROM table' );

		$this->assertEquals( 'result', $result );
	}

	/**
	 * Test reset clears all state.
	 */
	public function test_reset_clears_all_state(): void {
		$this->connection->set_expected_result( 'query', 'result' );
		$this->connection->set_default_result( 'default' );
		$this->connection->set_last_error( 'error' );
		$this->connection->set_insert_id( 100 );
		$this->connection->set_rows_affected( 50 );
		$this->connection->query( 'SELECT 1' );

		$this->connection->reset();

		$this->assertCount( 0, $this->connection->get_queries() );
		$this->assertEquals( 0, $this->connection->get_insert_id() );
		$this->assertEquals( '', $this->connection->get_last_error() );
		$this->assertEquals( 1, $this->connection->get_rows_affected() );
	}

	/**
	 * Test esc_like escapes wildcard characters.
	 */
	public function test_esc_like_escapes_wildcards(): void {
		$this->assertEquals( '100\\%', $this->connection->esc_like( '100%' ) );
		$this->assertEquals( 'test\\_value', $this->connection->esc_like( 'test_value' ) );
		$this->assertEquals( '100\\%\\_test', $this->connection->esc_like( '100%_test' ) );
	}

	/**
	 * Test large result set handling.
	 */
	public function test_large_result_set(): void {
		$large_results = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$large_results[] = (object) array( 'id' => $i, 'value' => "item_{$i}" );
		}

		$this->connection->set_expected_result( 'SELECT * FROM large_table', $large_results );

		$result = $this->connection->get_results( 'SELECT * FROM large_table' );

		$this->assertCount( 1000, $result );
		$this->assertEquals( 0, $result[0]->id );
		$this->assertEquals( 999, $result[999]->id );
	}

	/**
	 * Test get_col returns empty array when result is scalar.
	 */
	public function test_get_col_returns_empty_array_on_scalar_result(): void {
		$this->connection->set_expected_result( 'SELECT id FROM posts', 'not_an_array' );

		$result = $this->connection->get_col( 'SELECT id FROM posts' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_results returns empty array when result is scalar.
	 */
	public function test_get_results_returns_empty_array_on_scalar_result(): void {
		$this->connection->set_expected_result( 'SELECT * FROM posts', 'not_an_array' );

		$result = $this->connection->get_results( 'SELECT * FROM posts' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test prepare with no placeholders returns original query.
	 */
	public function test_prepare_no_placeholders(): void {
		$query = 'SELECT * FROM wp_posts WHERE ID = 1';

		$result = $this->connection->prepare( $query );

		$this->assertEquals( $query, $result );
	}

	/**
	 * Test prepare with extra args ignores them.
	 */
	public function test_prepare_extra_args_ignored(): void {
		$result = $this->connection->prepare(
			'SELECT * FROM wp_posts WHERE ID = %d',
			123,
			'extra',
			'args'
		);

		$this->assertEquals( 'SELECT * FROM wp_posts WHERE ID = 123', $result );
	}

	/**
	 * Test prepare with missing args leaves placeholder.
	 */
	public function test_prepare_missing_args_leaves_placeholder(): void {
		$result = $this->connection->prepare(
			'SELECT * FROM wp_posts WHERE ID = %d AND status = %s'
		);

		// With no args, placeholders remain.
		$this->assertStringContainsString( '%d', $result );
		$this->assertStringContainsString( '%s', $result );
	}

	/**
	 * Test Unicode characters in queries.
	 */
	public function test_unicode_in_queries(): void {
		$unicode_query = "SELECT * FROM posts WHERE title = '日本語テスト'";

		$this->connection->set_expected_result( $unicode_query, 'unicode_result' );

		$result = $this->connection->get_var( $unicode_query );

		$this->assertEquals( 'unicode_result', $result );

		$queries = $this->connection->get_queries();
		$this->assertEquals( $unicode_query, $queries[0]['query'] );
	}

	/**
	 * Test insert with Unicode values.
	 */
	public function test_insert_unicode_values(): void {
		$this->connection->insert(
			'wp_posts',
			array(
				'post_title' => 'Test émojis 🎉 and 日本語',
			)
		);

		$queries = $this->connection->get_queries();
		$this->assertStringContainsString( 'émojis', $queries[0]['query'] );
		$this->assertStringContainsString( '🎉', $queries[0]['query'] );
		$this->assertStringContainsString( '日本語', $queries[0]['query'] );
	}

	/**
	 * Test pattern matching is case insensitive.
	 */
	public function test_pattern_matching_case_insensitive(): void {
		$this->connection->set_expected_result( 'SELECT * FROM WP_POSTS WHERE ID = %%', 'matched' );

		$result = $this->connection->get_var( 'SELECT * FROM wp_posts WHERE ID = 123' );

		$this->assertEquals( 'matched', $result );
	}

	/**
	 * Test get_var returns null by default.
	 */
	public function test_get_var_returns_null_by_default(): void {
		$result = $this->connection->get_var( 'SELECT nonexistent FROM nowhere' );

		$this->assertNull( $result );
	}

	/**
	 * Test query records timestamp.
	 */
	public function test_query_records_timestamp(): void {
		$before = microtime( true );
		$this->connection->query( 'SELECT 1' );
		$after = microtime( true );

		$queries = $this->connection->get_queries();

		$this->assertArrayHasKey( 'time', $queries[0] );
		$this->assertGreaterThanOrEqual( $before, $queries[0]['time'] );
		$this->assertLessThanOrEqual( $after, $queries[0]['time'] );
	}

	/**
	 * Test esc_like with empty string.
	 */
	public function test_esc_like_empty_string(): void {
		$result = $this->connection->esc_like( '' );

		$this->assertEquals( '', $result );
	}

	/**
	 * Test esc_like with no special characters.
	 */
	public function test_esc_like_no_special_chars(): void {
		$result = $this->connection->esc_like( 'normal string' );

		$this->assertEquals( 'normal string', $result );
	}

	/**
	 * Test multiple wildcards in single pattern.
	 */
	public function test_multiple_wildcards_in_pattern(): void {
		$this->connection->set_expected_result( 'SELECT %% FROM %% WHERE %%', 'multi_match' );

		$result = $this->connection->get_var( 'SELECT id FROM users WHERE status = active' );

		$this->assertEquals( 'multi_match', $result );
	}

	/**
	 * Test combined multi-char and single-char wildcards.
	 */
	public function test_combined_wildcards(): void {
		// %% followed by __ should match multiple chars then exactly one char.
		$this->connection->set_expected_result( 'SELECT * FROM wp_post%%meta__', 'combined' );

		$result = $this->connection->get_var( 'SELECT * FROM wp_postmetax' );

		$this->assertEquals( 'combined', $result );
	}
}
