<?php
/**
 * Media Exception
 *
 * Exception class for media-related errors.
 *
 * @package WPAdminHealth\Exceptions
 */

namespace WPAdminHealth\Exceptions;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Media exception for media-related errors.
 *
 * Use for file not found, invalid file type, upload errors, etc.
 *
 * @since 1.3.0
 */
class MediaException extends WPAdminHealthException {

	/**
	 * Error codes for media exceptions.
	 */
	public const ERROR_FILE_NOT_FOUND    = 'media_file_not_found';
	public const ERROR_INVALID_TYPE      = 'media_invalid_type';
	public const ERROR_UPLOAD_FAILED     = 'media_upload_failed';
	public const ERROR_SIZE_EXCEEDED     = 'media_size_exceeded';
	public const ERROR_DELETE_FAILED     = 'media_delete_failed';
	public const ERROR_SCAN_FAILED       = 'media_scan_failed';
	public const ERROR_ATTACHMENT_NOT_FOUND = 'media_attachment_not_found';

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
	 * Create a file not found exception.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $file_path Optional file path.
	 * @return static
	 */
	public static function file_not_found( int $attachment_id, string $file_path = '' ) {
		$context = array( 'attachment_id' => $attachment_id );
		if ( ! empty( $file_path ) ) {
			$context['file'] = basename( $file_path );
		}

		return static::with_context(
			sprintf( 'Media file not found for attachment ID: %d', $attachment_id ),
			self::ERROR_FILE_NOT_FOUND,
			$context,
			404
		);
	}

	/**
	 * Create an attachment not found exception.
	 *
	 * @since 1.3.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return static
	 */
	public static function attachment_not_found( int $attachment_id ) {
		return static::with_context(
			sprintf( 'Attachment not found: %d', $attachment_id ),
			self::ERROR_ATTACHMENT_NOT_FOUND,
			array( 'attachment_id' => $attachment_id ),
			404
		);
	}

	/**
	 * Create an invalid file type exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string      $file_type The invalid file type.
	 * @param string|null $mime_type Optional MIME type.
	 * @return static
	 */
	public static function invalid_type( string $file_type, ?string $mime_type = null ) {
		$context = array( 'type' => $file_type );
		if ( null !== $mime_type ) {
			$context['mime_type'] = $mime_type;
		}

		return static::with_context(
			sprintf( 'Invalid or unsupported media type: %s', sanitize_text_field( $file_type ) ),
			self::ERROR_INVALID_TYPE,
			$context,
			400
		);
	}

	/**
	 * Create a size exceeded exception.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $file_size Actual file size in bytes.
	 * @param int    $max_size Maximum allowed size in bytes.
	 * @param string $file_name Optional file name.
	 * @return static
	 */
	public static function size_exceeded( int $file_size, int $max_size, string $file_name = '' ) {
		$context = array(
			'size'     => $file_size,
			'max_size' => $max_size,
		);
		if ( ! empty( $file_name ) ) {
			$context['file'] = basename( $file_name );
		}

		return static::with_context(
			sprintf(
				'Media file size (%s) exceeds maximum allowed (%s).',
				size_format( $file_size ),
				size_format( $max_size )
			),
			self::ERROR_SIZE_EXCEEDED,
			$context,
			413
		);
	}

	/**
	 * Create a delete failed exception.
	 *
	 * @since 1.3.0
	 *
	 * @param int          $attachment_id The attachment ID.
	 * @param string|null  $reason Optional failure reason.
	 * @return static
	 */
	public static function delete_failed( int $attachment_id, ?string $reason = null ) {
		$context = array( 'attachment_id' => $attachment_id );
		if ( null !== $reason ) {
			$context['reason'] = $reason;
		}

		return static::with_context(
			sprintf( 'Failed to delete attachment: %d', $attachment_id ),
			self::ERROR_DELETE_FAILED,
			$context,
			500
		);
	}

	/**
	 * Create a scan failed exception.
	 *
	 * @since 1.3.0
	 *
	 * @param string      $reason Scan failure reason.
	 * @param array       $context Optional additional context.
	 * @return static
	 */
	public static function scan_failed( string $reason, array $context = array() ) {
		return static::with_context(
			sprintf( 'Media scan failed: %s', $reason ),
			self::ERROR_SCAN_FAILED,
			$context,
			500
		);
	}
}
