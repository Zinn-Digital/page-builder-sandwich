/**
 * `parse()` for markup that may have been saved before the block namespace moved from `pbs/` to
 * `pbsw/` (library items, recovered drafts, revisions). The PHP side is Core\Block_Names.
 */
import { parse as coreParse } from '@wordpress/blocks';

/**
 * Translate the block comments in serialized markup.
 *
 * @param {string} markup Block markup.
 * @return {string} The markup with current block names.
 */
export const upgradeMarkup = ( markup ) =>
	String( markup ?? '' ).replace(
		/<!--(\s+\/?)wp:pbs\/(?=[a-z])/g,
		'<!--$1wp:pbsw/'
	);

/**
 * Parse block markup, reading earlier block names.
 *
 * @param {string} markup  Block markup.
 * @param {Object} options Options for core's parser.
 * @return {Array} The blocks.
 */
export const parse = ( markup, options ) =>
	coreParse( upgradeMarkup( markup ), options );
