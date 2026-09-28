import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';

import { mountAdminKit } from '../admin-kit';
import App from './App';
import './admin.scss';

// DOM ready, not at parse time: modules add panels through the `pbs.admin.panels` filter from
// their own footer scripts (Admin::enqueue's `pbsw_admin_enqueue` action), which run after this one.
domReady( () => {
	const root = document.getElementById( 'pbs-admin-root' );
	const data = window.pbsAdmin;
	if ( ! root || ! data ) {
		return;
	}
	// The admin kit is the shell (overview, plans, add-ons, help, the setup wizard); the plugin's
	// own settings screen is one route inside it and is unchanged. The wizard's step IS that
	// screen, so a choice made during setup is the same setting as on its own route.
	mountAdminKit(
		root,
		'page-builder-sandwich',
		[
			{
				id: 'settings',
				label: __( 'Settings', 'page-builder-sandwich' ),
				render: () => <App data={ data } />,
			},
		],
		[
			{
				id: 'settings',
				title: __( 'Settings', 'page-builder-sandwich' ),
				render: () => <App data={ data } />,
			},
		]
	);
} );
