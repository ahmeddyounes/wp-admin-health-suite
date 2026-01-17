# WP Admin Health Suite

A comprehensive suite for monitoring and maintaining WordPress admin health and performance.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://php.net)
[![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D6.0-blue.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## Description

WP Admin Health Suite is a powerful all-in-one solution for maintaining your WordPress site's health and performance. Monitor database bloat, audit media files, profile plugin performance, and get AI-powered recommendations—all from one intuitive dashboard.

## Features

### Health Score Dashboard

- Visual health score (0-100) with A-F grading
- Real-time metrics and statistics
- Recent activity timeline
- Quick action buttons for common tasks

### Database Optimization

- Clean post revisions with configurable retention
- Remove expired transients and spam/trash
- Find and clean orphaned metadata
- Optimize database tables
- Detect orphaned tables from deactivated plugins
- Scheduled automated cleanups

### Media Library Audit

- Find unused media files
- Detect duplicate images
- Identify large files for optimization
- Report missing alt text
- Safe two-step deletion with configurable recovery period (7-365 days)
- Support for ACF, Elementor, WooCommerce, and multilingual plugins (WPML, Polylang)

### Performance Monitoring

- Profile plugin performance impact
- Monitor slow database queries
- Control WordPress Heartbeat API
- Track admin AJAX requests
- Check object cache status
- Analyze autoloaded options

### AI-Powered Recommendations

- Intelligent issue prioritization
- Actionable optimization steps
- One-click fixes for safe operations
- Impact estimates before changes
- Category-based organization

### Multisite Support

- Network-wide dashboard and settings
- Per-site or network-wide scanning modes
- Shared scan results across sites
- Network admin controls

### Flexible Settings

- Granular control over all features
- Scheduled maintenance tasks
- Email notifications
- Settings export/import
- Safe mode for testing
- Debug mode for troubleshooting

### Developer Friendly

- REST API for external integrations
- Comprehensive hook system (actions and filters)
- Full documentation
- PSR-4 autoloading
- Modern React-based UI
- Dependency injection container

## Requirements

- PHP 7.4 or higher
- WordPress 6.0 or higher
- MySQL 5.6 or higher / MariaDB 10.0 or higher

## Installation

### From WordPress Admin

1. Log in to your WordPress dashboard
2. Navigate to **Plugins > Add New**
3. Search for "WP Admin Health Suite"
4. Click **Install Now**
5. Activate the plugin
6. Go to **Admin Health** in your admin menu

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/` directory
3. Unzip the file
4. Activate the plugin through the **Plugins** menu
5. Go to **Admin Health** in your admin menu

### Via Composer

```bash
composer require wp-admin-health/suite
```

## Quick Start

1. Navigate to **Admin Health > Dashboard**
2. Click **Full Scan** to analyze your site
3. Review your health score and recommendations
4. Start with the highest priority items
5. Configure scheduled maintenance in **Settings**

## Page Builder Support

WP Admin Health Suite intelligently detects media usage across popular page builders:

- Elementor
- Advanced Custom Fields (ACF)
- WooCommerce

## Safety Features

We understand your WordPress site is critical. That's why:

- All destructive actions require confirmation
- Media files go through a two-step deletion process
- Deleted media can be recovered for 30 days (configurable: 7-365 days)
- Safe mode disables all destructive operations
- All operations are logged for audit trails

## Development

### Building Assets

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Build for development
npm run build:dev

# Watch for changes
npm run watch
```

### Running Tests

```bash
# PHP unit tests (standalone, no WordPress)
composer test:standalone

# JavaScript tests
npm test
```

### Code Quality

```bash
# PHP linting (PHPCS with WordPress standards)
composer phpcs

# PHP static analysis (PHPStan)
composer phpstan

# JavaScript linting
npm run lint

# Fix linting issues
npm run lint:fix
composer phpcbf
```

## Hooks Reference

### Actions

| Hook               | Description                       |
| ------------------ | --------------------------------- |
| `wpha_init`        | Fires after plugin initialization |
| `wpha_activate`    | Fires before plugin activation    |
| `wpha_activated`   | Fires after plugin activation     |
| `wpha_deactivate`  | Fires before plugin deactivation  |
| `wpha_deactivated` | Fires after plugin deactivation   |

### Filters

| Filter                   | Description                         |
| ------------------------ | ----------------------------------- |
| `wpha_service_providers` | Modify registered service providers |

## REST API

The plugin provides a REST API under the `wp-admin-health/v1` namespace:

| Endpoint             | Method | Description                |
| -------------------- | ------ | -------------------------- |
| `/dashboard/stats`   | GET    | Get dashboard statistics   |
| `/database/analyze`  | GET    | Analyze database health    |
| `/database/cleanup`  | POST   | Execute database cleanup   |
| `/media/scan`        | POST   | Scan media library         |
| `/media/cleanup`     | POST   | Clean up unused media      |
| `/performance/stats` | GET    | Get performance statistics |
| `/recommendations`   | GET    | Get AI recommendations     |

All endpoints require authentication and the `manage_options` capability.

## FAQ

### Is it safe to use on a production site?

Yes! WP Admin Health Suite is designed with safety in mind. All destructive operations require confirmation, and media files can be recovered for 30 days. We recommend starting with Safe Mode enabled to preview changes before committing.

### Will this plugin slow down my site?

No. The plugin only loads its assets on admin pages where they're needed. Scans and optimizations are batch-processed to prevent timeouts, and results are cached to minimize database queries.

### Does it work with multisite?

Yes! WP Admin Health Suite fully supports WordPress multisite installations. Network administrators can configure network-wide settings and run scans across all sites.

### Will it detect media used in page builders?

Yes! The plugin intelligently scans content from Elementor, ACF, WooCommerce, and standard WordPress content. We're always adding support for more builders.

### Can developers extend the plugin?

Yes! The plugin provides extensive hooks and filters, plus a full REST API. Check the documentation for details on available actions and filters.

## Privacy

WP Admin Health Suite does not:

- Collect or transmit any data to external servers
- Track user behavior
- Store personal information
- Use cookies or tracking scripts

All operations are performed locally on your WordPress installation. Scan results and settings are stored in your WordPress database only.

## Support

- **Documentation**: [Wiki](https://github.com/yourusername/wp-admin-health-suite/wiki)
- **Issue Tracker**: [GitHub Issues](https://github.com/yourusername/wp-admin-health-suite/issues)
- **Support Forum**: [WordPress.org](https://wordpress.org/support/plugin/wp-admin-health-suite/)

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting pull requests.

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

Developed with care for the WordPress community.

Special thanks to:

- The WordPress core team for an amazing platform
- The open-source community for inspiration and feedback
- All contributors who help make this plugin better
