/**
 * pbs/related-posts editor: the server's own render (live posts), options in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes, context } ) {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Related posts', 'page-builder-sandwich' ) }
				>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'How many', 'page-builder-sandwich' ) }
						min={ 1 }
						max={ 12 }
						value={ attributes.count }
						onChange={ ( count ) => setAttributes( { count } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Related by', 'page-builder-sandwich' ) }
						value={ attributes.by }
						options={ [
							{
								value: 'both',
								label: __(
									'Categories or tags',
									'page-builder-sandwich'
								),
							},
							{
								value: 'categories',
								label: __(
									'Categories',
									'page-builder-sandwich'
								),
							},
							{
								value: 'tags',
								label: __( 'Tags', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( by ) => setAttributes( { by } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'When nothing is related, show the newest instead',
							'page-builder-sandwich'
						) }
						checked={ !! attributes.fallback }
						onChange={ ( fallback ) =>
							setAttributes( { fallback } )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Heading', 'page-builder-sandwich' ) }
						help={ __(
							'Leave empty for "Related posts".',
							'page-builder-sandwich'
						) }
						value={ attributes.heading }
						onChange={ ( heading ) => setAttributes( { heading } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Heading level', 'page-builder-sandwich' ) }
						value={ String( attributes.headingLevel ) }
						options={ [ 2, 3, 4, 5, 6 ].map( ( n ) => ( {
							value: String( n ),
							label: `H${ n }`,
						} ) ) }
						onChange={ ( v ) =>
							setAttributes( { headingLevel: Number( v ) } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show the featured image',
							'page-builder-sandwich'
						) }
						checked={ !! attributes.showImage }
						onChange={ ( v ) => setAttributes( { showImage: v } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show the date', 'page-builder-sandwich' ) }
						checked={ !! attributes.showDate }
						onChange={ ( v ) => setAttributes( { showDate: v } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show a summary',
							'page-builder-sandwich'
						) }
						checked={ !! attributes.showExcerpt }
						onChange={ ( v ) =>
							setAttributes( { showExcerpt: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="pbs/related-posts"
				attributes={ attributes }
				urlQueryArgs={
					context?.postId ? { post_id: context.postId } : undefined
				}
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Related posts', 'page-builder-sandwich' ) }
						instructions={ __(
							'No posts share this post’s categories or tags yet. When some do, they show here.',
							'page-builder-sandwich'
						) }
					/>
				) }
			/>
		</div>
	);
}
