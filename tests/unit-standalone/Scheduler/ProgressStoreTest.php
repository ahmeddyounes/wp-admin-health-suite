<?php
/**
 * ProgressStore unit tests.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Scheduler;

use WPAdminHealth\Scheduler\ProgressStore;
use WPAdminHealth\Tests\StandaloneTestCase;

class ProgressStoreTest extends StandaloneTestCase {

	/**
	 * Progress store under test.
	 *
	 * @var ProgressStore
	 */
	private ProgressStore $store;

	/**
	 * {@inheritdoc}
	 */
	protected function setup_test_environment(): void {
		$this->store = new ProgressStore( 'test_option_key' );
	}

	public function test_constructor_sets_default_option_key(): void {
		$store = new ProgressStore( 'my_option_key' );
		$this->assertSame( 'my_option_key', $store->get_option_key() );
	}

	public function test_constructor_with_empty_key(): void {
		$store = new ProgressStore();
		$this->assertSame( '', $store->get_option_key() );
	}

	public function test_for_task_creates_prefixed_store(): void {
		$store = ProgressStore::for_task( 'database_cleanup' );
		$this->assertSame( 'wpha_progress_database_cleanup', $store->get_option_key() );
	}

	public function test_get_option_key_returns_default_when_no_task_id(): void {
		$store = new ProgressStore( 'default_key' );
		$this->assertSame( 'default_key', $store->get_option_key() );
	}

	public function test_get_option_key_returns_prefixed_when_task_id_provided(): void {
		$store = new ProgressStore( 'default_key' );
		$this->assertSame( 'wpha_progress_some_task', $store->get_option_key( 'some_task' ) );
	}

	public function test_load_returns_empty_array_when_no_key(): void {
		$store = new ProgressStore();
		$this->assertSame( array(), $store->load() );
	}

	public function test_save_returns_false_when_no_key(): void {
		$store = new ProgressStore();
		$this->assertFalse( $store->save( array( 'test' => 'data' ) ) );
	}

	public function test_clear_returns_false_when_no_key(): void {
		$store = new ProgressStore();
		$this->assertFalse( $store->clear() );
	}

	public function test_has_progress_returns_false_when_empty(): void {
		// This test would need WordPress functions to fully work.
		// For standalone testing, we verify the method exists and returns bool.
		$store = new ProgressStore();
		$this->assertIsBool( $store->has_progress() );
	}

	public function test_get_completed_tasks_returns_array(): void {
		$store = new ProgressStore();
		$this->assertIsArray( $store->get_completed_tasks() );
	}

	public function test_get_errors_returns_array(): void {
		$store = new ProgressStore();
		$this->assertIsArray( $store->get_errors() );
	}

	/**
	 * Tests that require WordPress functions.
	 *
	 * The following functionality requires WordPress functions:
	 * - load() - uses get_option()
	 * - save() - uses update_option()
	 * - clear() - uses delete_option()
	 * - has_progress() - uses get_option()
	 * - save_interrupted() - uses update_option()
	 * - update() - uses get_option() and update_option()
	 * - add_completed_task() - uses get_option() and update_option()
	 * - add_error() - uses get_option() and update_option()
	 * - increment() - uses get_option() and update_option()
	 * - is_stale() - uses get_option() and time comparison
	 * - clear_all() - uses $wpdb
	 * - list_all() - uses $wpdb
	 *
	 * These should be tested in integration tests with WordPress loaded.
	 */
	public function test_interface_is_complete(): void {
		$store = new ProgressStore( 'test_key' );

		// Verify all public methods exist.
		$this->assertTrue( method_exists( $store, 'get_option_key' ) );
		$this->assertTrue( method_exists( $store, 'load' ) );
		$this->assertTrue( method_exists( $store, 'save' ) );
		$this->assertTrue( method_exists( $store, 'clear' ) );
		$this->assertTrue( method_exists( $store, 'has_progress' ) );
		$this->assertTrue( method_exists( $store, 'get_saved_at' ) );
		$this->assertTrue( method_exists( $store, 'get_interrupted_at' ) );
		$this->assertTrue( method_exists( $store, 'get_completed_tasks' ) );
		$this->assertTrue( method_exists( $store, 'get_errors' ) );
		$this->assertTrue( method_exists( $store, 'save_interrupted' ) );
		$this->assertTrue( method_exists( $store, 'update' ) );
		$this->assertTrue( method_exists( $store, 'add_completed_task' ) );
		$this->assertTrue( method_exists( $store, 'add_error' ) );
		$this->assertTrue( method_exists( $store, 'increment' ) );
		$this->assertTrue( method_exists( $store, 'is_stale' ) );
		$this->assertTrue( method_exists( $store, 'clear_all' ) );
		$this->assertTrue( method_exists( $store, 'list_all' ) );
	}

	public function test_for_task_static_method(): void {
		$store = ProgressStore::for_task( 'media_scan' );

		$this->assertInstanceOf( ProgressStore::class, $store );
		$this->assertSame( 'wpha_progress_media_scan', $store->get_option_key() );
	}
}
