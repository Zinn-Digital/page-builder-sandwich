/**
 * Core blocks in the Sandwich inserter categories (lane L09 G-C, contract §3 "D2 portability"):
 * Heading, Text, Image, Video, Spacer, Divider, Embed, Gallery are WordPress's own blocks — so the
 * content stays valid and readable without the plugin — offered as Sandwich variations with a few
 * design presets, and edited with the same Style tab as every block. The presets are ordinary
 * `pbs.s` values (the element id is given when the block is inserted, editor/ids-watch.js).
 * Footnotes join the Content category.
 *
 * Loaded by the design bundle (in the head), so the category filter is in place before the
 * editor or Studio registers the core blocks.
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { getBlockVariations, registerBlockVariation } from '@wordpress/blocks';
import domReady from '@wordpress/dom-ready';

/**
 * The variations: [block, name, category, title, description, attributes, keywords].
 *
 * @return {Array<Object>} Variations.
 */
export function variations() {
	return [
		{
			block: 'core/heading',
			name: 'pbs-heading',
			category: 'pbs-content',
			title: __( 'Heading', 'page-builder-sandwich' ),
			description: __(
				'A heading with a tighter line height, styled with the Style tab.',
				'page-builder-sandwich'
			),
			attributes: {
				level: 2,
				pbs: {
					s: { base: { lineHeight: 1.2, letterSpacing: '-0.01em' } },
				},
			},
		},
		{
			block: 'core/paragraph',
			name: 'pbs-text',
			category: 'pbs-content',
			title: __( 'Text', 'page-builder-sandwich' ),
			description: __(
				'A paragraph of text, styled with the Style tab.',
				'page-builder-sandwich'
			),
			attributes: {},
		},
		{
			block: 'core/image',
			name: 'pbs-image',
			category: 'pbs-media',
			title: __( 'Image', 'page-builder-sandwich' ),
			description: __(
				'An image with rounded corners, styled with the Style tab.',
				'page-builder-sandwich'
			),
			attributes: {
				pbs: {
					s: {
						base: {
							radius: {
								startStart: '8px',
								startEnd: '8px',
								endStart: '8px',
								endEnd: '8px',
							},
							overflow: 'hidden',
						},
					},
				},
			},
		},
		{
			block: 'core/video',
			name: 'pbs-video',
			category: 'pbs-media',
			title: __( 'Video', 'page-builder-sandwich' ),
			description: __(
				'A video from the Media Library, styled with the Style tab.',
				'page-builder-sandwich'
			),
			attributes: {},
		},
		{
			block: 'core/spacer',
			name: 'pbs-spacer',
			category: 'pbs-design',
			title: __( 'Spacer', 'page-builder-sandwich' ),
			description: __(
				'Empty space between blocks; set a different height per screen size in the Style tab.',
				'page-builder-sandwich'
			),
			attributes: { height: '48px' },
		},
		{
			block: 'core/separator',
			name: 'pbs-divider',
			category: 'pbs-design',
			title: __( 'Divider', 'page-builder-sandwich' ),
			description: __(
				'A horizontal line between sections, styled with the Style tab.',
				'page-builder-sandwich'
			),
			attributes: {
				pbs: {
					s: {
						base: {
							margin: { blockStart: '2em', blockEnd: '2em' },
							opacity: 0.4,
						},
					},
				},
			},
		},
		{
			block: 'core/embed',
			name: 'pbs-embed',
			category: 'pbs-media',
			title: __( 'Embed', 'page-builder-sandwich' ),
			description: __(
				'Content from another site (a video, a post, a map) by pasting its address.',
				'page-builder-sandwich'
			),
			attributes: {},
		},
		{
			block: 'core/gallery',
			name: 'pbs-gallery',
			category: 'pbs-media',
			title: __( 'Gallery', 'page-builder-sandwich' ),
			description: __(
				'Several images in a grid, styled with the Style tab.',
				'page-builder-sandwich'
			),
			attributes: { columns: 3, linkTo: 'none' },
		},
	];
}

/**
 * Register every variation whose block exists and does not have it yet.
 */
export function registerVariations() {
	for ( const v of variations() ) {
		const { block, ...variation } = v;
		if (
			( getBlockVariations( block ) || [] ).some(
				( x ) => x.name === v.name
			)
		) {
			continue;
		}
		registerBlockVariation( block, {
			...variation,
			scope: [ 'inserter' ],
			keywords: [ 'sandwich', variation.title ],
		} );
	}
}

// Footnotes are inserted by the footnote format, and listed under Sandwich: Content.
addFilter(
	'blocks.registerBlockType',
	'pbsw/design/footnotes-category',
	( settings, name ) =>
		name === 'core/footnotes'
			? { ...settings, category: 'pbs-content' }
			: settings
);

domReady( registerVariations );
