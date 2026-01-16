<?php
/**
 * WP Admin Health Suite Base Exception
 *
 * Base exception class for all WP Admin Health Suite exceptions.
 *
 * @package WPAdminHealth\Exceptions
 */

namespace WPAdminHealth\Exceptions;

use Exception;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Base exception class for WP Admin Health Suite.
 *
 * Provides consistent exception handling with error codes,
 * additional context, and WP_Error conversion support.
 *
 * @since 1.3.0
 */
class WPAdminHealthException extends Exception {

	/**
	 * Additional error context data.
	 *
	 * @var array
	 */
	protected array $context = array();

	/**
	 * HTTP status code for REST API responses.
	 *
	 * @var int
	 */
	protected int $http_status = 500;

	/**
	 * Create exception from a WP_Error.
	 *
	 * @since 1.3.0
	 *
	 * @param \WP_Error $wp_error The WP_Error object.
	 * @param int       $http_status Optional HTTP status code. Default 500.
	 * @return WPAdminHealthException
	 */
	public static function from_wp_error( \WP_Error $wp_error, int $http_status = 500 ): WPAdminHealthException {
		$message = $wp_error->get_error_message();
		if ( empty( $message ) ) {
			$message = 'An unknown error occurred.';
		}

		$code = $wp_error->get_error_code();
		if ( empty( $code ) ) {
			$code = 'unknown_error';
		}

		$exception = new self( $message, 0, null );
		$exception->code = $code;
		$exception->http_status = $http_status;
		$exception->context = array(
			'wp_error_data' => $wp_error->get_error_data(),
		);

		return $exception;
	}

	/**
	 * Create exception with context.
	 *
	 * @since 1.3.0
	 *
	 * @param string $message Error message.
	 * @param string $code Error code.
	 * @param array  $context Additional context data.
	 * @param int    $http_status HTTP status code.
	 * @return WPAdminHealthException
	 */
	public static function with_context(
		string $message,
		string $code = 'error',
		array $context = array(),
		int $http_status = 500
	): WPAdminHealthException {
		$exception = new self( $message, 0, null );
		$exception->code = $code;
		$exception->context = $context;
		$exception->http_status = $http_status;

		return $exception;
	}

	/**
	 * Get the error context.
	 *
	 * @since 1.3.0
	 *
	 * @return array Error context data.
	 */
	public function get_context(): array {
		return $this->context;
	}

	/**
	 * Set the error context.
	 *
	 * @since 1.3.0
	 *
	 * @param array $context Context data.
	 * @return void
	 */
	public function set_context( array $context ): void {
		$this->context = $context;
	}

	/**
	 * Get the HTTP status code.
	 *
	 * @since 1.3.0
	 *
	 * @return int HTTP status code.
	 */
	public function get_http_status(): int {
		return $this->http_status;
	}

	/**
	 * Set the HTTP status code.
	 *
	 * @since 1.3.0
	 *
	 * @param int $status HTTP status code.
	 * @return void
	 */
	public function set_http_status( int $status ): void {
		$this->http_status = $status;
	}

	/**
	 * Convert to WP_Error.
	 *
	 * @since 1.3.0
	 *
	 * @return \WP_Error WP_Error representation of this exception.
	 */
	public function to_wp_error(): \WP_Error {
		$data = $this->context;
		$data['status'] = $this->http_status;
		$data['exception'] = static::class;

		return new \WP_Error( $this->getCode(), $this->getMessage(), $data );
	}

	/**
	 * Convert to REST response.
	 *
	 * @since 1.3.0
	 *
	 * @return \WP_REST_Response REST response representation of this exception.
	 */
	public function to_rest_response(): \WP_REST_Response {
		$data = array(
			'code'    => $this->getCode(),
			'message' => $this->getMessage(),
			'status'  => $this->http_status,
		);

		if ( ! empty( $this->context ) ) {
			$data['data'] = $this->context;
		}

		return new \WP_REST_Response( $data, $this->http_status );
	}

	/**
	 * Get safe error message for logging/display.
	 *
	 * Removes potentially sensitive information from context.
	 *
	 * @since 1.3.0
	 *
	 * @return string Safe error message.
	 */
	public function get_safe_message(): string {
		$message = $this->getMessage();

		// Remove file paths that might expose system structure.
		$message = preg_replace( '/in [\/].*? on line \d+/', '', $message );
		$message = preg_replace( '/\/[^\/\s]+\/[^\/\s]+\.php/', '[file]', $message );

		return $message;
	}
}
