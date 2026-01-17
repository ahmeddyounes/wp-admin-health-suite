# Unit Test Coverage Gap Analysis

**Generated:** 2026-01-17
**Scope:** Database, Media, Performance modules in `tests/unit/`

## Executive Summary

This document identifies untested code paths and missing test cases across critical plugin components. The analysis reveals:

- **Database Module:** Good coverage via `DatabaseAnalyzerTest.php` (640 lines)
- **Media Module:** Partial coverage via `MediaScannerTest.php` and `LargeFilesTest.php`
- **Performance Module:** **No dedicated tests exist**

---

## 1. Database Module

### 1.1 Existing Test Coverage

**File:** `tests/unit/DatabaseAnalyzerTest.php`

**Classes Tested:**
| Class | Status | Coverage Level |
|-------|--------|----------------|
| `Database\Analyzer` | Tested | High |
| `Database\RevisionsManager` | Tested | High |
| `Database\TransientsCleaner` | Tested | High |
| `Database\OrphanedCleaner` | Tested | High |
| `Database\Optimizer` | Tested | Medium |

**Well-Covered Functionality:**

- Revision counting and deletion
- Transient cleanup (expired transients, exclusion patterns)
- Orphaned postmeta/commentmeta/termmeta detection and deletion
- Table optimization (single table and bulk)
- Edge cases (no revisions, no expired transients)

### 1.2 Untested Classes

| Class                                | Priority | Missing Tests                                 |
| ------------------------------------ | -------- | --------------------------------------------- |
| `Database\TrashCleaner`              | **HIGH** | Zero tests                                    |
| `Database\OrphanedTables`            | **HIGH** | Zero tests                                    |
| `Database\WpdbConnection`            | Medium   | Integration tests exist in `unit-standalone/` |
| `Database\Tasks\DatabaseCleanupTask` | Low      | Background task wrapper                       |

### 1.3 Missing Test Cases for TrashCleaner

**Critical methods to test:**

```
- get_trashed_posts(array $post_types)
- count_trashed_posts()
- count_spam_comments()
- count_trashed_comments()
- delete_trashed_posts(array $post_types, int $older_than_days)
- delete_spam_comments(int $older_than_days)
- delete_trashed_comments(int $older_than_days)
- empty_all_trash()
```

**Specific test scenarios needed:**

1. `test_count_trashed_posts_returns_correct_count`
2. `test_count_spam_comments_returns_correct_count`
3. `test_count_trashed_comments_returns_correct_count`
4. `test_get_trashed_posts_filters_by_post_type`
5. `test_get_trashed_posts_returns_all_types_when_empty_array`
6. `test_delete_trashed_posts_with_age_filter`
7. `test_delete_trashed_posts_without_age_filter`
8. `test_delete_spam_comments_with_age_filter`
9. `test_delete_trashed_comments_respects_age_filter`
10. `test_empty_all_trash_deletes_posts_and_comments`
11. `test_batch_processing_handles_large_datasets`

### 1.4 Missing Test Cases for OrphanedTables

**Methods to test:**

```
- find_orphaned_tables()
- get_orphaned_table_count()
- get_orphaned_table_size()
- is_core_table(string $table_name)
- is_known_plugin_table(string $table_name)
- drop_orphaned_table(string $table_name)
```

**Specific test scenarios needed:**

1. `test_find_orphaned_tables_excludes_wp_core_tables`
2. `test_find_orphaned_tables_identifies_orphaned_plugin_tables`
3. `test_is_core_table_recognizes_all_wp_tables`
4. `test_is_known_plugin_table_detects_common_plugins`
5. `test_drop_orphaned_table_requires_valid_table_name`
6. `test_drop_orphaned_table_refuses_core_tables`
7. `test_get_orphaned_table_size_calculates_correctly`

---

## 2. Media Module

### 2.1 Existing Test Coverage

**Files:**

- `tests/unit/MediaScannerTest.php` (721 lines)
- `tests/unit/LargeFilesTest.php` (563 lines)

**Classes Tested:**
| Class | Status | Coverage Level |
|-------|--------|----------------|
| `Media\Scanner` | Tested | High |
| `Media\Exclusions` | Tested | High |
| `Media\SafeDelete` | Tested | High |
| `Media\LargeFiles` | Tested | High |

**Well-Covered Functionality:**

- Unused media detection (post content, featured images, postmeta, Elementor, options)
- Duplicate file detection
- Large file threshold detection
- Exclusion management (add, remove, bulk operations)
- Safe delete workflow (prepare, restore, permanent delete)
- Missing alt text detection
- Size distribution analysis

### 2.2 Untested Classes

| Class                       | Priority | Missing Tests           |
| --------------------------- | -------- | ----------------------- |
| `Media\DuplicateDetector`   | **HIGH** | Dedicated tests missing |
| `Media\AltTextChecker`      | **HIGH** | Dedicated tests missing |
| `Media\ReferenceFinder`     | Medium   | Utility class           |
| `Media\Tasks\MediaScanTask` | Low      | Background task wrapper |

### 2.3 Missing Test Cases for DuplicateDetector

**Critical methods to test:**

```
- find_duplicates(array $options)
- get_duplicate_groups()
- get_potential_savings()
- find_duplicates_by_hash() [private, test via public methods]
- find_duplicates_by_pattern() [private]
- find_duplicates_by_dimensions() [private]
```

**Specific test scenarios needed:**

1. `test_find_duplicates_by_hash_detects_exact_copies`
2. `test_find_duplicates_by_pattern_detects_wp_numbered_files`
3. `test_find_duplicates_by_pattern_detects_scaled_images`
4. `test_find_duplicates_by_dimensions_catches_recompressed_images`
5. `test_get_duplicate_groups_returns_correct_structure`
6. `test_get_duplicate_groups_respects_exclusions`
7. `test_determine_original_uses_oldest_upload_date`
8. `test_potential_savings_includes_thumbnails`
9. `test_memory_limit_handling_clears_caches`
10. `test_generator_based_iteration_handles_large_libraries`
11. `test_exclude_thumbnails_filters_correctly`
12. `test_extract_base_filename_handles_all_patterns`

### 2.4 Missing Test Cases for AltTextChecker

**Critical methods to test:**

```
- find_missing_alt_text(int $limit)
- is_decorative(int $attachment_id)
- set_decorative(int $attachment_id, bool $decorative)
- get_alt_text(int $attachment_id)
- get_alt_text_by_language(int $attachment_id)
- get_alt_text_coverage()
- get_accessibility_report()
- bulk_suggest_alt_text(array $attachment_ids)
```

**Specific test scenarios needed:**

1. `test_find_missing_alt_text_excludes_decorative_images`
2. `test_find_missing_alt_text_respects_limit`
3. `test_set_decorative_marks_image_correctly`
4. `test_get_alt_text_with_multilingual_support`
5. `test_get_alt_text_coverage_excludes_decorative_from_total`
6. `test_get_accessibility_report_determines_compliance_level`
7. `test_bulk_suggest_alt_text_generates_from_filename`
8. `test_bulk_suggest_alt_text_prefers_title_over_filename`
9. `test_generate_alt_from_filename_cleans_slugs`
10. `test_compliance_level_critical_below_70_percent`
11. `test_compliance_level_excellent_at_100_percent`

---

## 3. Performance Module

### 3.1 Existing Test Coverage

**Status:** **NO DEDICATED TESTS EXIST**

This is the most critical gap in test coverage.

### 3.2 Untested Classes

| Class                                    | Priority     | Missing Tests           |
| ---------------------------------------- | ------------ | ----------------------- |
| `Performance\AutoloadAnalyzer`           | **CRITICAL** | Zero tests              |
| `Performance\CacheChecker`               | **CRITICAL** | Zero tests              |
| `Performance\PluginProfiler`             | **CRITICAL** | Zero tests              |
| `Performance\QueryMonitor`               | **HIGH**     | Zero tests              |
| `Performance\AjaxMonitor`                | **HIGH**     | Zero tests              |
| `Performance\HeartbeatController`        | Medium       | Zero tests              |
| `Performance\Tasks\PerformanceCheckTask` | Low          | Background task wrapper |

### 3.3 Required Test File: AutoloadAnalyzerTest.php

**Critical methods to test:**

```
- get_autoloaded_options()
- get_autoload_size()
- find_large_autoloads(?int $threshold)
- recommend_autoload_changes()
- change_autoload_status(string $option_name, string $new_autoload)
- detect_option_source(string $option_name) [private]
- identify_problematic_options(array $options) [private]
```

**Specific test scenarios needed:**

1. `test_get_autoloaded_options_returns_all_autoloaded`
2. `test_get_autoload_size_calculates_totals_correctly`
3. `test_find_large_autoloads_uses_default_threshold`
4. `test_find_large_autoloads_uses_custom_threshold`
5. `test_recommend_autoload_changes_critical_over_1mb`
6. `test_recommend_autoload_changes_warning_over_500kb`
7. `test_recommend_autoload_changes_success_under_500kb`
8. `test_change_autoload_status_validates_input`
9. `test_change_autoload_status_clears_cache`
10. `test_detect_option_source_identifies_core_options`
11. `test_detect_option_source_identifies_plugin_options`
12. `test_identify_problematic_options_flags_transients`
13. `test_identify_problematic_options_flags_sessions`
14. `test_identify_problematic_options_flags_cache_data`

### 3.4 Required Test File: CacheCheckerTest.php

**Critical methods to test:**

```
- is_persistent_cache_available()
- get_cache_status()
- test_cache_performance()
- get_cache_recommendations()
- detect_cache_type() [private]
- detect_cache_backend() [private]
- detect_hosting_environment() [private]
- detect_page_cache() [private]
- detect_caching_plugins() [private]
```

**Specific test scenarios needed:**

1. `test_is_persistent_cache_available_detects_ext_object_cache`
2. `test_is_persistent_cache_available_detects_dropin`
3. `test_get_cache_status_returns_complete_structure`
4. `test_test_cache_performance_measures_set_operations`
5. `test_test_cache_performance_measures_get_operations`
6. `test_test_cache_performance_calculates_hit_rate`
7. `test_get_cache_recommendations_when_no_cache`
8. `test_get_cache_recommendations_when_cache_active`
9. `test_detect_cache_backend_identifies_redis`
10. `test_detect_cache_backend_identifies_memcached`
11. `test_detect_cache_backend_identifies_apcu`
12. `test_detect_hosting_environment_identifies_wpengine`
13. `test_detect_hosting_environment_identifies_kinsta`
14. `test_detect_page_cache_plugins_detection`
15. `test_detect_server_level_cache_varnish`

### 3.5 Required Test File: PluginProfilerTest.php

**Critical methods to test:**

```
- measure_plugin_impact()
- get_slowest_plugins(int $limit)
- get_plugin_memory_usage()
- get_plugin_query_counts()
- get_asset_counts_by_plugin()
- clear_cache()
- calculate_impact_score(array $measurement) [private]
```

**Specific test scenarios needed:**

1. `test_measure_plugin_impact_returns_cached_results`
2. `test_measure_plugin_impact_measures_all_active_plugins`
3. `test_measure_plugin_impact_handles_no_plugins`
4. `test_get_slowest_plugins_respects_limit`
5. `test_get_slowest_plugins_sorted_by_impact_score`
6. `test_get_plugin_memory_usage_returns_formatted_sizes`
7. `test_get_plugin_query_counts_sorted_by_count`
8. `test_get_asset_counts_by_plugin_counts_scripts_and_styles`
9. `test_calculate_impact_score_weights_correctly`
10. `test_clear_cache_deletes_transient`
11. `test_estimate_plugin_queries_counts_options`
12. `test_estimate_plugin_memory_handles_single_file_plugins`
13. `test_estimate_plugin_memory_handles_directory_plugins`
14. `test_max_files_per_plugin_prevents_resource_exhaustion`

---

## 4. Priority Matrix

| Priority | Module      | Class               | Test File Needed                              |
| -------- | ----------- | ------------------- | --------------------------------------------- |
| 1        | Performance | AutoloadAnalyzer    | `tests/unit/AutoloadAnalyzerTest.php`         |
| 2        | Performance | CacheChecker        | `tests/unit/CacheCheckerTest.php`             |
| 3        | Performance | PluginProfiler      | `tests/unit/PluginProfilerTest.php`           |
| 4        | Database    | TrashCleaner        | Add to `DatabaseAnalyzerTest.php` or new file |
| 5        | Database    | OrphanedTables      | Add to `DatabaseAnalyzerTest.php` or new file |
| 6        | Media       | DuplicateDetector   | `tests/unit/DuplicateDetectorTest.php`        |
| 7        | Media       | AltTextChecker      | `tests/unit/AltTextCheckerTest.php`           |
| 8        | Performance | QueryMonitor        | `tests/unit/QueryMonitorTest.php`             |
| 9        | Performance | AjaxMonitor         | `tests/unit/AjaxMonitorTest.php`              |
| 10       | Performance | HeartbeatController | `tests/unit/HeartbeatControllerTest.php`      |

---

## 5. Estimated Test Implementation Effort

| Test File             | Est. Tests | Complexity | Notes                          |
| --------------------- | ---------- | ---------- | ------------------------------ |
| AutoloadAnalyzerTest  | 14         | Medium     | Requires mock options table    |
| CacheCheckerTest      | 15         | High       | Requires mock cache backends   |
| PluginProfilerTest    | 14         | High       | Requires mock plugin files     |
| TrashCleanerTest      | 11         | Low        | Straightforward CRUD           |
| OrphanedTablesTest    | 7          | Medium     | Requires careful table mocking |
| DuplicateDetectorTest | 12         | High       | Requires test file creation    |
| AltTextCheckerTest    | 11         | Medium     | Requires multilingual mocking  |

**Total estimated new tests:** ~84 test methods

---

## 6. Code Paths Not Covered by Any Tests

### 6.1 Error Handling Paths

1. **TrashCleaner:** `delete_trashed_posts()` when `wp_delete_post()` returns false
2. **DuplicateDetector:** Memory limit exceeded during hash operations
3. **AutoloadAnalyzer:** `change_autoload_status()` database update failure
4. **CacheChecker:** Cache backend detection fallbacks

### 6.2 Edge Cases

1. **TrashCleaner:** Large batch processing (>100 items)
2. **DuplicateDetector:** Files with same size but different content
3. **AltTextChecker:** Multilingual fallback when no translation has alt text
4. **PluginProfiler:** Single-file plugins vs directory-based plugins

### 6.3 Integration Points

1. **AltTextChecker:** WPML/Polylang integration
2. **CacheChecker:** Various hosting environment detection
3. **PluginProfiler:** Hook registration counting

---

## 7. Recommendations

### Immediate Actions (Critical)

1. Create `tests/unit/Performance/` directory
2. Implement `AutoloadAnalyzerTest.php` - core performance feature
3. Implement `CacheCheckerTest.php` - essential for cache recommendations

### Short-Term Actions (High Priority)

4. Add TrashCleaner tests to Database module
5. Implement `DuplicateDetectorTest.php` with test file fixtures
6. Implement `AltTextCheckerTest.php` with multilingual mocking

### Medium-Term Actions

7. Implement OrphanedTables tests
8. Add PluginProfiler tests with mock plugins
9. Consider integration tests for hosting environment detection

### Test Infrastructure Improvements

10. Create shared test fixtures for media files
11. Add mock classes for ConnectionInterface variants
12. Consider PHPUnit code coverage reporting integration

---

## 8. Integration Test Review

**Generated:** 2026-01-17
**Scope:** REST API integration tests in `tests/integration/`

### 8.1 Existing Integration Test Coverage

**File:** `tests/integration/RestApiTest.php`

The current integration test file provides foundational REST API testing with the following coverage:

| Test Category            | Status    | Notes                                  |
| ------------------------ | --------- | -------------------------------------- |
| Authentication           | ✅ Tested | Tests unauthenticated access rejection |
| Permission checks        | ✅ Tested | Tests capability requirements          |
| Nonce verification       | ✅ Tested | Tests missing and invalid nonces       |
| Rate limiting            | ✅ Tested | Tests rate limit enforcement           |
| Basic endpoint responses | ✅ Tested | Tests successful GET requests          |

**Well-Covered Functionality:**

- REST API permission callback verification
- Nonce verification via X-WP-Nonce header
- Rate limiting with transient-based tracking
- Safe mode preview responses
- Basic CRUD response formatting

### 8.2 REST Controller Endpoint Gaps

The following REST controllers have endpoints that need dedicated integration tests:

#### 8.2.1 DatabaseController Endpoints (Partial Coverage)

| Endpoint                       | Method | Current Coverage | Priority     |
| ------------------------------ | ------ | ---------------- | ------------ |
| `/wpha/v1/database/stats`      | GET    | ✅ Basic         | Medium       |
| `/wpha/v1/database/revisions`  | GET    | ⚠️ Minimal       | High         |
| `/wpha/v1/database/transients` | GET    | ⚠️ Minimal       | Medium       |
| `/wpha/v1/database/orphaned`   | GET    | ⚠️ Minimal       | Medium       |
| `/wpha/v1/database/clean`      | POST   | ❌ None          | **Critical** |
| `/wpha/v1/database/optimize`   | POST   | ❌ None          | **Critical** |

**Missing Test Scenarios for DatabaseController:**

1. `test_clean_revisions_with_keep_count_option`
2. `test_clean_revisions_safe_mode_preview_only`
3. `test_clean_transients_expired_only_flag`
4. `test_clean_transients_exclude_patterns`
5. `test_clean_spam_with_age_filter`
6. `test_clean_trash_by_post_type`
7. `test_clean_orphaned_selective_types`
8. `test_optimize_specific_tables`
9. `test_optimize_all_tables`
10. `test_clean_returns_activity_log_entry`

#### 8.2.2 MediaController Endpoints (Minimal Coverage)

| Endpoint                     | Method          | Current Coverage | Priority     |
| ---------------------------- | --------------- | ---------------- | ------------ |
| `/wpha/v1/media/scan`        | GET             | ⚠️ Minimal       | High         |
| `/wpha/v1/media/unused`      | GET             | ❌ None          | High         |
| `/wpha/v1/media/duplicates`  | GET             | ❌ None          | High         |
| `/wpha/v1/media/large-files` | GET             | ❌ None          | Medium       |
| `/wpha/v1/media/delete`      | POST            | ❌ None          | **Critical** |
| `/wpha/v1/media/restore`     | POST            | ❌ None          | **Critical** |
| `/wpha/v1/media/exclusions`  | GET/POST/DELETE | ❌ None          | High         |

**Missing Test Scenarios for MediaController:**

1. `test_scan_returns_correct_counts`
2. `test_unused_media_detection_excludes_featured_images`
3. `test_unused_media_respects_exclusion_list`
4. `test_duplicates_detection_by_hash`
5. `test_large_files_threshold_filter`
6. `test_delete_requires_confirmation`
7. `test_delete_safe_mode_creates_backup`
8. `test_restore_from_trash`
9. `test_exclusion_crud_operations`
10. `test_bulk_operations_with_batch_limits`

#### 8.2.3 PerformanceController Endpoints (No Coverage)

| Endpoint                        | Method | Current Coverage | Priority     |
| ------------------------------- | ------ | ---------------- | ------------ |
| `/wpha/v1/performance/overview` | GET    | ❌ None          | **Critical** |
| `/wpha/v1/performance/autoload` | GET    | ❌ None          | **Critical** |
| `/wpha/v1/performance/cache`    | GET    | ❌ None          | High         |
| `/wpha/v1/performance/plugins`  | GET    | ❌ None          | High         |
| `/wpha/v1/performance/queries`  | GET    | ❌ None          | Medium       |

**Missing Test Scenarios for PerformanceController:**

1. `test_overview_returns_all_metrics`
2. `test_autoload_size_calculation`
3. `test_autoload_recommendations`
4. `test_cache_status_detection`
5. `test_cache_performance_benchmark`
6. `test_plugin_impact_scoring`
7. `test_slowest_plugins_ordering`
8. `test_query_monitoring_data`

#### 8.2.4 DashboardController Endpoints (Partial Coverage)

| Endpoint                             | Method | Current Coverage | Priority |
| ------------------------------------ | ------ | ---------------- | -------- |
| `/wpha/v1/dashboard/health`          | GET    | ✅ Basic         | Low      |
| `/wpha/v1/dashboard/recommendations` | GET    | ⚠️ Minimal       | Medium   |
| `/wpha/v1/dashboard/recent-activity` | GET    | ⚠️ Minimal       | Low      |

#### 8.2.5 ActivityController Endpoints (No Coverage)

| Endpoint                   | Method | Current Coverage | Priority |
| -------------------------- | ------ | ---------------- | -------- |
| `/wpha/v1/activity`        | GET    | ❌ None          | Medium   |
| `/wpha/v1/activity/export` | GET    | ❌ None          | Low      |

### 8.3 Testing Patterns Assessment

**Current Strengths:**

1. Proper use of `WP_UnitTestCase` base class
2. Correct factory pattern for creating test users
3. Proper nonce generation with `wp_create_nonce('wp_rest')`
4. Good isolation between test methods

**Areas for Improvement:**

1. **Mock Injection:** Tests should inject mock dependencies into controllers rather than relying on real database operations
2. **Response Structure Verification:** Tests should validate complete response structures, not just success flags
3. **Error Path Coverage:** Need tests for error conditions (invalid parameters, database failures)
4. **Pagination Testing:** Collection endpoints need pagination boundary tests
5. **Safe Mode Testing:** Comprehensive tests for preview-only behavior in safe mode

### 8.4 Missing Integration Test Files

The following new test files should be created:

| File                                             | Priority     | Est. Tests | Notes                           |
| ------------------------------------------------ | ------------ | ---------- | ------------------------------- |
| `tests/integration/DatabaseCleanupTest.php`      | **Critical** | 15         | POST endpoint testing           |
| `tests/integration/MediaOperationsTest.php`      | **Critical** | 12         | Delete/restore workflows        |
| `tests/integration/PerformanceEndpointsTest.php` | **Critical** | 10         | New module coverage             |
| `tests/integration/RateLimitingTest.php`         | High         | 8          | Edge cases, concurrent requests |
| `tests/integration/SafeModeTest.php`             | High         | 10         | Preview-only behavior           |
| `tests/integration/ActivityLoggingTest.php`      | Medium       | 6          | Audit trail verification        |

### 8.5 Integration Test Recommendations

#### Immediate Actions (Critical)

1. **Create `DatabaseCleanupTest.php`**
    - Test all POST cleanup operations
    - Verify activity logging
    - Test safe mode preview behavior

2. **Create `MediaOperationsTest.php`**
    - Test delete workflow with confirmation
    - Test restore from trash
    - Test exclusion management

3. **Create `PerformanceEndpointsTest.php`**
    - Cover all performance endpoints
    - Test with mocked cache backends
    - Test autoload recommendations

#### Short-Term Actions (High Priority)

4. **Enhance `RestApiTest.php`**
    - Add response structure assertions
    - Add pagination boundary tests
    - Add error path tests

5. **Create `RateLimitingTest.php`**
    - Test lock acquisition edge cases
    - Test rate limit reset timing
    - Test external object cache path

#### Test Infrastructure Improvements

6. **Create REST API Test Traits**
    - `trait HasRestApiAssertions` for common response checks
    - `trait CreatesTestMedia` for media endpoint tests
    - `trait MocksDatabaseServices` for isolation

7. **Add PHPUnit Data Providers**
    - Parameterized tests for cleanup types
    - Parameterized tests for permission levels

### 8.6 Estimated Integration Test Effort

| Test File                | Est. Tests | Complexity | Dependencies        |
| ------------------------ | ---------- | ---------- | ------------------- |
| DatabaseCleanupTest      | 15         | Medium     | Database fixtures   |
| MediaOperationsTest      | 12         | High       | Media file fixtures |
| PerformanceEndpointsTest | 10         | High       | Mock cache backends |
| RateLimitingTest         | 8          | Medium     | Timing-sensitive    |
| SafeModeTest             | 10         | Low        | Existing fixtures   |
| ActivityLoggingTest      | 6          | Low        | Database table      |

**Total estimated new integration tests:** ~61 test methods

---

## 9. Combined Test Priority Matrix

| Priority | Type        | Module      | Focus Area                   | Est. Tests |
| -------- | ----------- | ----------- | ---------------------------- | ---------- |
| 1        | Integration | REST API    | DatabaseCleanupTest.php      | 15         |
| 2        | Integration | REST API    | MediaOperationsTest.php      | 12         |
| 3        | Integration | REST API    | PerformanceEndpointsTest.php | 10         |
| 4        | Unit        | Performance | AutoloadAnalyzerTest.php     | 14         |
| 5        | Unit        | Performance | CacheCheckerTest.php         | 15         |
| 6        | Unit        | Performance | PluginProfilerTest.php       | 14         |
| 7        | Integration | REST API    | SafeModeTest.php             | 10         |
| 8        | Unit        | Database    | TrashCleanerTest.php         | 11         |
| 9        | Unit        | Database    | OrphanedTablesTest.php       | 7          |
| 10       | Unit        | Media       | DuplicateDetectorTest.php    | 12         |

---

## 10. Conclusion

The test suite has two major gaps requiring attention:

1. **Integration Tests:** REST API endpoint testing is minimal, with critical POST operations (cleanup, delete, optimize) having zero coverage. The `tests/integration/RestApiTest.php` provides good foundational tests for authentication and permissions but needs expansion for actual business logic testing.

2. **Unit Tests:** The Performance module has zero dedicated tests, while Database and Media modules have partial coverage gaps for TrashCleaner, OrphanedTables, DuplicateDetector, and AltTextChecker classes.

**Total estimated new tests needed:**

- Unit tests: ~84 test methods
- Integration tests: ~61 test methods
- **Grand total: ~145 new test methods**

Priority should be given to:

1. Integration tests for POST/DELETE operations (data modification endpoints)
2. Unit tests for Performance module (zero coverage)
3. Safe mode integration tests (critical safety feature)

Implementing these tests would significantly improve code quality, enable safer refactoring, and provide confidence in the plugin's reliability for production deployments.
