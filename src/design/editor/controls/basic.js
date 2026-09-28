/**
 * Simple controls: a select, a button group, text, a number, a range, a grid span.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalNumberControl as NumberControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';

/**
 * The label shown for "not set here".
 *
 * @param {unknown} placeholder Inherited value.
 * @param {Object}  labels      value → label.
 * @return {string} Label.
 */
function unsetLabel( placeholder, labels = {} ) {
	if ( placeholder === undefined || placeholder === null ) {
		return __( 'Default', 'page-builder-sandwich' );
	}
	return sprintf(
		/* translators: %s: the value a setting inherits from a wider screen size. */
		__( 'Inherited (%s)', 'page-builder-sandwich' ),
		labels[ placeholder ] ?? String( placeholder )
	);
}

export function SelectPropControl( { prop, value, placeholder, onChange } ) {
	const labels = prop.options ? prop.options() : {};
	const choices = prop.choices || Object.keys( labels );
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ prop.label }
			value={ value === undefined ? '' : String( value ) }
			options={ [
				{ value: '', label: unsetLabel( placeholder, labels ) },
				...choices.map( ( c ) => ( {
					value: String( c ),
					label: labels[ c ] ?? c,
				} ) ),
			] }
			onChange={ ( v ) => onChange( v === '' ? undefined : v ) }
		/>
	);
}

export function ButtonsPropControl( { prop, value, placeholder, onChange } ) {
	const labels = prop.options ? prop.options() : {};
	const choices = prop.choices || Object.keys( labels );
	if ( choices.length > 5 ) {
		return (
			<SelectPropControl
				prop={ prop }
				value={ value }
				placeholder={ placeholder }
				onChange={ onChange }
			/>
		);
	}
	return (
		<ToggleGroupControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			isBlock
			isDeselectable
			label={ prop.label }
			help={
				value === undefined && placeholder !== undefined
					? unsetLabel( placeholder, labels )
					: undefined
			}
			value={ value }
			onChange={ ( v ) =>
				onChange( v === undefined || v === '' ? undefined : v )
			}
			className={ value === undefined ? 'is-inherited' : undefined }
		>
			{ choices.map( ( c ) => (
				<ToggleGroupControlOption
					key={ c }
					value={ c }
					label={ labels[ c ] ?? c }
					showTooltip
				/>
			) ) }
		</ToggleGroupControl>
	);
}

export function TextPropControl( { prop, value, placeholder, onChange } ) {
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ prop.label }
			value={ typeof value === 'string' ? value : '' }
			placeholder={ typeof placeholder === 'string' ? placeholder : '' }
			onChange={ ( v ) => onChange( v === '' ? undefined : v ) }
		/>
	);
}

export function NumberPropControl( { prop, value, placeholder, onChange } ) {
	return (
		<NumberControl
			__next40pxDefaultSize
			label={ prop.label }
			min={ prop.min }
			max={ prop.max }
			step={ prop.integer ? 1 : 0.1 }
			value={ value ?? '' }
			placeholder={
				placeholder === undefined ? '' : String( placeholder )
			}
			onChange={ ( v ) =>
				onChange(
					v === '' || v === undefined ? undefined : Number( v )
				)
			}
		/>
	);
}

export function RangePropControl( { prop, value, placeholder, onChange } ) {
	return (
		<RangeControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ prop.label }
			min={ prop.min }
			max={ prop.max }
			step={ 0.05 }
			value={ value }
			initialPosition={
				typeof placeholder === 'number' ? placeholder : prop.max
			}
			allowReset
			resetFallbackValue={ undefined }
			onChange={ ( v ) => onChange( v === undefined ? undefined : v ) }
		/>
	);
}

export function SpanPropControl( { prop, value, placeholder, onChange } ) {
	const full = value === 'full';
	return (
		<div className="pbsw-design-span">
			<NumberControl
				__next40pxDefaultSize
				label={ prop.label }
				min={ 1 }
				max={ 24 }
				disabled={ full }
				value={ full ? '' : ( value ?? '' ) }
				placeholder={
					placeholder === undefined ? '1' : String( placeholder )
				}
				onChange={ ( v ) =>
					onChange(
						v === '' || v === undefined
							? undefined
							: parseInt( v, 10 )
					)
				}
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Every track', 'page-builder-sandwich' ) }
				checked={ full }
				onChange={ ( on ) => onChange( on ? 'full' : undefined ) }
			/>
		</div>
	);
}
