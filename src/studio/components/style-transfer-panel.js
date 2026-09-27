/**
 * Copy/paste a block's style, or a whole section, through the SYSTEM clipboard — so it also works
 * between two WordPress sites (pbs-d7). The payload format is lib/style-transfer.js.
 *
 * ⭐ Reading the clipboard needs a secure context (https) and a permission. Where either is missing
 * (a plain-http staging site, a refused prompt) Studio opens a small paste box instead and the
 * user presses Ctrl/Cmd+V into it — the browser always allows a paste the user performs.
 */
import { useCallback, useState } from '@wordpress/element';
import { useDispatch, useRegistry } from '@wordpress/data';
import {
	BlockSettingsMenuControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { getBlockType, parse, serialize } from '@wordpress/blocks';
import { store as noticesStore } from '@wordpress/notices';
import {
	MenuGroup,
	MenuItem,
	Modal,
	TextareaControl,
} from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';
import {
	copyStyle as buildStyle,
	copySection as buildSection,
	readClipboard,
	styleUpdateFor,
} from '../lib/style-transfer';
import { useUi } from '../context';

/**
 * Write text to the system clipboard.
 *
 * @param {string} text Text.
 * @return {Promise<boolean>} Written.
 */
async function writeText( text ) {
	if ( window.isSecureContext && window.navigator.clipboard?.writeText ) {
		try {
			await window.navigator.clipboard.writeText( text );
			return true;
		} catch {
			// Fall through to the selection-based copy.
		}
	}
	const area = document.createElement( 'textarea' );
	area.value = text;
	area.setAttribute( 'readonly', '' );
	area.style.position = 'fixed';
	area.style.insetBlockStart = '-1000px';
	document.body.appendChild( area );
	area.select();
	let ok;
	try {
		// The legacy path, for plain-http sites where navigator.clipboard does not exist.
		ok = document.execCommand( 'copy' );
	} catch {
		ok = false;
	}
	area.remove();
	return ok;
}

/**
 * Copy/paste actions for the current selection.
 *
 * @return {Object} { copyStyle, pasteStyle, copySection, pasteSection, applyText }
 */
export function useStudioClipboard() {
	const registry = useRegistry();
	const ui = useUi();
	const { updateBlockAttributes, insertBlocks } =
		useDispatch( blockEditorStore );
	const { createSuccessNotice, createWarningNotice } =
		useDispatch( noticesStore );

	const selected = useCallback( () => {
		const s = registry.select( blockEditorStore );
		const ids = s.getSelectedBlockClientIds();
		return ids.map( ( id ) => s.getBlock( id ) ).filter( Boolean );
	}, [ registry ] );

	const notice = useCallback(
		( msg, warn = false ) =>
			( warn ? createWarningNotice : createSuccessNotice )( msg, {
				type: 'snackbar',
				id: 'pbsw-studio-clipboard',
			} ),
		[ createSuccessNotice, createWarningNotice ]
	);

	const copyStyle = useCallback( async () => {
		const [ block ] = selected();
		if ( ! block ) {
			notice(
				__( 'Select a block first.', 'page-builder-sandwich' ),
				true
			);
			return;
		}
		const ok = await writeText(
			buildStyle( block, getBlockType( block.name ) )
		);
		notice(
			ok
				? __(
						'Style copied. Paste it onto any block, on this site or another.',
						'page-builder-sandwich'
					)
				: __( 'The browser refused to copy.', 'page-builder-sandwich' ),
			! ok
		);
	}, [ selected, notice ] );

	const copySection = useCallback( async () => {
		const blocks = selected();
		if ( ! blocks.length ) {
			notice(
				__( 'Select a block first.', 'page-builder-sandwich' ),
				true
			);
			return;
		}
		const ok = await writeText(
			buildSection( serialize( blocks ), blocks.length )
		);
		notice(
			ok
				? __(
						'Section copied. Paste it into any page, on this site or another.',
						'page-builder-sandwich'
					)
				: __( 'The browser refused to copy.', 'page-builder-sandwich' ),
			! ok
		);
	}, [ selected, notice ] );

	/**
	 * Apply clipboard text for an intent ('style' or 'section').
	 */
	const applyText = useCallback(
		( text, intent ) => {
			const payload = readClipboard( text );
			if ( intent === 'style' ) {
				if ( ! payload || payload.kind !== 'style' ) {
					notice(
						__(
							'The clipboard does not hold a style copied from Sandwich Studio.',
							'page-builder-sandwich'
						),
						true
					);
					return;
				}
				let applied = 0;
				for ( const block of selected() ) {
					const update = styleUpdateFor(
						payload,
						block.name,
						getBlockType( block.name )
					);
					if ( update ) {
						updateBlockAttributes( block.clientId, update );
						applied++;
					}
				}
				notice(
					applied
						? sprintf(
								/* translators: %d: number of blocks. */
								_n(
									'Style pasted onto %d block.',
									'Style pasted onto %d blocks.',
									applied,
									'page-builder-sandwich'
								),
								applied
							)
						: __(
								'That style does not fit the selected block.',
								'page-builder-sandwich'
							),
					! applied
				);
				return;
			}
			let markup = null;
			if ( payload?.kind === 'section' ) {
				markup = payload.content;
			} else if (
				typeof text === 'string' &&
				text.includes( '<!-- wp:' )
			) {
				markup = text;
			}
			const blocks = markup ? parse( markup ) : [];
			if ( ! blocks.length ) {
				notice(
					__(
						'The clipboard does not hold a copied section.',
						'page-builder-sandwich'
					),
					true
				);
				return;
			}
			const s = registry.select( blockEditorStore );
			const ids = s.getSelectedBlockClientIds();
			const last = ids[ ids.length - 1 ];
			const root = last ? s.getBlockRootClientId( last ) : undefined;
			const index = last
				? s.getBlockIndex( last ) + 1
				: s.getBlockCount();
			insertBlocks( blocks, index, root || undefined );
			notice( __( 'Section pasted.', 'page-builder-sandwich' ) );
		},
		[ notice, selected, updateBlockAttributes, insertBlocks, registry ]
	);

	const paste = useCallback(
		async ( intent ) => {
			if ( intent === 'style' && ! selected().length ) {
				notice(
					__( 'Select a block first.', 'page-builder-sandwich' ),
					true
				);
				return;
			}
			if (
				window.isSecureContext &&
				window.navigator.clipboard?.readText
			) {
				try {
					applyText(
						await window.navigator.clipboard.readText(),
						intent
					);
					return;
				} catch {
					// Permission refused: ask for a paste the user performs.
				}
			}
			ui.setPasteIntent( intent );
		},
		[ applyText, ui, selected, notice ]
	);

	return {
		copyStyle,
		copySection,
		pasteStyle: () => paste( 'style' ),
		pasteSection: () => paste( 'section' ),
		applyText,
	};
}

/**
 * The paste box shown when the clipboard cannot be read directly.
 */
export function PasteDialog() {
	const ui = useUi();
	const { applyText } = useStudioClipboard();
	const [ value, setValue ] = useState( '' );
	if ( ! ui.pasteIntent ) {
		return null;
	}
	const close = () => {
		setValue( '' );
		ui.setPasteIntent( null );
	};
	const intent = ui.pasteIntent;
	return (
		<Modal
			title={
				intent === 'style'
					? __( 'Paste style', 'page-builder-sandwich' )
					: __( 'Paste section', 'page-builder-sandwich' )
			}
			onRequestClose={ close }
			className="pbsw-studio-paste"
		>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __(
					'Press Ctrl+V (or Cmd+V on a Mac) here to paste',
					'page-builder-sandwich'
				) }
				value={ value }
				onChange={ setValue }
				rows={ 4 }
				autoFocus // eslint-disable-line jsx-a11y/no-autofocus -- the dialog exists only to receive this paste.
				onPaste={ ( event ) => {
					const text =
						event.clipboardData?.getData( 'text/plain' ) || '';
					if ( text ) {
						event.preventDefault();
						close();
						applyText( text, intent );
					}
				} }
			/>
		</Modal>
	);
}

/**
 * Items in every block's "⋮" menu.
 */
export function BlockMenuItems() {
	const { copyStyle, pasteStyle, copySection, pasteSection } =
		useStudioClipboard();
	return (
		<BlockSettingsMenuControls>
			{ ( { onClose } ) => (
				<MenuGroup
					label={ __( 'Sandwich Studio', 'page-builder-sandwich' ) }
				>
					<MenuItem
						onClick={ () => {
							onClose();
							copyStyle();
						} }
					>
						{ __( 'Copy style', 'page-builder-sandwich' ) }
					</MenuItem>
					<MenuItem
						onClick={ () => {
							onClose();
							pasteStyle();
						} }
					>
						{ __( 'Paste style', 'page-builder-sandwich' ) }
					</MenuItem>
					<MenuItem
						onClick={ () => {
							onClose();
							copySection();
						} }
					>
						{ __( 'Copy section', 'page-builder-sandwich' ) }
					</MenuItem>
					<MenuItem
						onClick={ () => {
							onClose();
							pasteSection();
						} }
					>
						{ __( 'Paste section', 'page-builder-sandwich' ) }
					</MenuItem>
				</MenuGroup>
			) }
		</BlockSettingsMenuControls>
	);
}
