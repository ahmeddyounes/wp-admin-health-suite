<?php
/**
 * Heartbeat Controller Class
 *
 * Provides WordPress Heartbeat API control and optimization.
 * Allows customizing heartbeat frequency per location (admin, post-editor, frontend)
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
class Heartbeat_Controller {

	/**
	 * Option name for storing heartbeat settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wpha_heartbeat_settings';

	/**
	 * Valid locations for heartbeat control.
	 *
	 * @var array
	 */
	const VALID_LOCATIONS = array( 'admin', 'post-editor', 'frontend' );

	/**
	 * Valid frequency options in seconds.
	 *
	 * @var array
	 */
	const VALID_FREQUENCIES = array( 15, 30, 60, 120, 'disabled' );

	/**
	 * Default frequency in seconds (WordPress default).
	 *
	 * @var int
	 */
	const DEFAULT_FREQUENCY = 60;

	/**
	 * Constructor.
 * @since 1.0.0
 *
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
	 * Get current heartbeat settings.
	 *
 * @since 1.0.0
 *
	 * @return array Current settings for all locations.
	 */
	public function get_current_settings() {
		$default_settings = array(
			'admin'       => self::DEFAULT_FREQUENCY,
			'post-editor' => self::DEFAULT_FREQUENCY,
			'frontend'    => self::DEFAULT_FREQUENCY,
		);

		$settings = get_option( self::OPTION_NAME, $default_settings );

		// Ensure all locations are present.
		foreach ( self::VALID_LOCATIONS as $location ) {
			if ( ! isset( $settings[ $location ] ) ) {
				$settings[ $location ] = self::DEFAULT_FREQUENCY;
			}
		}

		return $settings;
	}

	/**
	 * Update heartbeat frequency for a specific location.
	 *
 * @since 1.0.0
 *
	 * @param string     $location Location: 'admin', 'post-editor', or 'frontend'.
	 * @param int|string $seconds  Frequency in seconds (15, 30, 60, 120) or 'disabled'.
	 * @return bool True on success, false on failure.
	 */
	public function update_frequency( $location, $seconds ) {
		// Validate location.
		if ( ! in_array( $location, self::VALID_LOCATIONS, true ) ) {
			return false;
		}

		// Validate frequency.
		if ( ! in_array( $seconds, self::VALID_FREQUENCIES, true ) ) {
			return false;
		}

		$settings = $this->get_current_settings();
		$settings[ $location ] = $seconds;

		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Disable heartbeat for a specific location.
	 *
 * @since 1.0.0
 *
	 * @param string $location Location: 'admin', 'post-editor', or 'frontend'.
	 * @return bool True on success, false on failure.
	 */
	public function disable_heartbeat( $location ) {
		return $this->update_frequency( $location, 'disabled' );
	}

	/**
	 * Enable heartbeat for a specific location with default frequency.
	 *
 * @since 1.0.0
 *
	 * @param string $location Location: 'admin', 'post-editor', or 'frontend'.
	 * @return bool True on success, false on failure.
	 */
	public function enable_heartbeat( $location ) {
		return $this->update_frequency( $location, self::DEFAULT_FREQUENCY );
	}

	/**
	 * Apply heartbeat settings using WordPress filter.
	 *
 * @since 1.0.0
 *
	 * @param array $settings WordPress heartbeat settings.
	 * @return array Modified settings.
	 */
	public function apply_heartbeat_settings( $settings ) {
		$custom_settings = $this->get_current_settings();
		$location = $this->get_current_location();

		if ( ! $location || ! isset( $custom_settings[ $location ] ) ) {
			return $settings;
		}

		$frequency = $custom_settings[ $location ];

		// If disabled, we return settings as-is (actual disabling happens in maybe_disable_heartbeat).
		if ( 'disabled' === $frequency ) {
			return $settings;
		}

		// Set the interval (in seconds).
		$settings['interval'] = absint( $frequency );

		return $settings;
	}

	/**
	 * Maybe disable heartbeat completely for the current location.
	 *
 * @since 1.0.0
 *
	 * @return void
	 */
	public function maybe_disable_heartbeat() {
		$custom_settings = $this->get_current_settings();
		$location = $this->get_current_location();

		if ( ! $location || ! isset( $custom_settings[ $location ] ) ) {
			return;
		}

		$frequency = $custom_settings[ $location ];

		// Disable heartbeat if set to 'disabled'.
		if ( 'disabled' === $frequency ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	/**
	 * Get the current location context.
	 *
	 * @return string|null Current location: 'admin', 'post-editor', 'frontend', or null.
	 */
	private function get_current_location() {
		// Check if we're in the admin area.
		if ( ! is_admin() ) {
			return 'frontend';
		}

		// Check if we're in the post editor.
		global $pagenow;
		$editor_pages = array( 'post.php', 'post-new.php' );

		if ( in_array( $pagenow, $editor_pages, true ) ) {
			return 'post-editor';
		}

		// Default to admin.
		return 'admin';
	}

	/**
	 * Get available presets with their settings and estimated CPU savings.
	 *
 * @since 1.0.0
 *
	 * @return array Array of presets with settings and CPU savings.
	 */
	public function get_presets() {
		return array(
			'default'   => array(
				'label'       => 'Default',
				'description' => 'WordPress default heartbeat settings',
				'settings'    => array(
					'admin'       => 60,
					'post-editor' => 15,
					'frontend'    => 60,
				),
				'cpu_savings' => 0,
			),
			'optimized' => array(
				'label'       => 'Optimized',
				'description' => 'Balanced performance with essential features',
				'settings'    => array(
					'admin'       => 120,
					'post-editor' => 30,
					'frontend'    => 'disabled',
				),
				'cpu_savings' => 35,
			),
			'minimal'   => array(
				'label'       => 'Minimal',
				'description' => 'Maximum performance, minimal heartbeat activity',
				'settings'    => array(
					'admin'       => 'disabled',
					'post-editor' => 60,
					'frontend'    => 'disabled',
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
	public function apply_preset( $preset_name ) {
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
	 * @return array Savings information including percentage and details.
	 */
	public function calculate_cpu_savings() {
		$current_settings = $this->get_current_settings();
		$default_preset = $this->get_presets()['default']['settings'];

		$savings_percent = 0;
		$details = array();

		// Calculate savings per location.
		foreach ( self::VALID_LOCATIONS as $location ) {
			$current = $current_settings[ $location ];
			$default = $default_preset[ $location ];

			if ( 'disabled' === $current ) {
				// Disabling saves 100% for this location.
				$location_savings = 100;
			} elseif ( $current > $default ) {
				// Longer interval means fewer requests.
				// Savings = (1 - default/current) * 100.
				$location_savings = ( 1 - ( $default / $current ) ) * 100;
			} else {
				// No savings if frequency is higher or equal.
				$location_savings = 0;
			}

			$details[ $location ] = round( $location_savings, 1 );
		}

		// Overall savings (average across locations).
		// Weight post-editor higher as it's typically the most active.
		$weights = array(
			'admin'       => 0.3,
			'post-editor' => 0.5,
			'frontend'    => 0.2,
		);

		foreach ( $details as $location => $saving ) {
			$savings_percent += $saving * $weights[ $location ];
		}

		return array(
			'total_percent' => round( $savings_percent, 1 ),
			'by_location'   => $details,
			'estimated_requests_saved' => $this->estimate_requests_saved( $current_settings, $default_preset ),
		);
	}

	/**
	 * Estimate the number of requests saved per hour.
	 *
	 * @param array $current Current settings.
	 * @param array $default Default settings.
	 * @return int Estimated requests saved per hour.
	 */
	private function estimate_requests_saved( $current, $default ) {
		$requests_saved = 0;

		foreach ( self::VALID_LOCATIONS as $location ) {
			$current_freq = $current[ $location ];
			$default_freq = $default[ $location ];

			// Calculate requests per hour for each setting.
			$default_requests_per_hour = 3600 / $default_freq;

			if ( 'disabled' === $current_freq ) {
				// All default requests are saved.
				$requests_saved += $default_requests_per_hour;
			} elseif ( $current_freq > $default_freq ) {
				// Difference in requests per hour.
				$current_requests_per_hour = 3600 / $current_freq;
				$requests_saved += ( $default_requests_per_hour - $current_requests_per_hour );
			}
		}

		return round( $requests_saved );
	}

	/**
	 * Get heartbeat status information.
	 *
 * @since 1.0.0
 *
	 * @return array Status information including current settings and savings.
	 */
	public function get_status() {
		$settings = $this->get_current_settings();
		$savings = $this->calculate_cpu_savings();

		return array(
			'current_settings' => $settings,
			'cpu_savings'      => $savings,
			'active_preset'    => $this->get_active_preset(),
		);
	}

	/**
	 * Get the active preset name if settings match a preset.
	 *
	 * @return string|null Preset name or null if custom settings.
	 */
	private function get_active_preset() {
		$current_settings = $this->get_current_settings();
		$presets = $this->get_presets();

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
	public function reset_to_defaults() {
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Validate heartbeat settings array.
	 *
 * @since 1.0.0
 *
	 * @param array $settings Settings to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return false;
		}

		foreach ( self::VALID_LOCATIONS as $location ) {
			if ( ! isset( $settings[ $location ] ) ) {
				return false;
			}

			if ( ! in_array( $settings[ $location ], self::VALID_FREQUENCIES, true ) ) {
				return false;
			}
		}

		return true;
	}
}
