<?php
/**
 * AI writing (P12, free): write and rewrite text (pbs-ai1) and a section from a sentence (pbs-ai2).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Ai;

use ZinnDigital\PBS\Mcp\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The writing helpers behind the editor's AI menu, the REST routes and the MCP abilities.
 */
final class Writer {

	/** What the text menu can do. */
	public const ACTIONS = array( 'write', 'improve', 'shorten', 'expand', 'tone', 'fix', 'custom' );

	/** Tones offered for `tone`. */
	public const TONES = array( 'professional', 'friendly', 'confident', 'playful', 'formal', 'simple' );

	/** Longest text one request rewrites. */
	public const MAX_TEXT = 8000;

	/**
	 * Write or rewrite one piece of text (pbs-ai1).
	 *
	 * @param array<string, mixed> $input `text`, `action`, `tone`, `instruction`, `context`.
	 * @return array{text: string, action: string}|\WP_Error
	 */
	public static function text( array $input ) {
		$action      = (string) ( $input['action'] ?? 'improve' );
		$text        = trim( (string) ( $input['text'] ?? '' ) );
		$instruction = trim( (string) ( $input['instruction'] ?? '' ) );
		$tone        = (string) ( $input['tone'] ?? '' );
		$context     = trim( (string) ( $input['context'] ?? '' ) );
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return new \WP_Error( 'pbsw_ai_action', __( 'Unknown AI writing action.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		if ( 'write' !== $action && '' === $text ) {
			return new \WP_Error( 'pbsw_ai_no_text', __( 'There is no text to rewrite. Type some first, or ask AI to write it.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		if ( in_array( $action, array( 'write', 'custom' ), true ) && mb_strlen( $instruction ) < 3 ) {
			return new \WP_Error( 'pbsw_ai_no_instruction', __( 'Say what the text should be about.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		if ( 'tone' === $action && ! in_array( $tone, self::TONES, true ) ) {
			return new \WP_Error( 'pbsw_ai_tone', __( 'Choose one of the offered tones.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $text ) > self::MAX_TEXT || mb_strlen( $instruction ) > 2000 || mb_strlen( $context ) > 4000 ) {
			return new \WP_Error( 'pbsw_ai_too_long', __( 'That is too much text for one AI request. Select a shorter part.', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$tasks = array(
			'write'   => 'Write new text for a web page as the instruction asks.',
			'improve' => 'Improve the text: clearer, more engaging, same meaning and about the same length.',
			'shorten' => 'Make the text about half as long, keeping its meaning.',
			'expand'  => 'Make the text about twice as long, adding useful detail without inventing facts, prices or figures.',
			'tone'    => 'Rewrite the text in a ' . $tone . ' tone, keeping its meaning.',
			'fix'     => 'Fix spelling, grammar and punctuation only; change nothing else.',
			'custom'  => 'Rewrite the text as the instruction asks.',
		);
		$data  = Ai::ask(
			'You edit text on a web page. ' . $tasks[ $action ] . ' Keep the language of the text (or of the instruction when there is no text). The text may contain inline HTML (links, bold, italics): keep those tags and their attributes where the words they wrap remain. Return only the new text, no quotes and no explanation.',
			array(
				'text'        => $text,
				'instruction' => $instruction,
				'page'        => $context,
			),
			array(
				'type'                 => 'object',
				'properties'           => array( 'text' => array( 'type' => 'string' ) ),
				'required'             => array( 'text' ),
				'additionalProperties' => false,
			),
			array(
				'purpose'     => 'pbs-ai-text',
				'schema_name' => 'text',
				'temperature' => 'fix' === $action ? 0.0 : 0.7,
			)
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = Ai::inline( (string) ( $data['text'] ?? '' ) );
		if ( '' === $out ) {
			return new \WP_Error( 'pbsw_ai_empty', __( 'The AI returned no text. Try again.', 'page-builder-sandwich' ), array( 'status' => 502 ) );
		}

		return array(
			'text'   => $out,
			'action' => $action,
		);
	}

	/**
	 * A section from a sentence (pbs-ai2): the model picks the best section from the section
	 * library for the description and writes its text; the design (blocks, styles, the brand kit)
	 * is the library's, so the result is always real, editable blocks.
	 *
	 * @param array<string, mixed> $input `description`, optional `section` (a library name),
	 *                                    `post_id` + `position` (`end`|`start`) to add it to a page.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function section( array $input ) {
		$description = trim( (string) ( $input['description'] ?? '' ) );
		if ( mb_strlen( $description ) < 5 || mb_strlen( $description ) > 2000 ) {
			return new \WP_Error( 'pbsw_ai_no_description', __( 'Describe the section in a sentence (up to 2,000 characters).', 'page-builder-sandwich' ), array( 'status' => 400 ) );
		}
		$library = Abilities::sections();
		if ( array() === $library ) {
			return new \WP_Error( 'pbsw_no_sections', __( 'There are no sections in the library to build from.', 'page-builder-sandwich' ), array( 'status' => 500 ) );
		}
		$name = (string) ( $input['section'] ?? '' );
		if ( '' !== $name && ! isset( $library[ $name ] ) ) {
			/* translators: %s: a section name. */
			return new \WP_Error( 'pbsw_unknown_section', sprintf( __( 'There is no section called "%s".', 'page-builder-sandwich' ), $name ), array( 'status' => 400 ) );
		}
		if ( '' === $name ) {
			$choices = array();
			foreach ( $library as $key => $pattern ) {
				$choices[] = array(
					'name'        => $key,
					'title'       => (string) $pattern['title'],
					'description' => (string) ( $pattern['description'] ?? '' ),
					'keywords'    => array_values( array_map( 'strval', (array) ( $pattern['keywords'] ?? array() ) ) ),
				);
			}
			$data = Ai::ask(
				'You choose ONE ready-made web page section from a library that best fits the description. Answer with its exact "name" from the library.',
				array(
					'description' => $description,
					'library'     => $choices,
				),
				array(
					'type'                 => 'object',
					'properties'           => array( 'name' => array( 'type' => 'string' ) ),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				array(
					'purpose'     => 'pbs-ai-section',
					'schema_name' => 'section_choice',
					'temperature' => 0.2,
				)
			);
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			$name = (string) ( $data['name'] ?? '' );
			if ( ! isset( $library[ $name ] ) ) {
				return new \WP_Error( 'pbsw_ai_no_sections', __( 'The AI did not choose a section from the library. Pick one yourself, or describe it differently.', 'page-builder-sandwich' ), array( 'status' => 502 ) );
			}
		}
		$markup = Ai::rewrite_markup( (string) $library[ $name ]['content'], $description, 'pbs-ai-section' );
		if ( is_wp_error( $markup ) ) {
			return $markup;
		}
		$out     = array(
			'section' => $name,
			'content' => $markup,
		);
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( $post_id > 0 ) {
			$post = Ai::editable_post( $post_id );
			if ( is_wp_error( $post ) ) {
				return $post;
			}
			$content = 'start' === ( $input['position'] ?? 'end' )
				? $markup . "\n\n" . $post->post_content
				: rtrim( $post->post_content ) . "\n\n" . $markup;
			$saved   = Ai::save_content( $post, $content );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$out += $saved;
		}

		return $out;
	}
}
