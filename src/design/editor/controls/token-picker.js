/**
 * A token picker: a small button that opens the list of presets/tokens for a value.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, Dropdown, MenuGroup, MenuItem } from '@wordpress/components';
import { check, styles } from '@wordpress/icons';

import { useTokenOptions } from '../tokens';

/**
 * @param {Object}   props
 * @param {string[]} props.types    Accepted token types.
 * @param {Object}   props.value    Current value (a token or not).
 * @param {Function} props.onChange Called with a token.
 * @return {Element|null} Picker, or nothing when there is nothing to pick.
 */
export default function TokenPicker( { types, value, onChange } ) {
	const options = useTokenOptions( types );
	if ( ! options.length ) {
		return null;
	}
	return (
		<Dropdown
			className="pbsw-design-token"
			popoverProps={ { placement: 'bottom-end' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					size="small"
					icon={ styles }
					label={ __( 'Choose a preset', 'page-builder-sandwich' ) }
					onClick={ onToggle }
					aria-expanded={ isOpen }
					isPressed={ !! value?.t }
				/>
			) }
			renderContent={ ( { onClose } ) => (
				<MenuGroup
					label={ __( 'Presets', 'page-builder-sandwich' ) }
					className="pbsw-design-token__menu"
				>
					{ options.map( ( o ) => {
						const active = value?.t === o.t && value?.v === o.v;
						return (
							<MenuItem
								key={ `${ o.t }:${ o.v }` }
								icon={ active ? check : undefined }
								isSelected={ active }
								role="menuitemradio"
								info={ o.value || undefined }
								onClick={ () => {
									onChange( { t: o.t, v: o.v } );
									onClose();
								} }
							>
								{ o.t === 'color' ? (
									<span className="pbsw-design-token__row">
										<span
											className="pbsw-design-swatch"
											style={ { background: o.value } }
											aria-hidden="true"
										/>
										{ o.label }
									</span>
								) : (
									o.label
								) }
							</MenuItem>
						);
					} ) }
				</MenuGroup>
			) }
		/>
	);
}

/**
 * The label of a token value, for a chip.
 *
 * @param {Array<Object>} options useTokenOptions() result.
 * @param {Object}        token   Token.
 * @return {string} Label.
 */
export function tokenLabel( options, token ) {
	const found = options.find( ( o ) => o.t === token.t && o.v === token.v );
	return sprintf(
		/* translators: %s: the name of a design preset, e.g. "Large". */
		__( 'Preset: %s', 'page-builder-sandwich' ),
		found ? found.label : token.v
	);
}
