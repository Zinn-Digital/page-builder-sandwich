/**
 * SEO plugin content analysis sees what the visitor sees (P13, pbs-p10).
 *
 * Yoast SEO and Rank Math analyse the editor's saved markup, where dynamic text (block bindings,
 * query loops, server-rendered blocks) is not filled in yet. This asks the server for the page as a
 * visitor gets it (POST /pbs/v1/seo/rendered, cached on the server by content) and hands it to each
 * plugin's own content hook: Yoast's `registerModification( 'content' )`, Rank Math's
 * `rank_math_content` filter. Nothing is asked for while the page has no dynamic part.
 */
import apiFetch from '@wordpress/api-fetch';
import { addFilter } from '@wordpress/hooks';
import { subscribe, select } from '@wordpress/data';
import { debounce } from '@wordpress/compose';

const NAME = 'pbsRenderedContent';
let rendered = null;
let lastContent = null;

/**
 * Does this markup have text that only exists once the page is rendered?
 *
 * @param {string} content Block markup.
 * @return {boolean} Dynamic.
 */
export function isDynamic( content ) {
	// Any of our blocks (many are rendered on the server) or any block binding.
	return /<!-- wp:pbs\//.test( content ) || /"bindings"\s*:/.test( content );
}

/**
 * The content each plugin should analyse.
 *
 * @param {string} content What the plugin read.
 * @return {string} Rendered HTML when known.
 */
export function contentFor( content ) {
	return rendered !== null ? rendered : content;
}

function reload() {
	window.YoastSEO?.app?.pluginReloaded?.( NAME );
	window.rankMathEditor?.refresh?.( 'content' );
}

const refresh = debounce( () => {
	// Yoast builds its app after the editor: register the moment it exists.
	registerYoast();
	const editor = select( 'core/editor' );
	const postId = editor?.getCurrentPostId?.();
	const content = editor?.getEditedPostContent?.();
	if ( ! postId || typeof content !== 'string' || content === lastContent ) {
		return;
	}
	lastContent = content;
	if ( ! isDynamic( content ) ) {
		if ( rendered !== null ) {
			rendered = null;
			reload();
		}
		return;
	}
	apiFetch( {
		path: '/pbs/v1/seo/rendered',
		method: 'POST',
		data: { post_id: postId, content },
	} )
		.then( ( res ) => {
			rendered = typeof res?.html === 'string' ? res.html : null;
			reload();
		} )
		.catch( () => {
			// The plugins keep analysing the saved markup, as they would without us.
		} );
}, 1500 );

function registerYoast() {
	const app = window.YoastSEO?.app;
	if ( ! app?.registerPlugin || app.__pbsRegistered ) {
		return;
	}
	app.registerPlugin( NAME, { status: 'ready' } );
	app.registerModification( 'content', contentFor, NAME, 20 );
	app.__pbsRegistered = true;
}

addFilter( 'rank_math_content', 'pbs/seo/rendered', contentFor );

if ( window.YoastSEO?.app ) {
	registerYoast();
} else if ( window.jQuery ) {
	window.jQuery( window ).on( 'YoastSEO:ready', registerYoast );
}
window.addEventListener( 'load', registerYoast );

// Expose for tests and add-ons: what the analysis currently receives.
window.pbsSeoContent = ( base ) => contentFor( base );

subscribe( refresh );
