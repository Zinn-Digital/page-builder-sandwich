/**
 * The command palette (pbs-r15): Ctrl/Cmd+K opens it (the `core/commands` shortcut CommandMenu
 * registers). Studio adds its actions, "add a block" by name, and "open another page".
 */
import { useMemo } from '@wordpress/element';
import { useDispatch, useSelect, useRegistry } from '@wordpress/data';
import { useCommand, useCommandLoader } from '@wordpress/commands';
import { store as coreStore } from '@wordpress/core-data';
import {
	createBlock,
	getBlockTypes,
	store as blocksStore,
} from '@wordpress/blocks';
import { BlockIcon, store as blockEditorStore } from '@wordpress/block-editor';
import { __, sprintf } from '@wordpress/i18n';
import {
	backup,
	blockDefault,
	copy,
	desktop,
	keyboard,
	listView,
	mobile,
	page as pageIcon,
	plus,
	redo as redoIcon,
	shield,
	tablet,
	undo as undoIcon,
	wordpress,
	check,
} from '@wordpress/icons';
import { useSave } from '../hooks/use-save';
import { useBoot, useUi } from '../context';
import { useSafeModeToggle } from './safe-mode';
import { useStudioClipboard } from './style-transfer-panel';

/**
 * Where a new block goes: after the selected block, or at the end.
 *
 * @param {Object} s block-editor selectors.
 * @return {{root: string|undefined, index: number}} Insertion point.
 */
function insertionPoint( s ) {
	const id = s.getSelectedBlockClientId();
	if ( ! id ) {
		return { root: undefined, index: s.getBlockCount() };
	}
	const root = s.getBlockRootClientId( id ) || undefined;
	return { root, index: s.getBlockIndex( id ) + 1 };
}

/**
 * "Add block: …" commands, matched against the search.
 *
 * @param {Object} props
 * @param {string} props.search Search text.
 * @return {Object} Commands.
 */
function useAddBlockCommands( { search } ) {
	const registry = useRegistry();
	const commands = useMemo( () => {
		const q = ( search || '' ).trim().toLowerCase();
		if ( ! q ) {
			return [];
		}
		const s = registry.select( blockEditorStore );
		const { root } = insertionPoint( s );
		return getBlockTypes()
			.filter(
				( t ) =>
					t.supports?.inserter !== false &&
					! t.parent &&
					s.canInsertBlockType( t.name, root ) &&
					[ t.title, t.name, ...( t.keywords || [] ) ]
						.join( ' ' )
						.toLowerCase()
						.includes( q )
			)
			.slice( 0, 12 )
			.map( ( t ) => ( {
				name: `pbsw/studio/add/${ t.name }`,
				label: sprintf(
					/* translators: %s: block name, e.g. Heading. */
					__( 'Add block: %s', 'page-builder-sandwich' ),
					t.title
				),
				searchLabel: `${ t.title } ${ ( t.keywords || [] ).join( ' ' ) } block`,
				// The palette's Icon clones this as a React ELEMENT, but a block's icon may be a
				// Dashicon name (pbs/testimonial's `format-quote`), a component or an element:
				// typing "quote" crashed the whole palette on the string (D28371). BlockIcon is
				// how the inserter renders every one of those shapes.
				icon: t.icon?.src ? (
					<BlockIcon icon={ t.icon } />
				) : (
					blockDefault
				),
				callback: ( { close } ) => {
					close();
					const sel = registry.select( blockEditorStore );
					const at = insertionPoint( sel );
					const target = sel.canInsertBlockType( t.name, at.root )
						? at
						: { root: undefined, index: sel.getBlockCount() };
					registry
						.dispatch( blockEditorStore )
						.insertBlock(
							createBlock( t.name ),
							target.index,
							target.root
						);
				},
			} ) );
	}, [ search, registry ] );
	return { commands, isLoading: false };
}

/**
 * "Open in Studio: <page>" commands from a search of pages and posts.
 *
 * @param {Object} props
 * @param {string} props.search Search text.
 * @return {Object} Commands.
 */
function useJumpCommands( { search } ) {
	const boot = useBoot();
	const q = ( search || '' ).trim();
	const { records, isLoading } = useSelect(
		( select ) => {
			if ( q.length < 2 ) {
				return { records: [], isLoading: false };
			}
			const s = select( coreStore );
			const query = {
				search: q,
				per_page: 8,
				status: 'publish,draft,pending,future,private',
				_fields: 'id,title,type',
			};
			const out = [];
			let loading = false;
			for ( const type of [ 'page', 'post' ] ) {
				out.push(
					...( s.getEntityRecords( 'postType', type, query ) || [] )
				);
				loading =
					loading ||
					! s.hasFinishedResolution( 'getEntityRecords', [
						'postType',
						type,
						query,
					] );
			}
			return { records: out, isLoading: loading };
		},
		[ q ]
	);
	const commands = useMemo(
		() =>
			records
				.filter(
					( r, i, all ) =>
						r.id !== boot.postId &&
						all.findIndex( ( x ) => x.id === r.id ) === i
				)
				.map( ( r ) => {
					const title =
						( r.title?.raw ?? r.title?.rendered ?? '' ) ||
						__( '(no title)', 'page-builder-sandwich' );
					return {
						name: `pbsw/studio/open/${ r.id }`,
						label: sprintf(
							/* translators: %s: page title. */
							__( 'Open in Studio: %s', 'page-builder-sandwich' ),
							title
						),
						searchLabel: title,
						icon: pageIcon,
						callback: ( { close } ) => {
							close();
							window.location.href = `${ boot.studioUrl }&post=${ r.id }`;
						},
					};
				} ),
		[ records, boot ]
	);
	return { commands, isLoading };
}

/**
 * Registers every Studio command.
 */
export default function StudioCommands() {
	const boot = useBoot();
	const ui = useUi();
	const { save } = useSave();
	const { undo, redo } = useDispatch( coreStore );
	const safeMode = useSafeModeToggle();
	const clip = useStudioClipboard();
	useSelect( ( select ) => select( blocksStore ).getBlockTypes(), [] );

	useCommand( {
		name: 'pbsw/studio/save',
		label: __( 'Save', 'page-builder-sandwich' ),
		icon: check,
		callback: ( { close } ) => {
			close();
			save();
		},
	} );
	useCommand( {
		name: 'pbsw/studio/undo',
		label: __( 'Undo', 'page-builder-sandwich' ),
		icon: undoIcon,
		callback: ( { close } ) => {
			close();
			undo();
		},
	} );
	useCommand( {
		name: 'pbsw/studio/redo',
		label: __( 'Redo', 'page-builder-sandwich' ),
		icon: redoIcon,
		callback: ( { close } ) => {
			close();
			redo();
		},
	} );
	useCommand( {
		name: 'pbsw/studio/toggle-layers',
		label: __( 'Show or hide layers', 'page-builder-sandwich' ),
		icon: listView,
		callback: ( { close } ) => {
			close();
			ui.toggleLeft( 'layers' );
		},
	} );
	useCommand( {
		name: 'pbsw/studio/toggle-inserter',
		label: __( 'Show or hide the block library', 'page-builder-sandwich' ),
		icon: plus,
		callback: ( { close } ) => {
			close();
			ui.toggleLeft( 'inserter' );
		},
	} );
	useCommand( {
		name: 'pbsw/studio/revisions',
		label: __( 'Show revisions', 'page-builder-sandwich' ),
		icon: backup,
		callback: ( { close } ) => {
			close();
			ui.setRevisionsOpen( true );
		},
	} );
	useCommand( {
		name: 'pbsw/studio/normal-editor',
		label: __( 'Open the normal editor', 'page-builder-sandwich' ),
		icon: wordpress,
		callback: ( { close } ) => {
			close();
			ui.goNormalEditor();
		},
	} );
	useCommand( {
		name: 'pbsw/studio/shortcuts',
		label: __( 'Keyboard shortcuts', 'page-builder-sandwich' ),
		icon: keyboard,
		callback: ( { close } ) => {
			close();
			ui.setHelpOpen( true );
		},
	} );
	useCommand( {
		name: 'pbsw/studio/device-desktop',
		label: __( 'Preview: desktop', 'page-builder-sandwich' ),
		icon: desktop,
		callback: ( { close } ) => {
			close();
			ui.setDevice( 'desktop' );
		},
	} );
	useCommand( {
		name: 'pbsw/studio/device-tablet',
		label: __( 'Preview: tablet', 'page-builder-sandwich' ),
		icon: tablet,
		callback: ( { close } ) => {
			close();
			ui.setDevice( 'tablet' );
		},
	} );
	useCommand( {
		name: 'pbsw/studio/device-mobile',
		label: __( 'Preview: mobile', 'page-builder-sandwich' ),
		icon: mobile,
		callback: ( { close } ) => {
			close();
			ui.setDevice( 'mobile' );
		},
	} );
	for ( const [ key, label, fn ] of [
		[
			'copy-style',
			__( 'Copy style of the selected block', 'page-builder-sandwich' ),
			clip.copyStyle,
		],
		[
			'paste-style',
			__(
				'Paste style onto the selected block',
				'page-builder-sandwich'
			),
			clip.pasteStyle,
		],
		[
			'copy-section',
			__( 'Copy the selected section', 'page-builder-sandwich' ),
			clip.copySection,
		],
		[
			'paste-section',
			__( 'Paste a copied section', 'page-builder-sandwich' ),
			clip.pasteSection,
		],
	] ) {
		// A fixed list, so the hook order never changes between renders.
		// eslint-disable-next-line react-hooks/rules-of-hooks
		useCommand( {
			name: `pbsw/studio/${ key }`,
			label,
			icon: copy,
			callback: ( { close } ) => {
				close();
				fn();
			},
		} );
	}
	useCommand( {
		name: 'pbsw/studio/safe-mode',
		label: boot.safeMode?.active
			? __( 'Turn off safe mode', 'page-builder-sandwich' )
			: __(
					'Turn on safe mode (other plugins off, for you, in Studio only)',
					'page-builder-sandwich'
				),
		icon: shield,
		disabled: ! safeMode,
		callback: ( { close } ) => {
			close();
			safeMode?.();
		},
	} );
	useCommandLoader( {
		name: 'pbsw/studio/add-block',
		hook: useAddBlockCommands,
	} );
	useCommandLoader( {
		name: 'pbsw/studio/open-page',
		hook: useJumpCommands,
	} );
	return null;
}
