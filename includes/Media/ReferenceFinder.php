<?php
/**
 * Media Reference Finder Class
 *
 * Finds all references to media attachments across WordPress content.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\ReferenceFinderInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Reference Finder class for locating media usage across the site.
 *
 * @since 1.0.0
 * @since 1.2.0 Implements ReferenceFinderInterface.
 */
class ReferenceFinder implements ReferenceFinderInterface {

	/**
	 * Database connection.
	 *
	 * @var ConnectionInterface
	 */
	private ConnectionInterface $connection;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param ConnectionInterface $connection Database connection.
	 */
	public function __construct( ConnectionInterface $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Find all references to a media attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment ID to search for.
	 * @return array Array of reference locations with context.
	 */
	public function find_references( int $attachment_id ): array {
		$references = array();

		// Get attachment URL and metadata.
		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( ! $attachment_url ) {
			return $references;
		}

		$upload_dir = wp_upload_dir();
		$attachment_path = str_replace( $upload_dir['baseurl'], '', $attachment_url );
		$attachment_filename = basename( $attachment_url );

		// Search in post content.
		$content_refs = $this->search_post_content( $attachment_id, $attachment_path, $attachment_filename );
		$references = array_merge( $references, $content_refs );

		// Search in featured images.
		$featured_refs = $this->search_featured_images( $attachment_id );
		$references = array_merge( $references, $featured_refs );

		// Search in post meta (galleries, ACF fields, etc.).
		$postmeta_refs = $this->search_postmeta( $attachment_id, $attachment_path, $attachment_filename );
		$references = array_merge( $references, $postmeta_refs );

		// Search in options (widgets, customizer, etc.).
		$options_refs = $this->search_options( $attachment_id, $attachment_path, $attachment_filename );
		$references = array_merge( $references, $options_refs );

		// Search for WooCommerce product galleries.
		$woo_refs = $this->search_woocommerce_galleries( $attachment_id );
		$references = array_merge( $references, $woo_refs );

		// Search for Elementor usage.
		$elementor_refs = $this->search_elementor( $attachment_id );
		$references = array_merge( $references, $elementor_refs );

		// Search for Beaver Builder usage.
		$beaver_refs = $this->search_beaver_builder( $attachment_id );
		$references = array_merge( $references, $beaver_refs );

		// Search for ACF fields.
		$acf_refs = $this->search_acf_fields( $attachment_id );
		$references = array_merge( $references, $acf_refs );

		// Search in user meta (profile pictures, custom user fields).
		$usermeta_refs = $this->search_usermeta( $attachment_id, $attachment_path, $attachment_filename );
		$references = array_merge( $references, $usermeta_refs );

		// Search in term meta (category/tag images).
		$termmeta_refs = $this->search_termmeta( $attachment_id, $attachment_path, $attachment_filename );
		$references = array_merge( $references, $termmeta_refs );

		// Check parent post attachment.
		$parent_refs = $this->check_parent_attachment( $attachment_id );
		$references = array_merge( $references, $parent_refs );

		return $references;
	}

	/**
	 * Check if media is used anywhere.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment ID to check.
	 * @return bool True if used, false otherwise.
	 */
	public function is_media_used( int $attachment_id ): bool {
		$references = $this->find_references( $attachment_id );
		return ! empty( $references );
	}

	/**
	 * Get reference locations with detailed context.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment ID to search for.
	 * @return array Array of reference locations with actionable context.
	 */
	public function get_reference_locations( int $attachment_id ): array {
		return $this->find_references( $attachment_id );
	}

	/**
	 * Search for references in post content.
	 *
	 * Searches for media in:
	 * - Classic editor: direct URLs and filenames
	 * - Block editor (Gutenberg): wp-image-{ID} class and JSON attributes like {"id":123}
	 * - Shortcodes: [gallery ids="123,456"]
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $attachment_path Attachment path.
	 * @param string $attachment_filename Attachment filename.
	 * @return array Array of references found in post content.
	 */
	private function search_post_content( $attachment_id, $attachment_path, $attachment_filename ) {
		$references = array();

		$posts_table = $this->connection->get_posts_table();

		$id_str = (string) $attachment_id;

		// Search by URL/path or filename or wp-image-{ID} class.
		// Also search for Gutenberg block JSON attributes {"id":123} and shortcode patterns.
		$query = $this->connection->prepare(
			"SELECT ID, post_title, post_type, post_status
			FROM {$posts_table}
			WHERE post_status NOT IN ('trash', 'auto-draft')
			AND post_type != %s
			AND (
				post_content LIKE %s
				OR post_content LIKE %s
				OR post_content LIKE %s
				OR post_content LIKE %s
				OR post_content LIKE %s
				OR post_content LIKE %s
			)",
			'attachment',
			'%' . $this->connection->esc_like( $attachment_path ) . '%',
			'%' . $this->connection->esc_like( $attachment_filename ) . '%',
			'%wp-image-' . $attachment_id . '%',
			'%' . $this->connection->esc_like( '"id":' . $id_str ) . '%',
			'%' . $this->connection->esc_like( '"id": ' . $id_str ) . '%',
			'%' . $this->connection->esc_like( 'ids="' ) . '%' . $this->connection->esc_like( $id_str ) . '%'
		);

		if ( null === $query ) {
			return $references;
		}

		$posts = $this->connection->get_results( $query );

		foreach ( $posts as $post ) {
			$references[] = array(
				'location' => 'post_content',
				'post_id' => $post->ID,
				'post_title' => $post->post_title,
				'post_type' => $post->post_type,
				'post_status' => $post->post_status,
				'context' => 'Content in ' . $post->post_type . ': ' . $post->post_title,
				'edit_url' => get_edit_post_link( $post->ID ),
			);
		}

		return $references;
	}

	/**
	 * Search for featured images.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found in featured images.
	 */
	private function search_featured_images( $attachment_id ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		$query = $this->connection->prepare(
			"SELECT post_id FROM {$postmeta_table}
			WHERE meta_key = %s AND meta_value = %d",
			'_thumbnail_id',
			$attachment_id
		);

		if ( null === $query ) {
			return $references;
		}

		$post_ids = $this->connection->get_col( $query );

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$references[] = array(
					'location' => 'featured_image',
					'post_id' => $post_id,
					'post_title' => $post->post_title,
					'post_type' => $post->post_type,
					'post_status' => $post->post_status,
					'context' => 'Featured image for ' . $post->post_type . ': ' . $post->post_title,
					'edit_url' => get_edit_post_link( $post_id ),
				);
			}
		}

		return $references;
	}

	/**
	 * Search for references in postmeta.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $attachment_path Attachment path.
	 * @param string $attachment_filename Attachment filename.
	 * @return array Array of references found in postmeta.
	 */
	private function search_postmeta( $attachment_id, $attachment_path, $attachment_filename ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		// Build serialized ID pattern safely (for JSON/serialized arrays).
		$serialized_id_pattern = '%' . $this->connection->esc_like( '"' . (string) $attachment_id . '"' ) . '%';

		$query = $this->connection->prepare(
			"SELECT post_id, meta_key, meta_value
			FROM {$postmeta_table}
			WHERE meta_key NOT LIKE %s
			AND (
				meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value = %d
				OR meta_value LIKE %s
			)",
			'%' . $this->connection->esc_like( '_thumbnail_id' ) . '%',
			'%' . $this->connection->esc_like( $attachment_path ) . '%',
			'%' . $this->connection->esc_like( $attachment_filename ) . '%',
			$attachment_id,
			$serialized_id_pattern
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		foreach ( $results as $result ) {
			$post = get_post( $result->post_id );
			if ( $post ) {
				$references[] = array(
					'location' => 'postmeta',
					'post_id' => $result->post_id,
					'post_title' => $post->post_title,
					'post_type' => $post->post_type,
					'post_status' => $post->post_status,
					'meta_key' => $result->meta_key,
					'context' => 'Post meta "' . $result->meta_key . '" in ' . $post->post_type . ': ' . $post->post_title,
					'edit_url' => get_edit_post_link( $result->post_id ),
				);
			}
		}

		return $references;
	}

	/**
	 * Search for references in options table.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $attachment_path Attachment path.
	 * @param string $attachment_filename Attachment filename.
	 * @return array Array of references found in options.
	 */
	private function search_options( $attachment_id, $attachment_path, $attachment_filename ) {
		$references = array();

		$options_table = $this->connection->get_options_table();

		// Build serialized ID pattern safely (for JSON/serialized arrays).
		$serialized_id_pattern = '%' . $this->connection->esc_like( '"' . (string) $attachment_id . '"' ) . '%';

		$query = $this->connection->prepare(
			"SELECT option_name, option_value
			FROM {$options_table}
			WHERE option_name NOT LIKE %s
			AND option_name NOT LIKE %s
			AND (
				option_value LIKE %s
				OR option_value LIKE %s
				OR option_value = %d
				OR option_value LIKE %s
			)",
			'%' . $this->connection->esc_like( '_transient_' ) . '%',
			'%' . $this->connection->esc_like( '_site_transient_' ) . '%',
			'%' . $this->connection->esc_like( $attachment_path ) . '%',
			'%' . $this->connection->esc_like( $attachment_filename ) . '%',
			$attachment_id,
			$serialized_id_pattern
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		foreach ( $results as $result ) {
			$context_type = 'Option';
			if ( strpos( $result->option_name, 'widget' ) !== false ) {
				$context_type = 'Widget';
			} elseif ( strpos( $result->option_name, 'theme_mods' ) !== false || strpos( $result->option_name, 'theme_mod' ) !== false ) {
				$context_type = 'Theme customizer';
			} elseif ( strpos( $result->option_name, 'sidebars_widgets' ) !== false ) {
				$context_type = 'Sidebar widget area';
			}

			$references[] = array(
				'location' => 'option',
				'option_name' => $result->option_name,
				'context' => $context_type . ': ' . $result->option_name,
				'edit_url' => admin_url( 'options.php' ),
			);
		}

		return $references;
	}

	/**
	 * Search for WooCommerce product gallery references.
	 *
	 * WooCommerce stores product gallery IDs as a comma-separated string
	 * (e.g., "123,456,789"). We need to check for exact matches to avoid
	 * false positives (e.g., ID 1 matching "10,21,100").
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found in WooCommerce galleries.
	 */
	private function search_woocommerce_galleries( $attachment_id ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		// Build patterns that ensure exact ID matching in comma-separated lists.
		// Patterns: start of string, after comma, before comma, end of string, or exact match.
		$id_str = (string) $attachment_id;

		$query = $this->connection->prepare(
			"SELECT post_id, meta_value
			FROM {$postmeta_table}
			WHERE meta_key = %s
			AND (
				meta_value = %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			'_product_image_gallery',
			$id_str,
			$id_str . ',%',
			'%,' . $id_str . ',%',
			'%,' . $id_str
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		foreach ( $results as $result ) {
			// Double-check by parsing the comma-separated list to avoid edge cases.
			$gallery_ids = array_map( 'intval', explode( ',', $result->meta_value ) );
			if ( ! in_array( $attachment_id, $gallery_ids, true ) ) {
				continue;
			}

			$post = get_post( $result->post_id );
			if ( $post ) {
				$references[] = array(
					'location' => 'woocommerce_gallery',
					'post_id' => $result->post_id,
					'post_title' => $post->post_title,
					'post_type' => $post->post_type,
					'post_status' => $post->post_status,
					'context' => 'WooCommerce product gallery: ' . $post->post_title,
					'edit_url' => get_edit_post_link( $result->post_id ),
				);
			}
		}

		return $references;
	}

	/**
	 * Search for Elementor page builder references.
	 *
	 * Elementor stores media IDs in JSON format. Common patterns:
	 * - "id":123 (no space)
	 * - "id": 123 (with space)
	 * - "id":"123" (as string)
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found in Elementor data.
	 */
	private function search_elementor( $attachment_id ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		$id_str = (string) $attachment_id;

		// Build multiple Elementor ID patterns to handle JSON variations.
		// Pattern 1: "id":123 or "id": 123 (integer format).
		$id_pattern_int = '%' . $this->connection->esc_like( '"id":' ) . '%' . $this->connection->esc_like( $id_str ) . '%';
		// Pattern 2: "id":"123" (string format).
		$id_pattern_str = '%' . $this->connection->esc_like( '"id":"' . $id_str . '"' ) . '%';
		// Pattern 3: attachment ID anywhere in JSON (catches nested structures).
		$generic_pattern = '%' . $this->connection->esc_like( ':' . $id_str . ',' ) . '%';
		$generic_pattern2 = '%' . $this->connection->esc_like( ':' . $id_str . '}' ) . '%';

		$query = $this->connection->prepare(
			"SELECT post_id, meta_value
			FROM {$postmeta_table}
			WHERE meta_key = %s
			AND (
				meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			'_elementor_data',
			$id_pattern_int,
			$id_pattern_str,
			$generic_pattern,
			$generic_pattern2
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		foreach ( $results as $result ) {
			// Verify the attachment ID actually exists in the JSON data.
			// Use regex to find exact ID matches in JSON context.
			$json_data = $result->meta_value;
			$pattern = '/"id"\s*:\s*"?' . preg_quote( $id_str, '/' ) . '"?(?:[,}\]])/';
			if ( ! preg_match( $pattern, $json_data ) ) {
				// Also check for the ID in arrays or other contexts.
				if ( strpos( $json_data, ':' . $id_str . ',' ) === false
					&& strpos( $json_data, ':' . $id_str . '}' ) === false
					&& strpos( $json_data, ':' . $id_str . ']' ) === false ) {
					continue;
				}
			}

			$post = get_post( $result->post_id );
			if ( $post ) {
				$references[] = array(
					'location' => 'elementor',
					'post_id' => $result->post_id,
					'post_title' => $post->post_title,
					'post_type' => $post->post_type,
					'post_status' => $post->post_status,
					'context' => 'Elementor content in ' . $post->post_type . ': ' . $post->post_title,
					'edit_url' => get_edit_post_link( $result->post_id ),
				);
			}
		}

		return $references;
	}

	/**
	 * Search for Beaver Builder references.
	 *
	 * Beaver Builder stores data in serialized format. Common patterns:
	 * - s:5:"photo";i:123; (integer ID)
	 * - s:5:"photo";s:3:"123"; (string ID)
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found in Beaver Builder data.
	 */
	private function search_beaver_builder( $attachment_id ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		// Check multiple Beaver Builder meta keys.
		$bb_meta_keys = array( '_fl_builder_data', '_fl_builder_draft', '_fl_builder_data_settings' );

		$id_str = (string) $attachment_id;

		// Build Beaver Builder patterns for serialized data.
		// Pattern for integer: i:123;
		$serialized_int = '%' . $this->connection->esc_like( 'i:' . $id_str . ';' ) . '%';
		// Pattern for string: s:N:"123";
		$serialized_str = '%' . $this->connection->esc_like( ':"' . $id_str . '";' ) . '%';
		// Pattern for JSON in newer versions.
		$json_pattern = '%' . $this->connection->esc_like( ':' . $id_str . ',' ) . '%';
		$json_pattern2 = '%' . $this->connection->esc_like( ':' . $id_str . '}' ) . '%';

		foreach ( $bb_meta_keys as $meta_key ) {
			$query = $this->connection->prepare(
				"SELECT post_id, meta_value
				FROM {$postmeta_table}
				WHERE meta_key = %s
				AND (
					meta_value LIKE %s
					OR meta_value LIKE %s
					OR meta_value LIKE %s
					OR meta_value LIKE %s
				)",
				$meta_key,
				$serialized_int,
				$serialized_str,
				$json_pattern,
				$json_pattern2
			);

			if ( null === $query ) {
				continue;
			}

			$results = $this->connection->get_results( $query );

			foreach ( $results as $result ) {
				// Verify by checking for exact ID patterns.
				$data = $result->meta_value;
				$found = false;

				// Check serialized integer pattern.
				if ( preg_match( '/i:' . preg_quote( $id_str, '/' ) . ';/', $data ) ) {
					$found = true;
				}
				// Check serialized string pattern.
				if ( ! $found && preg_match( '/s:\d+:"' . preg_quote( $id_str, '/' ) . '";/', $data ) ) {
					$found = true;
				}
				// Check JSON patterns.
				if ( ! $found && ( strpos( $data, ':' . $id_str . ',' ) !== false
					|| strpos( $data, ':' . $id_str . '}' ) !== false
					|| strpos( $data, ':' . $id_str . ']' ) !== false ) ) {
					$found = true;
				}

				if ( ! $found ) {
					continue;
				}

				$post = get_post( $result->post_id );
				if ( $post ) {
					$references[] = array(
						'location' => 'beaver_builder',
						'post_id' => $result->post_id,
						'post_title' => $post->post_title,
						'post_type' => $post->post_type,
						'post_status' => $post->post_status,
						'context' => 'Beaver Builder content in ' . $post->post_type . ': ' . $post->post_title,
						'edit_url' => get_edit_post_link( $result->post_id ),
					);
				}
			}
		}

		return $references;
	}

	/**
	 * Search for ACF (Advanced Custom Fields) references.
	 *
	 * ACF stores attachment IDs in various formats:
	 * - Plain integer: 123
	 * - Serialized integer in array: a:1:{i:0;i:123;}
	 * - Serialized string in array: a:1:{i:0;s:3:"123";}
	 * - JSON array: [123,456]
	 * - JSON object: {"id":123}
	 *
	 * This method searches broadly first, then verifies ACF field ownership.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found in ACF fields.
	 */
	private function search_acf_fields( $attachment_id ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		$id_str = (string) $attachment_id;

		// ACF stores attachment IDs as plain integers or in serialized/JSON arrays.
		// Build patterns for various storage formats.
		$serialized_int_pattern = '%' . $this->connection->esc_like( 'i:' . $id_str . ';' ) . '%';
		$serialized_str_pattern = '%' . $this->connection->esc_like( ':"' . $id_str . '";' ) . '%';
		// JSON patterns for ACF 6.x+ which may use JSON.
		$json_array_pattern = '%' . $this->connection->esc_like( '[' . $id_str . ',' ) . '%';
		$json_array_pattern2 = '%' . $this->connection->esc_like( ',' . $id_str . ',' ) . '%';
		$json_array_pattern3 = '%' . $this->connection->esc_like( ',' . $id_str . ']' ) . '%';
		$json_array_single = '%' . $this->connection->esc_like( '[' . $id_str . ']' ) . '%';

		// First, find all potential matches (including those starting with underscore
		// since some ACF subfields may have unusual naming).
		$query = $this->connection->prepare(
			"SELECT post_id, meta_key, meta_value
			FROM {$postmeta_table}
			WHERE (
				meta_value = %d
				OR meta_value = %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			$attachment_id,
			$id_str,
			$serialized_int_pattern,
			$serialized_str_pattern,
			$json_array_pattern,
			$json_array_pattern2,
			$json_array_pattern3,
			$json_array_single
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		// Track already added to avoid duplicates.
		$added = array();

		foreach ( $results as $result ) {
			$meta_key = $result->meta_key;

			// Skip internal WordPress meta keys.
			if ( in_array( $meta_key, array( '_thumbnail_id', '_wp_attached_file', '_wp_attachment_metadata' ), true ) ) {
				continue;
			}

			// Skip Elementor, Beaver Builder, etc. (handled by dedicated methods).
			if ( strpos( $meta_key, '_elementor_' ) === 0 || strpos( $meta_key, '_fl_builder' ) === 0 ) {
				continue;
			}

			// Check if this is an ACF field by looking for the field key reference.
			$field_key_meta = get_post_meta( $result->post_id, '_' . $meta_key, true );
			$is_acf = ( ! empty( $field_key_meta ) && strpos( $field_key_meta, 'field_' ) === 0 );

			// Also check for ACF sub-fields (repeater fields store as field_name_0_subfield).
			if ( ! $is_acf ) {
				// Check if this looks like an ACF repeater/flexible content sub-field.
				if ( preg_match( '/^(.+)_(\d+)_(.+)$/', $meta_key, $matches ) ) {
					$parent_key = $matches[1];
					$parent_field_meta = get_post_meta( $result->post_id, '_' . $parent_key, true );
					$is_acf = ( ! empty( $parent_field_meta ) && strpos( $parent_field_meta, 'field_' ) === 0 );
				}
			}

			if ( ! $is_acf ) {
				continue;
			}

			// Verify the ID is actually in the value (avoid false positives).
			$meta_value = $result->meta_value;
			$found = false;

			// Exact integer match.
			if ( (int) $meta_value === $attachment_id ) {
				$found = true;
			}
			// Exact string match.
			if ( ! $found && $meta_value === $id_str ) {
				$found = true;
			}
			// Serialized integer.
			if ( ! $found && strpos( $meta_value, 'i:' . $id_str . ';' ) !== false ) {
				$found = true;
			}
			// Serialized string.
			if ( ! $found && preg_match( '/s:\d+:"' . preg_quote( $id_str, '/' ) . '";/', $meta_value ) ) {
				$found = true;
			}
			// JSON array.
			if ( ! $found && preg_match( '/[\[,]' . preg_quote( $id_str, '/' ) . '[\],]/', $meta_value ) ) {
				$found = true;
			}

			if ( ! $found ) {
				continue;
			}

			// Avoid duplicates.
			$key = $result->post_id . ':' . $meta_key;
			if ( isset( $added[ $key ] ) ) {
				continue;
			}
			$added[ $key ] = true;

			$post = get_post( $result->post_id );
			if ( $post ) {
				$references[] = array(
					'location' => 'acf_field',
					'post_id' => $result->post_id,
					'post_title' => $post->post_title,
					'post_type' => $post->post_type,
					'post_status' => $post->post_status,
					'meta_key' => $meta_key,
					'context' => 'ACF field "' . $meta_key . '" in ' . $post->post_type . ': ' . $post->post_title,
					'edit_url' => get_edit_post_link( $result->post_id ),
				);
			}
		}

		return $references;
	}

	/**
	 * Search for references in user meta.
	 *
	 * Media can be used in user profiles (avatars, custom profile fields, ACF user fields).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $attachment_path Attachment path.
	 * @param string $attachment_filename Attachment filename.
	 * @return array Array of references found in user meta.
	 */
	private function search_usermeta( $attachment_id, $attachment_path, $attachment_filename ) {
		$references = array();

		$usermeta_table = $this->connection->get_usermeta_table();

		$id_str = (string) $attachment_id;

		// Build patterns for user meta search.
		$serialized_int_pattern = '%' . $this->connection->esc_like( 'i:' . $id_str . ';' ) . '%';
		$serialized_str_pattern = '%' . $this->connection->esc_like( ':"' . $id_str . '";' ) . '%';

		$query = $this->connection->prepare(
			"SELECT user_id, meta_key, meta_value
			FROM {$usermeta_table}
			WHERE meta_key NOT LIKE %s
			AND (
				meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value = %d
				OR meta_value = %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			$this->connection->esc_like( 'wp_' ) . '%capabilities%',
			'%' . $this->connection->esc_like( $attachment_path ) . '%',
			'%' . $this->connection->esc_like( $attachment_filename ) . '%',
			$attachment_id,
			$id_str,
			$serialized_int_pattern,
			$serialized_str_pattern
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		foreach ( $results as $result ) {
			// Skip internal WordPress user meta.
			if ( in_array( $result->meta_key, array( 'session_tokens', 'wp_capabilities', 'wp_user_level' ), true ) ) {
				continue;
			}

			$user = get_userdata( $result->user_id );
			if ( $user ) {
				$references[] = array(
					'location' => 'usermeta',
					'user_id' => $result->user_id,
					'user_name' => $user->display_name,
					'meta_key' => $result->meta_key,
					'context' => 'User meta "' . $result->meta_key . '" for user: ' . $user->display_name,
					'edit_url' => get_edit_user_link( $result->user_id ),
				);
			}
		}

		return $references;
	}

	/**
	 * Search for references in term meta.
	 *
	 * Media can be used for category/tag images, taxonomy thumbnails, etc.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $attachment_path Attachment path.
	 * @param string $attachment_filename Attachment filename.
	 * @return array Array of references found in term meta.
	 */
	private function search_termmeta( $attachment_id, $attachment_path, $attachment_filename ) {
		$references = array();

		$termmeta_table = $this->connection->get_termmeta_table();

		$id_str = (string) $attachment_id;

		// Build patterns for term meta search.
		$serialized_int_pattern = '%' . $this->connection->esc_like( 'i:' . $id_str . ';' ) . '%';
		$serialized_str_pattern = '%' . $this->connection->esc_like( ':"' . $id_str . '";' ) . '%';

		$query = $this->connection->prepare(
			"SELECT term_id, meta_key, meta_value
			FROM {$termmeta_table}
			WHERE (
				meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value = %d
				OR meta_value = %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			'%' . $this->connection->esc_like( $attachment_path ) . '%',
			'%' . $this->connection->esc_like( $attachment_filename ) . '%',
			$attachment_id,
			$id_str,
			$serialized_int_pattern,
			$serialized_str_pattern
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		foreach ( $results as $result ) {
			$term = get_term( $result->term_id );
			if ( $term && ! is_wp_error( $term ) ) {
				$taxonomy_obj = get_taxonomy( $term->taxonomy );
				$taxonomy_label = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $term->taxonomy;

				$references[] = array(
					'location' => 'termmeta',
					'term_id' => $result->term_id,
					'term_name' => $term->name,
					'taxonomy' => $term->taxonomy,
					'meta_key' => $result->meta_key,
					'context' => 'Term meta "' . $result->meta_key . '" for ' . $taxonomy_label . ': ' . $term->name,
					'edit_url' => get_edit_term_link( $result->term_id, $term->taxonomy ),
				);
			}
		}

		return $references;
	}

	/**
	 * Check if attachment is attached to a parent post.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Array with parent post reference if exists.
	 */
	private function check_parent_attachment( $attachment_id ) {
		$references = array();

		$post = get_post( $attachment_id );
		if ( $post && $post->post_parent > 0 ) {
			$parent_post = get_post( $post->post_parent );
			if ( $parent_post && 'trash' !== $parent_post->post_status ) {
				$references[] = array(
					'location' => 'parent_attachment',
					'post_id' => $parent_post->ID,
					'post_title' => $parent_post->post_title,
					'post_type' => $parent_post->post_type,
					'post_status' => $parent_post->post_status,
					'context' => 'Attached to ' . $parent_post->post_type . ': ' . $parent_post->post_title,
					'edit_url' => get_edit_post_link( $parent_post->ID ),
				);
			}
		}

		return $references;
	}
}
