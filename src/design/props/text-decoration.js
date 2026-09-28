/**
 * Design prop `textDecoration` (pbs-p4). Twin: includes/design/props/class-text-decoration.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'textDecoration',
	group: 'typography',
	property: 'text-decoration-line',
	choices: [ 'none', 'underline', 'overline', 'line-through' ],
	label: __( 'Decoration', 'page-builder-sandwich' ),
	control: 'buttons',
	options: () => ( {
		none: __( 'None', 'page-builder-sandwich' ),
		underline: __( 'Underline', 'page-builder-sandwich' ),
		overline: __( 'Overline', 'page-builder-sandwich' ),
		'line-through': __( 'Strikethrough', 'page-builder-sandwich' ),
	} ),
} );
