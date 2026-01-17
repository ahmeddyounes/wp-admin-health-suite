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
		if ( null !== $this->cached_settings ) {
			return $this->cached_settings;
		}

		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = $this->migrate_legacy_settings( $settings );

		// Only keep known setting keys to prevent option injection and stale keys.
		$known_fields = $this->get_all_fields();
		if ( ! empty( $known_fields ) ) {
			$settings = array_intersect_key( $settings, $known_fields );
		}

		$this->cached_settings = wp_parse_args( $settings, $this->get_default_settings() );
		return $this->cached_settings;
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
		// Allow wp-config.php to force safe mode on/off.
		if ( defined( 'WPHA_SAFE_MODE' ) ) {
			return (bool) WPHA_SAFE_MODE;
		}

		return (bool) $this->get_setting( 'safe_mode', false );
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool True if debug mode is enabled.
	 */
	public function is_debug_mode_enabled(): bool {
		// Allow wp-config.php to force debug mode on/off.
		if ( defined( 'WPHA_DEBUG_MODE' ) ) {
			return (bool) WPHA_DEBUG_MODE;
		}

		return (bool) $this->get_setting( 'debug_mode', false );
	}

	/**
	 * Check if REST API is enabled.
	 *
	 * @return bool True if REST API is enabled.
	 */
	public function is_rest_api_enabled(): bool {
		// Allow wp-config.php to disable the REST API regardless of settings.
		if ( defined( 'WPHA_DISABLE_REST_API' ) && WPHA_DISABLE_REST_API ) {
			return false;
		}

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
		$stored    = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$input  = $this->migrate_legacy_settings( $input );
		$stored = $this->migrate_legacy_settings( $stored );

		foreach ( $fields as $field_id => $field ) {
			$has_input_value = array_key_exists( $field_id, $input );
			$value           = null;

			// Preserve existing settings for fields not present in the submitted payload.
			// This is required because the settings UI saves per-tab, not as a single form.
			if ( $has_input_value ) {
				$value = $input[ $field_id ];
			} elseif ( array_key_exists( $field_id, $stored ) ) {
				$value = $stored[ $field_id ];
			} else {
				$value = $field['default'] ?? null;
			}

			$sanitized[ $field_id ] = $this->sanitize_field_value( $value, $field );
		}

		// Clear any cached settings since we're returning the next canonical value.
		$this->cached_settings = null;

		return $sanitized;
	}

	/**
	 * Build a REST/Options API schema for the settings option.
	 *
	 * Intended for use with register_setting() => show_in_rest schema.
	 *
	 * @since 1.2.0
	 *
	 * @return array JSON schema for the settings option.
	 */
	public function get_option_schema(): array {
		$properties = array();
		foreach ( $this->get_all_fields() as $field_id => $field ) {
			$properties[ $field_id ] = $this->build_field_schema( $field );
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
	}

	/**
	 * Sanitize CSS input.
	 *
	 * @param string $css CSS code to sanitize.
	 * @return string Sanitized CSS.
	 */
	private function sanitize_css( string $css ): string {
		// Strip all HTML tags first (prevents </style><script> attacks).
		$css = wp_strip_all_tags( $css );

		// Remove any remaining HTML entities that could be decoded.
		$css = wp_kses( $css, array() );

		// Block style tag breakout attempts.
		$css = preg_replace( '/<\s*\/?\s*style/i', '', $css );

		// Remove JavaScript protocol and expressions.
		$css = preg_replace( '/javascript\s*:/i', '', $css );
		$css = preg_replace( '/expression\s*\(/i', '', $css );
		$css = preg_replace( '/behavior\s*:/i', '', $css );
		$css = preg_replace( '/-moz-binding\s*:/i', '', $css );

		// Block @import which could load external malicious CSS.
		$css = preg_replace( '/@import\b/i', '', $css );

		// Block @charset which could enable encoding attacks.
		$css = preg_replace( '/@charset\b/i', '', $css );

		// Remove any null bytes.
		$css = str_replace( "\0", '', $css );

		// Remove any remaining angle brackets.
		$css = str_replace( array( '<', '>' ), '', $css );

		return trim( $css );
	}

	/**
	 * Sanitize a single field value based on its field definition.
	 *
	 * @param mixed $value Raw value.
	 * @param array $field Field definition.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_field_value( $value, array $field ) {
		$default  = $field['default'] ?? null;
		$sanitize = $field['sanitize'] ?? 'text';

		// Reject non-scalar input for scalar field types.
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = $default;
		}

		switch ( $sanitize ) {
			case 'boolean':
				if ( null === $value ) {
					return (bool) $default;
				}

				$bool = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				return null === $bool ? (bool) $default : $bool;

			case 'integer':
				if ( null === $value || '' === $value ) {
					$value = $default;
				}

				$int = is_numeric( $value ) ? (int) $value : (int) $default;
				if ( isset( $field['min'] ) && $int < $field['min'] ) {
					$int = (int) $field['min'];
				}
				if ( isset( $field['max'] ) && $int > $field['max'] ) {
					$int = (int) $field['max'];
				}
				return $int;

			case 'integer_list':
				if ( null === $value ) {
					return (string) $default;
				}

				$raw = wp_strip_all_tags( (string) $value );
				$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );

				preg_match_all( '/\d+/', $raw, $matches );

				$ints = array();
				if ( isset( $matches[0] ) && is_array( $matches[0] ) ) {
					foreach ( $matches[0] as $match ) {
						$int = absint( $match );
						if ( $int <= 0 ) {
							continue;
						}
						$ints[] = $int;
					}
				}

				$ints = array_values( array_unique( $ints ) );

				$max_items = isset( $field['max_items'] ) ? absint( $field['max_items'] ) : 100;
				if ( $max_items > 0 ) {
					$ints = array_slice( $ints, 0, $max_items );
				}

				return implode( "\n", array_map( 'strval', $ints ) );

			case 'email':
				$email = sanitize_email( (string) $value );
				if ( '' !== $email && ! is_email( $email ) ) {
					return (string) $default;
				}
				return $email;

			case 'select':
				if ( isset( $field['options'] ) && array_key_exists( $value, $field['options'] ) ) {
					return $value;
				}
				return $default;

			case 'css':
				return $this->sanitize_css( (string) $value );

			case 'newline_list':
				if ( null === $value ) {
					return (string) $default;
				}

				$raw = wp_strip_all_tags( (string) $value );
				$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );

				$items = array_map( 'trim', explode( "\n", $raw ) );
				$items = array_filter( $items, 'strlen' );

				$sanitized_items = array();
				foreach ( $items as $item ) {
					$item = sanitize_text_field( $item );
					$item = preg_replace( '/\s+/', '', $item );
					$item = trim( $item );

					if ( '' === $item ) {
						continue;
					}

					$sanitized_items[] = $item;
				}

				$sanitized_items = array_values( array_unique( $sanitized_items ) );

				$max_items = isset( $field['max_items'] ) ? absint( $field['max_items'] ) : 100;
				if ( $max_items > 0 ) {
					$sanitized_items = array_slice( $sanitized_items, 0, $max_items );
				}

				return implode( "\n", $sanitized_items );

			case 'line_list':
				if ( null === $value ) {
					return (string) $default;
				}

				$raw = wp_strip_all_tags( (string) $value );
				$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );

				$items = array_map( 'trim', explode( "\n", $raw ) );
				$items = array_filter( $items, 'strlen' );

				$sanitized_items = array();
				foreach ( $items as $item ) {
					$item = sanitize_text_field( $item );
					$item = trim( $item );

					if ( '' === $item ) {
						continue;
					}

					$sanitized_items[] = $item;
				}

				$sanitized_items = array_values( array_unique( $sanitized_items ) );

				$max_items = isset( $field['max_items'] ) ? absint( $field['max_items'] ) : 100;
				if ( $max_items > 0 ) {
					$sanitized_items = array_slice( $sanitized_items, 0, $max_items );
				}

				return implode( "\n", $sanitized_items );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Migrate legacy settings keys to current keys.
	 *
	 * @since 1.5.0
	 *
	 * @param array $settings Raw settings.
	 * @return array Settings with legacy keys migrated.
	 */
	private function migrate_legacy_settings( array $settings ): array {
		if (
			! array_key_exists( 'orphaned_cleanup_enabled', $settings )
			&& array_key_exists( 'cleanup_orphaned_metadata', $settings )
		) {
			$settings['orphaned_cleanup_enabled'] = (bool) $settings['cleanup_orphaned_metadata'];
		}

		if (
			! array_key_exists( 'media_trash_retention_days', $settings )
			&& array_key_exists( 'media_retention_days', $settings )
		) {
			$settings['media_trash_retention_days'] = absint( $settings['media_retention_days'] );
		}

		return $settings;
	}

	/**
	 * Convert a field definition to a JSON schema fragment.
	 *
	 * @param array $field Field definition.
	 * @return array Schema fragment.
	 */
	private function build_field_schema( array $field ): array {
		$sanitize = $field['sanitize'] ?? 'text';

		switch ( $sanitize ) {
			case 'boolean':
				$schema = array(
					'type'    => 'boolean',
					'default' => (bool) ( $field['default'] ?? false ),
				);
				break;

			case 'integer':
				$schema = array(
					'type'    => 'integer',
					'default' => absint( $field['default'] ?? 0 ),
				);
				if ( isset( $field['min'] ) ) {
					$schema['minimum'] = (int) $field['min'];
				}
				if ( isset( $field['max'] ) ) {
					$schema['maximum'] = (int) $field['max'];
				}
				break;

			case 'email':
				$schema = array(
					'type'    => 'string',
					'format'  => 'email',
					'default' => (string) ( $field['default'] ?? '' ),
				);
				break;

			case 'select':
				$schema = array(
					'type'    => 'string',
					'default' => (string) ( $field['default'] ?? '' ),
				);
				if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
					$schema['enum'] = array_map( 'strval', array_keys( $field['options'] ) );
				}
				break;

			case 'css':
			case 'textarea':
			case 'text':
			default:
				$schema = array(
					'type'    => 'string',
					'default' => (string) ( $field['default'] ?? '' ),
				);
				break;
		}

		if ( ! empty( $field['description'] ) && is_string( $field['description'] ) ) {
			$schema['description'] = $field['description'];
		}

		return $schema;
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
