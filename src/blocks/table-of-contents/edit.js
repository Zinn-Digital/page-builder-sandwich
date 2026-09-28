/**
 * pbs/table-of-contents editor: the list built live from the headings being edited, exactly as
 * the page will show it.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

import { cls } from '../shared/cls';
import { headingsOf, nest } from './headings';

const LEVELS = [ 1, 2, 3, 4, 5, 6 ];

/**
 * One list level.
 *
 * @param {Object}   props
 * @param {Object[]} props.items   Nested items.
 * @param {boolean}  props.ordered Numbered.
 * @return {Element} List.
 */
function List( { items, ordered } ) {
	const Tag = ordered ? 'ol' : 'ul';
	return (
		<Tag className={ cls( 'table-of-contents__list' ) }>
			{ items.map( ( item ) => (
				<li
					key={ item.id }
					className={ cls( 'table-of-contents__item' ) }
				>
					<a
						className={ cls( 'table-of-contents__link' ) }
						href={ `#${ item.id }` }
						onClick={ ( event ) => event.preventDefault() }
					>
						{ item.text }
					</a>
					{ item.children.length > 0 && (
						<List items={ item.children } ordered={ ordered } />
					) }
				</li>
			) ) }
		</Tag>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const { title, minLevel, maxLevel, ordered, collapsible, collapsed, spy } =
		attributes;
	const blocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks(),
		[]
	);
	const min = Math.min( minLevel, maxLevel );
	const items = headingsOf( blocks ).filter(
		( h ) => h.level >= min && h.level <= maxLevel
	);
	const label = title || __( 'Contents', 'page-builder-sandwich' );
	const blockProps = useBlockProps( {
		className: cls( 'table-of-contents' ),
	} );
	const levelOptions = LEVELS.map( ( n ) => ( {
		value: String( n ),
		label: sprintf(
			/* translators: %d: a heading level, 1 to 6. */
			__( 'Heading %d', 'page-builder-sandwich' ),
			n
		),
	} ) );
	const list = items.length ? (
		<List items={ nest( items, min ) } ordered={ ordered } />
	) : (
		<p className={ cls( 'table-of-contents__empty' ) }>
			{ __(
				'Add headings to the page and they will be listed here.',
				'page-builder-sandwich'
			) }
		</p>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Table of contents', 'page-builder-sandwich' ) }
				>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Title', 'page-builder-sandwich' ) }
						placeholder={ __(
							'Contents',
							'page-builder-sandwich'
						) }
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __(
							'From heading level',
							'page-builder-sandwich'
						) }
						value={ String( minLevel ) }
						options={ levelOptions }
						onChange={ ( value ) =>
							setAttributes( { minLevel: Number( value ) } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __(
							'To heading level',
							'page-builder-sandwich'
						) }
						value={ String( maxLevel ) }
						options={ levelOptions }
						onChange={ ( value ) =>
							setAttributes( { maxLevel: Number( value ) } )
						}
					/>
					<ToggleControl
						label={ __( 'Numbered', 'page-builder-sandwich' ) }
						checked={ ordered }
						onChange={ ( value ) =>
							setAttributes( { ordered: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Can be collapsed',
							'page-builder-sandwich'
						) }
						checked={ collapsible }
						onChange={ ( value ) =>
							setAttributes( { collapsible: value } )
						}
					/>
					{ collapsible && (
						<ToggleControl
							label={ __(
								'Start collapsed',
								'page-builder-sandwich'
							) }
							checked={ collapsed }
							onChange={ ( value ) =>
								setAttributes( { collapsed: value } )
							}
						/>
					) }
					<ToggleControl
						label={ __(
							'Highlight the section being read',
							'page-builder-sandwich'
						) }
						checked={ spy }
						onChange={ ( value ) =>
							setAttributes( { spy: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<nav { ...blockProps } aria-label={ label }>
				{ collapsible ? (
					<div className={ cls( 'table-of-contents__details' ) }>
						<p className={ cls( 'table-of-contents__title' ) }>
							{ label }
						</p>
						{ list }
					</div>
				) : (
					<>
						{ title && (
							<p className={ cls( 'table-of-contents__title' ) }>
								{ title }
							</p>
						) }
						{ list }
					</>
				) }
			</nav>
		</>
	);
}
