/**
 * pbs/protected editor: the protected blocks edited in place; the password is set in the sidebar.
 * Only its hash is stored (hashed by the server, pbs/v1/blocks/protected/hash), never the password.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	InnerBlocks,
	InspectorControls,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { Button, Notice, PanelBody, TextControl } from '@wordpress/components';

import { cls } from '../shared/cls';
import { newId } from '../../design/ids';

export default function Edit( { attributes, setAttributes } ) {
	const { lockId, hash, message } = attributes;
	const [ password, setPassword ] = useState( '' );
	const [ state, setState ] = useState( null );
	useEffect( () => {
		if ( ! lockId ) {
			setAttributes( { lockId: newId() } );
		}
	}, [ lockId, setAttributes ] );

	const save = () => {
		setState( 'busy' );
		apiFetch( {
			path: '/pbs/v1/blocks/protected/hash',
			method: 'POST',
			data: { password },
		} )
			.then( ( r ) => {
				setAttributes( { hash: r.hash } );
				setPassword( '' );
				setState( 'saved' );
			} )
			.catch( ( e ) => setState( e.message ) );
	};

	const blockProps = useBlockProps( {
		className: cls( 'protected', 'protected--open' ),
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: cls( 'protected__content' ) },
		{ renderAppender: InnerBlocks.ButtonBlockAppender }
	);

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Password', 'page-builder-sandwich' ) }>
					<p>
						{ hash
							? __(
									'A password is set. Visitors see a password form instead of this content until they enter it.',
									'page-builder-sandwich'
								)
							: __(
									'No password is set yet, so visitors see nothing here.',
									'page-builder-sandwich'
								) }
					</p>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						type="password"
						autoComplete="new-password"
						label={
							hash
								? __( 'New password', 'page-builder-sandwich' )
								: __( 'Password', 'page-builder-sandwich' )
						}
						value={ password }
						onChange={ setPassword }
					/>
					<Button
						variant="secondary"
						isBusy={ state === 'busy' }
						disabled={ password.length < 4 || state === 'busy' }
						accessibleWhenDisabled
						onClick={ save }
					>
						{ __( 'Set the password', 'page-builder-sandwich' ) }
					</Button>
					{ state === 'saved' && (
						<Notice status="success" isDismissible={ false }>
							{ __(
								'Password set. Save the page to apply it; visitors who had unlocked it will need the new one.',
								'page-builder-sandwich'
							) }
						</Notice>
					) }
					{ state && state !== 'busy' && state !== 'saved' && (
						<Notice status="error" isDismissible={ false }>
							{ state }
						</Notice>
					) }
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'If Page Builder Sandwich is ever switched off, this content is shown to everyone.',
							'page-builder-sandwich'
						) }
					</Notice>
				</PanelBody>
			</InspectorControls>
			<RichText
				tagName="p"
				className={ cls( 'protected__message' ) }
				value={ message }
				onChange={ ( v ) => setAttributes( { message: v } ) }
				placeholder={ __(
					'Message above the password form (optional)',
					'page-builder-sandwich'
				) }
			/>
			<div { ...innerBlocksProps } />
		</div>
	);
}
