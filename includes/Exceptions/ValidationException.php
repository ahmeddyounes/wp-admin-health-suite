<?php
/**
 * Validation Exception
 *
 * Exception class for validation-related errors.
 *
 * @package WPAdminHealth\Exceptions
 */

namespace WPAdminHealth\Exceptions;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Validation exception for input validation errors.
 *
 * Use for invalid parameters, malformed data, missing required fields, etc.
 *
 * @since 1.3.0
 */
class ValidationException extends WPAdminHealthException {

	/**
	 * Error codes for validation exceptions.
	 */
	public const ERROR_INVALID_PARAM    = 'validation_invalid_param';
	public const ERROR_MISSING_PARAM    = 'validation_missing_param';
	public const ERROR_INVALID_FORMAT   = 'validation_invalid_format';
	public const ERROR_INVALID_RANGE    = 'validation_invalid_range';
	public const ERROR_INVALID_TYPE     = 'validation_invalid_type';

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
	 * Create an invalid parameter exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string      $param_name The invalid parameter name.
	 * @param string|null $value Optional the invalid value (sanitized).
	 * @param string|null $reason Optional reason why it's invalid.
	 * @return static
	 */
	public static function invalid_param( string $param_name, $value = null, ?string $reason = null ) {
		$context = array( 'param' => $param_name );

		$message = sprintf( 'Invalid parameter: %s', sanitize_text_field( $param_name ) );

		if ( null !== $value ) {
			$display_value = is_scalar( $value ) ? $value : json_encode( $value );
			$context['value'] = substr( sanitize_text_field( (string) $display_value ), 0, 100 );
		}

		if ( null !== $reason ) {
			$context['reason'] = $reason;
			$message .= sprintf( ' - %s', $reason );
		}

		return static::with_context( $message, self::ERROR_INVALID_PARAM, $context, 400 );
	}

	/**
	 * Create a missing parameter exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string $param_name The missing parameter name.
	 * @return static
	 */
	public static function missing_param( string $param_name ) {
		return static::with_context(
			sprintf( 'Missing required parameter: %s', sanitize_text_field( $param_name ) ),
			self::ERROR_MISSING_PARAM,
			array( 'param' => $param_name ),
			400
		);
	}

	/**
	 * Create an invalid format exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string      $param_name The parameter with invalid format.
	 * @param string      $expected_format Expected format description.
	 * @param string|null $actual_value Optional actual value.
	 * @return static
	 */
	public static function invalid_format( string $param_name, string $expected_format, ?string $actual_value = null ) {
		$context = array(
			'param'  => $param_name,
			'format' => $expected_format,
		);

		$message = sprintf(
			'Invalid format for %s. Expected: %s',
			sanitize_text_field( $param_name ),
			sanitize_text_field( $expected_format )
		);

		if ( null !== $actual_value ) {
			$context['value'] = substr( sanitize_text_field( $actual_value ), 0, 100 );
		}

		return static::with_context( $message, self::ERROR_INVALID_FORMAT, $context, 400 );
	}

	/**
	 * Create an invalid range exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string     $param_name The parameter out of range.
	 * @param int|float  $value The actual value.
	 * @param int|float  $min Minimum allowed value.
	 * @param int|float  $max Maximum allowed value.
	 * @return static
	 */
	public static function invalid_range( string $param_name, $value, $min, $max ) {
		return static::with_context(
			sprintf(
				'Parameter %s value (%s) is out of range. Must be between %s and %s.',
				sanitize_text_field( $param_name ),
				is_numeric( $value ) ? $value : 'invalid',
				$min,
				$max
			),
			self::ERROR_INVALID_RANGE,
			array(
				'param' => $param_name,
				'value' => $value,
				'min'   => $min,
				'max'   => $max,
			),
			400
		);
	}

	/**
	 * Create an invalid type exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string    $param_name The parameter with invalid type.
	 * @param string    $expected_type Expected type.
	 * @param string    $actual_type Actual type received.
	 * @return static
	 */
	public static function invalid_type( string $param_name, string $expected_type, string $actual_type ) {
		return static::with_context(
			sprintf(
				'Invalid type for %s. Expected %s, got %s.',
				sanitize_text_field( $param_name ),
				sanitize_text_field( $expected_type ),
				sanitize_text_field( $actual_type )
			),
			self::ERROR_INVALID_TYPE,
			array(
				'param'    => $param_name,
				'expected' => $expected_type,
				'actual'   => $actual_type,
			),
			400
		);
	}
}
