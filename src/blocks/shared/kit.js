/**
 * Shared editor pieces of the content and marketing blocks (lane L09, P5 group G-B). Twin of
 * includes/blocks/class-kit.php: the SAME built-in icons, so the editor preview and the page
 * show one drawing.
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal, SelectControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

import IconPicker from '../../design/icons/IconPicker';
import { sanitizeSvg } from '../../design/icons/sanitize';
import { cls } from './cls';

/** Built-in icons (Kit::ICONS). */
export const ICONS = {
	check: <path d="M20 6 9 17l-5-5" />,
	circle: <circle cx="12" cy="12" r="9" />,
	cross: <path d="M18 6 6 18M6 6l12 12" />,
	dot: <circle cx="12" cy="12" r="3.5" fill="currentColor" />,
	arrow: <path d="M5 12h14M13 6l6 6-6 6" />,
	star: (
		<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z" />
	),
	info: (
		<>
			<circle cx="12" cy="12" r="10" />
			<path d="M12 16v-5M12 8h.01" />
		</>
	),
	link: (
		<>
			<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7" />
			<path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7" />
		</>
	),
	mail: (
		<>
			<rect x="3" y="5" width="18" height="14" rx="2" />
			<path d="m3 7 9 6 9-6" />
		</>
	),
	rss: (
		<>
			<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16" />
			<circle cx="5" cy="19" r="1.5" fill="currentColor" />
		</>
	),
	phone: (
		<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z" />
	),
	'star-f': (
		<path
			d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z"
			fill="currentColor"
		/>
	),
};

export { safeUrl } from './kit-url';

// The few editor-only styles of the list tools (sidebar rows, the "Add" button in the canvas,
// the cell-type switch). Editor chrome only; logical properties, so nothing needs mirroring.
if (
	typeof document !== 'undefined' &&
	! document.getElementById( 'pbsw-kit-css' )
) {
	const style = document.createElement( 'style' );
	style.id = 'pbsw-kit-css';
	style.textContent = `.pbsw-kit-row,.pbsw-kit-fieldset{display:flex;flex-wrap:wrap;gap:4px 8px;align-items:center;margin-block-end:12px}
.pbsw-kit-fieldset{display:grid;padding:8px;border:1px solid #dcdcde;border-radius:2px}
.pbsw-kit-fieldset legend{padding-inline:4px;font-weight:500}
.pbsw-kit-row>strong{flex:1 1 100%}
.pbsw-kit-tools{display:inline-flex;gap:2px}
.pbsw-kit-add{list-style:none;margin-block-start:8px}
.pbsw-kit-cell-type{margin-inline-start:4px;vertical-align:middle}`;
	document.head.appendChild( style );
}

/** Accent colours (Kit::ACCENTS). */
export const ACCENTS = [ 'text', 'primary', 'secondary', 'contrast' ];

/**
 * Labels of the built-in icons.
 *
 * @return {Object} name → label.
 */
export const iconLabels = () => ( {
	check: __( 'Check mark', 'page-builder-sandwich' ),
	circle: __( 'Circle', 'page-builder-sandwich' ),
	cross: __( 'Cross', 'page-builder-sandwich' ),
	dot: __( 'Dot', 'page-builder-sandwich' ),
	arrow: __( 'Arrow', 'page-builder-sandwich' ),
	star: __( 'Star', 'page-builder-sandwich' ),
	info: __( 'Information', 'page-builder-sandwich' ),
} );

/**
 * An icon as the page draws it: the picked SVG when set, else a built-in.
 *
 * @param {Object} props         Props.
 * @param {string} props.svg     Picked SVG ('' for none).
 * @param {string} props.builtin Built-in name.
 * @return {Element|null} Icon.
 */
export function Icon( { svg = '', builtin = '' } ) {
	const clean = svg ? sanitizeSvg( svg ) : '';
	if ( clean ) {
		return (
			<span
				className={ cls( 'kit-icon' ) }
				aria-hidden="true"
				// Sanitised by sanitizeSvg() (the twin of Core\Svg::sanitize).
				dangerouslySetInnerHTML={ { __html: clean } }
			/>
		);
	}
	if ( ! ICONS[ builtin ] ) {
		return null;
	}
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="1em"
			height="1em"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			{ ICONS[ builtin ] }
		</svg>
	);
}

/**
 * The accent colour select.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Label.
 * @param {string}   props.value    Value.
 * @param {Function} props.onChange Change handler.
 * @return {Element} Control.
 */
export function AccentControl( { label, value, onChange } ) {
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label || __( 'Icon colour', 'page-builder-sandwich' ) }
			value={ value }
			options={ [
				{
					value: 'text',
					label: __( 'Same as the text', 'page-builder-sandwich' ),
				},
				{
					value: 'primary',
					label: __( 'Theme primary', 'page-builder-sandwich' ),
				},
				{
					value: 'secondary',
					label: __( 'Theme secondary', 'page-builder-sandwich' ),
				},
				{
					value: 'contrast',
					label: __( 'Theme contrast', 'page-builder-sandwich' ),
				},
			] }
			onChange={ onChange }
		/>
	);
}

/**
 * A heading level select (2–6).
 *
 * @param {Object}   props          Props.
 * @param {number}   props.value    Level.
 * @param {Function} props.onChange Change handler.
 * @param {string}   props.label    Label.
 * @return {Element} Control.
 */
export function LevelControl( { value, onChange, label } ) {
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label || __( 'Heading level', 'page-builder-sandwich' ) }
			value={ String( value ) }
			options={ [ 2, 3, 4, 5, 6 ].map( ( n ) => ( {
				value: String( n ),
				label: `H${ n }`,
			} ) ) }
			onChange={ ( v ) => onChange( Number( v ) ) }
			help={ __(
				'Keep headings in order down the page: a heading one level below the section it sits in.',
				'page-builder-sandwich'
			) }
		/>
	);
}

/**
 * A picked icon as a LIST ITEM stores it. The keys end in `icon` and `id` because Tranzly's
 * content contract (F6) skips values under such keys, so the SVG markup and the picker
 * reference are never sent for translation while the item's text is.
 *
 * @param {{svg:string,iconRef:string}} picked What IconButton or brandIcon() returned.
 * @return {{icon:string,iconId:string}} The item fields.
 */
export function itemIcon( picked ) {
	return { icon: picked?.svg || '', iconId: picked?.iconRef || '' };
}

/**
 * "Choose icon" for one item or block: opens the icon picker; also offers "Use the default".
 *
 * @param {Object}   props          Props.
 * @param {string}   props.svg      Current SVG.
 * @param {string}   props.iconRef  Current picker reference.
 * @param {string}   props.label    Button label.
 * @param {Function} props.onChange Receives { svg, iconRef }.
 * @return {Element} Control.
 */
export function IconButton( { svg, iconRef, label, onChange } ) {
	const [ open, setOpen ] = useState( false );
	return (
		<>
			<Button
				variant="secondary"
				size="compact"
				onClick={ () => setOpen( true ) }
			>
				{ label || __( 'Choose icon', 'page-builder-sandwich' ) }
			</Button>
			{ svg && (
				<Button
					variant="tertiary"
					size="compact"
					onClick={ () => onChange( { svg: '', iconRef: '' } ) }
				>
					{ __( 'Use the default icon', 'page-builder-sandwich' ) }
				</Button>
			) }
			{ open && (
				<Modal
					title={ __( 'Choose an icon', 'page-builder-sandwich' ) }
					onRequestClose={ () => setOpen( false ) }
					size="large"
				>
					<IconPicker
						value={ iconRef }
						onSelect={ ( picked ) => {
							onChange( {
								svg: picked.svg,
								iconRef: picked.ref,
							} );
							setOpen( false );
						} }
					/>
				</Modal>
			) }
		</>
	);
}

/**
 * Immutable list edits.
 */
export const listOps = {
	update: ( list, i, patch ) =>
		list.map( ( item, j ) => ( j === i ? { ...item, ...patch } : item ) ),
	remove: ( list, i ) => list.filter( ( item, j ) => j !== i ),
	move: ( list, i, by ) => {
		const j = i + by;
		if ( j < 0 || j >= list.length ) {
			return list;
		}
		const next = [ ...list ];
		[ next[ i ], next[ j ] ] = [ next[ j ], next[ i ] ];
		return next;
	},
	add: ( list, item, at = list.length ) => [
		...list.slice( 0, at ),
		item,
		...list.slice( at ),
	],
};

/**
 * Move up / move down / remove buttons for one item (keyboard reachable, labelled).
 *
 * @param {Object}   props          Props.
 * @param {number}   props.index    Item index.
 * @param {number}   props.count    Items.
 * @param {Function} props.onMove   Receives -1 or 1.
 * @param {Function} props.onRemove Remove.
 * @param {string}   props.name     Item name for labels.
 * @return {Element} Buttons.
 */
export function ItemTools( { index, count, onMove, onRemove, name } ) {
	return (
		<span className="pbsw-kit-tools">
			<Button
				size="small"
				icon="arrow-up-alt2"
				label={ `${ __( 'Move up', 'page-builder-sandwich' ) }: ${ name }` }
				disabled={ index === 0 }
				onClick={ () => onMove( -1 ) }
			/>
			<Button
				size="small"
				icon="arrow-down-alt2"
				label={ `${ __( 'Move down', 'page-builder-sandwich' ) }: ${ name }` }
				disabled={ index === count - 1 }
				onClick={ () => onMove( 1 ) }
			/>
			<Button
				size="small"
				icon="trash"
				isDestructive
				label={ `${ __( 'Remove', 'page-builder-sandwich' ) }: ${ name }` }
				onClick={ onRemove }
			/>
		</span>
	);
}

/**
 * The same visually-hidden text the PHP prints (Kit::sr).
 *
 * @param {Object} props          Props.
 * @param {*}      props.children Text.
 * @return {Element} Span.
 */
export function Sr( { children } ) {
	return <span className={ cls( 'sr' ) }>{ children }</span>;
}
