/**
 * Small helpers over the core-data entity Studio edits.
 */
import { parse, serialize } from '@wordpress/blocks';

/**
 * The edited content of the post as markup. core-data stores it as a function of the edited
 * blocks between saves (useEntityBlockEditor), so it is evaluated here.
 *
 * @param {Object} record An edited entity record.
 * @return {string} Markup.
 */
export function contentOf( record ) {
	if ( ! record ) {
		return '';
	}
	if ( typeof record.content === 'function' ) {
		return record.content( record );
	}
	if ( record.blocks && typeof record.content !== 'string' ) {
		return serialize( record.blocks );
	}
	if ( record.content && typeof record.content === 'object' ) {
		return record.content.raw ?? '';
	}
	return record.content ?? '';
}

/**
 * The title of a record, raw or edited.
 *
 * @param {Object} record Record.
 * @return {string} Title.
 */
export function titleOf( record ) {
	const t = record?.title;
	if ( t && typeof t === 'object' ) {
		return t.raw ?? '';
	}
	return t ?? '';
}

/**
 * Markup normalised the way the editor would write it, so "unchanged" compares equal.
 *
 * @param {string} markup Markup.
 * @return {string} Normalised markup.
 */
export function normalise( markup ) {
	try {
		return serialize( parse( markup || '' ) );
	} catch {
		return markup || '';
	}
}
