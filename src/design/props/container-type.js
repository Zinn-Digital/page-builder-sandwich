/**
 * Design prop `containerType` (DESIGN1): makes an element a query container for `@container`
 * at-rule presets. Twin: includes/design/props/class-container-type.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'containerType',
	group: 'advanced',
	property: 'container-type',
	choices: [ 'normal', 'inline-size', 'size' ],
	label: __( 'Query container', 'page-builder-sandwich' ),
	control: 'select',
	options: () => ( {
		normal: __( 'No', 'page-builder-sandwich' ),
		'inline-size': __( 'By its width', 'page-builder-sandwich' ),
		size: __( 'By its width and height', 'page-builder-sandwich' ),
	} ),
} );
