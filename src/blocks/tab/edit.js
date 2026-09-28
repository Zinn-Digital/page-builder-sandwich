/**
 * pbs/tab editor: the panel's blocks (its title is edited in the parent's tab row, or here).
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

import { cls } from '../shared/cls';

export default function Edit( { attributes, setAttributes } ) {
	const innerBlocksProps = useInnerBlocksProps(
		useBlockProps( { className: cls( 'tab' ) } ),
		{ template: [ [ 'core/paragraph' ] ] }
	);
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Tab', 'page-builder-sandwich' ) }>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Title', 'page-builder-sandwich' ) }
						value={ ( attributes.title || '' ).replace(
							/<[^>]+>/g,
							''
						) }
						onChange={ ( title ) => setAttributes( { title } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}
