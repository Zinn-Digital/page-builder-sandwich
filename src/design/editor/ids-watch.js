/**
 * Keeps element ids unique in the post being edited (pbs-p4): after a duplicate, a paste or an
 * insert of a pattern, a block carrying an id another block already owns gets a new one. The
 * re-assignment is not an undo step of its own.
 */
import { select, dispatch, subscribe } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

import { dedupe } from '../ids';

/**
 * Start watching a registry's block editor store.
 *
 * @return {Function} Unsubscribe.
 */
export function watchIds() {
	const owners = new Map();
	let last = null;
	let busy = false;
	return subscribe( () => {
		if ( busy ) {
			return;
		}
		const be = select( blockEditorStore );
		if ( ! be ) {
			return;
		}
		const blocks = be.getBlocks();
		if ( blocks === last ) {
			return;
		}
		last = blocks;
		const changes = dedupe( blocks, owners );
		if ( ! changes.length ) {
			return;
		}
		busy = true;
		try {
			const actions = dispatch( blockEditorStore );
			actions.__unstableMarkNextChangeAsNotPersistent?.();
			// One action for every re-assignment, so it is one (non-persistent) change.
			const attributes = {};
			for ( const { clientId, id } of changes ) {
				const pbs = be.getBlockAttributes( clientId )?.pbs || {};
				attributes[ clientId ] = { pbs: { ...pbs, id } };
			}
			actions.updateBlockAttributes(
				changes.map( ( c ) => c.clientId ),
				attributes,
				true
			);
		} finally {
			busy = false;
			last = select( blockEditorStore ).getBlocks();
		}
	}, blockEditorStore );
}
