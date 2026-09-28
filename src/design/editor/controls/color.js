/**
 * A colour: the theme palette (stored as a token, so a palette change updates every use) or a
 * custom colour.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useInstanceId } from '@wordpress/compose';
import {
	BaseControl,
	Button,
	ColorPalette,
	Dropdown,
	FlexBlock,
} from '@wordpress/components';

import { isToken, useTokenOptions } from '../tokens';

/**
 * CSS for a colour value, for a swatch.
 *
 * @param {unknown}       v       Value.
 * @param {Array<Object>} options Palette options.
 * @return {string|undefined} CSS colour.
 */
function swatch( v, options ) {
	if ( isToken( v ) ) {
		return options.find( ( o ) => o.t === v.t && o.v === v.v )?.value;
	}
	return typeof v === 'string' ? v : undefined;
}

/**
 * @param {Object}   props
 * @param {string}   props.label       Label.
 * @param {unknown}  props.value       Value.
 * @param {unknown}  props.placeholder Inherited value.
 * @param {Function} props.onChange    Change.
 * @return {Element} Control.
 */
export function ColorControl( { label, value, placeholder, onChange } ) {
	const options = useTokenOptions( [ 'color' ] );
	const palette = options.map( ( o ) => ( {
		name: o.label,
		slug: o.v,
		color: o.value,
	} ) );
	const shown = swatch( value, options );
	const inheritedColor = swatch( placeholder, options );
	const name = isToken( value )
		? options.find( ( o ) => o.v === value.v )?.label || value.v
		: shown;
	const id = useInstanceId( ColorControl, 'pbsw-design-color' );

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ id }
			label={ label }
			className="pbsw-design-color"
		>
			<Dropdown
				popoverProps={ { placement: 'left-start', offset: 36 } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						id={ id }
						className="pbsw-design-color__toggle"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						__next40pxDefaultSize
					>
						<span
							className={ `pbsw-design-swatch${ shown ? '' : ' is-empty' }` }
							style={ {
								background:
									shown || inheritedColor || undefined,
							} }
							aria-hidden="true"
						/>
						<FlexBlock className={ shown ? '' : 'is-inherited' }>
							{ name ||
								( inheritedColor
									? sprintf(
											/* translators: %s: a colour inherited from a wider screen size. */
											__(
												'Inherited (%s)',
												'page-builder-sandwich'
											),
											inheritedColor
										)
									: __(
											'Default',
											'page-builder-sandwich'
										) ) }
						</FlexBlock>
					</Button>
				) }
				renderContent={ () => (
					<div className="pbsw-design-color__popover">
						<ColorPalette
							colors={ palette }
							value={ shown }
							enableAlpha
							onChange={ ( color ) => {
								if ( ! color ) {
									onChange( undefined );
									return;
								}
								const preset = palette.find(
									( p ) => p.color === color
								);
								onChange(
									preset
										? { t: 'color', v: preset.slug }
										: color
								);
							} }
							clearable
						/>
					</div>
				) }
			/>
		</BaseControl>
	);
}

export function ColorPropControl( { prop, value, placeholder, onChange } ) {
	return (
		<ColorControl
			label={ prop.label }
			value={ value }
			placeholder={ placeholder }
			onChange={ onChange }
		/>
	);
}
