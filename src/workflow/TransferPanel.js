import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	FormFileUpload,
	Notice,
	PanelBody,
	RadioControl,
	Spinner,
} from '@wordpress/components';

import { downloadJson, readJsonFile, summarise } from './transfer-lib';

/**
 * Import and export (pbs-g5): templates, patterns, the site design and the plugin settings as one
 * file.
 *
 * @return {Element} The panel.
 */
export default function TransferPanel() {
	const [ items, setItems ] = useState( null );
	const [ picked, setPicked ] = useState( {} );
	const [ withSettings, setWithSettings ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ pkg, setPkg ] = useState( null );
	const [ mode, setMode ] = useState( 'skip' );
	const [ importSettings, setImportSettings ] = useState( true );
	const [ media, setMedia ] = useState( false );
	const [ preview, setPreview ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/pbs/v1/transfer/items' } )
			.then( ( list ) => {
				setItems( list );
				setPicked(
					Object.fromEntries( list.map( ( i ) => [ i.id, true ] ) )
				);
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			);
	}, [] );

	const ids = useMemo(
		() =>
			Object.keys( picked )
				.filter( ( id ) => picked[ id ] )
				.map( Number ),
		[ picked ]
	);

	const doExport = () => {
		setBusy( true );
		setNotice( null );
		apiFetch( {
			path: '/pbs/v1/transfer/export',
			method: 'POST',
			data: { ids, settings: withSettings },
		} )
			.then( ( result ) => {
				downloadJson(
					result,
					`site-design-${ new Date().toISOString().slice( 0, 10 ) }.json`
				);
				setNotice( {
					status: 'success',
					message: sprintf(
						/* translators: %d: number of items exported. */
						_n(
							'Exported %d item.',
							'Exported %d items.',
							result.items.length,
							'page-builder-sandwich'
						),
						result.items.length
					),
				} );
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			)
			.finally( () => setBusy( false ) );
	};

	const runImport = ( dryRun ) => {
		setBusy( true );
		setNotice( null );
		apiFetch( {
			path: '/pbs/v1/transfer/import',
			method: 'POST',
			data: {
				package: pkg,
				mode,
				settings: importSettings,
				media,
				dryRun,
			},
		} )
			.then( ( report ) => {
				if ( dryRun ) {
					setPreview( report );
					return;
				}
				setPreview( null );
				setPkg( null );
				setNotice( {
					status: report.errors.length ? 'warning' : 'success',
					message: summarise( report, false ),
					errors: report.errors,
				} );
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			)
			.finally( () => setBusy( false ) );
	};

	const onFile = ( event ) => {
		setNotice( null );
		setPreview( null );
		readJsonFile( event.target.files[ 0 ] )
			.then( setPkg )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __(
						'That file could not be read as an export file.',
						'page-builder-sandwich'
					),
				} )
			);
	};

	return (
		<PanelBody
			title={ __( 'Import and export', 'page-builder-sandwich' ) }
			initialOpen={ false }
		>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					<p>{ notice.message }</p>
					{ notice.errors?.length > 0 && (
						<ul>
							{ notice.errors.map( ( e ) => (
								<li key={ e }>{ e }</li>
							) ) }
						</ul>
					) }
				</Notice>
			) }
			<h3>{ __( 'Export', 'page-builder-sandwich' ) }</h3>
			<p>
				{ __(
					'Download your templates, patterns and site design as one file, to import on another site.',
					'page-builder-sandwich'
				) }
			</p>
			{ null === items && <Spinner /> }
			{ items && 0 === items.length && (
				<p>
					{ __(
						'There are no templates or patterns to export yet.',
						'page-builder-sandwich'
					) }
				</p>
			) }
			{ items &&
				items.map( ( item ) => (
					<CheckboxControl
						__nextHasNoMarginBottom
						key={ item.id }
						label={ `${ item.title } (${ item.typeLabel })` }
						checked={ !! picked[ item.id ] }
						onChange={ ( on ) =>
							setPicked( { ...picked, [ item.id ]: on } )
						}
					/>
				) ) }
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ __( 'Include the settings', 'page-builder-sandwich' ) }
				checked={ withSettings }
				onChange={ setWithSettings }
			/>
			<Button
				variant="primary"
				isBusy={ busy }
				disabled={ busy || ( ! ids.length && ! withSettings ) }
				onClick={ doExport }
			>
				{ __( 'Download the export file', 'page-builder-sandwich' ) }
			</Button>

			<h3>{ __( 'Import', 'page-builder-sandwich' ) }</h3>
			<FormFileUpload
				__next40pxDefaultSize
				accept="application/json,.json"
				onChange={ onFile }
				render={ ( { openFileDialog } ) => (
					<Button variant="secondary" onClick={ openFileDialog }>
						{ __(
							'Choose an export file',
							'page-builder-sandwich'
						) }
					</Button>
				) }
			/>
			{ pkg && (
				<>
					<p>
						{ sprintf(
							/* translators: 1: number of items in the file, 2: the site it came from. */
							_n(
								'The file holds %1$d item from %2$s.',
								'The file holds %1$d items from %2$s.',
								pkg.items?.length ?? 0,
								'page-builder-sandwich'
							),
							pkg.items?.length ?? 0,
							pkg.source ?? ''
						) }
					</p>
					<RadioControl
						label={ __(
							'When an item with the same name already exists here',
							'page-builder-sandwich'
						) }
						selected={ mode }
						onChange={ setMode }
						options={ [
							{
								label: __(
									'Keep the one on this site',
									'page-builder-sandwich'
								),
								value: 'skip',
							},
							{
								label: __(
									'Replace it with the imported one',
									'page-builder-sandwich'
								),
								value: 'replace',
							},
							{
								label: __(
									'Import a copy beside it',
									'page-builder-sandwich'
								),
								value: 'duplicate',
							},
						] }
					/>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __(
							'Apply the settings in the file',
							'page-builder-sandwich'
						) }
						checked={ importSettings }
						onChange={ setImportSettings }
					/>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __(
							'Copy the images into this site’s media library',
							'page-builder-sandwich'
						) }
						checked={ media }
						onChange={ setMedia }
					/>
					{ preview && <p>{ summarise( preview, true ) }</p> }
					<Button
						variant="secondary"
						isBusy={ busy }
						disabled={ busy }
						onClick={ () => runImport( true ) }
					>
						{ __(
							'Show what will happen',
							'page-builder-sandwich'
						) }
					</Button>{ ' ' }
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy }
						onClick={ () => runImport( false ) }
					>
						{ __( 'Import', 'page-builder-sandwich' ) }
					</Button>
				</>
			) }
		</PanelBody>
	);
}
