/**
 * The canvas guard: when the page canvas is blocked or does not load, say so, with Retry.
 */
import { useEffect, useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	canvasState,
	isCanvasLoaded,
	isFrameViolation,
} from '../lib/canvas-watch';

/** How often the canvas is looked at while it loads. */
const POLL_MS = 500;

/**
 * Watch one attempt at loading the canvas.
 *
 * @param {{current: Element|null}} ref     The device wrapper around the canvas.
 * @param {number}                  attempt Bumped by Retry (the canvas is mounted again).
 * @return {'ok'|'waiting'|'blocked'|'failed'} State.
 */
export function useCanvasGuard( ref, attempt ) {
	const [ state, setState ] = useState( 'waiting' );
	useEffect( () => {
		setState( 'waiting' );
		const started = Date.now();
		let blocked = false;
		const onViolation = ( event ) => {
			if ( isFrameViolation( event ) ) {
				blocked = true;
			}
		};
		document.addEventListener( 'securitypolicyviolation', onViolation );
		const timer = setInterval( () => {
			const next = canvasState( {
				loaded: isCanvasLoaded( ref.current ),
				blocked,
				elapsed: Date.now() - started,
			} );
			setState( next );
			if ( 'waiting' !== next ) {
				clearInterval( timer );
			}
		}, POLL_MS );
		return () => {
			clearInterval( timer );
			document.removeEventListener(
				'securitypolicyviolation',
				onViolation
			);
		};
	}, [ ref, attempt ] );
	return state;
}

/**
 * The message, shown over the canvas when it is blocked or did not load.
 *
 * @param {Object}   props
 * @param {string}   props.state   From useCanvasGuard().
 * @param {Function} props.onRetry Mount the canvas again.
 * @return {Element|null} The message.
 */
export function CanvasGuardNotice( { state, onRetry } ) {
	if ( 'blocked' !== state && 'failed' !== state ) {
		return null;
	}
	return (
		<Notice
			status="error"
			isDismissible={ false }
			className="pbsw-studio-canvas-guard"
			actions={ [
				{
					label: __( 'Retry', 'page-builder-sandwich' ),
					onClick: onRetry,
					variant: 'primary',
				},
			] }
		>
			<p>
				{ 'blocked' === state
					? __(
							'The page preview was blocked by a security setting on this site or in your browser (a Content-Security-Policy that does not allow frames).',
							'page-builder-sandwich'
						)
					: __(
							'The page preview did not load within 30 seconds. A security plugin, a browser extension or a slow connection can stop it.',
							'page-builder-sandwich'
						) }
			</p>
			<p>
				{ __(
					'Your work is safe: nothing is lost. Retry loads the preview again.',
					'page-builder-sandwich'
				) }
			</p>
		</Notice>
	);
}
