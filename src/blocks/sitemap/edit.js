/**
 * pbs/sitemap editor: the server's own render (live pages), the content types in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	CheckboxControl,
	PanelBody,
	Placeholder,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const types = useSelect(
		( select ) =>
			(
				select( coreStore ).getPostTypes( { per_page: -1 } ) || []
			).filter( ( t ) => t.viewable && t.slug !== 'attachment' ),
		[]
	);
	const chosen = attributes.postTypes || [];
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Sitemap', 'page-builder-sandwich' ) }>
					{ types.map( ( t ) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={ t.slug }
							label={ t.name }
							checked={ chosen.includes( t.slug ) }
							onChange={ ( on ) =>
								setAttributes( {
									postTypes: on
										? [ ...chosen, t.slug ]
										: chosen.filter(
												( s ) => s !== t.slug
											),
								} )
							}
						/>
					) ) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show a heading for each type',
							'page-builder-sandwich'
						) }
						checked={ !! attributes.showHeadings }
						onChange={ ( showHeadings ) =>
							setAttributes( { showHeadings } )
						}
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
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="pbs/sitemap"
				attributes={ attributes }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Sitemap', 'page-builder-sandwich' ) }
						instructions={ __(
							'Nothing published yet in the types you chose.',
							'page-builder-sandwich'
						) }
					/>
				) }
			/>
		</div>
	);
}
