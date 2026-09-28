/**
 * The "Highlight" rich-text format (P5 group G-B): marker pen, underline or background. Saves
 * `<mark data-hl="…">` — neutral markup that is still a highlight with the plugin off. Its CSS
 * reaches a page only when the page uses it (Blocks\Extensions).
 */
import { __ } from '@wordpress/i18n';
import {
	applyFormat,
	getActiveFormat,
	registerFormatType,
	removeFormat,
} from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { Button, Popover } from '@wordpress/components';
import { useState } from '@wordpress/element';

export const NAME = 'pbs/highlight';
export const LOOKS = [ 'marker', 'underline', 'background' ];

/**
 * Labels of the looks.
 *
 * @return {Object} look → label.
 */
export const lookLabels = () => ( {
	marker: __( 'Marker pen', 'page-builder-sandwich' ),
	underline: __( 'Thick underline', 'page-builder-sandwich' ),
	background: __( 'Background', 'page-builder-sandwich' ),
} );

/**
 * Apply (or change) a look on the selection.
 *
 * @param {Object} value Rich-text value.
 * @param {string} look  Look.
 * @return {Object} New value.
 */
export function withLook( value, look ) {
	return applyFormat( value, {
		type: NAME,
		attributes: { look: LOOKS.includes( look ) ? look : 'marker' },
	} );
}

function Edit( { isActive, value, onChange, contentRef } ) {
	const [ open, setOpen ] = useState( false );
	const labels = lookLabels();
	const active = getActiveFormat( value, NAME );
	return (
		<>
			<RichTextToolbarButton
				icon="art"
				title={ __( 'Highlight', 'page-builder-sandwich' ) }
				onClick={ () => setOpen( ( o ) => ! o ) }
				isActive={ isActive }
			/>
			{ open && (
				<Popover
					anchor={ contentRef.current }
					placement="bottom-start"
					onClose={ () => setOpen( false ) }
					focusOnMount="firstElement"
				>
					<div className="pbsw-kit-row" style={ { padding: 8 } }>
						{ LOOKS.map( ( look ) => (
							<Button
								key={ look }
								variant={
									active && active.attributes.look === look
										? 'primary'
										: 'secondary'
								}
								size="compact"
								onClick={ () => {
									onChange( withLook( value, look ) );
									setOpen( false );
								} }
							>
								{ labels[ look ] }
							</Button>
						) ) }
						{ isActive && (
							<Button
								variant="tertiary"
								size="compact"
								onClick={ () => {
									onChange( removeFormat( value, NAME ) );
									setOpen( false );
								} }
							>
								{ __(
									'Remove highlight',
									'page-builder-sandwich'
								) }
							</Button>
						) }
					</div>
				</Popover>
			) }
		</>
	);
}

export const settings = {
	title: __( 'Highlight', 'page-builder-sandwich' ),
	tagName: 'mark',
	className: null,
	attributes: { look: 'data-hl' },
	edit: Edit,
};

export function register() {
	registerFormatType( NAME, settings );
}
