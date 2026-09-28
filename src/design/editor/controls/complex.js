/**
 * Structured controls: grid tracks and areas, aspect ratio, font, background image, border,
 * shadow and transition.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	BaseControl,
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { plus, trash } from '@wordpress/icons';

import { LengthControl, sideNames } from './length';
import { ColorControl } from './color';
import TokenPicker, { tokenLabel } from './token-picker';
import { isToken, useTokenOptions } from '../tokens';
import { STYLES } from '../../props/border';
import { EASINGS, PROPERTIES } from '../../props/transition';

const COUNTED = /^repeat\((\d{1,2}),minmax\(0,1fr\)\)$/;

/**
 * Grid columns or rows: a count, or a custom track list.
 *
 * @param {Object}   props             Control props.
 * @param {Object}   props.prop        The prop.
 * @param {unknown}  props.value       Value.
 * @param {unknown}  props.placeholder Inherited value.
 * @param {Function} props.onChange    Change.
 * @return {Element} Control.
 */
export function GridTemplateControl( { prop, value, placeholder, onChange } ) {
	const count =
		typeof value === 'number'
			? value
			: parseInt( COUNTED.exec( value || '' )?.[ 1 ] || '', 10 ) || null;
	const custom =
		typeof value === 'string' && ! /^\d+$/.test( value ) && ! count;
	const [ mode, setMode ] = useState( custom ? 'custom' : 'count' );
	return (
		<div className="pbsw-design-grid-template">
			{ mode === 'count' ? (
				<NumberControl
					__next40pxDefaultSize
					label={ prop.label }
					min={ 1 }
					max={ 24 }
					value={
						count ?? ( typeof value === 'string' ? value : '' )
					}
					placeholder={
						placeholder === undefined ? '' : String( placeholder )
					}
					onChange={ ( v ) =>
						onChange(
							v === '' || v === undefined
								? undefined
								: parseInt( v, 10 )
						)
					}
				/>
			) : (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ prop.label }
					help={ __(
						'Track sizes, for example: 1fr 2fr 200px',
						'page-builder-sandwich'
					) }
					value={ typeof value === 'string' ? value : '' }
					placeholder={
						typeof placeholder === 'string' ? placeholder : ''
					}
					onChange={ ( v ) => onChange( v === '' ? undefined : v ) }
				/>
			) }
			<Button
				variant="link"
				size="small"
				onClick={ () =>
					setMode( mode === 'count' ? 'custom' : 'count' )
				}
			>
				{ mode === 'count'
					? __( 'Custom track sizes', 'page-builder-sandwich' )
					: __( 'Equal tracks', 'page-builder-sandwich' ) }
			</Button>
		</div>
	);
}

export function GridAreasControl( { prop, value, placeholder, onChange } ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ prop.label }
			help={ __(
				'One quoted row per line, for example: "head head" "side main". Name the area on each child block.',
				'page-builder-sandwich'
			) }
			rows={ 3 }
			value={
				typeof value === 'string' ? value.replace( /" "/g, '"\n"' ) : ''
			}
			placeholder={ typeof placeholder === 'string' ? placeholder : '' }
			onChange={ ( v ) =>
				onChange(
					v.trim() === '' ? undefined : v.replace( /\s*\n\s*/g, ' ' )
				)
			}
		/>
	);
}

export function AspectRatioControl( { prop, value, placeholder, onChange } ) {
	const presets = {
		'': placeholder
			? sprintf(
					/* translators: %s: inherited value. */ __(
						'Inherited (%s)',
						'page-builder-sandwich'
					),
					placeholder
				)
			: __( 'Default', 'page-builder-sandwich' ),
		auto: __( 'Automatic', 'page-builder-sandwich' ),
		1: __( 'Square (1:1)', 'page-builder-sandwich' ),
		'4/3': __( 'Standard (4:3)', 'page-builder-sandwich' ),
		'3/2': __( 'Classic (3:2)', 'page-builder-sandwich' ),
		'16/9': __( 'Wide (16:9)', 'page-builder-sandwich' ),
		'21/9': __( 'Cinema (21:9)', 'page-builder-sandwich' ),
		'9/16': __( 'Tall (9:16)', 'page-builder-sandwich' ),
	};
	const known =
		value === undefined || Object.hasOwn( presets, String( value ) );
	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ prop.label }
				value={ known ? String( value ?? '' ) : 'custom' }
				options={ [
					...Object.entries( presets ).map( ( [ v, label ] ) => ( {
						value: v,
						label,
					} ) ),
					{
						value: 'custom',
						label: __( 'Custom', 'page-builder-sandwich' ),
					},
				] }
				onChange={ ( v ) => {
					if ( v === 'custom' ) {
						onChange( '2/1' );
						return;
					}
					onChange( v === '' ? undefined : v );
				} }
			/>
			{ ! known && (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Width / height', 'page-builder-sandwich' ) }
					value={ String( value ) }
					onChange={ ( v ) => onChange( v === '' ? undefined : v ) }
				/>
			) }
		</>
	);
}

export function FontFamilyControl( { prop, value, placeholder, onChange } ) {
	const options = useTokenOptions( [ 'font' ] );
	if ( isToken( value ) ) {
		return (
			<BaseControl __nextHasNoMarginBottom>
				<BaseControl.VisualLabel>
					{ prop.label }
				</BaseControl.VisualLabel>
				<div className="pbsw-design-length__row">
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => onChange( undefined ) }
					>
						{ tokenLabel( options, value ) }
					</Button>
					<TokenPicker
						types={ [ 'font', 'var' ] }
						value={ value }
						onChange={ onChange }
					/>
				</div>
			</BaseControl>
		);
	}
	return (
		<div className="pbsw-design-length__row">
			<div className="pbsw-design-length__input">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ prop.label }
					value={ typeof value === 'string' ? value : '' }
					placeholder={
						isToken( placeholder )
							? options.find( ( o ) => o.v === placeholder.v )
									?.label || placeholder.v
							: placeholder || ''
					}
					onChange={ ( v ) => onChange( v === '' ? undefined : v ) }
				/>
			</div>
			<TokenPicker
				types={ [ 'font', 'var' ] }
				value={ value }
				onChange={ onChange }
			/>
		</div>
	);
}

export function BackgroundImageControl( {
	prop,
	value,
	placeholder,
	onChange,
} ) {
	const v = value || {};
	const set = ( patch ) => onChange( { ...v, ...patch } );
	const positions = {
		center: __( 'Centre', 'page-builder-sandwich' ),
		top: __( 'Top', 'page-builder-sandwich' ),
		bottom: __( 'Bottom', 'page-builder-sandwich' ),
	};
	return (
		<BaseControl __nextHasNoMarginBottom className="pbsw-design-bgimage">
			<BaseControl.VisualLabel>{ prop.label }</BaseControl.VisualLabel>
			<MediaUploadCheck>
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					value={ v.id }
					onSelect={ ( media ) =>
						set( { url: media.url, id: media.id } )
					}
					render={ ( { open } ) => (
						<div className="pbsw-design-bgimage__pick">
							{ v.url ? (
								<button
									type="button"
									className="pbsw-design-bgimage__preview"
									onClick={ open }
								>
									<img
										src={ v.url }
										alt={ __(
											'Background image',
											'page-builder-sandwich'
										) }
									/>
								</button>
							) : (
								<Button
									variant="secondary"
									onClick={ open }
									__next40pxDefaultSize
								>
									{ placeholder?.url
										? __(
												'Inherited image — replace',
												'page-builder-sandwich'
											)
										: __(
												'Choose image',
												'page-builder-sandwich'
											) }
								</Button>
							) }
							{ v.url && (
								<Button
									icon={ trash }
									label={ __(
										'Remove image',
										'page-builder-sandwich'
									) }
									onClick={ () => onChange( undefined ) }
								/>
							) }
						</div>
					) }
				/>
			</MediaUploadCheck>
			{ v.url && (
				<>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Position', 'page-builder-sandwich' ) }
						value={ v.position || '' }
						options={ [
							{
								value: '',
								label: __( 'Default', 'page-builder-sandwich' ),
							},
							...Object.entries( positions ).map(
								( [ k, l ] ) => ( { value: k, label: l } )
							),
						] }
						onChange={ ( position ) =>
							set( { position: position || undefined } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Size', 'page-builder-sandwich' ) }
						value={ v.size || '' }
						options={ [
							{
								value: '',
								label: __( 'Default', 'page-builder-sandwich' ),
							},
							{
								value: 'cover',
								label: __( 'Cover', 'page-builder-sandwich' ),
							},
							{
								value: 'contain',
								label: __( 'Contain', 'page-builder-sandwich' ),
							},
							{
								value: 'auto',
								label: __(
									'Original size',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( size ) =>
							set( { size: size || undefined } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Repeat', 'page-builder-sandwich' ) }
						value={ v.repeat || '' }
						options={ [
							{
								value: '',
								label: __( 'Default', 'page-builder-sandwich' ),
							},
							{
								value: 'no-repeat',
								label: __(
									'No repeat',
									'page-builder-sandwich'
								),
							},
							{
								value: 'repeat',
								label: __( 'Tile', 'page-builder-sandwich' ),
							},
							{
								value: 'repeat-x',
								label: __(
									'Tile across',
									'page-builder-sandwich'
								),
							},
							{
								value: 'repeat-y',
								label: __(
									'Tile down',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( repeat ) =>
							set( { repeat: repeat || undefined } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Fixed while scrolling',
							'page-builder-sandwich'
						) }
						checked={ v.attachment === 'fixed' }
						onChange={ ( on ) =>
							set( { attachment: on ? 'fixed' : undefined } )
						}
					/>
				</>
			) }
		</BaseControl>
	);
}

/**
 * Border: one width/style/colour for every side, or per logical side.
 *
 * @param {Object}   props             Control props.
 * @param {Object}   props.prop        The prop.
 * @param {unknown}  props.value       Value.
 * @param {unknown}  props.placeholder Inherited value.
 * @param {Function} props.onChange    Change.
 * @return {Element} Control.
 */
export function BorderControl( { prop, value, placeholder, onChange } ) {
	const names = sideNames();
	const keys = Object.keys( names );
	const v = value || {};
	const same = keys.every(
		( k ) => JSON.stringify( v[ k ] ) === JSON.stringify( v[ keys[ 0 ] ] )
	);
	const [ split, setSplit ] = useState( ! same );
	const [ side, setSide ] = useState( keys[ 0 ] );
	const edit = split ? side : keys[ 0 ];
	const cur = v[ edit ] || {};
	const ph = placeholder?.[ edit ] || {};
	const write = ( patch ) => {
		const one = { ...cur, ...patch };
		Object.keys( one ).forEach(
			( k ) => one[ k ] === undefined && delete one[ k ]
		);
		const next = { ...v };
		for ( const k of split ? [ side ] : keys ) {
			if ( Object.keys( one ).length ) {
				next[ k ] = one;
			} else {
				delete next[ k ];
			}
		}
		onChange( Object.keys( next ).length ? next : undefined );
	};
	const styles = {
		none: __( 'None', 'page-builder-sandwich' ),
		solid: __( 'Solid', 'page-builder-sandwich' ),
		dashed: __( 'Dashed', 'page-builder-sandwich' ),
		dotted: __( 'Dotted', 'page-builder-sandwich' ),
		double: __( 'Double', 'page-builder-sandwich' ),
	};
	return (
		<fieldset className="pbsw-design-border">
			<legend className="pbsw-design-sides__legend">
				{ prop.label }
			</legend>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Each side separately', 'page-builder-sandwich' ) }
				checked={ split }
				onChange={ setSplit }
			/>
			{ split && (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Side', 'page-builder-sandwich' ) }
					value={ side }
					options={ keys.map( ( k ) => ( {
						value: k,
						label: names[ k ],
					} ) ) }
					onChange={ setSide }
				/>
			) }
			<LengthControl
				label={ __( 'Width', 'page-builder-sandwich' ) }
				value={ cur.width }
				placeholder={ ph.width }
				opts={ {
					keywords: [ 'thin', 'medium', 'thick' ],
					tokens: [ 'var' ],
				} }
				onChange={ ( width ) => write( { width } ) }
			/>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Style', 'page-builder-sandwich' ) }
				value={ cur.style || '' }
				options={ [
					{
						value: '',
						label: __( 'Default', 'page-builder-sandwich' ),
					},
					...STYLES.filter( ( s ) => styles[ s ] ).map( ( s ) => ( {
						value: s,
						label: styles[ s ],
					} ) ),
				] }
				onChange={ ( style ) => write( { style: style || undefined } ) }
			/>
			<ColorControl
				label={ __( 'Colour', 'page-builder-sandwich' ) }
				value={ cur.color }
				placeholder={ ph.color }
				onChange={ ( color ) => write( { color } ) }
			/>
		</fieldset>
	);
}

/**
 * Shadow layers (box or text).
 *
 * @param {Object}   props          Control props.
 * @param {Object}   props.prop     The prop.
 * @param {unknown}  props.value    Value.
 * @param {Function} props.onChange Change.
 * @return {Element} Control.
 */
export function ShadowControl( { prop, value, onChange } ) {
	const options = useTokenOptions( [ 'shadow' ] );
	const box = prop.box !== false;
	if ( isToken( value ) ) {
		return (
			<BaseControl __nextHasNoMarginBottom>
				<BaseControl.VisualLabel>
					{ prop.label }
				</BaseControl.VisualLabel>
				<div className="pbsw-design-length__row">
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => onChange( undefined ) }
					>
						{ tokenLabel( options, value ) }
					</Button>
					<TokenPicker
						types={ [ 'shadow', 'var' ] }
						value={ value }
						onChange={ onChange }
					/>
				</div>
			</BaseControl>
		);
	}
	const layers = Array.isArray( value ) ? value : [];
	const setLayer = ( i, patch ) => {
		const next = layers.map( ( l, j ) =>
			j === i ? { ...l, ...patch } : l
		);
		onChange( next );
	};
	return (
		<fieldset className="pbsw-design-shadow">
			<div className="pbsw-design-sides__head">
				<legend className="pbsw-design-sides__legend">
					{ prop.label }
				</legend>
				{ box && (
					<TokenPicker
						types={ [ 'shadow', 'var' ] }
						value={ value }
						onChange={ onChange }
					/>
				) }
				<Button
					size="small"
					icon={ plus }
					label={ __( 'Add a shadow', 'page-builder-sandwich' ) }
					disabled={ layers.length >= 4 }
					onClick={ () =>
						onChange( [
							...layers,
							box
								? {
										x: '0px',
										y: '4px',
										blur: '12px',
										spread: '0px',
										color: 'rgba(0,0,0,0.15)',
									}
								: {
										x: '1px',
										y: '1px',
										blur: '2px',
										color: 'rgba(0,0,0,0.3)',
									},
						] )
					}
				/>
			</div>
			{ layers.map( ( l, i ) => (
				<div className="pbsw-design-shadow__layer" key={ i }>
					<div className="pbsw-design-sides__grid">
						<LengthControl
							label={ __(
								'Horizontal',
								'page-builder-sandwich'
							) }
							value={ l.x }
							opts={ { neg: true } }
							onChange={ ( x ) => setLayer( i, { x } ) }
						/>
						<LengthControl
							label={ __( 'Vertical', 'page-builder-sandwich' ) }
							value={ l.y }
							opts={ { neg: true } }
							onChange={ ( y ) => setLayer( i, { y } ) }
						/>
						<LengthControl
							label={ __( 'Blur', 'page-builder-sandwich' ) }
							value={ l.blur }
							onChange={ ( blur ) => setLayer( i, { blur } ) }
						/>
						{ box && (
							<LengthControl
								label={ __(
									'Spread',
									'page-builder-sandwich'
								) }
								value={ l.spread }
								opts={ { neg: true } }
								onChange={ ( spread ) =>
									setLayer( i, { spread } )
								}
							/>
						) }
					</div>
					<ColorControl
						label={ __( 'Colour', 'page-builder-sandwich' ) }
						value={ l.color }
						onChange={ ( color ) => setLayer( i, { color } ) }
					/>
					{ box && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Inside', 'page-builder-sandwich' ) }
							checked={ !! l.inset }
							onChange={ ( inset ) =>
								setLayer( i, { inset: inset || undefined } )
							}
						/>
					) }
					<Button
						variant="link"
						isDestructive
						onClick={ () => {
							const next = layers.filter( ( _, j ) => j !== i );
							onChange( next.length ? next : undefined );
						} }
					>
						{ __( 'Remove this shadow', 'page-builder-sandwich' ) }
					</Button>
				</div>
			) ) }
		</fieldset>
	);
}

export function TransitionControl( { prop, value, onChange } ) {
	const v = value || null;
	const names = {
		all: __( 'Everything', 'page-builder-sandwich' ),
		colors: __( 'Colours', 'page-builder-sandwich' ),
		opacity: __( 'Opacity', 'page-builder-sandwich' ),
		transform: __( 'Transform', 'page-builder-sandwich' ),
		shadow: __( 'Shadow', 'page-builder-sandwich' ),
	};
	const easings = {
		ease: __( 'Ease', 'page-builder-sandwich' ),
		linear: __( 'Linear', 'page-builder-sandwich' ),
		'ease-in': __( 'Ease in', 'page-builder-sandwich' ),
		'ease-out': __( 'Ease out', 'page-builder-sandwich' ),
		'ease-in-out': __( 'Ease in and out', 'page-builder-sandwich' ),
	};
	return (
		<fieldset className="pbsw-design-transition">
			<legend className="pbsw-design-sides__legend">
				{ prop.label }
			</legend>
			<NumberControl
				__next40pxDefaultSize
				label={ __(
					'Duration (milliseconds)',
					'page-builder-sandwich'
				) }
				min={ 0 }
				max={ 10000 }
				step={ 50 }
				value={ v?.duration ?? '' }
				onChange={ ( d ) =>
					onChange(
						d === '' || d === undefined
							? undefined
							: {
									property: 'all',
									easing: 'ease',
									...( v || {} ),
									duration: Number( d ),
								}
					)
				}
			/>
			{ v && (
				<>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'What animates', 'page-builder-sandwich' ) }
						value={ v.property || 'all' }
						options={ Object.keys( PROPERTIES ).map( ( k ) => ( {
							value: k,
							label: names[ k ],
						} ) ) }
						onChange={ ( property ) =>
							onChange( { ...v, property } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Easing', 'page-builder-sandwich' ) }
						value={ v.easing || 'ease' }
						options={ EASINGS.map( ( k ) => ( {
							value: k,
							label: easings[ k ],
						} ) ) }
						onChange={ ( easing ) => onChange( { ...v, easing } ) }
					/>
					<NumberControl
						__next40pxDefaultSize
						label={ __(
							'Delay (milliseconds)',
							'page-builder-sandwich'
						) }
						min={ 0 }
						max={ 10000 }
						step={ 50 }
						value={ v.delay ?? '' }
						onChange={ ( d ) =>
							onChange( {
								...v,
								delay:
									d === '' || d === undefined
										? undefined
										: Number( d ),
							} )
						}
					/>
				</>
			) }
		</fieldset>
	);
}
