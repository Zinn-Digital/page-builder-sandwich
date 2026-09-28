/**
 * pbs/post-meta editor: the server's own render, and which details to show.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { CheckboxControl, PanelBody, Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const ORDER = [
	'author',
	'date',
	'modified',
	'categories',
	'tags',
	'readingTime',
	'comments',
];

export default function Edit( { attributes, setAttributes, context } ) {
	const blockProps = useBlockProps();
	const labels = {
		author: __( 'Author', 'page-builder-sandwich' ),
		date: __( 'Published date', 'page-builder-sandwich' ),
		modified: __(
			'Updated date (when it differs)',
			'page-builder-sandwich'
		),
		categories: __( 'Categories', 'page-builder-sandwich' ),
		tags: __( 'Tags', 'page-builder-sandwich' ),
		readingTime: __( 'Reading time', 'page-builder-sandwich' ),
		comments: __( 'Comment count', 'page-builder-sandwich' ),
	};
	const items = attributes.items || [];
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Details to show', 'page-builder-sandwich' ) }
				>
					{ ORDER.map( ( key ) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={ key }
							label={ labels[ key ] }
							checked={ items.includes( key ) }
							onChange={ ( on ) =>
								setAttributes( {
									items: ORDER.filter( ( k ) =>
										k === key ? on : items.includes( k )
									),
								} )
							}
						/>
					) ) }
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="pbs/post-meta"
				attributes={ attributes }
				urlQueryArgs={
					context?.postId ? { post_id: context.postId } : undefined
				}
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Post details', 'page-builder-sandwich' ) }
						instructions={ __(
							'Choose at least one detail to show.',
							'page-builder-sandwich'
						) }
					/>
				) }
			/>
		</div>
	);
}
