/**
 * Design prop `rowSpan` (pbs-p4). Twin: includes/design/props/class-row-span.php.
 */
import { __ } from '@wordpress/i18n';

import { spanProp } from '../kinds';

export default spanProp( {
	key: 'rowSpan',
	property: 'grid-row',
	label: __( 'Row span', 'page-builder-sandwich' ),
	control: 'span',
} );
