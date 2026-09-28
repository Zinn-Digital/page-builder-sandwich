/**
 * Design prop `colSpan` (pbs-p4). Twin: includes/design/props/class-col-span.php.
 */
import { __ } from '@wordpress/i18n';

import { spanProp } from '../kinds';

export default spanProp( {
	key: 'colSpan',
	property: 'grid-column',
	label: __( 'Column span', 'page-builder-sandwich' ),
	control: 'span',
} );
