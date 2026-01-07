<?php
/**
 * Media Reference Finder Class
 *
 * Finds all references to media attachments across WordPress content.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Media;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Reference Finder class for locating media usage across the site.
 *
 * @since 1.0.0
 */
class Reference_Finder {

	/**
	 * Find all references to a media attachment.
	 *
 * @since 1.0.0
 *
	 * @param int $attachment_id Attachment ID to search for.
	 * @return array Array of reference locations with context.
	 */
	public function find_references( $attachment_id ) {
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
	public function is_media_used( $attachment_id ) {
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
	public function get_reference_locations( $attachment_id ) {
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
		global $wpdb;

		$references = array();

		// Search by URL/path or filename or wp-image-{ID} class.
		$query = $wpdb->prepare(
			"SELECT ID, post_title, post_type, post_status
			FROM {$wpdb->posts}
			WHERE post_status NOT IN ('trash', 'auto-draft')
			AND (
				post_content LIKE %s
				OR post_content LIKE %s
				OR post_content LIKE %s
			)",
			'%' . $wpdb->esc_like( $attachment_path ) . '%',
			'%' . $wpdb->esc_like( $attachment_filename ) . '%',
			'%wp-image-' . $attachment_id . '%'
		);

		$posts = $wpdb->get_results( $query );

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
		global $wpdb;

		$references = array();

		$query = $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = %s AND meta_value = %d",
			'_thumbnail_id',
			$attachment_id
		);

		$post_ids = $wpdb->get_col( $query );

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
		global $wpdb;

		$references = array();

		$query = $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key NOT LIKE %s
			AND (
				meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value = %d
				OR meta_value LIKE %s
			)",
			'%' . $wpdb->esc_like( '_thumbnail_id' ) . '%',
			'%' . $wpdb->esc_like( $attachment_path ) . '%',
			'%' . $wpdb->esc_like( $attachment_filename ) . '%',
			$attachment_id,
			'%"' . $attachment_id . '"%'
		);

		$results = $wpdb->get_results( $query );

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
		global $wpdb;

		$references = array();

		$query = $wpdb->prepare(
			"SELECT option_name, option_value
			FROM {$wpdb->options}
			WHERE option_name NOT LIKE %s
			AND option_name NOT LIKE %s
			AND (
				option_value LIKE %s
				OR option_value LIKE %s
				OR option_value = %d
				OR option_value LIKE %s
			)",
			'%' . $wpdb->esc_like( '_transient_' ) . '%',
			'%' . $wpdb->esc_like( '_site_transient_' ) . '%',
			'%' . $wpdb->esc_like( $attachment_path ) . '%',
			'%' . $wpdb->esc_like( $attachment_filename ) . '%',
			$attachment_id,
			'%"' . $attachment_id . '"%'
		);

		$results = $wpdb->get_results( $query );

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
		global $wpdb;

		$references = array();

		$query = $wpdb->prepare(
			"SELECT post_id, meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = %s
			AND (
				meta_value LIKE %s
				OR meta_value = %d
			)",
			'_product_image_gallery',
			'%' . $wpdb->esc_like( (string) $attachment_id ) . '%',
			$attachment_id
		);

		$results = $wpdb->get_results( $query );

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
		global $wpdb;

		$references = array();

		$query = $wpdb->prepare(
			"SELECT post_id, meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = %s
			AND (
				meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			'_elementor_data',
			'%"id":' . $attachment_id . '%',
			'%"url"%' . $wpdb->esc_like( (string) $attachment_id ) . '%'
		);

		$results = $wpdb->get_results( $query );

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
		global $wpdb;

		$references = array();

		// Check multiple Beaver Builder meta keys.
		$bb_meta_keys = array( '_fl_builder_data', '_fl_builder_draft' );

		foreach ( $bb_meta_keys as $meta_key ) {
			$query = $wpdb->prepare(
				"SELECT post_id, meta_value
				FROM {$wpdb->postmeta}
				WHERE meta_key = %s
				AND (
					meta_value LIKE %s
					OR meta_value LIKE %s
				)",
				$meta_key,
				'%"photo":' . $attachment_id . '%',
				'%"photo_src"%' . $wpdb->esc_like( (string) $attachment_id ) . '%'
			);

			$results = $wpdb->get_results( $query );

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
		global $wpdb;

		$references = array();

		// ACF stores attachment IDs as plain integers or serialized arrays.
		$query = $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key NOT LIKE %s
			AND (
				meta_value = %d
				OR meta_value LIKE %s
				OR meta_value LIKE %s
			)",
			'%' . $wpdb->esc_like( '_' ) . '%',
			$attachment_id,
			'%i:' . $attachment_id . ';%',
			'%s:%:"' . $attachment_id . '"%'
		);

		$results = $wpdb->get_results( $query );

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
