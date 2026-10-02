/**
 * The AI menu on every text block's toolbar (pbs-ai1): improve, shorten, expand, fix, change the
 * tone, or write/rewrite as asked. The answer replaces the block's text in the editor (one undo
 * step); nothing is saved until the page is.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { BlockControls } from '@wordpress/block-editor';
import {
	ToolbarGroup,
	ToolbarDropdownMenu,
	MenuGroup,
	MenuItem,
	Modal,
	TextareaControl,
	Button,
	Spinner,
	Flex,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { getBlockType } from '@wordpress/blocks';
import { store as noticesStore } from '@wordpress/notices';

import { ai, status, AiError } from './api';

/**
 * The attribute that holds a block's own text, or '' when it has none.
 *
 * @param {string} name Block name.
 * @return {string} Attribute name.
 */
export function textAttribute( name ) {
	const attrs = getBlockType( name )?.attributes || {};
	for ( const key of [ 'content', 'text', 'value', 'citation' ] ) {
		const a = attrs[ key ];
		if ( a && ( a.type === 'rich-text' || a.type === 'string' ) ) {
			return key;
		}
	}
	return '';
}

/**
 * The tone labels.
 *
 * @return {Object} tone => label.
 */
function tones() {
	return {
		professional: __( 'Professional', 'page-builder-sandwich' ),
		friendly: __( 'Friendly', 'page-builder-sandwich' ),
		confident: __( 'Confident', 'page-builder-sandwich' ),
		playful: __( 'Playful', 'page-builder-sandwich' ),
		formal: __( 'Formal', 'page-builder-sandwich' ),
		simple: __( 'Simple', 'page-builder-sandwich' ),
	};
}

/**
 * The toolbar menu.
 *
 * @param {Object}   props
 * @param {string}   props.name          Block name.
 * @param {Object}   props.attributes    Attributes.
 * @param {Function} props.setAttributes Setter.
 * @return {Element|null} Controls.
 */
export default function TextMenu( { name, attributes, setAttributes } ) {
	const key = textAttribute( name );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ asking, setAsking ] = useState( false );
	const [ instruction, setInstruction ] = useState( '' );
	const { createSuccessNotice } = useDispatch( noticesStore );
	if ( ! key || ! status().ready ) {
		return null;
	}
	const text = String( attributes[ key ]?.toString?.() ?? '' );

	const run = ( action, extra = {} ) => {
		setBusy( true );
		setError( null );
		ai( 'text', { action, text, ...extra } )
			.then( ( res ) => {
				setAttributes( { [ key ]: res.text } );
				setAsking( false );
				setInstruction( '' );
				createSuccessNotice(
					__(
						'AI changed the text. Undo to go back.',
						'page-builder-sandwich'
					),
					{ type: 'snackbar' }
				);
			} )
			.catch( setError )
			.finally( () => setBusy( false ) );
	};

	return (
		<>
			<BlockControls group="other">
				<ToolbarGroup>
					<ToolbarDropdownMenu
						icon={ busy ? <Spinner /> : 'superhero' }
						label={ __( 'AI writing', 'page-builder-sandwich' ) }
					>
						{ ( { onClose } ) => (
							<>
								<MenuGroup>
									{ text ? (
										<>
											<MenuItem
												disabled={ busy }
												onClick={ () => {
													onClose();
													run( 'improve' );
												} }
											>
												{ __(
													'Improve',
													'page-builder-sandwich'
												) }
											</MenuItem>
											<MenuItem
												disabled={ busy }
												onClick={ () => {
													onClose();
													run( 'shorten' );
												} }
											>
												{ __(
													'Make shorter',
													'page-builder-sandwich'
												) }
											</MenuItem>
											<MenuItem
												disabled={ busy }
												onClick={ () => {
													onClose();
													run( 'expand' );
												} }
											>
												{ __(
													'Make longer',
													'page-builder-sandwich'
												) }
											</MenuItem>
											<MenuItem
												disabled={ busy }
												onClick={ () => {
													onClose();
													run( 'fix' );
												} }
											>
												{ __(
													'Fix spelling and grammar',
													'page-builder-sandwich'
												) }
											</MenuItem>
										</>
									) : null }
									<MenuItem
										disabled={ busy }
										onClick={ () => {
											onClose();
											setAsking( true );
										} }
									>
										{ text
											? __(
													'Rewrite as I ask…',
													'page-builder-sandwich'
												)
											: __(
													'Write with AI…',
													'page-builder-sandwich'
												) }
									</MenuItem>
								</MenuGroup>
								{ text ? (
									<MenuGroup
										label={ __(
											'Change the tone',
											'page-builder-sandwich'
										) }
									>
										{ Object.entries( tones() ).map(
											( [ tone, label ] ) => (
												<MenuItem
													key={ tone }
													disabled={ busy }
													onClick={ () => {
														onClose();
														run( 'tone', { tone } );
													} }
												>
													{ label }
												</MenuItem>
											)
										) }
									</MenuGroup>
								) : null }
							</>
						) }
					</ToolbarDropdownMenu>
				</ToolbarGroup>
			</BlockControls>
			{ asking && (
				<Modal
					title={
						text
							? __( 'Rewrite with AI', 'page-builder-sandwich' )
							: __( 'Write with AI', 'page-builder-sandwich' )
					}
					onRequestClose={ () => setAsking( false ) }
				>
					<AiError
						error={ error }
						onDismiss={ () => setError( null ) }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __(
							'What should it say?',
							'page-builder-sandwich'
						) }
						value={ instruction }
						onChange={ setInstruction }
						rows={ 3 }
					/>
					<Flex justify="flex-end">
						{ busy && <Spinner /> }
						<Button
							variant="primary"
							disabled={ busy || instruction.trim().length < 3 }
							onClick={ () =>
								run( text ? 'custom' : 'write', {
									instruction,
								} )
							}
						>
							{ __( 'Write', 'page-builder-sandwich' ) }
						</Button>
					</Flex>
				</Modal>
			) }
			{ ! asking && error && (
				<Modal
					title={ __( 'AI writing', 'page-builder-sandwich' ) }
					onRequestClose={ () => setError( null ) }
				>
					<AiError
						error={ error }
						onDismiss={ () => setError( null ) }
					/>
				</Modal>
			) }
		</>
	);
}
