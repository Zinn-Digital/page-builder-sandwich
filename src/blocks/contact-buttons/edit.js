/**
 * pbs/contact-buttons editor: the buttons as visitors see them, each channel edited in the sidebar.
 */
import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	Placeholder,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { plus, trash } from '@wordpress/icons';

import { cls } from '../shared/cls';
import { contactHref } from './links';

export default function Edit( { attributes, setAttributes } ) {
	const items = attributes.items || [];
	const types = {
		phone: __( 'Phone call', 'page-builder-sandwich' ),
		email: __( 'Email', 'page-builder-sandwich' ),
		sms: __( 'Text message', 'page-builder-sandwich' ),
		whatsapp: __( 'WhatsApp', 'page-builder-sandwich' ),
	};
	const defaults = ( item ) =>
		( {
			phone: sprintf(
				/* translators: %s: a phone number. */ __(
					'Call %s',
					'page-builder-sandwich'
				),
				item.link
			),
			email: sprintf(
				/* translators: %s: an email address. */ __(
					'Email %s',
					'page-builder-sandwich'
				),
				item.link
			),
			sms: sprintf(
				/* translators: %s: a phone number. */ __(
					'Text %s',
					'page-builder-sandwich'
				),
				item.link
			),
			whatsapp: __( 'Chat on WhatsApp', 'page-builder-sandwich' ),
		} )[ item.type ];
	const set = ( i, patch ) =>
		setAttributes( {
			items: items.map( ( it, j ) =>
				j === i ? { ...it, ...patch } : it
			),
		} );
	const blockProps = useBlockProps();
	const shown = items.filter( ( it ) =>
		contactHref( it.type, it.link, attributes.message )
	);

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Buttons', 'page-builder-sandwich' ) }>
					{ items.map( ( item, i ) => (
						<fieldset key={ i } className="pbsw-contact-item">
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Type', 'page-builder-sandwich' ) }
								value={ item.type }
								options={ Object.entries( types ).map(
									( [ value, label ] ) => ( { value, label } )
								) }
								onChange={ ( type ) => set( i, { type } ) }
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={
									item.type === 'email'
										? __(
												'Email address',
												'page-builder-sandwich'
											)
										: __(
												'Phone number, with country code',
												'page-builder-sandwich'
											)
								}
								value={ item.link || '' }
								onChange={ ( link ) => set( i, { link } ) }
							/>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Button text',
									'page-builder-sandwich'
								) }
								placeholder={ defaults( item ) }
								value={ item.label || '' }
								onChange={ ( label ) => set( i, { label } ) }
							/>
							<Button
								icon={ trash }
								isDestructive
								variant="tertiary"
								onClick={ () =>
									setAttributes( {
										items: items.filter(
											( _, j ) => j !== i
										),
									} )
								}
							>
								{ __(
									'Remove this button',
									'page-builder-sandwich'
								) }
							</Button>
						</fieldset>
					) ) }
					<Button
						icon={ plus }
						variant="secondary"
						disabled={ items.length >= 8 }
						onClick={ () =>
							setAttributes( {
								items: [
									...items,
									{ type: 'phone', value: '', label: '' },
								],
							} )
						}
					>
						{ __( 'Add a button', 'page-builder-sandwich' ) }
					</Button>
					{ items.some( ( it ) => it.type === 'whatsapp' ) && (
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __(
								'WhatsApp message to start with (optional)',
								'page-builder-sandwich'
							) }
							value={ attributes.message }
							onChange={ ( message ) =>
								setAttributes( { message } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			{ shown.length ? (
				<ul className={ cls( 'contact-buttons' ) }>
					{ shown.map( ( item, i ) => (
						<li
							key={ i }
							className={ cls( 'contact-buttons__item' ) }
						>
							<span
								className={ cls(
									'contact-buttons__link',
									`contact-buttons__link--${ item.type }`
								) }
							>
								<span
									className={ cls(
										'contact-buttons__label'
									) }
								>
									{ item.label || defaults( item ) }
								</span>
							</span>
						</li>
					) ) }
				</ul>
			) : (
				<Placeholder
					icon="phone"
					label={ __( 'Contact buttons', 'page-builder-sandwich' ) }
					instructions={ __(
						'Add a phone number, an email address or a WhatsApp number in the sidebar.',
						'page-builder-sandwich'
					) }
				>
					<Button
						variant="primary"
						onClick={ () =>
							setAttributes( {
								items: [
									...items,
									{ type: 'phone', value: '', label: '' },
								],
							} )
						}
					>
						{ __( 'Add a button', 'page-builder-sandwich' ) }
					</Button>
				</Placeholder>
			) }
		</div>
	);
}
