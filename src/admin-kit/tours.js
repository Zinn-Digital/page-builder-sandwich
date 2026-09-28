/* Generated from wp/packages/zinn-admin-kit/src/js/tours.js by wp/bin/build-admin-kit.php. Edit the package, never this copy. */
import { __ } from '@wordpress/i18n';

/**
 * The guided tour for each of the kit's own screens (feature adm-3). A host route brings its own
 * `tour` array; a route with no tour hides the "Take the tour" item.
 *
 * @return {Object<string, Array<Object>>} Route id -> steps.
 */
export default function kitTours() {
	const nav = {
		target: '[data-zak-tour="nav"]',
		title: __( 'Everything in one place', 'page-builder-sandwich' ),
		content: __(
			'These tabs lead to every screen of the plugin: setup, settings, your licence, help and more.',
			'page-builder-sandwich'
		),
	};
	const help = {
		target: '[data-zak-tour="help"]',
		title: __( 'Help on every screen', 'page-builder-sandwich' ),
		content: __(
			'Open this menu for the guide to the screen you are on, this tour, or to ask us for help.',
			'page-builder-sandwich'
		),
	};
	const theme = {
		target: '[data-zak-tour="theme"]',
		title: __( 'Light or dark', 'page-builder-sandwich' ),
		content: __(
			'Choose light, dark, or the same as your computer. Your choice is remembered for you only.',
			'page-builder-sandwich'
		),
	};

	return {
		overview: [
			{
				title: __( 'Welcome', 'page-builder-sandwich' ),
				content: __(
					'This short tour shows where things are. You can leave it at any time with Escape.',
					'page-builder-sandwich'
				),
			},
			nav,
			{
				target: '[data-zak-tour="status"]',
				title: __( 'Your site at a glance', 'page-builder-sandwich' ),
				content: __(
					'Setup progress and your plan are shown here, with the next thing worth doing.',
					'page-builder-sandwich'
				),
			},
			help,
			theme,
		],
		plans: [
			{
				target: '[data-zak-tour="licence"]',
				title: __( 'Your licence', 'page-builder-sandwich' ),
				content: __(
					'Your plan, how many sites it covers, and whether this copy of the site counts toward that limit.',
					'page-builder-sandwich'
				),
			},
			{
				target: '[data-zak-tour="offers"]',
				title: __( 'Try Pro or upgrade', 'page-builder-sandwich' ),
				content: __(
					'Start a free trial without a card, upgrade, or renew, all from here.',
					'page-builder-sandwich'
				),
			},
			{
				target: '[data-zak-tour="compare"]',
				title: __( 'Compare the plans', 'page-builder-sandwich' ),
				content: __(
					'Every feature and the plan that includes it. Search for a feature or show one area at a time.',
					'page-builder-sandwich'
				),
			},
		],
		addons: [
			{
				target: '[data-zak-tour="addons"]',
				title: __( 'More from Zinn Digital®', 'page-builder-sandwich' ),
				content: __(
					'Plugins that work with this one, and services from the same team. Cards you hid on other screens can be shown again here.',
					'page-builder-sandwich'
				),
			},
		],
		help: [
			{
				target: '[data-zak-tour="connection"]',
				title: __( 'Connect for faster help', 'page-builder-sandwich' ),
				content: __(
					'Connecting is optional. It lets you send requests without typing your e-mail each time, and you can disconnect whenever you like.',
					'page-builder-sandwich'
				),
			},
			{
				target: '[data-zak-tour="ticket"]',
				title: __( 'Ask for help or report a bug', 'page-builder-sandwich' ),
				content: __(
					'Describe what happened. You choose whether to attach site information, and you can see exactly what it contains first.',
					'page-builder-sandwich'
				),
			},
			{
				target: '[data-zak-tour="access"]',
				title: __( 'Temporary support access', 'page-builder-sandwich' ),
				content: __(
					'If support needs to look inside your site, create a temporary login here. It deletes itself when it expires, and you can remove it at any time.',
					'page-builder-sandwich'
				),
			},
		],
		feedback: [
			{
				target: '[data-zak-tour="ticket"]',
				title: __( 'Tell us what to build', 'page-builder-sandwich' ),
				content: __(
					'Send an idea, a feature request or any feedback. It goes straight to the team.',
					'page-builder-sandwich'
				),
			},
		],
	};
}
