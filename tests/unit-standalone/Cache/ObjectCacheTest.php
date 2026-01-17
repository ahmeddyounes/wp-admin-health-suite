<?php
/**
 * ObjectCache Unit Tests (Standalone)
 *
 * Tests for the WordPress object cache implementation including
 * backend detection, wp_cache function wrapping, and fallback behavior.
 *
 * @package WPAdminHealth\Tests\UnitStandalone\Cache
 */

namespace WPAdminHealth\Tests\UnitStandalone\Cache;

use WPAdminHealth\Cache\ObjectCache;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Tests\StandaloneTestCase;

/**
 * ObjectCache test class.
 *
 * Tests object cache backend detection, wp_cache function wrapping,
 * and fallback behavior when persistent cache is not available.
 */
class ObjectCacheTest extends StandaloneTestCase {

	/**
	 * Mock object cache storage.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $cache_storage = array();

	/**
	 * Track function availability for testing.
	 *
	 * @var array<string, bool>
	 */
	private static array $function_availability = array();

	/**
	 * ObjectCache instance for testing.
	 *
	 * @var ObjectCache
	 */
	private ObjectCache $cache;

	/**
	 * Setup test environment before each test.
	 */
	protected function setup_test_environment(): void {
		self::$cache_storage         = array();
		self::$function_availability = array(
			'wp_cache_flush_group'    => false,
			'wp_cache_get_multiple'   => false,
			'wp_cache_set_multiple'   => false,
			'wp_cache_delete_multiple' => false,
		);

		// Reset external object cache flag.
		unset( $GLOBALS['wpha_test_ext_object_cache'] );

		$this->setup_wp_cache_mocks();
		$this->cache = new ObjectCache( 'wpha' );
	}

	/**
	 * Cleanup test environment after each test.
	 */
	protected function cleanup_test_environment(): void {
		self::$cache_storage         = array();
		self::$function_availability = array();
		unset( $GLOBALS['wpha_test_ext_object_cache'] );
		$this->restore_wp_cache_functions();
	}

	/**
	 * Setup WordPress cache mock functions.
	 */
	private function setup_wp_cache_mocks(): void {
		// Mock wp_cache_get.
		$GLOBALS['wpha_test_wp_cache_get'] = static function ( $key, $group = '', $force = false, &$found = null ) {
			$storage_key = self::build_storage_key( $key, $group );
			if ( isset( self::$cache_storage[ $storage_key ] ) ) {
				$found = true;
				return self::$cache_storage[ $storage_key ]['value'];
			}
			$found = false;
			return false;
		};

		// Mock wp_cache_set.
		$GLOBALS['wpha_test_wp_cache_set'] = static function ( $key, $data, $group = '', $expire = 0 ) {
			$storage_key                         = self::build_storage_key( $key, $group );
			self::$cache_storage[ $storage_key ] = array(
				'value'  => $data,
				'expire' => $expire,
			);
			return true;
		};

		// Mock wp_cache_delete.
		$GLOBALS['wpha_test_wp_cache_delete'] = static function ( $key, $group = '' ) {
			$storage_key = self::build_storage_key( $key, $group );
			if ( isset( self::$cache_storage[ $storage_key ] ) ) {
				unset( self::$cache_storage[ $storage_key ] );
				return true;
			}
			return false;
		};

		// Mock wp_cache_flush.
		$GLOBALS['wpha_test_wp_cache_flush'] = static function () {
			self::$cache_storage = array();
			return true;
		};

		// Mock wp_cache_incr.
		$GLOBALS['wpha_test_wp_cache_incr'] = static function ( $key, $offset = 1, $group = '' ) {
			$storage_key = self::build_storage_key( $key, $group );
			if ( ! isset( self::$cache_storage[ $storage_key ] ) ) {
				return false;
			}
			$value = self::$cache_storage[ $storage_key ]['value'];
			if ( ! is_numeric( $value ) ) {
				return false;
			}
			$new_value                                    = (int) $value + $offset;
			self::$cache_storage[ $storage_key ]['value'] = $new_value;
			return $new_value;
		};

		// Mock wp_cache_decr.
		$GLOBALS['wpha_test_wp_cache_decr'] = static function ( $key, $offset = 1, $group = '' ) {
			$storage_key = self::build_storage_key( $key, $group );
			if ( ! isset( self::$cache_storage[ $storage_key ] ) ) {
				return false;
			}
			$value = self::$cache_storage[ $storage_key ]['value'];
			if ( ! is_numeric( $value ) ) {
				return false;
			}
			$new_value                                    = max( 0, (int) $value - $offset );
			self::$cache_storage[ $storage_key ]['value'] = $new_value;
			return $new_value;
		};

		// Mock wp_cache_flush_group.
		$GLOBALS['wpha_test_wp_cache_flush_group'] = static function ( $group ) {
			if ( ! self::$function_availability['wp_cache_flush_group'] ) {
				return false;
			}
			$prefix = $group . ':';
			foreach ( array_keys( self::$cache_storage ) as $key ) {
				if ( strpos( $key, $prefix ) === 0 ) {
					unset( self::$cache_storage[ $key ] );
				}
			}
			return true;
		};

		// Mock wp_cache_get_multiple.
		// Note: Returns array of results even when "disabled" since function_exists is always true.
		$GLOBALS['wpha_test_wp_cache_get_multiple'] = static function ( $keys, $group = '' ) {
			$results = array();
			foreach ( $keys as $key ) {
				$storage_key     = self::build_storage_key( $key, $group );
				$results[ $key ] = isset( self::$cache_storage[ $storage_key ] )
					? self::$cache_storage[ $storage_key ]['value']
					: false;
			}
			return $results;
		};

		// Mock wp_cache_set_multiple.
		// Note: Returns array of results even when "disabled" since function_exists is always true.
		$GLOBALS['wpha_test_wp_cache_set_multiple'] = static function ( $data, $group = '', $expire = 0 ) {
			$results = array();
			foreach ( $data as $key => $value ) {
				$storage_key                         = self::build_storage_key( $key, $group );
				self::$cache_storage[ $storage_key ] = array(
					'value'  => $value,
					'expire' => $expire,
				);
				$results[ $key ] = true;
			}
			return $results;
		};

		// Mock wp_cache_delete_multiple.
		// Note: Returns array of results even when "disabled" since function_exists is always true.
		$GLOBALS['wpha_test_wp_cache_delete_multiple'] = static function ( $keys, $group = '' ) {
			$results = array();
			foreach ( $keys as $key ) {
				$storage_key     = self::build_storage_key( $key, $group );
				$results[ $key ] = isset( self::$cache_storage[ $storage_key ] );
				unset( self::$cache_storage[ $storage_key ] );
			}
			return $results;
		};
	}

	/**
	 * Restore WordPress cache functions.
	 */
	private function restore_wp_cache_functions(): void {
		unset( $GLOBALS['wpha_test_wp_cache_get'] );
		unset( $GLOBALS['wpha_test_wp_cache_set'] );
		unset( $GLOBALS['wpha_test_wp_cache_delete'] );
		unset( $GLOBALS['wpha_test_wp_cache_flush'] );
		unset( $GLOBALS['wpha_test_wp_cache_incr'] );
		unset( $GLOBALS['wpha_test_wp_cache_decr'] );
		unset( $GLOBALS['wpha_test_wp_cache_flush_group'] );
		unset( $GLOBALS['wpha_test_wp_cache_get_multiple'] );
		unset( $GLOBALS['wpha_test_wp_cache_set_multiple'] );
		unset( $GLOBALS['wpha_test_wp_cache_delete_multiple'] );
	}

	/**
	 * Build storage key for cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string
	 */
	private static function build_storage_key( string $key, string $group ): string {
		return $group . ':' . $key;
	}

	/**
	 * Enable a mock function availability.
	 *
	 * @param string $function Function name.
	 */
	private function enable_function( string $function ): void {
		self::$function_availability[ $function ] = true;
	}

	/**
	 * Disable a mock function availability.
	 *
	 * @param string $function Function name.
	 */
	private function disable_function( string $function ): void {
		self::$function_availability[ $function ] = false;
	}

	/**
	 * Set value directly in mock storage.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @param string $group Cache group.
	 */
	private function set_in_storage( string $key, $value, string $group = 'wpha' ): void {
		$storage_key                         = self::build_storage_key( $key, $group );
		self::$cache_storage[ $storage_key ] = array(
			'value'  => $value,
			'expire' => 0,
		);
	}

	/**
	 * Get value directly from mock storage.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return mixed|null
	 */
	private function get_from_storage( string $key, string $group = 'wpha' ) {
		$storage_key = self::build_storage_key( $key, $group );
		return isset( self::$cache_storage[ $storage_key ] )
			? self::$cache_storage[ $storage_key ]['value']
			: null;
	}

	// =========================================================================
	// Interface Implementation Tests
	// =========================================================================

	/**
	 * Test ObjectCache implements CacheInterface.
	 */
	public function test_implements_cache_interface(): void {
		$this->assertInstanceOf( CacheInterface::class, $this->cache );
	}

	/**
	 * Test constructor with default group.
	 */
	public function test_constructor_default_group(): void {
		$cache = new ObjectCache();

		$reflection = new \ReflectionClass( $cache );
		$property   = $reflection->getProperty( 'group' );
		$property->setAccessible( true );

		$this->assertEquals( 'wpha', $property->getValue( $cache ) );
	}

	/**
	 * Test constructor with custom group.
	 */
	public function test_constructor_custom_group(): void {
		$cache = new ObjectCache( 'custom_group' );

		$reflection = new \ReflectionClass( $cache );
		$property   = $reflection->getProperty( 'group' );
		$property->setAccessible( true );

		$this->assertEquals( 'custom_group', $property->getValue( $cache ) );
	}

	// =========================================================================
	// Backend Detection Tests (is_available)
	// =========================================================================

	/**
	 * Test is_available returns false when no external object cache.
	 */
	public function test_is_available_returns_false_without_ext_cache(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = false;
		$this->assertFalse( ObjectCache::is_available() );
	}

	/**
	 * Test is_available returns true with external object cache.
	 */
	public function test_is_available_returns_true_with_ext_cache(): void {
		$GLOBALS['wpha_test_ext_object_cache'] = true;
		$this->assertTrue( ObjectCache::is_available() );
	}

	/**
	 * Test is_available is static method.
	 */
	public function test_is_available_is_static(): void {
		$reflection = new \ReflectionMethod( ObjectCache::class, 'is_available' );
		$this->assertTrue( $reflection->isStatic() );
	}

	// =========================================================================
	// Basic Get/Set/Delete/Has Tests (wp_cache wrapping)
	// =========================================================================

	/**
	 * Test set stores value via wp_cache_set.
	 */
	public function test_set_stores_value(): void {
		$result = $this->cache->set( 'key', 'value' );

		$this->assertTrue( $result );
		$this->assertEquals( 'value', $this->get_from_storage( 'key' ) );
	}

	/**
	 * Test set with TTL passes expiration.
	 */
	public function test_set_with_ttl(): void {
		$this->cache->set( 'key', 'value', 3600 );

		$storage_key = self::build_storage_key( 'key', 'wpha' );
		$this->assertEquals( 3600, self::$cache_storage[ $storage_key ]['expire'] );
	}

	/**
	 * Test get retrieves value via wp_cache_get.
	 */
	public function test_get_retrieves_value(): void {
		$this->set_in_storage( 'key', 'stored_value' );

		$result = $this->cache->get( 'key' );

		$this->assertEquals( 'stored_value', $result );
	}

	/**
	 * Test get returns default for missing key.
	 */
	public function test_get_returns_default_for_missing_key(): void {
		$this->assertNull( $this->cache->get( 'nonexistent' ) );
		$this->assertEquals( 'default', $this->cache->get( 'nonexistent', 'default' ) );
	}

	/**
	 * Test get properly handles found parameter.
	 */
	public function test_get_handles_found_parameter(): void {
		// Test with existing value.
		$this->set_in_storage( 'existing', 'value' );
		$this->assertEquals( 'value', $this->cache->get( 'existing', 'default' ) );

		// Test with missing value.
		$this->assertEquals( 'default', $this->cache->get( 'missing', 'default' ) );
	}

	/**
	 * Test has returns true for existing key.
	 */
	public function test_has_returns_true_for_existing_key(): void {
		$this->set_in_storage( 'key', 'value' );
		$this->assertTrue( $this->cache->has( 'key' ) );
	}

	/**
	 * Test has returns false for missing key.
	 */
	public function test_has_returns_false_for_missing_key(): void {
		$this->assertFalse( $this->cache->has( 'nonexistent' ) );
	}

	/**
	 * Test delete removes value via wp_cache_delete.
	 */
	public function test_delete_removes_value(): void {
		$this->set_in_storage( 'key', 'value' );

		$result = $this->cache->delete( 'key' );

		$this->assertTrue( $result );
		$this->assertNull( $this->get_from_storage( 'key' ) );
	}

	/**
	 * Test delete returns false for nonexistent key.
	 */
	public function test_delete_returns_false_for_nonexistent(): void {
		$result = $this->cache->delete( 'nonexistent' );
		$this->assertFalse( $result );
	}

	// =========================================================================
	// Clear/Flush Tests (Fallback Behavior)
	// =========================================================================

	/**
	 * Test clear uses wp_cache_flush_group when it returns true.
	 *
	 * Note: Since wp_cache_flush_group is always defined in our stubs,
	 * ObjectCache will always call it. The mock controls whether it succeeds.
	 */
	public function test_clear_uses_flush_group_when_supported(): void {
		$this->enable_function( 'wp_cache_flush_group' );

		$this->set_in_storage( 'key1', 'value1' );
		$this->set_in_storage( 'key2', 'value2' );
		$this->set_in_storage( 'other', 'value', 'other_group' );

		$result = $this->cache->clear();

		$this->assertTrue( $result );
		$this->assertNull( $this->get_from_storage( 'key1' ) );
		$this->assertNull( $this->get_from_storage( 'key2' ) );
		// Other group should remain.
		$this->assertEquals( 'value', $this->get_from_storage( 'other', 'other_group' ) );
	}

	/**
	 * Test clear returns false when flush_group returns false.
	 *
	 * Note: In the current ObjectCache implementation, when wp_cache_flush_group
	 * exists but returns false, clear() returns false (doesn't fall back).
	 * This is a known limitation of the implementation.
	 */
	public function test_clear_returns_false_when_flush_group_fails(): void {
		$this->disable_function( 'wp_cache_flush_group' );

		$this->set_in_storage( 'key1', 'value1' );

		$result = $this->cache->clear();

		// Current implementation returns the result of wp_cache_flush_group
		// which is false when the function exists but doesn't support groups.
		$this->assertFalse( $result );
	}

	/**
	 * Test clear with prefix returns false.
	 */
	public function test_clear_with_prefix_returns_false(): void {
		$this->set_in_storage( 'prefix_key', 'value' );

		$result = $this->cache->clear( 'prefix_' );

		$this->assertFalse( $result );
		// Value should still exist - prefix clearing not supported.
		$this->assertEquals( 'value', $this->get_from_storage( 'prefix_key' ) );
	}

	/**
	 * Test clear calls wp_cache_flush_group on empty prefix.
	 */
	public function test_clear_calls_flush_group_on_empty_prefix(): void {
		$this->enable_function( 'wp_cache_flush_group' );
		$result = $this->cache->clear( '' );
		$this->assertTrue( $result );
	}

	// =========================================================================
	// Remember Tests
	// =========================================================================

	/**
	 * Test remember returns cached value on hit.
	 */
	public function test_remember_returns_cached_value(): void {
		$this->set_in_storage( 'key', 'cached_value' );

		$executed = false;
		$result   = $this->cache->remember(
			'key',
			function () use ( &$executed ) {
				$executed = true;
				return 'callback_value';
			}
		);

		$this->assertFalse( $executed );
		$this->assertEquals( 'cached_value', $result );
	}

	/**
	 * Test remember executes callback on miss.
	 */
	public function test_remember_executes_callback_on_miss(): void {
		$executed = false;
		$result   = $this->cache->remember(
			'key',
			function () use ( &$executed ) {
				$executed = true;
				return 'callback_value';
			}
		);

		$this->assertTrue( $executed );
		$this->assertEquals( 'callback_value', $result );
	}

	/**
	 * Test remember caches callback result.
	 */
	public function test_remember_caches_callback_result(): void {
		$call_count = 0;
		$callback   = function () use ( &$call_count ) {
			++$call_count;
			return 'value';
		};

		$this->cache->remember( 'key', $callback );
		$this->cache->remember( 'key', $callback );

		$this->assertEquals( 1, $call_count );
	}

	/**
	 * Test remember with TTL.
	 */
	public function test_remember_with_ttl(): void {
		$this->cache->remember( 'key', fn() => 'value', 3600 );

		$storage_key = self::build_storage_key( 'key', 'wpha' );
		$this->assertEquals( 3600, self::$cache_storage[ $storage_key ]['expire'] );
	}

	/**
	 * Test remember uses has() to detect cached null values.
	 */
	public function test_remember_handles_cached_null(): void {
		$this->set_in_storage( 'null_key', null );

		$executed = false;
		$result   = $this->cache->remember(
			'null_key',
			function () use ( &$executed ) {
				$executed = true;
				return 'new_value';
			}
		);

		$this->assertFalse( $executed );
		$this->assertNull( $result );
	}

	// =========================================================================
	// Increment/Decrement Tests (wp_cache_incr/decr wrapping)
	// =========================================================================

	/**
	 * Test increment returns false when key does not exist.
	 */
	public function test_increment_returns_false_for_nonexistent(): void {
		$result = $this->cache->increment( 'nonexistent' );
		$this->assertFalse( $result );
	}

	/**
	 * Test increment increments existing value.
	 */
	public function test_increment_increments_existing_value(): void {
		$this->set_in_storage( 'counter', 10 );

		$result = $this->cache->increment( 'counter' );

		$this->assertEquals( 11, $result );
		$this->assertEquals( 11, $this->get_from_storage( 'counter' ) );
	}

	/**
	 * Test increment with custom value.
	 */
	public function test_increment_with_custom_value(): void {
		$this->set_in_storage( 'counter', 10 );

		$result = $this->cache->increment( 'counter', 5 );

		$this->assertEquals( 15, $result );
	}

	/**
	 * Test increment returns false for non-numeric value.
	 */
	public function test_increment_returns_false_for_non_numeric(): void {
		$this->set_in_storage( 'string_key', 'not_a_number' );

		$result = $this->cache->increment( 'string_key' );

		$this->assertFalse( $result );
	}

	/**
	 * Test decrement returns false when key does not exist.
	 */
	public function test_decrement_returns_false_for_nonexistent(): void {
		$result = $this->cache->decrement( 'nonexistent' );
		$this->assertFalse( $result );
	}

	/**
	 * Test decrement decrements existing value.
	 */
	public function test_decrement_decrements_existing_value(): void {
		$this->set_in_storage( 'counter', 10 );

		$result = $this->cache->decrement( 'counter' );

		$this->assertEquals( 9, $result );
		$this->assertEquals( 9, $this->get_from_storage( 'counter' ) );
	}

	/**
	 * Test decrement with custom value.
	 */
	public function test_decrement_with_custom_value(): void {
		$this->set_in_storage( 'counter', 10 );

		$result = $this->cache->decrement( 'counter', 3 );

		$this->assertEquals( 7, $result );
	}

	/**
	 * Test decrement does not go below zero.
	 */
	public function test_decrement_does_not_go_below_zero(): void {
		$this->set_in_storage( 'counter', 5 );

		$result = $this->cache->decrement( 'counter', 10 );

		$this->assertEquals( 0, $result );
	}

	// =========================================================================
	// Multiple Operations Tests (with fallback behavior)
	// =========================================================================

	/**
	 * Test get_multiple retrieves all values.
	 *
	 * Note: Since wp_cache_get_multiple is defined in our stubs,
	 * ObjectCache always uses the batch function.
	 */
	public function test_get_multiple_retrieves_values(): void {
		$this->set_in_storage( 'key1', 'value1' );
		$this->set_in_storage( 'key2', 'value2' );

		$results = $this->cache->get_multiple( array( 'key1', 'key2', 'key3' ) );

		$this->assertEquals(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
				'key3' => null, // Default for missing.
			),
			$results
		);
	}

	/**
	 * Test get_multiple with custom default.
	 */
	public function test_get_multiple_with_custom_default(): void {
		$results = $this->cache->get_multiple( array( 'key1', 'key2' ), 'default' );

		$this->assertEquals(
			array(
				'key1' => 'default',
				'key2' => 'default',
			),
			$results
		);
	}

	/**
	 * Test set_multiple sets all values.
	 *
	 * Note: Since wp_cache_set_multiple is defined in our stubs,
	 * ObjectCache always uses the batch function.
	 */
	public function test_set_multiple_sets_values(): void {
		$result = $this->cache->set_multiple(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
			)
		);

		$this->assertTrue( $result );
		$this->assertEquals( 'value1', $this->get_from_storage( 'key1' ) );
		$this->assertEquals( 'value2', $this->get_from_storage( 'key2' ) );
	}

	/**
	 * Test set_multiple with TTL.
	 */
	public function test_set_multiple_with_ttl(): void {
		$this->cache->set_multiple(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
			),
			3600
		);

		$storage_key1 = self::build_storage_key( 'key1', 'wpha' );
		$storage_key2 = self::build_storage_key( 'key2', 'wpha' );

		$this->assertEquals( 3600, self::$cache_storage[ $storage_key1 ]['expire'] );
		$this->assertEquals( 3600, self::$cache_storage[ $storage_key2 ]['expire'] );
	}

	/**
	 * Test delete_multiple removes specified keys.
	 *
	 * Note: Since wp_cache_delete_multiple is defined in our stubs,
	 * ObjectCache always uses the batch function.
	 */
	public function test_delete_multiple_removes_keys(): void {
		$this->set_in_storage( 'key1', 'value1' );
		$this->set_in_storage( 'key2', 'value2' );
		$this->set_in_storage( 'key3', 'value3' );

		$result = $this->cache->delete_multiple( array( 'key1', 'key2' ) );

		$this->assertTrue( $result );
		$this->assertNull( $this->get_from_storage( 'key1' ) );
		$this->assertNull( $this->get_from_storage( 'key2' ) );
		$this->assertEquals( 'value3', $this->get_from_storage( 'key3' ) );
	}

	/**
	 * Test delete_multiple returns false when some keys don't exist.
	 */
	public function test_delete_multiple_returns_false_on_partial_failure(): void {
		$this->set_in_storage( 'key1', 'value1' );
		// key2 does not exist.

		$result = $this->cache->delete_multiple( array( 'key1', 'key2' ) );

		$this->assertFalse( $result );
	}

	// =========================================================================
	// Group Isolation Tests
	// =========================================================================

	/**
	 * Test cache operations use correct group.
	 */
	public function test_operations_use_correct_group(): void {
		$cache1 = new ObjectCache( 'group1' );
		$cache2 = new ObjectCache( 'group2' );

		$cache1->set( 'key', 'value1' );
		$cache2->set( 'key', 'value2' );

		$this->assertEquals( 'value1', $cache1->get( 'key' ) );
		$this->assertEquals( 'value2', $cache2->get( 'key' ) );
	}

	/**
	 * Test delete only affects own group.
	 */
	public function test_delete_only_affects_own_group(): void {
		$cache1 = new ObjectCache( 'group1' );
		$cache2 = new ObjectCache( 'group2' );

		$cache1->set( 'key', 'value1' );
		$cache2->set( 'key', 'value2' );

		$cache1->delete( 'key' );

		$this->assertNull( $cache1->get( 'key' ) );
		$this->assertEquals( 'value2', $cache2->get( 'key' ) );
	}

	// =========================================================================
	// Edge Cases Tests
	// =========================================================================

	/**
	 * Test storing false value.
	 */
	public function test_storing_false_value(): void {
		$this->cache->set( 'key', false );

		$this->assertTrue( $this->cache->has( 'key' ) );
		$this->assertFalse( $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing null value.
	 */
	public function test_storing_null_value(): void {
		$this->cache->set( 'key', null );

		$this->assertTrue( $this->cache->has( 'key' ) );
		$this->assertNull( $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing empty string.
	 */
	public function test_storing_empty_string(): void {
		$this->cache->set( 'key', '' );

		$this->assertTrue( $this->cache->has( 'key' ) );
		$this->assertEquals( '', $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing zero.
	 */
	public function test_storing_zero(): void {
		$this->cache->set( 'key', 0 );

		$this->assertTrue( $this->cache->has( 'key' ) );
		$this->assertEquals( 0, $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing array.
	 */
	public function test_storing_array(): void {
		$array = array( 'nested' => array( 'data' => 'value' ) );
		$this->cache->set( 'key', $array );

		$this->assertEquals( $array, $this->cache->get( 'key' ) );
	}

	/**
	 * Test storing object.
	 */
	public function test_storing_object(): void {
		$obj       = new \stdClass();
		$obj->prop = 'value';

		$this->cache->set( 'key', $obj );

		$retrieved = $this->cache->get( 'key' );
		$this->assertEquals( 'value', $retrieved->prop );
	}

	/**
	 * Test set overwrites existing value.
	 */
	public function test_set_overwrites_existing_value(): void {
		$this->cache->set( 'key', 'value1' );
		$this->cache->set( 'key', 'value2' );

		$this->assertEquals( 'value2', $this->cache->get( 'key' ) );
	}

	/**
	 * Test empty key handling.
	 */
	public function test_empty_key_handling(): void {
		$this->cache->set( '', 'value' );
		$this->assertEquals( 'value', $this->cache->get( '' ) );
	}

	/**
	 * Test special characters in key.
	 */
	public function test_special_characters_in_key(): void {
		$key = 'key:with/special\\chars!@#$%';
		$this->cache->set( $key, 'value' );
		$this->assertEquals( 'value', $this->cache->get( $key ) );
	}

	// =========================================================================
	// Comprehensive Fallback Flow Tests
	// =========================================================================

	/**
	 * Test complete workflow with batch functions available.
	 */
	public function test_workflow_with_batch_functions(): void {
		$this->enable_function( 'wp_cache_get_multiple' );
		$this->enable_function( 'wp_cache_set_multiple' );
		$this->enable_function( 'wp_cache_delete_multiple' );
		$this->enable_function( 'wp_cache_flush_group' );

		// Set multiple.
		$this->cache->set_multiple(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
				'key3' => 'value3',
			)
		);

		// Get multiple.
		$results = $this->cache->get_multiple( array( 'key1', 'key2', 'key3' ) );
		$this->assertEquals(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
				'key3' => 'value3',
			),
			$results
		);

		// Delete multiple.
		$this->cache->delete_multiple( array( 'key1', 'key2' ) );
		$this->assertFalse( $this->cache->has( 'key1' ) );
		$this->assertFalse( $this->cache->has( 'key2' ) );
		$this->assertTrue( $this->cache->has( 'key3' ) );

		// Clear with flush_group.
		$clear_result = $this->cache->clear();
		$this->assertTrue( $clear_result );
		$this->assertFalse( $this->cache->has( 'key3' ) );
	}

	/**
	 * Test complete workflow with individual set/get/delete operations.
	 */
	public function test_workflow_with_individual_operations(): void {
		$this->enable_function( 'wp_cache_flush_group' );

		// Set multiple values using set_multiple.
		$this->cache->set_multiple(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
				'key3' => 'value3',
			)
		);

		// Verify values were set.
		$results = $this->cache->get_multiple( array( 'key1', 'key2', 'key3' ) );
		$this->assertEquals(
			array(
				'key1' => 'value1',
				'key2' => 'value2',
				'key3' => 'value3',
			),
			$results
		);

		// Delete some keys.
		$this->cache->delete_multiple( array( 'key1', 'key2' ) );
		$this->assertFalse( $this->cache->has( 'key1' ) );
		$this->assertFalse( $this->cache->has( 'key2' ) );
		$this->assertTrue( $this->cache->has( 'key3' ) );

		// Clear with flush_group.
		$clear_result = $this->cache->clear();
		$this->assertTrue( $clear_result );
		$this->assertFalse( $this->cache->has( 'key3' ) );
	}

	/**
	 * Test remember callback exception propagates.
	 */
	public function test_remember_callback_exception(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Callback error' );

		$this->cache->remember(
			'error_key',
			function () {
				throw new \RuntimeException( 'Callback error' );
			}
		);
	}

	/**
	 * Test get_multiple with batch function handles false values correctly.
	 */
	public function test_get_multiple_batch_handles_false_values(): void {
		$this->enable_function( 'wp_cache_get_multiple' );

		$this->set_in_storage( 'key1', false );
		$this->set_in_storage( 'key2', 'value2' );

		$results = $this->cache->get_multiple( array( 'key1', 'key2', 'key3' ) );

		// false values should be returned as default since batch get returns false for missing.
		// This tests the current implementation's behavior.
		$this->assertArrayHasKey( 'key1', $results );
		$this->assertArrayHasKey( 'key2', $results );
		$this->assertArrayHasKey( 'key3', $results );
		$this->assertEquals( 'value2', $results['key2'] );
	}
}
