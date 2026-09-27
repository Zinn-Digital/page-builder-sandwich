/**
 * Revisions (pbs-g1): the post's core revisions, a preview of any of them, and restore. Restoring
 * puts the revision into the editor as an ordinary, undoable edit; saving keeps it (and saving
 * makes a new revision, so a restore can itself be undone later).
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as noticesStore } from '@wordpress/notices';
import { parse } from '@wordpress/blocks';
import { BlockPreview } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Spinner, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n, getSettings } from '@wordpress/date';
import { closeSmall } from '@wordpress/icons';
import { useBoot } from '../context';

/**
 * @param {Object}   props
 * @param {Function} props.onClose Close the panel.
 */
export default function Revisions( { onClose } ) {
	const boot = useBoot();
	const { editEntityRecord } = useDispatch( coreStore );
	const { createSuccessNotice } = useDispatch( noticesStore );
	const [ list, setList ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ preview, setPreview ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		apiFetch( {
			path: `/wp/v2/${ boot.restBase }/${ boot.postId }/revisions?context=edit&per_page=100&_fields=id,author,date,date_gmt,title,content`,
		} )
			.then( ( rows ) => ! cancelled && setList( rows || [] ) )
			.catch(
				( e ) =>
					! cancelled &&
					setError(
						e?.message ||
							__(
								'Revisions could not be loaded.',
								'page-builder-sandwich'
							)
					)
			);
		return () => {
			cancelled = true;
		};
	}, [ boot.restBase, boot.postId ] );

	const format = getSettings().formats.datetime;
	const blocks = useMemo(
		() => ( preview ? parse( preview.content?.raw ?? '' ) : [] ),
		[ preview ]
	);

	const restore = ( rev ) => {
		const content = rev.content?.raw ?? '';
		editEntityRecord( 'postType', boot.postType, boot.postId, {
			title: rev.title?.raw ?? '',
			content,
			blocks: parse( content ),
			selection: undefined,
		} );
		setPreview( null );
		createSuccessNotice(
			__(
				'Revision restored. Save to keep it, or undo to go back.',
				'page-builder-sandwich'
			),
			{ type: 'snackbar', id: 'pbsw-studio-revision' }
		);
	};

	return (
		<div className="pbsw-studio-revisions">
			<div className="pbsw-studio-panel__head">
				<h2>{ __( 'Revisions', 'page-builder-sandwich' ) }</h2>
				<Button
					icon={ closeSmall }
					label={ __( 'Close revisions', 'page-builder-sandwich' ) }
					onClick={ onClose }
					size="small"
				/>
			</div>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! list && ! error && <Spinner /> }
			{ list && ! list.length && (
				<p className="pbsw-studio-muted">
					{ __(
						'No revisions yet. Each save makes one.',
						'page-builder-sandwich'
					) }
				</p>
			) }
			{ list && list.length > 0 && (
				<ul className="pbsw-studio-revisions__list">
					{ list.map( ( rev ) => (
						<li key={ rev.id }>
							<Button
								variant="tertiary"
								className="pbsw-studio-revisions__item"
								onClick={ () => setPreview( rev ) }
							>
								{ dateI18n( format, rev.date_gmt + 'Z' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			{ preview && (
				<Modal
					title={ sprintf(
						/* translators: %s: date and time of the revision. */
						__( 'Revision from %s', 'page-builder-sandwich' ),
						dateI18n( format, preview.date_gmt + 'Z' )
					) }
					onRequestClose={ () => setPreview( null ) }
					size="large"
					className="pbsw-studio-revision-preview"
				>
					<div className="pbsw-studio-revision-preview__canvas">
						<BlockPreview
							blocks={ blocks }
							viewportWidth={ 1200 }
						/>
					</div>
					<div className="pbsw-studio-recovery__actions">
						<Button
							variant="primary"
							onClick={ () => restore( preview ) }
							__next40pxDefaultSize
						>
							{ __(
								'Restore this revision',
								'page-builder-sandwich'
							) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setPreview( null ) }
							__next40pxDefaultSize
						>
							{ __( 'Cancel', 'page-builder-sandwich' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
