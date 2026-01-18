<?php
/**
 * Test Integration
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Mocks;

use WPAdminHealth\Contracts\IntegrationInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Test-only integration used for bootstrap regression tests.
 */
class TestIntegration implements IntegrationInterface {
	/**
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'test_integration';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'Test Integration';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_min_version(): string {
		return '0.0.0';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_current_version(): ?string {
		return '1.0.0';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_compatible(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_dependencies(): array {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_capabilities(): array {
		return array( 'media_detection' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function has_capability( string $capability ): bool {
		return in_array( $capability, $this->get_capabilities(), true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_priority(): int {
		return 10;
	}

	/**
	 * {@inheritdoc}
	 */
	public function init(): void {
		$this->initialized = true;
		add_filter( 'wpha_media_is_attachment_used', array( $this, 'force_used' ), 10, 2 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function deactivate(): void {
		$this->initialized = false;
		remove_filter( 'wpha_media_is_attachment_used', array( $this, 'force_used' ), 10 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_initialized(): bool {
		return $this->initialized;
	}

	/**
	 * Filter callback to force attachments as "used".
	 *
	 * @param bool $is_used Existing usage state.
	 * @return bool
	 */
	public function force_used( bool $is_used, int $attachment_id ): bool {
		return true;
	}
}
