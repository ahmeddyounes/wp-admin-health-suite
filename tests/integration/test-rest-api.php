<?php
/**
 * REST API Tests
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests;

use WP_REST_Request;
use WP_REST_Server;
use WPAdminHealth\REST\REST_Controller;

/**
 * Test REST API endpoints.
 *
 * Tests authentication, response format, pagination, actions,
 * rate limiting, and HTTP status codes.
 */
class REST_API_Test extends Test_Case {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * REST controller instance.
	 *
	 * @var REST_Controller
	 */
	protected $controller;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected $admin_user_id;

	/**
	 * Test subscriber user ID.
	 *
	 * @var int
	 */
	protected $subscriber_user_id;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment() {
		parent::setup_test_environment();

		// Set up REST server.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );

		// Create test users.
		$this->admin_user_id = $this->factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$this->subscriber_user_id = $this->factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		// Create controller instance.
		$this->controller = new REST_Controller();
		$this->controller->register_routes();

		// Ensure REST API is enabled.
		$settings = \WPAdminHealth\Plugin::get_instance()->get_settings();
		update_option( 'wpha_settings', array_merge(
			$settings->get_settings(),
			array( 'enable_rest_api' => true )
		) );
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment() {
		global $wp_rest_server;
		$wp_rest_server = null;

		// Clean up rate limit transients.
		delete_transient( 'wpha_rate_limit_' . $this->admin_user_id );
		delete_transient( 'wpha_rate_limit_' . $this->subscriber_user_id );

		parent::cleanup_test_environment();
	}

	/**
	 * Test: Authentication is required for all endpoints.
	 */
	public function test_authentication_required() {
		// Test without authentication.
		$request  = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'rest_not_logged_in', $data['code'] );
	}

	/**
	 * Test: User must have manage_options capability.
	 */
	public function test_user_must_have_manage_options() {
		// Log in as subscriber (no manage_options).
		wp_set_current_user( $this->subscriber_user_id );

		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'rest_forbidden', $data['code'] );
	}

	/**
	 * Test: Valid nonce is required.
	 */
	public function test_nonce_required() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Test without nonce.
		$request  = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'rest_missing_nonce', $data['code'] );
	}

	/**
	 * Test: Invalid nonce is rejected.
	 */
	public function test_invalid_nonce_rejected() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', 'invalid_nonce' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'rest_invalid_nonce', $data['code'] );
	}

	/**
	 * Test: REST API can be disabled in settings.
	 */
	public function test_rest_api_can_be_disabled() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Disable REST API.
		$settings = \WPAdminHealth\Plugin::get_instance()->get_settings();
		update_option( 'wpha_settings', array_merge(
			$settings->get_settings(),
			array( 'enable_rest_api' => false )
		) );

		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'rest_api_disabled', $data['code'] );

		// Re-enable for other tests.
		update_option( 'wpha_settings', array_merge(
			$settings->get_settings(),
			array( 'enable_rest_api' => true )
		) );
	}

	/**
	 * Test: Response format is correct.
	 */
	public function test_response_format() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test: Response format includes debug info when debug mode is enabled.
	 */
	public function test_response_includes_debug_info_when_enabled() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Enable debug mode.
		$settings = \WPAdminHealth\Plugin::get_instance()->get_settings();
		update_option( 'wpha_settings', array_merge(
			$settings->get_settings(),
			array( 'debug_mode' => true )
		) );

		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'debug', $data );
		$this->assertArrayHasKey( 'queries', $data['debug'] );
		$this->assertArrayHasKey( 'memory_usage', $data['debug'] );
		$this->assertArrayHasKey( 'memory_peak', $data['debug'] );
		$this->assertArrayHasKey( 'time_elapsed', $data['debug'] );

		// Disable debug mode.
		update_option( 'wpha_settings', array_merge(
			$settings->get_settings(),
			array( 'debug_mode' => false )
		) );
	}

	/**
	 * Test: Pagination parameters work correctly.
	 */
	public function test_pagination_parameters() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Test with page parameter.
		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 25 );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test: Pagination validates minimum values.
	 */
	public function test_pagination_validates_minimum_values() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Test with invalid page parameter.
		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'page', 0 );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test: Pagination validates maximum values.
	 */
	public function test_pagination_validates_maximum_values() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Test with per_page exceeding max.
		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'per_page', 150 );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test: GET endpoint works correctly.
	 */
	public function test_get_items_endpoint() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test: GET single item endpoint works correctly.
	 */
	public function test_get_item_endpoint() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'GET', '/wpha/v1/123' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 123, $data['data']['id'] );
	}

	/**
	 * Test: POST endpoint works correctly.
	 */
	public function test_create_item_endpoint() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test: DELETE endpoint works correctly.
	 */
	public function test_delete_item_endpoint() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'DELETE', '/wpha/v1/123' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 123, $data['data']['id'] );
	}

	/**
	 * Test: Rate limiting works correctly.
	 */
	public function test_rate_limiting() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Set a low rate limit for testing.
		$settings = \WPAdminHealth\Plugin::get_instance()->get_settings();
		update_option( 'wpha_settings', array_merge(
			$settings->get_settings(),
			array( 'rest_api_rate_limit' => 3 )
		) );

		// Clean up any existing rate limit transients.
		delete_transient( 'wpha_rate_limit_' . $this->admin_user_id );

		// Make requests up to the limit.
		for ( $i = 0; $i < 3; $i++ ) {
			$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
			$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
			$response = $this->server->dispatch( $request );
			$this->assertEquals( 200, $response->get_status() );
		}

		// Next request should be rate limited.
		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 429, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'rest_rate_limit_exceeded', $data['code'] );

		// Reset rate limit to default.
		update_option( 'wpha_settings', array_merge(
			$settings->get_settings(),
			array( 'rest_api_rate_limit' => 60 )
		) );
		delete_transient( 'wpha_rate_limit_' . $this->admin_user_id );
	}

	/**
	 * Test: Rate limit setting can be configured.
	 */
	public function test_rate_limit_setting() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Set custom rate limit.
		$settings = \WPAdminHealth\Plugin::get_instance()->get_settings();
		update_option( 'wpha_settings', array_merge(
			$settings->get_settings(),
			array( 'rest_api_rate_limit' => 100 )
		) );

		$rate_limit = $settings->get_rest_api_rate_limit();
		$this->assertEquals( 100, $rate_limit );

		// Reset to default.
		update_option( 'wpha_settings', array_merge(
			$settings->get_settings(),
			array( 'rest_api_rate_limit' => 60 )
		) );
	}

	/**
	 * Test: HTTP status codes are correct for different scenarios.
	 */
	public function test_http_status_codes() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// 200 OK for successful GET.
		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		// 201 Created for successful POST.
		$request = new WP_REST_Request( 'POST', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		// 401 Unauthorized for missing auth.
		wp_set_current_user( 0 );
		$request  = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		// 403 Forbidden for insufficient permissions.
		wp_set_current_user( $this->subscriber_user_id );
		$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );

		// 404 Not Found for invalid endpoint.
		wp_set_current_user( $this->admin_user_id );
		$request = new WP_REST_Request( 'GET', '/wpha/v1/nonexistent/endpoint' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test: Invalid request parameters are rejected.
	 */
	public function test_invalid_parameters_rejected() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Test with invalid ID parameter (string instead of integer).
		$request = new WP_REST_Request( 'GET', '/wpha/v1/invalid-id' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test: Error response format is correct.
	 */
	public function test_error_response_format() {
		// Test without authentication to get an error.
		$request  = new WP_REST_Request( 'GET', '/wpha/v1/' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test: Multiple concurrent requests are handled correctly.
	 */
	public function test_concurrent_requests() {
		// Log in as admin.
		wp_set_current_user( $this->admin_user_id );

		// Clean up any existing rate limit transients.
		delete_transient( 'wpha_rate_limit_' . $this->admin_user_id );

		// Make multiple requests.
		for ( $i = 0; $i < 5; $i++ ) {
			$request = new WP_REST_Request( 'GET', '/wpha/v1/' );
			$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
			$response = $this->server->dispatch( $request );
			$this->assertEquals( 200, $response->get_status() );
		}
	}

	/**
	 * Test: Routes are properly registered.
	 */
	public function test_routes_registered() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/wpha/v1', $routes );
		$this->assertArrayHasKey( '/wpha/v1/(?P<id>[\d]+)', $routes );
	}

	/**
	 * Test: Endpoint supports correct HTTP methods.
	 */
	public function test_endpoint_methods() {
		$routes = $this->server->get_routes();

		// Collection endpoint should support GET and POST.
		$collection_route = $routes['/wpha/v1'];
		$methods          = array();
		foreach ( $collection_route as $route ) {
			$methods = array_merge( $methods, $route['methods'] );
		}
		$this->assertContains( 'GET', $methods );
		$this->assertContains( 'POST', $methods );

		// Single item endpoint should support GET and DELETE.
		$single_route = $routes['/wpha/v1/(?P<id>[\d]+)'];
		$methods      = array();
		foreach ( $single_route as $route ) {
			$methods = array_merge( $methods, $route['methods'] );
		}
		$this->assertContains( 'GET', $methods );
		$this->assertContains( 'DELETE', $methods );
	}
}
