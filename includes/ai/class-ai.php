<?php
/**
 * AI inside the builder (P12): the shared plumbing every AI feature uses.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One way to call the AI core, turn its failure into an error, and keep a model answer from ever
 * adding markup that the user could not have typed.
 *
 * Every request runs on the SITE OWNER's own provider and key (Settings → AI providers), under the
 * AI core's spending limits and, in Pro, its "which roles may use AI" rule. Nothing here chooses a
 * provider or holds a key.
 */
final class Ai {

	/** The AI core's client inside this plugin. */
	public const CLIENT = '\\ZinnDigital\\PBS\\AiCore\\Client';

	/** The inline tags a rewritten piece of text may keep (the ones a text block holds). */
	public const INLINE_TAGS = array(
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'u'      => array(),
		's'      => array(),
		'code'   => array(),
		'mark'   => array(),
		'sub'    => array(),
		'sup'    => array(),
		'br'     => array(),
	);

	/**
	 * Is the AI core loaded (it always is in a full build)?
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( self::CLIENT );
	}

	/**
	 * Is an AI provider connected?
	 *
	 * @return bool
	 */
	public static function ready(): bool {
		$client = self::CLIENT;

		return self::available() && $client::ready();
	}

	/**
	 * Where the site owner sets AI up.
	 *
	 * @return string
	 */
	public static function settings_url(): string {
		return admin_url( 'options-general.php?page=zinn-ai' );
	}

	/**
	 * Ask the model for structured data.
	 *
	 * @param string               $system  The instruction.
	 * @param mixed                $payload What the model works on (sent as JSON), or a list of
	 *                                      message parts (text and images).
	 * @param array<string, mixed> $schema  JSON schema of the answer.
	 * @param array<string, mixed> $options `purpose`, `task` (default `write`), `temperature`,
	 *                                      `schema_name`, `max_tokens`.
	 * @return array<string, mixed>|\WP_Error The decoded answer.
	 */
	public static function ask( string $system, $payload, array $schema, array $options = array() ) {
		if ( ! self::available() ) {
			return new \WP_Error( 'pbsw_no_ai', __( 'AI is not available in this build.', 'page-builder-sandwich' ), array( 'status' => 501 ) );
		}
		$content = is_array( $payload ) && isset( $payload[0]['type'] )
			? $payload
			: (string) wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$client  = self::CLIENT;
		$result  = $client::generate(
			array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $content,
				),
			),
			array(
				'task'        => (string) ( $options['task'] ?? 'write' ),
				'purpose'     => (string) ( $options['purpose'] ?? 'pbs-ai' ),
				'schema'      => $schema,
				'schema_name' => (string) ( $options['schema_name'] ?? 'answer' ),
				'temperature' => (float) ( $options['temperature'] ?? 0.4 ),
			) + ( isset( $options['max_tokens'] ) ? array( 'max_tokens' => (int) $options['max_tokens'] ) : array() )
		);
		if ( ! $result->ok() ) {
			return self::error( $result );
		}
		if ( ! is_array( $result->data ) ) {
			return new \WP_Error( 'pbsw_ai_unreadable', __( 'The AI answered in a form the builder cannot read. Try again, or choose another model in the AI settings.', 'page-builder-sandwich' ), array( 'status' => 502 ) );
		}

		return $result->data;
	}

	/**
	 * The AI core's failure as an error: what went wrong, whose problem it is and where to fix it.
	 *
	 * @param object $result The core's Result.
	 * @return \WP_Error
	 */
	public static function error( $result ): \WP_Error {
		$failure = $result->failure ?? null;
		$message = is_object( $failure ) && '' !== (string) ( $failure->message ?? '' ) ? (string) $failure->message : __( 'The AI request failed. Try again.', 'page-builder-sandwich' );

		return new \WP_Error(
			'pbsw_ai_failed',
			$message,
			array(
				'status' => 502,
				// Provider outage, key or billing problem, or refused by the site's own AI rules:
				// the editor and an agent can tell the user whose problem it is.
				'kind'   => is_object( $failure ) ? (string) ( $failure->kind ?? '' ) : '',
				'link'   => is_object( $failure ) ? (string) ( $failure->link ?? '' ) : '',
			)
		);
	}

	/**
	 * Clean a model's rewrite of a piece of text: only the inline tags a text block holds survive,
	 * links keep only http(s)/mailto/tel and relative targets.
	 *
	 * @param string $html The model's text.
	 * @return string
	 */
	public static function inline( string $html ): string {
		$clean = wp_kses( $html, self::INLINE_TAGS, array( 'http', 'https', 'mailto', 'tel' ) );

		return trim( (string) $clean );
	}

	/**
	 * Split block markup into text (even indexes) and tags or HTML comments (odd indexes).
	 *
	 * @param string $markup Markup.
	 * @return array<int, string>
	 */
	public static function split( string $markup ): array {
		$parts = preg_split( '/(<!--.*?-->|<[^>]*>)/s', $markup, -1, PREG_SPLIT_DELIM_CAPTURE );

		return is_array( $parts ) ? $parts : array( $markup );
	}

	/**
	 * The visible texts of some markup, keyed by part index.
	 *
	 * @param string $markup Block markup.
	 * @return array{0: array<int, string>, 1: array<string, string>}
	 */
	public static function pieces( string $markup ): array {
		$parts = self::split( $markup );
		$texts = array();
		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 ) {
				continue;
			}
			$text = html_entity_decode( $part, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( '' !== trim( $text ) ) {
				$texts[ (string) $i ] = trim( $text );
			}
		}

		return array( $parts, $texts );
	}

	/**
	 * Rewrite every visible piece of text in some block markup for a brief, keeping the design: every
	 * tag, block comment, setting and URL stays as it was. All or nothing.
	 *
	 * ⛔ Text lives in TWO places in many blocks: an attribute (a testimonial's name and role, an
	 * accordion item's title) AND the saved HTML the editor rebuilds from those attributes (here
	 * "Helen M., Homeowner" from `name` + `role`). Rewriting only the HTML makes the block "invalid"
	 * in the editor. So, per block: a text attribute that appears in the block's own HTML is ONE
	 * unit, changed in the attribute and replaced where it appears in that HTML; the remaining
	 * text of the HTML (core blocks keep their text only there) is rewritten piece by piece.
	 *
	 * @param string $markup  Block markup.
	 * @param string $brief   What the text should say.
	 * @param string $purpose Usage-log purpose.
	 * @return string|\WP_Error
	 */
	public static function rewrite_markup( string $markup, string $brief, string $purpose ) {
		$blocks = parse_blocks( $markup );
		$units  = array();
		self::collect_units( $blocks, '', $units );
		if ( array() === $units ) {
			return $markup;
		}
		$texts = array();
		foreach ( $units as $key => $unit ) {
			$texts[ $key ] = $unit['text'];
		}
		// Models sometimes drop an item from a long list: ask once more for exactly the ones that
		// are missing; then it is all or nothing.
		$answers = array();
		$total   = count( $texts );
		for ( $round = 0; $round < 2; $round++ ) {
			if ( count( $answers ) === $total ) {
				break;
			}
			$items = array();
			foreach ( $texts as $key => $text ) {
				if ( ! isset( $answers[ $key ] ) ) {
					$items[] = array(
						'key'  => (string) $key,
						'text' => $text,
					);
				}
			}
			$data = self::rewrite_items( $items, $brief, $purpose );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			$answers += self::answers( $texts, $data );
		}
		if ( count( $answers ) !== count( $texts ) ) {
			return new \WP_Error(
				'pbsw_ai_incomplete',
				/* translators: 1: pieces of text rewritten, 2: pieces of text in the section. */
				sprintf( __( 'The AI rewrote %1$d of %2$d pieces of text, so nothing was changed. Try again.', 'page-builder-sandwich' ), count( $answers ), count( $texts ) ),
				array( 'status' => 502 )
			);
		}
		$new = array();
		foreach ( $answers as $key => $text ) {
			$new[ (string) $key ] = esc_html( trim( wp_strip_all_tags( $text ) ) );
		}

		return serialize_blocks( self::apply_units( $blocks, '', $units, $new ) );
	}

	/**
	 * The units of text of a block tree: `b<path>:a<attr path>` (a text attribute shown in the
	 * block's own HTML) and `b<path>:c<chunk>:<part>` (a piece of the block's own HTML).
	 *
	 * @param array<int, array<string, mixed>>    $blocks Blocks.
	 * @param string                              $prefix Path.
	 * @param array<string, array<string, mixed>> $units Units (filled).
	 * @return void
	 */
	private static function collect_units( array $blocks, string $prefix, array &$units ): void {
		foreach ( $blocks as $i => $block ) {
			if ( null === ( $block['blockName'] ?? null ) ) {
				continue;
			}
			$path  = $prefix . '.' . $i;
			$html  = implode( '', array_filter( (array) $block['innerContent'], 'is_string' ) );
			$found = array();
			foreach ( self::string_leaves( (array) $block['attrs'] ) as $attr_path => $value ) {
				$plain = trim( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( mb_strlen( $plain ) >= 2 && wp_strip_all_tags( $value ) === $value && ! preg_match( '#^(https?:|/|\#|[\w-]+$)#', $plain ) && str_contains( $html, $value ) ) {
					$units[ 'b' . $path . ':a' . $attr_path ] = array(
						'type'  => 'attr',
						'value' => $value,
						'text'  => $plain,
					);
					$found[]                                  = $value;
				}
			}
			foreach ( (array) $block['innerContent'] as $c => $chunk ) {
				if ( ! is_string( $chunk ) ) {
					continue;
				}
				foreach ( self::split( $chunk ) as $n => $part ) {
					if ( 1 === $n % 2 ) {
						continue;
					}
					$text = trim( html_entity_decode( $part, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
					if ( '' === $text ) {
						continue;
					}
					$covered = false;
					foreach ( $found as $value ) {
						if ( str_contains( $part, $value ) ) {
							$covered = true;
							break;
						}
					}
					if ( ! $covered ) {
						$units[ 'b' . $path . ':c' . $c . ':' . $n ] = array(
							'type' => 'piece',
							'text' => $text,
						);
					}
				}
			}
			self::collect_units( (array) $block['innerBlocks'], $path, $units );
		}
	}

	/**
	 * Put the new texts into the block tree (attributes and the block's own HTML).
	 *
	 * @param array<int, array<string, mixed>>    $blocks Blocks.
	 * @param string                              $prefix Path.
	 * @param array<string, array<string, mixed>> $units  Units.
	 * @param array<string, string>               $replace New text per unit (escaped).
	 * @return array<int, array<string, mixed>>
	 */
	private static function apply_units( array $blocks, string $prefix, array $units, array $replace ): array {
		foreach ( $blocks as $i => $block ) {
			if ( null === ( $block['blockName'] ?? null ) ) {
				continue;
			}
			$path = $prefix . '.' . $i;
			$swap = array();
			foreach ( $units as $key => $unit ) {
				if ( 'attr' === $unit['type'] && str_starts_with( $key, 'b' . $path . ':a' ) ) {
					$block['attrs']         = self::set_leaf( (array) $block['attrs'], substr( $key, strlen( 'b' . $path . ':a' ) ), $replace[ $key ] );
					$swap[ $unit['value'] ] = $replace[ $key ];
				}
			}
			foreach ( (array) $block['innerContent'] as $c => $chunk ) {
				if ( ! is_string( $chunk ) ) {
					continue;
				}
				$parts = self::split( $chunk );
				foreach ( $parts as $n => $part ) {
					$key = 'b' . $path . ':c' . $c . ':' . $n;
					if ( isset( $replace[ $key ] ) ) {
						$lead        = (string) preg_replace( '/\S.*$/s', '', $part );
						$tail        = (string) preg_replace( '/^.*\S/s', '', $part );
						$parts[ $n ] = $lead . $replace[ $key ] . $tail;
					} elseif ( 0 === $n % 2 && array() !== $swap ) {
						$parts[ $n ] = strtr( $part, $swap );
					}
				}
				$block['innerContent'][ $c ] = implode( '', $parts );
			}
			$block['innerHTML']   = implode( '', array_filter( (array) $block['innerContent'], 'is_string' ) );
			$block['innerBlocks'] = self::apply_units( (array) $block['innerBlocks'], $path, $units, $replace );
			$blocks[ $i ]         = $block;
		}

		return $blocks;
	}

	/**
	 * Every string leaf of an attribute tree, by path ("title", "items/2/question").
	 *
	 * @param array<mixed> $attrs  Attributes.
	 * @param string       $prefix Path.
	 * @return array<string, string>
	 */
	private static function string_leaves( array $attrs, string $prefix = '' ): array {
		$out = array();
		foreach ( $attrs as $k => $v ) {
			$p = '' === $prefix ? (string) $k : $prefix . '/' . $k;
			if ( is_string( $v ) ) {
				$out[ $p ] = $v;
			} elseif ( is_array( $v ) ) {
				$out += self::string_leaves( $v, $p );
			}
		}

		return $out;
	}

	/**
	 * Set a leaf of an attribute tree by its path.
	 *
	 * @param array<mixed> $attrs Attributes.
	 * @param string       $path  Path.
	 * @param string       $value Value.
	 * @return array<mixed>
	 */
	private static function set_leaf( array $attrs, string $path, string $value ): array {
		$keys = explode( '/', $path );
		$key  = array_shift( $keys );
		if ( array() === $keys ) {
			$attrs[ $key ] = $value;
		} elseif ( isset( $attrs[ $key ] ) && is_array( $attrs[ $key ] ) ) {
			$attrs[ $key ] = self::set_leaf( $attrs[ $key ], implode( '/', $keys ), $value );
		}

		return $attrs;
	}

	/**
	 * One rewrite request for some pieces of text.
	 *
	 * @param array<int, array{key: string, text: string}> $items   Pieces.
	 * @param string                                       $brief   Brief.
	 * @param string                                       $purpose Usage-log purpose.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function rewrite_items( array $items, string $brief, string $purpose ) {
		return self::ask(
			'You are a website copywriter. Each item is one piece of text from a web page section, in order (eyebrows, headings, paragraphs, button labels, list items, names, questions and answers). Rewrite the "text" of every item for the brief: keep each item\'s role and roughly its length (a button label stays a short label, a name stays a name), write in the language of the brief, invent no prices, awards, statistics or real people\'s names the brief does not give. Return EVERY item, each with its "key" copied exactly.',
			array(
				'brief' => $brief,
				'items' => $items,
			),
			array(
				'type'                 => 'object',
				'properties'           => array(
					'items' => array(
						'type'  => 'array',
						'items' => array(
							'type'                 => 'object',
							'properties'           => array(
								'key'  => array( 'type' => 'string' ),
								'text' => array( 'type' => 'string' ),
							),
							'required'             => array( 'key', 'text' ),
							'additionalProperties' => false,
						),
					),
				),
				'required'             => array( 'items' ),
				'additionalProperties' => false,
			),
			array(
				'purpose'     => $purpose,
				'schema_name' => 'section_copy',
				'temperature' => 0.6,
			)
		);
	}

	/**
	 * The model's answers by unit key (a key that echoes the old text is accepted too).
	 *
	 * @param array<string, string> $texts Old texts by key.
	 * @param array<string, mixed>  $data  The model's answer.
	 * @return array<string, string>
	 */
	private static function answers( array $texts, array $data ): array {
		$by_text = array();
		foreach ( $texts as $key => $text ) {
			$by_text[ $text ] = $by_text[ $text ] ?? (string) $key;
		}
		$out = array();
		foreach ( (array) ( $data['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['key'], $item['text'] ) || '' === trim( (string) $item['text'] ) ) {
				continue;
			}
			$key = (string) $item['key'];
			$key = isset( $texts[ $key ] ) ? $key : (string) ( $by_text[ trim( $key ) ] ?? '' );
			if ( '' !== $key && ! isset( $out[ $key ] ) ) {
				$out[ $key ] = (string) $item['text'];
			}
		}

		return $out;
	}

	/**
	 * Is this block markup safe to save: every block a registered block type, and (for a user
	 * without unfiltered_html) nothing that kses would strip?
	 *
	 * @param string $markup Block markup.
	 * @return true|\WP_Error
	 */
	public static function validate_markup( string $markup ) {
		$registry = \WP_Block_Type_Registry::get_instance();
		$unknown  = array();
		$walk     = static function ( array $blocks ) use ( &$walk, $registry, &$unknown ): void {
			foreach ( $blocks as $block ) {
				$name = $block['blockName'] ?? null;
				if ( null === $name ) {
					if ( '' !== trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) ) ) {
						$unknown[] = 'freeform';
					}
				} elseif ( ! $registry->is_registered( (string) $name ) ) {
					$unknown[] = (string) $name;
				}
				$walk( (array) ( $block['innerBlocks'] ?? array() ) );
			}
		};
		$walk( parse_blocks( $markup ) );
		if ( array() !== $unknown ) {
			return new \WP_Error(
				'pbsw_ai_bad_blocks',
				/* translators: %s: block names. */
				sprintf( __( 'The AI used blocks this site does not have (%s), so nothing was changed. Try again.', 'page-builder-sandwich' ), implode( ', ', array_unique( $unknown ) ) ),
				array( 'status' => 502 )
			);
		}
		if ( ! current_user_can( 'unfiltered_html' ) && wp_kses_post( $markup ) !== $markup ) {
			return new \WP_Error( 'pbsw_ai_unsafe', __( 'The AI answer contained markup your account may not save, so nothing was changed.', 'page-builder-sandwich' ), array( 'status' => 502 ) );
		}

		return true;
	}

	/**
	 * Read a post the current user may edit.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	public static function editable_post( int $id ) {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $id ) ) {
			return new \WP_Error( 'pbsw_not_found', __( 'There is no such post, or you may not edit it.', 'page-builder-sandwich' ), array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * Save new content for a post as a normal update, so WordPress keeps a revision of what was
	 * there before (every AI change can be undone from the revisions screen or with the returned
	 * revision id).
	 *
	 * @param \WP_Post $post    Post.
	 * @param string   $content New content.
	 * @return array{id: int, undo_revision: int}|\WP_Error
	 */
	public static function save_content( \WP_Post $post, string $content ) {
		$before = self::snapshot( $post );
		$saved  = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => wp_slash( $content ),
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'id'            => (int) $post->ID,
			'undo_revision' => $before,
		);
	}

	/**
	 * A revision holding the post as it is now (made when the newest revision does not already
	 * hold exactly this content), so an AI change always has something to go back to.
	 *
	 * @param \WP_Post $post Post.
	 * @return int Revision ID, 0 when revisions are off for this post type.
	 */
	public static function snapshot( \WP_Post $post ): int {
		if ( ! wp_revisions_enabled( $post ) ) {
			return 0;
		}
		$latest = wp_get_post_revisions(
			$post->ID,
			array(
				'numberposts' => 1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			)
		);
		$latest = reset( $latest );
		if ( $latest instanceof \WP_Post && $latest->post_content === $post->post_content && $latest->post_title === $post->post_title ) {
			return (int) $latest->ID;
		}
		$id = _wp_put_post_revision( $post );

		return is_int( $id ) ? $id : 0;
	}

	/**
	 * Undo an AI change: restore the revision it returned.
	 *
	 * @param int $post_id     Post.
	 * @param int $revision_id Revision.
	 * @return array{id: int, restored: int}|\WP_Error
	 */
	public static function undo( int $post_id, int $revision_id ) {
		$post = self::editable_post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$revision = wp_get_post_revision( $revision_id );
		if ( ! $revision instanceof \WP_Post || (int) $revision->post_parent !== $post_id ) {
			return new \WP_Error( 'pbsw_no_revision', __( 'That change can no longer be undone: its saved copy is gone.', 'page-builder-sandwich' ), array( 'status' => 404 ) );
		}
		$restored = wp_restore_post_revision( $revision_id );
		if ( ! $restored ) {
			return new \WP_Error( 'pbsw_undo_failed', __( 'The change could not be undone. Restore it from the post\'s revisions instead.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
		}

		return array(
			'id'       => $post_id,
			'restored' => $revision_id,
		);
	}
}
