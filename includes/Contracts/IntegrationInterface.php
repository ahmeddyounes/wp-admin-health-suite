<?php
/**
 * Integration Interface
 *
 * Contract for third-party plugin integrations.
 *
 * @package WPAdminHealth\Contracts
 */

namespace WPAdminHealth\Contracts;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Interface IntegrationInterface
 *
 * Defines the contract for third-party plugin integrations.
 * All integrations (WooCommerce, Elementor, ACF, etc.) must implement this interface.
 *
 * @since 1.1.0
 */
interface IntegrationInterface {

	/**
	 * Get the unique identifier for this integration.
	 *
	 * @since 1.1.0
	 *
	 * @return string Integration ID (e.g., 'woocommerce', 'elementor').
	 */
	public function get_id(): string;

	/**
	 * Get the display name for this integration.
	 *
	 * @since 1.1.0
	 *
	 * @return string Human-readable integration name.
	 */
	public function get_name(): string;

	/**
	 * Check if the integrated plugin is active and available.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if the integration's target plugin is active.
	 */
	public function is_available(): bool;

	/**
	 * Get the minimum required version of the integrated plugin.
	 *
	 * @since 1.1.0
	 *
	 * @return string Minimum version string (e.g., '5.0.0').
	 */
	public function get_min_version(): string;

	/**
	 * Get the current version of the integrated plugin.
	 *
	 * @since 1.1.0
	 *
	 * @return string|null Current version or null if not available.
	 */
	public function get_current_version(): ?string;

	/**
	 * Check if the integration is compatible with the current plugin version.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if compatible, false otherwise.
	 */
	public function is_compatible(): bool;

	/**
	 * Initialize the integration.
	 *
	 * Called when the integration is loaded. Use this to register hooks,
	 * filters, and any initialization logic.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function init(): void;

	/**
	 * Deactivate the integration.
	 *
	 * Called when the integration is being deactivated. Use this to clean up
	 * any registered hooks, scheduled tasks, etc.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function deactivate(): void;

	/**
	 * Get the integration's dependencies.
	 *
	 * Returns a list of other integration IDs that must be loaded before this one.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string> Array of integration IDs.
	 */
	public function get_dependencies(): array;

	/**
	 * Get the integration's priority.
	 *
	 * Lower numbers mean higher priority. Default should be 10.
	 *
	 * @since 1.1.0
	 *
	 * @return int Priority value.
	 */
	public function get_priority(): int;

	/**
	 * Get the capabilities this integration provides.
	 *
	 * Capabilities are used by the IntegrationManager to find integrations
	 * that can handle specific tasks.
	 *
	 * Common capabilities:
	 * - 'media_detection': Can detect media usage in plugin content
	 * - 'database_cleanup': Provides database cleanup functions
	 * - 'performance_audit': Provides performance auditing
	 *
	 * @since 1.1.0
	 *
	 * @return array<string> Array of capability identifiers.
	 */
	public function get_capabilities(): array;

	/**
	 * Check if this integration provides a specific capability.
	 *
	 * @since 1.1.0
	 *
	 * @param string $capability Capability identifier.
	 * @return bool True if the capability is provided.
	 */
	public function has_capability( string $capability ): bool;

	/**
	 * Check if the integration is currently initialized.
	 *
	 * @since 1.1.0
	 *
	 * @return bool True if initialized, false otherwise.
	 */
	public function is_initialized(): bool;
}
