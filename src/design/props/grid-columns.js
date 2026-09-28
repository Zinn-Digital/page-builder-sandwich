/**
 * Design prop `gridColumns` (pbs-p4). Twin: includes/design/props/class-grid-columns.php.
 */
import { __ } from '@wordpress/i18n';

import { templateProp } from '../kinds';

export default templateProp( {
	key: 'gridColumns',
	group: 'layout',
	target: 'layout',
	property: 'grid-template-columns',
	track: 'minmax(0,1fr)',
	label: __( 'Columns', 'page-builder-sandwich' ),
	control: 'grid-template',
} );
