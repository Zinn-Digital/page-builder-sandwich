/**
 * Design prop `backgroundImage` (pbs-p4). Twin: includes/design/props/class-background-image.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';

const POSITION =
	/^(?:center|top|bottom|center top|center bottom|\d{1,3}% \d{1,3}%)$/;
const REPEAT = [
	'no-repeat',
	'repeat',
	'repeat-x',
	'repeat-y',
	'space',
	'round',
];
const ATTACHMENT = [ 'scroll', 'fixed', 'local' ];

/**
 * An http(s), protocol-relative or root-relative URL that cannot end the url("…") token.
 *
 * @param {unknown} url Candidate.
 * @return {string|null} URL.
 */
export function safeUrl( url ) {
	if ( typeof url !== 'string' ) {
		return null;
	}
	url = url.trim();
	return url.length <= 2000 &&
		/^(?:https?:\/\/|\/\/|\/)[^\s"'()\\<>]*$/i.test( url )
		? url
		: null;
}

export default {
	key: 'backgroundImage',
	group: 'colour',
	target: '',
	blocks: [],
	label: __( 'Background image', 'page-builder-sandwich' ),
	control: 'background-image',
	sanitize( value ) {
		if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
			return null;
		}
		const url = safeUrl( value.url );
		if ( url === null ) {
			return null;
		}
		const out = { url };
		if ( Number.isInteger( value.id ) && value.id > 0 ) {
			out.id = value.id;
		}
		const position =
			typeof value.position === 'string'
				? value.position.trim().toLowerCase()
				: '';
		if ( POSITION.test( position ) ) {
			out.position = position;
		}
		const size = V.length( value.size ?? null, {
			keywords: [ 'auto', 'cover', 'contain' ],
		} );
		if ( typeof size === 'string' ) {
			out.size = size;
		}
		const repeat = V.choice( value.repeat, REPEAT );
		if ( repeat !== null ) {
			out.repeat = repeat;
		}
		const attachment = V.choice( value.attachment, ATTACHMENT );
		if ( attachment !== null ) {
			out.attachment = attachment;
		}
		return out;
	},
	toCss( value ) {
		const out = { 'background-image': `url("${ value.url }")` };
		for ( const k of [ 'position', 'size', 'repeat', 'attachment' ] ) {
			if ( value[ k ] !== undefined ) {
				out[ `background-${ k }` ] = String( value[ k ] );
			}
		}
		return out;
	},
};
