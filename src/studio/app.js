/**
 * Sandwich Studio (pbs-f2): a full-screen editor over the SAME post the block editor edits.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import {
	store as coreStore,
	useEntityBlockEditor,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- the link control's suggestions; the block editor screen passes the same function.
	__experimentalFetchLinkSuggestions as fetchLinkSuggestions,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- rich previews in the link control, as the block editor screen does.
	__experimentalFetchUrlData as fetchUrlData,
} from '@wordpress/core-data';
import {
	BlockBreadcrumb,
	BlockCanvas,
	BlockEditorKeyboardShortcuts,
	BlockEditorProvider,
	BlockInspector,
	BlockList,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- the inserter panel; no stable export exists (WordPress 6.8-7.1).
	__experimentalLibrary as InserterLibrary,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- the layers panel; no stable export exists (WordPress 6.8-7.1).
	__experimentalListView as ListView,
} from '@wordpress/block-editor';
import {
	Button,
	Notice,
	Popover,
	Slot,
	SlotFillProvider,
	SnackbarList,
	Spinner,
} from '@wordpress/components';
import { CommandMenu } from '@wordpress/commands';
import { PluginArea } from '@wordpress/plugins';
import { store as noticesStore } from '@wordpress/notices';
import { uploadMedia } from '@wordpress/media-utils';
import { __, sprintf } from '@wordpress/i18n';
import { closeSmall } from '@wordpress/icons';
import { BootContext, UiContext, useBoot, useUi } from './context';
import Header from './components/header';
import Recovery from './components/recovery';
import Revisions from './components/revisions';
import StudioCommands from './components/commands';
import { SafeModeBanner } from './components/safe-mode';
import { ShortcutsHelp, StudioShortcuts } from './components/shortcuts';
import { BlockMenuItems, PasteDialog } from './components/style-transfer-panel';
import SavePatternMenu from './components/save-pattern';
import { useAutosave, useSave } from './hooks/use-save';
import { getSidebars } from './lib/sidebars';
import { useSnapshot } from './hooks/use-snapshot';
import { deviceWidth, toBreakpoint, toDevice } from './lib/devices';

/** The design system's editor store (src/design/store.js), registered by build/design.js. */
const DESIGN_STORE = 'pbsw/design';

/**
 * Snackbars and errors.
 */
function Notices() {
	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices(),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );
	return (
		<SnackbarList
			className="pbsw-studio-snackbars"
			notices={ notices.filter( ( n ) => n.type === 'snackbar' ) }
			onRemove={ removeNotice }
		/>
	);
}

/**
 * Warn before leaving with unsaved changes (the snapshot still keeps them).
 */
function useLeaveWarning() {
	const { isDirty } = useSave();
	useEffect( () => {
		if ( ! isDirty ) {
			return undefined;
		}
		const warn = ( e ) => {
			e.preventDefault();
			e.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ isDirty ] );
}

/**
 * The editor proper (inside the block editor provider).
 */
function Editor() {
	const boot = useBoot();
	const ui = useUi();
	const openSidebar = ui.sidebars.find( ( x ) => x.name === ui.sidebar );
	const [ resolved, setResolved ] = useState( false );
	const lastAutosave = useAutosave( boot.autosaveInterval );
	useSnapshot( resolved, boot.snapshotInterval );
	useLeaveWarning();

	const width = deviceWidth( ui.device );
	const layoutClass = boot.settings?.supportsLayout
		? 'is-layout-constrained has-global-padding'
		: 'is-layout-flow';

	return (
		<div className="pbsw-studio">
			<Header lastAutosave={ lastAutosave } />
			<SafeModeBanner />
			{ boot.lockedBy && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="pbsw-studio-lock"
				>
					{ sprintf(
						/* translators: %s: user name. */
						__(
							'%s is also editing this page. Saving may overwrite their changes.',
							'page-builder-sandwich'
						),
						boot.lockedBy
					) }
				</Notice>
			) }
			<div className="pbsw-studio-body">
				{ ui.left && (
					<div
						role="region"
						className="pbsw-studio-left"
						aria-label={
							ui.left === 'inserter'
								? __( 'Block library', 'page-builder-sandwich' )
								: __( 'Layers', 'page-builder-sandwich' )
						}
					>
						<div className="pbsw-studio-panel__head">
							<h2>
								{ ui.left === 'inserter'
									? __(
											'Block library',
											'page-builder-sandwich'
										)
									: __( 'Layers', 'page-builder-sandwich' ) }
							</h2>
							<Button
								icon={ closeSmall }
								label={ __(
									'Close panel',
									'page-builder-sandwich'
								) }
								onClick={ () => ui.toggleLeft( ui.left ) }
								size="small"
							/>
						</div>
						<div className="pbsw-studio-left__content">
							{ ui.left === 'inserter' ? (
								<InserterLibrary
									showInserterHelpPanel={ false }
									shouldFocusBlock
								/>
							) : (
								<ListView />
							) }
						</div>
					</div>
				) }
				<div
					role="region"
					className="pbsw-studio-canvas"
					aria-label={ __( 'Page canvas', 'page-builder-sandwich' ) }
				>
					<div
						className={ `pbsw-studio-device is-${ ui.device }` }
						style={
							width ? { inlineSize: `${ width }px` } : undefined
						}
					>
						<BlockCanvas
							height="100%"
							styles={ boot.settings?.styles }
						>
							<BlockList
								className={ `pbsw-studio-canvas-root ${ layoutClass }` }
								layout={
									boot.settings?.supportsLayout
										? { type: 'constrained' }
										: undefined
								}
							/>
						</BlockCanvas>
					</div>
				</div>
				<div
					role="region"
					className="pbsw-studio-right"
					aria-label={
						ui.revisionsOpen
							? __( 'Revisions', 'page-builder-sandwich' )
							: openSidebar?.title ||
								__( 'Block settings', 'page-builder-sandwich' )
					}
				>
					{ /* A `pbs-studio` plugin panel (Site design, L09) takes the place of the inspector while open;
					otherwise revisions, a registered Studio sidebar (L12), or the block inspector. */ }
					<Slot name="PbswStudioSidebar">
						{ ( fills ) => {
							if ( fills.length ) {
								return fills;
							}
							if ( ui.revisionsOpen ) {
								return (
									<Revisions
										onClose={ () =>
											ui.setRevisionsOpen( false )
										}
									/>
								);
							}
							if ( openSidebar ) {
								return (
									<openSidebar.Component
										onClose={ () => ui.setSidebar( null ) }
									/>
								);
							}
							return <BlockInspector />;
						} }
					</Slot>
				</div>
			</div>
			<div className="pbsw-studio-footer">
				<BlockBreadcrumb rootLabelText={ boot.typeLabel } />
			</div>
			<Recovery onResolved={ () => setResolved( true ) } />
			<StudioShortcuts />
			<StudioCommands />
			<CommandMenu />
			<BlockMenuItems />
			<SavePatternMenu />
			<PasteDialog />
			<ShortcutsHelp />
			<Notices />
			<PluginArea scope="pbs-studio" />
			<Popover.Slot />
		</div>
	);
}

/**
 * Loads the post, then mounts the editor.
 *
 * @param {Object} props
 * @param {Object} props.boot Boot data.
 */
export default function App( { boot } ) {
	const registry = useRegistry();
	const { postType, postId } = boot;
	const loaded = useSelect(
		( select ) =>
			!! select( coreStore ).getEntityRecord(
				'postType',
				postType,
				postId
			),
		[ postType, postId ]
	);
	// The selection is part of the entity's edits: passing it back lets undo/redo restore the
	// caret instead of the block editor re-deriving it (which records a new edit and wipes redo).
	const selection = useSelect(
		( select ) =>
			select( coreStore ).getEditedEntityRecord(
				'postType',
				postType,
				postId
			)?.selection,
		[ postType, postId ]
	);
	const [ blocks, onInput, onChange ] = useEntityBlockEditor(
		'postType',
		postType,
		{ id: postId }
	);

	const [ left, setLeft ] = useState( null );
	// The preview width IS the design breakpoint being edited (pbs-d2): one state for the canvas
	// and the Style tab. Local state only if the design bundle is missing.
	const [ localDevice, setLocalDevice ] = useState( 'desktop' );
	const hasDesign = !! window.pbswDesignStore;
	const designBp = useSelect(
		( select ) =>
			hasDesign ? select( DESIGN_STORE ).getBreakpoint() : null,
		[ hasDesign ]
	);
	const dispatch = useDispatch();
	const device = hasDesign ? toDevice( designBp ) : localDevice;
	const setDevice = useCallback(
		( next ) =>
			hasDesign
				? dispatch( DESIGN_STORE ).setBreakpoint( toBreakpoint( next ) )
				: setLocalDevice( next ),
		[ hasDesign, dispatch ]
	);
	const [ revisionsOpen, setRevisionsOpen ] = useState( false );
	const [ sidebar, setSidebar ] = useState( null );
	const sidebars = useMemo( () => getSidebars(), [] );
	const [ helpOpen, setHelpOpen ] = useState( false );
	const [ pasteIntent, setPasteIntent ] = useState( null );

	const goNormalEditor = useCallback( async () => {
		const s = registry.select( coreStore );
		if ( s.hasEditsForEntityRecord( 'postType', postType, postId ) ) {
			await registry
				.dispatch( coreStore )
				.saveEditedEntityRecord( 'postType', postType, postId );
			if ( ! s.getLastEntitySaveError( 'postType', postType, postId ) ) {
				boot.clearSnapshot();
			}
		}
		window.location.href = boot.editUrl;
	}, [ registry, postType, postId, boot ] );

	const ui = useMemo(
		() => ( {
			left,
			toggleLeft: ( panel ) =>
				setLeft( ( v ) => ( v === panel ? null : panel ) ),
			device,
			setDevice,
			revisionsOpen,
			setRevisionsOpen: ( v ) => {
				setSidebar( null );
				setRevisionsOpen( v );
			},
			sidebars,
			sidebar,
			setSidebar: ( name ) => {
				setRevisionsOpen( false );
				setSidebar( name );
			},
			helpOpen,
			setHelpOpen,
			pasteIntent,
			setPasteIntent,
			goNormalEditor,
		} ),
		[
			left,
			device,
			setDevice,
			revisionsOpen,
			sidebars,
			sidebar,
			helpOpen,
			pasteIntent,
			goNormalEditor,
		]
	);

	const settings = useMemo( () => {
		const s = boot.settings || {};
		return {
			...s,
			__experimentalBlockPatterns:
				s.__experimentalAdditionalBlockPatterns,
			__experimentalBlockPatternCategories:
				s.__experimentalAdditionalBlockPatternCategories,
			__experimentalFetchLinkSuggestions: ( search, options ) =>
				fetchLinkSuggestions( search, options, s ),
			__experimentalFetchRichUrlData: fetchUrlData,
			mediaUpload: ( { onError, ...rest } ) =>
				uploadMedia( {
					...rest,
					additionalData: { post: postId },
					allowedTypes: rest.allowedTypes,
					maxUploadFileSize: s.maxUploadFileSize,
					wpAllowedMimeTypes: s.allowedMimeTypes,
					onError: ( { message } ) => onError?.( message ),
				} ),
		};
	}, [ boot.settings, postId ] );

	if ( ! loaded ) {
		return (
			<div className="pbsw-studio-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<BootContext.Provider value={ boot }>
			<UiContext.Provider value={ ui }>
				<SlotFillProvider>
					<BlockEditorProvider
						value={ blocks }
						onInput={ onInput }
						onChange={ onChange }
						selection={ selection }
						settings={ settings }
						useSubRegistry={ false }
					>
						<BlockEditorKeyboardShortcuts.Register />
						<Editor />
					</BlockEditorProvider>
				</SlotFillProvider>
			</UiContext.Provider>
		</BootContext.Provider>
	);
}
