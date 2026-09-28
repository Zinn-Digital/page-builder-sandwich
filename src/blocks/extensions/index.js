/**
 * Extensions of core rich text and core blocks (P5 group G-B): the Highlight format, the drop
 * cap and the pull quote style. Registered once, with the design-system blocks.
 */
import { register as registerHighlight } from './highlight';
import { register as registerTextStyles } from './text-styles';

export function registerExtensions() {
	registerHighlight();
	registerTextStyles();
}
