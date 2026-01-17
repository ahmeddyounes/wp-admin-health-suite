<?php
/**
 * Settings Service Provider
 *
 * Registers settings-related services.
 *
 * @package WPAdminHealth\Settings
 */

namespace WPAdminHealth\Settings;

use WPAdminHealth\Container\ServiceProvider;
use WPAdminHealth\Contracts\SettingsInterface;
use WPAdminHealth\HealthCalculator;
use WPAdminHealth\Settings\Contracts\SettingsRegistryInterface;
use WPAdminHealth\Settings\Domain\CoreSettings;
use WPAdminHealth\Settings\Domain\DatabaseSettings;
use WPAdminHealth\Settings\Domain\MediaSettings;
use WPAdminHealth\Settings\Domain\PerformanceSettings;
use WPAdminHealth\Settings\Domain\SchedulingSettings;
use WPAdminHealth\Settings\Domain\AdvancedSettings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class SettingsServiceProvider
 *
 * Registers settings registry and domain settings.
 *
 * @since 1.2.0
 */
class SettingsServiceProvider extends ServiceProvider {

	/**
	 * Whether this provider should be deferred.
	 *
	 * @var bool
	 */
	protected bool $deferred = false;

	/**
	 * Services provided by this provider.
	 *
	 * @var array<string>
	 */
	protected array $provides = array(
		SettingsInterface::class,
		SettingsRegistryInterface::class,
		'settings.registry',
		'settings.core',
		'settings.database',
		'settings.media',
		'settings.performance',
		'settings.scheduling',
		'settings.advanced',
	);

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Register SettingsRegistry as singleton.
		$this->container->singleton(
			SettingsRegistryInterface::class,
			function () {
				$registry = new SettingsRegistry();

				// Register all domain settings.
				$registry->register( new CoreSettings() );
				$registry->register( new DatabaseSettings() );
				$registry->register( new MediaSettings() );
				$registry->register( new PerformanceSettings() );
				$registry->register( new SchedulingSettings() );
				$registry->register( new AdvancedSettings() );

				return $registry;
			}
		);

		// Alias SettingsInterface to use the registry.
		$this->container->alias( SettingsInterface::class, SettingsRegistryInterface::class );
		$this->container->alias( 'settings.registry', SettingsRegistryInterface::class );

		// Register individual domain settings for direct access.
		$this->container->bind(
			'settings.core',
			function () {
				return new CoreSettings();
			}
		);

		$this->container->bind(
			'settings.database',
			function () {
				return new DatabaseSettings();
			}
		);

		$this->container->bind(
			'settings.media',
			function () {
				return new MediaSettings();
			}
		);

		$this->container->bind(
			'settings.performance',
			function () {
				return new PerformanceSettings();
			}
		);

		$this->container->bind(
			'settings.scheduling',
			function () {
				return new SchedulingSettings();
			}
		);

		$this->container->bind(
			'settings.advanced',
			function () {
				return new AdvancedSettings();
			}
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// Register custom cron schedules for weekly and monthly intervals.
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );

		// Register WordPress settings hooks.
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wpha_export_settings', array( $this, 'export_settings' ) );
		add_action( 'admin_post_wpha_import_settings', array( $this, 'import_settings' ) );
		add_action( 'admin_post_wpha_reset_settings', array( $this, 'reset_settings' ) );
		add_action( 'admin_post_wpha_reset_section', array( $this, 'reset_section' ) );
		add_action( 'update_option_' . SettingsRegistry::OPTION_NAME, array( $this, 'handle_scheduling_update' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'output_custom_css' ) );

		// Core UI features (controlled by settings).
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_menu' ), 90 );
	}

	/**
	 * Register the WordPress dashboard widget.
	 *
	 * Controlled by the `enable_dashboard_widget` setting.
	 *
	 * @return void
	 */
	public function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/** @var SettingsInterface $settings */
		$settings = $this->container->get( SettingsInterface::class );

		if ( ! (bool) $settings->get_setting( 'enable_dashboard_widget', true ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wpha_admin_health_widget',
			__( 'Admin Health', 'wp-admin-health-suite' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the WordPress dashboard widget content.
	 *
	 * @return void
	 */
	public function render_dashboard_widget(): void {
		/** @var SettingsInterface $settings */
		$settings = $this->container->get( SettingsInterface::class );

		/** @var HealthCalculator $health_calculator */
		$health_calculator = $this->container->get( HealthCalculator::class );
		$health_data       = $health_calculator->calculate_overall_score();

		$score     = isset( $health_data['score'] ) ? absint( $health_data['score'] ) : 0;
		$grade     = isset( $health_data['grade'] ) ? sanitize_text_field( (string) $health_data['grade'] ) : '';
		$timestamp = isset( $health_data['timestamp'] ) ? absint( $health_data['timestamp'] ) : 0;

		$threshold = absint( $settings->get_setting( 'health_score_threshold', 70 ) );

		echo '<p><strong>' . esc_html__( 'Health Score:', 'wp-admin-health-suite' ) . '</strong> ' . esc_html( $score ) . ( '' !== $grade ? ' <span>(' . esc_html( $grade ) . ')</span>' : '' ) . '</p>';

		if ( $threshold > 0 && $score > 0 && $score < $threshold ) {
			echo '<p class="description" style="color:#d63638;">' . esc_html__( 'Below your configured warning threshold.', 'wp-admin-health-suite' ) . '</p>';
		}

		if ( $timestamp > 0 ) {
			echo '<p class="description">' . esc_html__( 'Last updated:', 'wp-admin-health-suite' ) . ' ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ) . '</p>';
		}

		if ( $settings->is_safe_mode_enabled() ) {
			echo '<p class="description"><strong>' . esc_html__( 'Safe Mode is enabled.', 'wp-admin-health-suite' ) . '</strong> ' . esc_html__( 'Destructive operations will run in preview-only mode.', 'wp-admin-health-suite' ) . '</p>';
		}

		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=admin-health' ) ) . '">' . esc_html__( 'Open Admin Health Dashboard', 'wp-admin-health-suite' ) . '</a></p>';
	}

	/**
	 * Register the admin bar menu.
	 *
	 * Controlled by the `admin_bar_menu` setting.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public function register_admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/** @var SettingsInterface $settings */
		$settings = $this->container->get( SettingsInterface::class );

		if ( ! (bool) $settings->get_setting( 'admin_bar_menu', true ) ) {
			return;
		}

		$parent_id = 'wpha_admin_health';

		$wp_admin_bar->add_node(
			array(
				'id'    => $parent_id,
				'title' => __( 'Admin Health', 'wp-admin-health-suite' ),
				'href'  => admin_url( 'admin.php?page=admin-health' ),
			)
		);

		$items = array(
			'dashboard'  => array(
				'title' => __( 'Dashboard', 'wp-admin-health-suite' ),
				'href'  => admin_url( 'admin.php?page=admin-health' ),
			),
			'database'   => array(
				'title' => __( 'Database Health', 'wp-admin-health-suite' ),
				'href'  => admin_url( 'admin.php?page=admin-health-database' ),
			),
			'media'      => array(
				'title' => __( 'Media Audit', 'wp-admin-health-suite' ),
				'href'  => admin_url( 'admin.php?page=admin-health-media' ),
			),
			'performance' => array(
				'title' => __( 'Performance', 'wp-admin-health-suite' ),
				'href'  => admin_url( 'admin.php?page=admin-health-performance' ),
			),
			'settings'   => array(
				'title' => __( 'Settings', 'wp-admin-health-suite' ),
				'href'  => admin_url( 'admin.php?page=admin-health-settings' ),
			),
		);

		foreach ( $items as $id => $item ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => $parent_id,
					'id'     => $parent_id . '_' . $id,
					'title'  => $item['title'],
					'href'   => $item['href'],
				)
			);
		}

		if ( $settings->is_safe_mode_enabled() ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => $parent_id,
					'id'     => $parent_id . '_safe_mode',
					'title'  => __( 'Safe Mode: On', 'wp-admin-health-suite' ),
					'href'   => admin_url( 'admin.php?page=admin-health-settings&tab=advanced' ),
				)
			);
		}
	}

	/**
	 * Register custom cron schedules for weekly and monthly intervals.
	 *
	 * WordPress only provides hourly, twicedaily, and daily by default.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function register_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'wp-admin-health-suite' ),
			);
		}

		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'wp-admin-health-suite' ),
			);
		}

		return $schedules;
	}

	/**
	 * Register settings with WordPress.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		/** @var SettingsRegistry $registry */
		$registry = $this->container->get( SettingsRegistryInterface::class );

		// Register the main settings option.
		register_setting(
			'wpha_settings_group',
			SettingsRegistry::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $registry, 'sanitize_settings' ),
				'default'           => $registry->get_default_settings(),
				'show_in_rest'      => array(
					'schema' => $registry->get_option_schema(),
				),
			)
		);

		// Add settings sections.
		foreach ( $registry->get_sections() as $section_id => $section ) {
			add_settings_section(
				'wpha_section_' . $section_id,
				$section['title'],
				function () use ( $section ) {
					if ( ! empty( $section['description'] ) ) {
						echo '<p>' . esc_html( $section['description'] ) . '</p>';
					}
				},
				'wpha_settings'
			);
		}

		// Add settings fields.
		foreach ( $registry->get_fields() as $field_id => $field ) {
			add_settings_field(
				'wpha_field_' . $field_id,
				$field['title'],
				array( $this, 'render_field' ),
				'wpha_settings',
				'wpha_section_' . $field['section'],
				array(
					'id'    => $field_id,
					'field' => $field,
				)
			);
		}
	}

	/**
	 * Render a settings field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field( array $args ): void {
		/** @var SettingsRegistry $registry */
		$registry = $this->container->get( SettingsRegistryInterface::class );

		$field_id = $args['id'];
		$field    = $args['field'];
		$settings = $registry->get_settings();
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
	 * Export settings as JSON.
	 *
	 * @return void
	 */
	public function export_settings(): void {
		// Security: Verify nonce first to confirm request legitimacy,
		// then check capability to avoid timing-based information disclosure.
		check_admin_referer( 'wpha_export_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-admin-health-suite' ) );
		}

		/** @var SettingsRegistry $registry */
		$registry = $this->container->get( SettingsRegistryInterface::class );
		$settings = $registry->get_settings();

		$export = array(
			'version'   => WP_ADMIN_HEALTH_VERSION,
			'timestamp' => current_time( 'mysql' ),
			'settings'  => $settings,
		);

		$json = wp_json_encode( $export, JSON_PRETTY_PRINT );
		if ( false === $json ) {
			wp_die( esc_html__( 'Failed to encode settings export.', 'wp-admin-health-suite' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wpha-settings-' . gmdate( 'Y-m-d-His' ) . '.json' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON export response.
		echo $json;
		exit;
	}

	/**
	 * Import settings from JSON.
	 *
	 * Security: Applies strict validation to prevent malicious file imports:
	 * - MIME type verification
	 * - File extension check
	 * - File size limits
	 * - JSON structure validation
	 * - Unknown key rejection
	 * - Settings sanitization
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Added comprehensive file and content validation.
	 *
	 * @return void
	 */
	public function import_settings(): void {
		// Security: Verify nonce first to confirm request legitimacy.
		check_admin_referer( 'wpha_import_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-admin-health-suite' ) );
		}

		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'No file uploaded.', 'wp-admin-health-suite' ) );
		}

		// Validate file extension.
		$file_extension = pathinfo( $_FILES['import_file']['name'], PATHINFO_EXTENSION );
		if ( 'json' !== strtolower( $file_extension ) ) {
			wp_die( esc_html__( 'Invalid file type. Only JSON files are accepted.', 'wp-admin-health-suite' ) );
		}

		// Validate file size (max 100KB - settings should be small).
		if ( $_FILES['import_file']['size'] > 102400 ) {
			wp_die( esc_html__( 'File too large. Maximum 100KB allowed.', 'wp-admin-health-suite' ) );
		}

		// Ensure file was actually uploaded via HTTP POST.
		if ( ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Invalid file upload.', 'wp-admin-health-suite' ) );
		}

		// Validate MIME type using WordPress's file type check.
		$file_info = wp_check_filetype_and_ext(
			$_FILES['import_file']['tmp_name'],
			$_FILES['import_file']['name'],
			array( 'json' => 'application/json' )
		);

		if ( empty( $file_info['ext'] ) ) {
			// Some servers report JSON as plain text; allow that as well.
			$file_info = wp_check_filetype_and_ext(
				$_FILES['import_file']['tmp_name'],
				$_FILES['import_file']['name'],
				array( 'json' => 'text/plain' )
			);
		}

		if ( empty( $file_info['ext'] ) ) {
			// As a last resort, allow generic binary mime types. The JSON decoding and
			// strict structure validation below will still block non-JSON content.
			$file_info = wp_check_filetype_and_ext(
				$_FILES['import_file']['tmp_name'],
				$_FILES['import_file']['name'],
				array( 'json' => 'application/octet-stream' )
			);
		}

		if ( empty( $file_info['ext'] ) ) {
			wp_die( esc_html__( 'Invalid file type. Only JSON files are accepted.', 'wp-admin-health-suite' ) );
		}

		// Read and validate file content.
		$json = file_get_contents( $_FILES['import_file']['tmp_name'] ); // phpcs:ignore
		if ( false === $json ) {
			wp_die( esc_html__( 'Failed to read uploaded file.', 'wp-admin-health-suite' ) );
		}

		// Validate JSON is valid.
		$data = json_decode( $json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_die(
				sprintf(
					/* translators: %s: JSON error message */
					esc_html__( 'Invalid JSON file: %s', 'wp-admin-health-suite' ),
					esc_html( json_last_error_msg() )
				)
			);
		}

		// Validate expected structure.
		$validation_error = $this->validate_import_structure( $data );
		if ( is_wp_error( $validation_error ) ) {
			wp_die( esc_html( $validation_error->get_error_message() ) );
		}

		/** @var SettingsRegistry $registry */
		$registry = $this->container->get( SettingsRegistryInterface::class );

		// Filter imported settings to only include known keys.
		$filtered_settings = $this->filter_known_settings( $data['settings'], $registry );

		// Sanitize all imported values.
		$sanitized = $registry->sanitize_settings( $filtered_settings );
		update_option( SettingsRegistry::OPTION_NAME, $sanitized );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'admin-health-settings',
					'message' => 'imported',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Validate import file structure.
	 *
	 * @since 1.2.0
	 *
	 * @param array|mixed $data Decoded JSON data.
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_import_structure( $data ) {
		// Must be an array.
		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'invalid_format',
				__( 'Import file must contain a JSON object.', 'wp-admin-health-suite' )
			);
		}

		// Must have settings key.
		if ( ! isset( $data['settings'] ) ) {
			return new \WP_Error(
				'missing_settings',
				__( 'Import file must contain a "settings" key.', 'wp-admin-health-suite' )
			);
		}

		// Settings must be an array.
		if ( ! is_array( $data['settings'] ) ) {
			return new \WP_Error(
				'invalid_settings',
				__( 'The "settings" key must contain an object.', 'wp-admin-health-suite' )
			);
		}

		// Validate version if present (warn but don't block).
		if ( isset( $data['version'] ) && version_compare( $data['version'], '1.0.0', '<' ) ) {
			// Very old version - might have incompatible settings.
			return new \WP_Error(
				'version_incompatible',
				__( 'Import file version is too old and may be incompatible.', 'wp-admin-health-suite' )
			);
		}

		// Limit number of settings to prevent DoS.
		if ( count( $data['settings'] ) > 200 ) {
			return new \WP_Error(
				'too_many_settings',
				__( 'Import file contains too many settings.', 'wp-admin-health-suite' )
			);
		}

		// Validate no excessively long values.
		foreach ( $data['settings'] as $key => $value ) {
			// Key validation.
			if ( ! is_string( $key ) || strlen( $key ) > 100 ) {
				return new \WP_Error(
					'invalid_key',
					__( 'Import file contains invalid setting keys.', 'wp-admin-health-suite' )
				);
			}

			// Value length validation (CSS can be long, but has a reasonable limit).
			if ( is_string( $value ) && strlen( $value ) > 50000 ) {
				return new \WP_Error(
					'value_too_long',
					sprintf(
						/* translators: %s: setting key name */
						__( 'Setting "%s" value is too long.', 'wp-admin-health-suite' ),
						$key
					)
				);
			}
		}

		return true;
	}

	/**
	 * Filter imported settings to only include known keys.
	 *
	 * This prevents injection of arbitrary options into the database.
	 *
	 * @since 1.2.0
	 *
	 * @param array            $settings Imported settings.
	 * @param SettingsRegistry $registry Settings registry.
	 * @return array Filtered settings with only known keys.
	 */
	private function filter_known_settings( array $settings, SettingsRegistry $registry ): array {
		$known_fields = array_keys( $registry->get_fields() );
		$filtered     = array();

		foreach ( $settings as $key => $value ) {
			// Only include settings that have registered fields.
			if ( in_array( $key, $known_fields, true ) ) {
				$filtered[ $key ] = $value;
			}
		}

		return $filtered;
	}

	/**
	 * Reset settings to defaults.
	 *
	 * @return void
	 */
	public function reset_settings(): void {
		// Security: Verify nonce first to confirm request legitimacy.
		check_admin_referer( 'wpha_reset_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-admin-health-suite' ) );
		}

		/** @var SettingsRegistry $registry */
		$registry = $this->container->get( SettingsRegistryInterface::class );
		update_option( SettingsRegistry::OPTION_NAME, $registry->get_default_settings() );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'admin-health-settings',
					'message' => 'reset',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Reset settings for a specific section.
	 *
	 * @return void
	 */
	public function reset_section(): void {
		// Security: Verify nonce first to confirm request legitimacy.
		check_admin_referer( 'wpha_reset_section' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-admin-health-suite' ) );
		}

		$section = isset( $_POST['section'] ) ? sanitize_key( $_POST['section'] ) : '';

		/** @var SettingsRegistry $registry */
		$registry = $this->container->get( SettingsRegistryInterface::class );
		$sections = $registry->get_sections();

		if ( empty( $section ) || ! isset( $sections[ $section ] ) ) {
			wp_die( esc_html__( 'Invalid section.', 'wp-admin-health-suite' ) );
		}

		$current_settings = $registry->get_settings();
		$default_settings = $registry->get_default_settings();
		$fields           = $registry->get_fields();

		foreach ( $fields as $field_id => $field ) {
			if ( $field['section'] === $section ) {
				$current_settings[ $field_id ] = $default_settings[ $field_id ];
			}
		}

		update_option( SettingsRegistry::OPTION_NAME, $current_settings );

		// Security: Use fixed redirect URL to prevent open redirect attacks.
		// Custom redirects are disabled as they provide minimal benefit but significant risk.
		$redirect = add_query_arg(
			array(
				'page'    => 'admin-health-settings',
				'tab'     => sanitize_key( $section ),
				'message' => 'reset',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle scheduling settings updates.
	 *
	 * Only reschedules tasks when their frequency changes, preferred time changes,
	 * or when the scheduler is newly enabled.
	 *
	 * @param array $old_value Previous settings.
	 * @param array $new_value New settings.
	 * @return void
	 */
	public function handle_scheduling_update( $old_value, $new_value ): void {
		if ( ! is_array( $old_value ) ) {
			$old_value = array();
		}

		if ( ! is_array( $new_value ) ) {
			$new_value = array();
		}

		// Handle scheduler being disabled.
		if ( empty( $new_value['scheduler_enabled'] ) ) {
			$this->unschedule_all_tasks();
			return;
		}

		// Check if scheduler was just enabled or preferred time changed.
		$was_enabled    = ! empty( $old_value['scheduler_enabled'] );
		$old_time       = isset( $old_value['preferred_time'] ) ? absint( $old_value['preferred_time'] ) : 2;
		$new_time       = isset( $new_value['preferred_time'] ) ? absint( $new_value['preferred_time'] ) : 2;
		$time_changed   = $old_time !== $new_time;
		$reschedule_all = ! $was_enabled || $time_changed;

		$next_run = $this->calculate_next_run_time( $new_time );

		// Task scheduling configuration.
		$tasks = array(
			'wpha_database_cleanup'  => array(
				'enabled_key'   => 'enable_scheduled_db_cleanup',
				'frequency_key' => 'database_cleanup_frequency',
				'default_freq'  => 'weekly',
			),
			'wpha_media_scan'        => array(
				'enabled_key'   => 'enable_scheduled_media_scan',
				'frequency_key' => 'media_scan_frequency',
				'default_freq'  => 'weekly',
			),
			'wpha_performance_check' => array(
				'enabled_key'   => 'enable_scheduled_performance_check',
				'frequency_key' => 'performance_check_frequency',
				'default_freq'  => 'daily',
			),
		);

		// Reschedule tasks only when necessary.
		foreach ( $tasks as $hook => $config ) {
			$enabled_key = $config['enabled_key'];

			$old_enabled = array_key_exists( $enabled_key, $old_value ) ? ! empty( $old_value[ $enabled_key ] ) : true;
			$new_enabled = array_key_exists( $enabled_key, $new_value ) ? ! empty( $new_value[ $enabled_key ] ) : true;

			$frequency_key = $config['frequency_key'];
			$default_freq  = $config['default_freq'];
			$old_frequency = $old_value[ $frequency_key ] ?? $default_freq;
			$new_frequency = $new_value[ $frequency_key ] ?? $default_freq;

			$enabled_changed   = $old_enabled !== $new_enabled;
			$frequency_changed = $old_frequency !== $new_frequency;

			// Disabled tasks should not remain scheduled.
			if ( ! $new_enabled ) {
				$this->unschedule_task( $hook );
				continue;
			}

			if ( $reschedule_all || $enabled_changed || $frequency_changed ) {
				$this->schedule_task( $hook, (string) $new_frequency, $next_run );
			}
		}
	}

	/**
	 * Output custom CSS in admin head.
	 *
	 * Uses WordPress's wp_add_inline_style() for safe CSS output.
	 * Applies strict sanitization to prevent XSS via CSS injection.
	 *
	 * @since 1.2.0 Added defense-in-depth sanitization.
	 * @since 1.2.1 Use wp_add_inline_style() for proper WordPress integration.
	 *
	 * @return void
	 */
	public function output_custom_css(): void {
		/** @var SettingsRegistry $registry */
		$registry   = $this->container->get( SettingsRegistryInterface::class );
		$custom_css = $registry->get_setting( 'custom_css', '' );

		if ( ! empty( $custom_css ) ) {
			// Defense-in-depth: Apply strict sanitization even though input was sanitized on save.
			// This protects against database compromise or import of malicious settings.
			$custom_css = $this->sanitize_css_output( $custom_css );

			if ( ! empty( $custom_css ) ) {
				// Use WordPress's recommended approach for inline styles.
				// Register a dummy handle and attach inline CSS to it.
				wp_register_style( 'wpha-custom-css', false );
				wp_enqueue_style( 'wpha-custom-css' );
				wp_add_inline_style( 'wpha-custom-css', $custom_css );
			}
		}
	}

	/**
	 * Sanitize CSS for output to prevent XSS attacks.
	 *
	 * Applies defense-in-depth sanitization including:
	 * - Strip all HTML tags
	 * - Remove JavaScript expressions
	 * - Block CSS import directives
	 * - Prevent style tag breakout attempts
	 *
	 * @since 1.2.0
	 *
	 * @param string $css The CSS to sanitize.
	 * @return string Sanitized CSS.
	 */
	private function sanitize_css_output( string $css ): string {
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
	 * Schedule a task.
	 *
	 * @param string $hook      Hook name.
	 * @param string $frequency Frequency.
	 * @param int    $next_run  Next run timestamp.
	 * @return void
	 */
	private function schedule_task( string $hook, string $frequency, int $next_run ): void {
		if ( 'disabled' === $frequency ) {
			$this->unschedule_task( $hook );
			return;
		}

		$interval = $this->get_interval_seconds( $frequency );
		if ( ! $interval ) {
			return;
		}

		if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook, array(), 'wpha_scheduling' );
			as_schedule_recurring_action( $next_run, $interval, $hook, array(), 'wpha_scheduling' );
		} else {
			// Ensure we don't accidentally leave multiple schedules behind.
			wp_clear_scheduled_hook( $hook );
			wp_schedule_event( $next_run, $this->get_cron_schedule_name( $frequency ), $hook );
		}
	}

	/**
	 * Unschedule a task.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	private function unschedule_task( string $hook ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook, array(), 'wpha_scheduling' );
		}

		// Clear all scheduled events for this hook (covers duplicates).
		wp_clear_scheduled_hook( $hook );
	}

	/**
	 * Unschedule all tasks.
	 *
	 * @return void
	 */
	private function unschedule_all_tasks(): void {
		$hooks = array( 'wpha_database_cleanup', 'wpha_media_scan', 'wpha_performance_check' );
		foreach ( $hooks as $hook ) {
			$this->unschedule_task( $hook );
		}
	}

	/**
	 * Calculate next run time.
	 *
	 * @param int $preferred_hour Preferred hour.
	 * @return int Timestamp.
	 */
	private function calculate_next_run_time( int $preferred_hour ): int {
		$preferred_hour = min( 23, max( 0, $preferred_hour ) );

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$now      = new \DateTimeImmutable( 'now', $timezone );

		$preferred = $now->setTime( $preferred_hour, 0, 0 );
		if ( $preferred->getTimestamp() <= $now->getTimestamp() ) {
			$preferred = $preferred->modify( '+1 day' );
		}

		return $preferred->getTimestamp();
	}

	/**
	 * Get interval in seconds.
	 *
	 * @param string $frequency Frequency.
	 * @return int|false Interval or false.
	 */
	private function get_interval_seconds( string $frequency ) {
		$intervals = array(
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => 30 * DAY_IN_SECONDS,
		);

		return $intervals[ $frequency ] ?? false;
	}

	/**
	 * Get WP-Cron schedule name.
	 *
	 * @param string $frequency Frequency.
	 * @return string Schedule name.
	 */
	private function get_cron_schedule_name( string $frequency ): string {
		$schedules = array(
			'daily'   => 'daily',
			'weekly'  => 'weekly',
			'monthly' => 'monthly',
		);

		return $schedules[ $frequency ] ?? 'daily';
	}
}
