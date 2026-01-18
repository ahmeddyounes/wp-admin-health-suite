<?php
/**
 * Exceptions Tests (Standalone)
 *
 * Tests for WPAdminHealth exception classes.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Exceptions
 */

namespace WPAdminHealth\Tests\UnitStandalone\Exceptions;

use WPAdminHealth\Exceptions\WPAdminHealthException;
use WPAdminHealth\Exceptions\ValidationException;
use WPAdminHealth\Exceptions\DatabaseException;
use WPAdminHealth\Exceptions\MediaException;
use WPAdminHealth\Exceptions\NotFoundException;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * Exception classes tests.
 */
class ExceptionsTest extends StandaloneTestCase {

	/**
	 * Test WPAdminHealthException can be created with context.
	 */
	public function test_base_exception_with_context(): void {
		$exception = WPAdminHealthException::with_context(
			'Test error message',
			'test_code',
			array( 'key' => 'value' ),
			503
		);

		$this->assertEquals( 'Test error message', $exception->getMessage() );
		$this->assertEquals( 'test_code', $exception->getCode() );
		$this->assertEquals( array( 'key' => 'value' ), $exception->get_context() );
		$this->assertEquals( 503, $exception->get_http_status() );
	}

	/**
	 * Test WPAdminHealthException to_wp_error conversion.
	 */
	public function test_base_exception_to_wp_error(): void {
		$exception = WPAdminHealthException::with_context(
			'Error message',
			'error_code',
			array( 'context_key' => 'context_value' ),
			400
		);

		$wp_error = $exception->to_wp_error();

		$this->assertInstanceOf( \WP_Error::class, $wp_error );
		$this->assertEquals( 'error_code', $wp_error->get_error_code() );
		$this->assertEquals( 'Error message', $wp_error->get_error_message() );
		$this->assertEquals( 400, $wp_error->get_error_data()['status'] );
	}

	/**
	 * Test WPAdminHealthException to_rest_response conversion.
	 */
	public function test_base_exception_to_rest_response(): void {
		$exception = WPAdminHealthException::with_context(
			'REST error',
			'rest_error_code',
			array( 'detail' => 'test' ),
			422
		);

		$response = $exception->to_rest_response();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 422, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_error_code', $data['code'] );
		$this->assertEquals( 'REST error', $data['message'] );
		$this->assertEquals( array( 'detail' => 'test' ), $data['data'] );
	}

	/**
	 * Test ValidationException invalid_param factory.
	 */
	public function test_validation_exception_invalid_param(): void {
		$exception = ValidationException::invalid_param( 'test_param', 'bad_value', 'Must be an integer' );

		$this->assertStringContainsString( 'Invalid parameter: test_param', $exception->getMessage() );
		$this->assertStringContainsString( 'Must be an integer', $exception->getMessage() );
		$this->assertEquals( ValidationException::ERROR_INVALID_PARAM, $exception->getCode() );
		$this->assertEquals( 400, $exception->get_http_status() );

		$context = $exception->get_context();
		$this->assertEquals( 'test_param', $context['param'] );
		$this->assertEquals( 'Must be an integer', $context['reason'] );
	}

	/**
	 * Test ValidationException missing_param factory.
	 */
	public function test_validation_exception_missing_param(): void {
		$exception = ValidationException::missing_param( 'required_field' );

		$this->assertStringContainsString( 'Missing required parameter: required_field', $exception->getMessage() );
		$this->assertEquals( ValidationException::ERROR_MISSING_PARAM, $exception->getCode() );
		$this->assertEquals( 400, $exception->get_http_status() );
	}

	/**
	 * Test ValidationException invalid_format factory.
	 */
	public function test_validation_exception_invalid_format(): void {
		$exception = ValidationException::invalid_format( 'email', 'example@email.com', 'invalid' );

		$this->assertStringContainsString( 'Invalid format for email', $exception->getMessage() );
		$this->assertEquals( ValidationException::ERROR_INVALID_FORMAT, $exception->getCode() );
		$this->assertEquals( 400, $exception->get_http_status() );
	}

	/**
	 * Test ValidationException invalid_range factory.
	 */
	public function test_validation_exception_invalid_range(): void {
		$exception = ValidationException::invalid_range( 'limit', 150, 1, 100 );

		$this->assertStringContainsString( 'out of range', $exception->getMessage() );
		$this->assertEquals( ValidationException::ERROR_INVALID_RANGE, $exception->getCode() );
		$this->assertEquals( 400, $exception->get_http_status() );

		$context = $exception->get_context();
		$this->assertEquals( 150, $context['value'] );
		$this->assertEquals( 1, $context['min'] );
		$this->assertEquals( 100, $context['max'] );
	}

	/**
	 * Test ValidationException invalid_type factory.
	 */
	public function test_validation_exception_invalid_type(): void {
		$exception = ValidationException::invalid_type( 'count', 'integer', 'string' );

		$this->assertStringContainsString( 'Invalid type for count', $exception->getMessage() );
		$this->assertStringContainsString( 'Expected integer, got string', $exception->getMessage() );
		$this->assertEquals( ValidationException::ERROR_INVALID_TYPE, $exception->getCode() );
	}

	/**
	 * Test DatabaseException query_failed factory.
	 */
	public function test_database_exception_query_failed(): void {
		$exception = DatabaseException::query_failed(
			'SELECT * FROM wp_posts WHERE 1=1',
			'MySQL server has gone away'
		);

		$this->assertEquals( 'Database query failed.', $exception->getMessage() );
		$this->assertEquals( DatabaseException::ERROR_QUERY_FAILED, $exception->getCode() );
		$this->assertEquals( 500, $exception->get_http_status() );

		$context = $exception->get_context();
		$this->assertArrayHasKey( 'query', $context );
		$this->assertEquals( 'MySQL server has gone away', $context['db_error'] );
	}

	/**
	 * Test DatabaseException table_not_found factory.
	 */
	public function test_database_exception_table_not_found(): void {
		$exception = DatabaseException::table_not_found( 'wp_missing_table' );

		$this->assertStringContainsString( 'Database table not found: wp_missing_table', $exception->getMessage() );
		$this->assertEquals( DatabaseException::ERROR_TABLE_NOT_FOUND, $exception->getCode() );
		$this->assertEquals( 404, $exception->get_http_status() );
	}

	/**
	 * Test DatabaseException connection_lost factory.
	 */
	public function test_database_exception_connection_lost(): void {
		$exception = DatabaseException::connection_lost( 'Connection timeout' );

		$this->assertStringContainsString( 'Database connection lost', $exception->getMessage() );
		$this->assertEquals( DatabaseException::ERROR_CONNECTION_LOST, $exception->getCode() );
		$this->assertEquals( 503, $exception->get_http_status() );
	}

	/**
	 * Test DatabaseException timeout factory.
	 */
	public function test_database_exception_timeout(): void {
		$exception = DatabaseException::timeout( 30 );

		$this->assertStringContainsString( 'timeout after 30 seconds', $exception->getMessage() );
		$this->assertEquals( DatabaseException::ERROR_TIMEOUT, $exception->getCode() );
		$this->assertEquals( 504, $exception->get_http_status() );
	}

	/**
	 * Test MediaException file_not_found factory.
	 */
	public function test_media_exception_file_not_found(): void {
		$exception = MediaException::file_not_found( 123, '/uploads/image.jpg' );

		$this->assertStringContainsString( 'Media file not found for attachment ID: 123', $exception->getMessage() );
		$this->assertEquals( MediaException::ERROR_FILE_NOT_FOUND, $exception->getCode() );
		$this->assertEquals( 404, $exception->get_http_status() );

		$context = $exception->get_context();
		$this->assertEquals( 123, $context['attachment_id'] );
		$this->assertEquals( 'image.jpg', $context['file'] );
	}

	/**
	 * Test MediaException attachment_not_found factory.
	 */
	public function test_media_exception_attachment_not_found(): void {
		$exception = MediaException::attachment_not_found( 456 );

		$this->assertStringContainsString( 'Attachment not found: 456', $exception->getMessage() );
		$this->assertEquals( MediaException::ERROR_ATTACHMENT_NOT_FOUND, $exception->getCode() );
		$this->assertEquals( 404, $exception->get_http_status() );
	}

	/**
	 * Test MediaException size_exceeded factory.
	 */
	public function test_media_exception_size_exceeded(): void {
		$exception = MediaException::size_exceeded( 15000000, 10000000, 'large-file.zip' );

		$this->assertStringContainsString( 'exceeds maximum allowed', $exception->getMessage() );
		$this->assertEquals( MediaException::ERROR_SIZE_EXCEEDED, $exception->getCode() );
		$this->assertEquals( 413, $exception->get_http_status() );
	}

	/**
	 * Test NotFoundException resource factory.
	 */
	public function test_not_found_exception_resource(): void {
		$exception = NotFoundException::resource( 'user', 999 );

		$this->assertStringContainsString( 'User not found: 999', $exception->getMessage() );
		$this->assertEquals( NotFoundException::ERROR_RESOURCE_NOT_FOUND, $exception->getCode() );
		$this->assertEquals( 404, $exception->get_http_status() );
	}

	/**
	 * Test NotFoundException post factory.
	 */
	public function test_not_found_exception_post(): void {
		$exception = NotFoundException::post( 123, 'page' );

		$this->assertStringContainsString( 'Post not found: 123', $exception->getMessage() );
		$this->assertEquals( NotFoundException::ERROR_POST_NOT_FOUND, $exception->getCode() );
		$this->assertEquals( 404, $exception->get_http_status() );

		$context = $exception->get_context();
		$this->assertEquals( 123, $context['post_id'] );
		$this->assertEquals( 'page', $context['post_type'] );
	}

	/**
	 * Test NotFoundException user factory.
	 */
	public function test_not_found_exception_user(): void {
		$exception = NotFoundException::user( 42 );

		$this->assertStringContainsString( 'User not found: 42', $exception->getMessage() );
		$this->assertEquals( NotFoundException::ERROR_USER_NOT_FOUND, $exception->getCode() );
	}

	/**
	 * Test NotFoundException setting factory.
	 */
	public function test_not_found_exception_setting(): void {
		$exception = NotFoundException::setting( 'unknown_option' );

		$this->assertStringContainsString( 'Setting not found: unknown_option', $exception->getMessage() );
		$this->assertEquals( NotFoundException::ERROR_SETTING_NOT_FOUND, $exception->getCode() );
	}

	/**
	 * Test NotFoundException route factory.
	 */
	public function test_not_found_exception_route(): void {
		$exception = NotFoundException::route( '/wpha/v1/unknown' );

		$this->assertStringContainsString( 'Route not found: /wpha/v1/unknown', $exception->getMessage() );
		$this->assertEquals( NotFoundException::ERROR_ROUTE_NOT_FOUND, $exception->getCode() );
	}

	/**
	 * Test exception context can be set.
	 */
	public function test_exception_context_can_be_set(): void {
		$exception = new WPAdminHealthException( 'Test' );
		$exception->set_context( array( 'custom' => 'data' ) );

		$this->assertEquals( array( 'custom' => 'data' ), $exception->get_context() );
	}

	/**
	 * Test exception HTTP status can be set.
	 */
	public function test_exception_http_status_can_be_set(): void {
		$exception = new WPAdminHealthException( 'Test' );
		$exception->set_http_status( 418 );

		$this->assertEquals( 418, $exception->get_http_status() );
	}

	/**
	 * Test get_safe_message removes file paths.
	 */
	public function test_get_safe_message_removes_sensitive_info(): void {
		$exception = WPAdminHealthException::with_context(
			'Error in /var/www/html/wp-content/plugins/test.php on line 42',
			'test_error'
		);

		$safe_message = $exception->get_safe_message();

		$this->assertStringNotContainsString( '/var/www/html', $safe_message );
		$this->assertStringNotContainsString( 'on line 42', $safe_message );
	}

	/**
	 * Test default HTTP status codes per exception type.
	 */
	public function test_default_http_status_codes(): void {
		$base_exception = new WPAdminHealthException( 'Base' );
		$this->assertEquals( 500, $base_exception->get_http_status() );

		$not_found = NotFoundException::resource( 'item', 1 );
		$this->assertEquals( 404, $not_found->get_http_status() );

		$validation = ValidationException::missing_param( 'field' );
		$this->assertEquals( 400, $validation->get_http_status() );

		$db_exception = DatabaseException::connection_lost();
		$this->assertEquals( 503, $db_exception->get_http_status() );
	}

	/**
	 * Test exception inheritance.
	 */
	public function test_exception_inheritance(): void {
		$validation = ValidationException::missing_param( 'test' );
		$database   = DatabaseException::query_failed( 'SELECT 1' );
		$media      = MediaException::attachment_not_found( 1 );
		$not_found  = NotFoundException::resource( 'item', 1 );

		$this->assertInstanceOf( WPAdminHealthException::class, $validation );
		$this->assertInstanceOf( WPAdminHealthException::class, $database );
		$this->assertInstanceOf( WPAdminHealthException::class, $media );
		$this->assertInstanceOf( WPAdminHealthException::class, $not_found );
		$this->assertInstanceOf( \Exception::class, $validation );
	}
}
