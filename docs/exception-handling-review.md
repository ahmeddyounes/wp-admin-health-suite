# Exception Handling Review

This document provides a comprehensive review of the custom exception definitions in the WP Admin Health Suite plugin, covering the exception hierarchy, error codes, meaningful error messages, and integration patterns.

## Overview

The plugin implements a well-structured exception hierarchy with a base exception class and domain-specific exceptions for different subsystems.

| Exception Class        | Namespace                | Purpose                                  | Since |
| ---------------------- | ------------------------ | ---------------------------------------- | ----- |
| WPAdminHealthException | WPAdminHealth\Exceptions | Base exception for all plugin exceptions | 1.3.0 |
| DatabaseException      | WPAdminHealth\Exceptions | Database-related errors                  | 1.3.0 |
| MediaException         | WPAdminHealth\Exceptions | Media/attachment errors                  | 1.3.0 |
| ValidationException    | WPAdminHealth\Exceptions | Input validation errors                  | 1.3.0 |
| ContainerException     | WPAdminHealth\Container  | DI container errors (PSR-11)             | 1.1.0 |
| NotFoundException      | WPAdminHealth\Container  | Missing container entries                | 1.1.0 |

## Exception Hierarchy

```
Exception (PHP built-in)
├── WPAdminHealthException
│   ├── DatabaseException
│   ├── MediaException
│   └── ValidationException
├── ContainerException (PSR-11 compatible)
└── NotFoundException (PSR-11 compatible)
```

## Base Exception: WPAdminHealthException

**Location:** `includes/Exceptions/WPAdminHealthException.php`

### Features

| Feature             | Description                                  |
| ------------------- | -------------------------------------------- |
| Context Support     | Additional error context data via `$context` |
| HTTP Status Codes   | Built-in HTTP status for REST API responses  |
| WP_Error Conversion | Bidirectional conversion to/from `WP_Error`  |
| REST Response       | Direct conversion to `WP_REST_Response`      |
| Safe Messages       | Sanitized messages that hide sensitive paths |

### Factory Methods

| Method            | Purpose                                          |
| ----------------- | ------------------------------------------------ |
| `from_wp_error()` | Create exception from existing `WP_Error` object |
| `with_context()`  | Create exception with additional context data    |

### Instance Methods

| Method               | Return Type         | Purpose                           |
| -------------------- | ------------------- | --------------------------------- |
| `get_context()`      | `array`             | Get additional error context      |
| `set_context()`      | `void`              | Set additional error context      |
| `get_http_status()`  | `int`               | Get HTTP status code              |
| `set_http_status()`  | `void`              | Set HTTP status code              |
| `to_wp_error()`      | `\WP_Error`         | Convert to WordPress error object |
| `to_rest_response()` | `\WP_REST_Response` | Convert to REST API response      |
| `get_safe_message()` | `string`            | Get sanitized message for logging |

### Security Considerations

The `get_safe_message()` method removes potentially sensitive information:

- File paths that expose system structure
- Line number references in error messages

## DatabaseException

**Location:** `includes/Exceptions/DatabaseException.php`

### Error Codes

| Constant                  | Code                   | HTTP Status | Description                    |
| ------------------------- | ---------------------- | ----------- | ------------------------------ |
| `ERROR_QUERY_FAILED`      | `db_query_failed`      | 500         | Generic query failure          |
| `ERROR_CONNECTION_LOST`   | `db_connection_lost`   | 503         | Database connection issues     |
| `ERROR_TABLE_NOT_FOUND`   | `db_table_not_found`   | 404         | Missing database table         |
| `ERROR_COLUMN_NOT_FOUND`  | `db_column_not_found`  | -           | Missing column (not used)      |
| `ERROR_CONSTRAINT_FAILED` | `db_constraint_failed` | 409         | FK/unique constraint violation |
| `ERROR_TIMEOUT`           | `db_timeout`           | 504         | Query timeout exceeded         |

### Factory Methods

| Method                | Parameters                                 | Context Captured                      |
| --------------------- | ------------------------------------------ | ------------------------------------- |
| `query_failed()`      | `$query`, `$error = null`, `$context = []` | Truncated query (100 chars), DB error |
| `table_not_found()`   | `$table_name`                              | Table name                            |
| `connection_lost()`   | `$details = ''`                            | Connection details                    |
| `timeout()`           | `$seconds`                                 | Timeout duration                      |
| `constraint_failed()` | `$constraint_name`                         | Constraint name                       |

### Query Truncation

The `query_failed()` method truncates queries to 100 characters to prevent exposing full SQL in logs:

```php
$context['query'] = substr( $query, 0, 100 ) . ( strlen( $query ) > 100 ? '...' : '' );
```

## MediaException

**Location:** `includes/Exceptions/MediaException.php`

### Error Codes

| Constant                     | Code                         | HTTP Status | Description               |
| ---------------------------- | ---------------------------- | ----------- | ------------------------- |
| `ERROR_FILE_NOT_FOUND`       | `media_file_not_found`       | 404         | Physical file missing     |
| `ERROR_INVALID_TYPE`         | `media_invalid_type`         | 400         | Unsupported file type     |
| `ERROR_UPLOAD_FAILED`        | `media_upload_failed`        | -           | Upload failure (not used) |
| `ERROR_SIZE_EXCEEDED`        | `media_size_exceeded`        | 413         | File too large            |
| `ERROR_DELETE_FAILED`        | `media_delete_failed`        | 500         | Deletion failure          |
| `ERROR_SCAN_FAILED`          | `media_scan_failed`          | 500         | Media scan failure        |
| `ERROR_ATTACHMENT_NOT_FOUND` | `media_attachment_not_found` | 404         | Attachment record missing |

### Factory Methods

| Method                   | Parameters                                   | Context Captured              |
| ------------------------ | -------------------------------------------- | ----------------------------- |
| `file_not_found()`       | `$attachment_id`, `$file_path = ''`          | Attachment ID, basename only  |
| `attachment_not_found()` | `$attachment_id`                             | Attachment ID                 |
| `invalid_type()`         | `$file_type`, `$mime_type = null`            | File type, optional MIME      |
| `size_exceeded()`        | `$file_size`, `$max_size`, `$file_name = ''` | Sizes in bytes, basename only |
| `delete_failed()`        | `$attachment_id`, `$reason = null`           | Attachment ID, failure reason |
| `scan_failed()`          | `$reason`, `$context = []`                   | Scan failure details          |

### Path Security

File paths are sanitized to only expose the basename:

```php
$context['file'] = basename( $file_path );
```

## ValidationException

**Location:** `includes/Exceptions/ValidationException.php`

### Error Codes

| Constant               | Code                        | HTTP Status | Description                 |
| ---------------------- | --------------------------- | ----------- | --------------------------- |
| `ERROR_INVALID_PARAM`  | `validation_invalid_param`  | 400         | Invalid parameter value     |
| `ERROR_MISSING_PARAM`  | `validation_missing_param`  | 400         | Required parameter missing  |
| `ERROR_INVALID_FORMAT` | `validation_invalid_format` | 400         | Wrong format (e.g., date)   |
| `ERROR_INVALID_RANGE`  | `validation_invalid_range`  | 400         | Value outside allowed range |
| `ERROR_INVALID_TYPE`   | `validation_invalid_type`   | 400         | Wrong data type             |

### Factory Methods

| Method             | Parameters                                         | Context Captured                    |
| ------------------ | -------------------------------------------------- | ----------------------------------- |
| `invalid_param()`  | `$param_name`, `$value = null`, `$reason = null`   | Param name, truncated value, reason |
| `missing_param()`  | `$param_name`                                      | Parameter name                      |
| `invalid_format()` | `$param_name`, `$expected_format`, `$actual_value` | Param, expected format, value       |
| `invalid_range()`  | `$param_name`, `$value`, `$min`, `$max`            | Param, value, min/max bounds        |
| `invalid_type()`   | `$param_name`, `$expected_type`, `$actual_type`    | Param, expected/actual types        |

### Value Truncation

Values are truncated to 100 characters to prevent log pollution:

```php
$context['value'] = substr( sanitize_text_field( (string) $display_value ), 0, 100 );
```

## Container Exceptions (PSR-11 Compatible)

### ContainerException

**Location:** `includes/Container/ContainerException.php`

Thrown for general container errors, compatible with PSR-11 `ContainerExceptionInterface`.

#### Factory Methods

| Method                           | Purpose                                  |
| -------------------------------- | ---------------------------------------- |
| `provider_registration_failed()` | Service provider registration failure    |
| `provider_boot_failed()`         | Service provider boot failure            |
| `resolver_failed()`              | Service resolution failure               |
| `auto_wire_failed()`             | Auto-wiring/dependency injection failure |

### NotFoundException

**Location:** `includes/Container/NotFoundException.php`

Thrown when a requested service is not found, compatible with PSR-11 `NotFoundExceptionInterface`.

## Current Usage Analysis

### Exception Usage in Codebase

The custom exceptions in `includes/Exceptions/` are currently **not actively used** in the codebase. The codebase primarily uses:

| Exception Type       | Usage Locations                           |
| -------------------- | ----------------------------------------- |
| `\RuntimeException`  | Container, Integration Manager            |
| `\Exception`         | REST providers, AI Recommendations        |
| `\Throwable`         | Generic catch blocks in schedulers, media |
| `ContainerException` | Container and service providers           |
| `NotFoundException`  | Container service resolution              |

### Adoption Opportunities

The domain-specific exceptions could be adopted in:

| Component                   | Suggested Exception   |
| --------------------------- | --------------------- |
| `includes/Database/`        | `DatabaseException`   |
| `includes/Media/`           | `MediaException`      |
| REST API parameter handling | `ValidationException` |
| Batch processors            | `DatabaseException`   |

## HTTP Status Code Mapping

| Status Code | Meaning             | Used By                                                     |
| ----------- | ------------------- | ----------------------------------------------------------- |
| 400         | Bad Request         | All ValidationException methods                             |
| 404         | Not Found           | `table_not_found`, `file_not_found`, `attachment_not_found` |
| 409         | Conflict            | `constraint_failed`                                         |
| 413         | Payload Too Large   | `size_exceeded`                                             |
| 500         | Server Error        | Default, `query_failed`, `delete_failed`, `scan_failed`     |
| 503         | Service Unavailable | `connection_lost`                                           |
| 504         | Gateway Timeout     | `timeout`                                                   |

## Best Practices Observed

### Strengths

1. **Consistent Hierarchy**: All plugin exceptions extend a common base class
2. **Named Factory Methods**: Clear, semantic exception creation (e.g., `query_failed()`)
3. **Error Code Constants**: Defined constants for all error codes enable programmatic handling
4. **Context Preservation**: Additional debugging context without exposing in messages
5. **WordPress Integration**: Seamless `WP_Error` and `WP_REST_Response` conversion
6. **Input Sanitization**: All user input is sanitized via `sanitize_text_field()`
7. **Value Truncation**: Long values are truncated to prevent log bloat
8. **Path Security**: Only basenames are exposed, not full paths
9. **HTTP Status Mapping**: Appropriate HTTP codes for REST API responses
10. **PSR-11 Compatibility**: Container exceptions follow standard interfaces

### Areas for Improvement

1. **Adoption**: The `includes/Exceptions/` exceptions are defined but not yet used in the codebase
2. **Missing Constants**: `ERROR_COLUMN_NOT_FOUND` and `ERROR_UPLOAD_FAILED` defined but have no factory methods
3. **i18n Consistency**: Domain exceptions don't use `__()` for messages (unlike Container exceptions)

## Usage Examples

### Creating Database Exceptions

```php
use WPAdminHealth\Exceptions\DatabaseException;

// Query failure with error context
throw DatabaseException::query_failed( $wpdb->last_query, $wpdb->last_error );

// Table not found
throw DatabaseException::table_not_found( $wpdb->prefix . 'health_metrics' );

// Connection timeout
throw DatabaseException::timeout( 30 );
```

### Creating Media Exceptions

```php
use WPAdminHealth\Exceptions\MediaException;

// File not found
throw MediaException::file_not_found( $attachment_id, $file_path );

// File too large
throw MediaException::size_exceeded(
    filesize( $file ),
    wp_max_upload_size(),
    $file_name
);
```

### Creating Validation Exceptions

```php
use WPAdminHealth\Exceptions\ValidationException;

// Invalid parameter
throw ValidationException::invalid_param( 'days', $days, 'Must be positive integer' );

// Missing required parameter
throw ValidationException::missing_param( 'attachment_id' );

// Invalid range
throw ValidationException::invalid_range( 'quality', $quality, 1, 100 );
```

### Converting to REST Response

```php
try {
    // ... operation
} catch ( WPAdminHealthException $e ) {
    return $e->to_rest_response();
}
```

### Converting to WP_Error

```php
try {
    // ... operation
} catch ( WPAdminHealthException $e ) {
    return $e->to_wp_error();
}
```

## Recommendations

### Short-term

1. **Adopt in REST Endpoints**: Replace generic `WP_Error` returns with domain exceptions
2. **Add Factory Methods**: Create methods for `ERROR_COLUMN_NOT_FOUND` and `ERROR_UPLOAD_FAILED`
3. **Add i18n Support**: Wrap messages in `__()` for translation

### Medium-term

1. **Create ConfigException**: For configuration/settings validation errors
2. **Create SchedulerException**: For scheduled task failures
3. **Add Exception Logging**: Centralized logging hook for all `WPAdminHealthException` instances

### Long-term

1. **Exception Monitoring**: Integration with error monitoring services
2. **User-facing Error Messages**: Separate technical and user-friendly messages
3. **Error Code Registry**: Central documentation of all error codes

## Summary

The exception handling architecture provides a solid foundation with:

- **4 domain exceptions** in `includes/Exceptions/`
- **2 container exceptions** in `includes/Container/`
- **18 error code constants** across all exceptions
- **15 named factory methods** for semantic exception creation
- **Full WordPress integration** via WP_Error and WP_REST_Response conversion

The implementation follows security best practices by sanitizing inputs, truncating long values, and hiding sensitive file paths. The main opportunity is to increase adoption of these well-designed exceptions throughout the codebase.
