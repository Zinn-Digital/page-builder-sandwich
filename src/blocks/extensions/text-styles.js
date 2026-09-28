/**
 * Drop cap and pull quote (P5 group G-B), on CORE blocks so content stays portable:
 *
 * - core/paragraph: a "Drop cap" panel that sets the paragraph's own `dropCap` attribute (core
 *   draws it), shown even when the theme hides core's control; plus the block style
 *   `drop-cap-boxed` (the first letter in a box).
 * - core/pullquote: the block style `accent-bar`, offered in the inserter as a Page Builder
 *   Sandwich variation ("Pull quote (accent bar)") in the Content category, so it gets the design
 *   panel like every block.
 *
 * Their CSS reaches a page only when the page uses them (Blocks\Extensions).
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { registerBlockStyle, registerBlockVariation } from '@wordpress/blocks';

export const withDropCapPanel = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( props.name !== 'core/paragraph' || ! props.isSelected ) {
			return <BlockEdit { ...props } />;
		}
		const { attributes, setAttributes } = props;
		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Drop cap', 'page-builder-sandwich' ) }
						initialOpen={ false }
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Large first letter',
								'page-builder-sandwich'
							) }
							checked={ !! attributes.dropCap }
							onChange={ ( v ) =>
								setAttributes( { dropCap: v } )
							}
							help={ __(
								'For the first letter in a box, choose the "Drop cap in a box" style.',
								'page-builder-sandwich'
							) }
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	},
	'withDropCapPanel'
);

export function register() {
	addFilter( 'editor.BlockEdit', 'pbsw/drop-cap', withDropCapPanel );
	registerBlockStyle( 'core/paragraph', {
		name: 'drop-cap-boxed',
		label: __( 'Drop cap in a box', 'page-builder-sandwich' ),
	} );
	registerBlockStyle( 'core/pullquote', {
		name: 'accent-bar',
		label: __( 'Accent bar', 'page-builder-sandwich' ),
	} );
	registerBlockVariation( 'core/pullquote', {
		name: 'pbs-pull-quote',
		title: __( 'Pull quote (accent bar)', 'page-builder-sandwich' ),
		description: __(
			'A quote from the text, set large beside a coloured bar to draw the eye.',
			'page-builder-sandwich'
		),
		category: 'pbs-content',
		keywords: [ __( 'quote', 'page-builder-sandwich' ) ],
		scope: [ 'inserter' ],
		attributes: { className: 'is-style-accent-bar' },
		isActive: ( attrs ) =>
			/(^|\s)is-style-accent-bar(\s|$)/.test( attrs.className || '' ),
	} );
}
