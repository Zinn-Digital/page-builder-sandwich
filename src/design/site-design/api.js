/**
 * The Site design screen's REST client (`pbs/v1/design/*`) and boot data.
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Boot data printed by Site_Design::data().
 *
 * @return {Object} Boot data.
 */
export function boot() {
	return (
		window.pbswSiteDesign || {
			edition: 'free',
			namespace: 'pbs/v1',
			base: '/design',
			prefix: 'zd',
			canWriteCss: false,
		}
	);
}

/**
 * Call a design route.
 *
 * @param {string} path   Route after `/design`, e.g. `/globals`.
 * @param {string} method HTTP method.
 * @param {Object} data   Body.
 * @return {Promise<Object>} Response.
 */
export function designFetch( path, method = 'GET', data = undefined ) {
	const b = boot();
	return apiFetch( {
		path: `/${ b.namespace }${ b.base }${ path }`,
		method,
		data,
	} );
}
