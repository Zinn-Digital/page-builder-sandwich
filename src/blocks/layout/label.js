/**
 * pbs-d17 "Name your rows and sections": a section's or container's name, kept where WordPress
 * keeps a block's name (`metadata.name`, the one List View's "Rename" writes), so the Layers
 * panel, List View and this label all read the same value.
 *
 * ⛔ Editor-only: save() never prints it, so a name never reaches a visitor's page.
 */

/**
 * The block's name, trimmed; '' when it has none.
 *
 * @param {Object} attributes Block attributes.
 * @return {string} The name.
 */
export function labelOf( attributes ) {
	const name = attributes?.metadata?.name;
	return typeof name === 'string' ? name.trim() : '';
}

/**
 * The `metadata` attribute with the name set, or removed when blank. Every other metadata key
 * (bindings, block visibility, …) is kept.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} name       The new name.
 * @return {Object|undefined} The new `metadata` value (undefined when nothing is left).
 */
export function metadataWithLabel( attributes, name ) {
	const rest = { ...( attributes?.metadata || {} ) };
	delete rest.name;
	const clean = typeof name === 'string' ? name : '';
	if ( clean.trim() !== '' ) {
		return { ...rest, name: clean };
	}
	return Object.keys( rest ).length ? rest : undefined;
}
