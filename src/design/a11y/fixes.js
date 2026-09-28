/**
 * What a finding's fix does to the block (pure halves unit-tested; applyFix is the editor half).
 */
import { contrastRatio, parseColor } from './checks';

/**
 * The palette colour that reads best on a background, if one reaches the required ratio.
 *
 * @param {Array}  palette    `[{ slug, color }]` (the editor's colour settings).
 * @param {Object} background Opaque colour.
 * @param {number} need       Required ratio.
 * @return {{slug: string, ratio: number}|null} Best colour.
 */
export function bestTextColor( palette, background, need ) {
	let best = null;
	for ( const entry of palette || [] ) {
		const c = parseColor( entry.color );
		if ( ! c || c.a < 1 ) {
			continue;
		}
		const ratio = contrastRatio( c, background );
		if ( ratio >= need && ( ! best || ratio > best.ratio ) ) {
			best = { slug: entry.slug, ratio };
		}
	}
	return best;
}

/**
 * The attribute changes a fix makes, or null when the block cannot take them.
 *
 * @param {Object} fix       Finding fix.
 * @param {Object} block     `{ name, attributes }`.
 * @param {Object} blockType Registered block type (for `supports`).
 * @param {Object} context   `{ palette, alt, decorative }`.
 * @return {Object|null} Attributes to set.
 */
export function fixAttributes( fix, block, blockType, context = {} ) {
	switch ( fix?.type ) {
		case 'heading-level':
			return block.name === 'core/heading' ? { level: fix.level } : null;
		case 'image-alt':
			if ( block.name !== 'core/image' ) {
				return null;
			}
			if ( context.decorative ) {
				return {
					alt: '',
					pbs: {
						...( block.attributes.pbs || {} ),
						decorative: true,
					},
				};
			}
			return String( context.alt || '' ).trim()
				? { alt: String( context.alt ).trim() }
				: null;
		case 'contrast': {
			if ( ! blockType?.supports?.color ) {
				return null;
			}
			const best = bestTextColor(
				context.palette,
				fix.background,
				fix.need
			);
			if ( ! best ) {
				return null;
			}
			// Clear a custom colour, which would otherwise beat the preset.
			const style = { ...( block.attributes.style || {} ) };
			if ( style.color ) {
				style.color = { ...style.color };
				delete style.color.text;
			}
			return { textColor: best.slug, style };
		}
		default:
			return null;
	}
}
