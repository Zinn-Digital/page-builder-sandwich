/**
 * Descriptor (controls.js) → a real editor control.
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	BaseControl,
	Button,
	CheckboxControl,
	ColorPalette,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { useId } from '@wordpress/element';

import { patchFor, readValue } from './controls';

/**
 * The note shown under a degraded control.
 *
 * @param {Object} d Descriptor.
 * @return {string} Help text.
 */
export function helpFor( d ) {
	if ( ! d.degraded ) {
		return d.help;
	}
	const note = sprintf(
		/* translators: %s: the legacy control type, e.g. "border". */
		__(
			'The "%s" control from the old editor is not available in the block editor. Enter the value as text.',
			'page-builder-sandwich'
		),
		d.legacyType
	);
	return d.help ? d.help + ' ' + note : note;
}

/**
 * Media picker for image (attachment IDs) and file (URL) options.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.d        Descriptor.
 * @param {string}   props.value    Current value.
 * @param {Function} props.onChange Change handler.
 * @return {Element} Control.
 */
function MediaOption( { d, value, onChange } ) {
	const isImage = d.kind === 'image';
	const ids = isImage && value ? value.split( ',' ).map( Number ) : [];
	return (
		<div>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ d.label }
				help={ helpFor( d ) }
				value={ value }
				placeholder={ d.placeholder }
				onChange={ onChange }
			/>
			<MediaUploadCheck>
				<MediaUpload
					allowedTypes={ isImage ? [ 'image' ] : undefined }
					multiple={ isImage && d.multiple }
					gallery={ isImage && d.multiple }
					value={ isImage ? ids : undefined }
					onSelect={ ( media ) => {
						const list = Array.isArray( media ) ? media : [ media ];
						onChange(
							isImage
								? list.map( ( m ) => m.id ).join( ',' )
								: ( list[ 0 ]?.url ?? '' )
						);
					} }
					render={ ( { open } ) => (
						<Button variant="secondary" onClick={ open }>
							{ isImage
								? __( 'Choose image', 'page-builder-sandwich' )
								: __( 'Choose file', 'page-builder-sandwich' ) }
						</Button>
					) }
				/>
			</MediaUploadCheck>
		</div>
	);
}

/**
 * One control for one descriptor.
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.d             Descriptor.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Block setAttributes.
 * @return {Element|null} Control.
 */
export function OptionControl( { d, attributes, setAttributes } ) {
	const id = useId();
	const value = readValue( d, attributes );
	const onChange = ( next ) =>
		setAttributes( patchFor( d, next, attributes ) );
	const common = {
		__nextHasNoMarginBottom: true,
		label: d.label,
		help: helpFor( d ),
	};

	switch ( d.kind ) {
		case 'note':
			return d.label || d.help ? (
				<div className="pbsw-dev-compat-note">
					{ d.label && <strong>{ d.label }</strong> }
					{ d.help && <p>{ d.help }</p> }
				</div>
			) : null;
		case 'textarea':
			return (
				<TextareaControl
					{ ...common }
					value={ value }
					placeholder={ d.placeholder }
					onChange={ onChange }
				/>
			);
		case 'select':
			return (
				<SelectControl
					{ ...common }
					__next40pxDefaultSize
					value={ value }
					options={ [
						{
							value: '',
							label: __( '— Default —', 'page-builder-sandwich' ),
						},
						...d.choices,
					] }
					onChange={ onChange }
				/>
			);
		case 'checkbox':
			return (
				<CheckboxControl
					{ ...common }
					checked={ value }
					onChange={ onChange }
				/>
			);
		case 'multicheck':
			return (
				<BaseControl { ...common } id={ id }>
					{ d.choices.map( ( c ) => (
						<CheckboxControl
							key={ c.value }
							__nextHasNoMarginBottom
							label={ c.label }
							checked={ value.includes( c.value ) }
							onChange={ ( on ) =>
								onChange(
									on
										? [ ...value, c.value ]
										: value.filter( ( v ) => v !== c.value )
								)
							}
						/>
					) ) }
				</BaseControl>
			);
		case 'color':
			return (
				<BaseControl { ...common } id={ id }>
					<ColorPalette
						colors={ [] }
						value={ value || undefined }
						onChange={ ( c ) => onChange( c ?? '' ) }
					/>
				</BaseControl>
			);
		case 'image':
		case 'file':
			return (
				<MediaOption d={ d } value={ value } onChange={ onChange } />
			);
		case 'number':
			return (
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					type="number"
					min={ d.min }
					max={ d.max }
					step={ d.step }
					value={ value }
					onChange={ onChange }
				/>
			);
		case 'link':
			return (
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					type="url"
					value={ value }
					placeholder={ d.placeholder }
					onChange={ onChange }
				/>
			);
		default:
			return (
				<TextControl
					{ ...common }
					__next40pxDefaultSize
					value={ value }
					placeholder={ d.placeholder }
					onChange={ onChange }
				/>
			);
	}
}
