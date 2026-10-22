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
	RangeControl,
} from '@wordpress/components';

import { isToken, useTokenOptions } from '../tokens';
import { mix } from '../../values';

/**
 * The palette colour of a value, ignoring its shade and opacity.
 *
 * @param {unknown}       v       Value.
 * @param {Array<Object>} options Palette options.
 * @return {string|undefined} CSS colour.
 */
function baseColor( v, options ) {
	if ( isToken( v ) ) {
		return options.find( ( o ) => o.t === v.t && o.v === v.v )?.value;
	}
	return typeof v === 'string' ? v : undefined;
}

/**
 * CSS for a colour value, for a swatch: a palette colour with its shade and opacity mixed in
 * OKLCH exactly as the page will (Values::mix()).
 *
 * @param {unknown}       v       Value.
 * @param {Array<Object>} options Palette options.
 * @return {string|undefined} CSS colour.
 */
export function swatch( v, options ) {
	const base = baseColor( v, options );
	if ( ! base || ! isToken( v ) ) {
		return base;
	}
	return mix( base, v.sh ?? 0, v.o ?? 100 );
}

/**
 * A palette token with a new shade and/or opacity; the defaults (0, 100) are left out, so a
 * plain colour stays `{t, v}`. Pure.
 *
 * @param {Object} token Token.
 * @param {Object} patch `{ sh?, o? }`.
 * @return {Object} Token.
 */
export function withModifiers( token, patch ) {
	const next = { t: token.t, v: token.v };
	const sh = 'sh' in patch ? patch.sh : token.sh;
	const o = 'o' in patch ? patch.o : token.o;
	if ( Number.isInteger( sh ) && sh !== 0 ) {
		next.sh = Math.max( -95, Math.min( 95, sh ) );
	}
	if ( Number.isInteger( o ) && o < 100 ) {
		next.o = Math.max( 0, o );
	}
	return next;
}

/**
 * The words for a token's shade and opacity, e.g. "20 % lighter, 50 % opacity" ('' for none).
 *
 * @param {Object} token Token.
 * @return {string} Text.
 */
export function modifierText( token ) {
	const parts = [];
	if ( token.sh > 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: a percentage. */
				__( '%d%% lighter', 'page-builder-sandwich' ),
				token.sh
			)
		);
	} else if ( token.sh < 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: a percentage. */
				__( '%d%% darker', 'page-builder-sandwich' ),
				-token.sh
			)
		);
	}
	if ( Number.isInteger( token.o ) ) {
		parts.push(
			sprintf(
				/* translators: %d: a percentage. */
				__( '%d%% opacity', 'page-builder-sandwich' ),
				token.o
			)
		);
	}
	return parts.join( ', ' );
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
	const modifiers = isToken( value ) ? modifierText( value ) : '';
	const name = isToken( value )
		? ( options.find( ( o ) => o.v === value.v )?.label || value.v ) +
			( modifiers ? ` · ${ modifiers }` : '' )
		: shown;
	const isPalette = isToken( value ) && value.t === 'color';
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
							value={ baseColor( value, options ) }
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
						{ isPalette && (
							<div className="pbsw-design-color__mods">
								<RangeControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Shade',
										'page-builder-sandwich'
									) }
									help={ __(
										'Below 0 is darker, above 0 lighter. Mixed in OKLCH, so the colour keeps its hue.',
										'page-builder-sandwich'
									) }
									min={ -90 }
									max={ 90 }
									step={ 10 }
									value={ value.sh ?? 0 }
									onChange={ ( sh ) =>
										onChange(
											withModifiers( value, {
												sh: Number( sh ) || 0,
											} )
										)
									}
									allowReset
									resetFallbackValue={ 0 }
								/>
								<RangeControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Opacity',
										'page-builder-sandwich'
									) }
									min={ 0 }
									max={ 100 }
									step={ 5 }
									value={ value.o ?? 100 }
									onChange={ ( o ) =>
										onChange(
											withModifiers( value, {
												o:
													o === undefined
														? 100
														: Number( o ),
											} )
										)
									}
									allowReset
									resetFallbackValue={ 100 }
								/>
							</div>
						) }
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
