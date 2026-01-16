<?php
/**
 * Mock Exclusions Manager for Unit Testing
 *
 * Provides a testable implementation of ExclusionsInterface
 * that doesn't require WordPress functions.
 *
 * @package WPAdminHealth\Tests\Mocks
 */

namespace WPAdminHealth\Tests\Mocks;

use WPAdminHealth\Contracts\ExclusionsInterface;

/**
 * Mock exclusions manager for testing.
 *
 * Stores exclusions in memory and allows setting up test scenarios.
 */
class MockExclusions implements ExclusionsInterface {

	/**
	 * Stored exclusions.
	 *
	 * @var array<int, array{id: int, reason: string, added_at: string}>
	 */
	private array $exclusions = array();

	/**
	 * {@inheritdoc}
	 */
	public function add_exclusion( int $attachment_id, string $reason = '' ): bool {
		if ( $attachment_id <= 0 ) {
			return false;
		}

		if ( isset( $this->exclusions[ $attachment_id ] ) ) {
			return false;
		}

		$this->exclusions[ $attachment_id ] = array(
			'id'       => $attachment_id,
			'reason'   => $reason,
			'added_at' => gmdate( 'Y-m-d H:i:s' ),
		);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function remove_exclusion( int $attachment_id ): bool {
		if ( ! isset( $this->exclusions[ $attachment_id ] ) ) {
			return false;
		}

		unset( $this->exclusions[ $attachment_id ] );
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_exclusions(): array {
		return array_values( $this->exclusions );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_excluded( int $attachment_id ): bool {
		return isset( $this->exclusions[ $attachment_id ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function bulk_add_exclusions( array $attachment_ids, string $reason = '' ): array {
		$success = 0;
		$failed  = array();

		foreach ( $attachment_ids as $id ) {
			if ( $this->add_exclusion( $id, $reason ) ) {
				++$success;
			} else {
				$failed[] = $id;
			}
		}

		return array(
			'success' => $success,
			'failed'  => $failed,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear_exclusions(): bool {
		$this->exclusions = array();
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function filter_excluded( array $attachment_ids ): array {
		return array_filter(
			$attachment_ids,
			function ( $id ) {
				return ! $this->is_excluded( $id );
			}
		);
	}

	/**
	 * Reset mock state.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->exclusions = array();
	}
}
