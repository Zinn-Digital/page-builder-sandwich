/**
 * Every block type carries the `pbs` attribute (pbs-p4). Blocks registered on the server get it
 * from the server definition (Design\Styles::add_attribute); this filter covers blocks registered
 * only in JS. Loaded in the head, before footer scripts register their blocks.
 */
import { addFilter } from '@wordpress/hooks';

/**
 * Add the attribute to one block type's settings.
 *
 * @param {Object} settings Block settings.
 * @return {Object} Settings.
 */
export function withPbsAttribute( settings ) {
	if ( ! settings || settings.attributes?.pbs ) {
		return settings;
	}
	return {
		...settings,
		attributes: { ...settings.attributes, pbs: { type: 'object' } },
	};
}

addFilter(
	'blocks.registerBlockType',
	'pbsw/design/attribute',
	withPbsAttribute
);
