/**
 * "Save as pattern" in Sandwich Studio's block menu (feature pbs-c5, free).
 *
 * The block editor has this built in ("Create pattern"); Studio is its own editor, so it gets the
 * same thing here: the selected blocks become a WordPress pattern (a `wp_block` post), synced or
 * not. A synced pattern replaces the selection with the pattern itself, exactly as the block
 * editor does, so editing it later changes it everywhere it is used.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock, serialize } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import {
	BlockSettingsMenuControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	Button,
	MenuItem,
	Modal,
	Notice,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Create the pattern. Exported for the unit test.
 *
 * @param {Object}   args
 * @param {string}   args.title   Name.
 * @param {string}   args.content Serialised blocks.
 * @param {boolean}  args.synced  Synced pattern?
 * @param {Function} fetch        apiFetch.
 * @return {Promise<Object>} The wp_block post.
 */
export function createPattern( { title, content, synced }, fetch = apiFetch ) {
	return fetch( {
		path: '/wp/v2/blocks',
		method: 'POST',
		data: {
			title,
			content,
			status: 'publish',
			// Core stores an empty value for synced patterns and 'unsynced' for the others.
			wp_pattern_sync_status: synced ? '' : 'unsynced',
		},
	} );
}

/**
 * The menu item and its dialog.
 *
 * @return {Element} Menu item.
 */
export default function SavePatternMenu() {
	const [ open, setOpen ] = useState( false );
	const [ title, setTitle ] = useState( '' );
	const [ synced, setSynced ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const { getBlocksByClientId, getSelectedBlockClientIds } = useSelect(
		( select ) => select( blockEditorStore ),
		[]
	);
	const { replaceBlocks } = useDispatch( blockEditorStore );

	const save = () => {
		const ids = getSelectedBlockClientIds();
		const blocks = getBlocksByClientId( ids ).filter( Boolean );
		if ( ! blocks.length ) {
			return;
		}
		setBusy( true );
		setError( '' );
		createPattern( { title, content: serialize( blocks ), synced } )
			.then( ( post ) => {
				if ( synced ) {
					replaceBlocks(
						ids,
						createBlock( 'core/block', { ref: post.id } )
					);
				}
				setOpen( false );
				setTitle( '' );
			} )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<>
			<BlockSettingsMenuControls>
				{ ( { onClose } ) => (
					<MenuItem
						onClick={ () => {
							onClose();
							setOpen( true );
						} }
					>
						{ __( 'Save as pattern', 'page-builder-sandwich' ) }
					</MenuItem>
				) }
			</BlockSettingsMenuControls>
			{ open && (
				<Modal
					title={ __( 'Save as pattern', 'page-builder-sandwich' ) }
					onRequestClose={ () => setOpen( false ) }
				>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Name', 'page-builder-sandwich' ) }
						value={ title }
						onChange={ setTitle }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Synced', 'page-builder-sandwich' ) }
						help={
							synced
								? __(
										'Editing the pattern later changes it everywhere it is used.',
										'page-builder-sandwich'
									)
								: __(
										'Each copy can be edited on its own.',
										'page-builder-sandwich'
									)
						}
						checked={ synced }
						onChange={ setSynced }
					/>
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy || ! title.trim() }
						onClick={ save }
					>
						{ __( 'Save', 'page-builder-sandwich' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
