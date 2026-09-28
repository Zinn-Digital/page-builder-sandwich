/**
 * The Widget block's friendly settings (pbs-b3): the chosen widget's own settings as real
 * controls, read from the widget's form by `pbs/v1/widgets/fields`. Settings the form does not
 * list stay editable in the generic editor, so nothing already saved is hidden.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import {
	CheckboxControl,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

const cache = new Map();

/**
 * Load a widget's fields (cached per class for the editor session).
 *
 * @param {string} widget Widget class.
 * @return {Promise<Array>} Fields.
 */
export function loadFields( widget ) {
	if ( ! cache.has( widget ) ) {
		cache.set(
			widget,
			apiFetch( {
				path: addQueryArgs( '/pbs/v1/widgets/fields', { widget } ),
			} )
				.then( ( r ) => ( Array.isArray( r?.fields ) ? r.fields : [] ) )
				.catch( () => {
					cache.delete( widget );
					return [];
				} )
		);
	}
	return cache.get( widget );
}

/**
 * The instance after one field changes. Pure.
 *
 * @param {Object} instance Instance.
 * @param {Object} field    Field.
 * @param {*}      value    New value (a boolean for a checkbox).
 * @return {Object} New instance.
 */
export function withField( instance, field, value ) {
	const next = { ...( instance || {} ) };
	if ( field.kind === 'checkbox' ) {
		if ( value ) {
			next[ field.name ] = field.value || 'on';
		} else {
			delete next[ field.name ];
		}
		return next;
	}
	next[ field.name ] = value;
	return next;
}

/**
 * The value a field shows: the instance's, else the widget's own default. Pure.
 *
 * @param {Object} instance Instance.
 * @param {Object} field    Field.
 * @return {*} Value.
 */
export function fieldValue( instance, field ) {
	const has =
		instance &&
		Object.prototype.hasOwnProperty.call( instance, field.name );
	if ( field.kind === 'checkbox' ) {
		return has
			? !! instance[ field.name ] && instance[ field.name ] !== '0'
			: !! field.default;
	}
	return has
		? String( instance[ field.name ] ?? '' )
		: String( field.default ?? '' );
}

/**
 * The controls.
 *
 * @param {Object}   props
 * @param {string}   props.widget   Widget class.
 * @param {Object}   props.instance Instance.
 * @param {Function} props.onChange New instance.
 * @param {Function} props.children `( fields ) => ReactNode` for the generic editor of the rest.
 */
export default function WidgetFields( {
	widget,
	instance,
	onChange,
	children,
} ) {
	const [ fields, setFields ] = useState( null );
	useEffect( () => {
		let live = true;
		setFields( null );
		if ( widget ) {
			loadFields( widget ).then( ( f ) => live && setFields( f ) );
		}
		return () => {
			live = false;
		};
	}, [ widget ] );

	if ( ! widget ) {
		return null;
	}
	if ( fields === null ) {
		return <Spinner />;
	}
	return (
		<>
			{ fields.map( ( field ) => {
				const value = fieldValue( instance, field );
				const set = ( v ) =>
					onChange( withField( instance, field, v ) );
				switch ( field.kind ) {
					case 'checkbox':
						return (
							<CheckboxControl
								__nextHasNoMarginBottom
								key={ field.name }
								label={ field.label }
								checked={ value }
								onChange={ set }
							/>
						);
					case 'select':
						return (
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								key={ field.name }
								label={ field.label }
								value={ value }
								options={ field.choices || [] }
								onChange={ set }
							/>
						);
					case 'textarea':
						return (
							<TextareaControl
								__nextHasNoMarginBottom
								key={ field.name }
								label={ field.label }
								value={ value }
								onChange={ set }
							/>
						);
					default:
						return (
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								key={ field.name }
								type={
									[ 'number', 'url', 'email' ].includes(
										field.kind
									)
										? field.kind
										: 'text'
								}
								label={ field.label }
								value={ value }
								onChange={ set }
							/>
						);
				}
			} ) }
			{ fields.length === 0 && (
				<p className="pbsw-widget-help">
					{ __(
						'This widget does not describe its settings, so they are edited as name and value pairs.',
						'page-builder-sandwich'
					) }
				</p>
			) }
			{ children( fields ) }
		</>
	);
}
