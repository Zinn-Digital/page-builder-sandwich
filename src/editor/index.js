import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import fixture from '../../blocks/fixture/block.json';
import switcher from '../../blocks/fixture-switcher/block.json';
// The language switcher element (Tranzly tz-l2); its block.json is in blocks/language-switcher/.
import './language-switcher';

/*
 * Both blocks are dynamic: the server renders them (includes/class-pbs-fixture.php), so the
 * editor previews exactly the HTML a visitor gets and save() stores attributes only.
 */

function FixtureEdit( { attributes, setAttributes } ) {
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Content', 'page-builder-sandwich' ) }>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Heading', 'page-builder-sandwich' ) }
						value={ attributes.heading }
						onChange={ ( heading ) => setAttributes( { heading } ) }
					/>
					<TextareaControl
						label={ __( 'Text', 'page-builder-sandwich' ) }
						value={ attributes.body }
						onChange={ ( body ) => setAttributes( { body } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Style', 'page-builder-sandwich' ) }
						value={ attributes.tone }
						options={ [
							{
								label: __( 'Plain', 'page-builder-sandwich' ),
								value: 'plain',
							},
							{
								label: __( 'Accent', 'page-builder-sandwich' ),
								value: 'accent',
							},
						] }
						onChange={ ( tone ) => setAttributes( { tone } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block={ fixture.name }
				attributes={ attributes }
			/>
		</div>
	);
}

function SwitcherEmpty() {
	return (
		<p>
			{ __(
				'No languages to switch between. The switcher appears when a translation plugin lists two or more languages.',
				'page-builder-sandwich'
			) }
		</p>
	);
}

function SwitcherEdit( { attributes, setAttributes } ) {
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Switcher', 'page-builder-sandwich' ) }>
					<TextControl
						__next40pxDefaultSize
						label={ __(
							'Accessible label',
							'page-builder-sandwich'
						) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
					<ToggleControl
						label={ __(
							'Show the current language',
							'page-builder-sandwich'
						) }
						checked={ attributes.showCurrent }
						onChange={ ( showCurrent ) =>
							setAttributes( { showCurrent } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block={ switcher.name }
				attributes={ attributes }
				EmptyResponsePlaceholder={ SwitcherEmpty }
			/>
		</div>
	);
}

registerBlockType( fixture.name, { edit: FixtureEdit, save: () => null } );
registerBlockType( switcher.name, { edit: SwitcherEdit, save: () => null } );
