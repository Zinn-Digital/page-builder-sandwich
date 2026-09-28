import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import App from './App';
import './admin.scss';

// DOM ready, not at parse time: modules add panels through the `pbs.admin.panels` filter from
// their own footer scripts (Admin::enqueue's `pbsw_admin_enqueue` action), which run after this one.
domReady( () => {
	const root = document.getElementById( 'pbs-admin-root' );
	if ( root && window.pbsAdmin ) {
		createRoot( root ).render( <App data={ window.pbsAdmin } /> );
	}
} );
