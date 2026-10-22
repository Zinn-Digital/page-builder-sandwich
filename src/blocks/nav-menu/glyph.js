/**
 * pbs/nav-menu's glyphs, from the same file the render callback reads
 * (includes/blocks/site/nav-glyphs.json), so the editor draws exactly what the page prints.
 */
import GLYPHS from '../../../includes/blocks/site/nav-glyphs.json';

/**
 * One decorative glyph.
 *
 * @param {Object} props
 * @param {string} props.name Glyph name.
 * @return {Element|null} SVG.
 */
export default function Glyph( { name } ) {
	const d = GLYPHS[ name ];
	if ( ! d ) {
		return null;
	}
	return (
		<svg
			viewBox="0 0 24 24"
			width="1em"
			height="1em"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			<path d={ d } />
		</svg>
	);
}
