/**
 * Safe mode (pbs-r17): switch every other plugin off for THIS administrator's Studio only. The
 * server half is includes/core/class-safe-mode.php + the must-use file it installs.
 */
import { useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';
import { useBoot } from '../context';
import { useSave } from '../hooks/use-save';

/**
 * @return {Function|null} Toggle, or null when this user may not use safe mode.
 */
export function useSafeModeToggle() {
	const boot = useBoot();
	const { isDirty, save } = useSave();
	const { createErrorNotice } = useDispatch( noticesStore );
	const toggle = useCallback( async () => {
		// Reloading must never lose work: save first (the snapshot would also catch it).
		if ( isDirty && ! ( await save() ) ) {
			return;
		}
		try {
			await apiFetch( {
				path: '/pbsw/v1/studio/safe-mode',
				method: 'POST',
				data: { enabled: ! boot.safeMode.active },
			} );
		} catch ( e ) {
			createErrorNotice(
				e?.message ||
					__(
						'Safe mode could not be changed.',
						'page-builder-sandwich'
					),
				{ type: 'snackbar' }
			);
			return;
		}
		window.location.reload();
	}, [ isDirty, save, boot, createErrorNotice ] );
	return boot.safeMode?.allowed ? toggle : null;
}

/**
 * The banner shown for as long as safe mode is on.
 */
export function SafeModeBanner() {
	const boot = useBoot();
	const toggle = useSafeModeToggle();
	if ( ! boot.safeMode?.active ) {
		return null;
	}
	const names = boot.safeMode.disabled || [];
	return (
		<Notice
			status="warning"
			isDismissible={ false }
			className="pbsw-studio-safe-mode"
			actions={
				toggle
					? [
							{
								label: __(
									'Turn off safe mode',
									'page-builder-sandwich'
								),
								onClick: toggle,
								variant: 'secondary',
							},
						]
					: []
			}
		>
			<strong>
				{ __( 'Safe mode is on.', 'page-builder-sandwich' ) }
			</strong>{ ' ' }
			{ sprintf(
				/* translators: %d: number of plugins. */
				_n(
					'%d other plugin is switched off, for you and in Sandwich Studio only. The live site and other users are not affected.',
					'%d other plugins are switched off, for you and in Sandwich Studio only. The live site and other users are not affected.',
					names.length,
					'page-builder-sandwich'
				),
				names.length
			) }
			{ names.length > 0 && (
				<span className="pbsw-studio-safe-mode__list">
					{ ' ' }
					{ names.join( ', ' ) }
				</span>
			) }
		</Notice>
	);
}

/**
 * Header button.
 */
export function SafeModeButton() {
	const boot = useBoot();
	const toggle = useSafeModeToggle();
	if ( ! toggle ) {
		return null;
	}
	return (
		<Button
			size="compact"
			variant={ boot.safeMode.active ? 'primary' : 'tertiary' }
			isDestructive={ boot.safeMode.active }
			onClick={ toggle }
			aria-pressed={ !! boot.safeMode.active }
			label={ __(
				'Safe mode: switch other plugins off in Studio, for you only',
				'page-builder-sandwich'
			) }
			showTooltip
		>
			{ __( 'Safe mode', 'page-builder-sandwich' ) }
		</Button>
	);
}
