/**
 * The legacy developer API — `window.pbsAddInspector()` / `window.pbsRemoveInspector()` and the
 * PHP `pbs_shortcodes` filter — kept working on top of block variations.
 *
 * Legacy 5.1.0 kept every shortcode definition in `PBSInspectorOptions.Shortcode` and built its
 * inspector panel from it (js/dev/script.js:18864). Here each definition becomes a VARIATION of
 * the pbs/shortcode block instead, so a third-party add-on written against the old API shows up
 * in the block inserter with the controls it declared, and nothing in the add-on changes.
 *
 * The merge rules are the legacy ones, deliberately:
 * - the PHP filter's definitions are set first and REPLACE (script.js:18979);
 * - a JS call for a tag that is not defined yet sets it; for one that is, every key but
 *   `options` is overwritten and `options` is APPENDED (script.js:18892-18906);
 * - an array of tags registers the same definition under each;
 * - a definition without `is_shortcode: true` targeted a legacy EDITOR ELEMENT (a paragraph, an
 *   image …). Those have no block equivalent; the call is recorded and ignored.
 */

import { TAG_RE, plainText } from './serialize';

export { plainText };
import { describeOption, defaultsFor } from './controls';

/** Prefix of every variation name this layer registers. */
export const VARIATION_PREFIX = 'pbsw-sc-';

/**
 * Normalise one definition to the shape the rest of this layer uses.
 *
 * @param {string} tag Shortcode tag.
 * @param {Object} def Legacy definition.
 * @return {{label: string, desc: string, options: Array<Object>}} Definition.
 */
export function normaliseDefinition( tag, def ) {
	const src = def && typeof def === 'object' ? def : {};
	return {
		label: plainText( src.label ) || tag,
		desc: plainText( src.desc ),
		options: Array.isArray( src.options )
			? src.options.filter( ( o ) => o && typeof o === 'object' )
			: [],
	};
}

/**
 * The block variation for a definition.
 *
 * @param {string} tag Shortcode tag.
 * @param {Object} def Normalised definition.
 * @return {Object} Variation settings for registerBlockVariation().
 */
export function variationFor( tag, def ) {
	const descriptors = def.options.map( describeOption );
	const { attrs, content } = defaultsFor( descriptors );
	return {
		name: VARIATION_PREFIX + tag,
		title: def.label,
		description: def.desc,
		icon: 'shortcode',
		keywords: [ tag ],
		scope: [ 'inserter' ],
		attributes: { tag, attrs, content },
		isActive: ( blockAttributes ) => blockAttributes?.tag === tag,
	};
}

/**
 * Create the registry.
 *
 * @param {Object}   sink            Where variations go.
 * @param {Function} sink.register   ( variation ) => void.
 * @param {Function} sink.unregister ( variationName ) => void.
 * @return {Object} Registry.
 */
export function createRegistry( sink ) {
	/** @type {Map<string, {label: string, desc: string, options: Array<Object>}>} */
	const defs = new Map();
	const ignored = [];

	const publish = ( tag ) => {
		sink.unregister( VARIATION_PREFIX + tag );
		const def = defs.get( tag );
		if ( def ) {
			sink.register( variationFor( tag, def ) );
		}
	};

	/**
	 * Set a definition outright (the PHP filter's semantics).
	 *
	 * @param {string} tag Tag.
	 * @param {Object} def Definition.
	 * @return {boolean} Whether it was accepted.
	 */
	const set = ( tag, def ) => {
		if ( typeof tag !== 'string' || ! TAG_RE.test( tag ) ) {
			ignored.push( { tag, reason: 'invalid-tag' } );
			return false;
		}
		defs.set( tag, normaliseDefinition( tag, def ) );
		publish( tag );
		return true;
	};

	/**
	 * window.pbsAddInspector( tag | tag[], args ).
	 *
	 * @param {string|string[]} tag  Tag or tags.
	 * @param {Object}          args Legacy definition.
	 * @return {void}
	 */
	const add = ( tag, args ) => {
		if ( ! args || typeof args !== 'object' ) {
			return;
		}
		if ( Array.isArray( tag ) ) {
			tag.forEach( ( t ) => add( t, args ) );
			return;
		}
		if ( args.is_shortcode !== true ) {
			ignored.push( { tag, reason: 'element-inspector' } );
			return;
		}
		if ( typeof tag !== 'string' || ! TAG_RE.test( tag ) ) {
			ignored.push( { tag, reason: 'invalid-tag' } );
			return;
		}
		const existing = defs.get( tag );
		if ( ! existing ) {
			set( tag, args );
			return;
		}
		const merged = normaliseDefinition( tag, {
			...existing,
			...args,
			options: [
				...existing.options,
				...( Array.isArray( args.options ) ? args.options : [] ),
			],
		} );
		defs.set( tag, merged );
		publish( tag );
	};

	/**
	 * window.pbsRemoveInspector( tag | tag[] ).
	 *
	 * @param {string|string[]} tag Tag or tags.
	 * @return {void}
	 */
	const remove = ( tag ) => {
		if ( Array.isArray( tag ) ) {
			tag.forEach( remove );
			return;
		}
		if ( defs.delete( tag ) ) {
			sink.unregister( VARIATION_PREFIX + tag );
		}
	};

	return {
		set,
		add,
		remove,
		get: ( tag ) => defs.get( tag ),
		tags: () => [ ...defs.keys() ],
		ignored: () => [ ...ignored ],
	};
}

/**
 * Take over the globals the early stub (printed in <head> by class-dev-compat.php) installed.
 *
 * Order, and it is the legacy order: the PHP filter's definitions, then every call the stub
 * queued in the order it was made, then live calls. A caller that kept a reference to the STUB
 * function still works: the queue is replaced by an object whose push() applies immediately.
 *
 * @param {Window} win      The window (injected for tests).
 * @param {Object} registry From createRegistry().
 * @return {void}
 */
export function install( win, registry ) {
	const data = win.pbswDevCompat;
	const fromPhp =
		data && data.shortcodes && typeof data.shortcodes === 'object'
			? data.shortcodes
			: {};
	for ( const [ tag, def ] of Object.entries( fromPhp ) ) {
		registry.set( tag, def );
	}

	const apply = ( entry ) => {
		if ( ! Array.isArray( entry ) ) {
			return;
		}
		const [ op, ...args ] = entry;
		if ( op === 'add' ) {
			registry.add( ...args );
		} else if ( op === 'remove' ) {
			registry.remove( ...args );
		}
	};

	const queued = Array.isArray( win.pbswDevCompatQueue )
		? win.pbswDevCompatQueue
		: [];
	win.pbswDevCompatQueue = { push: apply };
	queued.forEach( apply );

	win.pbsAddInspector = ( tag, args ) => registry.add( tag, args );
	win.pbsRemoveInspector = ( tag ) => registry.remove( tag );
}
