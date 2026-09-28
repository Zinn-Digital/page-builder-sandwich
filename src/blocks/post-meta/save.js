/**
 * pbs/post-meta saved markup: nothing. The block shows live site data (the post's author, dates and terms), which static markup
 * could only get wrong, so it is rendered on every view by Site::render_post_meta() — and with the
 * plugin off the page simply does not show it.
 */
export default function save() {
	return null;
}
