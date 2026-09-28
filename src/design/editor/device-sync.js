/**
 * Keeps the Style tab's breakpoint and the block editor's device preview in step (pbs-d2): choose
 * Tablet in either and the other follows. A custom breakpoint previews on the nearest device.
 */
import { dispatch, select, subscribe } from '@wordpress/data';

import { STORE, config } from '../store';

/**
 * The editor device that previews a breakpoint.
 *
 * @param {string} bp Breakpoint id.
 * @return {string} Desktop | Tablet | Mobile.
 */
export function deviceFor( bp ) {
	const list = config().breakpoints;
	const tabletMax = list.find( ( b ) => b.id === 'tablet' )?.max ?? 1024;
	const mobileMax = list.find( ( b ) => b.id === 'mobile' )?.max ?? 767;
	const found = list.find( ( b ) => b.id === bp );
	if ( ! found || found.max === undefined ) {
		return 'Desktop';
	}
	if ( found.max <= mobileMax ) {
		return 'Mobile';
	}
	return found.max <= tabletMax ? 'Tablet' : 'Desktop';
}

const BP_FOR = { Desktop: 'base', Tablet: 'tablet', Mobile: 'mobile' };

/**
 * Start syncing (no-op where the editor store has no device type, e.g. Studio).
 *
 * @return {Function} Unsubscribe.
 */
export function syncDevice() {
	let lastDevice = null;
	let lastBp = null;
	return subscribe( () => {
		const editor = select( 'core/editor' );
		if ( ! editor || typeof editor.getDeviceType !== 'function' ) {
			return;
		}
		const device = editor.getDeviceType();
		const bp = select( STORE ).getBreakpoint();
		if ( lastDevice === null ) {
			lastDevice = device;
			lastBp = bp;
			return;
		}
		if ( device !== lastDevice ) {
			lastDevice = device;
			if ( deviceFor( bp ) !== device && BP_FOR[ device ] ) {
				dispatch( STORE ).setBreakpoint( BP_FOR[ device ] );
			}
			lastBp = select( STORE ).getBreakpoint();
			return;
		}
		if ( bp !== lastBp ) {
			lastBp = bp;
			const want = deviceFor( bp );
			if ( want !== device ) {
				lastDevice = want;
				dispatch( 'core/editor' ).setDeviceType?.( want );
			}
		}
	} );
}
