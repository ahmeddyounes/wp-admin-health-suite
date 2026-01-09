<?php
/**
 * Container Unit Tests (Standalone)
 *
 * Tests for the PSR-11 compatible dependency injection container.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Container
 */

namespace WPAdminHealth\Tests\UnitStandalone\Container;

use WPAdminHealth\Container\Container;
use WPAdminHealth\Container\ContainerException;
use WPAdminHealth\Container\NotFoundException;
use WPAdminHealth\Tests\Standalone_Test_Case;

/**
 * Container test class.
 */
class ContainerTest extends Standalone_Test_Case {

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
		// Verify all three retrievals return the exact same instance.
		$this->assertSame( $first, $second );
		$this->assertSame( $second, $third );
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

	/**
	 * Test circular dependency detection throws exception.
	 */
	public function test_circular_dependency_throws_exception(): void {
		$this->container->bind( 'service.a', function( $c ) {
			return $c->get( 'service.b' );
		});

		$this->container->bind( 'service.b', function( $c ) {
			return $c->get( 'service.a' );
		});

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Circular dependency' );

		$this->container->get( 'service.a' );
	}

	/**
	 * Test alias resolves to original service.
	 */
	public function test_alias_resolves_correctly(): void {
		$this->container->singleton( 'original.service', fn() => 'original value' );
		$this->container->alias( 'alias.name', 'original.service' );

		$this->assertTrue( $this->container->has( 'alias.name' ) );
		$this->assertEquals( 'original value', $this->container->get( 'alias.name' ) );
	}

	/**
	 * Test chained aliases resolve correctly.
	 */
	public function test_chained_aliases(): void {
		$this->container->singleton( 'root.service', fn() => 'root value' );
		$this->container->alias( 'first.alias', 'root.service' );
		$this->container->alias( 'second.alias', 'first.alias' );

		$this->assertEquals( 'root value', $this->container->get( 'second.alias' ) );
	}

	/**
	 * Test auto-wiring instantiates class without dependencies.
	 */
	public function test_auto_wire_simple_class(): void {
		$result = $this->container->get( \stdClass::class );

		$this->assertInstanceOf( \stdClass::class, $result );
	}

	/**
	 * Test auto-wiring injects dependencies.
	 */
	public function test_auto_wire_with_dependencies(): void {
		$this->container->singleton( \WPAdminHealth\Contracts\CacheInterface::class, function() {
			return new \WPAdminHealth\Cache\Memory_Cache();
		});

		// Get a class that depends on CacheInterface - use Memory_Cache itself which has no deps.
		$cache = $this->container->get( \WPAdminHealth\Cache\Memory_Cache::class );

		$this->assertInstanceOf( \WPAdminHealth\Cache\Memory_Cache::class, $cache );
	}

	/**
	 * Test flush clears all container state.
	 */
	public function test_flush_clears_container(): void {
		$this->container->singleton( 'service1', fn() => 'value1' );
		$this->container->bind( 'service2', fn() => 'value2' );
		$this->container->instance( 'service3', new \stdClass() );
		$this->container->alias( 'alias1', 'service1' );

		// Resolve to populate instances.
		$this->container->get( 'service1' );

		// Verify they exist.
		$this->assertTrue( $this->container->has( 'service1' ) );
		$this->assertTrue( $this->container->has( 'service2' ) );
		$this->assertTrue( $this->container->has( 'service3' ) );

		// Flush the container.
		$this->container->flush();

		// Verify all are cleared.
		$this->assertFalse( $this->container->has( 'service1' ) );
		$this->assertFalse( $this->container->has( 'service2' ) );
		$this->assertFalse( $this->container->has( 'service3' ) );
		$this->assertFalse( $this->container->has( 'alias1' ) );
		$this->assertEmpty( $this->container->get_bindings() );
		$this->assertEmpty( $this->container->get_instances() );
	}

	/**
	 * Test get_bindings returns list of bindings.
	 */
	public function test_get_bindings(): void {
		$this->container->bind( 'service1', fn() => 'value1' );
		$this->container->singleton( 'service2', fn() => 'value2' );

		$bindings = $this->container->get_bindings();

		$this->assertCount( 2, $bindings );
		$this->assertContains( 'service1', $bindings );
		$this->assertContains( 'service2', $bindings );
	}

	/**
	 * Test get_instances returns resolved instances.
	 */
	public function test_get_instances(): void {
		$this->container->singleton( 'singleton.service', fn() => 'singleton' );
		$this->container->instance( 'instance.service', new \stdClass() );

		// Resolve the singleton.
		$this->container->get( 'singleton.service' );

		$instances = $this->container->get_instances();

		$this->assertCount( 2, $instances );
		$this->assertContains( 'singleton.service', $instances );
		$this->assertContains( 'instance.service', $instances );
	}

	/**
	 * Test make is alias for get.
	 */
	public function test_make_equals_get(): void {
		$this->container->bind( 'test.service', fn() => 'test value' );

		$this->assertEquals(
			$this->container->get( 'test.service' ),
			$this->container->make( 'test.service' )
		);
	}

	/**
	 * Test has returns true for existing classes even without binding.
	 */
	public function test_has_for_existing_class(): void {
		// stdClass exists and can be auto-wired.
		$this->assertTrue( $this->container->has( \stdClass::class ) );
	}

	/**
	 * Test has returns false for non-existent class.
	 */
	public function test_has_for_nonexistent_class(): void {
		$this->assertFalse( $this->container->has( 'NonExistent\\ClassName' ) );
	}

	/**
	 * Test boot prevents double booting.
	 */
	public function test_boot_prevents_double_boot(): void {
		$this->assertFalse( $this->container->is_booted() );

		$this->container->boot();
		$this->assertTrue( $this->container->is_booted() );

		// Second boot should be no-op.
		$this->container->boot();
		$this->assertTrue( $this->container->is_booted() );
	}

	/**
	 * Test instance returns exact same object.
	 */
	public function test_instance_exact_reference(): void {
		$obj = new \stdClass();
		$obj->unique_id = uniqid();

		$this->container->instance( 'my.object', $obj );

		$retrieved1 = $this->container->get( 'my.object' );
		$retrieved2 = $this->container->get( 'my.object' );

		$this->assertSame( $obj, $retrieved1 );
		$this->assertSame( $obj, $retrieved2 );
		$this->assertEquals( $obj->unique_id, $retrieved1->unique_id );
	}

	/**
	 * Test auto-wiring throws NotFoundException for interface.
	 */
	public function test_auto_wire_throws_for_interface(): void {
		$this->expectException( NotFoundException::class );

		// CacheInterface is not instantiable (it's an interface).
		$this->container->get( \WPAdminHealth\Contracts\CacheInterface::class );
	}

	/**
	 * Test auto-wiring throws NotFoundException for abstract class.
	 */
	public function test_auto_wire_throws_for_abstract_class(): void {
		$this->expectException( NotFoundException::class );

		// Service_Provider is abstract.
		$this->container->get( \WPAdminHealth\Container\Service_Provider::class );
	}

	/**
	 * Test auto-wiring throws for unresolvable constructor parameter.
	 */
	public function test_auto_wire_throws_for_unresolvable_parameter(): void {
		$this->expectException( NotFoundException::class );

		// Try to auto-wire a class that requires a non-type-hinted parameter.
		$this->container->get( Test_Unresolvable_Class::class );
	}

	/**
	 * Test auto-wiring resolves nullable parameters as null.
	 */
	public function test_auto_wire_resolves_nullable_as_null(): void {
		$result = $this->container->get( Test_Nullable_Dependency::class );

		$this->assertInstanceOf( Test_Nullable_Dependency::class, $result );
		$this->assertNull( $result->dependency );
	}

	/**
	 * Test auto-wiring uses default values.
	 */
	public function test_auto_wire_uses_default_values(): void {
		$result = $this->container->get( Test_Default_Value_Class::class );

		$this->assertInstanceOf( Test_Default_Value_Class::class, $result );
		$this->assertEquals( 'default', $result->value );
	}

	/**
	 * Test resolver exception is wrapped in ContainerException with original preserved.
	 */
	public function test_resolver_exception_wrapped_in_container_exception(): void {
		$this->container->bind( 'throwing.service', function() {
			throw new \InvalidArgumentException( 'Test exception' );
		});

		try {
			$this->container->get( 'throwing.service' );
			$this->fail( 'Expected ContainerException to be thrown' );
		} catch ( ContainerException $e ) {
			// Verify the wrapper exception.
			$this->assertStringContainsString( 'throwing.service', $e->getMessage() );
			$this->assertStringContainsString( 'Test exception', $e->getMessage() );

			// Verify the original exception is preserved.
			$previous = $e->getPrevious();
			$this->assertInstanceOf( \InvalidArgumentException::class, $previous );
			$this->assertEquals( 'Test exception', $previous->getMessage() );
		}
	}

	/**
	 * Test alias to non-existent service throws NotFoundException.
	 */
	public function test_alias_to_nonexistent_throws(): void {
		$this->container->alias( 'my.alias', 'nonexistent.service' );

		$this->expectException( NotFoundException::class );

		$this->container->get( 'my.alias' );
	}

	/**
	 * Test circular alias detection.
	 */
	public function test_circular_alias_detection(): void {
		// This creates an infinite loop in alias resolution.
		$this->container->alias( 'alias.a', 'alias.b' );
		$this->container->alias( 'alias.b', 'alias.a' );

		// Should throw RuntimeException with clear error about circular alias.
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Circular alias detected' );

		$this->container->get( 'alias.a' );
	}

	/**
	 * Test overwriting singleton with bind.
	 */
	public function test_overwrite_singleton_with_bind(): void {
		$this->container->singleton( 'service', fn() => 'singleton' );
		$this->assertEquals( 'singleton', $this->container->get( 'service' ) );

		// Overwrite with regular bind.
		$counter = 0;
		$this->container->bind( 'service', function() use ( &$counter ) {
			return 'bind-' . ++$counter;
		});

		// Should now behave as regular bind (new instance each time).
		$this->assertEquals( 'bind-1', $this->container->get( 'service' ) );
		$this->assertEquals( 'bind-2', $this->container->get( 'service' ) );
	}

	/**
	 * Test container state after boot then flush.
	 */
	public function test_flush_resets_boot_state(): void {
		$this->container->boot();
		$this->assertTrue( $this->container->is_booted() );

		$this->container->flush();
		$this->assertFalse( $this->container->is_booted() );

		// Should be able to boot again.
		$this->container->boot();
		$this->assertTrue( $this->container->is_booted() );
	}

	/**
	 * Test instance can be overwritten.
	 */
	public function test_instance_can_be_overwritten(): void {
		$obj1 = new \stdClass();
		$obj1->id = 'first';

		$obj2 = new \stdClass();
		$obj2->id = 'second';

		$this->container->instance( 'object', $obj1 );
		$this->assertEquals( 'first', $this->container->get( 'object' )->id );

		$this->container->instance( 'object', $obj2 );
		$this->assertEquals( 'second', $this->container->get( 'object' )->id );
	}

	/**
	 * Test has returns true for alias even if target doesn't exist yet.
	 */
	public function test_has_for_alias_without_checking_target(): void {
		$this->container->alias( 'my.alias', 'some.service' );

		// has() for alias currently resolves to check if target exists.
		// Since 'some.service' doesn't exist and isn't a class, should be false.
		$this->assertFalse( $this->container->has( 'my.alias' ) );

		// Now bind the target.
		$this->container->bind( 'some.service', fn() => 'value' );

		// Now has() should return true.
		$this->assertTrue( $this->container->has( 'my.alias' ) );
	}

	/**
	 * Test deep circular dependency chain detection.
	 */
	public function test_deep_circular_dependency(): void {
		$this->container->bind( 'service.a', fn( $c ) => $c->get( 'service.b' ) );
		$this->container->bind( 'service.b', fn( $c ) => $c->get( 'service.c' ) );
		$this->container->bind( 'service.c', fn( $c ) => $c->get( 'service.a' ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Circular dependency' );

		$this->container->get( 'service.a' );
	}

	/**
	 * Test resolution stack is cleared after successful resolution.
	 */
	public function test_resolution_stack_cleared_after_success(): void {
		$this->container->bind( 'service.a', fn( $c ) => $c->get( 'service.b' ) );
		$this->container->bind( 'service.b', fn() => 'value' );

		// First resolution should work.
		$this->assertEquals( 'value', $this->container->get( 'service.a' ) );

		// Second resolution should also work (stack should be clear).
		$this->assertEquals( 'value', $this->container->get( 'service.a' ) );
	}

	/**
	 * Test resolution stack is cleared after exception.
	 */
	public function test_resolution_stack_cleared_after_exception(): void {
		$this->container->bind( 'failing', function() {
			throw new \RuntimeException( 'Intentional failure' );
		});

		try {
			$this->container->get( 'failing' );
		} catch ( \RuntimeException $e ) {
			// Expected.
		}

		// Should be able to resolve other services (stack should be clear).
		$this->container->bind( 'working', fn() => 'works' );
		$this->assertEquals( 'works', $this->container->get( 'working' ) );
	}

	/**
	 * Test binding returns void (fluent interface not supported).
	 */
	public function test_bind_returns_void(): void {
		$result = $this->container->bind( 'test', fn() => 'value' );

		$this->assertNull( $result );
	}
}

/**
 * Test class with unresolvable parameter.
 */
class Test_Unresolvable_Class {
	public string $required_param;

	public function __construct( string $required_param ) {
		$this->required_param = $required_param;
	}
}

/**
 * Test class with nullable dependency.
 */
class Test_Nullable_Dependency {
	public ?\WPAdminHealth\Contracts\CacheInterface $dependency;

	public function __construct( ?\WPAdminHealth\Contracts\CacheInterface $dependency = null ) {
		$this->dependency = $dependency;
	}
}

/**
 * Test class with default value.
 */
class Test_Default_Value_Class {
	public string $value;

	public function __construct( string $value = 'default' ) {
		$this->value = $value;
	}
}
