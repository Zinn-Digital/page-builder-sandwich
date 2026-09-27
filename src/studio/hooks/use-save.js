/**
 * Saving, publishing and the server autosave (pbs-r16) — all through core-data, so each one is a
 * core REST request with core's own permission checks, revisions and autosave rules.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect, useRegistry } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';
import { contentOf, titleOf } from '../lib/entity';
import { clearSnapshot } from '../lib/snapshot';
import { useBoot } from '../context';

/**
 * Save state and actions for the post being edited.
 *
 * @return {Object} { save, publish, isDirty, isSaving, isAutosaving, status, lastAutosave }
 */
export function useSave() {
	const boot = useBoot();
	const { postType, postId } = boot;
	const registry = useRegistry();
	const { editEntityRecord, saveEditedEntityRecord } =
		useDispatch( coreStore );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const state = useSelect(
		( select ) => {
			const s = select( coreStore );
			return {
				isDirty: s.hasEditsForEntityRecord(
					'postType',
					postType,
					postId
				),
				isSaving: s.isSavingEntityRecord(
					'postType',
					postType,
					postId
				),
				isAutosaving: s.isAutosavingEntityRecord(
					'postType',
					postType,
					postId
				),
				status: s.getEditedEntityRecord( 'postType', postType, postId )
					?.status,
			};
		},
		[ postType, postId ]
	);

	const save = useCallback(
		async ( extraEdits = {} ) => {
			const current = registry
				.select( coreStore )
				.getEditedEntityRecord( 'postType', postType, postId );
			const edits = { ...extraEdits };
			if ( current?.status === 'auto-draft' && ! edits.status ) {
				edits.status = 'draft';
			}
			if ( Object.keys( edits ).length ) {
				editEntityRecord( 'postType', postType, postId, edits );
			}
			try {
				await saveEditedEntityRecord( 'postType', postType, postId, {
					throwOnError: true,
				} );
			} catch ( error ) {
				createErrorNotice(
					error?.message ||
						__(
							'Saving failed. Check your connection and try again; your changes are kept in this browser.',
							'page-builder-sandwich'
						),
					{ id: 'pbsw-studio-save', type: 'snackbar' }
				);
				return false;
			}
			clearSnapshot( boot.storage, boot.snapshotKey );
			const saved = registry
				.select( coreStore )
				.getEntityRecord( 'postType', postType, postId );
			createSuccessNotice(
				saved?.status === 'publish' && edits.status === 'publish'
					? __( 'Published.', 'page-builder-sandwich' )
					: __( 'Saved.', 'page-builder-sandwich' ),
				{ id: 'pbsw-studio-save', type: 'snackbar' }
			);
			return true;
		},
		[
			registry,
			postType,
			postId,
			editEntityRecord,
			saveEditedEntityRecord,
			createErrorNotice,
			createSuccessNotice,
			boot,
		]
	);

	const publish = useCallback(
		() => save( { status: 'publish' } ),
		[ save ]
	);

	return { ...state, save, publish };
}

/**
 * Server autosave every `interval` seconds while the content differs from the last autosave.
 *
 * @param {number} interval Seconds.
 * @return {number|null} Time of the last successful autosave (ms).
 */
export function useAutosave( interval ) {
	const { postType, postId } = useBoot();
	const registry = useRegistry();
	const [ last, setLast ] = useState( null );
	const lastBody = useRef( null );

	useEffect( () => {
		const tick = async () => {
			const s = registry.select( coreStore );
			if (
				! s.hasEditsForEntityRecord( 'postType', postType, postId ) ||
				s.isSavingEntityRecord( 'postType', postType, postId )
			) {
				return;
			}
			const record = s.getEditedEntityRecord(
				'postType',
				postType,
				postId
			);
			const body = titleOf( record ) + '\u0000' + contentOf( record );
			if ( body === lastBody.current ) {
				return;
			}
			try {
				await registry
					.dispatch( coreStore )
					.saveEditedEntityRecord( 'postType', postType, postId, {
						isAutosave: true,
						throwOnError: true,
					} );
				lastBody.current = body;
				setLast( Date.now() );
			} catch {
				// The local snapshot still holds the work; the next tick retries.
			}
		};
		const id = window.setInterval( tick, Math.max( 5, interval ) * 1000 );
		return () => window.clearInterval( id );
	}, [ registry, postType, postId, interval ] );

	return last;
}
