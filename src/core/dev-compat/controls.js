/**
 * Legacy inspector options → control descriptors (pure data, no React).
 *
 * The vocabulary is legacy 5.1.0's `templates/option-*.php`. Each option `{type, name, id, desc,
 * …}` becomes a descriptor that says which control to draw, which shortcode attribute it edits,
 * and how a control value maps to that attribute. The React half is option-controls.js.
 *
 * ⭐ An option whose `id` is `content` edits the shortcode's enclosed content, as in legacy
 * (script.js:9664 put `sc.shortcode.content` into the model under `content`).
 *
 * ⚠️ Types whose legacy control edited the ELEMENT's own styles or embedded an admin screen —
 * iframe, border, margins-and-paddings, widgetSettings, or anything unknown — have no block
 * equivalent. They degrade to a text field carrying a note, rather than disappearing: the
 * attribute stays editable and the add-on's saved values survive.
 */

import { ATTR_NAME_RE, plainText } from './serialize';

/** Legacy types drawn as a native control. */
export const NATIVE_TYPES = [
	'text',
	'textarea',
	'select',
	'checkbox',
	'number',
	'color',
	'image',
	'file',
	'link',
	'multicheck',
	'note',
];

/** Legacy types that were plain text inputs under another name. */
const TEXT_ALIASES = [ 'shortcode-generic', 'text-dummy' ];

// One sanitiser for the whole layer (registry.js): no second, weaker copy here.
const strip = ( v ) => plainText( v );

const num = ( v ) => {
	const n = typeof v === 'string' && v.trim() !== '' ? Number( v ) : v;
	return typeof n === 'number' && Number.isFinite( n ) ? n : undefined;
};

/**
 * Legacy `options` for select/multicheck: a `{value: label}` map (the documented form), an
 * array of `{value, label}`, or an array of strings.
 *
 * @param {*} raw Legacy options.
 * @return {Array<{value: string, label: string}>} Choices.
 */
export function normaliseChoices( raw ) {
	if ( Array.isArray( raw ) ) {
		return raw
			.map( ( item ) => {
				if ( typeof item === 'string' || typeof item === 'number' ) {
					return { value: String( item ), label: String( item ) };
				}
				if ( item && typeof item === 'object' && 'value' in item ) {
					return {
						value: String( item.value ),
						label: strip( item.label ?? item.value ),
					};
				}
				return null;
			} )
			.filter( Boolean );
	}
	if ( raw && typeof raw === 'object' ) {
		return Object.entries( raw ).map( ( [ value, label ] ) => ( {
			value,
			label: strip( label ),
		} ) );
	}
	return [];
}

/**
 * Describe one legacy option.
 *
 * @param {Object} option Legacy option.
 * @return {Object} Descriptor.
 */
export function describeOption( option ) {
	const legacyType = typeof option?.type === 'string' ? option.type : 'text';
	const rawId = typeof option?.id === 'string' ? option.id : '';
	let target = null;
	if ( rawId === 'content' ) {
		target = 'content';
	} else if ( ATTR_NAME_RE.test( rawId ) ) {
		target = 'attr';
	}
	const label = strip( option?.name ) || rawId;
	const help = strip( option?.desc );

	let kind;
	let degraded = false;
	if ( NATIVE_TYPES.includes( legacyType ) ) {
		kind = legacyType;
	} else if ( TEXT_ALIASES.includes( legacyType ) ) {
		kind = 'text';
	} else {
		kind = 'text';
		degraded = true;
	}

	// A control with nowhere to store its value can only be a note.
	if ( target === null && kind !== 'note' ) {
		kind = 'note';
	}

	const d = {
		kind,
		legacyType,
		degraded,
		target,
		id: target ? rawId : null,
		label,
		help,
		placeholder: strip( option?.placeholder ),
		default:
			typeof option?.default === 'string' ||
			typeof option?.default === 'number'
				? String( option.default )
				: undefined,
	};

	if ( kind === 'select' || kind === 'multicheck' ) {
		d.choices = normaliseChoices( option?.options );
	}
	if ( kind === 'number' ) {
		d.min = num( option?.min );
		d.max = num( option?.max );
		d.step = num( option?.step );
	}
	if ( kind === 'checkbox' ) {
		// Legacy: `checked` defaults to true (script.js:20009), `unchecked` to false, and the
		// model value was written straight into the shortcode.
		d.checkedValue =
			option?.checked === undefined || option?.checked === true
				? 'true'
				: String( option.checked );
		d.uncheckedValue =
			option?.unchecked === undefined ||
			option?.unchecked === false ||
			option?.unchecked === ''
				? null
				: String( option.unchecked );
	}
	if ( kind === 'image' ) {
		d.multiple = option?.multiple === true;
	}
	return d;
}

/**
 * The current value a control shows.
 *
 * @param {Object} d          Descriptor.
 * @param {Object} attributes Block attributes ({tag, attrs, content}).
 * @return {*} Control value.
 */
export function readValue( d, attributes ) {
	if ( d.target === null ) {
		return undefined;
	}
	const raw =
		d.target === 'content'
			? ( attributes?.content ?? '' )
			: attributes?.attrs?.[ d.id ];
	const str = raw === undefined || raw === null ? '' : String( raw );
	switch ( d.kind ) {
		case 'checkbox':
			return str === d.checkedValue;
		case 'multicheck':
			return str === '' ? [] : str.split( ',' );
		default:
			return str;
	}
}

/**
 * The setAttributes() patch for a new control value.
 *
 * @param {Object} d          Descriptor.
 * @param {*}      value      New control value.
 * @param {Object} attributes Current block attributes.
 * @return {Object} Patch.
 */
export function patchFor( d, value, attributes ) {
	if ( d.target === null ) {
		return {};
	}
	let str;
	switch ( d.kind ) {
		case 'checkbox':
			str = value ? d.checkedValue : d.uncheckedValue;
			break;
		case 'multicheck':
			str = Array.isArray( value ) ? value.join( ',' ) : '';
			break;
		case 'image':
			str = Array.isArray( value )
				? value.join( ',' )
				: String( value ?? '' );
			break;
		default:
			str = value === undefined || value === null ? '' : String( value );
	}
	if ( d.target === 'content' ) {
		return { content: str ?? '' };
	}
	const attrs = { ...( attributes?.attrs || {} ) };
	if ( str === null || str === '' ) {
		delete attrs[ d.id ];
	} else {
		attrs[ d.id ] = str;
	}
	return { attrs };
}

/**
 * Initial attributes for a fresh block from the options' `default` values.
 *
 * @param {Array<Object>} descriptors Descriptors.
 * @return {{attrs: Object, content: string}} Defaults.
 */
export function defaultsFor( descriptors ) {
	const attrs = {};
	let content = '';
	for ( const d of descriptors ) {
		if ( d.default === undefined || d.target === null ) {
			continue;
		}
		if ( d.target === 'content' ) {
			content = d.default;
		} else {
			attrs[ d.id ] = d.default;
		}
	}
	return { attrs, content };
}
