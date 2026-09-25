import { createRoot } from '@wordpress/element';

import App from './App';
import './admin.scss';

const root = document.getElementById( 'pbs-admin-root' );
if ( root && window.pbsAdmin ) {
	createRoot( root ).render( <App data={ window.pbsAdmin } /> );
}
