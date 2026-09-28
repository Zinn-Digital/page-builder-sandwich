/**
 * Design prop `gridRows` (pbs-p4). Twin: includes/design/props/class-grid-rows.php.
 */
import { __ } from '@wordpress/i18n';

import { templateProp } from '../kinds';

export default templateProp( {
	key: 'gridRows',
	group: 'layout',
	target: 'layout',
	property: 'grid-template-rows',
	track: 'auto',
	label: __( 'Rows', 'page-builder-sandwich' ),
	control: 'grid-template',
} );
