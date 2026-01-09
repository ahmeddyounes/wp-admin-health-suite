<?php
/**
 * Standalone Test Case for unit tests not requiring WordPress
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base test case class for standalone unit tests.
 *
 * Use this for testing components that don't depend on WordPress functions.
 */
abstract class StandaloneTestCase extends TestCase {

	/**
	 * Set up test environment before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->setup_test_environment();
	}

	/**
	 * Clean up test environment after each test.
	 */
	protected function tearDown(): void {
		$this->cleanup_test_environment();
		parent::tearDown();
	}

	/**
	 * Setup test-specific environment.
	 *
	 * Override this method in child classes for custom setup.
	 */
	protected function setup_test_environment(): void {
		// Override in child classes.
	}

	/**
	 * Cleanup test-specific environment.
	 *
	 * Override this method in child classes for custom cleanup.
	 */
	protected function cleanup_test_environment(): void {
		// Override in child classes.
	}
}
