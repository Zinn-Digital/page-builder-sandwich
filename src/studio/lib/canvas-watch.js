/**
 * Is the Studio's page canvas showing the page? (the pure half of the canvas guard)
 *
 * The canvas is an iframe the block editor builds. A security plugin's Content-Security-Policy
 * (`frame-src`), a browser extension or a stalled theme stylesheet can leave it blank, and a blank
 * canvas with no explanation reads as "the builder is broken". After CANVAS_TIMEOUT_MS with no
 * page, or at once when the browser reports a blocked frame, the Studio says so and offers Retry.
 */

/** How long the canvas may take before the Studio says it did not load. */
export const CANVAS_TIMEOUT_MS = 30000;

/** The class the canvas's block list carries (app.js), present once the page is drawn. */
export const CANVAS_ROOT = 'pbsw-studio-canvas-root';

/**
 * The canvas state.
 *
 * @param {Object}  input
 * @param {boolean} input.loaded  The page is in the canvas.
 * @param {boolean} input.blocked The browser reported the frame blocked.
 * @param {number}  input.elapsed Milliseconds since this attempt started.
 * @param {number}  [timeout]     The limit.
 * @return {'ok'|'waiting'|'blocked'|'failed'} State.
 */
export function canvasState(
	{ loaded, blocked, elapsed },
	timeout = CANVAS_TIMEOUT_MS
) {
	if ( loaded ) {
		return 'ok';
	}
	if ( blocked ) {
		return 'blocked';
	}
	return elapsed >= timeout ? 'failed' : 'waiting';
}

/**
 * Does this container hold the drawn page, in the canvas iframe or (no iframe) directly?
 * A frame the browser refused, or one from another origin, reads as not loaded.
 *
 * @param {Element|null} container The device wrapper around the canvas.
 * @return {boolean} Loaded.
 */
export function isCanvasLoaded( container ) {
	if ( ! container ) {
		return false;
	}
	if ( container.querySelector( `.${ CANVAS_ROOT }` ) ) {
		return true;
	}
	for ( const frame of container.querySelectorAll( 'iframe' ) ) {
		try {
			if ( frame.contentDocument?.querySelector( `.${ CANVAS_ROOT }` ) ) {
				return true;
			}
		} catch {
			// A cross-origin or refused frame cannot be read: not loaded.
		}
	}
	return false;
}

/**
 * Is a `securitypolicyviolation` event about a frame (the canvas)?
 *
 * @param {{violatedDirective?: string, effectiveDirective?: string}} event The event.
 * @return {boolean} A frame was blocked.
 */
export function isFrameViolation( event ) {
	const directive = String(
		event?.effectiveDirective || event?.violatedDirective || ''
	);
	return /^(frame-src|child-src)\b/.test( directive );
}
