/**
 * AI inside the builder (P12): the one way the editor calls the AI routes, and how a failure is
 * shown (what went wrong, whose problem it is, and a link to fix it — the AI core's own message).
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { Notice, ExternalLink } from '@wordpress/components';

/**
 * Call an AI route.
 *
 * @param {string} route  Route under /pbs/v1/ai (e.g. 'text').
 * @param {Object} data   Body (POST) or query (GET when method is GET).
 * @param {string} method HTTP method.
 * @return {Promise<Object>} The answer.
 */
export function ai( route, data = {}, method = 'POST' ) {
	if ( method === 'GET' ) {
		const query = new URLSearchParams( data ).toString();
		return apiFetch( {
			path: `/pbs/v1/ai/${ route }${ query ? `?${ query }` : '' }`,
		} );
	}
	return apiFetch( { path: `/pbs/v1/ai/${ route }`, method, data } );
}

/**
 * The status the server printed with the bundle.
 *
 * @return {Object} Status.
 */
export function status() {
	return window.pbswAi || { ready: false, pro_features: [] };
}

/**
 * Is a Pro AI feature loaded?
 *
 * @param {string} name Feature.
 * @return {boolean} Loaded.
 */
export function hasPro( name ) {
	return ( status().pro_features || [] ).includes( name );
}

/**
 * A failure as a notice, with the remedy link when the AI core gave one.
 *
 * @param {Object}   props
 * @param {Object}   props.error     The error apiFetch threw.
 * @param {Function} props.onDismiss Dismiss.
 * @return {Element|null} Notice.
 */
export function AiError( { error, onDismiss } ) {
	if ( ! error ) {
		return null;
	}
	const link = error?.data?.link;
	return (
		<Notice status="error" onRemove={ onDismiss }>
			{ error.message ||
				__(
					'The AI request failed. Try again.',
					'page-builder-sandwich'
				) }
			{ link && (
				<>
					{ ' ' }
					<ExternalLink href={ link }>
						{ __( 'Fix it', 'page-builder-sandwich' ) }
					</ExternalLink>
				</>
			) }
		</Notice>
	);
}

/**
 * Shown instead of the AI tools until a provider is connected.
 *
 * @return {Element} Notice.
 */
export function NotReady() {
	return (
		<Notice status="info" isDismissible={ false }>
			{ __(
				'AI uses your own AI provider account. Connect one to start.',
				'page-builder-sandwich'
			) }{ ' ' }
			<ExternalLink href={ status().settings_url }>
				{ __( 'Set up AI', 'page-builder-sandwich' ) }
			</ExternalLink>
		</Notice>
	);
}
