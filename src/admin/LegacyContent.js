import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, PanelBody, ProgressBar } from '@wordpress/components';

/**
 * How often the panel re-reads the status while a run is in progress.
 */
const POLL_MS = 5000;

/**
 * A failure message for the screen. Codes recorded by the migration are translated here;
 * anything else is the converter's own message and is shown as it is.
 *
 * @param {string} message Stored message or code.
 * @return {string} Text to show.
 */
export function failureText( message ) {
	switch ( message ) {
		case 'interrupted':
			return __(
				'The request converting this post stopped before it finished, so the post was skipped. Open it to convert it, or convert it again from here.',
				'page-builder-sandwich'
			);
		case 'no-backup':
			return __(
				'There is no backup of this post to restore.',
				'page-builder-sandwich'
			);
		case 'write-failed':
			return __(
				'The converted content could not be saved.',
				'page-builder-sandwich'
			);
		case '':
			return __( 'Unknown error.', 'page-builder-sandwich' );
		default:
			return message;
	}
}

/**
 * A one-line summary of the current state.
 *
 * @param {Object} status Payload of GET /pbs/v1/migration.
 * @return {string} Summary.
 */
export function summaryText( status ) {
	if ( 'none' === status.status ) {
		return __(
			'No legacy content has been checked yet.',
			'page-builder-sandwich'
		);
	}
	if ( 'running' === status.status ) {
		return 'undo' === status.mode
			? sprintf(
					/* translators: 1: posts handled so far, 2: posts to handle. */
					__(
						'Restoring legacy content: %1$d of %2$d posts.',
						'page-builder-sandwich'
					),
					status.processed,
					status.total
				)
			: sprintf(
					/* translators: 1: posts handled so far, 2: posts to handle. */
					__(
						'Converting legacy content: %1$d of %2$d posts.',
						'page-builder-sandwich'
					),
					status.processed,
					status.total
				);
	}
	return 'undo' === status.mode
		? sprintf(
				/* translators: 1: posts restored, 2: posts that failed. */
				__(
					'Undo finished: %1$d restored, %2$d failed.',
					'page-builder-sandwich'
				),
				status.restored,
				status.failed
			)
		: sprintf(
				/* translators: 1: posts converted, 2: posts left as they were, 3: posts that failed. */
				__(
					'Conversion finished: %1$d converted, %2$d unchanged, %3$d failed.',
					'page-builder-sandwich'
				),
				status.converted,
				status.skipped,
				status.failed
			);
}

/**
 * The "Legacy content" panel: progress of the conversion to blocks, failures, run and undo.
 *
 * @param {Object} props
 * @param {string} props.path REST path of the migration status (pbs/v1/migration).
 * @return {Element} The panel.
 */
export default function LegacyContent( { path } ) {
	const [ status, setStatus ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const timer = useRef( null );

	const load = useCallback(
		() =>
			apiFetch( { path } )
				.then( setStatus )
				.catch( ( error ) =>
					setNotice( { status: 'error', message: error.message } )
				),
		[ path ]
	);

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		if ( status && 'running' === status.status ) {
			timer.current = setTimeout( load, POLL_MS );
		}
		return () => clearTimeout( timer.current );
	}, [ status, load ] );

	const act = ( actionPath, done ) => {
		setBusy( true );
		setNotice( null );
		apiFetch( { path: actionPath, method: 'POST' } )
			.then( ( result ) => {
				setStatus( result );
				setNotice( { status: 'success', message: done } );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: error.message } )
			)
			.finally( () => setBusy( false ) );
	};

	const running = status && 'running' === status.status;

	return (
		<PanelBody
			title={ __( 'Legacy content', 'page-builder-sandwich' ) }
			initialOpen
		>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<p>
				{ __(
					'Pages made with earlier versions of Page Builder Sandwich are converted to blocks in the background. Every post is backed up first, so each conversion can be undone.',
					'page-builder-sandwich'
				) }
			</p>
			{ status && (
				<>
					<p className="pbs-admin__summary">
						{ summaryText( status ) }
					</p>
					{ running && status.total > 0 && (
						<ProgressBar
							value={ Math.round(
								( 100 * status.processed ) / status.total
							) }
						/>
					) }
					<p>
						{ sprintf(
							/* translators: %d: number of posts that still hold legacy content. */
							_n(
								'%d post still holds legacy content.',
								'%d posts still hold legacy content.',
								status.remaining,
								'page-builder-sandwich'
							),
							status.remaining
						) }{ ' ' }
						{ sprintf(
							/* translators: %d: number of posts with a backup. */
							_n(
								'%d post has a backup.',
								'%d posts have a backup.',
								status.backups,
								'page-builder-sandwich'
							),
							status.backups
						) }
					</p>
					{ status.failures.length > 0 && (
						<>
							<h3>
								{ sprintf(
									/* translators: %d: number of posts that failed. */
									_n(
										'%d post could not be handled',
										'%d posts could not be handled',
										status.failing,
										'page-builder-sandwich'
									),
									status.failing
								) }
							</h3>
							<ul className="pbs-admin__failures">
								{ status.failures.map( ( failure ) => (
									<li key={ failure.id }>
										{ failure.editUrl ? (
											<a href={ failure.editUrl }>
												{ failure.title ||
													sprintf(
														/* translators: %d: post ID. */
														__(
															'Post %d',
															'page-builder-sandwich'
														),
														failure.id
													) }
											</a>
										) : (
											sprintf(
												/* translators: %d: post ID. */
												__(
													'Post %d',
													'page-builder-sandwich'
												),
												failure.id
											)
										) }
										{ ': ' }
										{ failureText( failure.message ) }
									</li>
								) ) }
							</ul>
						</>
					) }
					<div className="pbs-admin__actions">
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy || running }
							onClick={ () =>
								act(
									path,
									__(
										'Conversion started. It continues in the background; you can leave this page.',
										'page-builder-sandwich'
									)
								)
							}
						>
							{ __(
								'Convert legacy content',
								'page-builder-sandwich'
							) }
						</Button>{ ' ' }
						<Button
							variant="secondary"
							isDestructive
							disabled={ busy || running || 0 === status.backups }
							onClick={ () => {
								// eslint-disable-next-line no-alert
								const sure = window.confirm(
									__(
										'Restore every converted post to its legacy content? Changes made to those posts since they were converted will be lost.',
										'page-builder-sandwich'
									)
								);
								if ( sure ) {
									act(
										path + '/undo',
										__(
											'Undo started. It continues in the background; you can leave this page.',
											'page-builder-sandwich'
										)
									);
								}
							} }
						>
							{ __(
								'Undo all conversions',
								'page-builder-sandwich'
							) }
						</Button>
					</div>
				</>
			) }
		</PanelBody>
	);
}
