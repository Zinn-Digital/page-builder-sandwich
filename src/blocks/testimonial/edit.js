/**
 * pbs/testimonial editor: the front end's own markup, the quote, name and role edited in place;
 * photo, rating and layout in the sidebar.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import { Icon, Sr } from '../shared/kit';

export default function Edit( { attributes, setAttributes } ) {
	const {
		quote,
		name,
		role,
		imageUrl,
		imageId,
		imageAlt,
		rating,
		layout,
		align,
	} = attributes;
	const blockProps = useBlockProps( {
		className: cls(
			'testimonial',
			`testimonial--${ layout }`,
			`testimonial--${ align }`
		),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Testimonial', 'page-builder-sandwich' ) }
				>
					<MediaUploadCheck>
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							value={ imageId }
							onSelect={ ( m ) =>
								setAttributes( {
									imageUrl:
										( m.sizes &&
											m.sizes.thumbnail &&
											m.sizes.thumbnail.url ) ||
										m.url,
									imageId: m.id,
								} )
							}
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open }>
									{ imageUrl
										? __(
												'Replace photo',
												'page-builder-sandwich'
											)
										: __(
												'Choose photo',
												'page-builder-sandwich'
											) }
								</Button>
							) }
						/>
					</MediaUploadCheck>
					{ imageUrl && (
						<>
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () =>
									setAttributes( {
										imageUrl: '',
										imageId: 0,
										imageAlt: '',
									} )
								}
							>
								{ __(
									'Remove photo',
									'page-builder-sandwich'
								) }
							</Button>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Photo description',
									'page-builder-sandwich'
								) }
								help={ __(
									'Leave empty when the photo only shows the person named next to it.',
									'page-builder-sandwich'
								) }
								value={ imageAlt }
								onChange={ ( v ) =>
									setAttributes( { imageAlt: v } )
								}
							/>
						</>
					) }
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Star rating (0 = none)',
							'page-builder-sandwich'
						) }
						min={ 0 }
						max={ 5 }
						value={ rating }
						onChange={ ( v ) =>
							setAttributes( { rating: v || 0 } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Layout', 'page-builder-sandwich' ) }
						value={ layout }
						options={ [
							{
								value: 'stacked',
								label: __(
									'Name under the quote',
									'page-builder-sandwich'
								),
							},
							{
								value: 'side',
								label: __(
									'Photo beside the quote',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( v ) => setAttributes( { layout: v } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Alignment', 'page-builder-sandwich' ) }
						value={ align }
						options={ [
							{
								value: 'start',
								label: __( 'Start', 'page-builder-sandwich' ),
							},
							{
								value: 'center',
								label: __( 'Centre', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( v ) => setAttributes( { align: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<figure { ...blockProps }>
				{ rating > 0 && (
					<p className={ cls( 'testimonial__rating' ) }>
						<span
							className={ cls( 'testimonial__stars' ) }
							aria-hidden="true"
						>
							{ [ 1, 2, 3, 4, 5 ].map( ( n ) => (
								<Icon
									key={ n }
									builtin={ n <= rating ? 'star-f' : 'star' }
								/>
							) ) }
						</span>
						<Sr>
							{ sprintf(
								/* translators: %d: number of stars, 1 to 5. */
								__(
									'Rated %d out of 5',
									'page-builder-sandwich'
								),
								rating
							) }
						</Sr>
					</p>
				) }
				<blockquote className={ cls( 'testimonial__quote' ) }>
					<RichText
						tagName="p"
						value={ quote }
						onChange={ ( v ) => setAttributes( { quote: v } ) }
						placeholder={ __(
							'What the customer said…',
							'page-builder-sandwich'
						) }
					/>
				</blockquote>
				<figcaption className={ cls( 'testimonial__author' ) }>
					{ imageUrl && (
						<img
							className={ cls( 'testimonial__photo' ) }
							src={ imageUrl }
							alt={ imageAlt }
							width="56"
							height="56"
						/>
					) }
					<span className={ cls( 'testimonial__who' ) }>
						<RichText
							tagName="span"
							className={ cls( 'testimonial__name' ) }
							value={ name }
							onChange={ ( v ) => setAttributes( { name: v } ) }
							placeholder={ __(
								'Name',
								'page-builder-sandwich'
							) }
							allowedFormats={ [] }
						/>
						<RichText
							tagName="span"
							className={ cls( 'testimonial__role' ) }
							value={ role }
							onChange={ ( v ) => setAttributes( { role: v } ) }
							placeholder={ __(
								'Role or company',
								'page-builder-sandwich'
							) }
							allowedFormats={ [ 'core/link' ] }
						/>
					</span>
				</figcaption>
			</figure>
		</>
	);
}
