/**
 * Friendly settings for well-known shortcodes that no add-on declared (pbs-b3): WordPress's own
 * media shortcodes and the four form plugins. A definition has the legacy `pbsAddInspector`
 * shape, so it is drawn by the same OptionControl as an add-on's; any attribute it does not name
 * stays editable in the generic "Other attributes" list, so nothing a user typed is ever hidden.
 */
import { __ } from '@wordpress/i18n';

/**
 * The form choices for a form plugin's shortcode, from the Form block's provider list.
 *
 * @param {Array}  providers `pbs/v1/forms`.
 * @param {string} provider  Provider key.
 * @return {Array<{value: string, label: string}>} Choices.
 */
function formChoices( providers, provider ) {
	const row = ( providers || [] ).find( ( p ) => p.provider === provider );
	return [
		{ value: '', label: __( 'Choose a form…', 'page-builder-sandwich' ) },
		...( row?.forms || [] ).map( ( f ) => ( {
			value: f.id,
			label: f.title,
		} ) ),
	];
}

const yesNo = ( id, name ) => ( {
	type: 'checkbox',
	id,
	name,
	checked: 'true',
	unchecked: 'false',
} );

/**
 * The definition for a tag, or null.
 *
 * @param {string} tag     Shortcode tag.
 * @param {Object} context `{ providers }` (the form lists).
 * @return {Object|null} Legacy-shaped definition `{ label, desc, options }`.
 */
export function knownDefinition( tag, context = {} ) {
	const { providers } = context;
	switch ( tag ) {
		case 'gallery':
			return {
				label: __( 'Gallery', 'page-builder-sandwich' ),
				options: [
					{
						type: 'image',
						id: 'ids',
						name: __( 'Images', 'page-builder-sandwich' ),
						multiple: true,
					},
					{
						type: 'number',
						id: 'columns',
						name: __( 'Columns', 'page-builder-sandwich' ),
						min: 1,
						max: 9,
						default: '3',
					},
					{
						type: 'select',
						id: 'size',
						name: __( 'Image size', 'page-builder-sandwich' ),
						options: {
							thumbnail: __(
								'Thumbnail',
								'page-builder-sandwich'
							),
							medium: __( 'Medium', 'page-builder-sandwich' ),
							large: __( 'Large', 'page-builder-sandwich' ),
							full: __( 'Full size', 'page-builder-sandwich' ),
						},
					},
					{
						type: 'select',
						id: 'link',
						name: __( 'Link to', 'page-builder-sandwich' ),
						options: {
							'': __(
								'Attachment page',
								'page-builder-sandwich'
							),
							file: __( 'Media file', 'page-builder-sandwich' ),
							none: __( 'None', 'page-builder-sandwich' ),
						},
					},
				],
			};
		case 'audio':
		case 'video':
			return {
				label:
					'audio' === tag
						? __( 'Audio', 'page-builder-sandwich' )
						: __( 'Video', 'page-builder-sandwich' ),
				options: [
					{
						type: 'link',
						id: 'src',
						name: __( 'File address', 'page-builder-sandwich' ),
					},
					yesNo( 'loop', __( 'Loop', 'page-builder-sandwich' ) ),
					yesNo(
						'autoplay',
						__( 'Play automatically', 'page-builder-sandwich' )
					),
					...( 'video' === tag
						? [
								{
									type: 'image',
									id: 'poster',
									name: __(
										'Poster image',
										'page-builder-sandwich'
									),
								},
							]
						: [] ),
				],
			};
		case 'playlist':
			return {
				label: __( 'Playlist', 'page-builder-sandwich' ),
				options: [
					{
						type: 'select',
						id: 'type',
						name: __( 'Type', 'page-builder-sandwich' ),
						options: {
							audio: __( 'Audio', 'page-builder-sandwich' ),
							video: __( 'Video', 'page-builder-sandwich' ),
						},
					},
					{
						type: 'text',
						id: 'ids',
						name: __(
							'Media IDs, separated by commas',
							'page-builder-sandwich'
						),
					},
					yesNo(
						'tracklist',
						__( 'Show the track list', 'page-builder-sandwich' )
					),
				],
			};
		case 'embed':
			return {
				label: __( 'Embed', 'page-builder-sandwich' ),
				options: [
					{
						type: 'link',
						id: 'content',
						name: __( 'Address to embed', 'page-builder-sandwich' ),
					},
					{
						type: 'number',
						id: 'width',
						name: __( 'Maximum width', 'page-builder-sandwich' ),
						min: 0,
					},
				],
			};
		case 'caption':
			return {
				label: __( 'Caption', 'page-builder-sandwich' ),
				options: [
					{
						type: 'textarea',
						id: 'content',
						name: __(
							'Image and caption HTML',
							'page-builder-sandwich'
						),
					},
					{
						type: 'select',
						id: 'align',
						name: __( 'Alignment', 'page-builder-sandwich' ),
						options: {
							alignnone: __( 'None', 'page-builder-sandwich' ),
							alignleft: __( 'Start', 'page-builder-sandwich' ),
							aligncenter: __(
								'Centre',
								'page-builder-sandwich'
							),
							alignright: __( 'End', 'page-builder-sandwich' ),
						},
					},
					{
						type: 'number',
						id: 'width',
						name: __( 'Width', 'page-builder-sandwich' ),
						min: 0,
					},
				],
			};
		case 'contact-form-7':
			return {
				label: 'Contact Form 7',
				desc: __(
					'Tip: the Form block shows this form styled to match your site.',
					'page-builder-sandwich'
				),
				options: [
					{
						type: 'select',
						id: 'id',
						name: __( 'Form', 'page-builder-sandwich' ),
						options: formChoices( providers, 'cf7' ),
					},
				],
			};
		case 'wpforms':
			return {
				label: 'WPForms',
				desc: __(
					'Tip: the Form block shows this form styled to match your site.',
					'page-builder-sandwich'
				),
				options: [
					{
						type: 'select',
						id: 'id',
						name: __( 'Form', 'page-builder-sandwich' ),
						options: formChoices( providers, 'wpforms' ),
					},
					yesNo(
						'title',
						__( 'Show the form title', 'page-builder-sandwich' )
					),
					yesNo(
						'description',
						__(
							'Show the form description',
							'page-builder-sandwich'
						)
					),
				],
			};
		case 'gravityform':
			return {
				label: 'Gravity Forms',
				desc: __(
					'Tip: the Form block shows this form styled to match your site.',
					'page-builder-sandwich'
				),
				options: [
					{
						type: 'select',
						id: 'id',
						name: __( 'Form', 'page-builder-sandwich' ),
						options: formChoices( providers, 'gravityforms' ),
					},
					yesNo(
						'title',
						__( 'Show the form title', 'page-builder-sandwich' )
					),
					yesNo(
						'description',
						__(
							'Show the form description',
							'page-builder-sandwich'
						)
					),
					yesNo(
						'ajax',
						__(
							'Submit without reloading the page',
							'page-builder-sandwich'
						)
					),
				],
			};
		case 'fluentform':
			return {
				label: 'Fluent Forms',
				desc: __(
					'Tip: the Form block shows this form styled to match your site.',
					'page-builder-sandwich'
				),
				options: [
					{
						type: 'select',
						id: 'id',
						name: __( 'Form', 'page-builder-sandwich' ),
						options: formChoices( providers, 'fluentforms' ),
					},
				],
			};
		default:
			return null;
	}
}
