/**
 * The local crash-recovery snapshot writer (pbs-r16). Writes every few seconds while there are
 * unsaved changes, and once more when the tab is hidden or closed; cleared by a successful save
 * (use-save.js).
 *
 * ⛔ It stays OFF until the recovery question on open has been answered: otherwise the freshly
 * loaded page (which a block migration can already mark as edited) would overwrite the very
 * snapshot the user is about to be offered.
 */
import { useEffect, useRef } from '@wordpress/element';
import { useRegistry } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { contentOf, titleOf } from '../lib/entity';
import { writeSnapshot } from '../lib/snapshot';
import { useBoot } from '../context';

/**
 * @param {boolean} enabled  Recovery resolved, writing allowed.
 * @param {number}  interval Seconds.
 */
export function useSnapshot( enabled, interval ) {
	const boot = useBoot();
	const registry = useRegistry();
	const last = useRef( null );

	useEffect( () => {
		if ( ! enabled || ! boot.storage ) {
			return undefined;
		}
		const { postType, postId } = boot;
		const write = () => {
			const s = registry.select( coreStore );
			if ( ! s.hasEditsForEntityRecord( 'postType', postType, postId ) ) {
				return;
			}
			const record = s.getEditedEntityRecord(
				'postType',
				postType,
				postId
			);
			const title = titleOf( record );
			const content = contentOf( record );
			const body = title + '\u0000' + content;
			if ( body === last.current ) {
				return;
			}
			if (
				writeSnapshot( boot.storage, boot.snapshotKey, {
					title,
					content,
					time: Date.now(),
				} )
			) {
				last.current = body;
			}
		};
		const id = window.setInterval( write, Math.max( 1, interval ) * 1000 );
		const onHide = () => {
			if ( document.visibilityState === 'hidden' ) {
				write();
			}
		};
		document.addEventListener( 'visibilitychange', onHide );
		window.addEventListener( 'pagehide', write );
		return () => {
			window.clearInterval( id );
			document.removeEventListener( 'visibilitychange', onHide );
			window.removeEventListener( 'pagehide', write );
		};
	}, [ enabled, interval, boot, registry ] );
}
