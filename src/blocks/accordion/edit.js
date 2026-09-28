/**
 * pbs/accordion editor: its items, and the two options that matter.
 */
import { __ } from '@wordpress/i18n';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';

import { cls } from '../shared/cls';

const TEMPLATE = [
	[ 'pbs/accordion-item', { open: true }, [ [ 'core/paragraph' ] ] ],
	[ 'pbs/accordion-item', {}, [ [ 'core/paragraph' ] ] ],
];

export default function Edit( { attributes, setAttributes } ) {
	const innerBlocksProps = useInnerBlocksProps(
		useBlockProps( { className: cls( 'accordion' ) } ),
		{
			allowedBlocks: [ 'pbs/accordion-item' ],
			template: TEMPLATE,
			renderAppender: InnerBlocks.ButtonBlockAppender,
		}
	);
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Accordion', 'page-builder-sandwich' ) }>
					<ToggleControl
						label={ __(
							'Only one item open at a time',
							'page-builder-sandwich'
						) }
						checked={ attributes.single }
						onChange={ ( single ) => setAttributes( { single } ) }
					/>
					<ToggleControl
						label={ __(
							'Mark up as FAQ for search engines',
							'page-builder-sandwich'
						) }
						help={ __(
							'Adds FAQ structured data built from the titles (questions) and their text (answers).',
							'page-builder-sandwich'
						) }
						checked={ attributes.faq }
						onChange={ ( faq ) => setAttributes( { faq } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}
