<?php
/**
 * Provider Contract Test (Standalone)
 *
 * Tests that all service providers can be registered and booted
 * in a controlled stub environment to catch wiring regressions early.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Providers
 */

namespace WPAdminHealth\Tests\UnitStandalone\Providers;

use WPAdminHealth\Container\Container;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Tests\Mocks\MockCache;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Tests\StandaloneTestCase;

// Import all service providers.
use WPAdminHealth\Providers\CoreServiceProvider;
use WPAdminHealth\Providers\DatabaseServiceProvider;
use WPAdminHealth\Providers\MediaServiceProvider;
use WPAdminHealth\Providers\PerformanceServiceProvider;
use WPAdminHealth\Providers\SchedulerServiceProvider;
use WPAdminHealth\Providers\ServicesServiceProvider;
use WPAdminHealth\Providers\AIServiceProvider;
use WPAdminHealth\Providers\BootstrapServiceProvider;
use WPAdminHealth\Providers\InstallerServiceProvider;
use WPAdminHealth\Providers\IntegrationServiceProvider;
use WPAdminHealth\Providers\MultisiteServiceProvider;
use WPAdminHealth\Providers\RESTServiceProvider;
use WPAdminHealth\Settings\SettingsServiceProvider;

/**
 * Provider Contract Test
 *
 * Validates the DI container boots correctly with all service providers
 * and that services are properly wired and resolvable.
 */
class ProviderContractTest extends StandaloneTestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Mock connection.
	 *
	 * @var MockConnection
	 */
	protected MockConnection $mock_connection;

	/**
	 * Mock cache.
	 *
	 * @var MockCache
	 */
	protected MockCache $mock_cache;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->container       = new Container();
		$this->mock_connection = new MockConnection();
		$this->mock_cache      = new MockCache();

		// Set up default results for common queries.
		$this->mock_connection->set_default_result( array() );

		// Register core dependencies that all providers need.
		$this->register_core_dependencies();
	}

	/**
	 * Clean up test environment.
	 */
	protected function cleanup_test_environment(): void {
		$this->container->flush();
		$this->mock_connection->reset();
		$this->mock_cache->reset();
	}

	/**
	 * Register core dependencies that all providers need.
	 *
	 * Note: Connection and cache mocks are registered in override_with_mocks()
	 * AFTER providers register, because providers may call singleton() which
	 * clears any existing instance.
	 */
	private function register_core_dependencies(): void {
		// Define the version constant if not already defined (needed by Installer).
		if ( ! defined( 'WP_ADMIN_HEALTH_VERSION' ) ) {
			define( 'WP_ADMIN_HEALTH_VERSION', '1.0.0' );
		}

		// Define plugin directory constants (needed by Assets and other classes).
		if ( ! defined( 'WP_ADMIN_HEALTH_PLUGIN_DIR' ) ) {
			define( 'WP_ADMIN_HEALTH_PLUGIN_DIR', WP_ADMIN_HEALTH_TESTS_DIR . '/../' );
		}
		if ( ! defined( 'WP_ADMIN_HEALTH_PLUGIN_URL' ) ) {
			define( 'WP_ADMIN_HEALTH_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-admin-health-suite/' );
		}

		// Set the stored version to match the current version so Installer::maybe_upgrade()
		// doesn't trigger an actual upgrade (which requires WordPress DB functions).
		$GLOBALS['wpha_test_options']['wpha_version'] = WP_ADMIN_HEALTH_VERSION;

		// Register plugin path and URL (needed by BootstrapServiceProvider).
		$this->container->instance( 'plugin.path', WP_ADMIN_HEALTH_TESTS_DIR . '/../' );
		$this->container->instance( 'plugin.url', 'https://example.com/wp-content/plugins/wp-admin-health-suite/' );
		$this->container->instance( 'plugin.version', '1.0.0' );
	}

	/**
	 * Override connection and cache bindings with mocks.
	 *
	 * Must be called AFTER providers register their bindings but BEFORE boot(),
	 * because providers use singleton() which clears existing instances.
	 */
	private function override_with_mocks(): void {
		// Override the connection binding with our mock.
		// Using instance() after providers register ensures our mock takes precedence.
		$this->container->instance( ConnectionInterface::class, $this->mock_connection );

		// Override the cache binding with our mock.
		$this->container->instance( CacheInterface::class, $this->mock_cache );
	}

	/**
	 * Get the ordered list of providers to register.
	 *
	 * Order matters because some providers depend on services from others.
	 *
	 * @return array<class-string>
	 */
	private function get_provider_classes(): array {
		return array(
			// Foundation providers (no dependencies on other providers).
			SettingsServiceProvider::class,
			CoreServiceProvider::class,
			ServicesServiceProvider::class,

			// Database and domain providers.
			DatabaseServiceProvider::class,
			MediaServiceProvider::class,
			PerformanceServiceProvider::class,

			// Infrastructure providers.
			SchedulerServiceProvider::class,
			InstallerServiceProvider::class,
			MultisiteServiceProvider::class,

			// Integration providers (deferred).
			IntegrationServiceProvider::class,
			AIServiceProvider::class,
			RESTServiceProvider::class,

			// Bootstrap (UI) provider - depends on many services.
			BootstrapServiceProvider::class,
		);
	}

	/**
	 * Test all providers can be instantiated without errors.
	 */
	public function test_all_providers_can_be_instantiated(): void {
		foreach ( $this->get_provider_classes() as $provider_class ) {
			$provider = new $provider_class( $this->container );

			$this->assertInstanceOf(
				$provider_class,
				$provider,
				"Provider {$provider_class} should be instantiable"
			);
		}
	}

	/**
	 * Test all providers can be registered without throwing exceptions.
	 */
	public function test_all_providers_can_be_registered(): void {
		foreach ( $this->get_provider_classes() as $provider_class ) {
			$provider = new $provider_class( $this->container );

			// This should not throw any exceptions.
			$this->container->register( $provider );
		}

		// Verify container is still healthy.
		$this->assertGreaterThan( 0, count( $this->container->get_bindings() ) );
	}

	/**
	 * Test container can boot all providers without errors.
	 */
	public function test_container_boots_all_providers(): void {
		// Register all providers.
		foreach ( $this->get_provider_classes() as $provider_class ) {
			$this->container->register( new $provider_class( $this->container ) );
		}

		// Override with mocks AFTER registration but BEFORE boot.
		$this->override_with_mocks();

		// Boot should not throw.
		$this->container->boot();

		$this->assertTrue( $this->container->is_booted() );
	}

	/**
	 * Test SettingsServiceProvider binds expected services.
	 */
	public function test_settings_provider_binds_services(): void {
		$provider = new SettingsServiceProvider( $this->container );
		$this->container->register( $provider );

		// Check interface binding.
		$this->assertTrue( $this->container->has( SettingsInterface::class ) );
		$this->assertTrue( $this->container->has( 'settings.registry' ) );

		// Verify resolvable.
		$settings = $this->container->get( SettingsInterface::class );
		$this->assertInstanceOf( SettingsInterface::class, $settings );
	}

	/**
	 * Test CoreServiceProvider binds expected services.
	 */
	public function test_core_provider_binds_services(): void {
		// Settings must be registered first.
		$this->container->register( new SettingsServiceProvider( $this->container ) );
		$this->container->register( new CoreServiceProvider( $this->container ) );

		// Check cache interface (already mocked, but CoreServiceProvider binds it too).
		$this->assertTrue( $this->container->has( CacheInterface::class ) );

		// Check health calculator.
		$this->assertTrue( $this->container->has( 'WPAdminHealth\\HealthCalculator' ) );
	}

	/**
	 * Test DatabaseServiceProvider binds expected services.
	 */
	public function test_database_provider_binds_services(): void {
		// Register dependencies.
		$this->container->register( new SettingsServiceProvider( $this->container ) );
		$this->container->register( new CoreServiceProvider( $this->container ) );

		// Register database provider.
		$this->container->register( new DatabaseServiceProvider( $this->container ) );

		// Check expected bindings.
		$expected_services = array(
			'WPAdminHealth\\Contracts\\AnalyzerInterface',
			'WPAdminHealth\\Contracts\\OptimizerInterface',
			'WPAdminHealth\\Contracts\\RevisionsManagerInterface',
			'WPAdminHealth\\Contracts\\TransientsCleanerInterface',
			'WPAdminHealth\\Contracts\\OrphanedCleanerInterface',
			'WPAdminHealth\\Contracts\\TrashCleanerInterface',
		);

		foreach ( $expected_services as $service ) {
			$this->assertTrue(
				$this->container->has( $service ),
				"DatabaseServiceProvider should bind {$service}"
			);
		}
	}

	/**
	 * Test MediaServiceProvider binds expected services.
	 */
	public function test_media_provider_binds_services(): void {
		// Register dependencies.
		$this->container->register( new SettingsServiceProvider( $this->container ) );

		// Register media provider.
		$this->container->register( new MediaServiceProvider( $this->container ) );

		$expected_services = array(
			'WPAdminHealth\\Contracts\\ScannerInterface',
			'WPAdminHealth\\Contracts\\DuplicateDetectorInterface',
			'WPAdminHealth\\Contracts\\LargeFilesInterface',
			'WPAdminHealth\\Contracts\\AltTextCheckerInterface',
			'WPAdminHealth\\Contracts\\SafeDeleteInterface',
			'WPAdminHealth\\Contracts\\ExclusionsInterface',
		);

		foreach ( $expected_services as $service ) {
			$this->assertTrue(
				$this->container->has( $service ),
				"MediaServiceProvider should bind {$service}"
			);
		}
	}

	/**
	 * Test PerformanceServiceProvider is deferred and binds services on demand.
	 */
	public function test_performance_provider_is_deferred(): void {
		// Register dependencies.
		$this->container->register( new SettingsServiceProvider( $this->container ) );

		// Register performance provider.
		$provider = new PerformanceServiceProvider( $this->container );
		$this->container->register( $provider );

		// Should be deferred.
		$this->assertTrue( $provider->is_deferred() );

		// Services should be available via has() even when deferred.
		$this->assertTrue( $this->container->has( 'WPAdminHealth\\Contracts\\QueryMonitorInterface' ) );
		$this->assertTrue( $this->container->has( 'WPAdminHealth\\Contracts\\AutoloadAnalyzerInterface' ) );
	}

	/**
	 * Test ServicesServiceProvider binds foundation services.
	 */
	public function test_services_provider_binds_services(): void {
		// Register dependencies.
		$this->container->register( new SettingsServiceProvider( $this->container ) );

		// Register services provider.
		$this->container->register( new ServicesServiceProvider( $this->container ) );

		$expected_services = array(
			'WPAdminHealth\\Contracts\\ConfigurationInterface',
			'WPAdminHealth\\Contracts\\ActivityLoggerInterface',
			'WPAdminHealth\\Contracts\\TableCheckerInterface',
		);

		foreach ( $expected_services as $service ) {
			$this->assertTrue(
				$this->container->has( $service ),
				"ServicesServiceProvider should bind {$service}"
			);
		}
	}

	/**
	 * Test SchedulerServiceProvider binds scheduler services.
	 */
	public function test_scheduler_provider_binds_services(): void {
		// Register all dependencies (scheduler depends on many services).
		$this->container->register( new SettingsServiceProvider( $this->container ) );
		$this->container->register( new CoreServiceProvider( $this->container ) );
		$this->container->register( new ServicesServiceProvider( $this->container ) );
		$this->container->register( new DatabaseServiceProvider( $this->container ) );
		$this->container->register( new MediaServiceProvider( $this->container ) );
		$this->container->register( new PerformanceServiceProvider( $this->container ) );

		// Register scheduler provider.
		$this->container->register( new SchedulerServiceProvider( $this->container ) );

		$expected_services = array(
			'WPAdminHealth\\Scheduler\\Contracts\\SchedulerRegistryInterface',
			'WPAdminHealth\\Scheduler\\Contracts\\SchedulingServiceInterface',
			'WPAdminHealth\\Scheduler\\ProgressStore',
			'WPAdminHealth\\Scheduler\\TaskObservabilityService',
		);

		foreach ( $expected_services as $service ) {
			$this->assertTrue(
				$this->container->has( $service ),
				"SchedulerServiceProvider should bind {$service}"
			);
		}
	}

	/**
	 * Test AIServiceProvider is deferred.
	 */
	public function test_ai_provider_is_deferred(): void {
		$provider = new AIServiceProvider( $this->container );

		$this->assertTrue( $provider->is_deferred() );
		$this->assertContains( 'WPAdminHealth\\AI\\Recommendations', $provider->provides() );
		$this->assertContains( 'WPAdminHealth\\AI\\OneClickFix', $provider->provides() );
	}

	/**
	 * Test RESTServiceProvider is deferred.
	 */
	public function test_rest_provider_is_deferred(): void {
		$provider = new RESTServiceProvider( $this->container );

		$this->assertTrue( $provider->is_deferred() );
	}

	/**
	 * Test IntegrationServiceProvider is deferred.
	 */
	public function test_integration_provider_is_deferred(): void {
		$provider = new IntegrationServiceProvider( $this->container );

		$this->assertTrue( $provider->is_deferred() );
		$this->assertContains( 'WPAdminHealth\\Integrations\\IntegrationManager', $provider->provides() );
	}

	/**
	 * Test InstallerServiceProvider binds Installer.
	 */
	public function test_installer_provider_binds_services(): void {
		$this->container->register( new InstallerServiceProvider( $this->container ) );

		$this->assertTrue( $this->container->has( 'WPAdminHealth\\Installer' ) );
		$this->assertTrue( $this->container->has( 'installer' ) );
	}

	/**
	 * Test MultisiteServiceProvider binds Multisite service.
	 */
	public function test_multisite_provider_binds_services(): void {
		$this->container->register( new MultisiteServiceProvider( $this->container ) );

		$this->assertTrue( $this->container->has( 'WPAdminHealth\\Multisite' ) );
		$this->assertTrue( $this->container->has( 'multisite' ) );
	}

	/**
	 * Test BootstrapServiceProvider binds admin services.
	 */
	public function test_bootstrap_provider_binds_services(): void {
		// Register dependencies.
		$this->container->register( new SettingsServiceProvider( $this->container ) );

		// Register bootstrap provider.
		$this->container->register( new BootstrapServiceProvider( $this->container ) );

		$expected_services = array(
			'WPAdminHealth\\Admin\\MenuRegistrar',
			'WPAdminHealth\\Admin\\PageRenderer',
			'WPAdminHealth\\Assets',
			'WPAdminHealth\\Performance\\HeartbeatController',
		);

		foreach ( $expected_services as $service ) {
			$this->assertTrue(
				$this->container->has( $service ),
				"BootstrapServiceProvider should bind {$service}"
			);
		}
	}

	/**
	 * Test full provider stack can resolve critical services.
	 *
	 * This is the comprehensive contract test that ensures all wiring works.
	 */
	public function test_full_stack_resolves_critical_services(): void {
		// Register all providers in order.
		foreach ( $this->get_provider_classes() as $provider_class ) {
			$this->container->register( new $provider_class( $this->container ) );
		}

		// Override with mocks AFTER registration but BEFORE boot.
		$this->override_with_mocks();

		// Boot the container.
		$this->container->boot();

		// Test critical service resolution.
		$critical_services = array(
			// Settings (foundation).
			SettingsInterface::class,

			// Database services.
			'WPAdminHealth\\Contracts\\AnalyzerInterface',
			'WPAdminHealth\\Contracts\\OptimizerInterface',

			// Media services.
			'WPAdminHealth\\Contracts\\ScannerInterface',
			'WPAdminHealth\\Contracts\\DuplicateDetectorInterface',

			// Scheduler services.
			'WPAdminHealth\\Scheduler\\Contracts\\SchedulingServiceInterface',

			// Admin services.
			'WPAdminHealth\\Admin\\MenuRegistrar',
			'WPAdminHealth\\Assets',
		);

		foreach ( $critical_services as $service ) {
			$this->assertTrue(
				$this->container->has( $service ),
				"Critical service {$service} should be bound"
			);

			// Resolve the service - this tests the full wiring.
			$instance = $this->container->get( $service );
			$this->assertNotNull(
				$instance,
				"Critical service {$service} should resolve to non-null"
			);
		}
	}

	/**
	 * Test deferred providers resolve correctly after boot.
	 */
	public function test_deferred_providers_resolve_after_boot(): void {
		// Register all providers.
		foreach ( $this->get_provider_classes() as $provider_class ) {
			$this->container->register( new $provider_class( $this->container ) );
		}

		// Override with mocks AFTER registration but BEFORE boot.
		$this->override_with_mocks();

		// Boot the container.
		$this->container->boot();

		// Request a deferred service (QueryMonitorInterface from PerformanceServiceProvider).
		$query_monitor = $this->container->get( 'WPAdminHealth\\Contracts\\QueryMonitorInterface' );
		$this->assertNotNull( $query_monitor );

		// Request another deferred service (IntegrationManager from IntegrationServiceProvider).
		$integration_manager = $this->container->get( 'WPAdminHealth\\Integrations\\IntegrationManager' );
		$this->assertNotNull( $integration_manager );
	}

	/**
	 * Test alias resolution works correctly.
	 */
	public function test_alias_resolution(): void {
		// Register settings provider.
		$this->container->register( new SettingsServiceProvider( $this->container ) );

		// Both should resolve to the same instance.
		$via_interface = $this->container->get( SettingsInterface::class );
		$via_alias     = $this->container->get( 'settings.registry' );

		$this->assertSame(
			$via_interface,
			$via_alias,
			'Alias should resolve to the same instance as the interface'
		);
	}

	/**
	 * Test singleton services return same instance.
	 */
	public function test_singleton_services_return_same_instance(): void {
		// Register providers.
		$this->container->register( new SettingsServiceProvider( $this->container ) );
		$this->container->register( new ServicesServiceProvider( $this->container ) );

		// Override with mocks (ActivityLogger depends on ConnectionInterface).
		$this->override_with_mocks();

		// Get ActivityLogger twice.
		$logger1 = $this->container->get( 'WPAdminHealth\\Contracts\\ActivityLoggerInterface' );
		$logger2 = $this->container->get( 'WPAdminHealth\\Contracts\\ActivityLoggerInterface' );

		$this->assertSame(
			$logger1,
			$logger2,
			'Singleton services should return the same instance'
		);
	}

	/**
	 * Test no circular dependencies exist in provider chain.
	 */
	public function test_no_circular_dependencies(): void {
		// Register all providers.
		foreach ( $this->get_provider_classes() as $provider_class ) {
			$this->container->register( new $provider_class( $this->container ) );
		}

		// Override with mocks AFTER registration but BEFORE boot.
		$this->override_with_mocks();

		// Boot the container.
		$this->container->boot();

		// Services that depend on WordPress core classes (like WP_REST_Controller)
		// cannot be resolved in standalone tests. Skip these.
		$skip_patterns = array(
			'WPAdminHealth\\REST\\',  // REST controllers extend WP_REST_Controller.
		);

		// Attempt to resolve all non-deferred bindings.
		// If there are circular dependencies, this will throw a RuntimeException.
		$exception_thrown = false;
		$resolved_count   = 0;

		try {
			// Get the list of all bindings.
			$bindings = $this->container->get_bindings();

			// Try to resolve each one.
			foreach ( $bindings as $service_id ) {
				// Skip services that require WordPress core classes.
				$should_skip = false;
				foreach ( $skip_patterns as $pattern ) {
					if ( strpos( $service_id, $pattern ) === 0 ) {
						$should_skip = true;
						break;
					}
				}

				if ( $should_skip ) {
					continue;
				}

				$this->container->get( $service_id );
				$resolved_count++;
			}
		} catch ( \RuntimeException $e ) {
			if ( strpos( $e->getMessage(), 'Circular dependency' ) !== false ) {
				$exception_thrown = true;
				$this->fail( 'Circular dependency detected: ' . $e->getMessage() );
			}
			throw $e;
		}

		$this->assertFalse( $exception_thrown, 'No circular dependencies should exist' );
		$this->assertGreaterThan( 0, $resolved_count, 'Should resolve at least some services' );
	}

	/**
	 * Test provider count matches expected.
	 *
	 * This helps catch accidentally missing providers.
	 */
	public function test_provider_count(): void {
		$provider_classes = $this->get_provider_classes();

		// We expect 13 providers total.
		$this->assertCount(
			13,
			$provider_classes,
			'Expected 13 service providers to be registered'
		);
	}
}
