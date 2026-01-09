<?php
/**
 * Database Settings
 *
 * Database cleanup settings.
 *
 * @package WPAdminHealth\Settings\Domain
 */

namespace WPAdminHealth\Settings\Domain;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class DatabaseSettings
 *
 * Manages database cleanup settings.
 *
 * @since 1.2.0
 */
class DatabaseSettings extends AbstractDomainSettings {

	/**
	 * {@inheritdoc}
	 */
	protected function define_domain(): string {
		return 'database_cleanup';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_section(): array {
		return array(
			'title'       => __( 'Database Cleanup', 'wp-admin-health-suite' ),
			'description' => __( 'Configure database cleanup options.', 'wp-admin-health-suite' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function define_fields(): array {
		return array(
			'cleanup_revisions'           => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Post Revisions', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_auto_drafts'         => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Auto-Drafts', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_trashed_posts'       => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Trashed Posts', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_spam_comments'       => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Spam Comments', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_trashed_comments'    => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Trashed Comments', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'cleanup_expired_transients'  => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Expired Transients', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => true,
				'sanitize' => 'boolean',
			),
			'cleanup_orphaned_metadata'   => array(
				'section'  => 'database_cleanup',
				'title'    => __( 'Clean Orphaned Metadata', 'wp-admin-health-suite' ),
				'type'     => 'checkbox',
				'default'  => false,
				'sanitize' => 'boolean',
			),
			'revisions_to_keep'           => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Revisions to Keep', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 0,
				'sanitize'    => 'integer',
				'description' => __( 'Number of revisions to keep per post (0-50, 0 = delete all).', 'wp-admin-health-suite' ),
				'min'         => 0,
				'max'         => 50,
			),
			'auto_clean_spam_days'        => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Auto Clean Spam Comments (days)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 0,
				'sanitize'    => 'integer',
				'description' => __( 'Auto-delete spam comments older than X days (0-365, 0 = disabled).', 'wp-admin-health-suite' ),
				'min'         => 0,
				'max'         => 365,
			),
			'auto_clean_trash_days'       => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Auto Clean Trash (days)', 'wp-admin-health-suite' ),
				'type'        => 'number',
				'default'     => 0,
				'sanitize'    => 'integer',
				'description' => __( 'Auto-delete trashed content older than X days (0-365, 0 = disabled).', 'wp-admin-health-suite' ),
				'min'         => 0,
				'max'         => 365,
			),
			'excluded_transient_prefixes' => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Excluded Transient Prefixes', 'wp-admin-health-suite' ),
				'type'        => 'textarea',
				'default'     => '',
				'sanitize'    => 'textarea',
				'description' => __( 'Transient prefixes to exclude from cleanup (one per line).', 'wp-admin-health-suite' ),
			),
			'optimize_tables_weekly'      => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Optimize Tables Weekly', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Automatically optimize database tables weekly.', 'wp-admin-health-suite' ),
			),
			'orphaned_cleanup_enabled'    => array(
				'section'     => 'database_cleanup',
				'title'       => __( 'Enable Orphaned Cleanup', 'wp-admin-health-suite' ),
				'type'        => 'checkbox',
				'default'     => false,
				'sanitize'    => 'boolean',
				'description' => __( 'Automatically clean orphaned metadata during scheduled tasks.', 'wp-admin-health-suite' ),
			),
		);
	}
}
