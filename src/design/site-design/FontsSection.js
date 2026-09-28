/**
 * Fonts (pbs-d4): the heading and body fonts, and the site's own font stacks. Uploaded and Adobe
 * fonts (Pro, pbs-r13) appear here as soon as they are activated.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { plus, trash } from '@wordpress/icons';
import { Loading, ResultNotice, SaveRow, runRequest } from './components';
import { globals, saveGlobals } from './globals';
import { fontOptions, isFontStack, slugify } from './lib';

export default function FontsSection() {
	const { data } = globals.use();
	const [ custom, setCustom ] = useState( null );
	const [ body, setBody ] = useState( '' );
	const [ heading, setHeading ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		if ( data ) {
			setCustom( data.fonts.custom );
			setBody( data.bodyFont );
			setHeading( data.headingFont );
		}
	}, [ data ] );

	if ( ! data || custom === null ) {
		return <Loading />;
	}
	const options = [
		{
			label: __( 'Theme default', 'page-builder-sandwich' ),
			value: '',
		},
		...fontOptions( { theme: data.fonts.theme, custom } ),
	];
	const dirty =
		JSON.stringify( custom ) !== JSON.stringify( data.fonts.custom ) ||
		body !== data.bodyFont ||
		heading !== data.headingFont;
	const bad = custom.find( ( f ) => ! isFontStack( f.fontFamily ) );
	const error = bad
		? sprintf(
				/* translators: %s: font name. */
				__(
					'The font stack for "%s" may contain only font names, quotes, commas and spaces.',
					'page-builder-sandwich'
				),
				bad.name || bad.slug
			)
		: '';
	const taken = [ ...data.fonts.theme, ...custom ].map( ( f ) => f.slug );
	const update = ( i, patch ) =>
		setCustom(
			custom.map( ( f, j ) => ( i === j ? { ...f, ...patch } : f ) )
		);

	return (
		<div className="pbsw-sd-section">
			<ResultNotice
				notice={ notice }
				onRemove={ () => setNotice( null ) }
			/>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Heading font', 'page-builder-sandwich' ) }
				value={ heading }
				options={ options }
				onChange={ setHeading }
			/>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Body font', 'page-builder-sandwich' ) }
				value={ body }
				options={ options }
				onChange={ setBody }
			/>
			<h3 className="pbsw-sd-h">
				{ __( 'Theme fonts', 'page-builder-sandwich' ) }
			</h3>
			<ul className="pbsw-sd-fonts">
				{ data.fonts.theme.map( ( f ) => (
					<li key={ f.slug } style={ { fontFamily: f.fontFamily } }>
						{ f.name }
					</li>
				) ) }
			</ul>
			<h3 className="pbsw-sd-h">
				{ __( 'Your fonts', 'page-builder-sandwich' ) }
			</h3>
			<div className="pbsw-sd-list">
				{ custom.map( ( f, i ) => (
					<div className="pbsw-sd-entry" key={ `${ f.slug }-${ i }` }>
						<div className="pbsw-sd-row">
							<TextControl
								__next40pxDefaultSize
								label={ __( 'Name', 'page-builder-sandwich' ) }
								value={ f.name }
								onChange={ ( name ) => update( i, { name } ) }
							/>
							<TextControl
								__next40pxDefaultSize
								label={ __(
									'Font stack',
									'page-builder-sandwich'
								) }
								value={ f.fontFamily }
								onChange={ ( fontFamily ) =>
									update( i, { fontFamily } )
								}
								dir="ltr"
							/>
							<Button
								icon={ trash }
								size="small"
								label={ sprintf(
									/* translators: %s: font name. */
									__( 'Remove %s', 'page-builder-sandwich' ),
									f.name || f.slug
								) }
								onClick={ () =>
									setCustom(
										custom.filter( ( _, j ) => j !== i )
									)
								}
							/>
						</div>
						{ f.hasFiles && (
							<p className="pbsw-sd-help pbsw-sd-entry__note">
								{ __(
									'Uploaded font: its files come from the font library.',
									'page-builder-sandwich'
								) }
							</p>
						) }
					</div>
				) ) }
				<Button
					icon={ plus }
					variant="secondary"
					onClick={ () =>
						setCustom( [
							...custom,
							{
								slug: slugify( 'system-sans', taken ),
								name: __(
									'System sans-serif',
									'page-builder-sandwich'
								),
								fontFamily:
									'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
							},
						] )
					}
				>
					{ __( 'Add font stack', 'page-builder-sandwich' ) }
				</Button>
			</div>
			<SaveRow
				dirty={ dirty }
				saving={ saving }
				error={ error }
				onSave={ () =>
					runRequest(
						setSaving,
						setNotice,
						() =>
							saveGlobals( {
								fonts: {
									custom: custom.map(
										( { slug, name, fontFamily } ) => ( {
											slug,
											name,
											fontFamily,
										} )
									),
								},
								bodyFont: body,
								headingFont: heading,
							} ),
						__( 'Fonts saved.', 'page-builder-sandwich' )
					)
				}
			/>
		</div>
	);
}
