<?php
/**
 * Container Unit Tests
 *
 * Tests for the PSR-11 compatible dependency injection container.
 *
 * @package WPAdminHealth\Tests\Unit\Container
 */

namespace WPAdminHealth\Tests\Unit\Container;

use WPAdminHealth\Container\Container;
use WPAdminHealth\Container\NotFoundException;
use WPAdminHealth\Tests\Test_Case;

/**
 * Container test class.
 */
class ContainerTest extends Test_Case {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment() {
		$this->container = new Container();
	}

	/**
	 * Test container implements ContainerInterface.
	 */
	public function test_implements_container_interface(): void {
		$this->assertInstanceOf( \WPAdminHealth\Container\Container_Interface::class, $this->container );
	}

	/**
	 * Test binding and resolving a simple value.
	 */
	public function test_bind_and_get_simple_value(): void {
		$this->container->bind( 'test.value', fn() => 'hello world' );

		$this->assertTrue( $this->container->has( 'test.value' ) );
		$this->assertEquals( 'hello world', $this->container->get( 'test.value' ) );
	}

	/**
	 * Test binding creates new instance each time.
	 */
	public function test_bind_creates_new_instance(): void {
		$counter = 0;
		$this->container->bind( 'test.counter', function() use ( &$counter ) {
			return ++$counter;
		});

		$this->assertEquals( 1, $this->container->get( 'test.counter' ) );
		$this->assertEquals( 2, $this->container->get( 'test.counter' ) );
		$this->assertEquals( 3, $this->container->get( 'test.counter' ) );
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {
		$counter = 0;
		$this->container->singleton( 'test.singleton', function() use ( &$counter ) {
			return ++$counter;
		});

		$first  = $this->container->get( 'test.singleton' );
		$second = $this->container->get( 'test.singleton' );
		$third  = $this->container->get( 'test.singleton' );

		$this->assertEquals( 1, $first );
		$this->assertEquals( 1, $second );
		$this->assertEquals( 1, $third );
		$this->assertSame( $first, $second );
	}

	/**
	 * Test instance method stores a pre-built instance.
	 */
	public function test_instance_stores_prebuilt_object(): void {
		$object = new \stdClass();
		$object->value = 'test';

		$this->container->instance( 'test.instance', $object );

		$resolved = $this->container->get( 'test.instance' );

		$this->assertSame( $object, $resolved );
		$this->assertEquals( 'test', $resolved->value );
	}

	/**
	 * Test has returns false for unbound services.
	 */
	public function test_has_returns_false_for_unbound(): void {
		$this->assertFalse( $this->container->has( 'nonexistent.service' ) );
	}

	/**
	 * Test get throws exception for unbound service.
	 */
	public function test_get_throws_not_found_exception(): void {
		$this->expectException( NotFoundException::class );
		$this->expectExceptionMessage( 'nonexistent.service' );

		$this->container->get( 'nonexistent.service' );
	}

	/**
	 * Test factory receives container.
	 */
	public function test_factory_receives_container(): void {
		$this->container->bind( 'dependency', fn() => 'dep-value' );
		$this->container->bind( 'service', function( $container ) {
			return 'service-' . $container->get( 'dependency' );
		});

		$this->assertEquals( 'service-dep-value', $this->container->get( 'service' ) );
	}

	/**
	 * Test bind can be overwritten.
	 */
	public function test_bind_can_be_overwritten(): void {
		$this->container->bind( 'test.value', fn() => 'first' );
		$this->assertEquals( 'first', $this->container->get( 'test.value' ) );

		$this->container->bind( 'test.value', fn() => 'second' );
		$this->assertEquals( 'second', $this->container->get( 'test.value' ) );
	}

	/**
	 * Test complex dependency chain.
	 */
	public function test_complex_dependency_chain(): void {
		$this->container->singleton( 'config', fn() => array( 'db_host' => 'localhost' ) );

		$this->container->singleton( 'database', function( $c ) {
			$config = $c->get( 'config' );
			return new class( $config['db_host'] ) {
				public string $host;
				public function __construct( string $host ) {
					$this->host = $host;
				}
			};
		});

		$this->container->bind( 'repository', function( $c ) {
			return new class( $c->get( 'database' ) ) {
				public $db;
				public function __construct( $db ) {
					$this->db = $db;
				}
			};
		});

		$repo = $this->container->get( 'repository' );

		$this->assertEquals( 'localhost', $repo->db->host );
	}

	/**
	 * Test binding interface to implementation.
	 */
	public function test_bind_interface_to_implementation(): void {
		$this->container->bind( \WPAdminHealth\Contracts\CacheInterface::class, function() {
			return new \WPAdminHealth\Cache\Memory_Cache();
		});

		$cache = $this->container->get( \WPAdminHealth\Contracts\CacheInterface::class );

		$this->assertInstanceOf( \WPAdminHealth\Contracts\CacheInterface::class, $cache );
		$this->assertInstanceOf( \WPAdminHealth\Cache\Memory_Cache::class, $cache );
	}

	/**
	 * Test array access get.
	 */
	public function test_array_access_get(): void {
		$this->container->bind( 'test.key', fn() => 'array-value' );

		$this->assertEquals( 'array-value', $this->container['test.key'] );
	}

	/**
	 * Test array access set.
	 */
	public function test_array_access_set(): void {
		$this->container['test.array'] = fn() => 'set-via-array';

		$this->assertEquals( 'set-via-array', $this->container->get( 'test.array' ) );
	}

	/**
	 * Test array access isset.
	 */
	public function test_array_access_isset(): void {
		$this->assertFalse( isset( $this->container['nonexistent'] ) );

		$this->container->bind( 'exists', fn() => true );

		$this->assertTrue( isset( $this->container['exists'] ) );
	}

	/**
	 * Test array access unset.
	 */
	public function test_array_access_unset(): void {
		$this->container->bind( 'to.remove', fn() => 'value' );
		$this->assertTrue( $this->container->has( 'to.remove' ) );

		unset( $this->container['to.remove'] );
		$this->assertFalse( $this->container->has( 'to.remove' ) );
	}

	/**
	 * Test singleton is preserved after multiple gets.
	 */
	public function test_singleton_preserved_state(): void {
		$this->container->singleton( 'stateful', function() {
			return new class {
				public int $calls = 0;
				public function increment(): int {
					return ++$this->calls;
				}
			};
		});

		$instance1 = $this->container->get( 'stateful' );
		$instance1->increment();
		$instance1->increment();

		$instance2 = $this->container->get( 'stateful' );

		$this->assertEquals( 2, $instance2->calls );
		$this->assertSame( $instance1, $instance2 );
	}
}
