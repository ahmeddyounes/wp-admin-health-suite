# REST API Documentation

Complete API reference for WP Admin Health Suite REST API endpoints.

## Table of Contents

- [Overview](#overview)
- [API Compatibility](#api-compatibility)
- [Authentication](#authentication)
- [Rate Limiting](#rate-limiting)
- [Response Format](#response-format)
- [Error Codes](#error-codes)
- [Endpoints](#endpoints)
    - [Dashboard](#dashboard-endpoints)
    - [Database](#database-endpoints)
    - [Media](#media-endpoints)
    - [Performance](#performance-endpoints)
    - [Activity](#activity-endpoints)
- [Postman Collection](#postman-collection)

## Overview

The WP Admin Health Suite provides a comprehensive REST API for programmatic access to all plugin features. All endpoints are namespaced under `/wp-json/wpha/v1/`.

**Base URL**: `https://your-site.com/wp-json/wpha/v1/`

**API Version**: v1

## API Compatibility

The `wpha/v1` API follows a stability policy to ensure your integrations continue to work across plugin updates.

### Stability Promise

- **Route paths are stable**: Once an endpoint is released, its path will not change without a deprecation period
- **Response structures are additive**: New fields may be added, but existing fields will not be removed or renamed
- **Request parameters are additive**: New optional parameters may be added, but existing ones will not change meaning
- **Error codes are stable**: Documented error codes will continue to be returned for the same error conditions

### Backward Compatibility

We follow these rules for backward-compatible evolution:

| Change Type              | Allowed | Not Allowed            |
| ------------------------ | ------- | ---------------------- |
| Add new endpoint         | ✅      | —                      |
| Add optional parameter   | ✅      | —                      |
| Add response field       | ✅      | —                      |
| Remove endpoint          | —       | ❌ Without deprecation |
| Remove response field    | —       | ❌                     |
| Change parameter meaning | —       | ❌                     |
| Change field type        | —       | ❌                     |

### Deprecation Process

When an endpoint needs to be replaced:

1. **Deprecation headers**: Deprecated endpoints include response headers:
    - `X-WPHA-Deprecated: true`
    - `X-WPHA-Deprecated-Message: Use /wpha/v1/new-route instead`
    - `X-WPHA-Sunset-Version: 2.0.0`

2. **Grace period**: Deprecated endpoints continue to function for at least one minor release

3. **Removal**: Deprecated endpoints may be removed in the next major version

### Version Support

| Version | Status      | Notes                                    |
| ------- | ----------- | ---------------------------------------- |
| `v1`    | **Current** | Actively maintained, backward compatible |

**Note**: There is no `v2` API planned. The `v1` API evolves in place with backward-compatible changes only.

### Checking for Deprecation

To check if you're using deprecated endpoints, look for the `X-WPHA-Deprecated` header in responses:

```javascript
fetch('/wp-json/wpha/v1/some-endpoint', {
	headers: { 'X-WP-Nonce': wpApiSettings.nonce },
}).then((response) => {
	if (response.headers.get('X-WPHA-Deprecated')) {
		console.warn(
			'Deprecated endpoint:',
			response.headers.get('X-WPHA-Deprecated-Message')
		);
	}
	return response.json();
});
```

## Authentication

All REST API endpoints require authentication and proper permissions.

### Requirements

1. **User must be logged in** - Unauthenticated requests will receive a `401` error
2. **User must have `manage_options` capability** - Typically administrator users
3. **REST API must be enabled** - Can be toggled in plugin settings
4. **Valid nonce required** - Must send `X-WP-Nonce` header

### How to Authenticate

#### Method 1: Using WordPress Nonce (Recommended for AJAX)

Include the nonce in the request header:

```javascript
fetch('https://your-site.com/wp-json/wpha/v1/dashboard/stats', {
	method: 'GET',
	headers: {
		'X-WP-Nonce': wpApiSettings.nonce, // WordPress provides this
	},
});
```

#### Method 2: Cookie Authentication

WordPress automatically handles cookie authentication when making requests from the admin dashboard.

### Getting a Nonce

In WordPress admin, the nonce is available via:

```javascript
// WordPress localizes this for you
const nonce = wpApiSettings.nonce;
```

Or generate one in PHP:

```php
wp_create_nonce('wp_rest');
```

## Rate Limiting

To prevent abuse, the API implements rate limiting per user.

**Default Limit**: 60 requests per minute per user

**Configurable**: Can be adjusted in plugin settings

### Rate Limit Headers

Currently not included in response headers. Rate limit is enforced via transients.

### Rate Limit Exceeded Response

```json
{
	"success": false,
	"data": null,
	"message": "Rate limit exceeded. Maximum 60 requests per minute allowed.",
	"code": "rest_rate_limit_exceeded"
}
```

**HTTP Status Code**: `429 Too Many Requests`

## Response Format

All API endpoints return responses in a standardized format.

### Success Response

```json
{
	"success": true,
	"data": {
		// Response data here
	},
	"message": "Operation completed successfully."
}
```

**HTTP Status Code**: `200` (or `201` for create operations)

### Error Response

```json
{
	"success": false,
	"data": null,
	"message": "Error description here.",
	"code": "error_code"
}
```

**HTTP Status Code**: `400`, `401`, `403`, `404`, `429`, or `500`

### Debug Mode

When debug mode is enabled in plugin settings, additional debug information is included:

```json
{
  "success": true,
  "data": { ... },
  "message": "Success message",
  "debug": {
    "queries": 15,
    "memory_usage": "2 MB",
    "memory_peak": "3 MB",
    "time_elapsed": "0.234",
    "query_log": [
      {
        "query": "SELECT * FROM wp_posts WHERE...",
        "time": "0.001s",
        "stack": "require('wp-load.php')..."
      }
    ]
  }
}
```

## Error Codes

| Error Code                 | HTTP Status | Description                             |
| -------------------------- | ----------- | --------------------------------------- |
| `rest_api_disabled`        | 403         | REST API is disabled in plugin settings |
| `rest_not_logged_in`       | 401         | User is not authenticated               |
| `rest_forbidden`           | 403         | User lacks required permissions         |
| `rest_missing_nonce`       | 403         | X-WP-Nonce header is missing            |
| `rest_invalid_nonce`       | 403         | X-WP-Nonce header is invalid            |
| `rest_rate_limit_exceeded` | 429         | Rate limit exceeded for this user       |
| `invalid_action`           | 400         | Invalid action ID provided              |
| `invalid_type`             | 400         | Invalid cleanup type specified          |
| `invalid_ids`              | 400         | No valid IDs provided                   |
| `database_error`           | 500         | Database operation failed               |
| `deletion_failed`          | 400         | Media deletion operation failed         |
| `restore_failed`           | 400         | Media restore operation failed          |
| `optimization_failed`      | 400         | Database optimization failed            |
| `option_not_found`         | 404         | Specified option does not exist         |
| `update_failed`            | 500         | Update operation failed                 |

## Endpoints

### Dashboard Endpoints

#### Get Dashboard Statistics

Retrieve aggregated dashboard metrics including database size, media count, cleanable items, and last cleanup date.

**Endpoint**: `GET /wpha/v1/dashboard/stats`

**Response**:

```json
{
	"success": true,
	"data": {
		"total_db_size": 52428800,
		"total_media_count": 143,
		"cleanable_items": 27,
		"last_cleanup_date": "2025-01-06 10:30:00",
		"cache_timestamp": 1704540600
	},
	"message": "Dashboard stats retrieved successfully."
}
```

**Response Fields**:

- `total_db_size` (int): Total database size in bytes
- `total_media_count` (int): Total number of media attachments
- `cleanable_items` (int): Number of items that can be cleaned
- `last_cleanup_date` (string|null): Date of last cleanup operation
- `cache_timestamp` (int): Unix timestamp when stats were cached

**Caching**: Results are cached for 5 minutes

---

#### Get Health Score

Retrieve the overall health score along with individual factor scores and recommendations.

**Endpoint**: `GET /wpha/v1/dashboard/health-score`

**Response**:

```json
{
	"success": true,
	"data": {
		"score": 85,
		"grade": "B",
		"factors": {
			"database": 90,
			"media": 80,
			"performance": 85
		},
		"recommendations": [
			{
				"title": "Clean up orphaned data",
				"description": "15 orphaned postmeta entries found",
				"severity": "medium",
				"action": "clean_orphaned"
			}
		],
		"timestamp": 1704540600
	},
	"message": "Health score retrieved successfully."
}
```

---

#### Get Activity Log

Retrieve paginated activity log entries from scan history.

**Endpoint**: `GET /wpha/v1/dashboard/activity`

**Query Parameters**:

- `page` (int, optional): Page number (default: 1)
- `per_page` (int, optional): Items per page (default: 10, max: 100)

**Example Request**:

```bash
curl -X GET "https://your-site.com/wp-json/wpha/v1/dashboard/activity?page=1&per_page=20" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**Response**:

```json
{
	"success": true,
	"data": {
		"items": [
			{
				"id": 42,
				"scan_type": "database_revisions",
				"items_found": 150,
				"items_cleaned": 100,
				"bytes_freed": 1048576,
				"created_at": "2025-01-06 10:30:00"
			}
		],
		"total": 150,
		"total_pages": 8,
		"current_page": 1,
		"per_page": 20
	},
	"message": "Activities retrieved successfully."
}
```

---

#### Execute Quick Action

Execute a quick action by ID and log the result.

**Endpoint**: `POST /wpha/v1/dashboard/quick-action`

**Request Body**:

```json
{
	"action_id": "delete_trash"
}
```

**Available Actions**:

- `delete_trash` - Delete all trashed posts
- `delete_spam` - Delete all spam comments
- `delete_auto_drafts` - Delete all auto-drafts
- `clean_expired_transients` - Clean expired transients
- `optimize_tables` - Optimize all database tables

**Example Request**:

```bash
curl -X POST "https://your-site.com/wp-json/wpha/v1/dashboard/quick-action" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"action_id": "delete_trash"}'
```

**Response**:

```json
{
	"success": true,
	"data": {
		"action_id": "delete_trash",
		"items_cleaned": 15,
		"bytes_freed": 0,
		"log_id": 43
	},
	"message": "Action executed successfully. 15 items cleaned."
}
```

---

### Database Endpoints

#### Get Database Statistics

Retrieve comprehensive database statistics including sizes, counts, and orphaned data.

**Endpoint**: `GET /wpha/v1/database/stats`

**Response**:

```json
{
	"success": true,
	"data": {
		"database_size": 52428800,
		"table_sizes": {
			"wp_posts": 10485760,
			"wp_postmeta": 5242880
		},
		"revisions_count": 250,
		"auto_drafts_count": 12,
		"trashed_posts_count": 8,
		"spam_comments_count": 45,
		"trashed_comments_count": 3,
		"expired_transients_count": 67,
		"orphaned_postmeta_count": 15,
		"orphaned_commentmeta_count": 5,
		"orphaned_termmeta_count": 2
	},
	"message": "Database statistics retrieved successfully."
}
```

---

#### Get Revision Details

Get detailed information about post revisions.

**Endpoint**: `GET /wpha/v1/database/revisions`

**Query Parameters**:

- `limit` (int, optional): Number of posts to return with most revisions (default: 10, max: 50)

**Example Request**:

```bash
curl -X GET "https://your-site.com/wp-json/wpha/v1/database/revisions?limit=5" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**Response**:

```json
{
	"success": true,
	"data": {
		"total_count": 250,
		"size_estimate": 2621440,
		"posts_with_most_revisions": [
			{
				"post_id": 42,
				"post_title": "Sample Post",
				"revision_count": 25,
				"size_estimate": 262144
			}
		]
	},
	"message": "Revision details retrieved successfully."
}
```

---

#### Get Transients List

Get information about database transients.

**Endpoint**: `GET /wpha/v1/database/transients`

**Response**:

```json
{
	"success": true,
	"data": {
		"total_count": 150,
		"size_estimate": 524288,
		"expired_transients": [
			{
				"name": "_transient_feed_abc123",
				"size": 1024,
				"expires": "2025-01-05 10:00:00"
			}
		],
		"using_external_cache": false
	},
	"message": "Transient list retrieved successfully."
}
```

---

#### Get Orphaned Data Summary

Get summary of orphaned database entries.

**Endpoint**: `GET /wpha/v1/database/orphaned`

**Response**:

```json
{
	"success": true,
	"data": {
		"orphaned_postmeta": {
			"count": 15
		},
		"orphaned_commentmeta": {
			"count": 5
		},
		"orphaned_termmeta": {
			"count": 2
		},
		"orphaned_relationships": {
			"count": 8
		}
	},
	"message": "Orphaned data summary retrieved successfully."
}
```

---

#### Clean Database

Execute cleanup operation by type.

**Endpoint**: `POST /wpha/v1/database/clean`

**Request Body**:

```json
{
	"type": "revisions",
	"options": {
		"keep_per_post": 5
	}
}
```

**Available Types**:

- `revisions` - Clean post revisions
- `transients` - Clean transients
- `spam` - Clean spam comments
- `trash` - Clean trashed posts and comments
- `orphaned` - Clean orphaned metadata

**Type-Specific Options**:

**Revisions**:

```json
{
	"type": "revisions",
	"options": {
		"keep_per_post": 5
	}
}
```

**Transients**:

```json
{
	"type": "transients",
	"options": {
		"expired_only": true,
		"exclude_patterns": ["my_plugin_", "custom_cache_"]
	}
}
```

**Trash**:

```json
{
	"type": "trash",
	"options": {
		"older_than_days": 30,
		"post_types": ["post", "page"]
	}
}
```

**Spam**:

```json
{
	"type": "spam",
	"options": {
		"older_than_days": 7
	}
}
```

**Orphaned**:

```json
{
	"type": "orphaned",
	"options": {
		"types": ["postmeta", "commentmeta", "termmeta", "relationships"]
	}
}
```

**Example Request**:

```bash
curl -X POST "https://your-site.com/wp-json/wpha/v1/database/clean" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "revisions",
    "options": {
      "keep_per_post": 5
    }
  }'
```

**Response**:

```json
{
	"success": true,
	"data": {
		"type": "revisions",
		"deleted": 150,
		"bytes_freed": 1572864,
		"keep_per_post": 5
	},
	"message": "Revisions cleanup completed successfully."
}
```

**Safe Mode Response** (when safe mode is enabled):

```json
{
	"success": true,
	"data": {
		"type": "revisions",
		"deleted": 0,
		"would_delete": 150,
		"bytes_freed": 0,
		"would_free": 1572864,
		"keep_per_post": 5,
		"safe_mode": true,
		"preview_only": true
	},
	"message": "Revisions cleanup completed successfully."
}
```

---

#### Optimize Database Tables

Run database table optimization.

**Endpoint**: `POST /wpha/v1/database/optimize`

**Request Body**:

```json
{
	"tables": ["wp_posts", "wp_postmeta"]
}
```

Leave `tables` empty to optimize all tables:

```json
{
	"tables": []
}
```

**Example Request**:

```bash
curl -X POST "https://your-site.com/wp-json/wpha/v1/database/optimize" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"tables": []}'
```

**Response**:

```json
{
	"success": true,
	"data": {
		"results": [
			{
				"table": "wp_posts",
				"status": "OK",
				"size_before": 10485760,
				"size_after": 9437184,
				"size_reduced": 1048576
			}
		],
		"tables_optimized": 15,
		"total_bytes_freed": 5242880
	},
	"message": "Database optimization completed successfully."
}
```

---

### Media Endpoints

#### Get Media Statistics

Retrieve comprehensive media library statistics.

**Endpoint**: `GET /wpha/v1/media/stats`

**Response**:

```json
{
	"success": true,
	"data": {
		"total_count": 450,
		"total_size": 157286400,
		"total_size_formatted": "150 MB",
		"unused_count": 23,
		"duplicate_count": 15,
		"duplicate_groups": 5,
		"large_files_count": 12,
		"missing_alt_count": 67,
		"potential_savings": {
			"bytes": 10485760,
			"formatted": "10 MB"
		}
	},
	"message": "Media statistics retrieved successfully."
}
```

---

#### Get Unused Media

Retrieve paginated list of unused media files.

**Endpoint**: `GET /wpha/v1/media/unused`

**Query Parameters**:

- `cursor` (int, optional): Pagination cursor/offset (default: 0)
- `per_page` (int, optional): Items per page (default: 50, max: 100)

**Example Request**:

```bash
curl -X GET "https://your-site.com/wp-json/wpha/v1/media/unused?per_page=20" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**Response**:

```json
{
	"success": true,
	"data": {
		"items": [
			{
				"id": 123,
				"title": "unused-image",
				"filename": "unused-image.jpg",
				"file_size": 524288,
				"file_size_formatted": "512 KB",
				"mime_type": "image/jpeg",
				"thumbnail_url": "https://example.com/wp-content/uploads/2025/01/unused-image-150x150.jpg",
				"edit_link": "https://example.com/wp-admin/post.php?post=123&action=edit"
			}
		],
		"total": 23,
		"cursor": 20,
		"has_more": true
	},
	"message": "Unused media retrieved successfully."
}
```

---

#### Get Duplicate Media Groups

Retrieve groups of duplicate media files.

**Endpoint**: `GET /wpha/v1/media/duplicates`

**Query Parameters**:

- `cursor` (int, optional): Pagination cursor/offset (default: 0)
- `per_page` (int, optional): Items per page (default: 50, max: 100)

**Response**:

```json
{
	"success": true,
	"data": {
		"groups": [
			{
				"hash": "abc123def456",
				"count": 3,
				"original": {
					"id": 100,
					"title": "original-image",
					"filename": "image.jpg",
					"file_size": 1048576,
					"file_size_formatted": "1 MB",
					"mime_type": "image/jpeg",
					"thumbnail_url": "https://example.com/.../image-150x150.jpg",
					"edit_link": "https://example.com/wp-admin/post.php?post=100&action=edit"
				},
				"copies": [
					{
						"id": 101,
						"title": "image-copy",
						"filename": "image-1.jpg",
						"file_size": 1048576,
						"file_size_formatted": "1 MB",
						"mime_type": "image/jpeg",
						"thumbnail_url": "https://example.com/.../image-1-150x150.jpg",
						"edit_link": "https://example.com/wp-admin/post.php?post=101&action=edit"
					}
				]
			}
		],
		"total": 5,
		"cursor": null,
		"has_more": false
	},
	"message": "Duplicate groups retrieved successfully."
}
```

---

#### Get Large Files

Retrieve list of large media files.

**Endpoint**: `GET /wpha/v1/media/large`

**Query Parameters**:

- `threshold` (int, optional): Minimum file size in KB (default: 500)
- `cursor` (int, optional): Pagination cursor/offset (default: 0)
- `per_page` (int, optional): Items per page (default: 50, max: 100)

**Example Request**:

```bash
curl -X GET "https://your-site.com/wp-json/wpha/v1/media/large?threshold=1000&per_page=10" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**Response**:

```json
{
	"success": true,
	"data": {
		"items": [
			{
				"id": 150,
				"filename": "large-image.jpg",
				"size": 2097152,
				"size_formatted": "2 MB",
				"dimensions": "3000x2000",
				"thumbnail_url": "https://example.com/.../large-image-150x150.jpg",
				"edit_link": "https://example.com/wp-admin/post.php?post=150&action=edit"
			}
		],
		"total": 12,
		"cursor": null,
		"has_more": false
	},
	"message": "Large files retrieved successfully."
}
```

---

#### Get Images Missing Alt Text

Retrieve images that are missing alt text attributes.

**Endpoint**: `GET /wpha/v1/media/alt-text`

**Query Parameters**:

- `cursor` (int, optional): Pagination cursor/offset (default: 0)
- `per_page` (int, optional): Items per page (default: 50, max: 100)

**Response**:

```json
{
	"success": true,
	"data": {
		"items": [
			{
				"id": 200,
				"title": "Image without alt",
				"filename": "no-alt.jpg",
				"thumbnail_url": "https://example.com/.../no-alt-150x150.jpg",
				"edit_link": "https://example.com/wp-admin/post.php?post=200&action=edit"
			}
		],
		"total": 67,
		"cursor": 50,
		"has_more": true
	},
	"message": "Images missing alt text retrieved successfully."
}
```

---

#### Trigger Media Scan

Trigger a full media library scan (background task).

**Endpoint**: `POST /wpha/v1/media/scan`

**Example Request**:

```bash
curl -X POST "https://your-site.com/wp-json/wpha/v1/media/scan" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**Response (with Action Scheduler)**:

```json
{
	"success": true,
	"data": {
		"status": "scheduled",
		"message": "Media scan has been scheduled to run in the background."
	},
	"message": "Media scan scheduled successfully."
}
```

**Response (without Action Scheduler)**:

```json
{
	"success": true,
	"data": {
		"status": "completed",
		"results": {
			"scanned": 450,
			"unused": 23,
			"duplicates": 5
		}
	},
	"message": "Media scan completed successfully."
}
```

---

#### Safe Delete Media

Safely delete media files with backup capability.

**Endpoint**: `POST /wpha/v1/media/delete`

**Request Body**:

```json
{
	"ids": [123, 124, 125]
}
```

**Example Request**:

```bash
curl -X POST "https://your-site.com/wp-json/wpha/v1/media/delete" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"ids": [123, 124, 125]}'
```

**Response**:

```json
{
	"success": true,
	"data": {
		"prepared_items": [123, 124, 125],
		"deletion_id": 42,
		"message": "3 items prepared for deletion."
	},
	"message": "3 items prepared for deletion."
}
```

---

#### Restore Deleted Media

Restore media files from the deletion backup.

**Endpoint**: `POST /wpha/v1/media/restore`

**Request Body**:

```json
{
	"deletion_id": 42
}
```

**Example Request**:

```bash
curl -X POST "https://your-site.com/wp-json/wpha/v1/media/restore" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"deletion_id": 42}'
```

**Response**:

```json
{
	"success": true,
	"data": {
		"restored_items": [123, 124, 125],
		"message": "3 items restored successfully."
	},
	"message": "3 items restored successfully."
}
```

---

#### Get Media Exclusions

Retrieve all media items marked as excluded from cleanup.

**Endpoint**: `GET /wpha/v1/media/exclusions`

**Response**:

```json
{
	"success": true,
	"data": {
		"exclusions": [
			{
				"attachment_id": 100,
				"reason": "Logo image - do not delete",
				"excluded_by": 1,
				"excluded_by_name": "Admin User",
				"excluded_at": "2025-01-05 10:00:00",
				"id": 100,
				"title": "company-logo",
				"filename": "logo.png",
				"file_size": 51200,
				"file_size_formatted": "50 KB",
				"mime_type": "image/png",
				"thumbnail_url": "https://example.com/.../logo-150x150.png",
				"edit_link": "https://example.com/wp-admin/post.php?post=100&action=edit"
			}
		],
		"total": 5
	},
	"message": "Exclusions retrieved successfully."
}
```

---

#### Add Media Exclusions

Exclude media items from cleanup operations.

**Endpoint**: `POST /wpha/v1/media/exclusions`

**Request Body**:

```json
{
	"ids": [100, 101, 102],
	"reason": "Important company assets"
}
```

**Example Request**:

```bash
curl -X POST "https://your-site.com/wp-json/wpha/v1/media/exclusions" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{
    "ids": [100, 101, 102],
    "reason": "Important company assets"
  }'
```

**Response**:

```json
{
	"success": true,
	"data": {
		"added": 3,
		"failed": 0
	},
	"message": "3 item(s) excluded successfully."
}
```

---

#### Remove Media Exclusion

Remove a single media item from exclusions.

**Endpoint**: `DELETE /wpha/v1/media/exclusions/{id}`

**Example Request**:

```bash
curl -X DELETE "https://your-site.com/wp-json/wpha/v1/media/exclusions/100" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**Response**:

```json
{
	"success": true,
	"data": {
		"removed": 100
	},
	"message": "Exclusion removed successfully."
}
```

---

#### Clear All Media Exclusions

Remove all media exclusions at once.

**Endpoint**: `DELETE /wpha/v1/media/exclusions`

**Example Request**:

```bash
curl -X DELETE "https://your-site.com/wp-json/wpha/v1/media/exclusions" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**Response**:

```json
{
	"success": true,
	"data": {},
	"message": "All exclusions cleared successfully."
}
```

---

### Performance Endpoints

#### Get Performance Statistics

Retrieve performance score and metrics.

**Endpoint**: `GET /wpha/v1/performance/stats`

**Response**:

```json
{
	"success": true,
	"data": {
		"score": 75,
		"grade": "C",
		"plugin_count": 25,
		"autoload_size": 524288,
		"query_count": 45,
		"object_cache": false,
		"timestamp": 1704540600
	},
	"message": "Performance score retrieved successfully."
}
```

**Score Grading**:

- A: 90-100
- B: 80-89
- C: 70-79
- D: 60-69
- F: 0-59

---

#### Get Plugin Impact Analysis

Analyze the performance impact of active plugins.

**Endpoint**: `GET /wpha/v1/performance/plugins`

**Response**:

```json
{
	"success": true,
	"data": {
		"plugins": [
			{
				"name": "WooCommerce",
				"file": "woocommerce/woocommerce.php",
				"version": "8.5.0",
				"load_time": 85.5,
				"memory": 2048,
				"queries": 8
			}
		]
	},
	"message": "Plugin impact data retrieved successfully."
}
```

**Note**: Values are estimates based on plugin file count and size.

---

#### Get Query Analysis

Analyze database query performance.

**Endpoint**: `GET /wpha/v1/performance/queries`

**Response**:

```json
{
	"success": true,
	"data": {
		"total_queries": 45,
		"slow_queries": [
			{
				"query": "SELECT * FROM wp_posts WHERE...",
				"time": 0.125,
				"caller": "get_posts"
			}
		],
		"savequeries": true
	},
	"message": "Query analysis retrieved successfully."
}
```

**Note**: Requires `SAVEQUERIES` constant to be defined and set to `true` in `wp-config.php`.

---

#### Get Heartbeat Settings

Retrieve current WordPress Heartbeat API settings.

**Endpoint**: `GET /wpha/v1/performance/heartbeat`

**Response**:

```json
{
	"success": true,
	"data": {
		"dashboard": {
			"enabled": true,
			"interval": 60
		},
		"editor": {
			"enabled": true,
			"interval": 15
		},
		"frontend": {
			"enabled": false,
			"interval": 60
		}
	},
	"message": "Heartbeat settings retrieved successfully."
}
```

---

#### Update Heartbeat Settings

Update WordPress Heartbeat API settings for a specific location.

**Endpoint**: `POST /wpha/v1/performance/heartbeat`

**Request Body**:

```json
{
	"location": "dashboard",
	"enabled": false,
	"interval": 120
}
```

**Available Locations**:

- `dashboard` - WordPress admin dashboard
- `editor` - Post/page editor
- `frontend` - Site frontend

**Example Request**:

```bash
curl -X POST "https://your-site.com/wp-json/wpha/v1/performance/heartbeat" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{
    "location": "dashboard",
    "enabled": false
  }'
```

**Response**:

```json
{
	"success": true,
	"data": {
		"dashboard": {
			"enabled": false,
			"interval": 60
		},
		"editor": {
			"enabled": true,
			"interval": 15
		},
		"frontend": {
			"enabled": false,
			"interval": 60
		}
	},
	"message": "Heartbeat settings updated successfully."
}
```

---

#### Get Cache Status

Retrieve object cache and OPcache status information.

**Endpoint**: `GET /wpha/v1/performance/cache`

**Response**:

```json
{
	"success": true,
	"data": {
		"object_cache_enabled": true,
		"cache_type": "Redis",
		"opcache_enabled": true,
		"opcache_stats": {
			"hit_rate": 95.5,
			"memory_usage": 67108864,
			"cached_scripts": 450
		}
	},
	"message": "Cache status retrieved successfully."
}
```

---

#### Get Autoload Analysis

Analyze autoloaded WordPress options.

**Endpoint**: `GET /wpha/v1/performance/autoload`

**Response**:

```json
{
	"success": true,
	"data": {
		"total_size": 524288,
		"total_size_mb": 0.5,
		"options": [
			{
				"name": "active_plugins",
				"size": 2048
			},
			{
				"name": "theme_mods_twentytwentyfour",
				"size": 4096
			}
		],
		"count": 50
	},
	"message": "Autoload analysis retrieved successfully."
}
```

---

#### Update Autoload Setting

Change the autoload setting for a specific WordPress option.

**Endpoint**: `POST /wpha/v1/performance/autoload`

**Request Body**:

```json
{
	"option_name": "my_plugin_settings",
	"autoload": false
}
```

**Example Request**:

```bash
curl -X POST "https://your-site.com/wp-json/wpha/v1/performance/autoload" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{
    "option_name": "my_plugin_settings",
    "autoload": false
  }'
```

**Response**:

```json
{
	"success": true,
	"data": {
		"option_name": "my_plugin_settings",
		"autoload": false
	},
	"message": "Autoload setting updated successfully."
}
```

---

#### Get Performance Recommendations

Retrieve personalized performance optimization recommendations.

**Endpoint**: `GET /wpha/v1/performance/recommendations`

**Response**:

```json
{
	"success": true,
	"data": {
		"recommendations": [
			{
				"type": "warning",
				"title": "Too Many Plugins",
				"description": "You have 35 active plugins. Consider deactivating unused plugins to improve performance.",
				"action": "review_plugins"
			},
			{
				"type": "info",
				"title": "Enable Object Caching",
				"description": "Consider implementing an object cache (Redis, Memcached) to improve database performance.",
				"action": "enable_object_cache"
			}
		]
	},
	"message": "Recommendations retrieved successfully."
}
```

**Recommendation Types**:

- `warning` - Important issues that should be addressed
- `info` - Suggestions for improvement

---

### Activity Endpoints

#### Get Recent Activities

Retrieve recent activities from scan history.

**Endpoint**: `GET /wpha/v1/activity`

**Query Parameters**:

- `limit` (int, optional): Maximum activities to return (default: 10, max: 100)

**Example Request**:

```bash
curl -X GET "https://your-site.com/wp-json/wpha/v1/activity?limit=20" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**Response**:

```json
{
	"success": true,
	"data": [
		{
			"id": 42,
			"scan_type": "database_revisions",
			"items_found": 150,
			"items_cleaned": 100,
			"bytes_freed": 1048576,
			"created_at": "2025-01-06 10:30:00"
		},
		{
			"id": 41,
			"scan_type": "media_delete",
			"items_found": 5,
			"items_cleaned": 5,
			"bytes_freed": 2097152,
			"created_at": "2025-01-06 09:15:00"
		}
	],
	"message": "Activities retrieved successfully."
}
```

---

## Postman Collection

### Import Instructions

1. Download the Postman collection JSON file below
2. Open Postman
3. Click **Import** button
4. Select the downloaded JSON file
5. Configure environment variables:
    - `base_url`: Your WordPress site URL (e.g., `https://your-site.com`)
    - `nonce`: Your WordPress REST API nonce

### Postman Collection JSON

```json
{
	"info": {
		"name": "WP Admin Health Suite API",
		"description": "Complete API collection for WP Admin Health Suite plugin",
		"schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
	},
	"variable": [
		{
			"key": "base_url",
			"value": "https://your-site.com",
			"type": "string"
		},
		{
			"key": "nonce",
			"value": "YOUR_NONCE_HERE",
			"type": "string"
		}
	],
	"item": [
		{
			"name": "Dashboard",
			"item": [
				{
					"name": "Get Dashboard Stats",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/dashboard/stats",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"dashboard",
								"stats"
							]
						}
					}
				},
				{
					"name": "Get Health Score",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/dashboard/health-score",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"dashboard",
								"health-score"
							]
						}
					}
				},
				{
					"name": "Get Activity Log",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/dashboard/activity?page=1&per_page=10",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"dashboard",
								"activity"
							],
							"query": [
								{
									"key": "page",
									"value": "1"
								},
								{
									"key": "per_page",
									"value": "10"
								}
							]
						}
					}
				},
				{
					"name": "Execute Quick Action",
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\n  \"action_id\": \"delete_trash\"\n}"
						},
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/dashboard/quick-action",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"dashboard",
								"quick-action"
							]
						}
					}
				}
			]
		},
		{
			"name": "Database",
			"item": [
				{
					"name": "Get Database Stats",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/database/stats",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"database",
								"stats"
							]
						}
					}
				},
				{
					"name": "Get Revisions",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/database/revisions?limit=10",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"database",
								"revisions"
							],
							"query": [
								{
									"key": "limit",
									"value": "10"
								}
							]
						}
					}
				},
				{
					"name": "Get Transients",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/database/transients",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"database",
								"transients"
							]
						}
					}
				},
				{
					"name": "Get Orphaned Data",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/database/orphaned",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"database",
								"orphaned"
							]
						}
					}
				},
				{
					"name": "Clean Database - Revisions",
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\n  \"type\": \"revisions\",\n  \"options\": {\n    \"keep_per_post\": 5\n  }\n}"
						},
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/database/clean",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"database",
								"clean"
							]
						}
					}
				},
				{
					"name": "Optimize Tables",
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\n  \"tables\": []\n}"
						},
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/database/optimize",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"database",
								"optimize"
							]
						}
					}
				}
			]
		},
		{
			"name": "Media",
			"item": [
				{
					"name": "Get Media Stats",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/stats",
							"host": ["{{base_url}}"],
							"path": ["wp-json", "wpha", "v1", "media", "stats"]
						}
					}
				},
				{
					"name": "Get Unused Media",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/unused?per_page=20",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"media",
								"unused"
							],
							"query": [
								{
									"key": "per_page",
									"value": "20"
								}
							]
						}
					}
				},
				{
					"name": "Get Duplicates",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/duplicates",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"media",
								"duplicates"
							]
						}
					}
				},
				{
					"name": "Get Large Files",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/large?threshold=500",
							"host": ["{{base_url}}"],
							"path": ["wp-json", "wpha", "v1", "media", "large"],
							"query": [
								{
									"key": "threshold",
									"value": "500"
								}
							]
						}
					}
				},
				{
					"name": "Get Missing Alt Text",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/alt-text",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"media",
								"alt-text"
							]
						}
					}
				},
				{
					"name": "Trigger Media Scan",
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/scan",
							"host": ["{{base_url}}"],
							"path": ["wp-json", "wpha", "v1", "media", "scan"]
						}
					}
				},
				{
					"name": "Safe Delete Media",
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\n  \"ids\": [123, 124, 125]\n}"
						},
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/delete",
							"host": ["{{base_url}}"],
							"path": ["wp-json", "wpha", "v1", "media", "delete"]
						}
					}
				},
				{
					"name": "Restore Media",
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\n  \"deletion_id\": 42\n}"
						},
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/restore",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"media",
								"restore"
							]
						}
					}
				},
				{
					"name": "Get Exclusions",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/exclusions",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"media",
								"exclusions"
							]
						}
					}
				},
				{
					"name": "Add Exclusions",
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\n  \"ids\": [100, 101],\n  \"reason\": \"Important assets\"\n}"
						},
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/media/exclusions",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"media",
								"exclusions"
							]
						}
					}
				}
			]
		},
		{
			"name": "Performance",
			"item": [
				{
					"name": "Get Performance Stats",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/performance/stats",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"performance",
								"stats"
							]
						}
					}
				},
				{
					"name": "Get Plugin Impact",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/performance/plugins",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"performance",
								"plugins"
							]
						}
					}
				},
				{
					"name": "Get Query Analysis",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/performance/queries",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"performance",
								"queries"
							]
						}
					}
				},
				{
					"name": "Get Heartbeat Settings",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/performance/heartbeat",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"performance",
								"heartbeat"
							]
						}
					}
				},
				{
					"name": "Update Heartbeat Settings",
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\n  \"location\": \"dashboard\",\n  \"enabled\": false,\n  \"interval\": 120\n}"
						},
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/performance/heartbeat",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"performance",
								"heartbeat"
							]
						}
					}
				},
				{
					"name": "Get Cache Status",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/performance/cache",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"performance",
								"cache"
							]
						}
					}
				},
				{
					"name": "Get Autoload Analysis",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/performance/autoload",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"performance",
								"autoload"
							]
						}
					}
				},
				{
					"name": "Update Autoload",
					"request": {
						"method": "POST",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							},
							{
								"key": "Content-Type",
								"value": "application/json",
								"type": "text"
							}
						],
						"body": {
							"mode": "raw",
							"raw": "{\n  \"option_name\": \"my_option\",\n  \"autoload\": false\n}"
						},
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/performance/autoload",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"performance",
								"autoload"
							]
						}
					}
				},
				{
					"name": "Get Recommendations",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/performance/recommendations",
							"host": ["{{base_url}}"],
							"path": [
								"wp-json",
								"wpha",
								"v1",
								"performance",
								"recommendations"
							]
						}
					}
				}
			]
		},
		{
			"name": "Activity",
			"item": [
				{
					"name": "Get Recent Activities",
					"request": {
						"method": "GET",
						"header": [
							{
								"key": "X-WP-Nonce",
								"value": "{{nonce}}",
								"type": "text"
							}
						],
						"url": {
							"raw": "{{base_url}}/wp-json/wpha/v1/activity?limit=10",
							"host": ["{{base_url}}"],
							"path": ["wp-json", "wpha", "v1", "activity"],
							"query": [
								{
									"key": "limit",
									"value": "10"
								}
							]
						}
					}
				}
			]
		}
	]
}
```

### Getting Your Nonce

To use this collection:

1. Log into your WordPress admin dashboard
2. Open browser developer tools (F12)
3. Go to Console tab
4. Run: `wpApiSettings.nonce`
5. Copy the returned value and set it as the `nonce` variable in Postman

### Alternative: Export Collection File

You can also save the above JSON to a file named `wp-admin-health-suite-postman-collection.json` and import it directly into Postman.

## Code Examples

### JavaScript (Fetch API)

```javascript
// Get dashboard statistics
async function getDashboardStats() {
	try {
		const response = await fetch(
			'https://your-site.com/wp-json/wpha/v1/dashboard/stats',
			{
				method: 'GET',
				headers: {
					'X-WP-Nonce': wpApiSettings.nonce,
				},
			}
		);

		const data = await response.json();

		if (data.success) {
			console.log('Dashboard stats:', data.data);
		} else {
			console.error('Error:', data.message);
		}
	} catch (error) {
		console.error('Request failed:', error);
	}
}

// Clean database revisions
async function cleanRevisions() {
	try {
		const response = await fetch(
			'https://your-site.com/wp-json/wpha/v1/database/clean',
			{
				method: 'POST',
				headers: {
					'X-WP-Nonce': wpApiSettings.nonce,
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					type: 'revisions',
					options: {
						keep_per_post: 5,
					},
				}),
			}
		);

		const data = await response.json();
		console.log('Cleanup result:', data);
	} catch (error) {
		console.error('Request failed:', error);
	}
}
```

### PHP (WordPress)

```php
<?php
// Get dashboard statistics
$response = wp_remote_get(
    home_url('/wp-json/wpha/v1/dashboard/stats'),
    array(
        'headers' => array(
            'X-WP-Nonce' => wp_create_nonce('wp_rest'),
        ),
    )
);

if (!is_wp_error($response)) {
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if ($data['success']) {
        print_r($data['data']);
    }
}

// Clean database revisions
$response = wp_remote_post(
    home_url('/wp-json/wpha/v1/database/clean'),
    array(
        'headers' => array(
            'X-WP-Nonce' => wp_create_nonce('wp_rest'),
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'type' => 'revisions',
            'options' => array(
                'keep_per_post' => 5,
            ),
        )),
    )
);
```

### cURL

```bash
# Get dashboard statistics
curl -X GET "https://your-site.com/wp-json/wpha/v1/dashboard/stats" \
  -H "X-WP-Nonce: YOUR_NONCE"

# Clean database revisions
curl -X POST "https://your-site.com/wp-json/wpha/v1/database/clean" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "revisions",
    "options": {
      "keep_per_post": 5
    }
  }'
```

## Support

For issues, questions, or feature requests related to the REST API, please visit:

- GitHub Issues: [github.com/yourproject/issues](https://github.com/yourproject/issues)
- Documentation: [docs/developers/](../README.md)
- Hooks Reference: [hooks.md](hooks.md)
