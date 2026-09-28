<?php
/**
 * One compiled stylesheet per page (pbs-p2).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets\Perf;

use ZinnDigital\PBS\Assets;
use ZinnDigital\PBS\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compiles the CSS a post needs — the stylesheets of exactly the blocks it uses — into ONE cached,
 * content-hashed file `uploads/<prefix>-assets/<hash>.css`, so a page makes one stylesheet request
 * instead of one per block type.
 *
 * When: on every save (`wp_after_insert_post`), and on the first view of a post whose compiled
 * state is missing or stale (a version, prefix or content change the save hook did not see).
 * Where the uploads folder cannot be written directly, the same CSS is printed inline.
 *
 * ⛔⛔ BLOCK STYLES STAY INLINE — THE STYLESHEET HOLDS ONLY THE BLOCKS' OWN CSS. The first version
 * moved each block's `style="…"` into a `!important` class rule and claimed the look could not
 * change. It could, and did (the visual diff fell from 111 to 72 of 122 pages), because no class
 * rule can reproduce an inline declaration's place in the cascade:
 *
 * - WITHOUT `!important`, any author rule of higher specificity (a theme's `#main .entry a`)
 *   beats the class, where it never beat the inline style — the look changes.
 * - WITH `!important`, the class beats every lower-specificity `!important` rule (a theme's or
 *   legacy's own phone-width `!important` rules), which a normal inline style LOSES to — and it
 *   beats anything a script writes to `element.style` (legacy's behaviour script today, every
 *   Interactivity API block tomorrow) — the look changes.
 *
 * Inline sits in a cascade layer of its own that no selector can occupy, so there is no
 * specificity that reproduces it. The rule for all content, new and converted alike: a style that
 * can be touched by a theme or at runtime is left where it is. Proven by run.py ("compiled sheet
 * on vs off: identical computed styles") — a theme `!important` rule of low specificity and a
 * script writing `element.style`, every element under `<main>` compared with getComputedStyle in
 * Chrome with the compiled sheet on and off.
 *
 * ⛔ Neither the saved post_content (the static fallback that survives deactivation) nor the
 * rendered HTML is changed by this class.
 */
final class Page_Css {

	/** Post meta holding the compiled state. */
	public const META = '_pbsw_page_css';

	/** Post meta holding only the file hash, so a shared file is deleted only when unreferenced. */
	public const META_FILE = '_pbsw_page_css_file';

	/** Bumped when the stored shape changes. */
	private const SCHEMA = 3; // 2: no style rules (styles stay inline). 3: design rules (pbs-p4) via pbsw_page_css_extra.

	/** How deep synced patterns (`core/block`) are followed. */
	private const MAX_REF_DEPTH = 4;

	/**
	 * What this request enqueued: post id, covered block names and rule classes.
	 *
	 * @var array{post: int, names: array<string, bool>, handle: string}|null
	 */
	private static ?array $active = null;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_after_insert_post', array( self::class, 'on_save' ), 10, 2 );
		add_action( 'before_delete_post', array( self::class, 'on_delete' ) );
		// `template_redirect`, not `wp_enqueue_scripts`: a block theme renders the whole template —
		// and so every block — BEFORE wp_head, so the page's state must be active before that.
		add_action( 'template_redirect', array( self::class, 'enqueue_for_request' ), 1 );
	}

	// ── The pure half ────────────────────────────────────────────────────────────────────────

	/**
	 * Compile the stylesheet for a parsed block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks           Parsed blocks.
	 * @param string                           $prefix           Class prefix.
	 * @param callable|null                    $resolve_ref      `fn( int $ref ): array` → the parsed blocks of a synced pattern.
	 * @param bool                             $button_shortcode The content uses `[pbs_button]`.
	 * @param callable|null                    $read_source      `fn( string $key ): ?string` → a block stylesheet with the prefix applied.
	 * @param int                              $post_id          The post compiled (0 when none; passed to the extra filter).
	 * @return array{css: string, names: array<int, string>, blocks: array<int, array<string, mixed>>}
	 */
	public static function compile( array $blocks, string $prefix, ?callable $resolve_ref = null, bool $button_shortcode = false, ?callable $read_source = null, int $post_id = 0 ): array {
		$blocks = self::expand_refs( $blocks, $resolve_ref, 0 );
		$names  = Block_Assets::names_in( $blocks, $button_shortcode );

		$read_source = $read_source ?? static fn( string $key ): ?string => Assets::source( $key, $prefix );
		$css         = '';
		$map         = Block_Assets::map();
		foreach ( $names as $name ) {
			$css .= self::minify( (string) $read_source( $map[ $name ] ) );
		}
		/**
		 * Filters the CSS appended to a page's compiled sheet after the blocks' own stylesheets
		 * (pbs-p4: the design system's `.<prefix>-s-<id>` rules). Compiled, not minified here:
		 * a listener returns its CSS ready to serve.
		 *
		 * @param string                           $extra   CSS so far ('').
		 * @param array<int, array<string, mixed>> $blocks  Parsed blocks, synced patterns expanded.
		 * @param string                           $prefix  Class prefix.
		 * @param int                              $post_id The post (0 when none).
		 */
		$css .= (string) apply_filters( 'pbsw_page_css_extra', '', $blocks, $prefix, $post_id );

		return array(
			'css'    => $css,
			'names'  => $names,
			'blocks' => $blocks,
		);
	}

	/**
	 * Strip comments and collapse whitespace. Our own stylesheets only: no strings with `/*`.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function minify( string $css ): string {
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = (string) preg_replace( '/\s+/', ' ', $css );
		$css = (string) preg_replace( '/\s*([{};:,>])\s*/', '$1', $css );

		return str_replace( ';}', '}', trim( $css ) );
	}

	/**
	 * Replace `core/block` references with the synced pattern's blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Parsed blocks.
	 * @param callable|null                    $resolve Resolver.
	 * @param int                              $depth   Current depth.
	 * @return array<int, array<string, mixed>>
	 */
	private static function expand_refs( array $blocks, ?callable $resolve, int $depth ): array {
		$out = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$ref = (int) ( $block['attrs']['ref'] ?? 0 );
			if ( 'core/block' === ( $block['blockName'] ?? '' ) && $ref > 0 && null !== $resolve && $depth < self::MAX_REF_DEPTH ) {
				$block['innerBlocks'] = self::expand_refs( (array) $resolve( $ref ), $resolve, $depth + 1 );
			} else {
				$block['innerBlocks'] = self::expand_refs( (array) ( $block['innerBlocks'] ?? array() ), $resolve, $depth );
			}
			$out[] = $block;
		}

		return $out;
	}

	// ── State: build, read, delete ──────────────────────────────────────────────────────────

	/**
	 * The stored state for a post, if it is current for this version, prefix and content.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    The post, when the caller has it.
	 * @return array<string, mixed>|null
	 */
	public static function state( int $post_id, ?\WP_Post $post = null ): ?array {
		$post = $post ?? get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$state = get_post_meta( $post_id, self::META, true );
		if ( ! is_array( $state ) || self::signature( $post ) !== ( $state['sig'] ?? null ) ) {
			return null;
		}
		if ( is_string( $state['path'] ?? null ) && ! is_readable( $state['path'] ) ) {
			return null; // The file was removed (cleanup, a restore, a migration) — rebuild it.
		}

		return $state;
	}

	/**
	 * Compile, write and store a post's stylesheet. Fires `pbsw_page_css_changed` when the
	 * compiled file changed.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null The state, or null when the post has no PBS content.
	 */
	public static function build( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$old     = get_post_meta( $post_id, self::META, true );
		$old     = is_array( $old ) ? $old : array();
		$content = (string) $post->post_content;
		if ( ! Perf::is_pbs_content( $content ) ) {
			if ( array() !== $old ) {
				self::forget( $post_id, $old );
			}
			return null;
		}

		$blocks   = parse_blocks( $content );
		$compiled = self::compile( $blocks, Settings::prefix(), array( self::class, 'ref_blocks' ), has_shortcode( $content, 'pbs_button' ), null, $post_id );
		$state    = array(
			'schema' => self::SCHEMA,
			'sig'    => self::signature( $post ),
			'names'  => $compiled['names'],
			'dims'   => Layout_Guard::resolve_dims( $blocks ),
			'hero'   => Lcp::hero_image( $blocks ),
			'hash'   => '',
			'url'    => null,
			'path'   => null,
			'css'    => null,
		);
		if ( '' !== $compiled['css'] ) {
			$file = Files::write( $compiled['css'], 'css' );
			if ( null !== $file ) {
				$state['hash'] = $file['hash'];
				$state['url']  = $file['url'];
				$state['path'] = $file['path'];
			} else {
				$state['hash'] = substr( sha1( $compiled['css'] ), 0, 12 );
				$state['css']  = $compiled['css']; // Printed inline: uploads is not writable.
			}
		}

		/**
		 * Filters a post's compiled state before it is stored (pbs-p4 records which design rules
		 * the sheet carries, so the render filter does not print them again).
		 *
		 * @param array<string, mixed>             $state   State.
		 * @param array<int, array<string, mixed>> $blocks  Parsed blocks, synced patterns expanded.
		 * @param int                              $post_id Post ID.
		 */
		$state = (array) apply_filters( 'pbsw_page_css_state', $state, $compiled['blocks'], $post_id );

		update_post_meta( $post_id, self::META, wp_slash( $state ) );
		update_post_meta( $post_id, self::META_FILE, $state['hash'] );

		$old_hash = (string) ( $old['hash'] ?? '' );
		if ( $old_hash !== $state['hash'] ) {
			self::maybe_delete_file( $post_id, $old );
			/**
			 * Fires when a post's compiled stylesheet changed (cache plugins purge on it).
			 *
			 * @param int $post_id Post ID.
			 */
			do_action( 'pbsw_page_css_changed', $post_id );
		}

		return $state;
	}

	/**
	 * The blocks of a published synced pattern.
	 *
	 * @param int $ref `wp_block` post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function ref_blocks( int $ref ): array {
		$pattern = get_post( $ref );
		if ( ! $pattern instanceof \WP_Post || 'wp_block' !== $pattern->post_type || 'publish' !== $pattern->post_status ) {
			return array();
		}

		return parse_blocks( (string) $pattern->post_content );
	}

	/**
	 * `wp_after_insert_post`: rebuild on every real save.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post.
	 * @return void
	 */
	public static function on_save( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( $post instanceof \WP_Post && 'wp_block' === $post->post_type ) {
			return; // A pattern has no page of its own; pages using it rebuild on their next view (their signature includes it).
		}
		self::build( $post_id );
	}

	/**
	 * `before_delete_post`: drop the state and the file if nothing else uses it.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_delete( $post_id ): void {
		$state = get_post_meta( (int) $post_id, self::META, true );
		if ( is_array( $state ) ) {
			self::forget( (int) $post_id, $state );
		}
	}

	// ── The request half ────────────────────────────────────────────────────────────────────

	/**
	 * `template_redirect`: the singular post's compiled stylesheet, in `<head>`.
	 *
	 * @return void
	 */
	public static function enqueue_for_request(): void {
		self::$active = null;
		if ( ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! Perf::is_pbs_content( (string) $post->post_content ) ) {
			return;
		}
		$state = self::state( $post_id, $post ) ?? self::build( $post_id );
		if ( null === $state ) {
			return;
		}
		self::activate( $post_id, $state );
	}

	/**
	 * Enqueue a state's stylesheet and remember what it covers.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $state   State.
	 * @return void
	 */
	public static function activate( int $post_id, array $state ): void {
		$hash   = (string) ( $state['hash'] ?? '' );
		$handle = Settings::prefix() . '-' . substr( '' === $hash ? 'none' : $hash, 0, 8 );
		if ( '' !== $hash ) {
			if ( is_string( $state['url'] ?? null ) ) {
				wp_enqueue_style( $handle, Files::current_url( (string) $state['url'] ), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the file name is its content hash.
			} else {
				wp_register_style( $handle, false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- inline only.
				wp_enqueue_style( $handle );
				wp_add_inline_style( $handle, (string) ( $state['css'] ?? '' ) );
			}
		}
		self::$active = array(
			'post'   => $post_id,
			'names'  => array_fill_keys( (array) ( $state['names'] ?? array() ), true ),
			'handle' => '' === $hash ? '' : $handle,
		);

		/**
		 * Fires after a page's compiled stylesheet is enqueued (the premium critical-CSS layer
		 * hooks here).
		 *
		 * @param string               $handle  Style handle ('' when the page needs no CSS).
		 * @param array<string, mixed> $state   Compiled state.
		 * @param int                  $post_id Post ID.
		 */
		do_action( 'pbsw_page_css_enqueued', self::$active['handle'], $state, $post_id );
	}

	/**
	 * Is a block's stylesheet already part of the compiled sheet enqueued on this request?
	 *
	 * @param string $name Block name.
	 * @return bool
	 */
	public static function covers( string $name ): bool {
		return null !== self::$active && isset( self::$active['names'][ $name ] );
	}

	/**
	 * The active request state (for the critical-CSS layer and tests).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function active(): ?array {
		return self::$active;
	}

	/**
	 * Forget the active state (tests; a new request).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$active = null;
	}

	/**
	 * What makes a stored state current: schema, version, prefix, the content, and the synced
	 * patterns it references (their modified time).
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function signature( \WP_Post $post ): string {
		$content = (string) $post->post_content;
		$refs    = '';
		if ( str_contains( $content, '<!-- wp:block ' ) && preg_match_all( '/<!-- wp:block \{"ref":(\d+)/', $content, $m ) ) {
			foreach ( array_unique( $m[1] ) as $ref ) {
				$pattern = get_post( (int) $ref );
				$refs   .= $ref . ':' . ( $pattern instanceof \WP_Post ? $pattern->post_modified_gmt : '' ) . ';';
			}
		}

		/**
		 * Filters what else makes a compiled state current (pbs-p4: the breakpoints).
		 *
		 * @param string   $extra '' so far.
		 * @param \WP_Post $post  Post.
		 */
		$extra = (string) apply_filters( 'pbsw_page_css_signature', '', $post );

		return sha1( self::SCHEMA . '|' . PBSW_VERSION . '|' . Settings::prefix() . '|' . md5( $content ) . '|' . $refs . $extra );
	}

	/**
	 * Delete the state and, if unreferenced, its file.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $state   State.
	 * @return void
	 */
	private static function forget( int $post_id, array $state ): void {
		delete_post_meta( $post_id, self::META );
		delete_post_meta( $post_id, self::META_FILE );
		self::maybe_delete_file( $post_id, $state );
		do_action( 'pbsw_page_css_changed', $post_id );
	}

	/**
	 * Delete a state's file unless another post's state uses the same file (identical CSS is
	 * one shared file).
	 *
	 * @param int                  $post_id Post the state belonged to.
	 * @param array<string, mixed> $state   State.
	 * @return void
	 */
	private static function maybe_delete_file( int $post_id, array $state ): void {
		$hash = (string) ( $state['hash'] ?? '' );
		$path = $state['path'] ?? null;
		if ( '' === $hash || ! is_string( $path ) ) {
			return;
		}
		$others = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'post__not_in'   => array( $post_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- one row, on save only.
				'meta_key'       => self::META_FILE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- on save only.
				'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- on save only.
			)
		);
		if ( array() === $others ) {
			Files::delete( $path );
		}
	}
}
