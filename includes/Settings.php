<?php
/**
 * Settings Facade
 *
 * Template-layer facade providing both data access (via SettingsInterface) and HTML rendering
 * (via render_field()) for admin settings pages.
 *
 * EDGE ADAPTER: This class uses Plugin::get_instance()->get_container() as a service locator
 * because it is instantiated directly in templates (e.g., templates/admin/settings.php) where
 * dependency injection is not feasible. The plugin's DI container is guaranteed to be available
 * when templates are rendered since they execute during admin page callbacks after plugin init.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Settings\SettingsRegistry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class Settings
 *
 * Provides a simple interface for templates to read and render settings.
 * This facade wraps SettingsInterface for data access and adds HTML rendering capabilities.
 *
 * Note: This is an edge adapter that uses service location because templates instantiate
 * it directly. For application code, inject SettingsInterface via the container instead.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface
	 */
	private SettingsInterface $settings;

	/**
	 * Constructor.
	 *
	 * Resolves SettingsInterface from the plugin container if not injected.
	 * This service location is necessary for template compatibility.
	 *
	 * @param SettingsInterface|null $settings Optional settings instance for dependency injection.
	 */
	public function __construct( ?SettingsInterface $settings = null ) {
		if ( null === $settings ) {
			// Edge adapter: Resolve from container for template usage.
			// Templates instantiate this class directly without DI support.
			$settings = Plugin::get_instance()->get_container()->get( SettingsInterface::class );
		}

		$this->settings = $settings;
	}

	/**
	 * Get settings sections.
	 *
	 * @return array
	 */
	public function get_sections(): array {
		return $this->settings->get_sections();
	}

	/**
	 * Get all settings with defaults applied.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		return $this->settings->get_settings();
	}

	/**
	 * Get all field definitions.
	 *
	 * @return array
	 */
	public function get_fields(): array {
		return $this->settings->get_fields();
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed
	 */
	public function get_setting( string $key, $default = null ) {
		return $this->settings->get_setting( $key, $default );
	}

	/**
	 * Render a settings field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field( array $args ): void {
		$field_id = $args['id'];
		$field    = $args['field'];
		$settings = $this->settings->get_settings();
		$value    = $settings[ $field_id ] ?? $field['default'];

		$name = SettingsRegistry::OPTION_NAME . '[' . $field_id . ']';
		$id   = 'wpha_' . $field_id;

		switch ( $field['type'] ) {
			case 'checkbox':
				// Ensure a value is always submitted (unchecked checkboxes submit nothing).
				printf(
					'<input type="hidden" name="%s" value="0" />',
					esc_attr( $name )
				);
				printf(
					'<input type="checkbox" id="%s" name="%s" value="1" %s />',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( $value, true, false )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					isset( $field['min'] ) ? esc_attr( $field['min'] ) : '',
					isset( $field['max'] ) ? esc_attr( $field['max'] ) : ''
				);
				break;

			case 'text':
				printf(
					'<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'email':
				printf(
					'<input type="email" id="%s" name="%s" value="%s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'select':
				printf(
					'<select id="%s" name="%s">',
					esc_attr( $id ),
					esc_attr( $name )
				);
				foreach ( $field['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $option_value ),
						selected( $value, $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="5" class="large-text">%s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( $value )
				);
				break;
		}

		if ( ! empty( $field['description'] ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( $field['description'] )
			);
		}
	}
}

