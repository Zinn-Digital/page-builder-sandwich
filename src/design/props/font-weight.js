/**
 * Design prop `fontWeight` (pbs-p4). Twin: includes/design/props/class-font-weight.php.
 */
import { __ } from '@wordpress/i18n';

export default {
	key: 'fontWeight',
	group: 'typography',
	target: '',
	blocks: [],
	label: __( 'Weight', 'page-builder-sandwich' ),
	control: 'select',
	options: () => ( {
		100: __( 'Thin (100)', 'page-builder-sandwich' ),
		200: __( 'Extra light (200)', 'page-builder-sandwich' ),
		300: __( 'Light (300)', 'page-builder-sandwich' ),
		400: __( 'Regular (400)', 'page-builder-sandwich' ),
		500: __( 'Medium (500)', 'page-builder-sandwich' ),
		600: __( 'Semi bold (600)', 'page-builder-sandwich' ),
		700: __( 'Bold (700)', 'page-builder-sandwich' ),
		800: __( 'Extra bold (800)', 'page-builder-sandwich' ),
		900: __( 'Black (900)', 'page-builder-sandwich' ),
	} ),
	sanitize( value ) {
		if ( Number.isInteger( value ) ) {
			value = String( value );
		}
		if ( typeof value !== 'string' ) {
			return null;
		}
		value = value.trim().toLowerCase();
		return /^(?:[1-9]00|normal|bold)$/.test( value ) ? value : null;
	},
	toCss: ( value ) => ( { 'font-weight': String( value ) } ),
};
