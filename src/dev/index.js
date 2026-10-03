/**
 * The developer platform (P16): style controls registered in PHP with
 * pbsw_register_style_control() become design props in the editor, built with the same kinds
 * (choice, length, colour) as the builder's own, so an add-on writes no JavaScript for them.
 */
import { choiceProp, lengthProp, colorProp } from '../design/kinds';

const KINDS = { choice: choiceProp, length: lengthProp, color: colorProp };

/**
 * One registered control as a design prop.
 *
 * @param {Object} def Definition printed by the server.
 * @return {Object|null} Prop.
 */
export function toProp( def ) {
	const make = KINDS[ def.kind ];
	if ( ! make || ! def.key || ! def.property ) {
		return null;
	}
	const options =
		def.options && Object.keys( def.options ).length ? def.options : null;
	return make( {
		key: def.key,
		group: def.group || 'advanced',
		property: def.property,
		label: def.label,
		blocks: def.blocks || [],
		...( def.kind === 'choice'
			? {
					choices: def.choices,
					control: 'select',
					options: () =>
						options ||
						Object.fromEntries(
							def.choices.map( ( c ) => [ c, c ] )
						),
				}
			: {} ),
	} );
}

// Meet the design registry through its queue (it may load before or after this bundle).
window.pbswDesignProps = window.pbswDesignProps || [];
window.pbswDesignProps.push(
	( window.pbswStyleControls || [] ).map( toProp ).filter( Boolean )
);
