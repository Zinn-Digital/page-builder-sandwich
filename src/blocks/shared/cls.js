/**
 * The editor's twin of \ZinnDigital\PBS\Frontend::cls(): the SAME neutral classes the front end
 * prints, so the block stylesheets (loaded into the editor canvas by Blocks\Registry) style the
 * edit view exactly as they style the page. The prefix comes from `window.pbswBlocks`
 * (Registry::editor_data()); `zd` is the plugin's default.
 *
 * ⛔ Editor only. A block's save() never uses this: saved markup carries no classes at all.
 */

/**
 * The site's neutral class prefix.
 *
 * @return {string} Prefix.
 */
export function prefix() {
	const value =
		typeof window !== 'undefined' && window.pbswBlocks
			? window.pbswBlocks.prefix
			: '';
	return /^[a-z][a-z0-9]{0,7}$/.test( value || '' ) ? value : 'zd';
}

/**
 * Prefixed class names: `cls( 'alert', 'alert--info' )` → `zd-alert zd-alert--info`.
 *
 * @param {...(string|false|null|undefined)} parts Class suffixes; falsy ones are skipped.
 * @return {string} Class names.
 */
export function cls( ...parts ) {
	const p = prefix();
	return parts
		.filter( Boolean )
		.map( ( part ) => `${ p }-${ part }` )
		.join( ' ' );
}
