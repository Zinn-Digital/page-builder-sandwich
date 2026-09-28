/**
 * A tiny shared resource: one request, many readers. Colours and fonts are read by several
 * sections (Colours, Fonts, Dark mode, Brand kit), so they share one copy that every save
 * replaces with what the server answered — the screen never shows a value the server did not
 * keep.
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Make a resource.
 *
 * @param {Function} load `() => Promise<data>`.
 * @return {{ use: Function, set: Function, reload: Function, get: Function }} Resource.
 */
export function createResource( load ) {
	let data = null;
	let error = null;
	let pending = null;
	const listeners = new Set();
	const emit = () => listeners.forEach( ( fn ) => fn() );

	const reload = () => {
		pending = Promise.resolve()
			.then( load )
			.then( ( value ) => {
				data = value;
				error = null;
			} )
			.catch( ( e ) => {
				error = e;
			} )
			.finally( () => {
				pending = null;
				emit();
			} );
		return pending;
	};

	const set = ( value ) => {
		data = value;
		error = null;
		emit();
	};

	const use = () => {
		const [ , force ] = useState( 0 );
		useEffect( () => {
			const fn = () => force( ( n ) => n + 1 );
			listeners.add( fn );
			if ( data === null && error === null && ! pending ) {
				reload();
			}
			return () => listeners.delete( fn );
		}, [] );
		return { data, error, set, reload };
	};

	return { use, set, reload, get: () => data };
}
