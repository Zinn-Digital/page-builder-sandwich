/**
 * pbs/reading-progress editor: the bar as it looks part-filled, with a note of where it sits on
 * the page (it is fixed to the window edge there, which the editor canvas cannot show).
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

import { cls } from '../shared/cls';

export default function Edit( { attributes, setAttributes } ) {
	const { position } = attributes;
	const blockProps = useBlockProps( {
		className: cls( 'reading-progress', 'reading-progress--preview' ),
	} );
	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Reading progress bar',
						'page-builder-sandwich'
					) }
				>
					<SelectControl
						__next40pxDefaultSize
						label={ __(
							'Edge of the window',
							'page-builder-sandwich'
						) }
						value={ position }
						options={ [
							{
								value: 'top',
								label: __( 'Top', 'page-builder-sandwich' ),
							},
							{
								value: 'bottom',
								label: __( 'Bottom', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { position: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className={ cls( 'reading-progress__bar' ) } />
				<p className={ cls( 'reading-progress__note' ) }>
					{ 'bottom' === position
						? __(
								'Shown along the bottom of the window as visitors scroll.',
								'page-builder-sandwich'
							)
						: __(
								'Shown along the top of the window as visitors scroll.',
								'page-builder-sandwich'
							) }
				</p>
			</div>
		</>
	);
}
