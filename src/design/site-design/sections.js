/**
 * The Site design sections. Free ships Colours and Fonts; the premium bundle adds its own through
 * the `pbsw.siteDesign.sections` filter (registered before the panel renders, because the
 * premium script is a dependency of this bundle).
 *
 * A section: `{ name, title, description, order, Component, needsPost?, upsell? }`.
 * `needsPost` sections (Page CSS) only appear where a post is being edited.
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import ColorsSection from './ColorsSection';
import FontsSection from './FontsSection';

/**
 * The premium sections' names and titles, shown as upgrade rows in the free edition so a
 * customer can see what the Pro plan adds (with no controls behind them).
 *
 * @return {Array<Object>} Rows.
 */
function upsells() {
	return [
		[ 'classes', __( 'Classes', 'page-builder-sandwich' ), 30 ],
		[ 'variables', __( 'Variables', 'page-builder-sandwich' ), 40 ],
		[ 'tokens', __( 'Spacing, radius and shadows', 'page-builder-sandwich' ), 50 ],
		[ 'dark-mode', __( 'Dark mode', 'page-builder-sandwich' ), 60 ],
		[ 'brand-kit', __( 'Brand kit', 'page-builder-sandwich' ), 70 ],
		[ 'custom-fonts', __( 'Custom fonts', 'page-builder-sandwich' ), 80 ],
		[ 'page-css', __( 'Page CSS', 'page-builder-sandwich' ), 90 ],
	].map( ( [ name, title, order ] ) => ( {
		name,
		title,
		order,
		upsell: true,
	} ) );
}

/**
 * The sections, sorted, for the given context.
 *
 * @param {Object}  context
 * @param {boolean} context.hasPost A post is being edited.
 * @param {string}  context.edition `free` or `pro`.
 * @return {Array<Object>} Sections.
 */
export function getSections( { hasPost = false, edition = 'free' } = {} ) {
	const base = [
		{
			name: 'colors',
			title: __( 'Colours', 'page-builder-sandwich' ),
			description: __(
				'Shared with your theme and the Site Editor.',
				'page-builder-sandwich'
			),
			order: 10,
			Component: ColorsSection,
		},
		{
			name: 'fonts',
			title: __( 'Fonts', 'page-builder-sandwich' ),
			description: __(
				'Heading and body fonts for the whole site.',
				'page-builder-sandwich'
			),
			order: 20,
			Component: FontsSection,
		},
		...( edition === 'free' ? upsells() : [] ),
	];
	const sections = applyFilters( 'pbsw.siteDesign.sections', base );
	return ( Array.isArray( sections ) ? sections : base )
		.filter( ( s ) => s && s.name && ( hasPost || ! s.needsPost ) )
		.sort( ( a, b ) => ( a.order || 0 ) - ( b.order || 0 ) );
}
