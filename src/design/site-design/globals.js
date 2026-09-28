/**
 * The site's colours and fonts (pbs-d4), shared by every section that shows them.
 */
import { designFetch } from './api';
import { createResource } from './resource';

export const globals = createResource( () => designFetch( '/globals' ) );

/**
 * Save colour/font changes; the shared copy becomes what the server kept.
 *
 * @param {Object} changes `colors`, `fonts`, `bodyFont`, `headingFont`.
 * @return {Promise<Object>} New globals.
 */
export async function saveGlobals( changes ) {
	const next = await designFetch( '/globals', 'POST', changes );
	globals.set( next );
	return next;
}
