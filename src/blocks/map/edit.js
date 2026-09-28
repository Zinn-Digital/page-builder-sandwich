/**
 * pbs/map editor: the place (latitude, longitude, zoom) and its name in the sidebar; the canvas
 * shows the same click-to-load card visitors get, and the author can load the live map to check it.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	ExternalLink,
	PanelBody,
	Placeholder,
	RangeControl,
	TextControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import { mapUrls, parseCoordinates } from './urls';

export default function Edit( { attributes, setAttributes } ) {
	const { lat, lng, zoom, label } = attributes;
	const [ preview, setPreview ] = useState( false );
	const [ paste, setPaste ] = useState( '' );
	const has = Number.isFinite( lat ) && Number.isFinite( lng );
	const urls = has ? mapUrls( lat, lng, zoom ?? 14 ) : null;
	const blockProps = useBlockProps( { className: cls( 'map' ) } );

	const sidebar = (
		<InspectorControls>
			<PanelBody title={ __( 'Place', 'page-builder-sandwich' ) }>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __(
						'Paste coordinates or an OpenStreetMap link',
						'page-builder-sandwich'
					) }
					help={ __(
						'On openstreetmap.org, find the place, then copy the address from the browser bar, or right-click the map and choose "Show address".',
						'page-builder-sandwich'
					) }
					value={ paste }
					onChange={ ( v ) => {
						setPaste( v );
						const found = parseCoordinates( v );
						if ( found ) {
							setAttributes( found );
						}
					} }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					label={ __( 'Latitude', 'page-builder-sandwich' ) }
					value={ lat ?? '' }
					onChange={ ( v ) =>
						setAttributes( {
							lat: v === '' ? undefined : Number( v ),
						} )
					}
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					label={ __( 'Longitude', 'page-builder-sandwich' ) }
					value={ lng ?? '' }
					onChange={ ( v ) =>
						setAttributes( {
							lng: v === '' ? undefined : Number( v ),
						} )
					}
				/>
				<RangeControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Zoom', 'page-builder-sandwich' ) }
					min={ 1 }
					max={ 19 }
					value={ zoom ?? 14 }
					onChange={ ( v ) => setAttributes( { zoom: v } ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Name of the place', 'page-builder-sandwich' ) }
					help={ __(
						'Shown under the map and read out by screen readers.',
						'page-builder-sandwich'
					) }
					value={ label }
					onChange={ ( v ) => setAttributes( { label: v } ) }
				/>
			</PanelBody>
		</InspectorControls>
	);

	if ( ! has ) {
		return (
			<div { ...blockProps }>
				{ sidebar }
				<Placeholder
					icon="location-alt"
					label={ __( 'Map', 'page-builder-sandwich' ) }
					instructions={ __(
						'Paste coordinates (for example 51.5074, -0.1278) or an OpenStreetMap link.',
						'page-builder-sandwich'
					) }
				>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Coordinates or link',
							'page-builder-sandwich'
						) }
						value={ paste }
						onChange={ ( v ) => {
							setPaste( v );
							const found = parseCoordinates( v );
							if ( found ) {
								setAttributes( found );
							}
						} }
					/>
				</Placeholder>
			</div>
		);
	}

	return (
		<figure { ...blockProps }>
			{ sidebar }
			<div className={ cls( 'map__frame' ) }>
				{ preview ? (
					<iframe
						className={ cls( 'map__iframe' ) }
						title={ label || __( 'Map', 'page-builder-sandwich' ) }
						src={ urls.embed }
					/>
				) : (
					<div className={ cls( 'map__consent' ) }>
						<p className={ cls( 'map__notice' ) }>
							{ __(
								'Showing the map loads it from OpenStreetMap.',
								'page-builder-sandwich'
							) }
						</p>
						<Button
							variant="primary"
							onClick={ () => setPreview( true ) }
						>
							{ __( 'Preview the map', 'page-builder-sandwich' ) }
						</Button>
						<ExternalLink href={ urls.link }>
							{ sprintf(
								/* translators: 1: latitude, 2: longitude. */
								__(
									'Check %1$s, %2$s on OpenStreetMap',
									'page-builder-sandwich'
								),
								lat,
								lng
							) }
						</ExternalLink>
					</div>
				) }
			</div>
			{ label && (
				<figcaption className={ cls( 'map__caption' ) }>
					{ label }
				</figcaption>
			) }
		</figure>
	);
}
