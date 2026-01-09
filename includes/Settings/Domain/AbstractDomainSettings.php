<?php
/**
 * Abstract Domain Settings
 *
 * Base class for domain-specific settings.
 *
 * @package WPAdminHealth\Settings\Domain
 */

namespace WPAdminHealth\Settings\Domain;

use WPAdminHealth\Settings\Contracts\DomainSettingsInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class AbstractDomainSettings
 *
 * Abstract base class providing common functionality for domain settings.
 *
 * @since 1.2.0
 */
abstract class AbstractDomainSettings implements DomainSettingsInterface {

	/**
	 * Option name for storing all plugin settings.
	 *
	 * @var string
	 */
	protected const OPTION_NAME = 'wpha_settings';

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	protected static ?array $cached_settings = null;

	/**
	 * Domain identifier.
	 *
	 * @var string
	 */
	protected string $domain;

	/**
	 * Section definition.
	 *
	 * @var array
	 */
	protected array $section;

	/**
	 * Field definitions.
	 *
	 * @var array
	 */
	protected array $fields;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->domain  = $this->define_domain();
		$this->section = $this->define_section();
		$this->fields  = $this->define_fields();
	}

	/**
	 * Define the domain identifier.
	 *
	 * @return string Domain identifier.
	 */
	abstract protected function define_domain(): string;

	/**
	 * Define the section configuration.
	 *
	 * @return array Section definition.
	 */
	abstract protected function define_section(): array;

	/**
	 * Define the field configurations.
	 *
	 * @return array Field definitions.
	 */
	abstract protected function define_fields(): array;

	/**
	 * {@inheritdoc}
	 */
	public function get_domain(): string {
		return $this->domain;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_section(): array {
		return $this->section;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields(): array {
		return $this->fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_defaults(): array {
		$defaults = array();
		foreach ( $this->fields as $field_id => $field ) {
			$defaults[ $field_id ] = $field['default'] ?? null;
		}
		return $defaults;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get( string $key, $default = null ) {
		$settings = $this->get_all_stored_settings();

		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		if ( null !== $default ) {
			return $default;
		}

		return $this->fields[ $key ]['default'] ?? null;
	}

	/**
	 * Get all stored settings from the database.
	 *
	 * @return array All stored settings.
	 */
	protected function get_all_stored_settings(): array {
		if ( null === self::$cached_settings ) {
			self::$cached_settings = get_option( self::OPTION_NAME, array() );
		}
		return self::$cached_settings;
	}

	/**
	 * Clear the settings cache.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$cached_settings = null;
	}
}
