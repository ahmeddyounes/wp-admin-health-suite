<?php
/**
 * Integration Manager Unit Tests
 *
 * Tests for the integration management system.
 *
 * @package WPAdminHealth\Tests\Unit\Integration
 */

namespace WPAdminHealth\Tests\Unit\Integration;

use WPAdminHealth\Integrations\IntegrationManager;
use WPAdminHealth\Integrations\AbstractIntegration;
use WPAdminHealth\Contracts\IntegrationInterface;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Tests\Mocks\MockConnection;
use WPAdminHealth\Cache\MemoryCache;
use WPAdminHealth\Tests\TestCase;

/**
 * Mock integration for testing.
 */
class Mock_Integration extends AbstractIntegration {
	private string $id;
	private string $name;
	private bool $available;
	private array $capabilities;

	public function __construct(
		ConnectionInterface $connection,
		CacheInterface $cache,
		string $id = 'mock',
		string $name = 'Mock Integration',
		bool $available = true,
		array $capabilities = array( 'media_detection' )
	) {
		$this->id           = $id;
		$this->name         = $name;
		$this->available    = $available;
		$this->capabilities = $capabilities;
		parent::__construct( $connection, $cache );
	}

	public function get_id(): string {
		return $this->id;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function is_available(): bool {
		return $this->available;
	}

	public function get_min_version(): string {
		return '1.0.0';
	}

	public function get_current_version(): ?string {
		return '2.0.0';
	}

	public function get_capabilities(): array {
		return $this->capabilities;
	}

	protected function register_hooks(): void {
		// No hooks for mock.
	}
}

/**
 * Integration Manager test class.
 */
class IntegrationManagerTest extends TestCase {

	/**
	 * Integration Manager instance.
	 *
	 * @var IntegrationManager
	 */
	protected IntegrationManager $manager;

	/**
	 * Mock connection.
	 *
	 * @var MockConnection
	 */
	protected MockConnection $connection;

	/**
	 * Memory cache.
	 *
	 * @var MemoryCache
	 */
	protected MemoryCache $cache;

	/**
	 * Set up test environment.
	 */
	protected function setup_test_environment() {
		$this->connection = new MockConnection();
		$this->cache      = new MemoryCache();
		$this->manager    = new IntegrationManager();
	}

	/**
	 * Test registering an integration.
	 */
	public function test_register_integration(): void {
		$integration = new Mock_Integration( $this->connection, $this->cache, 'test', 'Test Integration' );

		$this->manager->register( $integration );

		$this->assertTrue( $this->manager->has( 'test' ) );
	}

	/**
	 * Test getting a registered integration.
	 */
	public function test_get_integration(): void {
		$integration = new Mock_Integration( $this->connection, $this->cache, 'test', 'Test Integration' );
		$this->manager->register( $integration );

		$retrieved = $this->manager->get( 'test' );

		$this->assertSame( $integration, $retrieved );
	}

	/**
	 * Test getting nonexistent integration returns null.
	 */
	public function test_get_nonexistent_returns_null(): void {
		$result = $this->manager->get( 'nonexistent' );

		$this->assertNull( $result );
	}

	/**
	 * Test getting all integrations.
	 */
	public function test_get_all_integrations(): void {
		$integration1 = new Mock_Integration( $this->connection, $this->cache, 'int1', 'Integration 1' );
		$integration2 = new Mock_Integration( $this->connection, $this->cache, 'int2', 'Integration 2' );

		$this->manager->register( $integration1 );
		$this->manager->register( $integration2 );

		$all = $this->manager->all();

		$this->assertCount( 2, $all );
		$this->assertArrayHasKey( 'int1', $all );
		$this->assertArrayHasKey( 'int2', $all );
	}

	/**
	 * Test getting available integrations only returns available ones.
	 */
	public function test_get_available_integrations(): void {
		$available   = new Mock_Integration( $this->connection, $this->cache, 'available', 'Available', true );
		$unavailable = new Mock_Integration( $this->connection, $this->cache, 'unavailable', 'Unavailable', false );

		$this->manager->register( $available );
		$this->manager->register( $unavailable );

		$active = $this->manager->get_available();

		$this->assertCount( 1, $active );
		$this->assertArrayHasKey( 'available', $active );
		$this->assertArrayNotHasKey( 'unavailable', $active );
	}

	/**
	 * Test querying by capability.
	 */
	public function test_get_by_capability(): void {
		$media   = new Mock_Integration( $this->connection, $this->cache, 'media', 'Media', true, array( 'media_detection' ) );
		$cleanup = new Mock_Integration( $this->connection, $this->cache, 'cleanup', 'Cleanup', true, array( 'database_cleanup' ) );
		$both    = new Mock_Integration( $this->connection, $this->cache, 'both', 'Both', true, array( 'media_detection', 'database_cleanup' ) );

		$this->manager->register( $media );
		$this->manager->register( $cleanup );
		$this->manager->register( $both );

		$media_integrations = $this->manager->get_by_capability( 'media_detection' );

		$this->assertCount( 2, $media_integrations );
		$this->assertArrayHasKey( 'media', $media_integrations );
		$this->assertArrayHasKey( 'both', $media_integrations );
	}

	/**
	 * Test duplicate registration replaces existing integration.
	 */
	public function test_duplicate_registration_replaces(): void {
		$integration1 = new Mock_Integration( $this->connection, $this->cache, 'duplicate', 'First' );
		$integration2 = new Mock_Integration( $this->connection, $this->cache, 'duplicate', 'Second' );

		$this->manager->register( $integration1 );
		$this->manager->register( $integration2 );

		// New registration should replace the old.
		$retrieved = $this->manager->get( 'duplicate' );
		$this->assertEquals( 'Second', $retrieved->get_name() );
	}

	/**
	 * Test initialization initializes available integrations.
	 */
	public function test_init(): void {
		$available   = new Mock_Integration( $this->connection, $this->cache, 'available', 'Available', true );
		$unavailable = new Mock_Integration( $this->connection, $this->cache, 'unavailable', 'Unavailable', false );

		$this->manager->register( $available );
		$this->manager->register( $unavailable );

		$this->manager->init();

		// Available integration should be initialized.
		$this->assertTrue( $available->is_initialized() );
		// Unavailable should not.
		$this->assertFalse( $unavailable->is_initialized() );
	}

	/**
	 * Test count method.
	 */
	public function test_count(): void {
		$this->assertEquals( 0, $this->manager->count() );

		$this->manager->register( new Mock_Integration( $this->connection, $this->cache, 'one', 'One' ) );
		$this->assertEquals( 1, $this->manager->count() );

		$this->manager->register( new Mock_Integration( $this->connection, $this->cache, 'two', 'Two' ) );
		$this->assertEquals( 2, $this->manager->count() );
	}

	/**
	 * Test getting integration info.
	 */
	public function test_get_integration_info(): void {
		$integration = new Mock_Integration(
			$this->connection,
			$this->cache,
			'test',
			'Test Integration',
			true,
			array( 'media_detection', 'database_cleanup' )
		);
		$this->manager->register( $integration );

		$info = $this->manager->get_integration_info();

		$this->assertCount( 1, $info );
		$this->assertEquals( 'test', $info[0]['id'] );
		$this->assertEquals( 'Test Integration', $info[0]['name'] );
		$this->assertTrue( $info[0]['available'] );
		$this->assertContains( 'media_detection', $info[0]['capabilities'] );
	}
}
