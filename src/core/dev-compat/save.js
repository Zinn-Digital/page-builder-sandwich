/**
 * pbs/shortcode save(): the shortcode as plain text, nothing around it.
 *
 * ⭐ No wrapper element, so no class and no markup: the front end gets exactly what the
 * add-on's shortcode prints (footprint-free, docs/adr/0033). And if the plugin is deactivated,
 * the text left in post_content is an ordinary shortcode WordPress still runs.
 */

import { RawHTML } from '@wordpress/element';

import { buildShortcode } from './serialize';

export default function save( { attributes } ) {
	return (
		<RawHTML>
			{ buildShortcode(
				attributes.tag,
				attributes.attrs,
				attributes.content
			) }
		</RawHTML>
	);
}
