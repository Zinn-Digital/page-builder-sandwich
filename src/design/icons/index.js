/**
 * The icon library for the editor (pbs-b2, pbs-r12). Editor-only; the front end loads nothing
 * from here.
 */

export { default as IconPicker, parseRef, UPLOADS_PATH } from './IconPicker';
export { default as IconGrid } from './IconGrid';
export { iconSvg, sanitizeSvg, sizedSvg } from './sanitize';
export { SETS, loadSet, search } from './sets';
