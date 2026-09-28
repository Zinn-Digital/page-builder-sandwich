/**
 * pbs/booking editor: the service and the booking page address; the canvas shows the same
 * click-to-load card visitors get, and the author can load the live calendar to check it.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import { PROVIDERS, bookingUrl, embedUrl } from './urls';

export default function Edit( { attributes, setAttributes } ) {
	const { provider, url, label } = attributes;
	const [ preview, setPreview ] = useState( false );
	const valid = bookingUrl( provider, url );
	const name = PROVIDERS[ provider ]?.name || provider;
	const blockProps = useBlockProps( { className: cls( 'booking' ) } );

	return (
		<figure { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Booking calendar', 'page-builder-sandwich' ) }
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Service', 'page-builder-sandwich' ) }
						value={ provider }
						options={ Object.entries( PROVIDERS ).map(
							( [ value, p ] ) => ( {
								value,
								label: p.name,
							} )
						) }
						onChange={ ( v ) => setAttributes( { provider: v } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						type="url"
						label={ __(
							'Booking page address',
							'page-builder-sandwich'
						) }
						help={ sprintf(
							/* translators: %s: an example booking page address. */
							__( 'For example %s', 'page-builder-sandwich' ),
							PROVIDERS[ provider ]?.example
						) }
						value={ url }
						onChange={ ( v ) => setAttributes( { url: v.trim() } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Title', 'page-builder-sandwich' ) }
						help={ __(
							'What the calendar is for, read out by screen readers and shown under it.',
							'page-builder-sandwich'
						) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ url && ! valid && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: the booking service, e.g. "Calendly". */
						__(
							'This is not a %s booking page address, so nothing will show on the page.',
							'page-builder-sandwich'
						),
						name
					) }
				</Notice>
			) }
			<div className={ cls( 'booking__frame' ) }>
				{ preview && valid ? (
					<iframe
						className={ cls( 'booking__iframe' ) }
						title={ label || name }
						src={ embedUrl( provider, valid ) }
					/>
				) : (
					<div className={ cls( 'booking__consent' ) }>
						<p className={ cls( 'booking__notice' ) }>
							{ sprintf(
								/* translators: %s: the booking service, e.g. "Calendly". */
								__(
									'Showing the booking calendar loads it from %s.',
									'page-builder-sandwich'
								),
								name
							) }
						</p>
						<Button
							variant="primary"
							disabled={ ! valid }
							accessibleWhenDisabled
							onClick={ () => setPreview( true ) }
						>
							{ __(
								'Preview the calendar',
								'page-builder-sandwich'
							) }
						</Button>
					</div>
				) }
			</div>
			{ label && (
				<figcaption className={ cls( 'booking__caption' ) }>
					{ label }
				</figcaption>
			) }
		</figure>
	);
}
