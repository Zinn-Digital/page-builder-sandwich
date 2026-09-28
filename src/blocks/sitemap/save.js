/**
 * pbs/sitemap saved markup: nothing. The block shows live site data (every published page), which static markup
 * could only get wrong, so it is rendered on every view by Site::render_sitemap() — and with the
 * plugin off the page simply does not show it.
 */
export default function save() {
	return null;
}
