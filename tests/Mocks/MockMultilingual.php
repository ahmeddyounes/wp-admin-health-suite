<?php
/**
 * Mock Multilingual Integration for Unit Testing
 *
 * Provides a testable implementation of the Multilingual integration
 * that doesn't require WPML or Polylang to be active.
 *
 * @package WPAdminHealth\Tests\Mocks
 */

namespace WPAdminHealth\Tests\Mocks;

use WPAdminHealth\Integrations\Multilingual;
use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;

/**
 * Mock Multilingual integration for testing.
 *
 * Simulates WPML/Polylang behavior for unit tests.
 */
class MockMultilingual extends Multilingual {

	/**
	 * Whether the integration is available.
	 *
	 * @var bool
	 */
	private bool $available = true;

	/**
	 * Available languages.
	 *
	 * @var array<string>
	 */
	private array $languages = array( 'en', 'fr', 'de' );

	/**
	 * Current language.
	 *
	 * @var string
	 */
	private string $current_language = 'en';

	/**
	 * Translation map (attachment_id => array of translation IDs).
	 *
	 * @var array<int, array<int>>
	 */
	private array $translations = array();

	/**
	 * Attachment language map (attachment_id => language code).
	 *
	 * @var array<int, string>
	 */
	private array $attachment_languages = array();

	/**
	 * Constructor.
	 *
	 * @param ConnectionInterface $connection Database connection.
	 * @param CacheInterface      $cache      Cache instance.
	 */
	public function __construct(
		ConnectionInterface $connection,
		CacheInterface $cache
	) {
		// Intentionally skip parent constructor to avoid WPML/Polylang detection.
		// We set up our own mock state instead.
	}

	/**
	 * Set whether the integration is available.
	 *
	 * @param bool $available Availability status.
	 * @return void
	 */
	public function set_available( bool $available ): void {
		$this->available = $available;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return $this->available;
	}

	/**
	 * Set available languages.
	 *
	 * @param array<string> $languages Language codes.
	 * @return void
	 */
	public function set_languages( array $languages ): void {
		$this->languages = $languages;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_languages(): array {
		return $this->languages;
	}

	/**
	 * Set current language.
	 *
	 * @param string $language Language code.
	 * @return void
	 */
	public function set_current_language( string $language ): void {
		$this->current_language = $language;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_current_language(): string {
		return $this->current_language;
	}

	/**
	 * Set translations for an attachment.
	 *
	 * @param int        $attachment_id  Original attachment ID.
	 * @param array<int> $translation_ids Array of translation IDs (including original).
	 * @return void
	 */
	public function set_translations( int $attachment_id, array $translation_ids ): void {
		$this->translations[ $attachment_id ] = $translation_ids;
		// Also set reverse mappings.
		foreach ( $translation_ids as $tid ) {
			$this->translations[ $tid ] = $translation_ids;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_translations( int $post_id ): array {
		if ( isset( $this->translations[ $post_id ] ) ) {
			return $this->translations[ $post_id ];
		}
		return array( $post_id );
	}

	/**
	 * Set language for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $language      Language code.
	 * @return void
	 */
	public function set_attachment_language( int $attachment_id, string $language ): void {
		$this->attachment_languages[ $attachment_id ] = $language;
	}

	/**
	 * {@inheritdoc}
	 */
	public function filter_attachments_by_language( array $attachments, string $language = '' ): array {
		if ( empty( $language ) ) {
			$language = $this->current_language;
		}

		if ( empty( $language ) || empty( $attachments ) ) {
			return $attachments;
		}

		return array_filter(
			$attachments,
			function ( $attachment_id ) use ( $language ) {
				// If no language set for attachment, include it.
				if ( ! isset( $this->attachment_languages[ $attachment_id ] ) ) {
					return true;
				}
				return $this->attachment_languages[ $attachment_id ] === $language;
			}
		);
	}

	/**
	 * Reset mock state.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->available            = true;
		$this->languages            = array( 'en', 'fr', 'de' );
		$this->current_language     = 'en';
		$this->translations         = array();
		$this->attachment_languages = array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'mock-multilingual';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'Mock Multilingual';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_min_version(): string {
		return '1.0.0';
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
	public function get_capabilities(): array {
		return array( 'media_detection' );
	}
}
