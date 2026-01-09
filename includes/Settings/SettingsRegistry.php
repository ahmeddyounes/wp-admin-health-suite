<?php
/**
 * Settings Registry
 *
 * Aggregates and manages all domain-specific settings.
 *
 * @package WPAdminHealth\Settings
 */

namespace WPAdminHealth\Settings;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Settings\Contracts\DomainSettingsInterface;
use WPAdminHealth\Settings\Contracts\SettingsRegistryInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class SettingsRegistry
 *
 * Aggregates domain settings and provides unified access.
 * Implements both SettingsRegistryInterface and SettingsInterface for backward compatibility.
 *
 * @since 1.2.0
 */
class SettingsRegistry implements SettingsRegistryInterface, SettingsInterface {

	/**
	 * Option name for storing plugin settings.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'wpha_settings';

	/**
	 * Registered domain settings.
	 *
	 * @var array<string, DomainSettingsInterface>
	 */
	private array $domains = array();

	/**
	 * Cached merged settings.
	 *
	 * @var array|null
	 */
	private ?array $cached_settings = null;

	/**
	 * Cached sections.
	 *
	 * @var array|null
	 */
	private ?array $cached_sections = null;

	/**
	 * Cached fields.
	 *
	 * @var array|null
	 */
	private ?array $cached_fields = null;

	/**
	 * {@inheritdoc}
	 */
	public function register( DomainSettingsInterface $domain ): void {
		$this->domains[ $domain->get_domain() ] = $domain;
		$this->clear_cache();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_domain( string $domain ): ?DomainSettingsInterface {
		return $this->domains[ $domain ] ?? null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_domains(): array {
		return $this->domains;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_all_settings(): array {
		$settings = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $settings, $this->get_default_settings() );
	}

	/**
	 * Alias for get_all_settings for backward compatibility.
	 *
	 * @return array All settings.
	 */
	public function get_settings(): array {
		return $this->get_all_settings();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_all_sections(): array {
		if ( null !== $this->cached_sections ) {
			return $this->cached_sections;
		}

		$this->cached_sections = array();
		foreach ( $this->domains as $domain_id => $domain ) {
			$this->cached_sections[ $domain_id ] = $domain->get_section();
		}

		return $this->cached_sections;
	}

	/**
	 * Alias for get_all_sections for backward compatibility.
	 *
	 * @return array All sections.
	 */
	public function get_sections(): array {
		return $this->get_all_sections();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_all_fields(): array {
		if ( null !== $this->cached_fields ) {
			return $this->cached_fields;
		}

		$this->cached_fields = array();
		foreach ( $this->domains as $domain ) {
			$this->cached_fields = array_merge( $this->cached_fields, $domain->get_fields() );
		}

		return $this->cached_fields;
	}

	/**
	 * Alias for get_all_fields for backward compatibility.
	 *
	 * @return array All fields.
	 */
	public function get_fields(): array {
		return $this->get_all_fields();
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed Setting value.
	 */
	public function get_setting( string $key, $default = null ) {
		$settings = $this->get_all_settings();

		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		if ( null !== $default ) {
			return $default;
		}

		$fields = $this->get_all_fields();
		return $fields[ $key ]['default'] ?? null;
	}

	/**
	 * Get default settings for all domains.
	 *
	 * @return array Merged defaults from all domains.
	 */
	public function get_default_settings(): array {
		$defaults = array();
		foreach ( $this->domains as $domain ) {
			$defaults = array_merge( $defaults, $domain->get_defaults() );
		}
		return $defaults;
	}

	/**
	 * Check if safe mode is enabled.
	 *
	 * @return bool True if safe mode is enabled.
	 */
	public function is_safe_mode_enabled(): bool {
		return (bool) $this->get_setting( 'safe_mode', false );
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool True if debug mode is enabled.
	 */
	public function is_debug_mode_enabled(): bool {
		return (bool) $this->get_setting( 'debug_mode', false );
	}

	/**
	 * Check if REST API is enabled.
	 *
	 * @return bool True if REST API is enabled.
	 */
	public function is_rest_api_enabled(): bool {
		return (bool) $this->get_setting( 'enable_rest_api', true );
	}

	/**
	 * Get REST API rate limit.
	 *
	 * @return int Requests per minute.
	 */
	public function get_rest_api_rate_limit(): int {
		return absint( $this->get_setting( 'rest_api_rate_limit', 60 ) );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();
		$fields    = $this->get_all_fields();

		foreach ( $fields as $field_id => $field ) {
			$value = $input[ $field_id ] ?? $field['default'];

			switch ( $field['sanitize'] ?? 'text' ) {
				case 'boolean':
					$sanitized[ $field_id ] = (bool) $value;
					break;

				case 'integer':
					$sanitized[ $field_id ] = absint( $value );
					if ( isset( $field['min'] ) && $sanitized[ $field_id ] < $field['min'] ) {
						$sanitized[ $field_id ] = $field['min'];
					}
					if ( isset( $field['max'] ) && $sanitized[ $field_id ] > $field['max'] ) {
						$sanitized[ $field_id ] = $field['max'];
					}
					break;

				case 'email':
					$sanitized[ $field_id ] = sanitize_email( $value );
					if ( ! empty( $sanitized[ $field_id ] ) && ! is_email( $sanitized[ $field_id ] ) ) {
						$sanitized[ $field_id ] = $field['default'];
					}
					break;

				case 'select':
					if ( isset( $field['options'] ) && array_key_exists( $value, $field['options'] ) ) {
						$sanitized[ $field_id ] = $value;
					} else {
						$sanitized[ $field_id ] = $field['default'];
					}
					break;

				case 'css':
					$sanitized[ $field_id ] = $this->sanitize_css( $value );
					break;

				case 'textarea':
					$sanitized[ $field_id ] = sanitize_textarea_field( $value );
					break;

				case 'text':
				default:
					$sanitized[ $field_id ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize CSS input.
	 *
	 * @param string $css CSS code to sanitize.
	 * @return string Sanitized CSS.
	 */
	private function sanitize_css( string $css ): string {
		$css = wp_strip_all_tags( $css );
		$css = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $css );
		$css = preg_replace( '/javascript:/i', '', $css );
		$css = preg_replace( '/expression\s*\(/i', '', $css );
		$css = preg_replace( '/import\s+/i', '', $css );

		return trim( $css );
	}

	/**
	 * Clear the settings cache.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cached_settings = null;
		$this->cached_sections = null;
		$this->cached_fields   = null;
	}
}
