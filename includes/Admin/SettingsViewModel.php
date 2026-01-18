<?php
/**
 * Settings View Model
 *
 * Provides template-ready data for the settings page without requiring
 * direct facade instantiation in templates.
 *
 * @package WPAdminHealth\Admin
 */

namespace WPAdminHealth\Admin;

use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\Settings\SettingsRegistry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class SettingsViewModel
 *
 * Prepares settings data for template rendering. This class bridges the gap between
 * the SettingsInterface contract (data access) and template-specific rendering needs
 * (HTML field generation).
 *
 * Unlike the Settings facade, this class is designed to be injected via the container
 * and passed to templates as data, eliminating service location in templates.
 *
 * @since 1.5.0
 */
class SettingsViewModel {

	/**
	 * Settings instance.
	 *
	 * @var SettingsInterface
	 */
	private SettingsInterface $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.5.0
	 *
	 * @param SettingsInterface $settings Settings instance.
	 */
	public function __construct( SettingsInterface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get settings sections.
	 *
	 * @since 1.5.0
	 *
	 * @return array Settings sections.
	 */
	public function get_sections(): array {
		return $this->settings->get_sections();
	}

	/**
	 * Get all settings with defaults applied.
	 *
	 * @since 1.5.0
	 *
	 * @return array All settings values.
	 */
	public function get_settings(): array {
		return $this->settings->get_settings();
	}

	/**
	 * Get all field definitions.
	 *
	 * @since 1.5.0
	 *
	 * @return array Field definitions.
	 */
	public function get_fields(): array {
		return $this->settings->get_fields();
	}

	/**
	 * Get a specific setting value.
	 *
	 * @since 1.5.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed Setting value.
	 */
	public function get_setting( string $key, $default = null ) {
		return $this->settings->get_setting( $key, $default );
	}

	/**
	 * Render a settings field as HTML.
	 *
	 * This method generates the HTML for a settings field based on its type.
	 * Field types supported: checkbox, number, text, email, select, textarea.
	 *
	 * @since 1.5.0
	 *
	 * @param array $args Field arguments with 'id' and 'field' keys.
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

	/**
	 * Get template-ready data for the settings page.
	 *
	 * Returns all data needed by the settings template in a single array,
	 * useful when passing data via PageRenderer::render().
	 *
	 * @since 1.5.0
	 *
	 * @return array Template data including sections, settings, and fields.
	 */
	public function get_template_data(): array {
		return array(
			'sections' => $this->get_sections(),
			'settings' => $this->get_settings(),
			'fields'   => $this->get_fields(),
		);
	}
}
