/**
 * Which blocks will shift the layout when they load (pbs-p4). Pure: attributes in, a reason out,
 * so the rule is unit-tested without an editor.
 *
 * The server fills in what it can (an attachment's real size, a WordPress-sized file name — see
 * includes/assets/perf/class-layout-guard.php), so this warns only about what it cannot: an image
 * or video from outside the media library with no dimensions, raw HTML with a dimension-less
 * image, video or iframe, and embeds that resize themselves after loading.
 */

/** Embed types that are laid out by a third-party script after load. */
const RESIZING_EMBED_TYPES = [ 'rich' ];

/**
 * Does an element in some HTML lack a dimension?
 *
 * @param {string} html HTML.
 * @return {boolean} Whether an img/video/iframe lacks width or height.
 */
export function htmlHasUnsizedMedia( html ) {
	if ( ! html || ! /<(img|video|iframe)\b/i.test( html ) ) {
		return false;
	}
	const doc = new window.DOMParser().parseFromString( html, 'text/html' );
	return Array.from( doc.querySelectorAll( 'img, video, iframe' ) ).some(
		( el ) =>
			! ( el.hasAttribute( 'width' ) && el.hasAttribute( 'height' ) ) &&
			! /aspect-ratio/i.test( el.getAttribute( 'style' ) || '' )
	);
}

/**
 * The layout-shift problem of a block, or null.
 *
 * @param {string} name       Block name.
 * @param {Object} attributes Block attributes.
 * @return {string|null} `image`, `video`, `html` or `embed`.
 */
export function layoutShiftIssue( name, attributes = {} ) {
	switch ( name ) {
		case 'core/image':
			return attributes.url &&
				! attributes.id &&
				! ( attributes.width && attributes.height ) &&
				! attributes.aspectRatio
				? 'image'
				: null;
		case 'core/video':
			return attributes.src && ! attributes.id ? 'video' : null;
		case 'core/html':
			return htmlHasUnsizedMedia( attributes.content ) ? 'html' : null;
		case 'core/embed':
			return RESIZING_EMBED_TYPES.includes( attributes.type )
				? 'embed'
				: null;
		default:
			return null;
	}
}
