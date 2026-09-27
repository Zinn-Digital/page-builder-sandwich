<?php
/**
 * Legacy Page Builder Sandwich content → blocks.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

use ZinnDigital\PBS\Core\Fallback;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names (childNodes, tagName, …) are PHP's, not ours.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts the markup legacy PBS (every release up to 5.1.0) stored in `post_content` into
 * serialized blocks, per the L08 design (docs/plugins-overhaul/plan/lanes/state/L08.md §P1.3).
 *
 * The mapping, and why each rule is the way it is:
 *
 * - `<table class="pbsandwich_column">` (pre-4.0) and `div.pbs-row` / `div.pbs-col` (4.x–5.x),
 *   at any nesting depth → `pbs/row` + `pbs/column`. One row per table `<tr>`: legacy's own
 *   migration kept only the first and silently dropped the rest.
 * - `a.pbs-button` (alone in its paragraph, or alone between blocks) and the pre-4.0
 *   `[pbs_button]` shortcode → `pbs/button`. ⛔ The shortcode's label is ESCAPED here: legacy's
 *   migration printed it raw (10-audit-pbs.md §3, stored XSS).
 * - `[pbs_widget]` / `[pbs_sidebar]` → `pbs/widget` / `pbs/sidebar`; any other shortcode that
 *   stands alone → `core/shortcode`.
 * - A leaf becomes a CORE block only when the markup written here is byte-for-byte what that
 *   block's `save()` produces, so the editor never reports "This block contains unexpected or
 *   invalid content" (proved over the whole corpus by wp/tests/js/pbs-convert). Everything else is
 *   `core/html` holding the original element: always valid, and the legacy classes inside it are
 *   rewritten at render time by the legacy compat layer.
 * - Text directly inside a column went through `wpautop` when legacy rendered it, so it is
 *   paragraphed here the same way; otherwise converting would change what visitors see.
 *
 * Blocks already in the input are passed through untouched and only the freeform parts are
 * converted, which is what makes conversion idempotent.
 */
final class Converter {

	/** Legacy markers that mean "this content was written by legacy PBS". */
	private const MARKER_RE = '/pbsandwich_column|\[pbs_(?:button|widget|sidebar)\b|\bclass\s*=\s*["\'][^"\']*(?<![\w-])pbs-[\w-]/i';

	/** A block comment delimiter, as core's block parser tokenizes it. */
	private const DELIMITER_RE = '/<!--\s+(?P<closer>\/)?wp:(?P<name>[a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s';

	/** One shortcode (WordPress's get_shortcode_regex() shape, for any tag name). */
	private const SHORTCODE_RE = '\[(\[?)([a-zA-Z][\w-]*)(?![\w-])((?:[^\[\]\/]|\/(?!\]))*?)(?:(\/)\]|\](?:((?:[^\[]|\[(?!\/\2\]))*?)\[\/\2\])?)(\]?)';

	/** Elements that wpautop and the browser treat as inline (a run of them is one paragraph). */
	private const INLINE_TAGS = array( 'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'cite', 'code', 'data', 'del', 'dfn', 'em', 'font', 'i', 'img', 'input', 'ins', 'kbd', 'label', 'mark', 'q', 's', 'samp', 'select', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'textarea', 'time', 'tt', 'u', 'var', 'wbr' );

	/**
	 * Inline elements a `core/paragraph`, `core/heading` or `core/list-item` may hold and still
	 * save byte-identically, with the attributes each may carry. Anything else sends the leaf
	 * to `core/html`. Widened only when the Node validation gate (wp/tests/js/pbs-convert)
	 * proves a shape valid — never by loosening that test.
	 */
	private const RICH_TEXT_TAGS = array(
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'br'     => array(),
		'code'   => array(),
		'sub'    => array(),
		'sup'    => array(),
		's'      => array(),
		'a'      => array( 'href' ),
	);

	/** Classes legacy's front-end scripts added at runtime; never meaningful in saved content. */
	private const RUNTIME_CLASSES = array( 'aos-init', 'aos-animate' );

	/** Row children legacy's script created at runtime (see is_runtime_layer()). */
	private const RUNTIME_LAYERS = array( 'pbs-video-bg', 'pbs-parallax', 'pbs-kenburns-bg', 'pbs-carousel-bullet-wrapper' );

	/** Row widths the `pbs/row` block understands. */
	private const ROW_WIDTHS = array( 'full-width', 'full-width-retain-content' );

	/**
	 * Whether HTML still carries legacy PBS markup outside any block.
	 *
	 * @param string $html Post content.
	 * @return bool
	 */
	public static function is_legacy( string $html ): bool {
		foreach ( self::split( $html ) as $part ) {
			if ( 'free' === $part[0] && 1 === preg_match( self::MARKER_RE, $part[1] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Converts content to serialized blocks. Existing blocks are kept byte-for-byte; only the
	 * freeform (classic) parts are converted, so converting the output again changes nothing.
	 *
	 * @param string $html Post content.
	 * @param array  $ctx  Reserved for callers (post id, …); not used by the mapping.
	 * @return string
	 */
	public static function convert_html( string $html, array $ctx = array() ): string {
		unset( $ctx );
		$out = array();
		foreach ( self::split( $html ) as $part ) {
			if ( 'block' === $part[0] ) {
				$out[] = $part[1];
			} elseif ( '' !== trim( $part[1] ) ) {
				array_push( $out, ...( new self() )->fragment( $part[1] ) );
			}
		}

		return implode( "\n\n", $out );
	}

	/**
	 * Converts one post: backup first, idempotent, never touches a post already converted.
	 *
	 * @param int $post_id Post ID.
	 * @return array{status:string,changed:bool}
	 */
	public static function convert_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'status'  => 'missing',
				'changed' => false,
			);
		}
		if ( '' !== (string) get_post_meta( $post_id, Legacy::CONVERTED_META, true ) ) {
			return array(
				'status'  => 'already-converted',
				'changed' => false,
			);
		}
		if ( ! Legacy::post_is_legacy( $post_id ) ) {
			return array(
				'status'  => 'not-legacy',
				'changed' => false,
			);
		}

		Backup::save( $post_id );
		$content = (string) $post->post_content;
		// Images legacy bundled in its own folder are gone after the update: point them at uploads.
		$converted = Media::localize( self::convert_html( $content ) );
		// Widgets and sidebars get the snapshot a visitor reads if the plugin is ever turned off.
		if ( function_exists( 'the_widget' ) && class_exists( Fallback::class ) ) {
			$converted = Fallback::fill_widgets( $converted, array( Fallback::class, 'render_widget_block' ) );
		}
		$changed = $converted !== $content;
		if ( $changed && ! Backup::write_content( $post_id, $converted ) ) {
			return array(
				'status'  => 'write-failed',
				'changed' => false,
			);
		}
		update_post_meta( $post_id, Legacy::CONVERTED_META, defined( 'PBSW_VERSION' ) ? (string) PBSW_VERSION : '0' );

		return array(
			'status'  => 'converted',
			'changed' => $changed,
		);
	}

	/**
	 * Splits content into top-level block and freeform parts, in order.
	 *
	 * @param string $html Content.
	 * @return array<int, array{0:string,1:string}> `['block'|'free', text]` pairs.
	 */
	public static function split( string $html ): array {
		$parts = array();
		$depth = 0;
		$start = 0;
		$pos   = 0;
		while ( 1 === preg_match( self::DELIMITER_RE, $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$at  = $m[0][1];
			$end = $at + strlen( $m[0][0] );
			$pos = $end;
			if ( '' !== ( $m['closer'][0] ?? '' ) && -1 !== $m['closer'][1] ) {
				if ( 0 === $depth ) {
					continue; // A stray closer: core ignores it too.
				}
				--$depth;
				if ( 0 === $depth ) {
					$parts[] = array( 'block', substr( $html, $start, $end - $start ) );
					$start   = $end;
				}
				continue;
			}
			if ( 0 === $depth && $at > $start ) {
				$parts[] = array( 'free', substr( $html, $start, $at - $start ) );
			}
			if ( 0 === $depth ) {
				$start = $at;
			}
			if ( isset( $m['void'] ) && -1 !== $m['void'][1] && '' !== $m['void'][0] ) {
				if ( 0 === $depth ) {
					$parts[] = array( 'block', substr( $html, $at, $end - $at ) );
					$start   = $end;
				}
				continue;
			}
			++$depth;
		}
		if ( $start < strlen( $html ) ) {
			// An unclosed block runs to the end, as core's parser treats it.
			$parts[] = array( $depth > 0 ? 'block' : 'free', substr( $html, $start ) );
		}

		return $parts;
	}

	/* ─── the walk ─────────────────────────────────────────────────────────────────────── */

	/**
	 * Converts one freeform fragment.
	 *
	 * @param string $html Fragment.
	 * @return string[] Serialized blocks.
	 */
	private function fragment( string $html ): array {
		$body = $this->parse( $html );
		foreach ( iterator_to_array( ( new \DOMXPath( $body->ownerDocument ) )->query( '//*[contains(@class, "ui-sortable")]' ) ) as $el ) {
			// Stray editor-handle classes pre-4.0 PBS left behind (legacy class-migration.php).
			$this->set_classes( $el, preg_grep( '/^ui-sortable(-\w+)?$/', $this->classes( $el ), PREG_GREP_INVERT ) );
		}

		return $this->nodes( iterator_to_array( $body->childNodes ) );
	}

	/**
	 * Parses an HTML fragment as UTF-8, network access off.
	 *
	 * @param string $html Fragment.
	 * @return \DOMElement The body holding the fragment's nodes.
	 */
	private function parse( string $html ): \DOMElement {
		$doc      = new \DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		// No LIBXML_NOENT: entities are never expanded, and the HTML parser loads no DTD.
		$doc->loadHTML(
			'<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $html . '</body></html>',
			LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_COMPACT
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body instanceof \DOMElement ) {
			$body = $doc->createElement( 'body' );
		}

		return $body;
	}

	/**
	 * A sequence of sibling nodes → blocks. Inline nodes collect into runs, paragraphed the way
	 * legacy's wpautop paragraphed them.
	 *
	 * @param \DOMNode[] $nodes Nodes.
	 * @param bool       $opens Whether these are a block container's children (see bare_lead()).
	 * @return string[]
	 */
	private function nodes( array $nodes, bool $opens = false ): array {
		$blocks = array();
		$run    = array();
		if ( $opens ) {
			$lead = $this->bare_lead( $nodes );
			if ( null !== $lead ) {
				$blocks[] = $lead[0];
				$nodes    = $lead[1];
			}
		}
		foreach ( $nodes as $node ) {
			if ( $this->is_inline( $node ) ) {
				$run[] = $node;
				continue;
			}
			array_push( $blocks, ...$this->run( $run ), ...$this->element( $node ) );
			$run = array();
		}
		array_push( $blocks, ...$this->run( $run ) );

		return $blocks;
	}

	/**
	 * The one place legacy's wpautop did NOT paragraph inline content: the run that follows a
	 * block container's opening tag on the same "paragraph" (no blank line between them).
	 *
	 * The function wpautop() wraps each blank-line chunk in `<p>…</p>` and then deletes a `<p>` that stands
	 * right before a block opening tag, and a `</p>` right after a block tag. For
	 * `<div class="pbs-col">\n<img> <p>Text</p>` the chunk is `<div class="pbs-col">\n<img>`, so
	 * the output is `<div class="pbs-col">\n<img> </p>`: the image is a DIRECT child of the
	 * column (a flex item, which legacy's `.pbs-col > *` rules size and align), and the orphan
	 * `</p>` becomes an empty `<p></p>` in the browser, whose margin is part of the layout.
	 * When the chunk runs to the container's end (`<div class="pbs-col"><img></div>`) both tags
	 * go and no empty paragraph appears. Measured: every page template that opens a column with
	 * an image rendered it wrapped in a paragraph, unconstrained, overflowing its column.
	 *
	 * @param \DOMNode[] $nodes A container's child nodes.
	 * @return array{0:string,1:\DOMNode[]}|null The bare block and the nodes left, or null.
	 */
	private function bare_lead( array $nodes ): ?array {
		$lead = array();
		$rest = $nodes;
		while ( array() !== $rest && $this->is_inline( $rest[0] ) && ! $rest[0] instanceof \DOMComment ) {
			$lead[] = array_shift( $rest );
		}
		$html = '';
		foreach ( $lead as $node ) {
			$html .= $this->html( $node );
		}
		if ( '' === trim( $html ) || 1 === preg_match( '/^\s*\n\s*\n/', $html ) ) {
			return null; // Nothing inline first, or a blank line first: wpautop paragraphs it.
		}
		$parts = preg_split( '/\n\s*\n/', $html, 2 );
		$first = trim( (string) $parts[0] );
		// Shortcodes keep their own blocks (run() handles them).
		if ( str_contains( $first, '[' ) ) {
			return null;
		}
		$more = isset( $parts[1] ) && '' !== trim( $parts[1] );
		// The chunk ends before a block or a blank line: its `</p>` survives as an empty paragraph.
		$orphan = $more || $this->has_content( $rest );
		$left   = $more ? array_merge( iterator_to_array( $this->parse( (string) $parts[1] )->childNodes ), $rest ) : $rest;
		$only   = $this->only_element( $this->parse( $first ) );
		if ( $only && 'a' === strtolower( $only->tagName ) && $this->has_class( $only, 'pbs-button' ) ) {
			// A lone legacy button here was NOT paragraphed: it stays a bare link (a flex item).
			$button = $this->button_from_anchor( $only, '', array() );
			return array( $orphan ? $button . "\n\n" . $this->html_block( '<p></p>' ) : $button, $left );
		}

		return array( $this->html_block( $first . ( $orphan ? "\n<p></p>" : '' ) ), $left );
	}

	/**
	 * Whether a node belongs to an inline run.
	 *
	 * @param \DOMNode $node Node.
	 * @return bool
	 */
	private function is_inline( \DOMNode $node ): bool {
		if ( $node instanceof \DOMComment ) {
			return ! in_array( trim( $node->data ), array( 'more', 'nextpage' ), true );
		}
		if ( ! $node instanceof \DOMElement ) {
			return true;
		}
		if ( $this->has_class( $node, 'pbs-row' ) || $this->has_class( $node, 'pbs-col' ) ) {
			return false;
		}

		return in_array( strtolower( $node->tagName ), self::INLINE_TAGS, true );
	}

	/**
	 * An inline run → blocks: split at blank lines like wpautop, a chunk that is only shortcodes
	 * becomes shortcode blocks, a chunk that is only a legacy button becomes a button, and every
	 * other chunk is a paragraph.
	 *
	 * @param \DOMNode[] $run Nodes.
	 * @return string[]
	 */
	private function run( array $run ): array {
		$html = '';
		foreach ( $run as $node ) {
			$html .= $this->html( $node );
		}
		// A legacy PBS shortcode is always its own block, even mid-sentence: legacy's own
		// migration turned [pbs_button] into a block-level paragraph, and the widget and sidebar
		// render block-level output wherever they stand.
		$html   = (string) preg_replace( '/\[pbs_(?:button|widget|sidebar)\b(?:[^\[\]]|\[(?!\/?pbs_))*\]/', "\n\n\$0\n\n", $html );
		$blocks = array();
		foreach ( preg_split( '/\n\s*\n/', $html ) as $chunk ) {
			$chunk = trim( $chunk );
			if ( '' === $chunk ) {
				continue;
			}
			if ( 1 === preg_match( '/^(?:\s*' . self::SHORTCODE_RE . ')+\s*$/s', $chunk ) ) {
				preg_match_all( '/' . self::SHORTCODE_RE . '/s', $chunk, $all, PREG_SET_ORDER );
				foreach ( $all as $sc ) {
					array_push( $blocks, ...$this->shortcode( $sc ) );
				}
				continue;
			}
			$inner = $this->parse( $chunk );
			$only  = $this->only_element( $inner );
			if ( $only && 'a' === strtolower( $only->tagName ) && $this->has_class( $only, 'pbs-button' ) ) {
				// wpautop put a lone button in a paragraph, which is part of its layout.
				$blocks[] = $this->button_from_anchor( $only, '', array(), '', true );
				continue;
			}
			$blocks[] = $this->paragraph( $inner, '' );
		}

		return $blocks;
	}

	/**
	 * One block-level node → blocks.
	 *
	 * @param \DOMNode $node Node.
	 * @return string[]
	 */
	private function element( \DOMNode $node ): array {
		if ( $node instanceof \DOMComment ) {
			$name = trim( $node->data );
			return array( Serializer::block( 'core/' . $name, array(), '<!--' . $name . '-->' ) );
		}
		if ( ! $node instanceof \DOMElement ) {
			return array();
		}
		$tag = strtolower( $node->tagName );

		if ( 'table' === $tag && $this->has_class( $node, 'pbsandwich_column' ) ) {
			return $this->table( $node );
		}
		if ( $this->has_class( $node, 'pbs-row' ) ) {
			return array( $this->row( $node ) );
		}
		if ( $this->has_class( $node, 'pbs-col' ) ) {
			return array( Serializer::block( 'pbs/row', array(), '<div style="display:flex;flex-wrap:wrap">', array( $this->column( $node ) ), '</div>' ) );
		}
		if ( 'p' === $tag ) {
			return $this->p( $node );
		}
		if ( 'div' === $tag && 'html' === $node->getAttribute( 'data-ce-tag' ) ) {
			// The legacy Html element. Its editor attributes go, but the wrapper stays: it is
			// layout — the column's `> *:last-child { margin-bottom: 0 }` hit the WRAPPER, so the
			// author's last paragraph inside kept its bottom margin (measured: dropping the
			// wrapper moved everything below it up 17px).
			$inner = trim( $this->inner_html( $node ) );
			return '' === $inner ? array() : array( $this->html_block( '<div>' . $inner . '</div>' ) );
		}
		if ( 1 === preg_match( '/^h([1-6])$/', $tag, $level ) && $this->bare( $node ) && $this->is_rich_text( $node ) ) {
			$attrs = '2' === $level[1] ? array() : array( 'level' => (int) $level[1] );
			return array( Serializer::block( 'core/heading', $attrs, '<' . $tag . ' class="wp-block-heading">' . $this->inner_html( $node ) . '</' . $tag . '>' ) );
		}
		if ( ( 'ul' === $tag || 'ol' === $tag ) && $this->bare( $node ) ) {
			$list = $this->list_block( $node, $tag );
			if ( null !== $list ) {
				return array( $list );
			}
		}
		if ( 'hr' === $tag && $this->bare( $node ) ) {
			return array( Serializer::block( 'core/separator', array(), '<hr class="wp-block-separator has-alpha-channel-opacity"/>' ) );
		}

		return array( $this->html_block( $this->autop( $node ) ) );
	}

	/**
	 * An element kept as HTML, paragraphed the way legacy rendered it.
	 *
	 * Legacy ran wpautop over the whole content on every view (moved to priority 8), so text
	 * directly inside a legacy element — an icon label's caption, a tab's body, a card — was
	 * wrapped in `<p>`, with `<br />` for its line breaks. A `core/html` block is printed as
	 * stored (WordPress skips wpautop for block content), so the element is stored AFTER the
	 * same wpautop: the SITE's own function, which is the one legacy called. Measured: service
	 * cards whose caption lost its paragraph re-flowed beside their icons. Only containers are
	 * touched; a `<pre>`, `<script>`, `<style>` or a leaf is stored as it was.
	 *
	 * @param \DOMElement $el Element.
	 * @return string
	 */
	private function autop( \DOMElement $el ): string {
		$html = $this->html( $el );
		if ( ! function_exists( 'wpautop' ) || ! in_array( strtolower( $el->tagName ), array( 'div', 'section', 'article', 'aside', 'header', 'footer', 'blockquote', 'figure' ), true ) ) {
			return $html;
		}
		if ( 1 === preg_match( '/<(?:pre|script|style|textarea)\b/i', $html ) ) {
			return $html;
		}

		return trim( (string) wpautop( $html ) );
	}

	/**
	 * A `<p>`: a legacy button wrapper, a paragraph holding only shortcodes, or a paragraph.
	 *
	 * @param \DOMElement $p Paragraph.
	 * @return string[]
	 */
	private function p( \DOMElement $p ): array {
		$only = $this->only_element( $p );
		if ( $only && 'a' === strtolower( $only->tagName ) && $this->has_class( $only, 'pbs-button' ) ) {
			$decls = self::declarations( $p->getAttribute( 'style' ) );
			$align = strtolower( $decls['text-align'] ?? '' );
			unset( $decls['text-align'] );
			$rest = array();
			foreach ( $decls as $prop => $value ) {
				$rest[] = $prop . ': ' . $value;
			}
			return array( $this->button_from_anchor( $only, $align, $this->data_attributes( $p ), self::style( implode( '; ', $rest ) ), true ) );
		}
		if ( ! $p->hasAttributes() && ! $this->has_element_child( $p ) ) {
			$text = trim( $this->inner_html( $p ) );
			if ( '' !== $text && 1 === preg_match( '/^(?:\s*' . self::SHORTCODE_RE . ')+\s*$/s', $text ) ) {
				return $this->run( array( $p->ownerDocument->createTextNode( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
			}
		}

		return array( $this->paragraph( $p, $p->hasAttributes() ? $this->html( $p ) : '' ) );
	}

	/**
	 * `core/paragraph` when the markup round-trips exactly, otherwise `core/html`.
	 *
	 * @param \DOMElement $holder   The `<p>` itself, or a parsed body holding a run's nodes.
	 * @param string      $original The element's own HTML when it has attributes ('' ⇒ build `<p>`).
	 * @return string
	 */
	private function paragraph( \DOMElement $holder, string $original ): string {
		$inner = $this->inner_html( $holder );
		if ( '' === $original && $this->is_rich_text( $holder ) && '' !== trim( $inner ) ) {
			return Serializer::block( 'core/paragraph', array(), '<p>' . $inner . '</p>' );
		}

		return $this->html_block( '' !== $original ? $original : '<p>' . $inner . '</p>' );
	}

	/**
	 * `core/list` with `core/list-item`s, or null when any item is not plain rich text.
	 *
	 * @param \DOMElement $ul  The list element.
	 * @param string      $tag `ul` or `ol`.
	 * @return string|null
	 */
	private function list_block( \DOMElement $ul, string $tag ): ?string {
		$items = array();
		foreach ( $ul->childNodes as $child ) {
			if ( $child instanceof \DOMText && '' === trim( $child->data ) ) {
				continue;
			}
			if ( ! $child instanceof \DOMElement || 'li' !== strtolower( $child->tagName ) || ! $this->bare( $child ) || ! $this->is_rich_text( $child ) ) {
				return null;
			}
			$items[] = Serializer::block( 'core/list-item', array(), '<li>' . $this->inner_html( $child ) . '</li>' );
		}
		if ( array() === $items ) {
			return null;
		}

		return Serializer::block( 'core/list', 'ol' === $tag ? array( 'ordered' => true ) : array(), '<' . $tag . ' class="wp-block-list">', $items, '</' . $tag . '>' );
	}

	/**
	 * `core/html` for markup no block reproduces.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	private function html_block( string $html ): string {
		// No delimiter can be inside: split() already read any as a block, exactly as core's
		// block parser reads the same content.
		return Serializer::block( 'core/html', array(), $html );
	}

	/* ─── structure ────────────────────────────────────────────────────────────────────── */

	/**
	 * `table.pbsandwich_column` → one `pbs/row` per table row, one column per cell.
	 *
	 * @param \DOMElement $table Table.
	 * @return string[]
	 */
	private function table( \DOMElement $table ): array {
		$rows = array();
		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			$columns = array();
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell instanceof \DOMElement && in_array( strtolower( $cell->tagName ), array( 'td', 'th' ), true ) ) {
					$columns[] = Serializer::block( 'pbs/column', array(), '<div style="flex:1 1 0;min-width:0">', $this->nodes( iterator_to_array( $cell->childNodes ), true ), '</div>' );
				}
			}
			$rows[] = Serializer::block( 'pbs/row', array(), '<div style="display:flex;flex-wrap:wrap">', $columns, '</div>' );
		}

		return $rows;
	}

	/**
	 * `div.pbs-row` → `pbs/row`. Children that are not columns are gathered into a column so no
	 * content is lost (the block's inner blocks may only be columns).
	 *
	 * @param \DOMElement $row Row.
	 * @return string
	 */
	private function row( \DOMElement $row ): string {
		$width = $row->getAttribute( 'data-width' );
		// Legacy's script never widened a row nested in a column (_pbsFixRowWidth resets it).
		$nested = $row->parentNode instanceof \DOMElement && $this->has_class( $row->parentNode, 'pbs-col' );
		$width  = ! $nested && in_array( $width, self::ROW_WIDTHS, true ) ? $width : '';
		$attrs  = $this->common_attrs( $row, 'pbs-row', $width );

		$columns = array();
		$stray   = array();
		foreach ( iterator_to_array( $row->childNodes ) as $child ) {
			if ( $child instanceof \DOMElement && $this->is_runtime_layer( $child ) ) {
				continue;
			}
			if ( $child instanceof \DOMElement && $this->has_class( $child, 'pbs-col' ) ) {
				if ( $this->has_content( $stray ) ) {
					$columns[] = Serializer::block( 'pbs/column', array(), '<div style="flex:1 1 0;min-width:0">', $this->nodes( $stray ), '</div>' );
				}
				$stray     = array();
				$columns[] = $this->column( $child );
				continue;
			}
			$stray[] = $child;
		}
		if ( $this->has_content( $stray ) ) {
			$columns[] = Serializer::block( 'pbs/column', array(), '<div style="flex:1 1 0;min-width:0">', $this->nodes( $stray ), '</div>' );
		}

		return Serializer::block( 'pbs/row', $attrs, '<div style="' . self::attr( 'display:flex;flex-wrap:wrap' . ( isset( $attrs['style'] ) ? ';' . $attrs['style'] : '' ) ) . '">', $columns, '</div>' );
	}

	/**
	 * A layer legacy's front-end script inserted into a row at runtime (video background,
	 * parallax, Ken Burns, carousel bullets) and its editor then SAVED. It is not content: the
	 * behaviour is rebuilt from the row's `data-pbs-*` attributes (kept in `legacyData`), and a
	 * saved copy would render as a stray, viewport-sized block inside the first column.
	 *
	 * @param \DOMElement $el Row child.
	 * @return bool
	 */
	private function is_runtime_layer( \DOMElement $el ): bool {
		foreach ( self::RUNTIME_LAYERS as $class ) {
			if ( $this->has_class( $el, $class ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * `div.pbs-col` → `pbs/column`.
	 *
	 * @param \DOMElement $col Column.
	 * @return string
	 */
	private function column( \DOMElement $col ): string {
		$attrs = $this->common_attrs( $col, 'pbs-col', '' );

		return Serializer::block( 'pbs/column', $attrs, '<div style="' . self::attr( 'flex:1 1 0;min-width:0' . ( isset( $attrs['style'] ) ? ';' . $attrs['style'] : '' ) ) . '">', $this->nodes( iterator_to_array( $col->childNodes ), true ), '</div>' );
	}

	/**
	 * The `style`, `legacyClass` and `legacyData` attributes a row or column carries.
	 *
	 * ⭐ `legacyData` is not in the P1 contract: it keeps the legacy `data-*` behaviour
	 * attributes (per-breakpoint margins, hide-on-phone, video background, AOS animation) and any
	 * non-PBS classes, so nothing the author set is thrown away. A block that does not declare it
	 * simply ignores it; the render layer can adopt it without re-converting anything.
	 *
	 * @param \DOMElement $el    Element.
	 * @param string      $own   The structural class this block replaces.
	 * @param string      $width A row's width ('' for a column, or a row at content width).
	 * @return array
	 */
	private function common_attrs( \DOMElement $el, string $own, string $width ): array {
		$attrs = array();
		// Declarations legacy's row script owned at runtime are not the author's; see style().
		$style = self::style( $el->getAttribute( 'style' ), $width, 'pbs-row' === $own );
		if ( '' !== $style ) {
			$attrs['style'] = $style;
		}
		if ( '' !== $width ) {
			$attrs['width'] = $width;
		}
		$legacy = array();
		$other  = array();
		foreach ( $this->classes( $el ) as $class ) {
			if ( $class === $own ) {
				continue;
			}
			if ( str_starts_with( $class, 'pbs-' ) ) {
				$legacy[] = substr( $class, 4 );
			} elseif ( ! in_array( $class, self::RUNTIME_CLASSES, true ) ) {
				$other[] = $class;
			}
		}
		if ( array() !== $legacy ) {
			$attrs['legacyClass'] = implode( ' ', $legacy );
		}
		$data = $this->data_attributes( $el );
		if ( array() !== $other ) {
			$data['class'] = implode( ' ', $other );
		}
		if ( array() !== $data ) {
			$attrs['legacyData'] = $data;
		}
		// Rendered with legacy's own structural class and compat stylesheet (Core\Render::base()).
		$attrs['legacy'] = true;

		return $attrs;
	}

	/**
	 * The element's `data-*` attributes (minus the ones the conversion itself consumed), keyed
	 * without the `data-` prefix.
	 *
	 * @param \DOMElement $el Element.
	 * @return array<string, string>
	 */
	private function data_attributes( \DOMElement $el ): array {
		$data = array();
		foreach ( $el->attributes as $attr ) {
			$name = strtolower( $attr->nodeName );
			if ( str_starts_with( $name, 'data-' ) && 'data-width' !== $name && ! str_starts_with( $name, 'data-ce-' ) ) {
				$data[ substr( $name, 5 ) ] = $attr->nodeValue;
			}
		}

		return $data;
	}

	/* ─── buttons, widgets, shortcodes ─────────────────────────────────────────────────── */

	/**
	 * `a.pbs-button` → `pbs/button`.
	 *
	 * @param \DOMElement $a     The anchor.
	 * @param string      $align Alignment from the wrapping paragraph.
	 * @param array       $data  `data-*` from the wrapping paragraph (kept on the paragraph).
	 * @param string      $wrap    The rest of the wrapping paragraph's style (margins …).
	 * @param bool        $wrapped Whether the anchor stood in its own paragraph.
	 * @return string
	 */
	private function button_from_anchor( \DOMElement $a, string $align, array $data, string $wrap = '', bool $wrapped = false ): string {
		$legacy = array();
		foreach ( $this->classes( $a ) as $class ) {
			if ( 'pbs-button' !== $class && str_starts_with( $class, 'pbs-' ) ) {
				$legacy[] = substr( $class, 4 );
			}
		}
		// The paragraph's own behaviour settings (per-breakpoint margins, scroll animation) stay
		// on the paragraph: a phone margin that 5.x applied to the <p> moved the layout, and on the
		// link it moved nothing (measured on the law template at 390px).
		return $this->button(
			array(
				'url'         => self::url( $a->getAttribute( 'href' ) ),
				'text'        => $this->inner_html( $a ),
				'style'       => self::style( $a->getAttribute( 'style' ), '' ),
				'target'      => $a->getAttribute( 'target' ),
				'rel'         => $a->getAttribute( 'rel' ),
				'align'       => $align,
				'wrapStyle'   => $wrap,
				'wrapped'     => $wrapped,
				'legacyClass' => implode( ' ', $legacy ),
				'legacyData'  => $this->data_attributes( $a ),
				'wrapData'    => $data,
			)
		);
	}

	/**
	 * `pbs/button` markup: comment attributes plus the static save() fallback.
	 *
	 * @param array $b Button fields.
	 * @return string
	 */
	private function button( array $b ): string {
		$align = in_array( $b['align'], array( 'left', 'center', 'right' ), true ) ? $b['align'] : '';
		$attrs = array_filter(
			array(
				'url'         => $b['url'],
				'text'        => $b['text'],
				'style'       => $b['style'],
				'target'      => $b['target'],
				'rel'         => $b['rel'],
				'align'       => $align,
				'wrapStyle'   => $b['wrapStyle'] ?? '',
				'wrapped'     => ! empty( $b['wrapped'] ) && '' === $align && '' === (string) ( $b['wrapStyle'] ?? '' ),
				'legacyClass' => $b['legacyClass'],
				'legacyData'  => $b['legacyData'],
				'wrapData'    => $b['wrapData'] ?? array(),
				// Legacy's button, legacy's metrics (Core\Render::base()); both sources are legacy.
				'legacy'      => true,
			),
			static fn( $v ) => '' !== $v && array() !== $v && false !== $v
		);

		$a    = '<a'
			. ( '' !== $b['url'] ? ' href="' . self::attr( $b['url'] ) . '"' : '' )
			. ( '' !== $b['style'] ? ' style="' . self::attr( $b['style'] ) . '"' : '' )
			. ( '' !== $b['target'] ? ' target="' . self::attr( $b['target'] ) . '"' : '' )
			. ( '' !== $b['rel'] ? ' rel="' . self::attr( $b['rel'] ) . '"' : '' )
			. '>' . $b['text'] . '</a>';
		$wrap = implode( ';', array_filter( array( '' !== $align ? 'text-align:' . $align : '', (string) ( $b['wrapStyle'] ?? '' ) ) ) );
		$html = '' !== $wrap ? '<p style="' . self::attr( $wrap ) . '">' . $a . '</p>' : ( ! empty( $attrs['wrapped'] ) ? '<p>' . $a . '</p>' : $a );

		return Serializer::block( 'pbs/button', $attrs, $html );
	}

	/**
	 * One shortcode match → blocks.
	 *
	 * @param array $sc A SHORTCODE_RE match.
	 * @return string[]
	 */
	private function shortcode( array $sc ): array {
		$raw = $sc[0];
		if ( '[' === $sc[1] && ']' === ( $sc[6] ?? '' ) ) {
			return array( $this->paragraph( $this->parse( $raw ), '' ) ); // [[escaped]] is text.
		}
		$tag  = strtolower( $sc[2] );
		$atts = self::shortcode_atts( html_entity_decode( $sc[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		if ( 'pbs_button' === $tag ) {
			$label = (string) ( $atts['label'] ?? $atts['text'] ?? '' );
			$color = self::color( (string) ( $atts['color'] ?? $atts['bg_color'] ?? '' ) );
			$text  = self::color( (string) ( $atts['text_color'] ?? '' ) );
			$style = array();
			if ( '' !== $color ) {
				$style[] = 'background-color: ' . $color;
				$style[] = 'border-color: ' . $color;
			}
			if ( '' !== $text ) {
				$style[] = 'color: ' . $text;
			}
			$blank = in_array( strtolower( (string) ( $atts['target'] ?? '' ) ), array( 'true', '1', '_blank', 'yes' ), true );
			return array(
				$this->button(
					array(
						'url'         => self::url( (string) ( $atts['url'] ?? $atts['link'] ?? '' ) ),
						// ⛔ Escaped: legacy's migration printed the label raw (stored XSS, audit §3).
						'text'        => htmlspecialchars( $label, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false ),
						'style'       => implode( '; ', $style ),
						'target'      => $blank ? '_blank' : '',
						'rel'         => $blank ? 'noopener' : '',
						'align'       => strtolower( (string) ( $atts['align'] ?? '' ) ),
						// Legacy's own migration printed it inside a paragraph (class-migration.php).
						'wrapped'     => true,
						'legacyClass' => '',
						'legacyData'  => array(),
					)
				),
			);
		}
		if ( 'pbs_widget' === $tag ) {
			$widget = (string) ( $atts['widget'] ?? 'WP_Widget_Text' ); // Legacy's own default.
			unset( $atts['widget'] );
			if ( 1 !== preg_match( '/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $widget ) ) {
				return array( $this->html_block( $raw ) );
			}
			return array(
				Serializer::block(
					'pbs/widget',
					array(
						'widget'   => $widget,
						'instance' => (object) array_map( 'strval', $atts ),
					)
				),
			);
		}
		if ( 'pbs_sidebar' === $tag ) {
			$id = (string) ( $atts['id'] ?? '' );
			// Legacy rendered nothing for a sidebar with no id, so nothing is lost by dropping it.
			return '' === $id ? array() : array( Serializer::block( 'pbs/sidebar', array( 'sidebar' => $id ) ) );
		}
		if ( function_exists( 'shortcode_exists' ) && ! shortcode_exists( $tag ) && ! str_starts_with( $tag, 'pbs_' ) ) {
			// Not a shortcode on this site: it is text, and stays in a paragraph.
			return array( $this->paragraph( $this->parse( $raw ), '' ) );
		}

		return array( Serializer::block( 'core/shortcode', array(), $raw ) );
	}

	/**
	 * Parses shortcode attributes (WordPress's shortcode_parse_atts(), usable without WordPress).
	 *
	 * @param string $text Attribute text.
	 * @return array<int|string, string>
	 */
	private static function shortcode_atts( string $text ): array {
		if ( function_exists( 'shortcode_parse_atts' ) ) {
			$atts = shortcode_parse_atts( $text );
			return is_array( $atts ) ? array_map( 'strval', $atts ) : array();
		}
		$atts = array();
		$text = (string) preg_replace( "/[\x{00a0}\x{200b}]+/u", ' ', $text );
		if ( preg_match_all( '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/', $text, $match, PREG_SET_ORDER ) ) {
			foreach ( $match as $m ) {
				if ( ! empty( $m[1] ) ) {
					$atts[ strtolower( $m[1] ) ] = stripcslashes( $m[2] );
				} elseif ( ! empty( $m[3] ) ) {
					$atts[ strtolower( $m[3] ) ] = stripcslashes( $m[4] );
				} elseif ( ! empty( $m[5] ) ) {
					$atts[ strtolower( $m[5] ) ] = stripcslashes( $m[6] );
				} elseif ( isset( $m[7] ) && '' !== $m[7] ) {
					$atts[] = stripcslashes( $m[7] );
				} elseif ( isset( $m[8] ) && '' !== $m[8] ) {
					$atts[] = stripcslashes( $m[8] );
				} elseif ( isset( $m[9] ) ) {
					$atts[] = stripcslashes( $m[9] );
				}
			}
		}

		return $atts;
	}

	/* ─── values ───────────────────────────────────────────────────────────────────────── */

	/**
	 * Sanitized CSS declarations, normalized to `prop: value; prop: value`.
	 *
	 * Uses the plugin's `Core\Css::sanitize_declarations()` when it is loaded (the one policy
	 * for every style the plugin prints), else an equivalent local allow-list.
	 *
	 * ⛔ Legacy's editor SAVED what its full-width script had computed for the editor's own
	 * viewport — `width: 1438px; max-width: 1438px; margin-left: 0px; left: -99px;
	 * padding-left: 99px` — and the script overwrote all of it on every page view. Kept, those
	 * pixel values pin the row to one screen size (measured: a 1438px row on a 390px phone), so a
	 * full-width row drops every declaration the script owned; the `width` attribute and the
	 * stylesheet do that job for any viewport.
	 *
	 * Every other row went through `_pbsRowReset()` on every page view, which cleared its inline
	 * `width`, `max-width`, `position`, `left`/`right` and `transform`: a row never showed them, so
	 * a converted row does not either.
	 *
	 * @param string $css   A style attribute's value.
	 * @param string $width The row's legacy `data-width` ('' when not a full-width row).
	 * @param bool   $row   Whether the element is a legacy row.
	 * @return string
	 */
	public static function style( string $css, string $width = '', bool $row = false ): string {
		$owned = array();
		if ( $row ) {
			// _pbsRowReset(): every row without a (top-level) width lost these on every page view.
			$owned = array( 'left', 'right', 'width', 'max-width', 'position', 'transform', '-webkit-transform', '-moz-transform', '-ms-transform' );
		}
		if ( '' !== $width ) {
			// _pbsFullWidthRow(): the script set all of these (transform it restored).
			$owned = array( 'left', 'right', 'width', 'max-width', 'position', 'margin-left', 'margin-right' );
			if ( 'full-width-retain-content' === $width ) {
				array_push( $owned, 'padding-left', 'padding-right' );
			}
		}
		$core = '\\ZinnDigital\\PBS\\Core\\Css';
		if ( class_exists( $core ) && method_exists( $core, 'sanitize_declarations' ) ) {
			$css = (string) $core::sanitize_declarations( $css );
		}
		$out = array();
		foreach ( self::declarations( $css ) as $prop => $value ) {
			// A shorthand that sets a side the script owned keeps only its other sides (a full-width
			// row's `margin: 0px` would otherwise pin the row against the breakout).
			if ( in_array( $prop, array( 'margin', 'padding' ), true ) && in_array( $prop . '-left', $owned, true ) ) {
				$sides = preg_split( '/\s+/', trim( $value ) );
				$sides = false === $sides ? array() : $sides;
				if ( count( $sides ) >= 1 && count( $sides ) <= 4 && self::safe_declaration( $prop, $value ) ) {
					$out[] = $prop . '-top: ' . $sides[0];
					$out[] = $prop . '-bottom: ' . $sides[ count( $sides ) >= 3 ? 2 : 0 ];
				}
				continue;
			}
			if ( in_array( $prop, $owned, true ) || str_contains( $prop, 'padding-padding' ) ) {
				continue;
			}
			if ( self::safe_declaration( $prop, $value ) ) {
				$out[] = $prop . ': ' . $value;
			}
		}

		return implode( '; ', $out );
	}

	/**
	 * Parses declarations (semicolons inside quotes or parentheses do not split).
	 *
	 * @param string $css Declarations.
	 * @return array<string, string> Property (lower-case) ⇒ value; a later duplicate wins.
	 */
	public static function declarations( string $css ): array {
		$out   = array();
		$buf   = '';
		$depth = 0;
		$quote = '';
		$len   = strlen( $css );
		for ( $i = 0; $i <= $len; $i++ ) {
			$c = $i < $len ? $css[ $i ] : ';';
			if ( '' !== $quote ) {
				$quote = $c === $quote ? '' : $quote;
			} elseif ( '"' === $c || "'" === $c ) {
				$quote = $c;
			} elseif ( '(' === $c ) {
				++$depth;
			} elseif ( ')' === $c && $depth > 0 ) {
				--$depth;
			} elseif ( ';' === $c && 0 === $depth ) {
				$pair = explode( ':', $buf, 2 );
				$buf  = '';
				if ( 2 === count( $pair ) ) {
					$prop  = strtolower( trim( $pair[0] ) );
					$value = trim( (string) preg_replace( '/\s+/', ' ', $pair[1] ) );
					if ( '' !== $prop && '' !== $value ) {
						$out[ $prop ] = $value;
					}
				}
				continue;
			}
			$buf .= $c;
		}

		return $out;
	}

	/**
	 * The local declaration policy: a plain property name, and a value that cannot run script,
	 * escape the attribute, or load anything but an http(s)/relative image.
	 *
	 * @param string $prop  Property.
	 * @param string $value Value.
	 * @return bool
	 */
	private static function safe_declaration( string $prop, string $value ): bool {
		if ( 1 !== preg_match( '/^(?:--[a-z0-9-]+|-?[a-z][a-z-]*)$/', $prop ) ) {
			return false;
		}
		if ( 1 === preg_match( '/[<>\\\\]|\/\*|expression\s*\(|javascript:|vbscript:|-moz-binding|behavior\s*:|@import/i', $value ) ) {
			return false;
		}
		if ( preg_match_all( '/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $value, $urls ) ) {
			foreach ( $urls[2] as $url ) {
				if ( 1 !== preg_match( '#^(?:https?://|/(?!/)|\.{0,2}/|[\w-]+\.(?:png|jpe?g|gif|webp|avif)$)#i', $url ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * A link, or '' when its scheme is not one WordPress allows (javascript:, data:, …).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function url( string $url ): string {
		$url = trim( $url );
		if ( 1 === preg_match( '/^([a-z][a-z0-9+.-]*):/i', $url, $scheme ) ) {
			$allowed = function_exists( 'wp_allowed_protocols' ) ? wp_allowed_protocols() : array( 'http', 'https', 'mailto', 'tel', 'ftp', 'sms' );
			if ( ! in_array( strtolower( $scheme[1] ), $allowed, true ) ) {
				return '';
			}
		}

		return $url;
	}

	/**
	 * A colour value from a shortcode attribute, or ''.
	 *
	 * @param string $color Value.
	 * @return string
	 */
	private static function color( string $color ): string {
		$color = trim( $color );

		return 1 === preg_match( '/^(?:#[0-9a-f]{3,8}|rgba?\([\d\s.,%]+\)|[a-z]{3,20})$/i', $color ) ? $color : '';
	}

	/**
	 * Attribute-value escaping (the same five characters React escapes).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function attr( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
	}

	/* ─── DOM helpers ──────────────────────────────────────────────────────────────────── */

	/**
	 * A node's HTML, with a non-breaking space written as the entity the classic editor uses.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	private function html( \DOMNode $node ): string {
		$html = (string) $node->ownerDocument->saveHTML( $node );

		return str_replace( "\u{00a0}", '&nbsp;', $html );
	}

	/**
	 * An element's inner HTML.
	 *
	 * @param \DOMNode $el Element.
	 * @return string
	 */
	private function inner_html( \DOMNode $el ): string {
		$html = '';
		foreach ( $el->childNodes as $child ) {
			$html .= $this->html( $child );
		}

		return $html;
	}

	/**
	 * The single element child when the element holds nothing else but whitespace.
	 *
	 * @param \DOMNode $el Element.
	 * @return \DOMElement|null
	 */
	private function only_element( \DOMNode $el ): ?\DOMElement {
		$found = null;
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof \DOMText && '' === trim( str_replace( "\u{00a0}", ' ', $child->data ) ) ) {
				continue;
			}
			if ( ! $child instanceof \DOMElement || null !== $found ) {
				return null;
			}
			$found = $child;
		}

		return $found;
	}

	/**
	 * Whether any node in a list has content (an element, or non-whitespace text).
	 *
	 * @param \DOMNode[] $nodes Nodes.
	 * @return bool
	 */
	private function has_content( array $nodes ): bool {
		foreach ( $nodes as $node ) {
			if ( $node instanceof \DOMElement || ( $node instanceof \DOMText && '' !== trim( $node->data ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an element has an element child.
	 *
	 * @param \DOMNode $el Element.
	 * @return bool
	 */
	private function has_element_child( \DOMNode $el ): bool {
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an element carries no attributes at all.
	 *
	 * @param \DOMElement $el Element.
	 * @return bool
	 */
	private function bare( \DOMElement $el ): bool {
		return ! $el->hasAttributes();
	}

	/**
	 * Whether every descendant is plain rich text a core text block saves unchanged, and nothing
	 * in it is legacy markup that must reach the render-time rewrite.
	 *
	 * @param \DOMNode $el Element.
	 * @return bool
	 */
	private function is_rich_text( \DOMNode $el ): bool {
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof \DOMText ) {
				if ( str_contains( $child->data, '[pbs_' ) ) {
					return false;
				}
				continue;
			}
			if ( ! $child instanceof \DOMElement ) {
				return false;
			}
			$tag = strtolower( $child->tagName );
			if ( ! isset( self::RICH_TEXT_TAGS[ $tag ] ) ) {
				return false;
			}
			foreach ( $child->attributes as $attr ) {
				if ( ! in_array( strtolower( $attr->nodeName ), self::RICH_TEXT_TAGS[ $tag ], true ) ) {
					return false;
				}
			}
			if ( ! $this->is_rich_text( $child ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * An element's classes.
	 *
	 * @param \DOMElement $el Element.
	 * @return string[]
	 */
	private function classes( \DOMElement $el ): array {
		return array_values( array_filter( preg_split( '/\s+/', trim( $el->getAttribute( 'class' ) ) ) ) );
	}

	/**
	 * Whether an element has a class.
	 *
	 * @param \DOMElement $el   Element.
	 * @param string      $name Class.
	 * @return bool
	 */
	private function has_class( \DOMElement $el, string $name ): bool {
		return in_array( $name, $this->classes( $el ), true );
	}

	/**
	 * Replaces an element's classes (removing the attribute when none remain).
	 *
	 * @param \DOMElement $el      Element.
	 * @param string[]    $classes Classes.
	 * @return void
	 */
	private function set_classes( \DOMElement $el, array $classes ): void {
		if ( array() === $classes ) {
			$el->removeAttribute( 'class' );
		} else {
			$el->setAttribute( 'class', implode( ' ', $classes ) );
		}
	}
}
