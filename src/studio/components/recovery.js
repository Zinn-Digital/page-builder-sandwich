/**
 * On open: offer back unsaved work (pbs-r16) — the newest of this browser's snapshot and the
 * server autosave, when it differs from the saved page.
 */
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useRegistry } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { parse } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { dateI18n, getSettings } from '@wordpress/date';
import { normalise, titleOf } from '../lib/entity';
import { clearSnapshot, readSnapshot, recoveryOffer } from '../lib/snapshot';
import { useBoot } from '../context';

/**
 * @param {Object}   props
 * @param {Function} props.onResolved Called once the question is settled (or there was none).
 */
export default function Recovery( { onResolved } ) {
	const boot = useBoot();
	const registry = useRegistry();
	const { editEntityRecord } = useDispatch( coreStore );
	const [ offer, setOffer ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		( async () => {
			const record = registry
				.select( coreStore )
				.getEntityRecord( 'postType', boot.postType, boot.postId );
			const saved = {
				title: titleOf( record ),
				content: normalise( record?.content?.raw ?? '' ),
				modifiedGmt: record?.modified_gmt,
			};
			let autosave = null;
			try {
				const list = await apiFetch( {
					path: `/wp/v2/${ boot.restBase }/${ boot.postId }/autosaves?context=edit`,
				} );
				const mine = ( Array.isArray( list ) ? list : [] ).find(
					( a ) => Number( a.author ) === Number( boot.userId )
				);
				if ( mine ) {
					autosave = {
						title: titleOf( mine ),
						content: normalise( mine.content?.raw ?? '' ),
						modifiedGmt: mine.modified_gmt,
					};
				}
			} catch {
				// No autosave is readable: the local snapshot is still offered.
			}
			if ( cancelled ) {
				return;
			}
			const snap = readSnapshot( boot.storage, boot.snapshotKey );
			const offered = recoveryOffer( {
				snapshot: snap
					? { ...snap, content: normalise( snap.content ) }
					: null,
				saved,
				autosave,
			} );
			if ( ! offered ) {
				if ( snap ) {
					// Identical to (or older than) the saved page: nothing to offer.
					clearSnapshot( boot.storage, boot.snapshotKey );
				}
				onResolved();
				return;
			}
			setOffer( offered );
		} )();
		return () => {
			cancelled = true;
		};
		// Runs once, on open.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( ! offer ) {
		return null;
	}

	const restore = () => {
		editEntityRecord( 'postType', boot.postType, boot.postId, {
			title: offer.title,
			content: offer.content,
			blocks: parse( offer.content ),
			selection: undefined,
		} );
		setOffer( null );
		onResolved();
	};
	const discard = () => {
		if ( offer.source === 'local' ) {
			clearSnapshot( boot.storage, boot.snapshotKey );
		}
		setOffer( null );
		onResolved();
	};
	const when = dateI18n(
		getSettings().formats.datetime,
		new Date( offer.time )
	);

	return (
		<Modal
			title={ __( 'Restore unsaved changes?', 'page-builder-sandwich' ) }
			onRequestClose={ discard }
			shouldCloseOnClickOutside={ false }
			className="pbsw-studio-recovery"
		>
			<p>
				{ offer.source === 'local'
					? __(
							'This page has changes that were never saved. They were kept in this browser:',
							'page-builder-sandwich'
						)
					: __(
							'This page has a newer autosave than the saved version:',
							'page-builder-sandwich'
						) }{ ' ' }
				<strong>{ when }</strong>
			</p>
			<div className="pbsw-studio-recovery__actions">
				<Button
					variant="primary"
					onClick={ restore }
					__next40pxDefaultSize
				>
					{ __( 'Restore unsaved changes', 'page-builder-sandwich' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ discard }
					__next40pxDefaultSize
				>
					{ __( 'Discard', 'page-builder-sandwich' ) }
				</Button>
			</div>
		</Modal>
	);
}
