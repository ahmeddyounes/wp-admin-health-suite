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
	 * @param int    $attachment_id Attachment ID.
	 * @param string $attachment_path Attachment path.
	 * @param string $attachment_filename Attachment filename.
	 * @return array Array of references found in post content.
	 */
	private function search_post_content( $attachment_id, $attachment_path, $attachment_filename ) {
		$references = array();

		$posts_table = $this->connection->get_posts_table();

		// Search by URL/path or filename or wp-image-{ID} class.
		$query = $this->connection->prepare(
			"SELECT ID, post_title, post_type, post_status
			FROM {$posts_table}
			WHERE post_status NOT IN ('trash', 'auto-draft')
			AND (
				post_content LIKE %s
				OR post_content LIKE %s
				OR post_content LIKE %s
			)",
			'%' . $this->connection->esc_like( $attachment_path ) . '%',
			'%' . $this->connection->esc_like( $attachment_filename ) . '%',
			'%wp-image-' . $attachment_id . '%'
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
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found in WooCommerce galleries.
	 */
	private function search_woocommerce_galleries( $attachment_id ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		$query = $this->connection->prepare(
			"SELECT post_id, meta_value
			FROM {$postmeta_table}
			WHERE meta_key = %s
			AND (
				meta_value LIKE %s
				OR meta_value = %d
			)",
			'_product_image_gallery',
			'%' . $this->connection->esc_like( (string) $attachment_id ) . '%',
			$attachment_id
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		foreach ( $results as $result ) {
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
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found in Elementor data.
	 */
	private function search_elementor( $attachment_id ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		// Build Elementor ID patterns safely.
		$id_pattern  = '%' . $this->connection->esc_like( '"id":' . (string) $attachment_id ) . '%';
		$url_pattern = '%' . $this->connection->esc_like( '"url"' ) . '%' . $this->connection->esc_like( (string) $attachment_id ) . '%';

		$query = $this->connection->prepare(
			"SELECT post_id, meta_value
			FROM {$postmeta_table}
			WHERE meta_key = %s
			AND (
				meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			'_elementor_data',
			$id_pattern,
			$url_pattern
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		foreach ( $results as $result ) {
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
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found in Beaver Builder data.
	 */
	private function search_beaver_builder( $attachment_id ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		// Check multiple Beaver Builder meta keys.
		$bb_meta_keys = array( '_fl_builder_data', '_fl_builder_draft' );

		// Build Beaver Builder patterns safely.
		$photo_pattern    = '%' . $this->connection->esc_like( '"photo":' . (string) $attachment_id ) . '%';
		$photo_src_pattern = '%' . $this->connection->esc_like( '"photo_src"' ) . '%' . $this->connection->esc_like( (string) $attachment_id ) . '%';

		foreach ( $bb_meta_keys as $meta_key ) {
			$query = $this->connection->prepare(
				"SELECT post_id, meta_value
				FROM {$postmeta_table}
				WHERE meta_key = %s
				AND (
					meta_value LIKE %s
					OR meta_value LIKE %s
				)",
				$meta_key,
				$photo_pattern,
				$photo_src_pattern
			);

			if ( null === $query ) {
				continue;
			}

			$results = $this->connection->get_results( $query );

			foreach ( $results as $result ) {
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
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of references found in ACF fields.
	 */
	private function search_acf_fields( $attachment_id ) {
		$references = array();

		$postmeta_table = $this->connection->get_postmeta_table();

		// ACF stores attachment IDs as plain integers or serialized arrays.
		// Build serialized patterns safely.
		$serialized_int_pattern = '%' . $this->connection->esc_like( 'i:' . (string) $attachment_id . ';' ) . '%';
		$serialized_str_pattern = '%' . $this->connection->esc_like( ':"' . (string) $attachment_id . '"' ) . '%';

		$query = $this->connection->prepare(
			"SELECT post_id, meta_key, meta_value
			FROM {$postmeta_table}
			WHERE meta_key NOT LIKE %s
			AND (
				meta_value = %d
				OR meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			$this->connection->esc_like( '_' ) . '%',
			$attachment_id,
			$serialized_int_pattern,
			$serialized_str_pattern
		);

		if ( null === $query ) {
			return $references;
		}

		$results = $this->connection->get_results( $query );

		foreach ( $results as $result ) {
			// Check if this might be an ACF field.
			$field_key_meta = get_post_meta( $result->post_id, '_' . $result->meta_key, true );
			if ( strpos( $field_key_meta, 'field_' ) === 0 ) {
				$post = get_post( $result->post_id );
				if ( $post ) {
					$references[] = array(
						'location' => 'acf_field',
						'post_id' => $result->post_id,
						'post_title' => $post->post_title,
						'post_type' => $post->post_type,
						'post_status' => $post->post_status,
						'meta_key' => $result->meta_key,
						'context' => 'ACF field "' . $result->meta_key . '" in ' . $post->post_type . ': ' . $post->post_title,
						'edit_url' => get_edit_post_link( $result->post_id ),
					);
				}
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
