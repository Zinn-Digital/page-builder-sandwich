/**
 * The breakpoints as the editor shows them (switcher, Studio, the layout blocks' settings).
 * Kept apart from the panel so the blocks bundle can use it without the whole prop registry.
 */
import { __ } from '@wordpress/i18n';
import { desktop, mobile, tablet } from '@wordpress/icons';

import { config } from '../store';

/**
 * Every breakpoint for the switcher: desktop, then the media list in output order.
 *
 * @return {Array<Object>} Breakpoints with icon and label.
 */
export function switcherItems() {
	const icons = { tablet, mobile };
	const names = {
		tablet: __( 'Tablet', 'page-builder-sandwich' ),
		mobile: __( 'Mobile', 'page-builder-sandwich' ),
	};
	return [
		{
			id: 'base',
			label: __( 'Desktop', 'page-builder-sandwich' ),
			icon: desktop,
		},
		...config().breakpoints.map( ( bp ) => ( {
			...bp,
			icon: icons[ bp.id ],
			label: names[ bp.id ] ?? bp.label,
		} ) ),
	];
}
