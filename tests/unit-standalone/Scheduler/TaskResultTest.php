<?php
/**
 * TaskResult unit tests.
 *
 * @package WPAdminHealth\Tests
 */

namespace WPAdminHealth\Tests\Scheduler;

use WPAdminHealth\Scheduler\TaskResult;
use WPAdminHealth\Tests\StandaloneTestCase;

class TaskResultTest extends StandaloneTestCase {

	public function test_constructor_sets_all_properties(): void {
		$result = new TaskResult(
			true,
			10,
			8,
			1024,
			array( 'subtask1' => 'error message' ),
			false,
			'2026-01-18 10:00:00',
			'database_cleanup',
			'2026-01-18 09:00:00',
			5.5
		);

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 10, $result->get_items_found() );
		$this->assertSame( 8, $result->get_items_cleaned() );
		$this->assertSame( 1024, $result->get_bytes_freed() );
		$this->assertSame( array( 'subtask1' => 'error message' ), $result->get_errors() );
		$this->assertFalse( $result->is_interrupted() );
		$this->assertSame( '2026-01-18 10:00:00', $result->get_next_run() );
		$this->assertSame( 'database_cleanup', $result->get_task_id() );
		$this->assertSame( '2026-01-18 09:00:00', $result->get_executed_at() );
		$this->assertSame( 5.5, $result->get_elapsed_time() );
	}

	public function test_default_values(): void {
		$result = new TaskResult();

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 0, $result->get_items_found() );
		$this->assertSame( 0, $result->get_items_cleaned() );
		$this->assertSame( 0, $result->get_bytes_freed() );
		$this->assertSame( array(), $result->get_errors() );
		$this->assertFalse( $result->is_interrupted() );
		$this->assertNull( $result->get_next_run() );
		$this->assertSame( '', $result->get_task_id() );
		$this->assertSame( 0.0, $result->get_elapsed_time() );
	}

	public function test_from_array_creates_instance(): void {
		$data = array(
			'success'       => true,
			'items_found'   => 15,
			'items_cleaned' => 12,
			'bytes_freed'   => 2048,
			'errors'        => array( 'test' => 'error' ),
			'interrupted'   => true,
			'next_run'      => '2026-01-19 00:00:00',
			'task_id'       => 'media_scan',
			'executed_at'   => '2026-01-18 12:00:00',
			'elapsed_time'  => 3.2,
		);

		$result = TaskResult::from_array( $data );

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 15, $result->get_items_found() );
		$this->assertSame( 12, $result->get_items_cleaned() );
		$this->assertSame( 2048, $result->get_bytes_freed() );
		$this->assertTrue( $result->is_interrupted() );
		$this->assertSame( 'media_scan', $result->get_task_id() );
	}

	public function test_from_array_handles_was_interrupted_alias(): void {
		$data = array(
			'success'         => true,
			'was_interrupted' => true,
		);

		$result = TaskResult::from_array( $data );

		$this->assertTrue( $result->is_interrupted() );
	}

	public function test_from_array_uses_items_cleaned_as_fallback_for_items_found(): void {
		$data = array(
			'items_cleaned' => 20,
		);

		$result = TaskResult::from_array( $data );

		$this->assertSame( 20, $result->get_items_found() );
	}

	public function test_success_factory_method(): void {
		$result = TaskResult::success( 'database_cleanup', 10, 8, 1024, 2.5 );

		$this->assertTrue( $result->is_success() );
		$this->assertFalse( $result->is_interrupted() );
		$this->assertSame( 10, $result->get_items_found() );
		$this->assertSame( 8, $result->get_items_cleaned() );
		$this->assertSame( 1024, $result->get_bytes_freed() );
		$this->assertSame( 'database_cleanup', $result->get_task_id() );
		$this->assertSame( 2.5, $result->get_elapsed_time() );
		$this->assertSame( array(), $result->get_errors() );
	}

	public function test_failure_factory_method(): void {
		$errors = array( 'cleanup' => 'Database error' );
		$result = TaskResult::failure( 'database_cleanup', $errors, 1.5 );

		$this->assertFalse( $result->is_success() );
		$this->assertFalse( $result->is_interrupted() );
		$this->assertSame( 0, $result->get_items_found() );
		$this->assertSame( 0, $result->get_items_cleaned() );
		$this->assertSame( 0, $result->get_bytes_freed() );
		$this->assertSame( 'database_cleanup', $result->get_task_id() );
		$this->assertSame( 1.5, $result->get_elapsed_time() );
		$this->assertSame( $errors, $result->get_errors() );
	}

	public function test_interrupted_factory_method(): void {
		$errors = array( 'subtask3' => 'Timed out' );
		$result = TaskResult::interrupted(
			'media_scan',
			50,
			40,
			10240,
			$errors,
			'2026-01-19 03:00:00',
			24.5
		);

		$this->assertTrue( $result->is_success() );
		$this->assertTrue( $result->is_interrupted() );
		$this->assertSame( 50, $result->get_items_found() );
		$this->assertSame( 40, $result->get_items_cleaned() );
		$this->assertSame( 10240, $result->get_bytes_freed() );
		$this->assertSame( 'media_scan', $result->get_task_id() );
		$this->assertSame( 24.5, $result->get_elapsed_time() );
		$this->assertSame( '2026-01-19 03:00:00', $result->get_next_run() );
		$this->assertSame( $errors, $result->get_errors() );
	}

	public function test_has_errors_returns_true_when_errors_exist(): void {
		$result = new TaskResult(
			true,
			0,
			0,
			0,
			array( 'error_key' => 'error message' )
		);

		$this->assertTrue( $result->has_errors() );
	}

	public function test_has_errors_returns_false_when_no_errors(): void {
		$result = new TaskResult();

		$this->assertFalse( $result->has_errors() );
	}

	public function test_to_array_returns_all_fields(): void {
		$result = new TaskResult(
			true,
			10,
			8,
			1024,
			array( 'key' => 'value' ),
			true,
			'2026-01-19 00:00:00',
			'test_task',
			'2026-01-18 12:00:00',
			5.0
		);

		$array = $result->to_array();

		$this->assertIsArray( $array );
		$this->assertSame( true, $array['success'] );
		$this->assertSame( 10, $array['items_found'] );
		$this->assertSame( 8, $array['items_cleaned'] );
		$this->assertSame( 1024, $array['bytes_freed'] );
		$this->assertSame( array( 'key' => 'value' ), $array['errors'] );
		$this->assertSame( true, $array['interrupted'] );
		$this->assertSame( true, $array['was_interrupted'] ); // Backwards compatibility.
		$this->assertSame( '2026-01-19 00:00:00', $array['next_run'] );
		$this->assertSame( 'test_task', $array['task_id'] );
		$this->assertSame( '2026-01-18 12:00:00', $array['executed_at'] );
		$this->assertSame( 5.0, $array['elapsed_time'] );
	}

	public function test_with_creates_new_instance_with_updated_values(): void {
		$original = TaskResult::success( 'test_task', 5, 3, 100, 1.0 );

		$updated = $original->with(
			array(
				'items_found'   => 10,
				'items_cleaned' => 8,
			)
		);

		// Original unchanged.
		$this->assertSame( 5, $original->get_items_found() );
		$this->assertSame( 3, $original->get_items_cleaned() );

		// Updated has new values.
		$this->assertSame( 10, $updated->get_items_found() );
		$this->assertSame( 8, $updated->get_items_cleaned() );

		// Other values preserved.
		$this->assertSame( 'test_task', $updated->get_task_id() );
		$this->assertSame( 100, $updated->get_bytes_freed() );
	}

	public function test_add_counts_increments_values(): void {
		$result = TaskResult::success( 'test_task', 5, 3, 100, 1.0 );

		$updated = $result->add_counts( 10, 8, 500 );

		// Original unchanged.
		$this->assertSame( 5, $result->get_items_found() );
		$this->assertSame( 3, $result->get_items_cleaned() );
		$this->assertSame( 100, $result->get_bytes_freed() );

		// Updated has incremented values.
		$this->assertSame( 15, $updated->get_items_found() );
		$this->assertSame( 11, $updated->get_items_cleaned() );
		$this->assertSame( 600, $updated->get_bytes_freed() );
	}

	public function test_add_error_adds_error_to_array(): void {
		$result = TaskResult::success( 'test_task' );

		$updated = $result->add_error( 'subtask1', 'Error message 1' );
		$updated = $updated->add_error( 'subtask2', 'Error message 2' );

		// Original unchanged.
		$this->assertFalse( $result->has_errors() );

		// Updated has errors.
		$this->assertTrue( $updated->has_errors() );
		$errors = $updated->get_errors();
		$this->assertSame( 'Error message 1', $errors['subtask1'] );
		$this->assertSame( 'Error message 2', $errors['subtask2'] );
	}

	public function test_immutability(): void {
		$original = TaskResult::success( 'test_task', 5, 5, 100, 1.0 );

		// All mutation methods should return new instances.
		$with_updated    = $original->with( array( 'success' => false ) );
		$with_counts     = $original->add_counts( 10 );
		$with_error      = $original->add_error( 'key', 'value' );

		// Original should be unchanged.
		$this->assertTrue( $original->is_success() );
		$this->assertSame( 5, $original->get_items_found() );
		$this->assertFalse( $original->has_errors() );

		// New instances should be different objects.
		$this->assertNotSame( $original, $with_updated );
		$this->assertNotSame( $original, $with_counts );
		$this->assertNotSame( $original, $with_error );
	}
}
