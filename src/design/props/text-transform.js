/**
 * Design prop `textTransform` (pbs-p4). Twin: includes/design/props/class-text-transform.php.
 */
import { __ } from '@wordpress/i18n';

import { choiceProp } from '../kinds';

export default choiceProp( {
	key: 'textTransform',
	group: 'typography',
	property: 'text-transform',
	choices: [ 'none', 'uppercase', 'lowercase', 'capitalize' ],
	label: __( 'Letter case', 'page-builder-sandwich' ),
	control: 'buttons',
	options: () => ( {
		none: __( 'None', 'page-builder-sandwich' ),
		uppercase: __( 'Uppercase', 'page-builder-sandwich' ),
		lowercase: __( 'Lowercase', 'page-builder-sandwich' ),
		capitalize: __( 'Capitalise', 'page-builder-sandwich' ),
	} ),
} );
