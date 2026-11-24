import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { applyFilters } from '@wordpress/hooks';
import { lazy, Suspense } from '@wordpress/element';
import { Spinner } from '@wordpress/components';

import { mountAdminKit } from '../admin-kit';
import App from './App';
import { withAddedTabs } from './tabs';
import './admin.scss';

// Templates & kits (pbs-c1, pbs-c2): its own chunk, fetched only when the route opens (§2.22).
const Templates = lazy( () => import( '../admin-templates/Templates' ) );

// DOM ready, not at parse time: modules add panels through the `pbsw.admin.panels` filter from
// their own footer scripts (Admin::enqueue's `pbsw_admin_enqueue` action), which run after this one.
domReady( () => {
	const root = document.getElementById( 'pbs-admin-root' );
	const data = window.pbswAdmin;
	if ( ! root || ! data ) {
		return;
	}
	// The admin kit is the shell (overview, plans, add-ons, help, the setup wizard); the plugin's
	// own settings screen is one route inside it and is unchanged. The wizard's step IS that
	// screen, so a choice made during setup is the same setting as on its own route.
	mountAdminKit(
		root,
		'page-builder-sandwich',
		/**
		 * Filters the routes of the plugin's admin screen: premium modules add theirs here
		 * (the Pro Marketing route, pbs-m2 / pbs-r18 / pbs-r24).
		 *
		 * @param {Array<Object>} routes Routes: {id, label, render}.
		 */
		// Tabs added from PHP (`pbsw_admin_tabs`: add-ons, and the site's own tabs in Pro) go
		// after every route a module added, and never take a route's id.
		withAddedTabs(
			applyFilters( 'pbsw.admin.routes', [
				{
					id: 'settings',
					label: __( 'Settings', 'page-builder-sandwich' ),
					render: () => <App data={ data } />,
				},
				{
					id: 'templates',
					label: __( 'Templates & kits', 'page-builder-sandwich' ),
					render: () => (
						<Suspense fallback={ <Spinner /> }>
							<Templates data={ data } />
						</Suspense>
					),
				},
			] ),
			data.tabs
		),
		[
			{
				id: 'settings',
				title: __( 'Settings', 'page-builder-sandwich' ),
				render: () => <App data={ data } />,
			},
		]
	);
} );
