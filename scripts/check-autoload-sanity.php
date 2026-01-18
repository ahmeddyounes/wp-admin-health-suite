#!/usr/bin/env php
<?php
/**
 * Autoload Sanity Check Script
 *
 * Verifies that key plugin classes can be autoloaded correctly.
 * This script is designed to run on Linux CI where the filesystem is case-sensitive,
 * catching autoload/casing issues that developers on macOS might miss.
 *
 * Usage: php scripts/check-autoload-sanity.php
 *
 * Exit codes:
 *   0 - All classes loaded successfully
 *   1 - One or more classes failed to load
 *
 * @package WPAdminHealth
 * @since 1.0.0
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI script.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI script.

declare(strict_types=1);

/**
 * Simple output helpers.
 */
function output_success( string $message ): void {
	echo "\033[32m✓\033[0m {$message}\n";
}

function output_error( string $message ): void {
	echo "\033[31m✗\033[0m {$message}\n";
}

function output_info( string $message ): void {
	echo "\033[34mℹ\033[0m {$message}\n";
}

/**
 * Define WordPress stubs needed for autoloading.
 * These are minimal stubs just to allow class loading without a full WordPress environment.
 */
function setup_wordpress_stubs(): void {
	// Define ABSPATH so the plugin files don't exit early.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}

	// Define plugin constants.
	define( 'WP_ADMIN_HEALTH_VERSION', '1.0.0' );
	define( 'WP_ADMIN_HEALTH_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
	define( 'WP_ADMIN_HEALTH_PLUGIN_URL', 'http://localhost/wp-content/plugins/wp-admin-health-suite/' );
	define( 'WP_ADMIN_HEALTH_PLUGIN_BASENAME', 'wp-admin-health-suite/wp-admin-health-suite.php' );

	// Time constants used by some classes.
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}
	if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
		define( 'WEEK_IN_SECONDS', 604800 );
	}
}

/**
 * Define WordPress core class stubs.
 * These minimal stubs allow our plugin classes to be loaded without a full WordPress environment.
 */
function setup_wordpress_class_stubs(): void {
	// WP_REST_Controller stub.
	if ( ! class_exists( 'WP_REST_Controller' ) ) {
		// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
		// phpcs:disable Squiz.Classes.ClassFileName.NoMatch
		class WP_REST_Controller {
			protected $namespace = '';
			protected $rest_base = '';
		}
	}

	// WP_REST_Request stub.
	if ( ! class_exists( 'WP_REST_Request' ) ) {
		class WP_REST_Request {
		}
	}

	// WP_REST_Response stub.
	if ( ! class_exists( 'WP_REST_Response' ) ) {
		class WP_REST_Response {
		}
	}

	// WP_Error stub.
	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			public function __construct( $code = '', $message = '', $data = '' ) {
			}
		}
	}

	// WP_Admin_Bar stub.
	if ( ! class_exists( 'WP_Admin_Bar' ) ) {
		class WP_Admin_Bar {
			public function add_node( $args ) {
			}
		}
	}
	// phpcs:enable
}

/**
 * Get the list of key classes to verify.
 *
 * @return array<string> List of fully-qualified class names.
 */
function get_classes_to_verify(): array {
	return array(
		// Core plugin class.
		'WPAdminHealth\\Plugin',

		// Container classes.
		'WPAdminHealth\\Container\\Container',
		'WPAdminHealth\\Container\\ContainerInterface',
		'WPAdminHealth\\Container\\ServiceProvider',

		// All service providers (from Plugin::register_providers).
		'WPAdminHealth\\Providers\\CoreServiceProvider',
		'WPAdminHealth\\Settings\\SettingsServiceProvider',
		'WPAdminHealth\\Providers\\InstallerServiceProvider',
		'WPAdminHealth\\Providers\\MultisiteServiceProvider',
		'WPAdminHealth\\Providers\\BootstrapServiceProvider',
		'WPAdminHealth\\Providers\\DatabaseServiceProvider',
		'WPAdminHealth\\Providers\\ServicesServiceProvider',
		'WPAdminHealth\\Providers\\MediaServiceProvider',
		'WPAdminHealth\\Providers\\PerformanceServiceProvider',
		'WPAdminHealth\\Providers\\SchedulerServiceProvider',
		'WPAdminHealth\\Providers\\IntegrationServiceProvider',
		'WPAdminHealth\\Providers\\AIServiceProvider',
		'WPAdminHealth\\Providers\\RESTServiceProvider',

		// Key domain classes.
		'WPAdminHealth\\Installer',
		'WPAdminHealth\\Multisite',
		'WPAdminHealth\\Assets',
		'WPAdminHealth\\Database',
		'WPAdminHealth\\Settings',
		'WPAdminHealth\\RestApi',
		'WPAdminHealth\\HealthCalculator',

		// Integration classes.
		'WPAdminHealth\\Integrations\\IntegrationManager',
		'WPAdminHealth\\Integrations\\AbstractIntegration',
		'WPAdminHealth\\Integrations\\IntegrationFactory',

		// Scheduler classes.
		'WPAdminHealth\\Scheduler\\AbstractScheduledTask',
		'WPAdminHealth\\Scheduler\\SchedulingService',
		'WPAdminHealth\\Scheduler\\ProgressStore',
		'WPAdminHealth\\Scheduler\\TaskResult',

		// REST controllers.
		'WPAdminHealth\\REST\\RestController',
		'WPAdminHealth\\REST\\DashboardController',
		'WPAdminHealth\\REST\\ActivityController',
		'WPAdminHealth\\REST\\Database\\CleanupController',
		'WPAdminHealth\\REST\\Database\\OptimizationController',
		'WPAdminHealth\\REST\\Media\\MediaScanController',
		'WPAdminHealth\\REST\\Media\\MediaCleanupController',
		'WPAdminHealth\\REST\\Performance\\PerformanceStatsController',

		// Application layer use cases.
		'WPAdminHealth\\Application\\AI\\GenerateRecommendations',
		'WPAdminHealth\\Application\\Database\\RunCleanup',
		'WPAdminHealth\\Application\\Database\\RunOptimization',
		'WPAdminHealth\\Application\\Media\\ProcessDuplicates',
		'WPAdminHealth\\Application\\Media\\RunScan',
		'WPAdminHealth\\Application\\Performance\\CollectMetrics',
		'WPAdminHealth\\Application\\Performance\\RunHealthCheck',

		// Admin classes.
		'WPAdminHealth\\Admin\\MenuRegistrar',
		'WPAdminHealth\\Admin\\PageRenderer',
		'WPAdminHealth\\Admin\\SettingsViewModel',

		// Exception classes.
		'WPAdminHealth\\Exceptions\\NotFoundException',

		// Contracts/Interfaces.
		'WPAdminHealth\\Contracts\\SettingsInterface',
		'WPAdminHealth\\Contracts\\IntegrationFactoryInterface',
		'WPAdminHealth\\Scheduler\\Contracts\\SchedulingServiceInterface',
		'WPAdminHealth\\Settings\\Contracts\\SettingsRegistryInterface',
	);
}

/**
 * Verify that a class can be autoloaded.
 *
 * @param string $class_name Fully-qualified class name.
 * @return bool True if class exists, false otherwise.
 */
function verify_class( string $class_name ): bool {
	// Use class_exists with autoload enabled.
	return class_exists( $class_name ) || interface_exists( $class_name ) || trait_exists( $class_name );
}

/**
 * Main script execution.
 */
function main(): int {
	echo "\n";
	echo "===========================================\n";
	echo "  WP Admin Health Suite - Autoload Check  \n";
	echo "===========================================\n";
	echo "\n";

	// Setup WordPress stubs.
	setup_wordpress_stubs();
	setup_wordpress_class_stubs();

	// Load the autoloader.
	$autoload_path = WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/autoload.php';

	if ( ! file_exists( $autoload_path ) ) {
		output_error( "Autoloader not found at: {$autoload_path}" );
		return 1;
	}

	require_once $autoload_path;
	output_success( 'Autoloader loaded successfully' );

	// Get classes to verify.
	$classes = get_classes_to_verify();
	$total   = count( $classes );
	$passed  = 0;
	$failed  = array();

	echo "\n";
	output_info( "Checking {$total} classes...\n" );

	foreach ( $classes as $class_name ) {
		if ( verify_class( $class_name ) ) {
			output_success( $class_name );
			++$passed;
		} else {
			output_error( $class_name );
			$failed[] = $class_name;
		}
	}

	// Summary.
	echo "\n";
	echo "-------------------------------------------\n";

	if ( count( $failed ) > 0 ) {
		output_error( "FAILED: {$passed}/{$total} classes loaded" );
		echo "\n";
		output_info( 'Failed classes:' );
		foreach ( $failed as $class_name ) {
			echo "  - {$class_name}\n";
		}
		echo "\n";
		output_info( 'This usually indicates a case-sensitivity issue.' );
		output_info( 'Check that file names match class names exactly.' );
		echo "\n";
		return 1;
	}

	output_success( "PASSED: All {$total} classes loaded successfully" );
	echo "\n";
	return 0;
}

// Run the script.
exit( main() );
