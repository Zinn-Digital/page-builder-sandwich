import { applyFilters } from '@wordpress/hooks';

/**
 * Extra right-hand panels for Sandwich Studio, registered by other modules (client review, the AI
 * assistant) and by add-ons through the `pbs.studio.sidebars` filter:
 *
 *   addFilter( 'pbs.studio.sidebars', 'my/plugin', ( list ) => [
 *     ...list,
 *     { name: 'my-panel', title: __( 'My panel' ), icon: myIcon, Component: MyPanel },
 *   ] );
 *
 * Component receives `{ onClose }`. The list is read once, when Studio mounts: every script that
 * registers a panel is enqueued on `enqueue_block_editor_assets`, which Studio fires before it
 * renders.
 *
 * @return {Array<Object>} Panels: { name, title, icon, Component }.
 */
export function getSidebars() {
	const list = applyFilters( 'pbs.studio.sidebars', [] );
	return Array.isArray( list )
		? list.filter(
				( s ) =>
					s &&
					'string' === typeof s.name &&
					'function' === typeof s.Component
			)
		: [];
}
