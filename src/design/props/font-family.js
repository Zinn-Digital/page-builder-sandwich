/**
 * Design prop `fontFamily` (pbs-p4). Twin: includes/design/props/class-font-family.php.
 */
import { __ } from '@wordpress/i18n';

import * as V from '../values';

const count = ( s, ch ) => s.split( ch ).length - 1;

export default {
	key: 'fontFamily',
	group: 'typography',
	target: '',
	blocks: [],
	label: __( 'Font', 'page-builder-sandwich' ),
	control: 'font-family',
	sanitize( value ) {
		if ( value && typeof value === 'object' ) {
			return V.token( value, [ 'font', 'var' ] );
		}
		if ( typeof value !== 'string' ) {
			return null;
		}
		value = value.replace( /\s+/g, ' ' ).trim();
		if (
			value === '' ||
			value.length > 200 ||
			! /^[A-Za-z0-9 ,'"-]+$/.test( value )
		) {
			return null;
		}
		if ( count( value, '"' ) % 2 || count( value, "'" ) % 2 ) {
			return null;
		}
		return value;
	},
	toCss: ( value, prefix ) => ( { 'font-family': V.css( value, prefix ) } ),
};
