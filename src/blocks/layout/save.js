/**
 * The saved (fallback) HTML of pbs/section and pbs/container: the chosen tag around the inner
 * blocks, no class, no style, no data attribute (docs/adr/0033). It is what a visitor reads if the
 * plugin is ever switched off; while it is on, Blocks\Layout rebuilds the live HTML.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export const TAGS = [
	'section',
	'div',
	'header',
	'footer',
	'article',
	'aside',
	'main',
	'nav',
];

/**
 * A save() for a layout block whose tag defaults to `fallbackTag`.
 *
 * @param {string} fallbackTag Default tag.
 * @return {Function} save().
 */
export function layoutSave( fallbackTag ) {
	return function save( { attributes } ) {
		const url = attributes.link?.url;
		if ( url ) {
			return (
				<a href={ url }>
					<InnerBlocks.Content />
				</a>
			);
		}
		const Tag = TAGS.includes( attributes.tag )
			? attributes.tag
			: fallbackTag;
		return (
			<Tag>
				<InnerBlocks.Content />
			</Tag>
		);
	};
}
