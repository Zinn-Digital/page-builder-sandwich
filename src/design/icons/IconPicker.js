/**
 * IconPicker (pbs-b2, pbs-r12): one tab per bundled set plus "My uploads", search with keywords
 * (optionally across every set), a virtualised keyboard grid, and custom SVG upload.
 *
 * `onSelect( { svg, ref, name } )` receives SANITISED inline SVG (sanitizeSvg, the twin of
 * Core\Svg::sanitize) sized 1em and painted with currentColor, plus a reference
 * (`<set>:<style>:<name>` or `upload:<id>`) the picker uses to show the current choice.
 *
 * ⭐ A set's JSON is fetched only when its tab is opened, or when the author turns on "Search
 * all sets". Nothing here runs on the front end.
 */
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	FormFileUpload,
	Notice,
	SearchControl,
	SelectControl,
	Spinner,
	TabPanel,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useMemo, useState, useCallback } from '@wordpress/element';

import IconGrid from './IconGrid';
import { SETS, firstStyles, loadSet, loadStyle, search } from './sets';
import { iconSvg, sanitizeSvg, sizedSvg } from './sanitize';

export const UPLOADS_PATH = '/pbs/v1/icons/uploads';

/**
 * Translated label of a style id.
 *
 * @param {string} style Style id.
 * @return {string} Label.
 */
export function styleLabel( style ) {
	const labels = {
		solid: __( 'Solid', 'page-builder-sandwich' ),
		regular: __( 'Regular', 'page-builder-sandwich' ),
		brands: __( 'Brands', 'page-builder-sandwich' ),
		outlined: __( 'Outlined', 'page-builder-sandwich' ),
		rounded: __( 'Rounded', 'page-builder-sandwich' ),
		sharp: __( 'Sharp', 'page-builder-sandwich' ),
		bold: __( 'Bold', 'page-builder-sandwich' ),
		fill: __( 'Fill', 'page-builder-sandwich' ),
		duotone: __( 'Duotone', 'page-builder-sandwich' ),
	};
	return labels[ style ] || style;
}

/**
 * Parse a stored reference.
 *
 * @param {string} ref Reference.
 * @return {{set:string,style:string,name:string}} Parts ('' when absent).
 */
export function parseRef( ref ) {
	const [ set = '', style = '', ...rest ] = String( ref || '' ).split( ':' );
	return { set, style, name: rest.join( ':' ) };
}

/** Cache of rendered cell SVGs (render is pure). */
const rendered = new Map();

function cellSvg( item ) {
	if ( ! rendered.has( item.key ) ) {
		rendered.set(
			item.key,
			item.svg ? item.svg : iconSvg( item.root, item.body )
		);
	}
	return rendered.get( item.key );
}

/**
 * Grid items for set entries in one style (or each entry's first style when style is '').
 *
 * @param {Object[]} entries Search results.
 * @param {Object}   sets    Loaded sets by id.
 * @param {string}   style   Style id or ''.
 * @return {Object[]} Items.
 */
export function toItems( entries, sets, style ) {
	const out = [];
	for ( const entry of entries ) {
		const set = sets[ entry.set ];
		const use =
			style && entry.styles.includes( style ) ? style : entry.styles[ 0 ];
		const body = entry.bodies[ use ];
		if ( set && body ) {
			out.push( {
				key: `${ entry.set }:${ use }:${ entry.name }`,
				name: entry.name,
				root: set.root,
				body,
			} );
		}
	}
	return out;
}

function SetPanel( { setId, value, onSelect } ) {
	const [ sets, setSets ] = useState( {} );
	const [ loadedStyles, setLoadedStyles ] = useState( {} );
	const [ error, setError ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ everywhere, setEverywhere ] = useState( false );
	const current = parseRef( value );
	const [ style, setStyle ] = useState(
		current.set === setId ? current.style : ''
	);

	const wanted = useMemo(
		() => ( everywhere ? SETS.map( ( s ) => s.id ) : [ setId ] ),
		[ everywhere, setId ]
	);
	const fail = () =>
		setError(
			__(
				'This icon set could not be loaded. Check your connection and try again.',
				'page-builder-sandwich'
			)
		);

	// Step 1: the indexes (names and search words) of the sets on screen.
	useEffect( () => {
		let live = true;
		wanted.forEach( ( id ) => {
			loadSet( id )
				.then( ( set ) => {
					if ( live ) {
						setSets( ( prev ) =>
							prev[ id ] ? prev : { ...prev, [ id ]: set }
						);
					}
				} )
				.catch( () => live && fail() );
		} );
		return () => {
			live = false;
		};
	}, [ wanted ] );

	const own = sets[ setId ];
	const activeStyle =
		style && own && own.styles.includes( style )
			? style
			: ( own && own.styles[ 0 ] ) || '';

	// Step 2: the drawings, only for the style(s) on screen.
	const needed = useMemo( () => {
		const list = [];
		for ( const id of wanted ) {
			const set = sets[ id ];
			if ( ! set ) {
				continue;
			}
			const styles = everywhere ? firstStyles( set ) : [ activeStyle ];
			styles.forEach( ( s ) => list.push( [ set, s ] ) );
		}
		return list;
	}, [ wanted, sets, everywhere, activeStyle ] );

	useEffect( () => {
		let live = true;
		needed.forEach( ( [ set, s ] ) => {
			const key = `${ set.id }/${ s }`;
			loadStyle( set, s )
				.then( () => {
					if ( live ) {
						setLoadedStyles( ( prev ) =>
							prev[ key ] ? prev : { ...prev, [ key ]: true }
						);
					}
				} )
				.catch( () => live && fail() );
		} );
		return () => {
			live = false;
		};
	}, [ needed ] );

	const ready =
		wanted.every( ( id ) => sets[ id ] ) &&
		needed.every( ( [ set, s ] ) => loadedStyles[ `${ set.id }/${ s }` ] );

	const items = useMemo( () => {
		if ( ! own ) {
			return [];
		}
		if ( everywhere ) {
			const all = wanted
				.filter( ( id ) => sets[ id ] )
				.flatMap( ( id ) => sets[ id ].entries );
			return toItems( search( all, query ), sets, '' );
		}
		return toItems(
			search( own.entries, query, activeStyle ),
			sets,
			activeStyle
		);
		// loadedStyles: bodies arrive by mutation, so a finished load must recompute.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ own, everywhere, wanted, sets, query, activeStyle, loadedStyles ] );

	const pick = useCallback(
		( item ) => {
			onSelect( {
				svg: iconSvg( item.root, item.body ),
				ref: item.key,
				name: item.name,
			} );
		},
		[ onSelect ]
	);

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}
	if ( ! own ) {
		return <Spinner />;
	}
	return (
		<div className="pbsw-icon-picker__panel">
			<div className="pbsw-icon-picker__tools">
				<SearchControl
					__nextHasNoMarginBottom
					label={ __( 'Search icons', 'page-builder-sandwich' ) }
					value={ query }
					onChange={ setQuery }
				/>
				{ ! everywhere && own.styles.length > 1 && (
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Style', 'page-builder-sandwich' ) }
						value={ activeStyle }
						options={ own.styles.map( ( s ) => ( {
							value: s,
							label: styleLabel( s ),
						} ) ) }
						onChange={ setStyle }
					/>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Search all sets', 'page-builder-sandwich' ) }
					checked={ everywhere }
					onChange={ setEverywhere }
				/>
			</div>
			<p className="pbsw-icon-picker__count" aria-live="polite">
				{ ready
					? sprintf(
							/* translators: %d: number of icons found. */
							__( '%d icons', 'page-builder-sandwich' ),
							items.length
						)
					: __( 'Loading icon sets…', 'page-builder-sandwich' ) }
			</p>
			<IconGrid
				items={ items }
				label={ sprintf(
					/* translators: %s: icon set name, e.g. Lucide. */
					__( '%s icons', 'page-builder-sandwich' ),
					everywhere
						? __( 'All sets', 'page-builder-sandwich' )
						: own.label
				) }
				selectedKey={ value }
				onPick={ pick }
				idPrefix={ `pbsw-icon-${ setId }` }
				renderIcon={ cellSvg }
			/>
		</div>
	);
}

/**
 * Read an uploaded file as text.
 *
 * @param {File} file File.
 * @return {Promise<string>} Text.
 */
export function readText( file ) {
	if ( file && typeof file.text === 'function' ) {
		return file.text();
	}
	return new Promise( ( resolve, reject ) => {
		const reader = new window.FileReader();
		reader.onload = () => resolve( String( reader.result ) );
		reader.onerror = () => reject( reader.error );
		reader.readAsText( file );
	} );
}

function UploadsPanel( { value, onSelect } ) {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const canUpload = !! ( window.pbswIcons && window.pbswIcons.canUpload );
	const current = parseRef( value );

	useEffect( () => {
		let live = true;
		apiFetch( { path: UPLOADS_PATH } )
			.then( ( list ) => live && setItems( list ) )
			.catch( () => live && setItems( [] ) );
		return () => {
			live = false;
		};
	}, [] );

	const upload = async ( event ) => {
		const file = event.target.files && event.target.files[ 0 ];
		event.target.value = '';
		if ( ! file ) {
			return;
		}
		setError( '' );
		const text = await readText( file );
		// The server sanitises again and is the authority; this only saves a round trip.
		if ( ! sanitizeSvg( text ) ) {
			setError(
				__(
					'This file is not a valid SVG image.',
					'page-builder-sandwich'
				)
			);
			return;
		}
		setBusy( true );
		try {
			const item = await apiFetch( {
				path: UPLOADS_PATH,
				method: 'POST',
				data: { name: file.name, svg: text },
			} );
			setItems( ( prev ) => [ item, ...( prev || [] ) ] );
			onSelect( {
				svg: sizedSvg( item.svg ),
				ref: `upload::${ item.id }`,
				name: item.name,
			} );
		} catch ( e ) {
			setError(
				( e && e.message ) ||
					__(
						'The icon could not be uploaded.',
						'page-builder-sandwich'
					)
			);
		} finally {
			setBusy( false );
		}
	};

	const remove = async ( id ) => {
		setError( '' );
		try {
			await apiFetch( {
				path: `${ UPLOADS_PATH }/${ id }`,
				method: 'DELETE',
			} );
			setItems( ( prev ) =>
				( prev || [] ).filter( ( i ) => i.id !== id )
			);
		} catch ( e ) {
			setError(
				( e && e.message ) ||
					__(
						'The icon could not be deleted.',
						'page-builder-sandwich'
					)
			);
		}
	};

	const gridItems = ( items || [] ).map( ( i ) => ( {
		key: `upload::${ i.id }`,
		name: i.name,
		svg: sanitizeSvg( i.svg ),
		id: i.id,
	} ) );

	return (
		<div className="pbsw-icon-picker__panel">
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<div className="pbsw-icon-picker__tools">
				{ canUpload && (
					<FormFileUpload
						__next40pxDefaultSize
						accept=".svg,image/svg+xml"
						onChange={ upload }
						variant="secondary"
						disabled={ busy }
					>
						{ __( 'Upload SVG', 'page-builder-sandwich' ) }
					</FormFileUpload>
				) }
				{ current.set === 'upload' && (
					<Button
						__next40pxDefaultSize
						variant="tertiary"
						isDestructive
						onClick={ () => remove( Number( current.name ) ) }
					>
						{ __(
							'Delete selected upload',
							'page-builder-sandwich'
						) }
					</Button>
				) }
				{ busy && <Spinner /> }
			</div>
			<p className="pbsw-icon-picker__hint">
				{ __(
					'Scripts, links and external references are removed from uploaded SVG files before they are saved.',
					'page-builder-sandwich'
				) }
			</p>
			{ items === null ? (
				<Spinner />
			) : (
				<IconGrid
					items={ gridItems }
					label={ __( 'My uploads', 'page-builder-sandwich' ) }
					selectedKey={ value }
					onPick={ ( item ) =>
						onSelect( {
							svg: sizedSvg( item.svg ),
							ref: item.key,
							name: item.name,
						} )
					}
					idPrefix="pbsw-icon-upload"
					renderIcon={ cellSvg }
				/>
			) }
		</div>
	);
}

export default function IconPicker( { value = '', onSelect } ) {
	const current = parseRef( value );
	const tabs = [
		...SETS.map( ( s ) => ( { name: s.id, title: s.label } ) ),
		{ name: 'upload', title: __( 'My uploads', 'page-builder-sandwich' ) },
	];
	const initial = tabs.some( ( t ) => t.name === current.set )
		? current.set
		: SETS[ 0 ].id;
	return (
		<div className="pbsw-icon-picker">
			<TabPanel tabs={ tabs } initialTabName={ initial }>
				{ ( tab ) =>
					tab.name === 'upload' ? (
						<UploadsPanel value={ value } onSelect={ onSelect } />
					) : (
						<SetPanel
							key={ tab.name }
							setId={ tab.name }
							value={ value }
							onSelect={ onSelect }
						/>
					)
				}
			</TabPanel>
		</div>
	);
}
