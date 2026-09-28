/**
 * pbs/form editor (pbs-m6): pick the form plugin and the form in the Content tab, the look in the
 * Styles tab; the preview is the real form, rendered by its plugin through the block's own render
 * callback (live data, so ServerSideRender), inside the block's wrapper.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	ExternalLink,
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

/** One request per editor load, shared by every Form block on the page. */
let pending = null;
export function loadProviders() {
	if ( ! pending ) {
		pending = apiFetch( { path: '/pbs/v1/forms' } ).catch( ( e ) => {
			pending = null;
			throw e;
		} );
	}
	return pending;
}

/**
 * The provider and form pickers.
 *
 * @param {Object}   props
 * @param {Array}    props.providers     From the REST route.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 */
export function Pickers( { providers, attributes, setAttributes } ) {
	const active = providers.filter( ( p ) => p.active );
	const current = providers.find(
		( p ) => p.provider === attributes.provider
	);
	if ( active.length === 0 ) {
		return (
			<p>
				{ __(
					'Install and activate Contact Form 7, WPForms, Gravity Forms or Fluent Forms, then choose one of its forms here.',
					'page-builder-sandwich'
				) }
			</p>
		);
	}
	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Form plugin', 'page-builder-sandwich' ) }
				value={ attributes.provider }
				options={ [
					{
						value: '',
						label: __( 'Choose…', 'page-builder-sandwich' ),
					},
					...active.map( ( p ) => ( {
						value: p.provider,
						label: p.label,
					} ) ),
				] }
				onChange={ ( provider ) =>
					setAttributes( { provider, formId: '' } )
				}
			/>
			{ current?.active && (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Form', 'page-builder-sandwich' ) }
					value={ attributes.formId }
					options={ [
						{
							value: '',
							label: current.forms.length
								? __(
										'Choose a form…',
										'page-builder-sandwich'
									)
								: sprintf(
										/* translators: %s: form plugin name. */
										__(
											'%s has no forms yet',
											'page-builder-sandwich'
										),
										current.label
									),
						},
						...current.forms.map( ( f ) => ( {
							value: f.id,
							label: f.title,
						} ) ),
					] }
					onChange={ ( formId ) => setAttributes( { formId } ) }
				/>
			) }
			{ current && ! current.active && (
				<p>
					{ sprintf(
						/* translators: %s: form plugin name. */
						__(
							'%s is not active on this site, so this form cannot be shown.',
							'page-builder-sandwich'
						),
						current.label
					) }
				</p>
			) }
		</>
	);
}

export default function Edit( { attributes, setAttributes, name } ) {
	const [ providers, setProviders ] = useState( null );
	const [ error, setError ] = useState( '' );
	const blockProps = useBlockProps();

	useEffect( () => {
		loadProviders().then( setProviders, ( e ) =>
			setError(
				e?.message ||
					__(
						'The form list could not be loaded.',
						'page-builder-sandwich'
					)
			)
		);
	}, [] );

	const ready = attributes.provider && attributes.formId;
	const pickers = providers ? (
		<Pickers
			providers={ providers }
			attributes={ attributes }
			setAttributes={ setAttributes }
		/>
	) : (
		<Spinner />
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form', 'page-builder-sandwich' ) }>
					{ error ? <p>{ error }</p> : pickers }
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="styles">
				<PanelBody
					title={ __( 'Form style', 'page-builder-sandwich' ) }
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Fields', 'page-builder-sandwich' ) }
						value={ attributes.fieldStyle }
						options={ [
							{
								value: 'outlined',
								label: __(
									'Outlined',
									'page-builder-sandwich'
								),
							},
							{
								value: 'filled',
								label: __( 'Filled', 'page-builder-sandwich' ),
							},
							{
								value: 'underlined',
								label: __(
									'Underlined',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( fieldStyle ) =>
							setAttributes( { fieldStyle } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Button width', 'page-builder-sandwich' ) }
						value={ attributes.buttonWidth }
						options={ [
							{
								value: 'auto',
								label: __(
									'Fit the text',
									'page-builder-sandwich'
								),
							},
							{
								value: 'full',
								label: __(
									'Full width',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( buttonWidth ) =>
							setAttributes( { buttonWidth } )
						}
					/>
					<p className="pbsw-form-help">
						{ __(
							'Colours, fonts, spacing and corners come from your site design and this block’s design settings.',
							'page-builder-sandwich'
						) }
					</p>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ ready ? (
					<ServerSideRender
						block={ name }
						attributes={ attributes }
					/>
				) : (
					<Placeholder
						icon="feedback"
						label={ __( 'Form', 'page-builder-sandwich' ) }
						instructions={ __(
							'Show a form from your form plugin, styled to match your site.',
							'page-builder-sandwich'
						) }
					>
						{ error ? <p>{ error }</p> : pickers }
						<ExternalLink href="https://wordpress.org/plugins/contact-form-7/">
							{ __(
								'Need a form plugin? Contact Form 7 is free.',
								'page-builder-sandwich'
							) }
						</ExternalLink>
					</Placeholder>
				) }
			</div>
		</>
	);
}
