/**
 * Studio's top bar.
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as commandsStore } from '@wordpress/commands';
import { Button, Slot } from '@wordpress/components';
import { __, isRTL, sprintf } from '@wordpress/i18n';
import { dateI18n, getSettings } from '@wordpress/date';
import {
	backup,
	chevronLeft,
	chevronRight,
	desktop,
	external,
	keyboard,
	listView,
	mobile,
	plus,
	redo as redoIcon,
	search,
	tablet,
	undo as undoIcon,
} from '@wordpress/icons';
import { displayShortcut } from '@wordpress/keycodes';
import { useBoot, useUi } from '../context';
import { useSave } from '../hooks/use-save';
import { SafeModeButton } from './safe-mode';
import { titleOf } from '../lib/entity';
import { breakpoints } from '../lib/devices';

const STATUS_LABELS = {
	publish: __( 'Published', 'page-builder-sandwich' ),
	future: __( 'Scheduled', 'page-builder-sandwich' ),
	pending: __( 'Pending review', 'page-builder-sandwich' ),
	private: __( 'Private', 'page-builder-sandwich' ),
	draft: __( 'Draft', 'page-builder-sandwich' ),
	'auto-draft': __( 'Draft', 'page-builder-sandwich' ),
};

/**
 * @param {Object}      props
 * @param {number|null} props.lastAutosave Time of the last autosave.
 */
export default function Header( { lastAutosave } ) {
	const boot = useBoot();
	const ui = useUi();
	const { postType, postId } = boot;
	const { save, publish, isDirty, isSaving, isAutosaving, status } =
		useSave();
	const { editEntityRecord, undo, redo } = useDispatch( coreStore );
	const { open: openCommands } = useDispatch( commandsStore );
	const { title, hasUndo, hasRedo } = useSelect(
		( select ) => {
			const s = select( coreStore );
			return {
				title: titleOf(
					s.getEditedEntityRecord( 'postType', postType, postId )
				),
				hasUndo: s.hasUndo(),
				hasRedo: s.hasRedo(),
			};
		},
		[ postType, postId ]
	);
	const published =
		status === 'publish' || status === 'future' || status === 'private';

	let saveState = __( 'All changes saved', 'page-builder-sandwich' );
	if ( isSaving && ! isAutosaving ) {
		saveState = __( 'Saving…', 'page-builder-sandwich' );
	} else if ( isAutosaving ) {
		saveState = __( 'Autosaving…', 'page-builder-sandwich' );
	} else if ( isDirty ) {
		saveState = lastAutosave
			? `${ __( 'Unsaved changes', 'page-builder-sandwich' ) } · ${ __( 'autosaved', 'page-builder-sandwich' ) } ${ dateI18n( getSettings().formats.time, new Date( lastAutosave ) ) }`
			: __( 'Unsaved changes', 'page-builder-sandwich' );
	}

	// Desktop, Tablet, Mobile, then every custom breakpoint (pbs-d3), each resizing the canvas.
	const devices = [
		[
			'desktop',
			desktop,
			__( 'Desktop preview', 'page-builder-sandwich' ),
		],
		...breakpoints().map( ( bp ) => {
			if ( bp.id === 'tablet' ) {
				return [
					'tablet',
					tablet,
					__( 'Tablet preview', 'page-builder-sandwich' ),
				];
			}
			if ( bp.id === 'mobile' ) {
				return [
					'mobile',
					mobile,
					__( 'Mobile preview', 'page-builder-sandwich' ),
				];
			}
			return [
				bp.id,
				null,
				sprintf(
					/* translators: 1: a custom screen size's name, 2: its width in pixels. */
					__( '%1$s preview (%2$d px)', 'page-builder-sandwich' ),
					bp.label,
					bp.min ?? bp.max
				),
				bp.label,
			];
		} ),
	];

	return (
		<div
			className="pbsw-studio-header"
			role="region"
			aria-label={ __( 'Studio toolbar', 'page-builder-sandwich' ) }
		>
			<div className="pbsw-studio-header__start">
				<Button
					className="pbsw-studio-header__back"
					icon={ isRTL() ? chevronRight : chevronLeft }
					onClick={ ui.goNormalEditor }
					size="compact"
				>
					{ __(
						'Back to the normal editor',
						'page-builder-sandwich'
					) }
				</Button>
				<Button
					icon={ plus }
					label={ __( 'Block library', 'page-builder-sandwich' ) }
					isPressed={ ui.left === 'inserter' }
					onClick={ () => ui.toggleLeft( 'inserter' ) }
					size="compact"
					variant={ ui.left === 'inserter' ? 'primary' : undefined }
				/>
				<Button
					icon={ listView }
					label={ __( 'Layers', 'page-builder-sandwich' ) }
					shortcut={ displayShortcut.access( 'o' ) }
					isPressed={ ui.left === 'layers' }
					onClick={ () => ui.toggleLeft( 'layers' ) }
					size="compact"
				/>
				<Button
					icon={ undoIcon }
					label={ __( 'Undo', 'page-builder-sandwich' ) }
					shortcut={ displayShortcut.primary( 'z' ) }
					disabled={ ! hasUndo }
					accessibleWhenDisabled
					onClick={ () => undo() }
					size="compact"
				/>
				<Button
					icon={ redoIcon }
					label={ __( 'Redo', 'page-builder-sandwich' ) }
					shortcut={ displayShortcut.primaryShift( 'z' ) }
					disabled={ ! hasRedo }
					accessibleWhenDisabled
					onClick={ () => redo() }
					size="compact"
				/>
			</div>
			<div className="pbsw-studio-header__center">
				<input
					className="pbsw-studio-header__title"
					type="text"
					value={ title }
					placeholder={ boot.settings?.titlePlaceholder || '' }
					aria-label={ __( 'Page title', 'page-builder-sandwich' ) }
					onChange={ ( e ) =>
						editEntityRecord( 'postType', postType, postId, {
							title: e.target.value,
						} )
					}
				/>
				<div
					className="pbsw-studio-header__devices"
					role="group"
					aria-label={ __(
						'Preview width',
						'page-builder-sandwich'
					) }
				>
					{ devices.map( ( [ key, icon, label, text ] ) => (
						<Button
							key={ key }
							icon={ icon || undefined }
							label={ label }
							isPressed={ ui.device === key }
							onClick={ () => ui.setDevice( key ) }
							size="compact"
						>
							{ icon ? undefined : text }
						</Button>
					) ) }
				</div>
			</div>
			<div className="pbsw-studio-header__end">
				<span
					className="pbsw-studio-header__state"
					role="status"
					aria-live="polite"
				>
					{ saveState }
				</span>
				<Button
					icon={ search }
					label={ __( 'Command palette', 'page-builder-sandwich' ) }
					shortcut={ displayShortcut.primary( 'k' ) }
					onClick={ () => openCommands() }
					size="compact"
				/>
				{ ui.sidebars.map( ( panel ) => (
					<Button
						key={ panel.name }
						icon={ panel.icon }
						label={ panel.title }
						isPressed={ ui.sidebar === panel.name }
						onClick={ () =>
							ui.setSidebar(
								ui.sidebar === panel.name ? null : panel.name
							)
						}
						size="compact"
					/>
				) ) }
				{ boot.supportsRevisions && (
					<Button
						icon={ backup }
						label={ __( 'Revisions', 'page-builder-sandwich' ) }
						isPressed={ ui.revisionsOpen }
						onClick={ () => ui.setRevisionsOpen( ( v ) => ! v ) }
						size="compact"
					/>
				) }
				<Button
					icon={ keyboard }
					label={ __(
						'Keyboard shortcuts',
						'page-builder-sandwich'
					) }
					shortcut={ displayShortcut.access( 'h' ) }
					onClick={ () => ui.setHelpOpen( true ) }
					size="compact"
				/>
				{ /* Plugins scoped `pbs-studio` add tools here (the Site design panel, L09). */ }
				<Slot name="PbswStudioHeaderTools" />
				<SafeModeButton />
				{ boot.viewUrl && (
					<Button
						icon={ external }
						label={ __(
							'View page (opens in a new tab)',
							'page-builder-sandwich'
						) }
						href={ boot.viewUrl }
						target="_blank"
						rel="noopener noreferrer"
						size="compact"
					/>
				) }
				<span className="pbsw-studio-header__status">
					{ STATUS_LABELS[ status ] || status }
				</span>
				<Button
					variant={
						published || ! boot.canPublish ? 'primary' : 'secondary'
					}
					onClick={ () => save() }
					isBusy={ isSaving && ! isAutosaving }
					disabled={ isSaving }
					accessibleWhenDisabled
					shortcut={ displayShortcut.primary( 's' ) }
					size="compact"
				>
					{ published
						? __( 'Update', 'page-builder-sandwich' )
						: __( 'Save draft', 'page-builder-sandwich' ) }
				</Button>
				{ ! published && boot.canPublish && (
					<Button
						variant="primary"
						onClick={ () => publish() }
						disabled={ isSaving }
						accessibleWhenDisabled
						size="compact"
					>
						{ __( 'Publish', 'page-builder-sandwich' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
