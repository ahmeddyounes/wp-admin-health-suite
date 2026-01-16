<?php
/**
 * Heartbeat Controller Class
 *
 * Provides WordPress Heartbeat API control and optimization.
 * Allows customizing heartbeat frequency per location (dashboard, editor, frontend)
 * with presets for different optimization levels.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Performance;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Heartbeat Controller class for managing WordPress Heartbeat API.
 *
 * @since 1.0.0
 */
class HeartbeatController {

	/**
	 * Option name for storing heartbeat settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wpha_heartbeat_settings';

	/**
	 * Valid locations for heartbeat control.
	 *
	 * @var array<string>
	 */
	const VALID_LOCATIONS = array( 'dashboard', 'editor', 'frontend' );

	/**
	 * Valid frequency options in seconds.
	 *
	 * @var array<int>
	 */
	const VALID_FREQUENCIES = array( 15, 30, 60, 120 );

	/**
	 * Default frequency in seconds (WordPress default).
	 *
	 * @var int
	 */
	const DEFAULT_FREQUENCY = 60;

	/**
	 * Default editor frequency in seconds.
	 *
	 * @var int
	 */
	const DEFAULT_EDITOR_FREQUENCY = 15;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'heartbeat_settings', array( $this, 'apply_heartbeat_settings' ) );
		add_action( 'init', array( $this, 'maybe_disable_heartbeat' ) );
	}

	/**
	 * Get default heartbeat settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{enabled: bool, interval: int}> Default settings.
	 */
	private function get_default_settings(): array {
		return array(
			'dashboard' => array(
				'enabled'  => true,
				'interval' => self::DEFAULT_FREQUENCY,
			),
			'editor'    => array(
				'enabled'  => true,
				'interval' => self::DEFAULT_EDITOR_FREQUENCY,
			),
			'frontend'  => array(
				'enabled'  => true,
				'interval' => self::DEFAULT_FREQUENCY,
			),
		);
	}

	/**
	 * Get current heartbeat settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{enabled: bool, interval: int}> Current settings for all locations.
	 */
	public function get_current_settings(): array {
		$default_settings = $this->get_default_settings();
		$settings         = get_option( self::OPTION_NAME, $default_settings );

		// Migrate old format if needed.
		if ( isset( $settings['admin'] ) || isset( $settings['post-editor'] ) ) {
			$settings = $this->migrate_settings( $settings );
		}

		// Ensure all locations are present with proper structure.
		foreach ( self::VALID_LOCATIONS as $location ) {
			if ( ! isset( $settings[ $location ] ) || ! is_array( $settings[ $location ] ) ) {
				$settings[ $location ] = $default_settings[ $location ];
			} else {
				// Ensure both enabled and interval keys exist.
				if ( ! isset( $settings[ $location ]['enabled'] ) ) {
					$settings[ $location ]['enabled'] = true;
				}
				if ( ! isset( $settings[ $location ]['interval'] ) ) {
					$settings[ $location ]['interval'] = $default_settings[ $location ]['interval'];
				}
			}
		}

		return $settings;
	}

	/**
	 * Migrate old settings format to new format.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $old_settings Old format settings.
	 * @return array<string, array{enabled: bool, interval: int}> Migrated settings.
	 */
	private function migrate_settings( array $old_settings ): array {
		$new_settings = $this->get_default_settings();
		$location_map = array(
			'admin'       => 'dashboard',
			'post-editor' => 'editor',
			'frontend'    => 'frontend',
		);

		foreach ( $location_map as $old_location => $new_location ) {
			if ( isset( $old_settings[ $old_location ] ) ) {
				$value = $old_settings[ $old_location ];
				if ( 'disabled' === $value ) {
					$new_settings[ $new_location ] = array(
						'enabled'  => false,
						'interval' => $new_settings[ $new_location ]['interval'],
					);
				} elseif ( is_int( $value ) ) {
					$new_settings[ $new_location ] = array(
						'enabled'  => true,
						'interval' => $value,
					);
				}
			}
		}

		// Save migrated settings.
		update_option( self::OPTION_NAME, $new_settings );

		return $new_settings;
	}

	/**
	 * Update heartbeat settings for a specific location.
	 *
	 * @since 1.0.0
	 *
	 * @param string $location Location: 'dashboard', 'editor', or 'frontend'.
	 * @param bool   $enabled  Whether heartbeat is enabled.
	 * @param int    $interval Interval in seconds (15, 30, 60, 120).
	 * @return bool True on success, false on failure.
	 */
	public function update_location_settings( string $location, bool $enabled, int $interval ): bool {
		// Validate location.
		if ( ! in_array( $location, self::VALID_LOCATIONS, true ) ) {
			return false;
		}

		// Validate interval.
		if ( ! in_array( $interval, self::VALID_FREQUENCIES, true ) ) {
			return false;
		}

		$settings              = $this->get_current_settings();
		$settings[ $location ] = array(
			'enabled'  => $enabled,
			'interval' => $interval,
		);

		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Update heartbeat frequency for a specific location.
	 *
	 * @since 1.0.0
	 * @deprecated Use update_location_settings() instead.
	 *
	 * @param string $location Location: 'dashboard', 'editor', or 'frontend'.
	 * @param int    $seconds  Frequency in seconds (15, 30, 60, 120).
	 * @return bool True on success, false on failure.
	 */
	public function update_frequency( string $location, int $seconds ): bool {
		return $this->update_location_settings( $location, true, $seconds );
	}

	/**
	 * Disable heartbeat for a specific location.
	 *
	 * @since 1.0.0
	 *
	 * @param string $location Location: 'dashboard', 'editor', or 'frontend'.
	 * @return bool True on success, false on failure.
	 */
	public function disable_heartbeat( string $location ): bool {
		if ( ! in_array( $location, self::VALID_LOCATIONS, true ) ) {
			return false;
		}

		$settings              = $this->get_current_settings();
		$settings[ $location ] = array(
			'enabled'  => false,
			'interval' => $settings[ $location ]['interval'],
		);

		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Enable heartbeat for a specific location with its current interval.
	 *
	 * @since 1.0.0
	 *
	 * @param string $location Location: 'dashboard', 'editor', or 'frontend'.
	 * @return bool True on success, false on failure.
	 */
	public function enable_heartbeat( string $location ): bool {
		if ( ! in_array( $location, self::VALID_LOCATIONS, true ) ) {
			return false;
		}

		$settings              = $this->get_current_settings();
		$settings[ $location ] = array(
			'enabled'  => true,
			'interval' => $settings[ $location ]['interval'],
		);

		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Apply heartbeat settings using WordPress filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings WordPress heartbeat settings.
	 * @return array<string, mixed> Modified settings.
	 */
	public function apply_heartbeat_settings( array $settings ): array {
		$custom_settings  = $this->get_current_settings();
		$location         = $this->get_current_location();
		$location_setting = $custom_settings[ $location ] ?? null;

		if ( ! $location_setting || ! is_array( $location_setting ) ) {
			return $settings;
		}

		// If disabled, we return settings as-is (actual disabling happens in maybe_disable_heartbeat).
		if ( empty( $location_setting['enabled'] ) ) {
			return $settings;
		}

		// Set the interval (in seconds).
		$settings['interval'] = absint( $location_setting['interval'] );

		return $settings;
	}

	/**
	 * Maybe disable heartbeat completely for the current location.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_disable_heartbeat(): void {
		$custom_settings  = $this->get_current_settings();
		$location         = $this->get_current_location();
		$location_setting = $custom_settings[ $location ] ?? null;

		if ( ! $location_setting || ! is_array( $location_setting ) ) {
			return;
		}

		// Disable heartbeat if not enabled.
		if ( empty( $location_setting['enabled'] ) ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	/**
	 * Get the current location context.
	 *
	 * @since 1.0.0
	 *
	 * @return string Current location: 'dashboard', 'editor', or 'frontend'.
	 */
	private function get_current_location(): string {
		// Check if we're in the admin area.
		if ( ! is_admin() ) {
			return 'frontend';
		}

		// Check if we're in the post editor.
		global $pagenow;
		$editor_pages = array( 'post.php', 'post-new.php' );

		if ( in_array( $pagenow, $editor_pages, true ) ) {
			return 'editor';
		}

		// Default to dashboard (admin).
		return 'dashboard';
	}

	/**
	 * Get available presets with their settings and estimated CPU savings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{label: string, description: string, settings: array<string, array{enabled: bool, interval: int}>, cpu_savings: int}> Array of presets.
	 */
	public function get_presets(): array {
		return array(
			'default'   => array(
				'label'       => __( 'Default', 'wp-admin-health-suite' ),
				'description' => __( 'WordPress default heartbeat settings', 'wp-admin-health-suite' ),
				'settings'    => array(
					'dashboard' => array(
						'enabled'  => true,
						'interval' => 60,
					),
					'editor'    => array(
						'enabled'  => true,
						'interval' => 15,
					),
					'frontend'  => array(
						'enabled'  => true,
						'interval' => 60,
					),
				),
				'cpu_savings' => 0,
			),
			'optimized' => array(
				'label'       => __( 'Optimized', 'wp-admin-health-suite' ),
				'description' => __( 'Balanced performance with essential features', 'wp-admin-health-suite' ),
				'settings'    => array(
					'dashboard' => array(
						'enabled'  => true,
						'interval' => 120,
					),
					'editor'    => array(
						'enabled'  => true,
						'interval' => 30,
					),
					'frontend'  => array(
						'enabled'  => false,
						'interval' => 60,
					),
				),
				'cpu_savings' => 35,
			),
			'minimal'   => array(
				'label'       => __( 'Minimal', 'wp-admin-health-suite' ),
				'description' => __( 'Maximum performance, minimal heartbeat activity', 'wp-admin-health-suite' ),
				'settings'    => array(
					'dashboard' => array(
						'enabled'  => false,
						'interval' => 60,
					),
					'editor'    => array(
						'enabled'  => true,
						'interval' => 60,
					),
					'frontend'  => array(
						'enabled'  => false,
						'interval' => 60,
					),
				),
				'cpu_savings' => 65,
			),
		);
	}

	/**
	 * Apply a preset to heartbeat settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string $preset_name Preset name: 'default', 'optimized', or 'minimal'.
	 * @return bool True on success, false on failure.
	 */
	public function apply_preset( string $preset_name ): bool {
		$presets = $this->get_presets();

		if ( ! isset( $presets[ $preset_name ] ) ) {
			return false;
		}

		$preset_settings = $presets[ $preset_name ]['settings'];

		return update_option( self::OPTION_NAME, $preset_settings );
	}

	/**
	 * Calculate estimated CPU savings based on current settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array{total_percent: float, by_location: array<string, float>, estimated_requests_saved: int} Savings information.
	 */
	public function calculate_cpu_savings(): array {
		$current_settings = $this->get_current_settings();
		$default_preset   = $this->get_presets()['default']['settings'];

		$savings_percent = 0.0;
		$details         = array();

		// Calculate savings per location.
		foreach ( self::VALID_LOCATIONS as $location ) {
			$current = $current_settings[ $location ];
			$default = $default_preset[ $location ];

			// Check if disabled (not enabled).
			if ( empty( $current['enabled'] ) ) {
				// Disabling saves 100% for this location.
				$location_savings = 100.0;
			} elseif ( $current['interval'] > $default['interval'] ) {
				// Longer interval means fewer requests.
				// Savings = (1 - default/current) * 100.
				$location_savings = ( 1 - ( $default['interval'] / $current['interval'] ) ) * 100;
			} else {
				// No savings if frequency is higher or equal.
				$location_savings = 0.0;
			}

			$details[ $location ] = round( $location_savings, 1 );
		}

		// Overall savings (average across locations).
		// Weight editor higher as it's typically the most active.
		$weights = array(
			'dashboard' => 0.3,
			'editor'    => 0.5,
			'frontend'  => 0.2,
		);

		foreach ( $details as $location => $saving ) {
			$savings_percent += $saving * $weights[ $location ];
		}

		return array(
			'total_percent'            => round( $savings_percent, 1 ),
			'by_location'              => $details,
			'estimated_requests_saved' => $this->estimate_requests_saved( $current_settings, $default_preset ),
		);
	}

	/**
	 * Estimate the number of requests saved per hour.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array{enabled: bool, interval: int}> $current Current settings.
	 * @param array<string, array{enabled: bool, interval: int}> $default Default settings.
	 * @return int Estimated requests saved per hour.
	 */
	private function estimate_requests_saved( array $current, array $default ): int {
		$requests_saved = 0.0;

		foreach ( self::VALID_LOCATIONS as $location ) {
			$current_setting = $current[ $location ];
			$default_setting = $default[ $location ];

			// Calculate requests per hour for default setting.
			$default_requests_per_hour = 3600 / $default_setting['interval'];

			// If disabled, all default requests are saved.
			if ( empty( $current_setting['enabled'] ) ) {
				$requests_saved += $default_requests_per_hour;
			} elseif ( $current_setting['interval'] > $default_setting['interval'] ) {
				// Difference in requests per hour.
				$current_requests_per_hour = 3600 / $current_setting['interval'];
				$requests_saved           += ( $default_requests_per_hour - $current_requests_per_hour );
			}
		}

		return (int) round( $requests_saved );
	}

	/**
	 * Get heartbeat status information.
	 *
	 * @since 1.0.0
	 *
	 * @return array{current_settings: array<string, array{enabled: bool, interval: int}>, cpu_savings: array, active_preset: string} Status information.
	 */
	public function get_status(): array {
		$settings = $this->get_current_settings();
		$savings  = $this->calculate_cpu_savings();

		return array(
			'current_settings' => $settings,
			'cpu_savings'      => $savings,
			'active_preset'    => $this->get_active_preset(),
		);
	}

	/**
	 * Get the active preset name if settings match a preset.
	 *
	 * @since 1.0.0
	 *
	 * @return string Preset name or 'custom' if no preset matches.
	 */
	private function get_active_preset(): string {
		$current_settings = $this->get_current_settings();
		$presets          = $this->get_presets();

		foreach ( $presets as $name => $preset ) {
			if ( $preset['settings'] === $current_settings ) {
				return $name;
			}
		}

		return 'custom';
	}

	/**
	 * Reset heartbeat settings to WordPress defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success.
	 */
	public function reset_to_defaults(): bool {
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Validate heartbeat settings array.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Settings to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_settings( array $settings ): bool {
		foreach ( self::VALID_LOCATIONS as $location ) {
			if ( ! isset( $settings[ $location ] ) || ! is_array( $settings[ $location ] ) ) {
				return false;
			}

			$location_setting = $settings[ $location ];

			if ( ! isset( $location_setting['enabled'] ) || ! is_bool( $location_setting['enabled'] ) ) {
				return false;
			}

			if ( ! isset( $location_setting['interval'] ) || ! in_array( $location_setting['interval'], self::VALID_FREQUENCIES, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if heartbeat is enabled for a specific location.
	 *
	 * @since 1.0.0
	 *
	 * @param string $location Location: 'dashboard', 'editor', or 'frontend'.
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled( string $location ): bool {
		if ( ! in_array( $location, self::VALID_LOCATIONS, true ) ) {
			return false;
		}

		$settings = $this->get_current_settings();

		return ! empty( $settings[ $location ]['enabled'] );
	}

	/**
	 * Get the interval for a specific location.
	 *
	 * @since 1.0.0
	 *
	 * @param string $location Location: 'dashboard', 'editor', or 'frontend'.
	 * @return int Interval in seconds, or default if location is invalid.
	 */
	public function get_interval( string $location ): int {
		if ( ! in_array( $location, self::VALID_LOCATIONS, true ) ) {
			return self::DEFAULT_FREQUENCY;
		}

		$settings = $this->get_current_settings();

		return $settings[ $location ]['interval'] ?? self::DEFAULT_FREQUENCY;
	}
}
