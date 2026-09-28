/**
 * pbs/shortcode edit(): the controls the add-on declared, in the block sidebar.
 *
 * The canvas shows the add-on's label and the shortcode text, not a live server render: a live
 * preview would mean executing a shortcode built from editor input on the server, which is the
 * "run any shortcode" primitive the legacy plugin was audited for (10-audit-pbs.md §3,
 * class-render-shortcode.php). Rendering happens only for saved content, on the front end.
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

import { buildShortcode, ATTR_NAME_RE, TAG_RE } from './serialize';
import { describeOption } from './controls';
import { OptionControl } from './option-controls';
import { knownDefinition } from './known';

/** Site data the friendly panels read, loaded once per editor. */
const siteData = {};
function useSiteData( key, path, enabled ) {
	const [ value, setValue ] = useState( siteData[ key ] ?? null );
	useEffect( () => {
		if ( ! enabled || siteData[ key ] ) {
			return;
		}
		siteData[ key ] = apiFetch( { path } ).catch( () => [] );
		siteData[ key ].then( ( v ) => {
			siteData[ key ] = v;
			setValue( v );
		} );
	}, [ key, path, enabled ] );
	return Array.isArray( value ) ? value : null;
}

/**
 * The legacy `pbs:shortcode-render` event. Legacy fired it on the editor's document after a
 * shortcode was drawn, with the element as `detail` (script.js:10007), so an add-on could
 * initialise its preview. It is fired the same way here, from the editor only.
 *
 * @param {Document} doc    Where listeners are (the editor's top document).
 * @param {Element}  detail The block element.
 * @return {void}
 */
export function dispatchRender( doc, detail ) {
	doc.dispatchEvent(
		new window.CustomEvent( 'pbs:shortcode-render', { detail } )
	);
}

/**
 * Controls for a tag no add-on has described: the tag, free attributes, the content.
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 * @param {string[]} props.exclude       Attribute names another control already offers.
 * @param {boolean}  props.showContent   Whether to offer the content field.
 * @return {Element} Controls.
 */
function GenericControls( {
	attributes,
	setAttributes,
	exclude = [],
	showContent = true,
} ) {
	const attrs = attributes.attrs || {};
	const tags = useSiteData( 'tags', '/pbs/v1/shortcodes', true );
	return (
		<>
			{ tags && (
				<datalist id="pbsw-shortcode-tags">
					{ tags.map( ( t ) => (
						<option key={ t } value={ t } />
					) ) }
				</datalist>
			) }
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Shortcode tag', 'page-builder-sandwich' ) }
				value={ attributes.tag }
				help={
					attributes.tag && ! TAG_RE.test( attributes.tag )
						? __(
								'Use letters, numbers, dashes and underscores only.',
								'page-builder-sandwich'
							)
						: undefined
				}
				onChange={ ( tag ) => setAttributes( { tag } ) }
				list="pbsw-shortcode-tags"
				autoComplete="off"
			/>
			{ Object.keys( attrs )
				.filter( ( name ) => ! exclude.includes( name ) )
				.map( ( name ) => (
					<TextControl
						key={ name }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ name }
						value={ String( attrs[ name ] ?? '' ) }
						onChange={ ( v ) =>
							setAttributes( {
								attrs: { ...attrs, [ name ]: v },
							} )
						}
					/>
				) ) }
			<Button
				variant="secondary"
				onClick={ () => {
					// eslint-disable-next-line no-alert -- a one-field prompt is enough here.
					const name = window.prompt(
						__( 'Attribute name', 'page-builder-sandwich' )
					);
					if ( name && ATTR_NAME_RE.test( name ) ) {
						setAttributes( { attrs: { ...attrs, [ name ]: '' } } );
					}
				} }
			>
				{ __( 'Add attribute', 'page-builder-sandwich' ) }
			</Button>
			{ showContent && (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Content', 'page-builder-sandwich' ) }
					value={ attributes.content }
					onChange={ ( content ) => setAttributes( { content } ) }
				/>
			) }
		</>
	);
}

/**
 * Build the edit component for a registry.
 *
 * @param {Object} registry From createRegistry().
 * @return {Function} Edit component.
 */
export function makeEdit( registry ) {
	return function Edit( { attributes, setAttributes } ) {
		const ref = useRef();
		const def = registry.get( attributes.tag );
		const isForm = [
			'contact-form-7',
			'wpforms',
			'gravityform',
			'fluentform',
		].includes( attributes.tag );
		const providers = useSiteData(
			'forms',
			'/pbs/v1/forms',
			isForm && ! def
		);
		const known = def
			? null
			: knownDefinition( attributes.tag, { providers } );
		const text = buildShortcode(
			attributes.tag,
			attributes.attrs,
			attributes.content
		);

		useEffect( () => {
			if ( ref.current && text ) {
				dispatchRender( window.document, ref.current );
			}
		}, [ text ] );

		return (
			<div { ...useBlockProps( { ref } ) }>
				<InspectorControls>
					<PanelBody
						title={
							def?.label ||
							known?.label ||
							__( 'Shortcode', 'page-builder-sandwich' )
						}
					>
						{ def?.desc && <p>{ def.desc }</p> }
						{ def &&
							def.options.map( ( option, i ) => (
								<OptionControl
									key={ i }
									d={ describeOption( option ) }
									attributes={ attributes }
									setAttributes={ setAttributes }
								/>
							) ) }
						{ ! def && known && (
							<>
								{ known.desc && <p>{ known.desc }</p> }
								{ known.options.map( ( option ) => (
									<OptionControl
										key={ option.id }
										d={ describeOption( option ) }
										attributes={ attributes }
										setAttributes={ setAttributes }
									/>
								) ) }
								<details className="pbsw-shortcode-more">
									<summary>
										{ __(
											'Other attributes',
											'page-builder-sandwich'
										) }
									</summary>
									<GenericControls
										attributes={ attributes }
										setAttributes={ setAttributes }
										exclude={ known.options.map(
											( o ) => o.id
										) }
										showContent={
											! known.options.some(
												( o ) => o.id === 'content'
											)
										}
									/>
								</details>
							</>
						) }
						{ ! def && ! known && (
							<GenericControls
								attributes={ attributes }
								setAttributes={ setAttributes }
							/>
						) }
					</PanelBody>
				</InspectorControls>
				<strong>
					{ def?.label || __( 'Shortcode', 'page-builder-sandwich' ) }
				</strong>
				<pre>
					{ text ||
						__(
							'Choose a shortcode tag in the block settings.',
							'page-builder-sandwich'
						) }
				</pre>
			</div>
		);
	};
}
