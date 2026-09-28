/**
 * Colours (pbs-d4): the theme's palette (editable — the change is saved as the site's own
 * version of the theme colour, exactly as the Site Editor does) and the site's own colours.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import { plus, trash } from '@wordpress/icons';
import { ColorField, Loading, ResultNotice, SaveRow, runRequest } from './components';
import { globals, saveGlobals } from './globals';
import { firstInvalidColor, slugify } from './lib';

/**
 * One editable colour list.
 *
 * @param {Object}   props
 * @param {Array}    props.items    Colours.
 * @param {Function} props.onChange New list.
 * @param {boolean}  props.canAdd   The list takes new entries (the site's own colours).
 * @param {Array}    props.taken    Slugs used anywhere (for new slugs).
 */
function ColorList( { items, onChange, canAdd, taken } ) {
	const update = ( i, patch ) =>
		onChange( items.map( ( c, j ) => ( i === j ? { ...c, ...patch } : c ) ) );
	return (
		<div className="pbsw-sd-list">
			{ items.length === 0 && (
				<p className="pbsw-sd-empty">
					{ __( 'No colours yet.', 'page-builder-sandwich' ) }
				</p>
			) }
			{ items.map( ( c, i ) => (
				<div className="pbsw-sd-row" key={ `${ c.slug }-${ i }` }>
					<ColorField
						label={ c.name || c.slug }
						value={ c.color }
						onChange={ ( color ) => update( i, { color } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Name', 'page-builder-sandwich' ) }
						hideLabelFromVision
						value={ c.name }
						onChange={ ( name ) => update( i, { name } ) }
						disabled={ ! canAdd }
					/>
					{ canAdd && (
						<Button
							icon={ trash }
							size="small"
							label={ sprintf(
								/* translators: %s: colour name. */
								__( 'Remove %s', 'page-builder-sandwich' ),
								c.name || c.slug
							) }
							onClick={ () =>
								onChange( items.filter( ( _, j ) => j !== i ) )
							}
						/>
					) }
				</div>
			) ) }
			{ canAdd && (
				<Button
					icon={ plus }
					variant="secondary"
					onClick={ () => {
						const name = sprintf(
							/* translators: %d: a number. */
							__( 'Colour %d', 'page-builder-sandwich' ),
							items.length + 1
						);
						onChange( [
							...items,
							{
								slug: slugify( name, taken ),
								name,
								color: '#1e73be',
							},
						] );
					} }
				>
					{ __( 'Add colour', 'page-builder-sandwich' ) }
				</Button>
			) }
		</div>
	);
}

export default function ColorsSection() {
	const { data } = globals.use();
	const [ theme, setTheme ] = useState( null );
	const [ custom, setCustom ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		if ( data ) {
			setTheme( data.colors.theme );
			setCustom( data.colors.custom );
		}
	}, [ data ] );

	if ( ! data || theme === null ) {
		return <Loading />;
	}
	const dirty =
		JSON.stringify( theme ) !== JSON.stringify( data.colors.theme ) ||
		JSON.stringify( custom ) !== JSON.stringify( data.colors.custom );
	const bad = firstInvalidColor( [ ...theme, ...custom ] );
	let error = '';
	if ( bad === '#dup' ) {
		error = __(
			'Two colours have the same name. Rename one.',
			'page-builder-sandwich'
		);
	} else if ( bad ) {
		error = sprintf(
			/* translators: %s: colour name. */
			__( '"%s" is not a valid colour.', 'page-builder-sandwich' ),
			bad
		);
	}
	const taken = [ ...theme, ...custom, ...data.colors.default ].map(
		( c ) => c.slug
	);

	return (
		<div className="pbsw-sd-section">
			<ResultNotice notice={ notice } onRemove={ () => setNotice( null ) } />
			<h3 className="pbsw-sd-h">
				{ __( 'Theme colours', 'page-builder-sandwich' ) }
			</h3>
			<p className="pbsw-sd-help">
				{ __(
					'Your theme’s palette. A change here is saved as your site’s version of the colour, the same way the Site Editor saves it, and applies everywhere the colour is used.',
					'page-builder-sandwich'
				) }
			</p>
			<ColorList items={ theme } onChange={ setTheme } taken={ taken } />
			<Button
				variant="link"
				disabled={ saving }
				onClick={ () =>
					runRequest(
						setSaving,
						setNotice,
						() => saveGlobals( { colors: { theme: [] } } ),
						__(
							'Theme colours are back to the theme’s own values.',
							'page-builder-sandwich'
						)
					)
				}
			>
				{ __( 'Reset theme colours', 'page-builder-sandwich' ) }
			</Button>
			<h3 className="pbsw-sd-h">
				{ __( 'Your colours', 'page-builder-sandwich' ) }
			</h3>
			<ColorList
				items={ custom }
				onChange={ setCustom }
				taken={ taken }
				canAdd
			/>
			<SaveRow
				dirty={ dirty }
				saving={ saving }
				error={ error }
				onSave={ () =>
					runRequest(
						setSaving,
						setNotice,
						() => saveGlobals( { colors: { theme, custom } } ),
						__( 'Colours saved.', 'page-builder-sandwich' )
					)
				}
			/>
		</div>
	);
}
