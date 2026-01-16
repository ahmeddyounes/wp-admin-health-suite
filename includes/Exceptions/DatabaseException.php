<?php
/**
 * Database Exception
 *
 * Exception class for database-related errors.
 *
 * @package WPAdminHealth\Exceptions
 */

namespace WPAdminHealth\Exceptions;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Database exception for database-related errors.
 *
 * Use for query failures, connection issues, table not found, etc.
 *
 * @since 1.3.0
 */
class DatabaseException extends WPAdminHealthException {

	/**
	 * Error codes for database exceptions.
	 */
	public const ERROR_QUERY_FAILED     = 'db_query_failed';
	public const ERROR_CONNECTION_LOST  = 'db_connection_lost';
	public const ERROR_TABLE_NOT_FOUND  = 'db_table_not_found';
	public const ERROR_COLUMN_NOT_FOUND = 'db_column_not_found';
	public const ERROR_CONSTRAINT_FAILED = 'db_constraint_failed';
	public const ERROR_TIMEOUT          = 'db_timeout';

	/**
	 * Create exception with context.
	 *
	 * Override parent method to return correct type.
	 *
	 * @since 1.3.0
	 *
	 * @param string $message Error message.
	 * @param string $code Error code.
	 * @param array  $context Additional context data.
	 * @param int    $http_status HTTP status code.
	 * @return static
	 */
	public static function with_context(
		string $message,
		string $code = 'error',
		array $context = array(),
		int $http_status = 500
	): self {
		$exception = new static( $message, 0, null );
		$exception->code = $code;
		$exception->context = $context;
		$exception->http_status = $http_status;

		return $exception;
	}

	/**
	 * Create a query failed exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string      $query The failed query (sanitized in output).
	 * @param string|null  $error Optional database error message.
	 * @param array       $context Optional additional context.
	 * @return static
	 */
	public static function query_failed( string $query, ?string $error = null, array $context = array() ) {
		$context['query'] = substr( $query, 0, 100 ) . ( strlen( $query ) > 100 ? '...' : '' );
		if ( null !== $error ) {
			$context['db_error'] = $error;
		}

		return static::with_context(
			'Database query failed.',
			self::ERROR_QUERY_FAILED,
			$context,
			500
		);
	}

	/**
	 * Create a table not found exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string $table_name The missing table name.
	 * @return static
	 */
	public static function table_not_found( string $table_name ) {
		return static::with_context(
			sprintf( 'Database table not found: %s', sanitize_text_field( $table_name ) ),
			self::ERROR_TABLE_NOT_FOUND,
			array( 'table' => $table_name ),
			404
		);
	}

	/**
	 * Create a connection lost exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string $details Optional connection details.
	 * @return static
	 */
	public static function connection_lost( string $details = '' ) {
		$context = array();
		if ( ! empty( $details ) ) {
			$context['details'] = $details;
		}

		return static::with_context(
			'Database connection lost or unable to connect.',
			self::ERROR_CONNECTION_LOST,
			$context,
			503
		);
	}

	/**
	 * Create a timeout exception.
	 *
	 * @since 1.3.0
	 *
	 * @param int $seconds Timeout duration in seconds.
	 * @return static
	 */
	public static function timeout( int $seconds ) {
		return static::with_context(
			sprintf( 'Database query timeout after %d seconds.', $seconds ),
			self::ERROR_TIMEOUT,
			array( 'timeout_seconds' => $seconds ),
			504
		);
	}

	/**
	 * Create a constraint failed exception.
	 *
	 * For foreign key constraints, unique constraints, etc.
	 *
	 * @since 1.3.0
	 *
	 * @param string $constraint_name The constraint that failed.
	 * @return static
	 */
	public static function constraint_failed( string $constraint_name ) {
		return static::with_context(
			sprintf( 'Database constraint failed: %s', sanitize_text_field( $constraint_name ) ),
			self::ERROR_CONSTRAINT_FAILED,
			array( 'constraint' => $constraint_name ),
			409
		);
	}
}
