=== WP Admin Health Suite ===
Contributors: yourname
Tags: admin, health, performance, database, optimization
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive suite for monitoring and maintaining WordPress admin health and performance.

== Description ==

WP Admin Health Suite is a powerful all-in-one solution for maintaining your WordPress site's health and performance. Monitor database bloat, audit media files, profile plugin performance, and get AI-powered recommendations—all from one intuitive dashboard.

= Key Features =

**Health Score Dashboard**

* Visual health score (0-100) with A-F grading
* Real-time metrics and statistics
* Recent activity timeline
* Quick action buttons for common tasks

**Database Optimization**

* Clean post revisions with configurable retention
* Remove expired transients and spam/trash
* Find and clean orphaned metadata
* Optimize database tables
* Detect orphaned tables from deactivated plugins
* Scheduled automated cleanups

**Media Library Audit**

* Find unused media files
* Detect duplicate images
* Identify large files for optimization
* Report missing alt text
* Safe two-step deletion with configurable recovery period (7-365 days)
* Support for ACF, Elementor, WooCommerce, and multilingual plugins

**Performance Monitoring**

* Profile plugin performance impact
* Monitor slow database queries
* Control WordPress Heartbeat API
* Track admin AJAX requests
* Check object cache status
* Analyze autoloaded options

**AI-Powered Recommendations**

* Intelligent issue prioritization
* Actionable optimization steps
* One-click fixes for safe operations
* Impact estimates before changes
* Category-based organization

**Flexible Settings**

* Granular control over all features
* Scheduled maintenance tasks
* Email notifications
* Settings export/import
* Safe mode for testing
* Debug mode for troubleshooting

**Developer Friendly**

* REST API for external integrations
* Comprehensive hook system
* Full documentation
* PSR-4 autoloading
* Modern React-based UI

= Why Choose WP Admin Health Suite? =

Unlike other optimization plugins that focus on just one aspect, WP Admin Health Suite provides a holistic approach to WordPress maintenance. Our AI-powered recommendations help you prioritize what matters most, while batch processing ensures even large sites can be optimized without timeouts.

= Page Builder Support =

WP Admin Health Suite intelligently detects media usage across popular page builders:

* Elementor
* Advanced Custom Fields (ACF)
* WooCommerce

= Multilingual Support =

The plugin integrates with multilingual plugins to ensure media detection works across all languages:

* WPML
* Polylang

= Safe by Default =

We understand your WordPress site is critical. That's why:

* All destructive actions require confirmation
* Media files go through a two-step deletion process
* Deleted media can be recovered for 30 days (configurable: 7-365 days)
* Safe mode disables all destructive operations
* All operations are logged for audit trails

= What Makes This Plugin Different? =

1. **Comprehensive Coverage** - Database, media, and performance in one plugin
2. **AI-Powered** - Smart recommendations based on your site's actual data
3. **Safe Operations** - Multiple safeguards prevent accidental data loss
4. **Modern UI** - React-based interface that's fast and intuitive
5. **Developer Friendly** - Extensive hooks and REST API for customization

== Installation ==

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to Plugins > Add New
3. Search for "WP Admin Health Suite"
4. Click "Install Now"
5. Activate the plugin
6. Go to Admin Health in your admin menu

= Manual Installation =

1. Download the plugin zip file
2. Upload to /wp-content/plugins/ directory
3. Unzip the file
4. Activate the plugin through the 'Plugins' menu
5. Go to Admin Health in your admin menu

= First Steps =

1. Navigate to Admin Health > Dashboard
2. Click "Full Scan" to analyze your site
3. Review your health score and recommendations
4. Start with the highest priority items
5. Configure scheduled maintenance in Settings

== Frequently Asked Questions ==

= Is it safe to use on a production site? =

Yes! WP Admin Health Suite is designed with safety in mind. All destructive operations require confirmation, and media files can be recovered for 30 days. We recommend starting with Safe Mode enabled to preview changes before committing.

= Will this plugin slow down my site? =

No. The plugin only loads its assets on admin pages where they're needed. Scans and optimizations are batch-processed to prevent timeouts, and results are cached to minimize database queries.

= Can I schedule automatic cleanups? =

Absolutely! Navigate to Settings > Scheduling to configure automated database cleanups, media scans, and performance checks. You can set the frequency and preferred time for each task.

= What if I accidentally delete something? =

Media files deleted through the plugin go to a special trash folder and can be recovered for 30 days (configurable from 7 to 365 days). Database cleanups are permanent, which is why we require confirmation before proceeding.

= Does it work with multisite? =

Yes! WP Admin Health Suite fully supports WordPress multisite installations. Network administrators can configure network-wide settings and run scans across all sites from the network admin dashboard.

= Will it detect media used in page builders? =

Yes! The plugin intelligently scans content from Elementor, ACF, WooCommerce, and standard WordPress content. We're always adding support for more builders.

= Can I customize what gets cleaned? =

Yes. You can configure retention settings (e.g., keep X revisions), exclude specific transient prefixes, exclude media files from deletion, and more through the Settings page.

= Is there a way to test before making changes? =

Enable Safe Mode in Settings > Advanced. This will prevent all destructive operations and show previews instead. Perfect for testing before committing changes.

= Does it require other plugins? =

No dependencies required. The plugin works standalone. However, if you have Query Monitor installed, we'll integrate with it for enhanced query analysis.

= Can developers extend the plugin? =

Yes! The plugin provides extensive hooks and filters, plus a full REST API. Check the documentation for details on available actions and filters.

== Screenshots ==

1. Health Score Dashboard - View your site's overall health at a glance
2. Database Health - Comprehensive database analysis and cleanup tools
3. Media Audit - Find unused, duplicate, and unoptimized media files
4. Performance Monitoring - Profile plugin impact and optimize settings
5. AI Recommendations - Smart, prioritized optimization suggestions
6. Settings Page - Granular control over all features

== Changelog ==

= 1.0.0 - 2026-01-07 =
* Initial release
* Health score dashboard with real-time metrics
* Database optimization (revisions, transients, orphaned data)
* Media library audit (unused, duplicates, large files)
* Performance monitoring (plugins, queries, heartbeat)
* AI-powered recommendations engine
* One-click fixes for safe operations
* Multisite network support
* Comprehensive settings with scheduling
* REST API with rate limiting
* Full internationalization support
* Developer hooks and filters
* Complete documentation

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Admin Health Suite. Comprehensive WordPress health monitoring and optimization.

== Privacy Policy ==

WP Admin Health Suite does not:

* Collect or transmit any data to external servers
* Track user behavior
* Store personal information
* Use cookies or tracking scripts

All operations are performed locally on your WordPress installation. Scan results and settings are stored in your WordPress database only.

== Support ==

For support, please visit:

* Documentation: https://github.com/yourusername/wp-admin-health-suite/wiki
* Issue Tracker: https://github.com/yourusername/wp-admin-health-suite/issues
* Support Forum: https://wordpress.org/support/plugin/wp-admin-health-suite/

== Credits ==

Developed with care for the WordPress community.

Special thanks to:

* The WordPress core team for an amazing platform
* The open-source community for inspiration and feedback
* All contributors who help make this plugin better

== Roadmap ==

Upcoming features in future versions:

* AI-powered alt text generation
* Advanced performance profiling
* Additional page builder integrations (Divi, Oxygen, Beaver Builder)
* White-label capabilities
* Pro version with premium features
* WP-CLI commands for automation
* Export reports as PDF

Have a feature request? Let us know on GitHub!
