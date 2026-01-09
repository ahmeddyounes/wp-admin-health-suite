<?php
/**
 * Batch Processor Class
 *
 * Provides efficient batch processing for large datasets using generators
 * to avoid memory issues on sites with 100k+ posts.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Batch Processor class for handling large datasets efficiently.
 *
 * Uses PHP generators to process large datasets without loading
 * everything into memory at once. This prevents timeout and memory
 * issues on large sites.
 *
 * @since 1.0.0
 */
class Batch_Processor {

	/**
	 * Default batch size for processing.
	 *
	 * @var int
	 */
	const DEFAULT_BATCH_SIZE = 100;

	/**
	 * Process posts in batches using a generator.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args    WP_Query arguments.
	 * @param int   $batch_size Batch size for processing.
	 * @return \Generator Generator yielding posts in batches.
	 */
	public static function process_posts( $args = array(), $batch_size = self::DEFAULT_BATCH_SIZE ) {
		global $wpdb;

		// Default query args.
		$defaults = array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => $batch_size,
			'paged'          => 1,
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Use direct query for better performance.
		$post_type = is_array( $args['post_type'] ) ? $args['post_type'] : array( $args['post_type'] );
		$post_status = is_array( $args['post_status'] ) ? $args['post_status'] : array( $args['post_status'] );

		$post_type_in = "'" . implode( "','", array_map( 'esc_sql', $post_type ) ) . "'";
		$post_status_in = "'" . implode( "','", array_map( 'esc_sql', $post_status ) ) . "'";

		$offset = 0;

		while ( true ) {
			$query = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type IN ($post_type_in)
				AND post_status IN ($post_status_in)
				ORDER BY ID ASC
				LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			);

			$post_ids = $wpdb->get_col( $query );

			if ( empty( $post_ids ) ) {
				break;
			}

			yield $post_ids;

			$offset += $batch_size;

			// Allow the server to breathe.
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}
	}

	/**
	 * Process attachments in batches using a generator.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args    Additional query arguments.
	 * @param int   $batch_size Batch size for processing.
	 * @return \Generator Generator yielding attachment IDs in batches.
	 */
	public static function process_attachments( $args = array(), $batch_size = self::DEFAULT_BATCH_SIZE ) {
		global $wpdb;

		$mime_type = isset( $args['mime_type'] ) ? $args['mime_type'] : '';
		$offset = 0;

		while ( true ) {
			if ( ! empty( $mime_type ) ) {
				$query = $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = %s
					AND post_mime_type LIKE %s
					ORDER BY ID ASC
					LIMIT %d OFFSET %d",
					'attachment',
					$wpdb->esc_like( $mime_type ) . '%',
					$batch_size,
					$offset
				);
			} else {
				$query = $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = %s
					ORDER BY ID ASC
					LIMIT %d OFFSET %d",
					'attachment',
					$batch_size,
					$offset
				);
			}

			$attachment_ids = $wpdb->get_col( $query );

			if ( empty( $attachment_ids ) ) {
				break;
			}

			yield $attachment_ids;

			$offset += $batch_size;

			// Allow the server to breathe.
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}
	}

	/**
	 * Process database rows in batches using a generator.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table      Table name.
	 * @param string $where      WHERE clause (without WHERE keyword).
	 * @param int    $batch_size Batch size for processing.
	 * @param string $id_column  Primary key column name.
	 * @return \Generator Generator yielding rows in batches.
	 */
	public static function process_table_rows( $table, $where = '1=1', $batch_size = self::DEFAULT_BATCH_SIZE, $id_column = 'ID' ) {
		global $wpdb;

		$offset = 0;

		while ( true ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE {$where}
				ORDER BY {$id_column} ASC
				LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			);

			$rows = $wpdb->get_results( $query );

			if ( empty( $rows ) ) {
				break;
			}

			yield $rows;

			$offset += $batch_size;

			// Allow the server to breathe.
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}
	}

	/**
	 * Process comments in batches using a generator.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args    Additional query arguments.
	 * @param int   $batch_size Batch size for processing.
	 * @return \Generator Generator yielding comment IDs in batches.
	 */
	public static function process_comments( $args = array(), $batch_size = self::DEFAULT_BATCH_SIZE ) {
		global $wpdb;

		$status = isset( $args['status'] ) ? $args['status'] : '';
		$offset = 0;

		while ( true ) {
			if ( ! empty( $status ) ) {
				$query = $wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments}
					WHERE comment_approved = %s
					ORDER BY comment_ID ASC
					LIMIT %d OFFSET %d",
					$status,
					$batch_size,
					$offset
				);
			} else {
				$query = $wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments}
					ORDER BY comment_ID ASC
					LIMIT %d OFFSET %d",
					$batch_size,
					$offset
				);
			}

			$comment_ids = $wpdb->get_col( $query );

			if ( empty( $comment_ids ) ) {
				break;
			}

			yield $comment_ids;

			$offset += $batch_size;

			// Allow the server to breathe.
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}
		}
	}

	/**
	 * Get total count for a query without loading all results.
	 *
	 * @since 1.0.0
	 *
	 * @param string $table Table name.
	 * @param string $where WHERE clause (without WHERE keyword).
	 * @return int Total count.
	 */
	public static function get_total_count( $table, $where = '1=1' ) {
		global $wpdb;

		$query = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		return absint( $wpdb->get_var( $query ) );
	}

	/**
	 * Execute a callback for each batch with progress tracking.
	 *
	 * @since 1.0.0
	 *
	 * @param \Generator $generator     Generator function yielding batches.
	 * @param callable   $callback      Callback to execute for each batch.
	 * @param int        $total         Total number of items (for progress).
	 * @param callable   $progress_callback Optional callback for progress updates.
	 * @return array Results from callback executions.
	 */
	public static function execute_with_progress( $generator, $callback, $total = 0, $progress_callback = null ) {
		$results = array();
		$processed = 0;

		foreach ( $generator as $batch ) {
			$result = call_user_func( $callback, $batch );

			if ( null !== $result ) {
				$results[] = $result;
			}

			$processed += count( $batch );

			// Call progress callback if provided.
			if ( null !== $progress_callback && $total > 0 ) {
				$progress = ( $processed / $total ) * 100;
				call_user_func( $progress_callback, $progress, $processed, $total );
			}

			// Prevent timeouts on large operations.
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 30 );
			}
		}

		return $results;
	}

	/**
	 * Delete posts in batches to avoid memory issues.
	 *
	 * @since 1.0.0
	 *
	 * @param array $post_ids   Post IDs to delete.
	 * @param bool  $force_delete Whether to bypass trash and force delete.
	 * @param int   $batch_size Batch size for processing.
	 * @return int Number of posts deleted.
	 */
	public static function delete_posts_in_batches( $post_ids, $force_delete = false, $batch_size = self::DEFAULT_BATCH_SIZE ) {
		if ( empty( $post_ids ) ) {
			return 0;
		}

		$deleted = 0;
		$chunks = array_chunk( $post_ids, $batch_size );

		foreach ( $chunks as $chunk ) {
			foreach ( $chunk as $post_id ) {
				$result = wp_delete_post( $post_id, $force_delete );
				if ( false !== $result ) {
					$deleted++;
				}
			}

			// Clear caches to prevent memory buildup.
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}

			// Prevent timeouts.
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 30 );
			}
		}

		return $deleted;
	}

	/**
	 * Delete comments in batches to avoid memory issues.
	 *
	 * @since 1.0.0
	 *
	 * @param array $comment_ids Comment IDs to delete.
	 * @param bool  $force_delete Whether to bypass trash and force delete.
	 * @param int   $batch_size  Batch size for processing.
	 * @return int Number of comments deleted.
	 */
	public static function delete_comments_in_batches( $comment_ids, $force_delete = false, $batch_size = self::DEFAULT_BATCH_SIZE ) {
		if ( empty( $comment_ids ) ) {
			return 0;
		}

		$deleted = 0;
		$chunks = array_chunk( $comment_ids, $batch_size );

		foreach ( $chunks as $chunk ) {
			foreach ( $chunk as $comment_id ) {
				$result = wp_delete_comment( $comment_id, $force_delete );
				if ( false !== $result ) {
					$deleted++;
				}
			}

			// Clear caches to prevent memory buildup.
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
			}

			// Prevent timeouts.
			if ( function_exists( 'set_time_limit' ) ) {
				set_time_limit( 30 );
			}
		}

		return $deleted;
	}
}
