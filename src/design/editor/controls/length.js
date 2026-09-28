/**
 * Length controls: one length (with presets, keywords and free CSS), four logical sides, four
 * logical corners, and a row/column gap.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	BaseControl,
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalUnitControl as UnitControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalUseCustomUnits as useCustomUnits,
} from '@wordpress/components';
import { link, linkOff, moreVertical } from '@wordpress/icons';

import TokenPicker, { tokenLabel } from './token-picker';
import { isToken, useTokenOptions } from '../tokens';

const NUMERIC =
	/^-?(?:\d+(?:\.\d+)?|\.\d+)(px|%|em|rem|vw|vh|vmin|vmax|svh|dvh|ch|ex|fr)?$/;

/**
 * Text shown for an inherited value.
 *
 * @param {unknown}       v       Value.
 * @param {Array<Object>} options Token options.
 * @return {string} Text.
 */
export function describe( v, options = [] ) {
	if ( v === undefined || v === null ) {
		return '';
	}
	if ( isToken( v ) ) {
		return (
			options.find( ( o ) => o.t === v.t && o.v === v.v )?.label || v.v
		);
	}
	return typeof v === 'number' ? `${ v }px` : String( v );
}

/**
 * One CSS length.
 *
 * @param {Object}   props
 * @param {string}   props.label       Label.
 * @param {unknown}  props.value       Value.
 * @param {unknown}  props.placeholder Inherited value.
 * @param {Function} props.onChange    Change (undefined = unset).
 * @param {Object}   props.opts        Prop options (keywords, tokens, unitless).
 * @param {boolean}  props.hideLabel   Visually hide the label.
 * @param {string[]} props.extraUnits  Units to offer beyond the defaults.
 * @return {Element} Control.
 */
export function LengthControl( {
	label,
	value,
	placeholder,
	onChange,
	opts = {},
	hideLabel = false,
	extraUnits = [],
} ) {
	const tokens = opts.tokens || [];
	const keywords = opts.keywords || [];
	const options = useTokenOptions( tokens );
	const units = useCustomUnits( {
		availableUnits: [ 'px', '%', 'em', 'rem', 'vw', 'vh', ...extraUnits ],
	} );
	const isFree =
		typeof value === 'string' && value !== '' && ! NUMERIC.test( value );
	const [ free, setFree ] = useState( isFree );
	const hint = describe( placeholder, options );
	const set = ( v ) =>
		onChange( v === '' || v === undefined ? undefined : v );

	if ( isToken( value ) ) {
		return (
			<BaseControl __nextHasNoMarginBottom className="pbsw-design-length">
				{ ! hideLabel && (
					<BaseControl.VisualLabel>{ label }</BaseControl.VisualLabel>
				) }
				<div className="pbsw-design-length__row">
					<Button
						variant="secondary"
						size="compact"
						className="pbsw-design-chip"
						onClick={ () => set( undefined ) }
						label={ __(
							'Remove the preset',
							'page-builder-sandwich'
						) }
						showTooltip
					>
						{ tokenLabel( options, value ) }
					</Button>
					<TokenPicker
						types={ tokens }
						value={ value }
						onChange={ set }
					/>
				</div>
			</BaseControl>
		);
	}

	const menu = (
		<DropdownMenu
			icon={ moreVertical }
			label={ __( 'More options', 'page-builder-sandwich' ) }
			toggleProps={ { size: 'small' } }
		>
			{ ( { onClose } ) => (
				<MenuGroup>
					{ keywords.map( ( k ) => (
						<MenuItem
							key={ k }
							onClick={ () => {
								setFree( false );
								set( k );
								onClose();
							} }
						>
							{ k }
						</MenuItem>
					) ) }
					<MenuItem
						onClick={ () => {
							setFree( ! free );
							onClose();
						} }
					>
						{ free
							? __( 'Number and unit', 'page-builder-sandwich' )
							: __(
									'Custom CSS value (calc, clamp…)',
									'page-builder-sandwich'
								) }
					</MenuItem>
				</MenuGroup>
			) }
		</DropdownMenu>
	);

	const input =
		free || keywords.includes( value ) ? (
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				hideLabelFromVision={ hideLabel }
				value={ typeof value === 'string' ? value : '' }
				placeholder={ hint }
				onChange={ set }
			/>
		) : (
			<UnitControl
				__next40pxDefaultSize
				label={ label }
				hideLabelFromVision={ hideLabel }
				value={
					typeof value === 'number' ? `${ value }px` : ( value ?? '' )
				}
				placeholder={ hint }
				units={ opts.unitless ? [] : units }
				disableUnits={ !! opts.unitless }
				onChange={ ( v ) => set( v ) }
			/>
		);

	return (
		<div className="pbsw-design-length">
			<div className="pbsw-design-length__row">
				<div className="pbsw-design-length__input">{ input }</div>
				<TokenPicker
					types={ tokens }
					value={ value }
					onChange={ set }
				/>
				{ menu }
			</div>
		</div>
	);
}

/**
 * Four logical sides (or corners), linked by default.
 *
 * @param {Object}   props
 * @param {Object}   props.prop        The prop.
 * @param {Object}   props.value       `{side: length}`.
 * @param {Object}   props.placeholder Inherited value.
 * @param {Function} props.onChange    Change.
 * @param {Object}   props.names       side key → label.
 * @return {Element} Control.
 */
export function SidesControl( { prop, value, placeholder, onChange, names } ) {
	const keys = Object.keys( names );
	const current = value || {};
	const values = keys.map( ( k ) => current[ k ] );
	const allSame = values.every(
		( v ) => JSON.stringify( v ) === JSON.stringify( values[ 0 ] )
	);
	const [ linked, setLinked ] = useState( allSame );
	const setSide = ( side, v ) => {
		const next = { ...current };
		if ( v === undefined ) {
			delete next[ side ];
		} else {
			next[ side ] = v;
		}
		onChange( Object.keys( next ).length ? next : undefined );
	};
	const setAll = ( v ) =>
		onChange(
			v === undefined
				? undefined
				: Object.fromEntries( keys.map( ( k ) => [ k, v ] ) )
		);

	return (
		<fieldset className="pbsw-design-sides">
			<div className="pbsw-design-sides__head">
				<legend className="pbsw-design-sides__legend">
					{ prop.label }
				</legend>
				<Button
					size="small"
					icon={ linked ? link : linkOff }
					isPressed={ linked }
					label={
						linked
							? __(
									'Set each side separately',
									'page-builder-sandwich'
								)
							: __(
									'Set all sides together',
									'page-builder-sandwich'
								)
					}
					onClick={ () => setLinked( ! linked ) }
				/>
			</div>
			{ linked ? (
				<LengthControl
					label={ __( 'All sides', 'page-builder-sandwich' ) }
					hideLabel
					value={ values[ 0 ] }
					placeholder={ placeholder?.[ keys[ 0 ] ] }
					opts={ prop.opts }
					onChange={ setAll }
				/>
			) : (
				<div className="pbsw-design-sides__grid">
					{ keys.map( ( k ) => (
						<LengthControl
							key={ k }
							label={ names[ k ] }
							value={ current[ k ] }
							placeholder={ placeholder?.[ k ] }
							opts={ prop.opts }
							onChange={ ( v ) => setSide( k, v ) }
						/>
					) ) }
				</div>
			) }
		</fieldset>
	);
}

/** Logical side labels (a right-to-left page puts "Start" on the right). */
export const sideNames = () => ( {
	blockStart: __( 'Top', 'page-builder-sandwich' ),
	inlineEnd: __( 'End', 'page-builder-sandwich' ),
	blockEnd: __( 'Bottom', 'page-builder-sandwich' ),
	inlineStart: __( 'Start', 'page-builder-sandwich' ),
} );

/** Logical corner labels. */
export const cornerNames = () => ( {
	startStart: __( 'Top start', 'page-builder-sandwich' ),
	startEnd: __( 'Top end', 'page-builder-sandwich' ),
	endStart: __( 'Bottom start', 'page-builder-sandwich' ),
	endEnd: __( 'Bottom end', 'page-builder-sandwich' ),
} );

/**
 * Row and column gap.
 *
 * @param {Object} props Control props.
 * @return {Element} Control.
 */
export function GapControl( props ) {
	const names = {
		row: __( 'Between rows', 'page-builder-sandwich' ),
		column: __( 'Between columns', 'page-builder-sandwich' ),
	};
	return (
		<SidesControl
			{ ...props }
			prop={ {
				...props.prop,
				opts: { keywords: [ 'normal' ], tokens: [ 'space', 'var' ] },
			} }
			names={ names }
		/>
	);
}
