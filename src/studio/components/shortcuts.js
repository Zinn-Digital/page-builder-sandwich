/**
 * Keyboard shortcuts (pbs-r15): Studio's own, registered in the keyboard-shortcuts store so the
 * help screen and the command palette show the same key names the handler listens for.
 */
import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	store as keyboardShortcutsStore,
	useShortcut,
} from '@wordpress/keyboard-shortcuts';
import { store as coreStore } from '@wordpress/core-data';
import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSave } from '../hooks/use-save';
import { useUi } from '../context';

export const STUDIO_SHORTCUTS = [
	{
		name: 'pbsw/studio/save',
		category: 'global',
		description: __( 'Save your changes.', 'page-builder-sandwich' ),
		keyCombination: { modifier: 'primary', character: 's' },
	},
	{
		name: 'pbsw/studio/undo',
		category: 'global',
		description: __( 'Undo your last change.', 'page-builder-sandwich' ),
		keyCombination: { modifier: 'primary', character: 'z' },
	},
	{
		name: 'pbsw/studio/redo',
		category: 'global',
		description: __( 'Redo your last undo.', 'page-builder-sandwich' ),
		keyCombination: { modifier: 'primaryShift', character: 'z' },
		aliases: [ { modifier: 'primary', character: 'y' } ],
	},
	{
		name: 'pbsw/studio/toggle-layers',
		category: 'global',
		description: __(
			'Show or hide the layers panel.',
			'page-builder-sandwich'
		),
		keyCombination: { modifier: 'access', character: 'o' },
	},
	{
		name: 'pbsw/studio/keyboard-shortcuts',
		category: 'global',
		description: __(
			'Display these keyboard shortcuts.',
			'page-builder-sandwich'
		),
		keyCombination: { modifier: 'access', character: 'h' },
	},
];

/** Shortcuts shown in the help, beyond Studio's own (registered by core packages). */
const CORE_SHORTCUTS = [
	'core/commands',
	'core/block-editor/duplicate',
	'core/block-editor/remove',
	'core/block-editor/insert-before',
	'core/block-editor/insert-after',
	'core/block-editor/move-up',
	'core/block-editor/move-down',
	'core/block-editor/select-all',
	'core/block-editor/unselect',
];

/**
 * Registers and handles Studio's shortcuts.
 */
export function StudioShortcuts() {
	const { registerShortcut } = useDispatch( keyboardShortcutsStore );
	const { undo, redo } = useDispatch( coreStore );
	const { save } = useSave();
	const ui = useUi();

	useEffect( () => {
		STUDIO_SHORTCUTS.forEach( ( s ) => registerShortcut( s ) );
	}, [ registerShortcut ] );

	useShortcut( 'pbsw/studio/save', ( event ) => {
		event.preventDefault();
		save();
	} );
	useShortcut( 'pbsw/studio/undo', ( event ) => {
		event.preventDefault();
		undo();
	} );
	useShortcut( 'pbsw/studio/redo', ( event ) => {
		event.preventDefault();
		redo();
	} );
	useShortcut( 'pbsw/studio/toggle-layers', ( event ) => {
		event.preventDefault();
		ui.toggleLeft( 'layers' );
	} );
	useShortcut( 'pbsw/studio/keyboard-shortcuts', ( event ) => {
		event.preventDefault();
		ui.setHelpOpen( ( v ) => ! v );
	} );
	return null;
}

/**
 * The shortcuts help screen.
 */
export function ShortcutsHelp() {
	const ui = useUi();
	const rows = useSelect(
		( select ) => {
			if ( ! ui.helpOpen ) {
				return [];
			}
			const s = select( keyboardShortcutsStore );
			return [
				...STUDIO_SHORTCUTS.map( ( x ) => x.name ),
				...CORE_SHORTCUTS,
			]
				.map( ( name ) => ( {
					name,
					keys: s.getShortcutRepresentation( name, 'display' ),
					description: s.getShortcutDescription( name ),
				} ) )
				.filter( ( r ) => r.keys && r.description );
		},
		[ ui.helpOpen ]
	);
	if ( ! ui.helpOpen ) {
		return null;
	}
	return (
		<Modal
			title={ __( 'Keyboard shortcuts', 'page-builder-sandwich' ) }
			onRequestClose={ () => ui.setHelpOpen( false ) }
			className="pbsw-studio-help"
		>
			<dl className="pbsw-studio-help__list">
				{ rows.map( ( r ) => (
					<div key={ r.name } className="pbsw-studio-help__row">
						<dt>
							<kbd>{ r.keys }</kbd>
						</dt>
						<dd>{ r.description }</dd>
					</div>
				) ) }
			</dl>
		</Modal>
	);
}
