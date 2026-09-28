/**
 * pbs/user-profile saved markup: nothing. The block shows live site data (a user's profile), which static markup
 * could only get wrong, so it is rendered on every view by Site::render_user_profile() — and with the
 * plugin off the page simply does not show it.
 */
export default function save() {
	return null;
}
