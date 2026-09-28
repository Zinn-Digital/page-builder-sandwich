/**
 * The inspector control for an icon attribute: a preview, "Choose icon" (opens the IconPicker in
 * a modal) and "Remove". Used by the pbs/icon block and by any block that carries an icon.
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import { useState } from '@wordpress/element';

import IconPicker from './IconPicker';
import { sanitizeSvg } from './sanitize';

/**
 * An inline, sanitised preview of an icon (currentColor follows the surrounding text colour).
 *
 * @param {Object} props      Props.
 * @param {string} props.svg  SVG markup (sanitised again here).
 * @param {string} props.tag  Wrapper element.
 * @param {Object} props.rest Other wrapper props.
 * @return {Element|null} Preview.
 */
export function IconPreview( { svg, tag: Tag = 'span', ...rest } ) {
	const clean = sanitizeSvg( svg );
	if ( ! clean ) {
		return null;
	}
	// Sanitised by sanitizeSvg() (the twin of Core\Svg::sanitize).
	return <Tag { ...rest } dangerouslySetInnerHTML={ { __html: clean } } />;
}

export default function IconField( { svg, iconRef, onChange } ) {
	const [ open, setOpen ] = useState( false );
	return (
		<div className="pbsw-icon-field">
			<div className="pbsw-icon-field__preview">
				<IconPreview svg={ svg } aria-hidden="true" />
				<Button
					__next40pxDefaultSize
					variant="secondary"
					onClick={ () => setOpen( true ) }
				>
					{ svg
						? __( 'Replace icon', 'page-builder-sandwich' )
						: __( 'Choose icon', 'page-builder-sandwich' ) }
				</Button>
				{ svg && (
					<Button
						__next40pxDefaultSize
						variant="tertiary"
						isDestructive
						onClick={ () => onChange( { svg: '', iconRef: '' } ) }
					>
						{ __( 'Remove', 'page-builder-sandwich' ) }
					</Button>
				) }
			</div>
			{ open && (
				<Modal
					title={ __( 'Choose an icon', 'page-builder-sandwich' ) }
					onRequestClose={ () => setOpen( false ) }
					size="large"
				>
					<IconPicker
						value={ iconRef }
						onSelect={ ( picked ) => {
							onChange( {
								svg: picked.svg,
								iconRef: picked.ref,
							} );
							setOpen( false );
						} }
					/>
				</Modal>
			) }
		</div>
	);
}
