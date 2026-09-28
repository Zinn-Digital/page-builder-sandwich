/**
 * Reading and writing one block's `pbs` attribute for the breakpoint and state being edited.
 */
import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

import { STORE, config } from '../store';
import { inherited } from '../breakpoints';
import { newId, walk } from '../ids';

const isEmpty = ( v ) =>
	v === undefined ||
	v === null ||
	v === '' ||
	( typeof v === 'object' &&
		! Array.isArray( v ) &&
		! Object.keys( v ).length );

/**
 * A copy of `pbs` with one value set (or removed when empty), empty states pruned.
 *
 * @param {Object}   pbs      Current attribute (may be undefined).
 * @param {string}   stateKey `<bp>` or `<bp>:hover`.
 * @param {string}   key      Prop key.
 * @param {*}        value    New value; empty removes it.
 * @param {Function} makeId   Id factory, used when the block has none yet.
 * @return {Object|undefined} New attribute; undefined when nothing is left.
 */
export function withValue( pbs, stateKey, key, value, makeId ) {
	const s = { ...( pbs?.s || {} ) };
	const values = { ...( s[ stateKey ] || {} ) };
	if ( isEmpty( value ) ) {
		delete values[ key ];
	} else {
		values[ key ] = value;
	}
	if ( Object.keys( values ).length ) {
		s[ stateKey ] = values;
	} else {
		delete s[ stateKey ];
	}
	return tidy( { ...( pbs || {} ), s }, makeId );
}

/**
 * Drop empty parts; give the element an id when it has anything to style.
 *
 * @param {Object}   pbs    Attribute.
 * @param {Function} makeId Id factory.
 * @return {Object|undefined} Attribute.
 */
export function tidy( pbs, makeId ) {
	const out = { ...pbs };
	for ( const k of [ 's', 'hide', 'cls' ] ) {
		if (
			isEmpty( out[ k ] ) ||
			( Array.isArray( out[ k ] ) && ! out[ k ].length )
		) {
			delete out[ k ];
		}
	}
	if ( ! out.css ) {
		delete out.css;
	}
	const meaningful = Object.keys( out ).some( ( k ) => k !== 'id' );
	if ( ! meaningful ) {
		return undefined;
	}
	if ( ! out.id ) {
		out.id = makeId();
	}
	return out;
}

/**
 * Ids already used in the post.
 *
 * @param {Array<Object>} blocks Editor blocks.
 * @return {Set<string>} Ids.
 */
export function takenIds( blocks ) {
	const taken = new Set();
	walk( blocks, ( b ) => {
		if ( b.attributes?.pbs?.id ) {
			taken.add( b.attributes.pbs.id );
		}
	} );
	return taken;
}

/**
 * The design state of one block.
 *
 * @param {string} clientId Block client id.
 * @return {Object} API.
 */
export function useDesign( clientId ) {
	const { pbs, bp, hover } = useSelect(
		( select ) => ( {
			pbs: select( blockEditorStore ).getBlockAttributes( clientId )?.pbs,
			bp: select( STORE ).getBreakpoint(),
			hover: select( STORE ).isHover(),
		} ),
		[ clientId ]
	);
	const { updateBlockAttributes } = useDispatch( blockEditorStore );
	const registrySelect = useSelect( ( select ) => select, [] );
	const list = config().breakpoints;
	const state = hover ? ':hover' : '';
	const stateKey = bp + state;

	const makeId = useCallback(
		() =>
			newId( takenIds( registrySelect( blockEditorStore ).getBlocks() ) ),
		[ registrySelect ]
	);

	const write = useCallback(
		( next ) => updateBlockAttributes( clientId, { pbs: next } ),
		[ clientId, updateBlockAttributes ]
	);

	return {
		pbs,
		bp,
		hover,
		stateKey,
		get: ( key ) => pbs?.s?.[ stateKey ]?.[ key ],
		inherited: ( key ) => inherited( pbs?.s, key, bp, list, state ),
		set: ( key, value ) =>
			write(
				withValue(
					registrySelect( blockEditorStore ).getBlockAttributes(
						clientId
					)?.pbs,
					stateKey,
					key,
					value,
					makeId
				)
			),
		setMany: ( values ) => {
			let next =
				registrySelect( blockEditorStore ).getBlockAttributes(
					clientId
				)?.pbs;
			for ( const [ key, value ] of Object.entries( values ) ) {
				next = withValue( next, stateKey, key, value, makeId );
			}
			write( next );
		},
		setHide: ( hide ) =>
			write( tidy( { ...( pbs || {} ), hide }, makeId ) ),
		setField: ( field, value ) =>
			write( tidy( { ...( pbs || {} ), [ field ]: value }, makeId ) ),
		resetState: () => {
			const s = { ...( pbs?.s || {} ) };
			delete s[ stateKey ];
			write( tidy( { ...( pbs || {} ), s }, makeId ) );
		},
	};
}
