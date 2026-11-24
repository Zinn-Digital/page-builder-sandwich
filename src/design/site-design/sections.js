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
 * The sections, sorted, for the given context.
 *
 * @param {Object}  context
 * @param {boolean} context.hasPost A post is being edited.
 * @return {Array<Object>} Sections.
 */
export function getSections( { hasPost = false } = {} ) {
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
	];
	const sections = applyFilters( 'pbsw.siteDesign.sections', base );
	return ( Array.isArray( sections ) ? sections : base )
		.filter( ( s ) => s && s.name && ( hasPost || ! s.needsPost ) )
		.sort( ( a, b ) => ( a.order || 0 ) - ( b.order || 0 ) );
}
