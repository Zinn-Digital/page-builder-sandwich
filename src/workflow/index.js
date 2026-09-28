/**
 * Workflow panels on the plugin's admin screen, free part: import and export (pbs-g5).
 */
import { addFilter } from '@wordpress/hooks';

import TransferPanel from './TransferPanel';

addFilter( 'pbs.admin.panels', 'pbs/workflow/transfer', ( panels ) => [
	...panels,
	{ name: 'transfer', Component: TransferPanel },
] );
