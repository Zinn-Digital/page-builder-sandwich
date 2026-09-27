/**
 * Editor warning: a block that will cause layout shift (CLS) says so in its sidebar (pbs-p4).
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { Notice, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { layoutShiftIssue } from './layout-shift';

/**
 * The message for an issue.
 *
 * @param {string} issue Issue key.
 * @return {string} Message.
 */
function message( issue ) {
	switch ( issue ) {
		case 'image':
			return __(
				'This image has no width and height, so the page will jump when it loads. Choose it from the Media Library, or set its width and height.',
				'page-builder-sandwich'
			);
		case 'video':
			return __(
				'This video is not from the Media Library, so its size is unknown and the page will jump when it loads. Upload it to the Media Library instead.',
				'page-builder-sandwich'
			);
		case 'html':
			return __(
				'An image, video or frame in this HTML has no width and height, so the page will jump when it loads. Add width and height attributes to it.',
				'page-builder-sandwich'
			);
		default:
			return __(
				'This embed resizes itself after it loads, which moves the content below it. Give it room or place it lower on the page.',
				'page-builder-sandwich'
			);
	}
}

const withLayoutShiftWarning = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const issue = layoutShiftIssue( props.name, props.attributes );
		return (
			<>
				<BlockEdit { ...props } />
				{ issue && props.isSelected && (
					<InspectorControls>
						<PanelBody
							title={ __(
								'Layout shift',
								'page-builder-sandwich'
							) }
						>
							<Notice status="warning" isDismissible={ false }>
								{ message( issue ) }
							</Notice>
						</PanelBody>
					</InspectorControls>
				) }
			</>
		);
	},
	'withLayoutShiftWarning'
);

addFilter(
	'editor.BlockEdit',
	'pbsw/layout-shift-warning',
	withLayoutShiftWarning
);
