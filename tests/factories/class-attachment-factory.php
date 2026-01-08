<?php
/**
 * Custom Attachment Factory for tests
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Tests\Factories;

use WP_UnitTest_Factory_For_Attachment;

/**
 * Extended Attachment Factory with custom methods for testing
 */
class Attachment_Factory extends WP_UnitTest_Factory_For_Attachment {

	/**
	 * Create an image attachment with specific dimensions
	 *
	 * @param int   $width Image width
	 * @param int   $height Image height
	 * @param array $args Attachment arguments
	 * @return int Attachment ID
	 */
	public function create_image( $width = 800, $height = 600, $args = array() ) {
		$defaults = array(
			'post_mime_type' => 'image/jpeg',
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
		);

		$args = wp_parse_args( $args, $defaults );
		$attachment_id = $this->create( $args );

		// Add metadata
		$metadata = array(
			'width'  => $width,
			'height' => $height,
			'file'   => 'test-image-' . $attachment_id . '.jpg',
			'sizes'  => array(),
		);

		update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );

		return $attachment_id;
	}

	/**
	 * Create an attachment with alt text
	 *
	 * @param string $alt_text Alt text for the image
	 * @param array  $args Attachment arguments
	 * @return int Attachment ID
	 */
	public function create_with_alt_text( $alt_text, $args = array() ) {
		$attachment_id = $this->create_image( 800, 600, $args );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		return $attachment_id;
	}

	/**
	 * Create an orphaned attachment (no parent post)
	 *
	 * @param array $args Attachment arguments
	 * @return int Attachment ID
	 */
	public function create_orphaned( $args = array() ) {
		$args['post_parent'] = 0;
		return $this->create_image( 800, 600, $args );
	}

	/**
	 * Create multiple attachments with the same parent
	 *
	 * @param int   $parent_id Parent post ID
	 * @param int   $count Number of attachments to create
	 * @param array $args Attachment arguments
	 * @return array Array of attachment IDs
	 */
	public function create_many_for_post( $parent_id, $count, $args = array() ) {
		$attachment_ids = array();
		$args['post_parent'] = $parent_id;

		for ( $i = 0; $i < $count; $i++ ) {
			$attachment_ids[] = $this->create_image( 800, 600, $args );
		}

		return $attachment_ids;
	}

	/**
	 * Create a large file attachment for testing
	 *
	 * @param int   $filesize Simulated file size in bytes
	 * @param array $args Attachment arguments
	 * @return int Attachment ID
	 */
	public function create_large_file( $filesize = 5242880, $args = array() ) {
		$attachment_id = $this->create_image( 3000, 2000, $args );

		// Store filesize in metadata
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$metadata['filesize'] = $filesize;
		update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );

		return $attachment_id;
	}
}
