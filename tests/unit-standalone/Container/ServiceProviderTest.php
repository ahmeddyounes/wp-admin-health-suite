<?php
/**
 * Service Provider Unit Tests (Standalone)
 *
 * Tests for the service provider base class.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Container
 */

namespace WPAdminHealth\Tests\UnitStandalone\Container;

use WPAdminHealth\Container\Container;
use WPAdminHealth\Container\ContainerException;
use WPAdminHealth\Container\Service_Provider;
use WPAdminHealth\Tests\Standalone_Test_Case;

/**
 * Test service provider for testing.
 */
class Test_Service_Provider extends Service_Provider {

	/**
	 * Track if register was called.
	 *
	 * @var bool
	 */
	public bool $registered = false;

	/**
	 * Track if boot was called.
	 *
	 * @var bool
	 */
	public bool $booted = false;

	/**
	 * Register services.
	 */
	public function register(): void {
		$this->registered = true;
		$this->singleton( 'test.service', fn() => 'test value' );
		$this->bind( 'test.factory', fn() => new \stdClass() );
	}

	/**
	 * Boot services.
	 */
	public function boot(): void {
		$this->booted = true;
	}
}

/**
 * Deferred service provider for testing.
 */
class Deferred_Test_Provider extends Service_Provider {

	/**
	 * Whether this provider is deferred.
	 *
	 * @var bool
	 */
	protected bool $deferred = true;

	/**
	 * Services provided.
	 *
	 * @var array<string>
	 */
	protected array $provides = array( 'deferred.service', 'another.deferred' );

	/**
	 * Track if register was called.
	 *
	 * @var bool
	 */
	public bool $registered = false;

	/**
	 * Register services.
	 */
	public function register(): void {
		$this->registered = true;
		$this->singleton( 'deferred.service', fn() => 'deferred value' );
		$this->singleton( 'another.deferred', fn() => 'another value' );
	}
}

/**
 * Service Provider test class.
 */
class ServiceProviderTest extends Standalone_Test_Case {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment(): void {
		$this->container = new Container();
	}

	/**
	 * Test provider can be registered.
	 */
	public function test_provider_can_be_registered(): void {
		$provider = new Test_Service_Provider( $this->container );

		$this->container->register( $provider );

		$this->assertTrue( $provider->registered );
	}

	/**
	 * Test provider registers services.
	 */
	public function test_provider_registers_services(): void {
		$provider = new Test_Service_Provider( $this->container );
		$this->container->register( $provider );

		$this->assertTrue( $this->container->has( 'test.service' ) );
		$this->assertTrue( $this->container->has( 'test.factory' ) );
		$this->assertEquals( 'test value', $this->container->get( 'test.service' ) );
	}

	/**
	 * Test provider boot is called after container boot.
	 */
	public function test_provider_boot_called_after_container_boot(): void {
		$provider = new Test_Service_Provider( $this->container );
		$this->container->register( $provider );

		$this->assertFalse( $provider->booted );

		$this->container->boot();

		$this->assertTrue( $provider->booted );
	}

	/**
	 * Test provider boot is called immediately if container already booted.
	 */
	public function test_provider_boot_called_immediately_if_container_booted(): void {
		$this->container->boot();

		$provider = new Test_Service_Provider( $this->container );
		$this->container->register( $provider );

		$this->assertTrue( $provider->booted );
	}

	/**
	 * Test deferred provider is not registered immediately.
	 */
	public function test_deferred_provider_not_registered_immediately(): void {
		$provider = new Deferred_Test_Provider( $this->container );
		$this->container->register( $provider );

		$this->assertFalse( $provider->registered );
	}

	/**
	 * Test deferred provider reports its services.
	 */
	public function test_deferred_provider_provides(): void {
		$provider = new Deferred_Test_Provider( $this->container );

		$provides = $provider->provides();

		$this->assertContains( 'deferred.service', $provides );
		$this->assertContains( 'another.deferred', $provides );
	}

	/**
	 * Test deferred provider is_deferred returns true.
	 */
	public function test_deferred_provider_is_deferred(): void {
		$provider = new Deferred_Test_Provider( $this->container );

		$this->assertTrue( $provider->is_deferred() );
	}

	/**
	 * Test non-deferred provider is_deferred returns false.
	 */
	public function test_non_deferred_provider_is_not_deferred(): void {
		$provider = new Test_Service_Provider( $this->container );

		$this->assertFalse( $provider->is_deferred() );
	}

	/**
	 * Test deferred provider is registered when service requested.
	 */
	public function test_deferred_provider_registered_on_demand(): void {
		$provider = new Deferred_Test_Provider( $this->container );
		$this->container->register( $provider );

		$this->assertFalse( $provider->registered );

		$result = $this->container->get( 'deferred.service' );

		$this->assertTrue( $provider->registered );
		$this->assertEquals( 'deferred value', $result );
	}

	/**
	 * Test deferred provider has returns true for provided services.
	 */
	public function test_has_returns_true_for_deferred_services(): void {
		$provider = new Deferred_Test_Provider( $this->container );
		$this->container->register( $provider );

		$this->assertTrue( $this->container->has( 'deferred.service' ) );
		$this->assertTrue( $this->container->has( 'another.deferred' ) );
	}

	/**
	 * Test provider helper methods work correctly.
	 */
	public function test_provider_helper_methods(): void {
		$provider = new Test_Service_Provider( $this->container );
		$this->container->register( $provider );

		// Singleton returns same instance
		$instance1 = $this->container->get( 'test.service' );
		$instance2 = $this->container->get( 'test.service' );
		$this->assertSame( $instance1, $instance2 );

		// Factory returns new instance each time
		$factory1 = $this->container->get( 'test.factory' );
		$factory2 = $this->container->get( 'test.factory' );
		$this->assertNotSame( $factory1, $factory2 );
	}

	/**
	 * Test provider instance helper.
	 */
	public function test_provider_instance_helper(): void {
		$provider = new class( $this->container ) extends Service_Provider {
			public function register(): void {
				$obj = new \stdClass();
				$obj->id = 'test';
				$this->instance( 'my.instance', $obj );
			}
		};

		$this->container->register( $provider );

		$result = $this->container->get( 'my.instance' );
		$this->assertEquals( 'test', $result->id );
	}

	/**
	 * Test provider alias helper.
	 */
	public function test_provider_alias_helper(): void {
		$provider = new class( $this->container ) extends Service_Provider {
			public function register(): void {
				$this->singleton( 'original', fn() => 'original value' );
				$this->alias( 'aliased', 'original' );
			}
		};

		$this->container->register( $provider );

		$this->assertEquals( 'original value', $this->container->get( 'aliased' ) );
	}

	/**
	 * Test multiple providers with same service (last wins).
	 */
	public function test_multiple_providers_last_wins(): void {
		$provider1 = new class( $this->container ) extends Service_Provider {
			public function register(): void {
				$this->singleton( 'shared.service', fn() => 'from provider 1' );
			}
		};

		$provider2 = new class( $this->container ) extends Service_Provider {
			public function register(): void {
				$this->singleton( 'shared.service', fn() => 'from provider 2' );
			}
		};

		$this->container->register( $provider1 );
		$this->container->register( $provider2 );

		$this->assertEquals( 'from provider 2', $this->container->get( 'shared.service' ) );
	}

	/**
	 * Test deferred provider boot when container already booted.
	 */
	public function test_deferred_provider_boot_when_container_booted(): void {
		$boot_count = 0;

		$provider = new class( $this->container, $boot_count ) extends Service_Provider {
			protected bool $deferred = true;
			protected array $provides = array( 'deferred.booted.service' );
			private $boot_counter;

			public function __construct( Container $container, &$boot_count ) {
				parent::__construct( $container );
				$this->boot_counter = &$boot_count;
			}

			public function register(): void {
				$this->singleton( 'deferred.booted.service', fn() => 'value' );
			}

			public function boot(): void {
				++$this->boot_counter;
			}
		};

		$this->container->boot();
		$this->container->register( $provider );

		// Request the service - should trigger register and boot.
		$this->container->get( 'deferred.booted.service' );

		$this->assertEquals( 1, $boot_count );
	}

	/**
	 * Test multiple deferred providers - one triggers only its provider.
	 */
	public function test_multiple_deferred_providers_isolated(): void {
		$provider1 = new class( $this->container ) extends Service_Provider {
			protected bool $deferred = true;
			protected array $provides = array( 'deferred.1' );
			public bool $registered = false;

			public function register(): void {
				$this->registered = true;
				$this->singleton( 'deferred.1', fn() => 'value 1' );
			}
		};

		$provider2 = new class( $this->container ) extends Service_Provider {
			protected bool $deferred = true;
			protected array $provides = array( 'deferred.2' );
			public bool $registered = false;

			public function register(): void {
				$this->registered = true;
				$this->singleton( 'deferred.2', fn() => 'value 2' );
			}
		};

		$this->container->register( $provider1 );
		$this->container->register( $provider2 );

		// Neither registered yet.
		$this->assertFalse( $provider1->registered );
		$this->assertFalse( $provider2->registered );

		// Request first service.
		$this->container->get( 'deferred.1' );

		// Only provider1 should be registered.
		$this->assertTrue( $provider1->registered );
		$this->assertFalse( $provider2->registered );
	}

	/**
	 * Test provider boot exception is wrapped in ContainerException with original preserved.
	 */
	public function test_provider_boot_exception_wrapped(): void {
		$provider = new class( $this->container ) extends Service_Provider {
			public function register(): void {
			}

			public function boot(): void {
				throw new \RuntimeException( 'Boot failed' );
			}
		};

		$this->container->register( $provider );

		try {
			$this->container->boot();
			$this->fail( 'Expected ContainerException to be thrown' );
		} catch ( ContainerException $e ) {
			// Verify the wrapper exception.
			$this->assertStringContainsString( 'Boot failed', $e->getMessage() );

			// Verify the original exception is preserved.
			$previous = $e->getPrevious();
			$this->assertInstanceOf( \RuntimeException::class, $previous );
			$this->assertEquals( 'Boot failed', $previous->getMessage() );
		}
	}

	/**
	 * Test provider register exception is wrapped in ContainerException with original preserved.
	 */
	public function test_provider_register_exception_wrapped(): void {
		$provider = new class( $this->container ) extends Service_Provider {
			public function register(): void {
				throw new \RuntimeException( 'Register failed' );
			}
		};

		try {
			$this->container->register( $provider );
			$this->fail( 'Expected ContainerException to be thrown' );
		} catch ( ContainerException $e ) {
			// Verify the wrapper exception.
			$this->assertStringContainsString( 'Register failed', $e->getMessage() );

			// Verify the original exception is preserved.
			$previous = $e->getPrevious();
			$this->assertInstanceOf( \RuntimeException::class, $previous );
			$this->assertEquals( 'Register failed', $previous->getMessage() );
		}
	}

	/**
	 * Test deferred provider with empty provides array.
	 */
	public function test_deferred_provider_empty_provides(): void {
		$provider = new class( $this->container ) extends Service_Provider {
			protected bool $deferred = true;
			protected array $provides = array();
			public bool $registered = false;

			public function register(): void {
				$this->registered = true;
			}
		};

		$this->container->register( $provider );

		// Even though deferred, with empty provides it won't be triggered.
		$this->assertFalse( $provider->registered );

		// Empty provides() array.
		$this->assertEmpty( $provider->provides() );
	}

	/**
	 * Test provider can access container during boot.
	 */
	public function test_provider_access_container_during_boot(): void {
		$provider = new class( $this->container ) extends Service_Provider {
			public ?string $config_value = null;

			public function register(): void {
				$this->singleton( 'config', fn() => array( 'key' => 'value' ) );
			}

			public function boot(): void {
				$config = $this->container->get( 'config' );
				$this->config_value = $config['key'];
			}
		};

		$this->container->register( $provider );
		$this->container->boot();

		$this->assertEquals( 'value', $provider->config_value );
	}

	/**
	 * Test provider can depend on services from other providers.
	 */
	public function test_provider_depends_on_other_provider(): void {
		$config_provider = new class( $this->container ) extends Service_Provider {
			public function register(): void {
				$this->singleton( 'config', fn() => array( 'db_host' => 'localhost' ) );
			}
		};

		$db_provider = new class( $this->container ) extends Service_Provider {
			public function register(): void {
				$this->singleton( 'database', function( $c ) {
					$config = $c->get( 'config' );
					return new class( $config['db_host'] ) {
						public string $host;

						public function __construct( string $host ) {
							$this->host = $host;
						}
					};
				});
			}
		};

		$this->container->register( $config_provider );
		$this->container->register( $db_provider );

		$db = $this->container->get( 'database' );
		$this->assertEquals( 'localhost', $db->host );
	}

	/**
	 * Test double boot is no-op.
	 */
	public function test_double_boot_is_noop(): void {
		$boot_count = 0;

		$provider = new class( $this->container, $boot_count ) extends Service_Provider {
			private $counter;

			public function __construct( Container $container, &$boot_count ) {
				parent::__construct( $container );
				$this->counter = &$boot_count;
			}

			public function register(): void {
			}

			public function boot(): void {
				++$this->counter;
			}
		};

		$this->container->register( $provider );
		$this->container->boot();
		$this->container->boot(); // Second boot should be no-op.

		$this->assertEquals( 1, $boot_count );
	}

	/**
	 * Test deferred provider services cleared after registration.
	 */
	public function test_deferred_provider_services_cleared_after_registration(): void {
		$register_count = 0;

		$provider = new class( $this->container, $register_count ) extends Service_Provider {
			protected bool $deferred = true;
			protected array $provides = array( 'service.a', 'service.b' );
			private $counter;

			public function __construct( Container $container, &$register_count ) {
				parent::__construct( $container );
				$this->counter = &$register_count;
			}

			public function register(): void {
				++$this->counter;
				$this->singleton( 'service.a', fn() => 'a' );
				$this->singleton( 'service.b', fn() => 'b' );
			}
		};

		$this->container->register( $provider );

		// First service triggers registration.
		$this->container->get( 'service.a' );
		$this->assertEquals( 1, $register_count );

		// Second service should NOT re-register.
		$this->container->get( 'service.b' );
		$this->assertEquals( 1, $register_count );
	}
}
