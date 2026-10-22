/**
 * pbs/nav-menu saves only its inner blocks (its mega menu panels): the footprint-free fallback a reader gets with
 * the plugin off (no classes, no data-wp-*). Site::render_nav_menu() rebuilds the live HTML.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
