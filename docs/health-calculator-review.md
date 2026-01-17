# Health Calculator Review

## Overview

This document provides a comprehensive review of `includes/HealthCalculator.php`, which computes an overall site health score (0-100) based on weighted factors. The class is central to the plugin's health monitoring capabilities and is consumed by the `DashboardController` REST API.

## File Information

- **File**: `includes/HealthCalculator.php`
- **Namespace**: `WPAdminHealth`
- **Since**: 1.0.0 (with updates in 1.3.0 and 1.6.0)
- **Dependencies**: `ConnectionInterface`, `SettingsInterface` (optional)

---

## Architecture Analysis

### Class Structure

The `HealthCalculator` class follows good OOP principles:

1. **Dependency Injection**: Uses `ConnectionInterface` for database operations, enabling testability
2. **Optional Settings**: `SettingsInterface` is optional (`?SettingsInterface`), allowing flexible instantiation
3. **Encapsulation**: All factor calculations are private methods, exposing only public API methods
4. **Separation of Concerns**: Each health factor has its own dedicated calculation method

### Public API

| Method                                    | Purpose                                                     |
| ----------------------------------------- | ----------------------------------------------------------- |
| `calculate_overall_score($force_refresh)` | Main entry point - returns score, grade, factors, timestamp |
| `get_grade($score)`                       | Converts numeric score to letter grade                      |
| `get_factor_scores()`                     | Returns individual factor scores without caching            |
| `get_recommendations()`                   | Returns actionable recommendations based on scores          |
| `clear_cache()`                           | Clears the cached health score                              |

---

## Health Score Algorithm Review

### Weighted Factors

The health score is calculated using five factors with the following weights:

| Factor             | Weight | Purpose                                                  |
| ------------------ | ------ | -------------------------------------------------------- |
| Database Bloat     | 25%    | Trashed posts, spam comments, orphaned meta, auto-drafts |
| Plugin Performance | 25%    | Active/inactive plugin counts                            |
| Unused Media       | 20%    | Unattached media files                                   |
| Revision Count     | 15%    | Post revisions                                           |
| Transient Bloat    | 15%    | Total and expired transients                             |

**Weight Sum Verification**: 0.25 + 0.20 + 0.25 + 0.15 + 0.15 = **1.00** (correct)

### Algorithm Accuracy Assessment

#### Database Bloat Score (`calculate_database_bloat_score`)

**Logic**:

- Starts at 100 points
- Deducts for trashed posts > 100: `min(30, (count - 100) / 10)` points
- Deducts for spam comments > 50: `min(25, (count - 50) / 5)` points
- Deducts for orphaned postmeta: `min(20, count / 10)` points
- Deducts for auto-drafts > 20: `min(15, (count - 20) / 5)` points

**Maximum Deduction**: 30 + 25 + 20 + 15 = **90 points** (minimum score: 10)

**Assessment**:

- Thresholds are reasonable for typical WordPress sites
- The algorithm uses conservative caps (`min()`) to prevent extreme penalties
- Orphaned meta detection is accurate (LEFT JOIN with NULL check)

#### Unused Media Score (`calculate_unused_media_score`)

**Logic**:

- Starts at 100 points
- Deducts if unattached percentage > 20%: `min(50, (percentage - 20) * 1.5)` points
- Additional deduction if total media > 1000 AND unattached > 100: `min(30, (unattached - 100) / 20)` points

**Maximum Deduction**: 50 + 30 = **80 points** (minimum score: 20)

**Assessment**:

- Uses `post_parent = 0` to identify unattached media
- **Caveat**: Media referenced via Gutenberg blocks or meta fields may appear "unattached" but are actually in use
- The percentage-based approach is reasonable for most use cases

#### Plugin Performance Score (`calculate_plugin_performance_score`)

**Logic**:

- Starts at 100 points
- Deducts for active plugins > 20: `min(40, (count - 20) * 2)` points
- Deducts for inactive plugins > 5: `min(30, (count - 5) * 3)` points
- Deducts for total plugins > 30: `min(20, (count - 30))` points

**Maximum Deduction**: 40 + 30 + 20 = **90 points** (minimum score: 10)

**Assessment**:

- Requires WordPress admin functions (`get_plugins()`) - properly handled with `require_once`
- **Limitation**: Doesn't measure actual performance impact, only counts
- Thresholds (20 active, 5 inactive, 30 total) align with WordPress best practices

#### Revision Count Score (`calculate_revision_count_score`)

**Logic**:

- Starts at 100 points
- Calculates average revisions per post
- Deducts for average revisions > 5: `min(50, (average - 5) * 5)` points
- Deducts for total revisions > 500: `min(30, (count - 500) / 50)` points

**Maximum Deduction**: 50 + 30 = **80 points** (minimum score: 20)

**Assessment**:

- Properly excludes trashed and auto-draft posts from denominator
- Average-based approach is more nuanced than absolute counts
- Threshold of 5 revisions per post is reasonable

#### Transient Bloat Score (`calculate_transient_bloat_score`)

**Logic**:

- Starts at 100 points
- Deducts for total transients > 200: `min(40, (count - 200) / 20)` points
- Deducts for expired transients > 50: `min(40, (count - 50) / 10)` points

**Maximum Deduction**: 40 + 40 = **80 points** (minimum score: 20)

**Assessment**:

- Uses `INNER JOIN` with timeout checking for expired transients
- Properly handles prepared statements with `esc_like()`
- **Note**: Total transients includes timeout entries (which are paired)

---

## Grade Boundaries Review

```php
private $grade_thresholds = array(
    'A' => 90,
    'B' => 80,
    'C' => 70,
    'D' => 60,
    'F' => 0,
);
```

| Grade | Range  | Assessment                                |
| ----- | ------ | ----------------------------------------- |
| A     | 90-100 | Excellent health, minimal issues          |
| B     | 80-89  | Good health, minor improvements possible  |
| C     | 70-79  | Fair health, several areas need attention |
| D     | 60-69  | Poor health, significant issues           |
| F     | 0-59   | Critical health, immediate action needed  |

**Assessment**: The grade boundaries follow a standard academic grading scale and are appropriate for health assessment. The boundaries are:

- Well-spaced (10-point intervals)
- The iteration order ensures correct grade assignment (highest threshold first)
- The fallback to 'F' is defensive but rarely reached due to iteration

---

## Caching Implementation Review

### Cache Configuration

| Constant/Setting              | Value                 | Purpose           |
| ----------------------------- | --------------------- | ----------------- |
| `CACHE_KEY`                   | `'wpha_health_score'` | Transient key     |
| `CACHE_EXPIRATION`            | `HOUR_IN_SECONDS`     | Default 1 hour    |
| `health_score_cache_duration` | 1-24 hours            | User-configurable |

### Cache Behavior

1. **Cache Read** (`calculate_overall_score`):
    - Checks `$force_refresh` parameter first
    - Validates cached data is an array before returning
    - Returns cached result with all fields preserved

2. **Cache Write**:
    - Stores complete result array including timestamp
    - Uses dynamic expiration from settings (clamped to 1-24 hours)
    - Called after successful calculation

3. **Cache Invalidation**:
    - `clear_cache()` method deletes transient
    - Called by `DashboardController` after quick actions

### Settings-Driven Cache Duration

```php
private function get_cache_expiration(): int {
    if ( ! $this->settings instanceof SettingsInterface ) {
        return self::CACHE_EXPIRATION;
    }

    $hours = absint( $this->settings->get_setting( 'health_score_cache_duration', 1 ) );
    $hours = max( 1, min( 24, $hours ) );

    return $hours * HOUR_IN_SECONDS;
}
```

**Assessment**:

- Gracefully falls back to default when settings unavailable
- Input validation ensures 1-24 hour range
- Uses `absint()` for safe integer conversion

---

## SQL Query Security Analysis

### Parameterized Queries

All queries using user input or dynamic values use prepared statements:

```php
$query = $this->connection->prepare(
    "SELECT COUNT(*) FROM {$options_table}
    WHERE option_name LIKE %s",
    $this->connection->esc_like( '_transient_' ) . '%'
);
```

### Table Name Handling

Table names are retrieved via `ConnectionInterface` methods:

- `get_posts_table()`
- `get_comments_table()`
- `get_postmeta_table()`
- `get_options_table()`

**Assessment**: Table names are not user-controllable and use the trusted prefix system.

### Potential Query Patterns

| Query               | Prepared | Notes                           |
| ------------------- | -------- | ------------------------------- |
| Trashed posts count | No       | Static query, no user input     |
| Spam comments count | No       | Static query, no user input     |
| Orphaned meta count | No       | Static query, no user input     |
| Transient counts    | Yes      | Uses `%s` and `%d` placeholders |

---

## Performance Considerations

### Query Efficiency

1. **COUNT(\*) Usage**: All factor calculations use `COUNT(*)` which is optimized in MySQL
2. **JOIN Queries**:
    - Orphaned meta uses `LEFT JOIN` (potentially expensive on large sites)
    - Expired transients uses `INNER JOIN` with string manipulation

### Potential Bottlenecks

| Query              | Concern                      | Mitigation                                    |
| ------------------ | ---------------------------- | --------------------------------------------- |
| Orphaned postmeta  | `LEFT JOIN` on large tables  | Index on `post_id` helps                      |
| Expired transients | `REPLACE()` function in JOIN | Unavoidable for WordPress transient structure |
| Plugin enumeration | Filesystem I/O               | Cached by WordPress                           |

### Caching Benefits

- 1-hour default cache prevents repeated expensive queries
- Manual refresh available via `$force_refresh` parameter
- Cache invalidation on cleanup actions ensures accuracy

---

## Recommendations Feature Review

The `get_recommendations()` method provides actionable guidance:

```php
public function get_recommendations() {
    $factor_scores   = $this->get_factor_scores();
    $recommendations = array();

    if ( $factor_scores['database_bloat'] < 80 ) {
        $recommendations[] = __( 'Consider optimizing...', 'wp-admin-health-suite' );
    }
    // ... similar for other factors
}
```

**Assessment**:

- Threshold of 80 (grade B) triggers recommendations
- Each factor has specific, actionable advice
- Positive feedback when all scores are good
- Properly internationalized with `__()` function

---

## Test Coverage Gap

**Current State**: No dedicated unit tests found for `HealthCalculator`:

- `tests/**/*HealthCalculator*.php` - Not found
- `tests/**/*Calculator*.php` - Not found

**Recommended Test Cases**:

1. **Score Calculation**:
    - Verify weights sum to 1.0
    - Test each factor calculation with mock data
    - Test edge cases (empty database, maximum bloat)

2. **Grade Assignment**:
    - Test boundary values (59, 60, 69, 70, 79, 80, 89, 90, 100)
    - Test grade thresholds iteration order

3. **Caching**:
    - Verify cache read/write behavior
    - Test `force_refresh` parameter
    - Test settings-driven cache duration

4. **Recommendations**:
    - Test recommendation generation for each factor
    - Test positive feedback when all factors healthy

---

## Integration Points

### REST API (`DashboardController`)

The `HealthCalculator` is consumed by:

- `GET /wpha/v1/dashboard/health-score` - Returns full health data
- `POST /wpha/v1/dashboard/quick-action` - Clears cache after actions

### Container Registration

Registered via `CoreServiceProvider` and `SettingsServiceProvider` for dependency injection.

---

## Potential Improvements

### 1. Factor Customization

Allow administrators to adjust factor weights via settings.

### 2. Score History

Track score changes over time for trend analysis.

### 3. Media Usage Detection

Improve unused media detection by checking:

- Gutenberg block content
- Theme/plugin usage
- Custom meta fields

### 4. Performance Weighting

Consider actual plugin load times instead of just counts.

### 5. Additional Factors

Consider adding:

- Database table fragmentation
- Orphaned term relationships
- Large autoloaded options

---

## Security Considerations

| Aspect                 | Status    | Notes                                  |
| ---------------------- | --------- | -------------------------------------- |
| SQL Injection          | Protected | Prepared statements used appropriately |
| Information Disclosure | N/A       | Only counts exposed, no sensitive data |
| Access Control         | Via REST  | Controller handles permission checks   |
| Input Validation       | Good      | Cache duration clamped to valid range  |

---

## Conclusion

The `HealthCalculator` class is well-designed with:

**Strengths**:

- Clean architecture with dependency injection
- Comprehensive factor analysis with reasonable thresholds
- Proper caching with configurable duration
- Secure SQL handling
- Good separation of concerns

**Areas for Improvement**:

- Missing unit test coverage
- Media detection could be more sophisticated
- Plugin scoring based on counts rather than actual performance

**Overall Assessment**: Production-ready with minor enhancement opportunities. The algorithm provides meaningful health insights while maintaining good performance through caching.
