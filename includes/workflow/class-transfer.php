<?php
/**
 * Import and export (pbs-g5, Free): move templates, kits, patterns, the site's design and the
 * plugin's settings between sites as one file.
 *
 * The file is plain JSON with a content checksum. An import never trusts anything in it: IDs are
 * ignored (items are matched by type + slug), block markup is re-sanitised for a user without
 * `unfiltered_html`, options are limited to the plugin's own allow-list, and media is either left
 * pointing where it was or, only when the user asks, downloaded into this site's library.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Workflow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and applies transfer packages.
 */
final class Transfer {

	/** The package format name. */
	public const FORMAT = 'pbs-transfer';

	/** The package format version this code writes and the newest it reads. */
	public const VERSION = 1;

	/** Import modes for an item that already exists here (same type + slug). */
	public const MODES = array( 'skip', 'replace', 'duplicate' );

	/**
	 * The post types a package may carry.
	 *
	 * Block templates, template parts, patterns ("My patterns") and the Site Editor's user styles.
	 * Other modules add their own (kits, theme-builder templates) through the filter.
	 *
	 * @return array<int, string>
	 */
	public static function post_types(): array {
		$types = array( 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles' );

		/**
		 * Filters the post types that import and export can move between sites.
		 *
		 * @param array<int, string> $types Post type names.
		 */
		$types = (array) apply_filters( 'pbsw_transfer_post_types', $types );

		return array_values( array_filter( array_map( 'strval', $types ), 'post_type_exists' ) );
	}

	/**
	 * The options a package may carry: the plugin's own, never anything else on the site.
	 *
	 * @return array<int, string>
	 */
	public static function options(): array {
		$options = array(
			\ZinnDigital\PBS\Settings::OPTION,
			'pbsw_breakpoints',
			'pbsw_classes',
			'pbsw_css_vars',
			'pbsw_tokens',
			'pbsw_brand_kit',
			'pbsw_roles',
			'pbsw_maintenance',
		);

		/**
		 * Filters the options import and export can move between sites. Only `pbsw_` options are
		 * accepted; anything else is dropped so a package can never write a core or third-party
		 * option.
		 *
		 * @param array<int, string> $options Option names.
		 */
		$options = (array) apply_filters( 'pbsw_transfer_options', $options );

		return array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $options ),
					static fn( string $name ): bool => str_starts_with( $name, 'pbsw_' )
				)
			)
		);
	}

	/**
	 * What this site can export, for the picker.
	 *
	 * @return array<int, array{id:int, type:string, typeLabel:string, slug:string, title:string}>
	 */
	public static function exportable(): array {
		$items = array();
		foreach ( self::post_types() as $type ) {
			$query  = new \WP_Query(
				array(
					'post_type'              => $type,
					'post_status'            => array( 'publish', 'draft', 'private' ),
					'posts_per_page'         => -1,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'suppress_filters'       => false,
				)
			);
			$object = get_post_type_object( $type );
			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof \WP_Post || ! self::can_export_post( $post ) ) {
					continue;
				}
				$items[] = array(
					'id'        => $post->ID,
					'type'      => $type,
					'typeLabel' => $object ? (string) $object->labels->singular_name : $type,
					'slug'      => $post->post_name,
					'title'     => '' !== $post->post_title ? $post->post_title : $post->post_name,
				);
			}
		}

		return $items;
	}

	/**
	 * Build a package.
	 *
	 * @param array<int, int> $ids              Post IDs to include (anything not exportable is ignored).
	 * @param bool            $include_settings Whether to include the plugin's settings.
	 * @return array<string, mixed>
	 */
	public static function export( array $ids, bool $include_settings ): array {
		$types = self::post_types();
		$items = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, $types, true ) || ! self::can_export_post( $post ) ) {
				continue;
			}
			$items[] = self::item( $post );
		}

		$settings = array();
		if ( $include_settings ) {
			foreach ( self::options() as $name ) {
				$value = get_option( $name, null );
				if ( null !== $value ) {
					$settings[ $name ] = $value;
				}
			}
		}

		$package = array(
			'format'   => self::FORMAT,
			'version'  => self::VERSION,
			'created'  => gmdate( 'c' ),
			'source'   => home_url( '/' ),
			'uploads'  => (string) wp_get_upload_dir()['baseurl'],
			'items'    => $items,
			'settings' => (object) $settings,
		);

		$package['checksum'] = self::checksum( $package );

		return $package;
	}

	/**
	 * One post as a package item.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private static function item( \WP_Post $post ): array {
		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$names = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'slugs' ) );
			if ( is_array( $names ) && $names ) {
				$terms[ $taxonomy ] = array_values( array_map( 'strval', $names ) );
			}
		}

		$meta = array();
		foreach ( self::portable_meta_keys() as $key ) {
			if ( metadata_exists( 'post', $post->ID, $key ) ) {
				$meta[ $key ] = get_post_meta( $post->ID, $key, true );
			}
		}

		return array(
			'type'    => $post->post_type,
			'slug'    => $post->post_name,
			'title'   => $post->post_title,
			'excerpt' => $post->post_excerpt,
			'status'  => $post->post_status,
			'content' => $post->post_content,
			'terms'   => (object) $terms,
			'meta'    => (object) $meta,
		);
	}

	/**
	 * Post meta that travels with an item: the plugin's own per-page data and core's pattern sync
	 * status. Never private third-party meta.
	 *
	 * @return array<int, string>
	 */
	private static function portable_meta_keys(): array {
		$keys = array( 'wp_pattern_sync_status', '_pbsw_page_custom_css', '_pbsw_display_conditions', '_pbsw_template_location' );

		/**
		 * Filters the post meta keys import and export carry with each item.
		 *
		 * @param array<int, string> $keys Meta keys.
		 */
		return array_values( array_map( 'strval', (array) apply_filters( 'pbsw_transfer_meta_keys', $keys ) ) );
	}

	/**
	 * Only posts the current user could edit are exported: a package must not become a way to
	 * read a private template.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	private static function can_export_post( \WP_Post $post ): bool {
		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Content checksum over everything except the checksum itself.
	 *
	 * The checksum catches a truncated or hand-edited file; it is not a signature, and the import
	 * never relies on it for security (everything is re-validated regardless).
	 *
	 * @param array<string, mixed> $package Package.
	 * @return string
	 */
	public static function checksum( array $package ): string {
		unset( $package['checksum'] );

		return hash( 'sha256', (string) wp_json_encode( self::canonical( $package ) ) );
	}

	/**
	 * Key-sorted copy, so the checksum does not depend on key order (a JSON round trip through a
	 * browser or another language keeps values but may reorder object keys). Objects become arrays,
	 * so an empty object written as `{}` and read back as an empty PHP array hash the same.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function canonical( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_is_list( $value ) ) {
			return array_map( array( self::class, 'canonical' ), $value );
		}
		ksort( $value, SORT_STRING );

		return array_map( array( self::class, 'canonical' ), $value );
	}

	/**
	 * Validate a decoded package.
	 *
	 * @param mixed $package Decoded JSON.
	 * @return true|\WP_Error
	 */
	public static function validate( $package ) {
		if ( ! is_array( $package ) || self::FORMAT !== ( $package['format'] ?? null ) ) {
			return new \WP_Error( 'pbsw_transfer_format', __( 'This is not an export file from this plugin.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$version = (int) ( $package['version'] ?? 0 );
		if ( $version < 1 || $version > self::VERSION ) {
			return new \WP_Error( 'pbsw_transfer_version', __( 'This export file was made by a newer version of the plugin. Update the plugin on this site, then import it again.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		if ( ! isset( $package['items'] ) || ! is_array( $package['items'] ) || ! array_is_list( $package['items'] ) ) {
			return new \WP_Error( 'pbsw_transfer_items', __( 'The export file is damaged: its list of items is missing.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		if ( ! hash_equals( self::checksum( $package ), (string) ( $package['checksum'] ?? '' ) ) ) {
			return new \WP_Error( 'pbsw_transfer_checksum', __( 'The export file is damaged or was edited after it was made (its checksum does not match). Export it again from the other site.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * Import a package.
	 *
	 * @param array<string, mixed> $package        Decoded package.
	 * @param string               $mode           One of self::MODES.
	 * @param bool                 $import_settings Whether to apply the package's settings.
	 * @param bool                 $download_media Whether to copy media from the source site.
	 * @param bool                 $dry_run        Report what would happen without writing.
	 * @return array{created:array<int,string>, replaced:array<int,string>, skipped:array<int,string>, settings:array<int,string>, media:int, errors:array<int,string>}|\WP_Error
	 */
	public static function import( array $package, string $mode, bool $import_settings, bool $download_media, bool $dry_run = false ) {
		$valid = self::validate( $package );
		if ( true !== $valid ) {
			return $valid;
		}
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$mode = 'skip';
		}

		$report = array(
			'created'  => array(),
			'replaced' => array(),
			'skipped'  => array(),
			'settings' => array(),
			'media'    => 0,
			'errors'   => array(),
		);
		$types  = self::post_types();
		$media  = array();

		foreach ( $package['items'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type  = (string) ( $item['type'] ?? '' );
			$slug  = sanitize_title( (string) ( $item['slug'] ?? '' ) );
			$label = '' !== (string) ( $item['title'] ?? '' ) ? (string) $item['title'] : $slug;
			if ( ! in_array( $type, $types, true ) || '' === $slug ) {
				/* translators: %s: item name from the export file. */
				$report['errors'][] = sprintf( __( '“%s” was not imported: this site does not have that kind of item.', 'page-builder-sandwich' ), $label );
				continue;
			}
			$object = get_post_type_object( $type );
			if ( ! $object || ! current_user_can( $object->cap->create_posts ) ) {
				/* translators: %s: item name from the export file. */
				$report['errors'][] = sprintf( __( '“%s” was not imported: you are not allowed to create that kind of item.', 'page-builder-sandwich' ), $label );
				continue;
			}

			$existing = self::find_existing( $type, $slug, $item );
			if ( $existing && 'skip' === $mode ) {
				$report['skipped'][] = $label;
				continue;
			}
			if ( $existing && 'replace' === $mode && ! current_user_can( 'edit_post', $existing->ID ) ) {
				/* translators: %s: item name from the export file. */
				$report['errors'][] = sprintf( __( '“%s” was not replaced: you are not allowed to edit the one on this site.', 'page-builder-sandwich' ), $label );
				continue;
			}

			$content = (string) ( $item['content'] ?? '' );
			if ( $download_media && ! $dry_run ) {
				$content = self::localise_media( $content, (string) ( $package['uploads'] ?? '' ), $media, $report );
			}
			if ( ! current_user_can( 'unfiltered_html' ) ) {
				$content = wp_kses_post( $content );
			}

			if ( $dry_run ) {
				$report[ $existing && 'replace' === $mode ? 'replaced' : 'created' ][] = $label;
				continue;
			}

			$result = self::write_item( $item, $type, $slug, $content, 'replace' === $mode ? $existing : null );
			if ( is_wp_error( $result ) ) {
				/* translators: 1: item name from the export file, 2: the reason. */
				$report['errors'][] = sprintf( __( '“%1$s” was not imported: %2$s', 'page-builder-sandwich' ), $label, $result->get_error_message() );
				continue;
			}
			$report[ $existing && 'replace' === $mode ? 'replaced' : 'created' ][] = $label;
		}

		if ( $import_settings && isset( $package['settings'] ) && is_array( $package['settings'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				$report['errors'][] = __( 'Settings were not imported: only an administrator can change them.', 'page-builder-sandwich' );
			} else {
				$allowed = self::options();
				foreach ( $package['settings'] as $name => $value ) {
					if ( ! in_array( (string) $name, $allowed, true ) ) {
						continue;
					}
					if ( ! $dry_run ) {
						update_option( (string) $name, self::clean_setting( $value ) );
					}
					$report['settings'][] = (string) $name;
				}
			}
		}

		if ( ! $dry_run ) {
			/**
			 * Fires after a package was imported, so caches built from templates and settings
			 * (the per-page styles, the site style sheet) can be rebuilt.
			 *
			 * @param array<string, mixed> $report What was imported.
			 */
			do_action( 'pbsw_transfer_imported', $report );
		}

		return $report;
	}

	/**
	 * Settings values are data, never code: strings are stripped of tags, other scalars kept.
	 *
	 * Custom CSS inside a setting is the one string that legitimately holds `<`/`>` characters
	 * (selectors, `>` combinators); it is sanitised by its own module when it is next saved or
	 * compiled, and only an administrator reaches this code.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function clean_setting( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( self::class, 'clean_setting' ), $value );
		}
		if ( is_string( $value ) ) {
			return str_contains( $value, '</' ) ? wp_strip_all_tags( $value ) : $value;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return null;
	}

	/**
	 * The item on this site with the same type and slug (templates also match on theme).
	 *
	 * @param string               $type Post type.
	 * @param string               $slug Slug.
	 * @param array<string, mixed> $item Package item.
	 * @return \WP_Post|null
	 */
	private static function find_existing( string $type, string $slug, array $item ): ?\WP_Post {
		$args = array(
			'post_type'      => $type,
			'name'           => $slug,
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		);
		if ( in_array( $type, array( 'wp_template', 'wp_template_part', 'wp_global_styles' ), true ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one indexed lookup per imported template.
				array(
					'taxonomy' => 'wp_theme',
					'field'    => 'slug',
					'terms'    => get_stylesheet(),
				),
			);
		}
		unset( $item );
		$posts = get_posts( $args );

		return $posts && $posts[0] instanceof \WP_Post ? $posts[0] : null;
	}

	/**
	 * Create or update one item.
	 *
	 * @param array<string, mixed> $item     Package item.
	 * @param string               $type     Post type.
	 * @param string               $slug     Slug.
	 * @param string               $content  Sanitised content.
	 * @param \WP_Post|null        $existing Post to replace, or null to create.
	 * @return int|\WP_Error
	 */
	private static function write_item( array $item, string $type, string $slug, string $content, ?\WP_Post $existing ) {
		$status = (string) ( $item['status'] ?? 'publish' );
		if ( ! in_array( $status, array( 'publish', 'draft', 'private' ), true ) ) {
			$status = 'draft';
		}
		$post = array(
			'post_type'    => $type,
			'post_name'    => $slug,
			'post_title'   => sanitize_text_field( (string) ( $item['title'] ?? $slug ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $item['excerpt'] ?? '' ) ),
			'post_status'  => $status,
			'post_content' => wp_slash( $content ),
		);
		if ( $existing ) {
			$post['ID'] = $existing->ID;
			$id         = wp_update_post( $post, true );
		} else {
			$id = wp_insert_post( $post, true );
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$id = (int) $id;

		$terms = is_array( $item['terms'] ?? null ) ? $item['terms'] : array();
		foreach ( get_object_taxonomies( $type ) as $taxonomy ) {
			if ( 'wp_theme' === $taxonomy ) {
				// Templates belong to THIS site's theme, whatever theme exported them.
				wp_set_object_terms( $id, get_stylesheet(), 'wp_theme' );
				continue;
			}
			if ( isset( $terms[ $taxonomy ] ) && is_array( $terms[ $taxonomy ] ) ) {
				wp_set_object_terms( $id, array_map( 'sanitize_title', array_map( 'strval', $terms[ $taxonomy ] ) ), $taxonomy );
			}
		}

		$meta    = is_array( $item['meta'] ?? null ) ? $item['meta'] : array();
		$allowed = self::portable_meta_keys();
		foreach ( $meta as $key => $value ) {
			if ( in_array( (string) $key, $allowed, true ) ) {
				update_post_meta( $id, (string) $key, is_string( $value ) ? wp_slash( $value ) : self::clean_setting( $value ) );
			}
		}

		return $id;
	}

	/**
	 * Download images the content references from the source site's uploads into this site's
	 * library and point the content at the copies. Only URLs under the source's uploads folder are
	 * fetched: a package cannot make this site request an arbitrary address.
	 *
	 * @param string                $content Content.
	 * @param string                $uploads Source uploads base URL.
	 * @param array<string, string> $media   Already-downloaded map (old URL => new URL), by reference.
	 * @param array<string, mixed>  $report  Report, by reference.
	 * @return string
	 */
	private static function localise_media( string $content, string $uploads, array &$media, array &$report ): string {
		$uploads = untrailingslashit( esc_url_raw( $uploads ) );
		if ( '' === $uploads || ! wp_http_validate_url( $uploads ) ) {
			return $content;
		}
		$pattern = '#' . preg_quote( $uploads, '#' ) . '/[^\s"\'<>()]+\.(?:jpe?g|png|gif|webp|avif|svg)#i';
		if ( ! preg_match_all( $pattern, $content, $found ) ) {
			return $content;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		foreach ( array_unique( $found[0] ) as $url ) {
			if ( isset( $media[ $url ] ) ) {
				continue;
			}
			if ( str_ends_with( strtolower( $url ), '.svg' ) ) {
				continue; // WordPress does not accept SVG uploads by default; leave the original link.
			}
			$id = media_sideload_image( $url, 0, null, 'id' );
			if ( is_wp_error( $id ) ) {
				/* translators: 1: image address, 2: the reason. */
				$report['errors'][] = sprintf( __( 'The image %1$s was not copied: %2$s', 'page-builder-sandwich' ), $url, $id->get_error_message() );
				continue;
			}
			$new = wp_get_attachment_url( (int) $id );
			if ( $new ) {
				$media[ $url ] = $new;
				++$report['media'];
			}
		}

		return strtr( $content, $media );
	}
}
