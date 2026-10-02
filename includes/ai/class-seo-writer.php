<?php
/**
 * AI SEO and alt text helper (P12, free, pbs-ai8).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes a meta title and description straight into the SEO plugin the site uses (Yoast SEO,
 * Rank Math, SEOPress, All in One SEO), and alt text for images by looking at them.
 */
final class Seo_Writer {

	/** Longest meta title we ask for. */
	public const TITLE_MAX = 60;

	/** Longest meta description we ask for. */
	public const DESCRIPTION_MAX = 155;

	/** Most images one alt-text run describes. */
	public const MAX_IMAGES = 20;

	/** Largest image sent to the model, in bytes. */
	private const MAX_IMAGE_BYTES = 4194304;

	/**
	 * The SEO plugins that are active, by our key.
	 *
	 * @return array<int, string> `yoast`, `rankmath`, `seopress`, `aioseo`.
	 */
	public static function plugins(): array {
		$out = array();
		if ( defined( 'WPSEO_VERSION' ) ) {
			$out[] = 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath' ) ) {
			$out[] = 'rankmath';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$out[] = 'seopress';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			$out[] = 'aioseo';
		}

		/**
		 * Filters which SEO plugins the AI SEO helper writes the meta title and description into.
		 *
		 * @param array<int, string> $out Active SEO plugins: `yoast`, `rankmath`, `seopress`, `aioseo`.
		 */
		return (array) apply_filters( 'pbsw_ai_seo_plugins', $out );
	}

	/**
	 * Suggest a meta title and description for a post (nothing is saved).
	 *
	 * @param int    $post_id Post.
	 * @param string $keyword Optional focus keyword.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function suggest( int $post_id, string $keyword = '' ) {
		$post = Ai::editable_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( do_blocks( $post->post_content ) ) ) );
		if ( mb_strlen( $text ) < 20 ) {
			return new \WP_Error( 'pbsw_ai_no_content', __( 'The page has too little text to write a description from. Add content first.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$data = Ai::ask(
			'You write search-engine metadata for one web page. Write a meta title of at most ' . self::TITLE_MAX . ' characters and a meta description of at most ' . self::DESCRIPTION_MAX . ' characters, in the language of the page, accurate to its content, with the focus keyword (when given) used naturally once in each. No quotes, no emoji, no claims the page does not make.',
			array(
				'title'   => get_the_title( $post ),
				'keyword' => $keyword,
				'site'    => get_bloginfo( 'name' ),
				'content' => mb_substr( $text, 0, 6000 ),
			),
			array(
				'type'                 => 'object',
				'properties'           => array(
					'title'       => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
				),
				'required'             => array( 'title', 'description' ),
				'additionalProperties' => false,
			),
			array(
				'purpose'     => 'pbs-ai-seo',
				'schema_name' => 'seo_meta',
				'temperature' => 0.4,
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array(
			'id'          => $post_id,
			'title'       => self::clip( sanitize_text_field( (string) ( $data['title'] ?? '' ) ), self::TITLE_MAX ),
			'description' => self::clip( sanitize_text_field( (string) ( $data['description'] ?? '' ) ), self::DESCRIPTION_MAX ),
			'plugins'     => self::plugins(),
		);
	}

	/**
	 * Write a meta title and description into every active SEO plugin's own fields.
	 *
	 * @param int    $post_id     Post.
	 * @param string $title       Meta title.
	 * @param string $description Meta description.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function apply( int $post_id, string $title, string $description ) {
		$post = Ai::editable_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$plugins = self::plugins();
		if ( array() === $plugins ) {
			return new \WP_Error( 'pbsw_no_seo_plugin', __( 'No supported SEO plugin is active (Yoast SEO, Rank Math, SEOPress or All in One SEO), so there is nowhere to save the meta title and description.', 'page-builder-sandwich' ), array( 'status' => 409 ) );
		}
		$title       = sanitize_text_field( $title );
		$description = sanitize_textarea_field( $description );
		$keys        = array(
			'yoast'    => array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ),
			'rankmath' => array( 'rank_math_title', 'rank_math_description' ),
			'seopress' => array( '_seopress_titles_title', '_seopress_titles_desc' ),
		);
		$written     = array();
		foreach ( $plugins as $plugin ) {
			if ( isset( $keys[ $plugin ] ) ) {
				update_post_meta( $post_id, $keys[ $plugin ][0], wp_slash( $title ) );
				update_post_meta( $post_id, $keys[ $plugin ][1], wp_slash( $description ) );
				$written[] = $plugin;
			} elseif ( 'aioseo' === $plugin && self::aioseo( $post_id, $title, $description ) ) {
				$written[] = $plugin;
			}
		}

		return array(
			'id'          => $post_id,
			'title'       => $title,
			'description' => $description,
			'written_to'  => $written,
		);
	}

	/**
	 * All in One SEO keeps its fields in its own table (`aioseo_posts`), read through its Post
	 * model; the post meta it also reads is written as the fallback.
	 *
	 * @param int    $post_id     Post.
	 * @param string $title       Title.
	 * @param string $description Description.
	 * @return bool
	 */
	private static function aioseo( int $post_id, string $title, string $description ): bool {
		$model = '\\AIOSEO\\Plugin\\Common\\Models\\Post';
		if ( class_exists( $model ) && method_exists( $model, 'getPost' ) ) {
			$row = $model::getPost( $post_id );
			if ( is_object( $row ) ) {
				$row->post_id     = $post_id;
				$row->title       = $title;
				$row->description = $description;
				$row->save();
			}
		}
		update_post_meta( $post_id, '_aioseo_title', wp_slash( $title ) );
		update_post_meta( $post_id, '_aioseo_description', wp_slash( $description ) );

		return true;
	}

	/**
	 * Write alt text for images by looking at them: one attachment, or every image on a post that
	 * has none (`only_missing`, the default).
	 *
	 * @param array<string, mixed> $input `attachment_id`, or `post_id` (+ `only_missing`),
	 *                                    optional `language`.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function alt_text( array $input ) {
		$ids = array();
		if ( ! empty( $input['attachment_id'] ) ) {
			$ids[] = (int) $input['attachment_id'];
		} elseif ( ! empty( $input['post_id'] ) ) {
			$post = Ai::editable_post( (int) $input['post_id'] );
			if ( is_wp_error( $post ) ) {
				return $post;
			}
			$ids = self::images_in( $post, ( false !== ( $input['only_missing'] ?? true ) ) );
		}
		if ( array() === $ids ) {
			return array(
				'written' => array(),
				'refused' => array(),
			);
		}
		$language = (string) ( $input['language'] ?? '' );
		$language = '' === $language ? get_bloginfo( 'language' ) : $language;
		$ids      = array_values( array_unique( $ids ) );
		$written  = array();
		$refused  = array();
		foreach ( array_slice( $ids, 0, self::MAX_IMAGES ) as $id ) {
			$alt = self::describe( $id, $language );
			if ( is_wp_error( $alt ) ) {
				$refused[] = array(
					'attachment_id' => $id,
					'reason'        => $alt->get_error_message(),
				);
				if ( 'pbsw_ai_failed' === $alt->get_error_code() ) {
					break; // The provider refused: every next image would fail the same way.
				}
				continue;
			}
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
			$written[] = array(
				'attachment_id' => $id,
				'alt'           => $alt,
			);
		}

		return array(
			'written'   => $written,
			'refused'   => $refused,
			// More images than one run describes: run it again (it skips what now has alt text).
			'remaining' => max( 0, count( $ids ) - self::MAX_IMAGES ),
		);
	}

	/**
	 * Describe one image for its alt text.
	 *
	 * @param int    $id       Attachment.
	 * @param string $language Language tag.
	 * @return string|\WP_Error
	 */
	public static function describe( int $id, string $language ) {
		if ( ! wp_attachment_is_image( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return new \WP_Error( 'pbsw_not_image', __( 'That is not an image you may edit.', 'page-builder-sandwich' ), array( 'status' => 404 ) );
		}
		$file = self::sendable_file( $id );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$data = Ai::ask(
			'You write alt text for an image on a web page: one short sentence (at most 125 characters) saying what the image shows and why it matters, in the language given, without "image of" or "picture of". If the image is purely decorative, answer with an empty string.',
			array(
				array(
					'type' => 'text',
					'text' => (string) wp_json_encode(
						array(
							'language' => $language,
							'title'    => get_the_title( $id ),
						)
					),
				),
				array(
					'type' => 'image',
					'mime' => $file['mime'],
					'data' => $file['data'],
				),
			),
			array(
				'type'                 => 'object',
				'properties'           => array( 'alt' => array( 'type' => 'string' ) ),
				'required'             => array( 'alt' ),
				'additionalProperties' => false,
			),
			array(
				'task'        => 'general',
				'purpose'     => 'pbs-ai-alt-text',
				'schema_name' => 'alt_text',
				'temperature' => 0.2,
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return self::clip( sanitize_text_field( (string) ( $data['alt'] ?? '' ) ), 125 );
	}

	/**
	 * The image's bytes to send: the medium or large size when there is one (smaller, cheaper),
	 * else the original; only PNG, JPEG, GIF and WebP.
	 *
	 * @param int $id Attachment.
	 * @return array{mime: string, data: string}|\WP_Error
	 */
	private static function sendable_file( int $id ) {
		$path = '';
		foreach ( array( 'large', 'medium_large', 'medium' ) as $size ) {
			$meta = image_get_intermediate_size( $id, $size );
			if ( is_array( $meta ) && ! empty( $meta['path'] ) ) {
				$candidate = trailingslashit( (string) wp_get_upload_dir()['basedir'] ) . $meta['path'];
				if ( is_readable( $candidate ) ) {
					$path = $candidate;
					break;
				}
			}
		}
		if ( '' === $path ) {
			$path = (string) get_attached_file( $id );
		}
		$type = wp_check_filetype( $path );
		$mime = (string) ( $type['type'] ?? '' );
		if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ), true ) || ! is_readable( $path ) ) {
			return new \WP_Error( 'pbsw_ai_image_type', __( 'Only PNG, JPEG, GIF and WebP images can be described.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$size = (int) filesize( $path );
		if ( $size <= 0 || $size > self::MAX_IMAGE_BYTES ) {
			return new \WP_Error( 'pbsw_ai_image_size', __( 'The image is too large to send to the AI (4 MB at most).', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}

		return array(
			'mime' => $mime,
			'data' => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a local upload, encoded for the provider's API.
		);
	}

	/**
	 * The media-library images a post shows (by block attribute `id` and by `wp-image-N` class).
	 *
	 * @param \WP_Post $post         Post.
	 * @param bool     $only_missing Only images without alt text.
	 * @return array<int, int>
	 */
	public static function images_in( \WP_Post $post, bool $only_missing ): array {
		$ids  = array();
		$walk = static function ( array $blocks ) use ( &$walk, &$ids ): void {
			foreach ( $blocks as $block ) {
				$attrs = (array) ( $block['attrs'] ?? array() );
				foreach ( array( 'id', 'mediaId', 'imageId' ) as $key ) {
					if ( ! empty( $attrs[ $key ] ) && is_numeric( $attrs[ $key ] ) ) {
						$ids[] = (int) $attrs[ $key ];
					}
				}
				$walk( (array) ( $block['innerBlocks'] ?? array() ) );
			}
		};
		$walk( parse_blocks( $post->post_content ) );
		if ( preg_match_all( '/wp-image-(\d+)/', $post->post_content, $m ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $m[1] ) );
		}
		$out = array();
		foreach ( array_unique( $ids ) as $id ) {
			if ( ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			if ( $only_missing && '' !== trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) {
				continue;
			}
			$out[] = $id;
		}

		return $out;
	}

	/**
	 * Cut a string to a length on a word boundary.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	public static function clip( string $text, int $max ): string {
		$text = trim( $text );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max );
		$space = mb_strrpos( $cut, ' ' );

		return rtrim( false !== $space && $space > $max / 2 ? mb_substr( $cut, 0, $space ) : $cut, ' ,;:-' );
	}
}
