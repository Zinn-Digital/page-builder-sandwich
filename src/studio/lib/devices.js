/**
 * Studio's preview widths follow the design breakpoints (pbs-d2, pbs-d3): Desktop, Tablet, Mobile
 * and every custom breakpoint, each resizing the canvas to its width. The device IS the design
 * breakpoint being edited (store `pbsw/design`), so the Style tab and the canvas always agree.
 */

/** Default preview widths for the built-in breakpoints (a real device, not the range's edge). */
export const DEFAULT_WIDTH = { tablet: 780, mobile: 380 };

/**
 * The breakpoints the design bundle printed (window.pbswDesign), or the built-in two.
 *
 * @return {Array<Object>} Breakpoints in output order.
 */
export const breakpoints = () =>
	window.pbswDesign?.breakpoints || [
		{ id: 'tablet', label: 'Tablet', max: 1024 },
		{ id: 'mobile', label: 'Mobile', max: 767 },
	];

/**
 * The canvas width for a device, or null for the full width.
 *
 * @param {string}        device Device (`desktop` or a breakpoint id).
 * @param {Array<Object>} list   Breakpoints.
 * @return {number|null} Width in px.
 */
export function deviceWidth( device, list = breakpoints() ) {
	if ( device === 'desktop' || device === 'base' ) {
		return null;
	}
	const bp = list.find( ( b ) => b.id === device );
	if ( ! bp ) {
		return DEFAULT_WIDTH[ device ] ?? null;
	}
	if ( bp.min !== undefined ) {
		return bp.min;
	}
	return DEFAULT_WIDTH[ bp.id ]
		? Math.min( DEFAULT_WIDTH[ bp.id ], bp.max )
		: bp.max;
}

/**
 * Studio device name → design breakpoint id.
 *
 * @param {string} device Device.
 * @return {string} Breakpoint id.
 */
export const toBreakpoint = ( device ) =>
	device === 'desktop' ? 'base' : device;

/**
 * Design breakpoint id → Studio device name.
 *
 * @param {string} bp Breakpoint id.
 * @return {string} Device.
 */
export const toDevice = ( bp ) => ( bp === 'base' ? 'desktop' : bp );
