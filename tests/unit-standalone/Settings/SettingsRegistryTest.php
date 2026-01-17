<?php
/**
 * SettingsRegistry standalone tests.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Settings;

use WPAdminHealth\Settings\SettingsRegistry;
use WPAdminHealth\Settings\Domain\DatabaseSettings;
use WPAdminHealth\Tests\StandaloneTestCase;

class SettingsRegistryTest extends StandaloneTestCase {

	public function test_sanitize_settings_migrates_legacy_orphaned_key(): void {
		$registry = new SettingsRegistry();
		$registry->register( new DatabaseSettings() );

		$sanitized = $registry->sanitize_settings(
			array(
				'cleanup_orphaned_metadata' => '1',
			)
		);

		$this->assertArrayHasKey( 'orphaned_cleanup_enabled', $sanitized );
		$this->assertTrue( $sanitized['orphaned_cleanup_enabled'] );
		$this->assertArrayNotHasKey( 'cleanup_orphaned_metadata', $sanitized );
	}

	public function test_sanitize_settings_clamps_integer_values(): void {
		$registry = new SettingsRegistry();
		$registry->register( new DatabaseSettings() );

		$sanitized = $registry->sanitize_settings(
			array(
				'revisions_to_keep'    => '-1',
				'auto_clean_trash_days' => 999,
				'auto_clean_spam_days'  => '-5',
			)
		);

		$this->assertSame( 0, $sanitized['revisions_to_keep'] );
		$this->assertSame( 365, $sanitized['auto_clean_trash_days'] );
		$this->assertSame( 0, $sanitized['auto_clean_spam_days'] );
	}

	public function test_sanitize_settings_normalizes_newline_list(): void {
		$registry = new SettingsRegistry();
		$registry->register( new DatabaseSettings() );

		$sanitized = $registry->sanitize_settings(
			array(
				'excluded_transient_prefixes' => "  wc_\r\nwc_\n  my prefix \n\n",
			)
		);

		$this->assertSame( "wc_\nmyprefix", $sanitized['excluded_transient_prefixes'] );
	}
}

