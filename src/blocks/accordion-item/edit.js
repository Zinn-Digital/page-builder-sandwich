/**
 * pbs/accordion-item editor: the title row as visitors see it (edited in place) and the content
 * below it, always shown while editing so it can be edited.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

import { cls } from '../shared/cls';

export default function Edit( { attributes, setAttributes } ) {
	const { title, open, level } = attributes;
	const blockProps = useBlockProps( { className: cls( 'accordion-item' ) } );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: cls( 'accordion-item__content' ) },
		{ template: [ [ 'core/paragraph' ] ] }
	);
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Item', 'page-builder-sandwich' ) }>
					<ToggleControl
						label={ __(
							'Open when the page loads',
							'page-builder-sandwich'
						) }
						checked={ open }
						onChange={ ( value ) =>
							setAttributes( { open: value } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __(
							'Title is a heading',
							'page-builder-sandwich'
						) }
						help={ __(
							'Make the title a heading when the items are sections of the page, so visitors can jump between them.',
							'page-builder-sandwich'
						) }
						value={ String( level ) }
						options={ [
							{
								value: '0',
								label: __( 'No', 'page-builder-sandwich' ),
							},
							...[ 2, 3, 4, 5, 6 ].map( ( n ) => ( {
								value: String( n ),
								label: sprintf(
									/* translators: %d: a heading level, 2 to 6. */
									__( 'Heading %d', 'page-builder-sandwich' ),
									n
								),
							} ) ),
						] }
						onChange={ ( value ) =>
							setAttributes( { level: Number( value ) } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className={ cls( 'accordion-item__summary' ) }>
					<RichText
						tagName="span"
						className={ cls( 'accordion-item__title' ) }
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
						placeholder={ __(
							'Question or section title',
							'page-builder-sandwich'
						) }
						allowedFormats={ [ 'core/italic', 'core/bold' ] }
					/>
					<svg
						className={ cls( 'accordion-item__icon' ) }
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 24 24"
						width="20"
						height="20"
						fill="none"
						stroke="currentColor"
						strokeWidth="2"
						strokeLinecap="round"
						strokeLinejoin="round"
						aria-hidden="true"
						focusable="false"
					>
						<path d="m6 9 6 6 6-6" />
					</svg>
				</div>
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
