<?php
/**
 * Task Result DTO
 *
 * Standardized result structure for scheduled task execution.
 *
 * @package WPAdminHealth\Scheduler
 */

namespace WPAdminHealth\Scheduler;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class TaskResult
 *
 * Data Transfer Object for task execution results.
 * Provides a consistent structure for all scheduled tasks.
 *
 * @since 1.7.0
 */
final class TaskResult {

	/**
	 * Whether the task executed successfully.
	 *
	 * @var bool
	 */
	private bool $success;

	/**
	 * Number of items found during execution.
	 *
	 * @var int
	 */
	private int $items_found;

	/**
	 * Number of items cleaned/processed during execution.
	 *
	 * @var int
	 */
	private int $items_cleaned;

	/**
	 * Bytes freed during execution.
	 *
	 * @var int
	 */
	private int $bytes_freed;

	/**
	 * Array of errors encountered during execution.
	 *
	 * @var array<string, string>
	 */
	private array $errors;

	/**
	 * Whether the task was interrupted and needs to resume.
	 *
	 * @var bool
	 */
	private bool $interrupted;

	/**
	 * Timestamp for the next scheduled run.
	 *
	 * @var string|null
	 */
	private ?string $next_run;

	/**
	 * Task identifier.
	 *
	 * @var string
	 */
	private string $task_id;

	/**
	 * Timestamp when the task was executed.
	 *
	 * @var string
	 */
	private string $executed_at;

	/**
	 * Elapsed time in seconds.
	 *
	 * @var float
	 */
	private float $elapsed_time;

	/**
	 * Constructor.
	 *
	 * @param bool                  $success       Whether the task executed successfully.
	 * @param int                   $items_found   Number of items found.
	 * @param int                   $items_cleaned Number of items cleaned.
	 * @param int                   $bytes_freed   Bytes freed.
	 * @param array<string, string> $errors        Errors encountered.
	 * @param bool                  $interrupted   Whether the task was interrupted.
	 * @param string|null           $next_run      Next scheduled run timestamp.
	 * @param string                $task_id       Task identifier.
	 * @param string                $executed_at   Execution timestamp.
	 * @param float                 $elapsed_time  Elapsed time in seconds.
	 */
	public function __construct(
		bool $success = true,
		int $items_found = 0,
		int $items_cleaned = 0,
		int $bytes_freed = 0,
		array $errors = array(),
		bool $interrupted = false,
		?string $next_run = null,
		string $task_id = '',
		string $executed_at = '',
		float $elapsed_time = 0.0
	) {
		$this->success       = $success;
		$this->items_found   = $items_found;
		$this->items_cleaned = $items_cleaned;
		$this->bytes_freed   = $bytes_freed;
		$this->errors        = $errors;
		$this->interrupted   = $interrupted;
		$this->next_run      = $next_run;
		$this->task_id       = $task_id;
		$this->executed_at   = $executed_at ?: current_time( 'mysql' );
		$this->elapsed_time  = $elapsed_time;
	}

	/**
	 * Create a TaskResult from an array.
	 *
	 * @param array<string, mixed> $data Result data array.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			(bool) ( $data['success'] ?? true ),
			(int) ( $data['items_found'] ?? $data['items_cleaned'] ?? 0 ),
			(int) ( $data['items_cleaned'] ?? 0 ),
			(int) ( $data['bytes_freed'] ?? 0 ),
			(array) ( $data['errors'] ?? array() ),
			(bool) ( $data['interrupted'] ?? $data['was_interrupted'] ?? false ),
			$data['next_run'] ?? null,
			(string) ( $data['task_id'] ?? '' ),
			(string) ( $data['executed_at'] ?? '' ),
			(float) ( $data['elapsed_time'] ?? 0.0 )
		);
	}

	/**
	 * Create a success result.
	 *
	 * @param string $task_id       Task identifier.
	 * @param int    $items_found   Number of items found.
	 * @param int    $items_cleaned Number of items cleaned.
	 * @param int    $bytes_freed   Bytes freed.
	 * @param float  $elapsed_time  Elapsed time in seconds.
	 * @return self
	 */
	public static function success(
		string $task_id,
		int $items_found = 0,
		int $items_cleaned = 0,
		int $bytes_freed = 0,
		float $elapsed_time = 0.0
	): self {
		return new self(
			true,
			$items_found,
			$items_cleaned,
			$bytes_freed,
			array(),
			false,
			null,
			$task_id,
			current_time( 'mysql' ),
			$elapsed_time
		);
	}

	/**
	 * Create a failure result.
	 *
	 * @param string                $task_id      Task identifier.
	 * @param array<string, string> $errors       Errors encountered.
	 * @param float                 $elapsed_time Elapsed time in seconds.
	 * @return self
	 */
	public static function failure(
		string $task_id,
		array $errors = array(),
		float $elapsed_time = 0.0
	): self {
		return new self(
			false,
			0,
			0,
			0,
			$errors,
			false,
			null,
			$task_id,
			current_time( 'mysql' ),
			$elapsed_time
		);
	}

	/**
	 * Create an interrupted result.
	 *
	 * @param string                $task_id       Task identifier.
	 * @param int                   $items_found   Number of items found so far.
	 * @param int                   $items_cleaned Number of items cleaned so far.
	 * @param int                   $bytes_freed   Bytes freed so far.
	 * @param array<string, string> $errors        Errors encountered.
	 * @param string|null           $next_run      Next scheduled run timestamp.
	 * @param float                 $elapsed_time  Elapsed time in seconds.
	 * @return self
	 */
	public static function interrupted(
		string $task_id,
		int $items_found = 0,
		int $items_cleaned = 0,
		int $bytes_freed = 0,
		array $errors = array(),
		?string $next_run = null,
		float $elapsed_time = 0.0
	): self {
		return new self(
			true,
			$items_found,
			$items_cleaned,
			$bytes_freed,
			$errors,
			true,
			$next_run,
			$task_id,
			current_time( 'mysql' ),
			$elapsed_time
		);
	}

	/**
	 * Check if the task was successful.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return $this->success;
	}

	/**
	 * Get the number of items found.
	 *
	 * @return int
	 */
	public function get_items_found(): int {
		return $this->items_found;
	}

	/**
	 * Get the number of items cleaned.
	 *
	 * @return int
	 */
	public function get_items_cleaned(): int {
		return $this->items_cleaned;
	}

	/**
	 * Get bytes freed.
	 *
	 * @return int
	 */
	public function get_bytes_freed(): int {
		return $this->bytes_freed;
	}

	/**
	 * Get errors.
	 *
	 * @return array<string, string>
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Check if there are errors.
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return ! empty( $this->errors );
	}

	/**
	 * Check if the task was interrupted.
	 *
	 * @return bool
	 */
	public function is_interrupted(): bool {
		return $this->interrupted;
	}

	/**
	 * Get the next run timestamp.
	 *
	 * @return string|null
	 */
	public function get_next_run(): ?string {
		return $this->next_run;
	}

	/**
	 * Get the task identifier.
	 *
	 * @return string
	 */
	public function get_task_id(): string {
		return $this->task_id;
	}

	/**
	 * Get the execution timestamp.
	 *
	 * @return string
	 */
	public function get_executed_at(): string {
		return $this->executed_at;
	}

	/**
	 * Get the elapsed time in seconds.
	 *
	 * @return float
	 */
	public function get_elapsed_time(): float {
		return $this->elapsed_time;
	}

	/**
	 * Convert to array for backwards compatibility.
	 *
	 * This maintains compatibility with existing code that expects array results.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'success'         => $this->success,
			'items_found'     => $this->items_found,
			'items_cleaned'   => $this->items_cleaned,
			'bytes_freed'     => $this->bytes_freed,
			'errors'          => $this->errors,
			'interrupted'     => $this->interrupted,
			'was_interrupted' => $this->interrupted, // Backwards compatibility alias.
			'next_run'        => $this->next_run,
			'task_id'         => $this->task_id,
			'executed_at'     => $this->executed_at,
			'elapsed_time'    => $this->elapsed_time,
		);
	}

	/**
	 * Create a new result with updated values.
	 *
	 * @param array<string, mixed> $updates Values to update.
	 * @return self
	 */
	public function with( array $updates ): self {
		return new self(
			$updates['success'] ?? $this->success,
			$updates['items_found'] ?? $this->items_found,
			$updates['items_cleaned'] ?? $this->items_cleaned,
			$updates['bytes_freed'] ?? $this->bytes_freed,
			$updates['errors'] ?? $this->errors,
			$updates['interrupted'] ?? $this->interrupted,
			$updates['next_run'] ?? $this->next_run,
			$updates['task_id'] ?? $this->task_id,
			$updates['executed_at'] ?? $this->executed_at,
			$updates['elapsed_time'] ?? $this->elapsed_time
		);
	}

	/**
	 * Add items to the current counts.
	 *
	 * @param int $found   Items found to add.
	 * @param int $cleaned Items cleaned to add.
	 * @param int $bytes   Bytes freed to add.
	 * @return self
	 */
	public function add_counts( int $found = 0, int $cleaned = 0, int $bytes = 0 ): self {
		return $this->with(
			array(
				'items_found'   => $this->items_found + $found,
				'items_cleaned' => $this->items_cleaned + $cleaned,
				'bytes_freed'   => $this->bytes_freed + $bytes,
			)
		);
	}

	/**
	 * Add an error.
	 *
	 * @param string $key     Error key/identifier.
	 * @param string $message Error message.
	 * @return self
	 */
	public function add_error( string $key, string $message ): self {
		$errors         = $this->errors;
		$errors[ $key ] = $message;
		return $this->with( array( 'errors' => $errors ) );
	}
}
