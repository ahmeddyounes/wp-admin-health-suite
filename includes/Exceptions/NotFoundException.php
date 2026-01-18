<?php
/**
 * Not Found Exception
 *
 * Exception class for resource not found errors.
 *
 * @package WPAdminHealth\Exceptions
 */

namespace WPAdminHealth\Exceptions;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Not found exception for missing resources.
 *
 * Use for resources that cannot be located, such as missing posts,
 * users, settings, or other entities.
 *
 * @since 1.4.0
 */
class NotFoundException extends WPAdminHealthException {

	/**
	 * Default HTTP status code for not found errors.
	 *
	 * @var int
	 */
	protected int $http_status = 404;

	/**
	 * Error codes for not found exceptions.
	 */
	public const ERROR_RESOURCE_NOT_FOUND = 'resource_not_found';
	public const ERROR_POST_NOT_FOUND     = 'post_not_found';
	public const ERROR_USER_NOT_FOUND     = 'user_not_found';
	public const ERROR_SETTING_NOT_FOUND  = 'setting_not_found';
	public const ERROR_ROUTE_NOT_FOUND    = 'route_not_found';

	/**
	 * Create exception with context.
	 *
	 * Override parent method to return correct type.
	 *
	 * @since 1.4.0
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
		int $http_status = 404
	): self {
		$exception = new static( $message, 0, null );
		$exception->code = $code;
		$exception->context = $context;
		$exception->http_status = $http_status;

		return $exception;
	}

	/**
	 * Create a generic resource not found exception.
	 *
	 * @since 1.4.0
	 *
	 * @param string     $resource_type The type of resource (e.g., 'post', 'user').
	 * @param int|string $identifier    The resource identifier.
	 * @return static
	 */
	public static function resource( string $resource_type, $identifier ) {
		return static::with_context(
			sprintf(
				'%s not found: %s',
				ucfirst( sanitize_text_field( $resource_type ) ),
				sanitize_text_field( (string) $identifier )
			),
			self::ERROR_RESOURCE_NOT_FOUND,
			array(
				'resource_type' => $resource_type,
				'identifier'    => $identifier,
			),
			404
		);
	}

	/**
	 * Create a post not found exception.
	 *
	 * @since 1.4.0
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $post_type Optional post type for context.
	 * @return static
	 */
	public static function post( int $post_id, string $post_type = 'post' ) {
		return static::with_context(
			sprintf( 'Post not found: %d', $post_id ),
			self::ERROR_POST_NOT_FOUND,
			array(
				'post_id'   => $post_id,
				'post_type' => $post_type,
			),
			404
		);
	}

	/**
	 * Create a user not found exception.
	 *
	 * @since 1.4.0
	 *
	 * @param int $user_id The user ID.
	 * @return static
	 */
	public static function user( int $user_id ) {
		return static::with_context(
			sprintf( 'User not found: %d', $user_id ),
			self::ERROR_USER_NOT_FOUND,
			array( 'user_id' => $user_id ),
			404
		);
	}

	/**
	 * Create a setting not found exception.
	 *
	 * @since 1.4.0
	 *
	 * @param string $setting_key The setting key.
	 * @return static
	 */
	public static function setting( string $setting_key ) {
		return static::with_context(
			sprintf( 'Setting not found: %s', sanitize_key( $setting_key ) ),
			self::ERROR_SETTING_NOT_FOUND,
			array( 'setting' => $setting_key ),
			404
		);
	}

	/**
	 * Create a route not found exception.
	 *
	 * @since 1.4.0
	 *
	 * @param string $route The route path.
	 * @return static
	 */
	public static function route( string $route ) {
		return static::with_context(
			sprintf( 'Route not found: %s', sanitize_text_field( $route ) ),
			self::ERROR_ROUTE_NOT_FOUND,
			array( 'route' => $route ),
			404
		);
	}
}
