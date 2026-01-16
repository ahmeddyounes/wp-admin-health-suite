# WP Admin Health Suite - API Documentation

This directory contains the auto-generated API documentation for the WP Admin Health Suite plugin.

## Generating Documentation

The plugin uses phpDocumentor to generate comprehensive API documentation from PHPDoc comments in the source code.

### Prerequisites

Install phpDocumentor via Composer:

```bash
composer install --dev
```

### Generate Documentation

Generate the API documentation:

```bash
composer run docs
```

Or directly with phpDocumentor:

```bash
vendor/bin/phpdoc run -c phpdoc.xml
```

### View Documentation

After generation, open `docs/api/index.html` in your web browser to browse the documentation.

### Clear Documentation

Remove generated documentation:

```bash
composer run docs:clear
```

## Documentation Structure

The generated documentation includes:

- **Namespaces**: Browse all classes by namespace
- **Packages**: Filter by package (WPAdminHealth)
- **Classes**: Detailed class documentation with:
    - Class description and @since version
    - Properties with types and descriptions
    - Methods with parameters, return types, and examples
    - WordPress hooks with @hook tags
- **Source Code**: View source code for each class
- **Graphs**: Class diagrams and dependency graphs

## PHPDoc Standards

All classes and methods follow WordPress PHPDoc standards:

- `@since 1.0.0` - Version when introduced
- `@param` - Parameter types and descriptions
- `@return` - Return type and description with array structure for complex returns
- `@hook` - WordPress action/filter hook documentation
- `@example` - Usage examples for complex methods
- `@throws` - Exception documentation (where applicable)

## Key Classes

### Core Classes

- `WPAdminHealth\Plugin` - Main plugin singleton
- `WPAdminHealth\Admin` - Admin functionality
- `WPAdminHealth\Database` - Database operations
- `WPAdminHealth\Assets` - Asset management
- `WPAdminHealth\REST_API` - REST API initialization
- `WPAdminHealth\Health_Calculator` - Health scoring system
- `WPAdminHealth\Scheduler` - WP-Cron task scheduling
- `WPAdminHealth\Settings` - Settings management
- `WPAdminHealth\Installer` - Installation and upgrade handler

### Database Module

- `WPAdminHealth\Database\Analyzer` - Database analysis
- `WPAdminHealth\Database\Optimizer` - Table optimization
- `WPAdminHealth\Database\Orphaned_Cleaner` - Orphaned data cleanup
- `WPAdminHealth\Database\Orphaned_Tables` - Orphaned table detection
- `WPAdminHealth\Database\Revisions_Manager` - Post revision management
- `WPAdminHealth\Database\Transients_Cleaner` - Transient cleanup
- `WPAdminHealth\Database\Trash_Cleaner` - Trash cleanup

### Media Module

- `WPAdminHealth\Media\Scanner` - Media library scanner
- `WPAdminHealth\Media\Safe_Delete` - Safe media deletion
- `WPAdminHealth\Media\Reference_Finder` - Media reference detection
- `WPAdminHealth\Media\Duplicate_Detector` - Duplicate file detection
- `WPAdminHealth\Media\Large_Files` - Large file analysis
- `WPAdminHealth\Media\Alt_Text_Checker` - Alt text validation
- `WPAdminHealth\Media\Exclusions` - Media exclusion rules

### Performance Module

- `WPAdminHealth\Performance\Query_Monitor` - Database query monitoring
- `WPAdminHealth\Performance\Ajax_Monitor` - AJAX request monitoring
- `WPAdminHealth\Performance\Plugin_Profiler` - Plugin performance profiling
- `WPAdminHealth\Performance\Cache_Checker` - Cache configuration checker
- `WPAdminHealth\Performance\Heartbeat_Controller` - WordPress Heartbeat control
- `WPAdminHealth\Performance\Autoload_Analyzer` - Autoload option analysis

### AI Module

- `WPAdminHealth\AI\Recommendations` - AI-powered recommendations
- `WPAdminHealth\AI\One_Click_Fix` - One-click fix automation

### REST API Controllers

- `WPAdminHealth\REST\REST_Controller` - Base REST controller
- `WPAdminHealth\REST\Activity_Controller` - Activity endpoint
- `WPAdminHealth\REST\Dashboard_Controller` - Dashboard endpoint
- `WPAdminHealth\REST\Database_Controller` - Database endpoint
- `WPAdminHealth\REST\Media_Controller` - Media endpoint
- `WPAdminHealth\REST\Performance_Controller` - Performance endpoint

## WordPress Hooks

The plugin provides 16 custom action/filter hooks for extensibility:

### Plugin Lifecycle

- `wpha_init` - After plugin initialization
- `wpha_dependencies_loaded` - After dependencies loaded
- `wpha_activate` - Before activation
- `wpha_activated` - After activation complete
- `wpha_deactivate` - Before deactivation
- `wpha_deactivated` - After deactivation complete

### Module Initialization

- `wpha_admin_init` - After admin initialization
- `wpha_database_init` - After database initialization
- `wpha_assets_init` - After assets initialization
- `wpha_rest_api_init` - After REST API initialization
- `wpha_register_rest_routes` - Custom REST routes registration

### Installer

- `wpha_upgraded` - After plugin upgrade
- `wpha_uninstalled` - After plugin uninstall
- `wpha_registered_plugin_tables` - After custom tables registered

### Scheduler

- `wpha_execute_cleanup` (filter) - Execute cleanup tasks
- `wpha_scheduler_log` - Scheduler logging

## Configuration

The phpDocumentor configuration is in `phpdoc.xml` at the project root.

Key settings:

- **Source**: `includes/` directory
- **Output**: `docs/api/`
- **Template**: Default template with graphs enabled
- **Ignores**: index.php files, node_modules, vendor, tests, assets

## Support

For issues with the documentation or to report errors:

- GitHub: https://github.com/anthropics/wp-admin-health-suite/issues
