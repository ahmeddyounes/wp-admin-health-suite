# Performance Optimization Documentation

## Overview

This document outlines the performance optimizations implemented in the WP Admin Health Suite plugin to ensure minimal impact on admin page load times and proper handling of large sites (100k+ posts).

## Target Metrics

- **Admin Page Load Impact**: < 200ms added to admin page load
- **Large Site Support**: Handle sites with 100k+ posts without timeout
- **Database Queries**: Efficient queries with proper caching to avoid N+1 issues

## Implemented Optimizations

### 1. Lazy Loading for Admin Pages

**File**: `includes/Assets.php`

**Implementation**:

- CSS files are now loaded conditionally based on the current admin screen
- Only the CSS required for the active page is enqueued
- Main admin CSS is always loaded, while page-specific CSS (dashboard, database, media, performance) is lazy-loaded

**Benefits**:

- Reduced CSS payload on initial page load
- Faster page rendering on admin pages that don't use all features
- Better critical rendering path

### 2. Async/Defer JavaScript Loading

**File**: `includes/Assets.php`

**Implementation**:

- Added `add_async_defer_attributes()` method to modify script tags
- Non-critical JavaScript files are loaded with the `defer` attribute
- Vendor bundles are not deferred to maintain dependency order
- Scripts are filtered to only apply defer to plugin scripts (prefix: `wpha-`)

**Benefits**:

- JavaScript files no longer block page rendering
- Improved Time to First Paint (FP) and First Contentful Paint (FCP)
- Better perceived performance for users

### 3. Transient Caching for Expensive Operations

**File**: `includes/database/class-analyzer.php`

**Implementation**:

- Added transient caching for database size calculations
- Added transient caching for table size queries
- Cache expiration: 5 minutes (configurable via `CACHE_EXPIRATION` constant)
- Cache keys prefixed with `wpha_db_analyzer_` for easy identification

**Cached Operations**:

- `get_database_size()` - Caches total database size
- `get_table_sizes()` - Caches individual table sizes

**Benefits**:

- Expensive `information_schema` queries run only once per 5 minutes
- Dramatically reduced database load on repeated requests
- Better performance on large databases

### 4. Fixed N+1 Database Query Issues

**File**: `includes/media/class-scanner.php`

**Implementation**:

- Moved `get_media_count()` call outside of loops
- Total count is now calculated once and reused for progress calculations
- Applied to all batch processing methods

**Fixed Methods**:

- `get_media_total_size()`
- `find_unused_media()`
- `find_duplicate_files()`
- `find_large_files()`
- `find_missing_alt_text()`

**Benefits**:

- Eliminated redundant COUNT queries in loops
- Reduced database round-trips
- Faster processing of large media libraries

### 5. Batch Processing with Generators

**File**: `includes/Batch_Processor.php` (new)

**Implementation**:

- Created a new `Batch_Processor` utility class
- Uses PHP generators to process large datasets without loading everything into memory
- Default batch size: 100 items (configurable)
- Includes cache flushing between batches to prevent memory buildup

**Available Methods**:

- `process_posts()` - Process posts in batches using a generator
- `process_attachments()` - Process attachments in batches
- `process_table_rows()` - Process database rows in batches
- `process_comments()` - Process comments in batches
- `execute_with_progress()` - Execute callbacks with progress tracking
- `delete_posts_in_batches()` - Safely delete posts in batches
- `delete_comments_in_batches()` - Safely delete comments in batches

**Benefits**:

- Handles sites with 100k+ posts without memory exhaustion
- Prevents PHP timeout on large operations
- Automatic cache flushing between batches
- Progress tracking support built-in

## Usage Examples

### Using Batch Processor for Large Operations

```php
use WPAdminHealth\Batch_Processor;

// Process all posts in batches
foreach ( Batch_Processor::process_posts( array( 'post_type' => 'post' ), 100 ) as $batch ) {
    foreach ( $batch as $post_id ) {
        // Process each post
    }
}

// Process attachments with progress tracking
$total = Batch_Processor::get_total_count( $wpdb->posts, "post_type = 'attachment'" );
$generator = Batch_Processor::process_attachments( array(), 100 );

Batch_Processor::execute_with_progress(
    $generator,
    function( $batch ) {
        // Process batch
        return count( $batch );
    },
    $total,
    function( $progress, $processed, $total ) {
        // Update progress
        set_transient( 'my_progress', $progress, HOUR_IN_SECONDS );
    }
);
```

### Leveraging Transient Cache

```php
use WPAdminHealth\Database\Analyzer;

$analyzer = new Analyzer();

// First call - queries database and caches result
$db_size = $analyzer->get_database_size();

// Subsequent calls within 5 minutes - returns cached value
$db_size = $analyzer->get_database_size();
```

## Performance Testing

### Recommended Testing Tools

1. **Query Monitor** - For profiling database queries and identifying slow operations
2. **Chrome DevTools** - For analyzing frontend performance metrics
3. **New Relic / Blackfire** - For PHP performance profiling

### Key Metrics to Monitor

- Database query count per page load
- Total page load time
- Time to First Byte (TTFB)
- First Contentful Paint (FCP)
- Memory usage during batch operations
- PHP execution time for large operations

## Configuration

### Adjusting Cache Duration

To modify transient cache duration, update the constant in `includes/database/class-analyzer.php`:

```php
const CACHE_EXPIRATION = 5 * MINUTE_IN_SECONDS; // Change as needed
```

### Adjusting Batch Size

To modify default batch size for the Batch Processor:

```php
// When using Batch_Processor methods
Batch_Processor::process_posts( array(), 200 ); // Use 200 instead of default 100
```

### Clearing Performance Caches

To manually clear performance-related caches:

```php
// Clear database analyzer cache
delete_transient( 'wpha_db_analyzer_database_size' );
delete_transient( 'wpha_db_analyzer_table_sizes' );

// Clear dashboard stats cache
delete_transient( 'wpha_dashboard_stats' );
```

## Best Practices

1. **Monitor Query Count**: Always check Query Monitor to ensure queries remain optimized
2. **Use Batch Processing**: For operations on 1000+ items, always use the Batch_Processor
3. **Cache Wisely**: Cache expensive operations but keep cache duration reasonable
4. **Test on Large Sites**: Test performance on sites with 100k+ posts
5. **Profile Regularly**: Use profiling tools to identify new bottlenecks

## Future Optimization Opportunities

1. Implement object caching support (Redis, Memcached)
2. Add AJAX-based progressive loading for large data sets in admin UI
3. Consider implementing WP-CLI commands for bulk operations
4. Add performance monitoring hooks for tracking metrics
5. Implement lazy loading for React components
