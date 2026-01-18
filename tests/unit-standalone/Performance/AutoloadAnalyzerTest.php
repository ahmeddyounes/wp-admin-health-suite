<?php
/**
 * Unit tests for AutoloadAnalyzer class.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Performance;

use WPAdminHealth\Performance\AutoloadAnalyzer;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Test class for AutoloadAnalyzer.
 *
 * @covers \WPAdminHealth\Performance\AutoloadAnalyzer
 */
class AutoloadAnalyzerTest extends StandaloneTestCase {

	/**
	 * Mock database connection.
	 *
	 * @var MockConnection
	 */
	private MockConnection $connection;

	/**
	 * AutoloadAnalyzer instance.
	 *
	 * @var AutoloadAnalyzer
	 */
	private AutoloadAnalyzer $analyzer;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setup_test_environment(): void {
		$this->connection = new MockConnection();
		$this->analyzer   = new AutoloadAnalyzer( $this->connection );
	}

	/**
	 * Clean up test environment.
	 *
	 * @return void
	 */
	protected function cleanup_test_environment(): void {
		$this->connection->reset();
	}

	/**
	 * Test get_autoloaded_options returns empty array when no results.
	 *
	 * @return void
	 */
	public function test_get_autoloaded_options_returns_empty_when_no_results(): void {
		$this->connection->set_expected_result(
			"SELECT option_name, option_value, autoload\n\t\t\tFROM wp_options\n\t\t\tWHERE autoload = 'yes'\n\t\t\tORDER BY LENGTH(option_value) DESC",
			array()
		);

		$result = $this->analyzer->get_autoloaded_options();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_autoloaded_options returns formatted options.
	 *
	 * @return void
	 */
	public function test_get_autoloaded_options_returns_formatted_options(): void {
		$mock_options = array(
			array(
				'option_name'  => 'siteurl',
				'option_value' => 'https://example.com',
				'autoload'     => 'yes',
			),
			array(
				'option_name'  => 'woocommerce_settings',
				'option_value' => str_repeat( 'a', 15000 ),
				'autoload'     => 'yes',
			),
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			$mock_options
		);

		$result = $this->analyzer->get_autoloaded_options();

		$this->assertCount( 2, $result );
		$this->assertEquals( 'siteurl', $result[0]['name'] );
		$this->assertEquals( strlen( 'https://example.com' ), $result[0]['size'] );
		$this->assertEquals( 'core', $result[0]['source'] );
		$this->assertEquals( 'woocommerce_settings', $result[1]['name'] );
		$this->assertEquals( 'WooCommerce', $result[1]['source'] );
	}

	/**
	 * Test get_autoload_size calculates correct statistics.
	 *
	 * @return void
	 */
	public function test_get_autoload_size_calculates_statistics(): void {
		$this->connection->set_expected_result(
			"%%SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as total_size%%WHERE autoload = 'yes'%%",
			array(
				'count'      => 50,
				'total_size' => 102400, // 100 KB
			)
		);

		$result = $this->analyzer->get_autoload_size();

		$this->assertEquals( 102400, $result['total_size'] );
		$this->assertEquals( 100, $result['total_size_kb'] );
		$this->assertEquals( 0.1, $result['total_size_mb'] );
		$this->assertEquals( 50, $result['count'] );
		$this->assertEquals( 2048, $result['average_size'] );
		$this->assertEquals( 2, $result['average_size_kb'] );
	}

	/**
	 * Test get_autoload_size handles null results.
	 *
	 * @return void
	 */
	public function test_get_autoload_size_handles_null_results(): void {
		$this->connection->set_expected_result(
			"%%SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as total_size%%",
			array(
				'count'      => null,
				'total_size' => null,
			)
		);

		$result = $this->analyzer->get_autoload_size();

		$this->assertEquals( 0, $result['total_size'] );
		$this->assertEquals( 0, $result['count'] );
		$this->assertEquals( 0, $result['average_size'] );
	}

	/**
	 * Test find_large_autoloads uses default threshold.
	 *
	 * @return void
	 */
	public function test_find_large_autoloads_uses_default_threshold(): void {
		$mock_options = array(
			array(
				'option_name'  => 'small_option',
				'option_value' => str_repeat( 'a', 5000 ),
				'autoload'     => 'yes',
			),
			array(
				'option_name'  => 'large_option',
				'option_value' => str_repeat( 'a', 15000 ),
				'autoload'     => 'yes',
			),
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			$mock_options
		);

		$result = $this->analyzer->find_large_autoloads();

		// Default threshold is 10KB (10240 bytes)
		$this->assertCount( 1, $result );
		$this->assertEquals( 'large_option', $result[0]['name'] );
		$this->assertEquals( 15000, $result[0]['size'] );
		$this->assertEqualsWithDelta( 14.65, $result[0]['size_kb'], 0.01 );
	}

	/**
	 * Test find_large_autoloads accepts custom threshold.
	 *
	 * @return void
	 */
	public function test_find_large_autoloads_accepts_custom_threshold(): void {
		$mock_options = array(
			array(
				'option_name'  => 'option_5k',
				'option_value' => str_repeat( 'a', 5000 ),
				'autoload'     => 'yes',
			),
			array(
				'option_name'  => 'option_8k',
				'option_value' => str_repeat( 'a', 8000 ),
				'autoload'     => 'yes',
			),
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			$mock_options
		);

		// Custom threshold of 6000 bytes
		$result = $this->analyzer->find_large_autoloads( 6000 );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'option_8k', $result[0]['name'] );
	}

	/**
	 * Test recommend_autoload_changes returns critical for large total size.
	 *
	 * @return void
	 */
	public function test_recommend_autoload_changes_returns_critical_for_large_total_size(): void {
		// Total size > 1000KB (1MB) triggers critical
		$this->connection->set_expected_result(
			"%%SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as total_size%%",
			array(
				'count'      => 100,
				'total_size' => 1500 * 1024, // 1.5MB
			)
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			array()
		);

		$result = $this->analyzer->recommend_autoload_changes();

		$this->assertNotEmpty( $result );
		$this->assertEquals( 'critical', $result[0]['type'] );
		$this->assertEquals( 'Excessive Autoload Size', $result[0]['title'] );
		$this->assertEquals( 'high', $result[0]['priority'] );
	}

	/**
	 * Test recommend_autoload_changes returns warning for medium total size.
	 *
	 * @return void
	 */
	public function test_recommend_autoload_changes_returns_warning_for_medium_total_size(): void {
		// Total size > 500KB but < 1000KB triggers warning
		$this->connection->set_expected_result(
			"%%SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as total_size%%",
			array(
				'count'      => 50,
				'total_size' => 750 * 1024, // 750KB
			)
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			array()
		);

		$result = $this->analyzer->recommend_autoload_changes();

		$this->assertNotEmpty( $result );
		$this->assertEquals( 'warning', $result[0]['type'] );
		$this->assertEquals( 'High Autoload Size', $result[0]['title'] );
		$this->assertEquals( 'medium', $result[0]['priority'] );
	}

	/**
	 * Test recommend_autoload_changes returns success for acceptable size.
	 *
	 * @return void
	 */
	public function test_recommend_autoload_changes_returns_success_for_acceptable_size(): void {
		// Total size < 500KB is acceptable
		$this->connection->set_expected_result(
			"%%SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as total_size%%",
			array(
				'count'      => 30,
				'total_size' => 200 * 1024, // 200KB
			)
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			array()
		);

		$result = $this->analyzer->recommend_autoload_changes();

		$this->assertNotEmpty( $result );
		$this->assertEquals( 'success', $result[0]['type'] );
		$this->assertEquals( 'Autoload Size Acceptable', $result[0]['title'] );
		$this->assertEquals( 'low', $result[0]['priority'] );
	}

	/**
	 * Test recommend_autoload_changes detects transient issues.
	 *
	 * @return void
	 */
	public function test_recommend_autoload_changes_detects_transient_issues(): void {
		$this->connection->set_expected_result(
			"%%SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as total_size%%",
			array(
				'count'      => 10,
				'total_size' => 50000,
			)
		);

		$mock_options = array(
			array(
				'option_name'  => '_transient_timeout_some_transient',
				'option_value' => '12345',
				'autoload'     => 'yes',
			),
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			$mock_options
		);

		$result = $this->analyzer->recommend_autoload_changes();

		// Find transient warning
		$transient_warning = null;
		foreach ( $result as $recommendation ) {
			if ( isset( $recommendation['option'] ) && strpos( $recommendation['option'], '_transient_' ) === 0 ) {
				$transient_warning = $recommendation;
				break;
			}
		}

		$this->assertNotNull( $transient_warning );
		$this->assertEquals( 'warning', $transient_warning['type'] );
		$this->assertEquals( 'Transient with Autoload Enabled', $transient_warning['title'] );
	}

	/**
	 * Test change_autoload_status validates autoload value.
	 *
	 * @return void
	 */
	public function test_change_autoload_status_validates_autoload_value(): void {
		$result = $this->analyzer->change_autoload_status( 'test_option', 'invalid' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Invalid autoload value', $result['error'] );
	}

	/**
	 * Test change_autoload_status handles non-existent option.
	 *
	 * @return void
	 */
	public function test_change_autoload_status_handles_nonexistent_option(): void {
		// get_option returns false for non-existent options
		$result = $this->analyzer->change_autoload_status( 'nonexistent_option', 'no' );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Option does not exist.', $result['error'] );
	}

	/**
	 * Test change_autoload_status updates successfully.
	 *
	 * @return void
	 */
	public function test_change_autoload_status_updates_successfully(): void {
		// Mock get_option to return a value.
		$GLOBALS['wpha_test_options']['existing_option'] = 'some_value';

		$this->connection->set_rows_affected( 1 );

		$result = $this->analyzer->change_autoload_status( 'existing_option', 'no' );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'Successfully changed', $result['message'] );

		// Verify the update query was made.
		$queries = $this->connection->get_queries();
		$this->assertNotEmpty( $queries );

		// Clean up.
		unset( $GLOBALS['wpha_test_options']['existing_option'] );
	}

	/**
	 * Test change_autoload_status handles database failure.
	 *
	 * @return void
	 */
	public function test_change_autoload_status_handles_database_failure(): void {
		$GLOBALS['wpha_test_options']['failing_option'] = 'some_value';

		// Simulate database error.
		$this->connection->set_last_error( 'Database error' );

		$result = $this->analyzer->change_autoload_status( 'failing_option', 'no' );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Database update failed.', $result['error'] );

		unset( $GLOBALS['wpha_test_options']['failing_option'] );
	}

	/**
	 * Test large option threshold constant.
	 *
	 * @return void
	 */
	public function test_large_option_threshold_constant(): void {
		$this->assertEquals( 10240, AutoloadAnalyzer::LARGE_OPTION_THRESHOLD );
	}

	/**
	 * Test source detection for core options.
	 *
	 * @return void
	 */
	public function test_source_detection_for_core_options(): void {
		$core_options = array(
			array(
				'option_name'  => 'siteurl',
				'option_value' => 'https://example.com',
				'autoload'     => 'yes',
			),
			array(
				'option_name'  => 'home',
				'option_value' => 'https://example.com',
				'autoload'     => 'yes',
			),
			array(
				'option_name'  => 'blogname',
				'option_value' => 'Test Blog',
				'autoload'     => 'yes',
			),
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			$core_options
		);

		$result = $this->analyzer->get_autoloaded_options();

		foreach ( $result as $option ) {
			$this->assertEquals( 'core', $option['source'] );
		}
	}

	/**
	 * Test source detection for plugin options.
	 *
	 * @return void
	 */
	public function test_source_detection_for_plugin_options(): void {
		$plugin_options = array(
			array(
				'option_name'  => 'woocommerce_version',
				'option_value' => '8.0.0',
				'autoload'     => 'yes',
			),
			array(
				'option_name'  => 'yoast_seo_settings',
				'option_value' => 'data',
				'autoload'     => 'yes',
			),
			array(
				'option_name'  => 'elementor_version',
				'option_value' => '3.0.0',
				'autoload'     => 'yes',
			),
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			$plugin_options
		);

		$result = $this->analyzer->get_autoloaded_options();

		$this->assertEquals( 'WooCommerce', $result[0]['source'] );
		$this->assertEquals( 'Yoast SEO', $result[1]['source'] );
		$this->assertEquals( 'Elementor', $result[2]['source'] );
	}

	/**
	 * Test recommend_autoload_changes includes action for large options.
	 *
	 * @return void
	 */
	public function test_recommend_autoload_changes_includes_action_for_large_options(): void {
		$this->connection->set_expected_result(
			"%%SELECT COUNT(*) as count, SUM(LENGTH(option_value)) as total_size%%",
			array(
				'count'      => 10,
				'total_size' => 50000,
			)
		);

		$mock_options = array(
			array(
				'option_name'  => 'rewrite_rules',
				'option_value' => str_repeat( 'a', 120000 ), // > 100KB triggers critical
				'autoload'     => 'yes',
			),
		);

		$this->connection->set_expected_result(
			"%%SELECT option_name, option_value, autoload%%WHERE autoload = 'yes'%%",
			$mock_options
		);

		$result = $this->analyzer->recommend_autoload_changes();

		// Find the recommendation for rewrite_rules
		$rewrite_rec = null;
		foreach ( $result as $rec ) {
			if ( isset( $rec['option'] ) && 'rewrite_rules' === $rec['option'] ) {
				$rewrite_rec = $rec;
				break;
			}
		}

		$this->assertNotNull( $rewrite_rec );
		$this->assertEquals( 'critical', $rewrite_rec['type'] );
		$this->assertArrayHasKey( 'action', $rewrite_rec );
	}
}
