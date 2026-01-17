<?php
/**
 * Media Settings
 *
 * Media audit settings.
 *
 * @package WPAdminHealth\Settings\Domain
 */

namespace WPAdminHealth\Settings\Domain;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class MediaSettings
 *
 * Manages media audit settings.
 *
 * @since 1.2.0
 */
class MediaSettings extends AbstractDomainSettings {

	/**
	 * {@inheritdoc}
	 */
	protected function define_domain(): string {
		return 'media_audit';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_section(): array {
		return array(
			'title'       => __( 'Media Audit', 'wp-admin-health-suite' ),
			'description' => __( 'Configure media audit settings.', 'wp-admin-health-suite' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_fields(): array {
		return array(
			'scan_unused_media'          => array(
				'section'  => 'media_audit',
				'title'    => __( 'Scan for Unused Media', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => true,
				'sanitize' => 'boolean',
			),
			'detect_duplicates'          => array(
				'section'     => 'media_audit',
				'title'       => __( 'Detect Duplicate Files', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable duplicate file detection during media scans.', 'wp-admin-health-suite' ),
			),
			'detect_large_files'         => array(
				'section'     => 'media_audit',
				'title'       => __( 'Detect Large Files', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Enable large file detection during media scans.', 'wp-admin-health-suite' ),
			),
			'check_alt_text'             => array(
				'section'     => 'media_audit',
				'title'       => __( 'Check Alt Text', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Check for missing alt text during media scans.', 'wp-admin-health-suite' ),
			),
			'exclude_media_types'        => array(
				'section'     => 'media_audit',
				'title'       => __( 'Excluded MIME Types', 'wp-admin-health-suite' ),
				'type'        => 'textarea',
				'default'     => '',
				'sanitize'    => 'newline_list',
				'max_items'   => 50,
				'description' => __( 'MIME types or patterns to exclude from scans and deletion (one per line). Supports wildcards like image/*.', 'wp-admin-health-suite' ),
			),
			'exclude_media_file_patterns' => array(
				'section'     => 'media_audit',
				'title'       => __( 'Excluded File Patterns', 'wp-admin-health-suite' ),
				'type'        => 'textarea',
				'default'     => '',
				'sanitize'    => 'line_list',
				'max_items'   => 100,
				'description' => __( 'Filename or path patterns to exclude from scans and deletion (one per line). Examples: *.svg, *-scaled.*, 2026/01/*.', 'wp-admin-health-suite' ),
			),
			'unused_media_scan_depth'    => array(
				'section'     => 'media_audit',
				'title'       => __( 'Unused Media Scan Depth', 'wp-admin-health-suite' ),
				'type'        => 'select',
				'default'     => 'posts_only',
				'sanitize'    => 'select',
				'options'     => array(
					'posts_only'  => __( 'Posts Only', 'wp-admin-health-suite' ),
					'all_content' => __( 'All Content', 'wp-admin-health-suite' ),
					'deep_scan'   => __( 'Deep Scan', 'wp-admin-health-suite' ),
				),
				'description' => __( 'Determines how thoroughly to scan for media usage.', 'wp-admin-health-suite' ),
			),
			'large_file_threshold_kb'    => array(
				'section'     => 'media_audit',
				'title'       => __( 'Large File Threshold (KB)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 1000,
				'sanitize'    => 'integer',
				'description' => __( 'Files larger than this size will be flagged as large files.', 'wp-admin-health-suite' ),
				'min'         => 100,
				'max'         => 5000,
			),
			'duplicate_detection_method' => array(
				'section'     => 'media_audit',
				'title'       => __( 'Duplicate Detection Method', 'wp-admin-health-suite' ),
				'type'        => 'select',
				'default'     => 'hash',
				'sanitize'    => 'select',
				'options'     => array(
					'hash'     => __( 'Hash', 'wp-admin-health-suite' ),
					'filename' => __( 'Filename', 'wp-admin-health-suite' ),
					'both'     => __( 'Both', 'wp-admin-health-suite' ),
				),
				'description' => __( 'Method used to detect duplicate media files.', 'wp-admin-health-suite' ),
			),
			'excluded_media_ids'         => array(
				'section'     => 'media_audit',
				'title'       => __( 'Excluded Media IDs', 'wp-admin-health-suite' ),
				'type'        => 'textarea',
				'default'     => '',
				'sanitize'    => 'integer_list',
				'max_items'   => 500,
				'description' => __( 'Attachment IDs to exclude from scans and deletion (comma or newline separated).', 'wp-admin-health-suite' ),
			),
			'require_media_delete_confirmation' => array(
				'section'     => 'media_audit',
				'title'       => __( 'Require Delete Confirmation', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => true,
				'sanitize'    => 'boolean',
				'description' => __( 'Require explicit confirmation for media deletion requests via REST.', 'wp-admin-health-suite' ),
			),
			'media_trash_retention_days' => array(
				'section'     => 'media_audit',
				'title'       => __( 'Media Trash Retention Days', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 30,
				'sanitize'    => 'integer',
				'description' => __( 'Number of days to keep media in the WP Admin Health trash folder before permanent deletion.', 'wp-admin-health-suite' ),
				'min'         => 7,
				'max'         => 365,
			),
			'scan_acf_fields'            => array(
				'section'     => 'media_audit',
				'title'       => __( 'Scan ACF Fields', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Include Advanced Custom Fields when scanning for media usage.', 'wp-admin-health-suite' ),
			),
			'scan_elementor'             => array(
				'section'     => 'media_audit',
				'title'       => __( 'Scan Elementor', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Include Elementor page builder content when scanning for media usage.', 'wp-admin-health-suite' ),
			),
			'scan_woocommerce'           => array(
				'section'     => 'media_audit',
				'title'       => __( 'Scan WooCommerce', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Include WooCommerce product images when scanning for media usage.', 'wp-admin-health-suite' ),
			),
		);
	}
}
