<?php
/**
 * WPML/Polylang Multilingual Integration Class
 *
 * Provides WPML and Polylang-specific media reference detection.
 * Ensures translated media is not flagged as unused.
 * Handles duplicate translations in media scanning.
 * Respects language context in admin pages.
 * Only loads when WPML or Polylang is active.
 *
 * @package WPAdminHealth
 */

namespace WPAdminHealth\Integrations;

use WPAdminHealth\Contracts\ConnectionInterface;
use WPAdminHealth\Contracts\CacheInterface;
use WPAdminHealth\Contracts\MediaAwareIntegrationInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Multilingual Integration class for WPML/Polylang-specific optimizations.
 *
 * @since 1.0.0
 */
class Multilingual extends AbstractIntegration implements MediaAwareIntegrationInterface {

	/**
	 * Minimum supported WPML version.
	 *
	 * @var string
	 */
	const MIN_WPML_VERSION = '4.0.0';

	/**
	 * Minimum supported Polylang version.
	 *
	 * @var string
	 */
	const MIN_POLYLANG_VERSION = '2.0.0';

	/**
	 * Current active multilingual plugin.
	 *
	 * @var string 'wpml', 'polylang', or empty if none active
	 */
	private string $active_plugin = '';

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param ConnectionInterface|null $connection Optional database connection.
	 * @param CacheInterface|null      $cache      Optional cache instance.
	 */
	public function __construct(
		?ConnectionInterface $connection = null,
		?CacheInterface $cache = null
	) {
		parent::__construct( $connection, $cache );

		// Determine which plugin is active.
		if ( self::is_wpml_active() ) {
			$this->active_plugin = 'wpml';
		} elseif ( self::is_polylang_active() ) {
			$this->active_plugin = 'polylang';
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'multilingual';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		if ( 'wpml' === $this->active_plugin ) {
			return 'WPML';
		} elseif ( 'polylang' === $this->active_plugin ) {
			return 'Polylang';
		}

		return 'Multilingual (WPML/Polylang)';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return self::is_wpml_active() || self::is_polylang_active();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_min_version(): string {
		if ( 'wpml' === $this->active_plugin ) {
			return self::MIN_WPML_VERSION;
		} elseif ( 'polylang' === $this->active_plugin ) {
			return self::MIN_POLYLANG_VERSION;
		}

		// Default to WPML version as it's more commonly used.
		return self::MIN_WPML_VERSION;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_current_version(): ?string {
		if ( ! $this->is_available() ) {
			return null;
		}

		if ( 'wpml' === $this->active_plugin && defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return ICL_SITEPRESS_VERSION;
		}

		if ( 'polylang' === $this->active_plugin && defined( 'POLYLANG_VERSION' ) ) {
			return POLYLANG_VERSION;
		}

		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_capabilities(): array {
		return array(
			'media_detection',
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function register_hooks(): void {
		// Hook into media scanner to detect multilingual media usage.
		$this->add_filter( 'wpha_media_is_attachment_used', array( $this, 'check_translated_media_usage' ), 10, 2 );

		// Hook into media scanner to include translated posts.
		$this->add_filter( 'wpha_media_scan_post_statuses', array( $this, 'include_all_language_posts' ), 10, 1 );

		// Hook into reference finder to detect translations.
		$this->add_filter( 'wpha_media_reference_search_posts', array( $this, 'get_all_translation_posts' ), 10, 1 );
	}

	/**
	 * Check if WPML is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if WPML is active.
	 */
	public static function is_wpml_active(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' );
	}

	/**
	 * Check if Polylang is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if Polylang is active.
	 */
	public static function is_polylang_active(): bool {
		return defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_languages_list' );
	}

	/**
	 * Check if any multilingual plugin is active.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use is_available() instead.
	 *
	 * @return bool True if WPML or Polylang is active.
	 */
	public static function is_active(): bool {
		return self::is_wpml_active() || self::is_polylang_active();
	}

	/**
	 * Get active multilingual plugin identifier.
	 *
	 * @since 1.1.0
	 *
	 * @return string 'wpml', 'polylang', or empty string if none active.
	 */
	public function get_active_plugin(): string {
		return $this->active_plugin;
	}

	/**
	 * Get all available languages.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string> Array of language codes.
	 */
	public function get_languages(): array {
		if ( 'wpml' === $this->active_plugin ) {
			return $this->get_wpml_languages();
		} elseif ( 'polylang' === $this->active_plugin ) {
			return $this->get_polylang_languages();
		}

		return array();
	}

	/**
	 * Get WPML languages.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string> Array of language codes.
	 */
	private function get_wpml_languages(): array {
		if ( ! function_exists( 'icl_get_languages' ) ) {
			global $sitepress;
			if ( $sitepress && method_exists( $sitepress, 'get_active_languages' ) ) {
				$languages = $sitepress->get_active_languages();
				return array_keys( $languages );
			}
			return array();
		}

		$languages = icl_get_languages( 'skip_missing=0' );
		return array_keys( $languages );
	}

	/**
	 * Get Polylang languages.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string> Array of language codes.
	 */
	private function get_polylang_languages(): array {
		if ( ! function_exists( 'pll_languages_list' ) ) {
			return array();
		}

		return pll_languages_list();
	}

	/**
	 * Get translated versions of a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return array<int> Array of translated post IDs (including original).
	 */
	public function get_translations( int $post_id ): array {
		if ( 'wpml' === $this->active_plugin ) {
			return $this->get_wpml_translations( $post_id );
		} elseif ( 'polylang' === $this->active_plugin ) {
			return $this->get_polylang_translations( $post_id );
		}

		return array( $post_id );
	}

	/**
	 * Get WPML translations of a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return array<int> Array of translated post IDs.
	 */
	private function get_wpml_translations( int $post_id ): array {
		// Get post type.
		$post_type = get_post_type( $post_id );

		if ( ! $post_type ) {
			return array( $post_id );
		}

		// Get element type for WPML (e.g., 'post_page', 'post_post', 'post_attachment').
		$element_type = 'post_' . $post_type;
		$prefix       = $this->connection->get_prefix();

		// Query WPML translations table.
		$translations = $this->connection->get_col(
			$this->connection->prepare(
				"SELECT element_id
				FROM {$prefix}icl_translations
				WHERE trid = (
					SELECT trid
					FROM {$prefix}icl_translations
					WHERE element_id = %d
					AND element_type = %s
				)
				AND element_type = %s",
				$post_id,
				$element_type,
				$element_type
			)
		);

		if ( empty( $translations ) ) {
			return array( $post_id );
		}

		return array_map( 'absint', $translations );
	}

	/**
	 * Get Polylang translations of a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return array<int> Array of translated post IDs.
	 */
	private function get_polylang_translations( int $post_id ): array {
		if ( ! function_exists( 'pll_get_post_translations' ) ) {
			return array( $post_id );
		}

		$translations = pll_get_post_translations( $post_id );

		if ( empty( $translations ) ) {
			return array( $post_id );
		}

		return array_values( array_map( 'absint', $translations ) );
	}

	/**
	 * Check if an attachment is used in translated content.
	 *
	 * This prevents translated media from being flagged as unused.
	 * Checks all language versions of posts for media usage.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $is_used       Whether the attachment is used.
	 * @param int  $attachment_id The attachment ID.
	 * @return bool True if used in any translated content.
	 */
	public function check_translated_media_usage( bool $is_used, int $attachment_id ): bool {
		if ( $is_used ) {
			return $is_used;
		}

		$prefix = $this->connection->get_prefix();

		// Get all translations of this attachment.
		$translated_attachments = $this->get_translations( $attachment_id );

		if ( count( $translated_attachments ) <= 1 ) {
			return $is_used;
		}

		// Check if any translation is used as featured image.
		$placeholders = implode( ',', array_fill( 0, count( $translated_attachments ), '%d' ) );

		$featured_check = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(*)
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE pm.meta_key = '_thumbnail_id'
				AND pm.meta_value IN ($placeholders)
				AND p.post_status NOT IN ('trash', 'auto-draft')",
				...$translated_attachments
			)
		);

		if ( $featured_check > 0 ) {
			return true;
		}

		// Check if any translation is used in post content.
		foreach ( $translated_attachments as $translation_id ) {
			// Cache the URL to avoid duplicate database calls.
			$attachment_url = wp_get_attachment_url( $translation_id );
			if ( ! $attachment_url ) {
				continue;
			}

			$posts_using_image = $this->connection->get_var(
				$this->connection->prepare(
					"SELECT COUNT(*)
					FROM {$prefix}posts
					WHERE post_status NOT IN ('trash', 'auto-draft')
					AND (
						post_content LIKE %s
						OR post_content LIKE %s
						OR post_content LIKE %s
					)",
					'%wp-image-' . $translation_id . '%',
					'%wp-content/uploads/%' . $this->connection->esc_like( basename( $attachment_url ) ) . '%',
					'%' . $this->connection->esc_like( $attachment_url ) . '%'
				)
			);

			if ( $posts_using_image > 0 ) {
				return true;
			}
		}

		// Check if any translation is used in galleries or meta.
		$gallery_check = $this->connection->get_var(
			$this->connection->prepare(
				"SELECT COUNT(*)
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE pm.meta_key = '_wp_attached_file'
				AND pm.post_id IN ($placeholders)
				AND p.post_status NOT IN ('trash', 'auto-draft')",
				...$translated_attachments
			)
		);

		if ( $gallery_check > 0 ) {
			return true;
		}

		return $is_used;
	}

	/**
	 * Include all language posts in media scan.
	 *
	 * Ensures the scanner doesn't miss posts in non-default languages.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $post_statuses Array of post statuses to scan.
	 * @return array<string> Modified array of post statuses.
	 */
	public function include_all_language_posts( array $post_statuses ): array {
		// This filter is primarily informational.
		// The actual language filtering happens in get_all_translation_posts().
		return $post_statuses;
	}

	/**
	 * Get all posts including translations for reference search.
	 *
	 * @since 1.0.0
	 *
	 * @param array<\WP_Post> $posts Array of posts to search.
	 * @return array<\WP_Post> Array of posts including all translations.
	 */
	public function get_all_translation_posts( array $posts ): array {
		if ( empty( $posts ) ) {
			return $posts;
		}

		$all_posts = array();

		foreach ( $posts as $post ) {
			$all_posts[] = $post;

			// Get translations of this post.
			$translations = $this->get_translations( $post->ID );

			// Add translated posts that aren't already in the list.
			foreach ( $translations as $translation_id ) {
				if ( $translation_id !== $post->ID ) {
					$translated_post = get_post( $translation_id );
					if ( $translated_post ) {
						$all_posts[] = $translated_post;
					}
				}
			}
		}

		return $all_posts;
	}

	/**
	 * Get current admin language context.
	 *
	 * Returns the language code for the current admin context.
	 *
	 * @since 1.0.0
	 *
	 * @return string Language code or empty string.
	 */
	public function get_current_language(): string {
		if ( 'wpml' === $this->active_plugin ) {
			return $this->get_wpml_current_language();
		} elseif ( 'polylang' === $this->active_plugin ) {
			return $this->get_polylang_current_language();
		}

		return '';
	}

	/**
	 * Get current WPML language.
	 *
	 * @since 1.0.0
	 *
	 * @return string Language code.
	 */
	private function get_wpml_current_language(): string {
		if ( function_exists( 'icl_get_current_language' ) ) {
			return icl_get_current_language();
		}

		global $sitepress;
		if ( $sitepress && method_exists( $sitepress, 'get_current_language' ) ) {
			return $sitepress->get_current_language();
		}

		return '';
	}

	/**
	 * Get current Polylang language.
	 *
	 * @since 1.0.0
	 *
	 * @return string Language code.
	 */
	private function get_polylang_current_language(): string {
		if ( function_exists( 'pll_current_language' ) ) {
			return pll_current_language();
		}

		return '';
	}

	/**
	 * Count posts per language.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int> Array of post counts by language.
	 */
	public function get_posts_per_language(): array {
		$languages = $this->get_languages();
		$counts    = array();

		if ( 'wpml' === $this->active_plugin ) {
			$prefix = $this->connection->get_prefix();

			foreach ( $languages as $lang ) {
				$count = $this->connection->get_var(
					$this->connection->prepare(
						"SELECT COUNT(DISTINCT element_id)
						FROM {$prefix}icl_translations
						WHERE language_code = %s
						AND element_type LIKE 'post_%'",
						$lang
					)
				);

				$counts[ $lang ] = absint( $count );
			}
		} elseif ( 'polylang' === $this->active_plugin && function_exists( 'pll_count_posts' ) ) {
			foreach ( $languages as $lang ) {
				$count = pll_count_posts( $lang );
				if ( is_object( $count ) ) {
					$counts[ $lang ] = isset( $count->publish ) ? absint( $count->publish ) : 0;
				} else {
					$counts[ $lang ] = 0;
				}
			}
		}

		return $counts;
	}

	/**
	 * Get duplicate translated media.
	 *
	 * Finds media items that have been duplicated across languages
	 * when they could share the same attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Maximum number of duplicates to return.
	 * @return array<array{filename: string, attachment_ids: array<int>, languages: array<int, string>}> Array of duplicate media groups.
	 */
	public function get_duplicate_translated_media( int $limit = 100 ): array {
		$prefix     = $this->connection->get_prefix();
		$duplicates = array();

		if ( 'wpml' === $this->active_plugin ) {
			// Find attachments with the same filename across languages.
			$results = $this->connection->get_results(
				$this->connection->prepare(
					"SELECT pm.meta_value as filename, GROUP_CONCAT(p.ID) as attachment_ids
					FROM {$prefix}postmeta pm
					INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
					WHERE pm.meta_key = '_wp_attached_file'
					AND p.post_type = 'attachment'
					GROUP BY pm.meta_value
					HAVING COUNT(*) > 1
					LIMIT %d",
					$limit
				)
			);

			foreach ( $results as $result ) {
				$attachment_ids = array_map( 'absint', explode( ',', $result->attachment_ids ) );

				// Verify these are actually translations.
				$languages = array();
				foreach ( $attachment_ids as $attachment_id ) {
					$lang = $this->get_attachment_language( $attachment_id );
					if ( $lang ) {
						$languages[ $attachment_id ] = $lang;
					}
				}

				// Only include if we have multiple languages.
				if ( count( array_unique( $languages ) ) > 1 ) {
					$duplicates[] = array(
						'filename'       => $result->filename,
						'attachment_ids' => $attachment_ids,
						'languages'      => $languages,
					);
				}
			}
		} elseif ( 'polylang' === $this->active_plugin ) {
			// Similar logic for Polylang.
			$results = $this->connection->get_results(
				$this->connection->prepare(
					"SELECT pm.meta_value as filename, GROUP_CONCAT(p.ID) as attachment_ids
					FROM {$prefix}postmeta pm
					INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
					WHERE pm.meta_key = '_wp_attached_file'
					AND p.post_type = 'attachment'
					GROUP BY pm.meta_value
					HAVING COUNT(*) > 1
					LIMIT %d",
					$limit
				)
			);

			foreach ( $results as $result ) {
				$attachment_ids = array_map( 'absint', explode( ',', $result->attachment_ids ) );

				// Check if these have different language assignments.
				$languages = array();
				foreach ( $attachment_ids as $attachment_id ) {
					$lang = $this->get_attachment_language( $attachment_id );
					if ( $lang ) {
						$languages[ $attachment_id ] = $lang;
					}
				}

				// Only include if we have multiple languages.
				if ( count( array_unique( $languages ) ) > 1 ) {
					$duplicates[] = array(
						'filename'       => $result->filename,
						'attachment_ids' => $attachment_ids,
						'languages'      => $languages,
					);
				}
			}
		}

		return $duplicates;
	}

	/**
	 * Get attachment language.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Language code or empty string.
	 */
	private function get_attachment_language( int $attachment_id ): string {
		if ( 'wpml' === $this->active_plugin ) {
			$prefix = $this->connection->get_prefix();

			$lang = $this->connection->get_var(
				$this->connection->prepare(
					"SELECT language_code
					FROM {$prefix}icl_translations
					WHERE element_id = %d
					AND element_type = 'post_attachment'",
					$attachment_id
				)
			);

			// Explicit type cast to ensure string return type.
			return $lang ? (string) $lang : '';
		} elseif ( 'polylang' === $this->active_plugin && function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $attachment_id );
			// Explicit type cast to ensure string return type.
			return $lang ? (string) $lang : '';
		}

		return '';
	}

	/**
	 * Get multilingual plugin statistics.
	 *
	 * @since 1.0.0
	 *
	 * @return array{active_plugin: string, languages: array<string>, posts_per_language: array<string, int>, current_language: string} Array of statistics.
	 */
	public function get_statistics(): array {
		return array(
			'active_plugin'      => $this->active_plugin,
			'languages'          => $this->get_languages(),
			'posts_per_language' => $this->get_posts_per_language(),
			'current_language'   => $this->get_current_language(),
		);
	}

	/**
	 * Get multilingual plugin version.
	 *
	 * @since 1.0.0
	 * @deprecated 1.1.0 Use get_current_version() instead.
	 *
	 * @return string Plugin version or empty string.
	 */
	public function get_version(): string {
		return $this->get_current_version() ?? '';
	}

	/**
	 * Filter media scan results to respect language context.
	 *
	 * When in a specific language context in admin, prioritize that language.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int> $attachments Array of attachment IDs.
	 * @param string     $language    Language code to filter by (optional).
	 * @return array<int> Filtered array of attachment IDs.
	 */
	public function filter_attachments_by_language( array $attachments, string $language = '' ): array {
		if ( empty( $language ) ) {
			$language = $this->get_current_language();
		}

		if ( empty( $language ) || empty( $attachments ) ) {
			return $attachments;
		}

		$filtered = array();

		foreach ( $attachments as $attachment_id ) {
			$attachment_lang = $this->get_attachment_language( $attachment_id );

			// Include if no language set or matches current language.
			if ( empty( $attachment_lang ) || $attachment_lang === $language ) {
				$filtered[] = $attachment_id;
			}
		}

		return $filtered;
	}

	/**
	 * Check if an attachment is used via multilingual plugin connections.
	 *
	 * Checks if this attachment or any of its translations is in use.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return bool True if the attachment is used via translations.
	 */
	public function is_attachment_used( int $attachment_id ): bool {
		return $this->check_translated_media_usage( false, $attachment_id );
	}

	/**
	 * Get all attachment IDs that have translations.
	 *
	 * Returns attachments that are part of translation groups.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int> Array of attachment IDs.
	 */
	public function get_used_attachments(): array {
		$prefix = $this->connection->get_prefix();

		if ( 'wpml' === $this->active_plugin ) {
			// Get all attachments that are part of translation groups.
			$results = $this->connection->get_col(
				"SELECT DISTINCT element_id
				FROM {$prefix}icl_translations
				WHERE element_type = 'post_attachment'
				AND trid IN (
					SELECT trid
					FROM {$prefix}icl_translations
					WHERE element_type = 'post_attachment'
					GROUP BY trid
					HAVING COUNT(*) > 1
				)"
			);

			return array_map( 'absint', $results );
		} elseif ( 'polylang' === $this->active_plugin ) {
			// Get all attachments that have translations in Polylang.
			// Polylang uses term relationships for language associations.
			$results = $this->connection->get_col(
				"SELECT DISTINCT tr.object_id
				FROM {$prefix}term_relationships tr
				INNER JOIN {$prefix}posts p ON tr.object_id = p.ID
				INNER JOIN {$prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE p.post_type = 'attachment'
				AND tt.taxonomy = 'language'
				AND p.post_status NOT IN ('trash', 'auto-draft')"
			);

			return array_map( 'absint', $results );
		}

		return array();
	}

	/**
	 * Get usage locations for a specific attachment in translations.
	 *
	 * Returns information about the translation group this attachment belongs to.
	 *
	 * @since 1.1.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return array<array{post_id: int, post_title: string, context: string}> Array of usage locations.
	 */
	public function get_attachment_usage( int $attachment_id ): array {
		$usages = array();

		// Get all translations of this attachment.
		$translations = $this->get_translations( $attachment_id );

		if ( count( $translations ) <= 1 ) {
			return $usages;
		}

		foreach ( $translations as $translation_id ) {
			$post = get_post( $translation_id );

			if ( ! $post ) {
				continue;
			}

			$language = $this->get_attachment_language( $translation_id );
			$context  = $translation_id === $attachment_id
				? sprintf( 'Original attachment (%s)', $language ?: 'no language' )
				: sprintf( 'Translated version (%s)', $language ?: 'no language' );

			$usages[] = array(
				'post_id'    => absint( $translation_id ),
				'post_title' => $post->post_title,
				'context'    => $context,
			);
		}

		// Also check if the attachment is used in translated posts.
		$prefix = $this->connection->get_prefix();

		// Check featured images.
		$featured_usages = $this->connection->get_results(
			$this->connection->prepare(
				"SELECT p.ID as post_id, p.post_title
				FROM {$prefix}postmeta pm
				INNER JOIN {$prefix}posts p ON pm.post_id = p.ID
				WHERE pm.meta_key = '_thumbnail_id'
				AND pm.meta_value = %d
				AND p.post_status NOT IN ('trash', 'auto-draft')",
				$attachment_id
			),
			'OBJECT'
		);

		foreach ( $featured_usages as $usage ) {
			$post_lang  = $this->get_post_language( absint( $usage->post_id ) );
			$usages[]   = array(
				'post_id'    => absint( $usage->post_id ),
				'post_title' => $usage->post_title,
				'context'    => sprintf( 'Featured image in translated post (%s)', $post_lang ?: 'default' ),
			);
		}

		return $usages;
	}

	/**
	 * Get the language of a post.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return string Language code or empty string.
	 */
	private function get_post_language( int $post_id ): string {
		$post_type = get_post_type( $post_id );

		if ( ! $post_type ) {
			return '';
		}

		if ( 'wpml' === $this->active_plugin ) {
			$prefix       = $this->connection->get_prefix();
			$element_type = 'post_' . $post_type;

			$lang = $this->connection->get_var(
				$this->connection->prepare(
					"SELECT language_code
					FROM {$prefix}icl_translations
					WHERE element_id = %d
					AND element_type = %s",
					$post_id,
					$element_type
				)
			);

			return $lang ? $lang : '';
		} elseif ( 'polylang' === $this->active_plugin && function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post_id );
			return $lang ? $lang : '';
		}

		return '';
	}
}
