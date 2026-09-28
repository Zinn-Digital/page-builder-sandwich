/**
 * A virtualised, keyboard-navigable grid of icons (a WAI-ARIA listbox laid out as a grid).
 *
 * - Only the rows in view (plus a small overscan) are in the DOM, so a 4,000-icon set costs the
 *   same as a 40-icon one.
 * - Keys: arrows move one cell (Left/Right swap in RTL, so "forward" follows reading order),
 *   Up/Down one row, PageUp/PageDown one screen, Home/End first/last, Enter/Space picks.
 * - Focus stays on the listbox; the active cell is announced through aria-activedescendant.
 */
import { __ } from '@wordpress/i18n';
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';

export const CELL = 48;
export const HEIGHT = 336;
const OVERSCAN = 2;

/**
 * Whether the editor is right-to-left.
 *
 * @param {Element|null} el Element to read the direction from.
 * @return {boolean} True in RTL.
 */
export function isRtl( el ) {
	if ( el && typeof window !== 'undefined' && window.getComputedStyle ) {
		const dir = window.getComputedStyle( el ).direction;
		if ( dir ) {
			return dir === 'rtl';
		}
	}
	return typeof document !== 'undefined' && document.dir === 'rtl';
}

/**
 * The index a key moves to, or null when the key is not a navigation key.
 *
 * @param {string}  key     KeyboardEvent.key.
 * @param {number}  index   Current index.
 * @param {number}  count   Number of items.
 * @param {number}  columns Columns per row.
 * @param {boolean} rtl     Right-to-left.
 * @return {number|null} New index.
 */
export function moveIndex( key, index, count, columns, rtl ) {
	if ( count < 1 ) {
		return null;
	}
	const page = columns * Math.max( 1, Math.floor( HEIGHT / CELL ) - 1 );
	const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
	const back = rtl ? 'ArrowRight' : 'ArrowLeft';
	let next;
	switch ( key ) {
		case forward:
			next = index + 1;
			break;
		case back:
			next = index - 1;
			break;
		case 'ArrowDown':
			next = index + columns;
			break;
		case 'ArrowUp':
			next = index - columns;
			break;
		case 'PageDown':
			next = index + page;
			break;
		case 'PageUp':
			next = index - page;
			break;
		case 'Home':
			next = 0;
			break;
		case 'End':
			next = count - 1;
			break;
		default:
			return null;
	}
	return Math.min( count - 1, Math.max( 0, next ) );
}

/**
 * The slice of rows to render for a scroll position.
 *
 * @param {number} scrollTop Scroll offset.
 * @param {number} rows      Total rows.
 * @return {{first:number,last:number}} Inclusive row range.
 */
export function visibleRows( scrollTop, rows ) {
	const first = Math.max( 0, Math.floor( scrollTop / CELL ) - OVERSCAN );
	const last = Math.min(
		rows - 1,
		Math.ceil( ( scrollTop + HEIGHT ) / CELL ) + OVERSCAN
	);
	return { first, last };
}

export default function IconGrid( {
	items,
	label,
	selectedKey,
	onPick,
	idPrefix,
	renderIcon,
} ) {
	const ref = useRef( null );
	const [ columns, setColumns ] = useState( 8 );
	const [ scrollTop, setScrollTop ] = useState( 0 );
	const [ active, setActive ] = useState( 0 );

	useLayoutEffect( () => {
		const el = ref.current;
		if ( ! el ) {
			return undefined;
		}
		const measure = () => {
			const width = el.clientWidth || CELL * 8;
			setColumns( Math.max( 1, Math.floor( width / CELL ) ) );
		};
		measure();
		if ( typeof window !== 'undefined' && window.ResizeObserver ) {
			const ro = new window.ResizeObserver( measure );
			ro.observe( el );
			return () => ro.disconnect();
		}
		return undefined;
	}, [] );

	// A new result list starts at its first icon.
	useEffect( () => {
		setActive( 0 );
		setScrollTop( 0 );
		if ( ref.current ) {
			ref.current.scrollTop = 0;
		}
	}, [ items ] );

	const rows = Math.ceil( items.length / columns );
	const { first, last } = visibleRows( scrollTop, rows );

	const reveal = useCallback(
		( index ) => {
			const el = ref.current;
			if ( ! el ) {
				return;
			}
			const top = Math.floor( index / columns ) * CELL;
			if ( top < el.scrollTop ) {
				el.scrollTop = top;
			} else if ( top + CELL > el.scrollTop + HEIGHT ) {
				el.scrollTop = top + CELL - HEIGHT;
			}
			setScrollTop( el.scrollTop );
		},
		[ columns ]
	);

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' || event.key === ' ' ) {
			if ( items[ active ] ) {
				event.preventDefault();
				onPick( items[ active ] );
			}
			return;
		}
		const next = moveIndex(
			event.key,
			active,
			items.length,
			columns,
			isRtl( ref.current )
		);
		if ( next === null ) {
			return;
		}
		event.preventDefault();
		setActive( next );
		reveal( next );
	};

	const cells = [];
	for ( let r = first; r <= last; r++ ) {
		for ( let c = 0; c < columns; c++ ) {
			const index = r * columns + c;
			const item = items[ index ];
			if ( ! item ) {
				break;
			}
			cells.push(
				// The WAI-ARIA listbox pattern with aria-activedescendant: the LISTBOX holds focus and
				// handles keys and clicks (delegated below); an option is never focused itself.
				<div
					key={ item.key }
					data-index={ index }
					id={ `${ idPrefix }-${ index }` }
					role="option"
					aria-selected={ item.key === selectedKey }
					aria-label={ item.name }
					title={ item.name }
					className={
						'pbsw-icon-grid__cell' +
						( index === active ? ' is-active' : '' ) +
						( item.key === selectedKey ? ' is-selected' : '' )
					}
					style={ {
						insetBlockStart: r * CELL,
						insetInlineStart: c * CELL,
						inlineSize: CELL,
						blockSize: CELL,
					} }
					// Sanitised by sanitizeSvg() (the twin of Core\Svg::sanitize).
					dangerouslySetInnerHTML={ { __html: renderIcon( item ) } }
				/>
			);
		}
	}

	return (
		<div
			ref={ ref }
			className="pbsw-icon-grid"
			role="listbox"
			tabIndex={ 0 }
			aria-label={ label }
			aria-activedescendant={
				items.length ? `${ idPrefix }-${ active }` : undefined
			}
			onKeyDown={ onKeyDown }
			onMouseDown={ ( e ) => {
				// A click on an option must not move focus off the listbox.
				if ( e.target.closest( '[role="option"]' ) ) {
					e.preventDefault();
				}
			} }
			onClick={ ( e ) => {
				const cell = e.target.closest( '[role="option"]' );
				if ( ! cell ) {
					return;
				}
				const index = Number( cell.dataset.index );
				setActive( index );
				onPick( items[ index ] );
			} }
			onScroll={ ( e ) => setScrollTop( e.currentTarget.scrollTop ) }
			style={ { blockSize: HEIGHT } }
		>
			{ items.length === 0 ? (
				<p className="pbsw-icon-grid__empty">
					{ __( 'No icons match.', 'page-builder-sandwich' ) }
				</p>
			) : (
				<div
					className="pbsw-icon-grid__inner"
					style={ { blockSize: rows * CELL } }
				>
					{ cells }
				</div>
			) }
		</div>
	);
}
