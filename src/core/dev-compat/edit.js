/**
 * pbs/shortcode edit(): the controls the add-on declared, in the block sidebar.
 *
 * The canvas shows the add-on's label and the shortcode text, not a live server render: a live
 * preview would mean executing a shortcode built from editor input on the server, which is the
 * "run any shortcode" primitive the legacy plugin was audited for (10-audit-pbs.md §3,
 * class-render-shortcode.php). Rendering happens only for saved content, on the front end.
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
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
 * @return {Element} Controls.
 */
function GenericControls( { attributes, setAttributes } ) {
	const attrs = attributes.attrs || {};
	return (
		<>
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
			/>
			{ Object.keys( attrs ).map( ( name ) => (
				<TextControl
					key={ name }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ name }
					value={ String( attrs[ name ] ?? '' ) }
					onChange={ ( v ) =>
						setAttributes( { attrs: { ...attrs, [ name ]: v } } )
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
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Content', 'page-builder-sandwich' ) }
				value={ attributes.content }
				onChange={ ( content ) => setAttributes( { content } ) }
			/>
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
							__( 'Shortcode', 'page-builder-sandwich' )
						}
					>
						{ def?.desc && <p>{ def.desc }</p> }
						{ def ? (
							def.options.map( ( option, i ) => (
								<OptionControl
									key={ i }
									d={ describeOption( option ) }
									attributes={ attributes }
									setAttributes={ setAttributes }
								/>
							) )
						) : (
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
