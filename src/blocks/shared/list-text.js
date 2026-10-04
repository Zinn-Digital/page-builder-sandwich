/**
 * A text field that edits a LIST (one item per line, or comma separated) without fighting the
 * person typing it.
 *
 * ⛔ The bug this exists for (docs/72 D-PBS-DEMO, demo.pagebuildersandwich.com 2026-10-04): the
 * Pros & Cons sidebar parsed the textarea into a trimmed, blank-free list on EVERY keystroke and
 * then showed that list joined again, so the space after "No" and the line break after "No live
 * chat" were deleted the moment they were typed ("No live chat" + Enter + "Slow" stored
 * ["NolivechatSlow"]). The same shape lost the comma in every comma-separated id field.
 *
 * The fix: the field keeps the RAW text while it is being edited and hands the parsed list to
 * the block on each change, so the stored attribute is always normalised (save and render are
 * unchanged) while the visitor's spaces, commas and line breaks stay where they typed them. When
 * the list changes from outside (undo, a pattern, another control), the field shows that list.
 */
import { useState } from '@wordpress/element';
import { TextControl, TextareaControl } from '@wordpress/components';

/**
 * Text → items, one per line, trimmed, blanks dropped. Pure.
 *
 * @param {string} text Text.
 * @return {string[]} Items.
 */
export const linesOf = ( text ) =>
	String( text ?? '' )
		.split( /\r\n|\r|\n/ )
		.map( ( l ) => l.trim() )
		.filter( Boolean );

/**
 * Text → items, comma separated, trimmed, blanks dropped. Pure.
 *
 * @param {string} text Text.
 * @return {string[]} Items.
 */
export const commaListOf = ( text ) =>
	String( text ?? '' )
		.split( ',' )
		.map( ( l ) => l.trim() )
		.filter( Boolean );

/**
 * What the field shows: the raw text while it still parses to the list the block holds, else the
 * list itself (it changed from outside). Pure.
 *
 * @param {string}                  raw    The text as last typed.
 * @param {Array}                   items  The list the block holds.
 * @param {(text: string) => Array} parse  Text → list.
 * @param {(list: Array) => string} format List → text.
 * @return {string} The field's value.
 */
export const shownText = ( raw, items, parse, format ) => {
	const held = format( items || [] );
	return typeof raw === 'string' && format( parse( raw ) ) === held
		? raw
		: held;
};

/**
 * A TextareaControl (multiline) or TextControl editing a list.
 *
 * @param {Object}                  props             Props; the rest go to the control.
 * @param {Array}                   props.items       The list.
 * @param {(list: Array) => void}   props.onChange    Receives the parsed list.
 * @param {(text: string) => Array} [props.parse]     Text → list (default: one per line).
 * @param {(list: Array) => string} [props.format]    List → text (default: line breaks).
 * @param {boolean}                 [props.multiline] A textarea (default true).
 * @return {Element} The control.
 */
export default function ListTextControl( {
	items,
	onChange,
	parse = linesOf,
	format = ( list ) => list.join( '\n' ),
	multiline = true,
	...props
} ) {
	const [ raw, setRaw ] = useState( null );
	const Control = multiline ? TextareaControl : TextControl;
	return (
		<Control
			{ ...props }
			value={ shownText( raw, items, parse, format ) }
			onChange={ ( text ) => {
				setRaw( text );
				onChange( parse( text ) );
			} }
		/>
	);
}
