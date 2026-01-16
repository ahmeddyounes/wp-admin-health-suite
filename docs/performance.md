# Performance Optimization Guide

Learn how to monitor, analyze, and optimize your WordPress site's performance using WP Admin Health Suite. This guide covers all performance features and provides recommended settings for different site types.

## Table of Contents

- [Reading Plugin Impact Data](#reading-plugin-impact-data)
- [Heartbeat API Explained](#heartbeat-api-explained)
- [Query Optimization Basics](#query-optimization-basics)
- [Object Cache Benefits](#object-cache-benefits)
- [Autoload Options](#autoload-options)
- [Recommended Settings by Site Type](#recommended-settings-by-site-type)
- [Performance Score Breakdown](#performance-score-breakdown)

---

## Reading Plugin Impact Data

### What is Plugin Impact Monitoring?

Plugin Impact Monitoring measures how each installed plugin affects your site's performance across four key metrics:

- **Database Queries**: Number of database queries each plugin adds per page load
- **Memory Usage**: How much RAM each plugin consumes
- **Load Time**: Estimated page load impact in milliseconds
- **Asset Count**: Number of CSS and JavaScript files each plugin enqueues

### How to Access Plugin Impact Data

Navigate to **Admin Health > Performance** in your WordPress admin menu. The Plugin Impact section displays a table showing all active plugins sorted by their overall impact score.

![Screenshot: Plugin Impact Dashboard](./screenshots/plugin-impact.png)
_Screenshot: Plugin impact analysis showing slowest plugins first_

### Understanding the Impact Score

The impact score is calculated using a weighted formula:

```
Impact Score = (Queries × 1.0) + (Memory in MB × 0.000001) + (Time in ms × 1000) + (Assets × 5.0)
```

**What the Numbers Mean:**

- **Impact Score < 50**: Low impact - plugin is well-optimized
- **Impact Score 50-200**: Medium impact - acceptable for most sites
- **Impact Score 200-500**: High impact - may cause noticeable slowdown
- **Impact Score > 500**: Critical impact - consider alternatives or optimization

### How Plugin Impact is Measured

WP Admin Health Suite uses approximate profiling techniques to estimate plugin performance:

1. **Query Estimation**: Counts plugin-specific database options and hooks
2. **Memory Calculation**: Analyzes total PHP file size with overhead multiplier
3. **Asset Detection**: Tracks enqueued scripts and styles matching plugin slug
4. **Load Time Estimation**: Combines all metrics for time approximation

**Important Note**: These are estimates based on heuristics, not exact runtime profiling. Real impact may vary based on your specific usage and configuration.

### What to Do About High-Impact Plugins

If you find plugins with high impact scores:

1. **Evaluate Necessity**: Is this plugin essential for your site?
2. **Check for Alternatives**: Look for lighter-weight plugins with similar features
3. **Configure Properly**: Some plugins have settings to reduce their footprint
4. **Contact Support**: Report performance issues to the plugin developer
5. **Use Selectively**: Consider disabling on pages where it's not needed

### API Access

Developers can access plugin impact data programmatically:

```php
// Get plugin impact data
$response = wp_remote_get(
    rest_url('wpha/v1/performance/plugins'),
    array(
        'headers' => array(
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        )
    )
);

$data = json_decode(wp_remote_retrieve_body($response), true);
```

### Cache Behavior

Plugin impact data is cached for 24 hours to avoid constant re-measurement. To force a fresh measurement:

1. Navigate to **Admin Health > Performance**
2. Click **Refresh Plugin Impact Data**
3. Wait for the scan to complete

You can also clear the cache programmatically:

```php
$profiler = new \WP_Admin_Health\Performance\Plugin_Profiler();
$profiler->clear_cache();
```

---

## Heartbeat API Explained

### What is the WordPress Heartbeat API?

The WordPress Heartbeat API is a built-in feature that sends regular AJAX requests from your browser to the server, even when you're not actively doing anything. It powers features like:

- Post edit lock notifications
- Login expiration warnings
- Live post previews
- Dashboard activity widgets

While useful, the Heartbeat API can significantly impact server performance, especially on busy sites.

### How Heartbeat Affects Performance

By default, WordPress sends a heartbeat request every 60 seconds in the admin area. On a high-traffic site with many logged-in users, this can mean:

- **100 editors active** = 100 requests/minute = 6,000 requests/hour
- **500 editors active** = 500 requests/minute = 30,000 requests/hour

Each request consumes server resources (CPU, memory, database queries), which adds up quickly.

### Understanding Heartbeat Locations

WP Admin Health Suite lets you control the Heartbeat API in three separate locations:

1. **Admin Area**: General WordPress admin pages (Dashboard, Settings, etc.)
2. **Post Editor**: The page where you create/edit posts and pages
3. **Frontend**: Public-facing pages (rarely needed, can usually be disabled)

### Heartbeat Frequency Options

For each location, you can set the heartbeat frequency:

- **15 seconds**: Very frequent - only for real-time collaboration needs
- **30 seconds**: Frequent - good balance for active post editing
- **60 seconds**: Default WordPress setting - standard behavior
- **120 seconds**: Reduced - saves resources with minimal impact
- **Disabled**: No heartbeat - maximum performance savings

### Recommended Settings by Location

**Admin Area:**

- **Recommended**: 120 seconds
- **Why**: Most admin tasks don't require real-time updates
- **Impact**: Minimal user experience change, significant CPU savings

**Post Editor:**

- **Recommended**: 30-60 seconds
- **Why**: Prevents edit conflicts and saves drafts automatically
- **Impact**: Keeps collaborative editing features functional

**Frontend:**

- **Recommended**: Disabled
- **Why**: Frontend pages rarely need heartbeat functionality
- **Impact**: Free performance boost with no downside for most sites

### Using Heartbeat Presets

WP Admin Health Suite includes three preconfigured presets:

#### 1. Default Preset (0% CPU Savings)

```
Admin: 60 seconds
Post Editor: 60 seconds
Frontend: 60 seconds
```

Standard WordPress behavior - no optimization.

#### 2. Optimized Preset (35% CPU Savings)

```
Admin: 120 seconds
Post Editor: 30 seconds
Frontend: Disabled
```

Best balance of performance and functionality - **recommended for most sites**.

#### 3. Minimal Preset (65% CPU Savings)

```
Admin: Disabled
Post Editor: 60 seconds
Frontend: Disabled
```

Maximum performance - use only if you don't need edit locking features.

### Applying Heartbeat Settings

**Via Admin Interface:**

1. Navigate to **Admin Health > Performance > Heartbeat Control**
2. Choose a preset or customize individual locations
3. Click **Save Settings**
4. The new settings take effect immediately for new page loads

**Via REST API:**

```php
// Apply optimized preset
wp_remote_post(
    rest_url('wpha/v1/performance/heartbeat'),
    array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ),
        'body' => json_encode(array(
            'preset' => 'optimized'
        ))
    )
);
```

```php
// Custom configuration
wp_remote_post(
    rest_url('wpha/v1/performance/heartbeat'),
    array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ),
        'body' => json_encode(array(
            'admin' => 120,
            'post-editor' => 30,
            'frontend' => 0  // 0 = disabled
        ))
    )
);
```

### Monitoring Heartbeat Savings

After applying heartbeat optimizations, you can view estimated savings:

```php
$controller = new \WP_Admin_Health\Performance\Heartbeat_Controller();
$savings = $controller->calculate_cpu_savings();

echo "CPU Savings: " . $savings['percentage'] . "%\n";
echo "Requests saved per hour: " . $savings['requests_saved_per_hour'];
```

The savings calculation compares your current settings to WordPress defaults and provides:

- **Overall CPU savings percentage** (weighted by location importance)
- **Requests saved per hour** across all locations
- **Per-location breakdown** of improvements

### When NOT to Disable Heartbeat

Keep heartbeat enabled (at least at 60s) if you:

- Have multiple editors working on the same content simultaneously
- Use plugins that depend on real-time updates (e.g., live chat, notifications)
- Need session timeout warnings for security compliance
- Rely on post revision autosave for content recovery

### Troubleshooting

**Problem**: Post editor says "Connection lost. Saving has been disabled."

**Solution**: Heartbeat may be disabled or too slow in the post editor. Set it to at least 60 seconds.

**Problem**: Not seeing performance improvements after disabling heartbeat.

**Solution**: Clear your browser cache and server cache, then test with logged-out users (frontend) or after fresh login (admin).

---

## Query Optimization Basics

### What are Database Queries?

Every time someone visits your WordPress site, WordPress makes requests to the database to retrieve content, settings, and other data. These requests are called "queries." Faster queries = faster page loads.

### How Query Monitoring Works

WP Admin Health Suite monitors database queries in two ways:

1. **Integration with Query Monitor Plugin** (if installed): Full query profiling with detailed traces
2. **Standalone SAVEQUERIES Mode**: Basic query logging without additional plugins

To enable standalone monitoring, add this to your `wp-config.php`:

```php
define('SAVEQUERIES', true);
```

**Important**: Only enable SAVEQUERIES on development/staging sites, as it has a performance impact.

### Understanding Query Metrics

Navigate to **Admin Health > Performance > Query Analysis** to view:

**Summary Statistics:**

- **Total queries logged**: How many queries have been captured
- **Average query time**: Mean execution time across all queries
- **Duplicate queries**: Queries executed multiple times (inefficiency indicator)
- **Queries needing indexes**: Slow queries that could benefit from database indexes

**Slowest Queries:**

- Individual query SQL
- Execution time in milliseconds
- Number of duplicate executions
- Source component (plugin, theme, or core)

### Query Performance Thresholds

WP Admin Health Suite flags queries exceeding these thresholds:

- **< 50ms**: Fast - no action needed
- **50-100ms**: Moderate - monitor for trends
- **100-500ms**: Slow - investigate and optimize
- **> 500ms**: Critical - immediate attention required

### What Makes Queries Slow?

Common causes of slow queries:

1. **Missing Database Indexes**: Database must scan entire tables to find data
2. **Large Result Sets**: Fetching thousands of rows when only a few are needed
3. **Complex JOINs**: Queries combining multiple tables inefficiently
4. **Full Table Scans**: No WHERE clause or index to limit search
5. **Filesort Operations**: Results must be sorted in memory

### Reading EXPLAIN Analysis

WP Admin Health Suite automatically runs EXPLAIN analysis on SELECT queries to detect optimization opportunities:

**Key Indicators:**

```
type: ALL
```

**Problem**: Full table scan - no index being used
**Impact**: Query examines every row in the table
**Solution**: Add an appropriate index on the WHERE/JOIN columns

```
rows: 50000
```

**Problem**: Examining too many rows
**Impact**: Query must process all rows even if few are returned
**Solution**: Add WHERE clause or index to narrow the search

```
Extra: Using filesort
```

**Problem**: Results sorted in memory instead of using an index
**Impact**: Additional processing overhead
**Solution**: Add index on ORDER BY column

```
Extra: Using temporary
```

**Problem**: Temporary table created for query processing
**Impact**: Extra disk I/O and memory usage
**Solution**: Optimize query structure or add indexes

### Identifying Query Sources

Each logged query is tagged with its source component:

- **Core**: WordPress core files (`wp-includes/`, `wp-admin/`)
- **Plugin Name**: Specific plugin responsible for the query
- **Theme Name**: Active theme files
- **Unknown**: Unable to determine source

Use this information to:

- Identify poorly-optimized plugins
- Contact plugin developers with specific query performance issues
- Decide whether to keep or replace a plugin

### Common Query Optimization Techniques

#### 1. Eliminate Duplicate Queries

**Problem**: Same query executed multiple times per page load

**Example**:

```sql
SELECT * FROM wp_options WHERE option_name = 'siteurl' LIMIT 1
(executed 5 times)
```

**Solution**: Use WordPress object caching or fix plugin code to cache results

#### 2. Add Missing Indexes

**Problem**: Queries scanning entire tables

**Example**:

```sql
SELECT * FROM wp_posts WHERE post_type = 'product' AND post_status = 'publish'
(no index on post_type + post_status combination)
```

**Solution**: Add composite index:

```sql
ALTER TABLE wp_posts ADD INDEX idx_type_status (post_type, post_status);
```

**Warning**: Only add indexes if you understand database administration. Backup first.

#### 3. Limit Large Result Sets

**Problem**: Fetching all results when only a subset is needed

**Example**:

```sql
SELECT * FROM wp_posts WHERE post_type = 'page'
(returns 500 pages, but only 10 are displayed)
```

**Solution**: Add LIMIT clause:

```sql
SELECT * FROM wp_posts WHERE post_type = 'page' LIMIT 10
```

#### 4. Optimize Plugin Queries

**Problem**: Poorly-coded plugin making inefficient queries

**Steps**:

1. Identify the plugin in the query source
2. Check if there's a newer version with performance improvements
3. Report the issue to the plugin developer with query details
4. Consider alternative plugins if no fix is available

### Using Query Data for Decisions

**Scenario 1: Plugin Evaluation**

Before installing a new plugin, run a query analysis:

1. Take a baseline measurement
2. Install and activate the plugin
3. Compare query counts and timing
4. Decide if the performance cost is acceptable

**Scenario 2: Troubleshooting Slow Pages**

If specific pages are slow:

1. Navigate to the slow page
2. Check the query log for that request
3. Identify queries taking > 100ms
4. Trace queries back to their source component
5. Optimize or replace the problematic component

### Query Log Management

Query logs are stored in the `wp_wpha_query_log` database table with a 7-day retention policy.

**Exporting Query Logs:**

```php
$monitor = new \WP_Admin_Health\Performance\Query_Monitor();
$csv_data = $monitor->export_query_log(7, 'csv');
```

**Pruning Old Logs Manually:**

```php
$monitor = new \WP_Admin_Health\Performance\Query_Monitor();
$monitor->prune_old_logs();
```

### Best Practices

1. **Enable Object Caching**: Reduces duplicate queries automatically (see [Object Cache Benefits](#object-cache-benefits))
2. **Monitor Regularly**: Check query analysis weekly to catch trends early
3. **Test Before Deploying**: Always test plugin updates on staging to catch query regressions
4. **Use Pagination**: Don't load all results at once - paginate long lists
5. **Clean Up Old Data**: Archive or delete old posts, comments, and transients regularly

### Advanced: Query Monitoring with Query Monitor Plugin

For developers, the Query Monitor plugin provides enhanced capabilities:

- Real-time query profiling on every page load
- Detailed stack traces showing exact code execution path
- Query type categorization (SELECT, INSERT, UPDATE, DELETE)
- Duplicate query detection with highlighted differences
- Component performance breakdown

Install Query Monitor alongside WP Admin Health Suite for the most comprehensive query analysis.

---

## Object Cache Benefits

### What is Object Caching?

Object caching stores frequently-accessed data in fast memory (RAM) instead of repeatedly querying the database. This dramatically reduces database load and speeds up your site.

**Without Object Cache:**

```
User visits page → WordPress queries database → Retrieves data → Displays page
(Database queried every single time)
```

**With Object Cache:**

```
User visits page → WordPress checks cache → Data in memory → Displays page
(Database queried once, then cached for subsequent requests)
```

### Performance Impact

Enabling object caching typically provides:

- **40-70% reduction** in database queries
- **2-5x faster** page load times
- **10x more capacity** for concurrent users
- **Lower server costs** due to reduced resource usage

### Types of Object Cache

WP Admin Health Suite detects and supports four types of object caching:

#### 1. Redis (Recommended)

**Best for**: High-traffic sites, WooCommerce, membership sites

**Pros**:

- Extremely fast (in-memory storage)
- Persistent across server restarts
- Supports data structures beyond simple key-value
- Excellent for session storage

**Cons**:

- Requires Redis server installation
- Uses additional RAM

**Installation**: See [Hosting-Specific Instructions](#hosting-specific-cache-setup)

#### 2. Memcached

**Best for**: Shared hosting, multi-server environments

**Pros**:

- Very fast in-memory caching
- Low memory overhead
- Good for distributed caching across multiple servers

**Cons**:

- Non-persistent (lost on restart)
- Limited data type support
- Requires Memcached server

**Installation**: Similar to Redis, check with your hosting provider

#### 3. APCu

**Best for**: Single-server sites, limited server access

**Pros**:

- Built into PHP (no separate server needed)
- Good performance for small to medium sites
- Easy to enable

**Cons**:

- Shared with PHP opcode cache
- Limited by PHP memory
- Cleared on PHP restart

**Installation**: Enable via PHP configuration (check with host)

#### 4. File-Based Cache

**Best for**: Shared hosting without Redis/Memcached access

**Pros**:

- Works on any hosting environment
- No special server requirements
- Persistent across restarts

**Cons**:

- Slower than memory-based caching
- Can be slower than database on some hosts
- Disk I/O overhead

**Installation**: Install a file-based cache plugin (e.g., W3 Total Cache, WP Super Cache)

### Checking Your Cache Status

Navigate to **Admin Health > Performance > Object Cache** to view:

**Cache Status:**

- Whether persistent object cache is active
- Cache backend type (Redis, Memcached, APCu, File, None)
- Object cache drop-in status
- Available cache extensions on your server

**Performance Metrics:**

- Cache hit rate percentage
- Operations per second
- Average GET/SET times
- Cache effectiveness rating

### Understanding Cache Hit Rate

Cache hit rate measures how often data is found in cache vs. fetched from the database:

- **90%+ hit rate**: Excellent - cache is working very well
- **70-90% hit rate**: Good - acceptable performance
- **50-70% hit rate**: Fair - some improvement possible
- **< 50% hit rate**: Poor - investigate cache configuration

**Low hit rate causes:**

1. Cache size too small (data being evicted too quickly)
2. Short cache TTL (Time To Live)
3. High traffic variability (each user requests unique data)
4. Cache warming needed (just restarted cache server)

### Cache Performance Benchmarking

WP Admin Health Suite automatically benchmarks your cache by:

1. Writing 100 test items to cache
2. Reading those 100 items back
3. Measuring time per operation
4. Calculating operations per second

**Interpreting Results:**

- **> 5,000 ops/sec**: Excellent (Redis, Memcached)
- **1,000-5,000 ops/sec**: Good (APCu, well-configured file cache)
- **< 1,000 ops/sec**: Slow (investigate configuration)

### Hosting-Specific Cache Setup

WP Admin Health Suite provides customized instructions for popular hosting providers:

#### WP Engine

1. Contact WP Engine support to enable Redis
2. Install "Redis Object Cache" plugin from WordPress.org
3. Activate the plugin
4. Redis is automatically configured

#### Kinsta

1. Log in to MyKinsta dashboard
2. Navigate to your site → Tools
3. Click "Enable" under Redis
4. Install "Redis Object Cache" plugin
5. Activate and connect

#### Pantheon

1. Install "WP Redis" plugin
2. Activate the plugin
3. Redis is automatically detected and connected
4. No additional configuration needed

#### Flywheel

1. Contact Flywheel support to request Redis
2. Install "Redis Object Cache" plugin after approval
3. Follow Flywheel's specific configuration instructions

#### Cloudways

1. Log in to Cloudways platform
2. Select your application
3. Go to Application Settings → Application Management
4. Toggle "Redis" to enabled
5. Install "Redis Object Cache" plugin in WordPress

#### SiteGround

1. Redis available on higher-tier plans
2. Access SiteGround Site Tools
3. Go to Speed → Caching
4. Enable "Dynamic Cache"
5. Install "Redis Object Cache" plugin

#### GoDaddy

1. Redis available on managed WordPress plans
2. Contact GoDaddy support to enable
3. Install "Redis Object Cache" plugin after enabled

For other hosts, contact support to ask about Redis or Memcached availability.

### Verifying Cache is Working

After installing object cache:

1. Navigate to **Admin Health > Performance > Object Cache**
2. Check that "Persistent Cache Available" shows **Yes**
3. Verify "Cache Type" shows your installed cache (e.g., Redis)
4. Confirm hit rate is above 70% after a few hours of traffic
5. Run performance benchmark to test speed

**Via Code:**

```php
// Test cache functionality
wp_cache_set('test_key', 'test_value', 'test_group', 3600);
$result = wp_cache_get('test_key', 'test_group');

if ($result === 'test_value') {
    echo "Object cache is working!";
} else {
    echo "Object cache is not working.";
}
```

### Cache Plugins We Recommend

**For Redis:**

- [Redis Object Cache](https://wordpress.org/plugins/redis-cache/) by Till Krüss (most popular)
- [WP Redis](https://wordpress.org/plugins/wp-redis/) by Pantheon

**For Memcached:**

- [Memcached Object Cache](https://wordpress.org/plugins/memcached/) by WordPress contributors

**For APCu:**

- [APCu Object Cache](https://wordpress.org/plugins/apcu/) by Pierre Schmitz

**For File-Based:**

- [W3 Total Cache](https://wordpress.org/plugins/w3-total-cache/) (comprehensive caching solution)
- [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) (simple and reliable)

### Common Issues and Solutions

**Problem**: "Redis Object Cache" plugin shows "Not Connected"

**Solutions**:

1. Verify Redis server is running on your host
2. Check Redis host/port configuration in `wp-config.php`
3. Ensure PHP Redis extension is installed (`phpinfo()` to verify)
4. Contact hosting support if on managed hosting

**Problem**: Cache hit rate is very low (< 50%)

**Solutions**:

1. Increase cache memory allocation
2. Adjust cache TTL settings
3. Verify cache isn't being cleared too frequently
4. Check for plugins that bypass or clear cache excessively

**Problem**: Site slower after enabling cache

**Solutions**:

1. File-based cache can be slower on some hosts - try memory-based cache
2. Clear all caches and test again
3. Check for cache plugin conflicts
4. Verify cache server has adequate memory

### Best Practices

1. **Always Use Production Cache on Production**: Don't rely on development-only caching
2. **Monitor Hit Rate**: Check weekly to ensure cache is effective
3. **Set Appropriate TTLs**: Balance freshness with performance (default: 12 hours)
4. **Flush When Needed**: Clear cache after major content updates
5. **Allocate Enough Memory**: Minimum 256MB for Redis/Memcached on medium sites

### Advanced Configuration

For developers who want to fine-tune cache behavior:

**wp-config.php Redis Configuration:**

```php
// Redis server connection
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_PASSWORD', 'your-password-here'); // if authentication enabled
define('WP_REDIS_DATABASE', 0); // Redis database number

// Performance tuning
define('WP_REDIS_MAXTTL', 86400); // 24 hours max TTL
define('WP_REDIS_TIMEOUT', 1); // Connection timeout in seconds
define('WP_REDIS_READ_TIMEOUT', 1); // Read timeout in seconds
```

**Selective Cache Bypass:**

```php
// Bypass cache for specific page
if (is_page('checkout')) {
    wp_cache_delete('transient_key', 'group');
}

// Don't cache user-specific data
if (is_user_logged_in()) {
    wp_cache_set('user_data', $data, 'users', 300); // Short TTL
}
```

---

## Autoload Options

### What are Autoloaded Options?

WordPress stores site settings in the `wp_options` table. Options marked with `autoload='yes'` are loaded on **every page request**, even if they're not used on that page.

**The Problem:**

If you have too many autoloaded options, or some are very large, WordPress must:

1. Query and load all autoload options from database
2. Store them in memory
3. Do this on every single page load

This slows down your site, especially on high-traffic pages.

### How to View Autoloaded Options

Navigate to **Admin Health > Performance > Autoload Analysis** to see:

**Summary Statistics:**

- Total autoload size (KB/MB)
- Number of autoloaded options
- Average option size
- Severity assessment (Success, Warning, Critical)

**Large Options Table:**

- Option name
- Size in KB
- Source (WordPress Core, Plugin Name, Theme Name)
- Recommendation

### Understanding Autoload Size Thresholds

**Total Autoload Size:**

- **< 500 KB**: Healthy - no action needed
- **500 KB - 1 MB**: Warning - should optimize
- **> 1 MB**: Critical - significant performance impact

**Individual Option Sizes:**

- **< 10 KB**: Normal - acceptable
- **10-50 KB**: Large - consider disabling autoload
- **50-100 KB**: Very large - should not be autoloaded
- **> 100 KB**: Critical - must be moved to separate storage

### Common Sources of Large Autoload

1. **Rewrite Rules** (`rewrite_rules`): WordPress permalink structure rules, can grow large on complex sites
2. **Cron Array** (`cron`): Scheduled tasks, can accumulate if old tasks aren't cleaned
3. **Theme Modifications** (`theme_mods_*`): Theme customizer settings
4. **Plugin Settings**: Some poorly-coded plugins store large data arrays as autoloaded options
5. **Transients**: Should NEVER be autoloaded, but some plugins misconfigure them

### Identifying Problematic Options

WP Admin Health Suite automatically detects problematic patterns:

#### 1. Transients with Autoload Enabled

**Pattern**: Option name starts with `_transient_` or `_site_transient_`

**Problem**: Transients are temporary cached data - they should use `autoload='no'`

**Impact**: Clutters autoload with temporary data that doesn't need to load on every page

**Solution**: Change autoload to 'no' or convert to proper object cache usage

#### 2. Session Data with Autoload Enabled

**Pattern**: Option name contains `session`, `_session_`, or `session_tokens`

**Problem**: Session data is user-specific and temporary

**Impact**: Wastes memory loading irrelevant session data on every page

**Solution**: Sessions should use proper session storage, not wp_options

#### 3. Cache Options with Autoload Enabled

**Pattern**: Option name contains `cache` or `_cache`

**Problem**: Cached data is temporary and should use object cache or transients

**Impact**: Cache data loading on every page defeats the purpose of caching

**Solution**: Use WordPress object cache or transients with `autoload='no'`

### How to Fix Autoload Issues

#### Method 1: Using WP Admin Health Suite Interface

1. Navigate to **Admin Health > Performance > Autoload Analysis**
2. Review the "Large Options" table
3. Click "Disable Autoload" next to options that don't need to load on every page
4. Confirm the change
5. Monitor performance improvement

#### Method 2: Using REST API

```php
// Disable autoload for a specific option
wp_remote_post(
    rest_url('wpha/v1/performance/autoload'),
    array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ),
        'body' => json_encode(array(
            'option_name' => 'large_option_name',
            'autoload' => 'no'
        ))
    )
);
```

#### Method 3: Direct Database Query

**Warning**: Backup your database before running direct SQL queries.

```sql
-- View current autoload status
SELECT option_name, LENGTH(option_value) as size, autoload
FROM wp_options
WHERE option_name = 'your_option_name';

-- Change autoload status
UPDATE wp_options
SET autoload = 'no'
WHERE option_name = 'your_option_name';

-- Clear options cache after manual changes
DELETE FROM wp_options WHERE option_name LIKE '_transient_%';
```

After database changes, clear your object cache:

```php
wp_cache_delete('alloptions', 'options');
```

### Specific Option Recommendations

#### rewrite_rules (Often 10-50 KB)

**What it is**: WordPress permalink structure rules

**Should you disable autoload?**: No - WordPress core requires this on every page

**How to optimize**:

1. Keep permalink structure simple
2. Avoid excessive custom post types with complex rewrite rules
3. Flush rewrite rules after changes: **Settings > Permalinks > Save Changes**

#### cron (Can grow over time)

**What it is**: Array of scheduled WordPress tasks

**Should you disable autoload?**: No - WordPress core requires this

**How to optimize**:

1. Install [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) plugin
2. Delete old/orphaned scheduled events from deactivated plugins
3. Remove duplicate cron entries

#### theme*mods*{theme_name} (Varies)

**What it is**: Theme customizer settings (colors, layouts, etc.)

**Should you disable autoload?**: Generally no, but depends on theme

**How to optimize**:

1. Themes should not store large images or serialized data in theme mods
2. If > 50 KB, contact theme developer about optimization
3. Consider switching themes if poorly optimized

#### Plugin Settings Options (Varies)

**What it is**: Configuration settings for plugins

**Should you disable autoload?**: Depends - settings used on every page should autoload, others should not

**How to optimize**:

1. Review if the plugin settings are used on every page
2. If plugin-specific (only used on plugin admin pages), disable autoload
3. Contact plugin developer if settings are unnecessarily large

### When NOT to Disable Autoload

Keep autoload enabled for:

- **WordPress core options** (siteurl, blogname, active_plugins, etc.)
- **Settings needed on every page** (site title, timezone, permalink structure)
- **Small options** (< 1 KB) that are frequently accessed
- **Critical plugin settings** used throughout the site

### Monitoring Autoload Over Time

After optimizing autoload options:

1. Note your starting autoload size
2. Make changes to disable autoload on large, unnecessary options
3. Wait 24 hours for cache to clear
4. Re-check autoload size in **Admin Health > Performance > Autoload Analysis**
5. Monitor page load times to verify improvement

**Expected Results:**

- Reducing autoload from 1.5 MB to 500 KB can improve page load by 100-300ms
- Lower memory usage allows more concurrent users
- Reduced database query time on every page request

### Best Practices

1. **Regular Audits**: Check autoload size monthly
2. **Plugin Testing**: Before installing a new plugin, check its autoload impact
3. **Clean Deactivated Plugins**: Remove options from deactivated plugins
4. **Avoid Storing Large Data**: Never store images, logs, or large arrays in autoloaded options
5. **Use Transients Properly**: Always set transients with `autoload='no'`

### Advanced: Programmatic Autoload Management

**Creating Options Without Autoload:**

```php
// Good: Don't autoload large data
add_option('my_large_data_array', $large_array, '', 'no');

// Bad: Autoloading large data
add_option('my_large_data_array', $large_array); // defaults to autoload='yes'
```

**Updating Options to Disable Autoload:**

```php
// Get option, update it, and change autoload status
$value = get_option('option_name');
delete_option('option_name');
add_option('option_name', $value, '', 'no');

// Alternative: Direct database update
global $wpdb;
$wpdb->update(
    $wpdb->options,
    array('autoload' => 'no'),
    array('option_name' => 'option_name')
);
wp_cache_delete('alloptions', 'options');
```

**Checking Autoload Status in Code:**

```php
global $wpdb;
$autoload_status = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT autoload FROM $wpdb->options WHERE option_name = %s",
        'option_name'
    )
);

if ($autoload_status === 'yes') {
    echo "Option is autoloaded";
} else {
    echo "Option is not autoloaded";
}
```

---

## Recommended Settings by Site Type

Different types of sites have different performance priorities. Here are optimized configurations for common WordPress site types.

### Personal Blog / Small Business Site

**Characteristics:**

- 1-5 authors
- < 10,000 monthly visitors
- Simple content (posts, pages, contact form)
- No e-commerce or complex functionality

**Recommended Settings:**

| Feature                     | Setting            | Reason                             |
| --------------------------- | ------------------ | ---------------------------------- |
| **Heartbeat - Admin**       | 120 seconds        | Few simultaneous editors           |
| **Heartbeat - Post Editor** | 60 seconds         | Preserve autosave functionality    |
| **Heartbeat - Frontend**    | Disabled           | Not needed for simple sites        |
| **Object Cache**            | APCu or File-based | Low traffic doesn't require Redis  |
| **Autoload Target**         | < 500 KB           | Keep site fast with shared hosting |
| **Plugin Limit**            | 10-15 plugins      | Minimize complexity                |

**Expected Performance:**

- Page load: < 2 seconds
- Performance score: 85-95 (Grade A/B)
- Supports 50-100 concurrent visitors

### Medium Business / Magazine Site

**Characteristics:**

- 5-20 authors/editors
- 10,000-100,000 monthly visitors
- Multiple content types, categories, tags
- Some custom functionality (membership, newsletters)

**Recommended Settings:**

| Feature                     | Setting            | Reason                                       |
| --------------------------- | ------------------ | -------------------------------------------- |
| **Heartbeat - Admin**       | 120 seconds        | Multiple editors, but not constantly active  |
| **Heartbeat - Post Editor** | 30 seconds         | Active collaboration, prevent edit conflicts |
| **Heartbeat - Frontend**    | Disabled           | No logged-in frontend users                  |
| **Object Cache**            | Redis or Memcached | High query volume needs memory caching       |
| **Autoload Target**         | < 800 KB           | More plugins, but keep optimized             |
| **Plugin Limit**            | 15-25 plugins      | Balance features with performance            |

**Expected Performance:**

- Page load: < 1.5 seconds
- Performance score: 80-90 (Grade B/A)
- Supports 200-500 concurrent visitors

### WooCommerce Store

**Characteristics:**

- E-commerce with product catalog
- Shopping cart, checkout, payment processing
- Customer accounts and order history
- High database query volume

**Recommended Settings:**

| Feature                     | Setting              | Reason                            |
| --------------------------- | -------------------- | --------------------------------- |
| **Heartbeat - Admin**       | 60 seconds           | Frequent order management         |
| **Heartbeat - Post Editor** | 30 seconds           | Product editing by multiple staff |
| **Heartbeat - Frontend**    | Disabled             | Cart persists without heartbeat   |
| **Object Cache**            | **Redis (required)** | WooCommerce is query-intensive    |
| **Autoload Target**         | < 1 MB               | WooCommerce adds many options     |
| **Plugin Limit**            | 20-30 plugins        | WooCommerce + extensions          |
| **Query Optimization**      | **Critical**         | Monitor product queries closely   |

**WooCommerce-Specific Optimizations:**

1. **Enable Persistent Cart**: Stores cart in database, not sessions
2. **Disable WooCommerce Status Widget**: Remove from dashboard to reduce admin queries
3. **Limit Related Products**: Show 4 instead of default 12
4. **Use Simple Products**: Avoid variable products when possible
5. **Clean Up Old Orders**: Archive orders older than 2 years

**Expected Performance:**

- Page load: < 2 seconds (product pages)
- Checkout: < 1.5 seconds per step
- Performance score: 75-85 (Grade B/C)
- Supports 500-1,000 concurrent visitors

### Membership / Learning Management Site

**Characteristics:**

- User registration and profiles
- Restricted content access
- Course/lesson progression tracking
- High logged-in user activity

**Recommended Settings:**

| Feature                     | Setting              | Reason                                |
| --------------------------- | -------------------- | ------------------------------------- |
| **Heartbeat - Admin**       | 120 seconds          | Course creators need moderate updates |
| **Heartbeat - Post Editor** | 60 seconds           | Course content editing                |
| **Heartbeat - Frontend**    | 30 seconds           | Live course participation tracking    |
| **Object Cache**            | **Redis (required)** | User-specific data caching crucial    |
| **Autoload Target**         | < 800 KB             | Keep lean despite membership data     |
| **Plugin Limit**            | 25-35 plugins        | LMS requires many features            |
| **Session Storage**         | Redis sessions       | Don't use database for sessions       |

**Membership-Specific Optimizations:**

1. **Use Redis for Sessions**: Store PHP sessions in Redis, not database
2. **Lazy Load User Data**: Don't load full user meta on every page
3. **Optimize Membership Checks**: Cache role/capability checks
4. **Limit Course Query Results**: Paginate course lists
5. **Background Process Progress**: Use Action Scheduler for lesson completion

**Expected Performance:**

- Page load: < 2 seconds (logged-in users)
- Course pages: < 2.5 seconds
- Performance score: 75-85 (Grade B/C)
- Supports 200-500 concurrent logged-in users

### High-Traffic News / Content Site

**Characteristics:**

- Thousands to millions of monthly visitors
- Frequently updated content (multiple posts per day)
- Heavy media usage (images, videos)
- Social sharing and commenting

**Recommended Settings:**

| Feature                     | Setting                      | Reason                             |
| --------------------------- | ---------------------------- | ---------------------------------- |
| **Heartbeat - Admin**       | Disabled                     | Editors can work without real-time |
| **Heartbeat - Post Editor** | 60 seconds                   | Coordinate publishing workflow     |
| **Heartbeat - Frontend**    | Disabled                     | Anonymous visitors                 |
| **Object Cache**            | **Redis (required)**         | Essential for high traffic         |
| **Autoload Target**         | < 600 KB                     | Lean configuration for scale       |
| **Plugin Limit**            | 15-25 plugins                | Minimize overhead                  |
| **Page Cache**              | **Full page cache required** | Varnish, Nginx, or CDN             |
| **CDN**                     | **Required**                 | Cloudflare, CloudFront, etc.       |

**High-Traffic Optimizations:**

1. **Full Page Caching**: Cache entire HTML output (Varnish, Nginx FastCGI Cache)
2. **CDN for All Assets**: Images, CSS, JS served from CDN
3. **Database Read Replicas**: Separate read and write databases
4. **Lazy Load Images**: Load images as user scrolls
5. **Async Comments**: Load comments via AJAX after page load
6. **Optimize Archives**: Limit posts per archive page to 10-20

**Expected Performance:**

- Page load: < 1 second (cached)
- Performance score: 85-95 (Grade A)
- Supports 5,000-50,000+ concurrent visitors

### Multisite Network

**Characteristics:**

- Multiple WordPress sites in one installation
- Shared plugins and themes
- Central user management
- Variable traffic across sites

**Recommended Settings:**

| Feature                     | Setting                 | Reason                               |
| --------------------------- | ----------------------- | ------------------------------------ |
| **Heartbeat - Admin**       | 120 seconds             | Network admin doesn't need real-time |
| **Heartbeat - Post Editor** | 60 seconds              | Individual site editors              |
| **Heartbeat - Frontend**    | Disabled                | Unless specific sites need it        |
| **Object Cache**            | **Redis (required)**    | Shared cache across network          |
| **Autoload Target**         | < 600 KB per site       | Multiply across all sites            |
| **Plugin Limit**            | 20 network + 5 per site | Control plugin sprawl                |

**Multisite-Specific Optimizations:**

1. **Network-Activate Critical Plugins**: Reduce per-site overhead
2. **Shared Redis Database**: Configure all sites to use same Redis instance
3. **Optimize `wp_blogs` Queries**: Index site lookups if > 100 sites
4. **Limit Per-Site Options**: Enforce autoload policies network-wide
5. **Monitor Aggregate Autoload**: Total autoload = sum of all sites

**Expected Performance:**

- Varies by site size and traffic
- Network admin: < 3 seconds
- Individual sites: Follow type-specific guidelines above

---

## Performance Score Breakdown

### How the Performance Score is Calculated

WP Admin Health Suite assigns your site a performance score from 0-100 and a letter grade (A-F). The score is calculated by starting at 100 and deducting points for common performance issues.

**Scoring Formula:**

```
Base Score: 100 points

Deductions:
- Plugin count > 30: -20 points
- Plugin count 20-30: -10 points
- Plugin count 10-20: -5 points

- Autoload size > 1 MB: -15 points
- Autoload size 500 KB - 1 MB: -10 points

- No object cache: -15 points

- Slow queries > 100: -10 points
- Slow queries 50-100: -5 points

Final Score: 100 - (sum of deductions)
```

**Grade Mapping:**

| Score  | Grade | Meaning                               |
| ------ | ----- | ------------------------------------- |
| 90-100 | A     | Excellent - well-optimized site       |
| 80-89  | B     | Good - minor improvements possible    |
| 70-79  | C     | Fair - should optimize                |
| 60-69  | D     | Poor - significant issues             |
| 0-59   | F     | Critical - urgent optimization needed |

### Improving Your Performance Score

#### From F to D (Score: 50 → 65)

**Quick Wins:**

1. Deactivate unused plugins (can gain +20 points)
2. Disable autoload on 5-10 large options (can gain +10 points)
3. Reduce slow queries by enabling SAVEQUERIES and investigating (can gain +5 points)

**Time Required:** 30-60 minutes

#### From D to C (Score: 65 → 75)

**Optimizations:**

1. Install object cache (APCu or file-based) (+15 points)
2. Optimize autoload to < 500 KB (+5 points)
3. Apply Heartbeat optimizations (+indirect improvement via lower CPU usage)

**Time Required:** 1-2 hours

#### From C to B (Score: 75 → 85)

**Enhancements:**

1. Upgrade to Redis object cache if not already using (+indirect improvement via better cache hit rate)
2. Audit plugins and replace heavy plugins with lighter alternatives (+5-10 points)
3. Optimize all queries > 100ms (+5 points)

**Time Required:** 2-4 hours

#### From B to A (Score: 85 → 95)

**Fine-Tuning:**

1. Reduce plugin count to < 20 (+5 points)
2. Keep autoload < 500 KB with regular audits (+5 points)
3. Achieve 90%+ object cache hit rate (indirect improvement)
4. Eliminate all queries > 100ms (+5 points)

**Time Required:** 4-8 hours + ongoing maintenance

### Understanding Performance vs. Functionality Trade-offs

**The Plugin Dilemma:**

A lower plugin count improves your score, but plugins add features your business needs. The key is balance:

- **Essential Plugins**: Keep regardless of performance impact (security, backups, forms)
- **Nice-to-Have Plugins**: Evaluate if benefit outweighs cost
- **Redundant Plugins**: Remove plugins that duplicate core or other plugin functionality
- **Consolidated Plugins**: Replace 3-4 single-purpose plugins with one multi-purpose plugin

**Example Decision Process:**

```
Scenario: You have a slider plugin with Impact Score = 450
Question: Is this slider essential to your business?

If YES:
- Keep the plugin
- Optimize slider settings (reduce autoplay, limit slides)
- Lazy load slider content
- Accept the performance cost

If NO:
- Remove the plugin (can use CSS-only slider or static hero image)
- Gain +450 impact points
- Improve performance score
```

### Monitoring Performance Over Time

Track your performance score weekly or monthly to:

1. **Identify Regressions**: New plugin or theme causing slowdown
2. **Validate Optimizations**: Confirm that changes improved performance
3. **Set Goals**: Target grade improvements over time
4. **Report Progress**: Show stakeholders performance improvements

**Accessing Historical Performance Data:**

```php
// Get current performance stats
$response = wp_remote_get(
    rest_url('wpha/v1/performance/stats'),
    array(
        'headers' => array('X-WP-Nonce' => wp_create_nonce('wp_rest'))
    )
);

$stats = json_decode(wp_remote_retrieve_body($response), true);
echo "Current Score: " . $stats['score'] . " (Grade: " . $stats['grade'] . ")";
```

### Real-World Performance Score Examples

**Case Study 1: Small Blog**

- **Before**: 72 (Grade C)
    - 22 plugins, 850 KB autoload, no object cache
- **After**: 91 (Grade A)
    - 14 plugins, 420 KB autoload, APCu cache enabled
- **Changes**: Removed 8 unused plugins, optimized autoload, enabled APCu
- **Time Investment**: 2 hours

**Case Study 2: WooCommerce Store**

- **Before**: 58 (Grade F)
    - 38 plugins, 1.4 MB autoload, no object cache, 150 slow queries
- **After**: 81 (Grade B)
    - 28 plugins, 780 KB autoload, Redis cache, 45 slow queries
- **Changes**: Consolidated WooCommerce extensions, enabled Redis, indexed database tables
- **Time Investment**: 8 hours over 2 weeks

**Case Study 3: Membership Site**

- **Before**: 65 (Grade D)
    - 31 plugins, 920 KB autoload, file cache, 80 slow queries
- **After**: 86 (Grade B)
    - 27 plugins, 600 KB autoload, Redis cache, 35 slow queries
- **Changes**: Removed redundant membership features, upgraded to Redis, optimized user queries
- **Time Investment**: 6 hours

### Beyond the Performance Score

Remember: The performance score is a guideline, not an absolute measure of site quality.

**What the score DOES measure:**

- Common performance issues and optimizations
- Database and query efficiency
- Plugin overhead
- Cache implementation

**What the score DOESN'T measure:**

- Actual page load time for users
- Server hardware quality
- Network latency
- CDN effectiveness
- Image optimization
- CSS/JavaScript minification
- Mobile performance

**For comprehensive performance:**

1. Use WP Admin Health Suite for backend optimization
2. Test with tools like GTmetrix, Google PageSpeed Insights for frontend
3. Monitor real user experience with RUM (Real User Monitoring)
4. Load test with tools like K6 or Apache Bench

---

## Conclusion

Performance optimization is an ongoing process, not a one-time task. Use this guide as a reference for understanding and improving your WordPress site's performance.

**Next Steps:**

1. Review your current performance score at **Admin Health > Performance**
2. Apply the recommended settings for your site type
3. Monitor improvements over 1-2 weeks
4. Make incremental optimizations based on data
5. Schedule monthly performance audits

For additional help, see:

- [Getting Started Guide](./getting-started.md) - Initial setup and configuration
- [Database Cleanup Guide](./database-cleanup.md) - Database optimization strategies
- [Media Audit Guide](./media-audit.md) - Image and media optimization

Need support? Contact us or visit the [WordPress support forums](https://wordpress.org/support/plugin/wp-admin-health-suite/).
