/**
 * Wrap a block dropped at the top level of a page (Settings > "Wrap a block dropped at the top
 * level"): `row` puts it in a Row with one Column, `container` in a Container, `none` (the
 * default) leaves it alone. Only ONE block that was never in the post before is wrapped, so loading
 * a post, moving a block, undo and redo, the code editor, a pasted run of blocks and a pattern of
 * several blocks never wrap anything. A layout block (a Row, Container, Section, Group, Columns, a
 * synced pattern) and the default text block (typing, Enter) are never wrapped. The wrap is not an
 * undo step of its own: one undo removes the dropped block.
 *
 * A transform or a split is not a drop, though Gutenberg gives its result a NEW client id
 * (createBlock -> uuid): typing "## ", "- " or "> ", a toolbar transform and Enter in a heading
 * all either REPLACE a block that had content or CHANGE a top-level block in the same step, and
 * neither ever wraps. Only the inserter's own replacement of an empty default block (inserting at
 * an empty paragraph) still counts as an insertion.
 */
import { select, dispatch, subscribe } from '@wordpress/data';
import {
	createBlock,
	cloneBlock,
	getDefaultBlockName,
	isUnmodifiedDefaultBlock,
} from '@wordpress/blocks';
import { store as blockEditorStore } from '@wordpress/block-editor';

/** The choices the setting stores. */
export const DROP_WRAPS = [ 'none', 'row', 'container' ];

/** Blocks that are already a layout: never wrapped. */
export const LAYOUT_BLOCKS = new Set( [
	'pbs/row',
	'pbs/column',
	'pbs/container',
	'pbs/section',
	'core/group',
	'core/columns',
	'core/column',
	'core/cover',
	'core/block',
	'core/template-part',
	'core/post-content',
	'core/pattern',
] );

/**
 * Every client id in a block tree.
 *
 * @param {Array} blocks Blocks.
 * @param {Set}   into   Collects the ids.
 * @return {Set} The same set.
 */
export function collectIds( blocks, into = new Set() ) {
	for ( const b of blocks || [] ) {
		into.add( b.clientId );
		collectIds( b.innerBlocks, into );
	}
	return into;
}

/**
 * The top-level block to wrap now, if any: exactly one new (never seen) top-level block, not a
 * layout block and not the default text block. Several new ones at once are a paste, a pattern or
 * the whole post re-read (code editor, a restored revision): none of them is wrapped.
 *
 * With `previous` (the top-level blocks before this change) a transform or a split is refused:
 * the change removed a top-level block that was not an empty default block, or changed a
 * top-level block that is still there (a split keeps the head's id and edits it).
 *
 * @param {Array}                      roots       The top-level blocks.
 * @param {Set}                        known       Every client id seen before.
 * @param {string}                     mode        none, row or container.
 * @param {string}                     defaultName The default block type (typing).
 * @param {Array}                      previous    The top-level blocks before this change (null: not known).
 * @param {(block: Object) => boolean} isEmpty     Is a block an empty, untouched default block.
 * @return {Array} Zero or one block.
 */
export function blocksToWrap(
	roots,
	known,
	mode,
	defaultName = 'core/paragraph',
	previous = null,
	isEmpty = isUnmodifiedDefaultBlock
) {
	if ( mode !== 'row' && mode !== 'container' ) {
		return [];
	}
	const fresh = ( roots || [] ).filter( ( b ) => ! known.has( b.clientId ) );
	if ( fresh.length !== 1 ) {
		return [];
	}
	if ( previous && editedInPlace( roots, previous, isEmpty ) ) {
		return [];
	}
	const [ block ] = fresh;
	return LAYOUT_BLOCKS.has( block.name ) || block.name === defaultName
		? []
		: [ block ];
}

/**
 * Did this change transform or split a block rather than insert one: a top-level block that had
 * content is gone, or a top-level block that is still there is a different object (edited).
 *
 * @param {Array}                      roots    The top-level blocks now.
 * @param {Array}                      previous The top-level blocks before.
 * @param {(block: Object) => boolean} isEmpty  Is a block an empty, untouched default block.
 * @return {boolean} True for a transform or a split.
 */
export function editedInPlace( roots, previous, isEmpty ) {
	const now = new Map( ( roots || [] ).map( ( b ) => [ b.clientId, b ] ) );
	return ( previous || [] ).some( ( b ) => {
		const still = now.get( b.clientId );
		if ( ! still ) {
			return ! isEmpty( b );
		}
		return still !== b;
	} );
}

/**
 * The wrapper for one block.
 *
 * @param {Object}   block  The dropped block.
 * @param {string}   mode   row or container.
 * @param {Function} create createBlock.
 * @param {Function} clone  cloneBlock.
 * @return {{wrapper: Object, inner: Object}} The wrapper and the block inside it.
 */
export function wrapperFor(
	block,
	mode,
	create = createBlock,
	clone = cloneBlock
) {
	const inner = clone( block );
	if ( mode === 'row' ) {
		return {
			wrapper: create( 'pbs/row', {}, [
				create( 'pbs/column', {}, [ inner ] ),
			] ),
			inner,
		};
	}
	return { wrapper: create( 'pbs/container', {}, [ inner ] ), inner };
}

/**
 * Start watching the block editor. Does nothing in the site editor (a template's top level is
 * its header, content and footer, never a page).
 *
 * @param {Function} getMode Reads the setting (default: the editor data, `window.pbswDesign`).
 * @return {Function} Unsubscribe.
 */
export function watchDrops(
	getMode = () => window.pbswDesign?.dropWrap || 'none'
) {
	let known = null;
	let last = null;
	let busy = false;
	return subscribe( () => {
		if ( busy ) {
			return;
		}
		const be = select( blockEditorStore );
		if ( ! be ) {
			return;
		}
		const roots = be.getBlocks();
		if ( roots === last ) {
			return;
		}
		const previous = last;
		last = roots;
		const editor = select( 'core/editor' );
		const ready = editor?.__unstableIsEditorReady
			? editor.__unstableIsEditorReady()
			: !! editor;
		const type = editor?.getCurrentPostType?.();
		if (
			null === known ||
			! ready ||
			type === 'wp_template' ||
			type === 'wp_template_part'
		) {
			// Until the editor is ready the blocks are the post being loaded: nothing was dropped.
			known = collectIds( roots );
			return;
		}
		const todo = blocksToWrap(
			roots,
			known,
			getMode(),
			getDefaultBlockName?.() || 'core/paragraph',
			previous
		);
		collectIds( roots, known );
		if ( ! todo.length ) {
			return;
		}
		busy = true;
		try {
			const actions = dispatch( blockEditorStore );
			let selected = null;
			for ( const block of todo ) {
				const { wrapper, inner } = wrapperFor( block, getMode() );
				collectIds( [ wrapper ], known );
				actions.__unstableMarkNextChangeAsNotPersistent?.();
				actions.replaceBlock( block.clientId, wrapper );
				selected = inner.clientId;
			}
			if ( selected ) {
				actions.selectBlock( selected );
			}
		} finally {
			busy = false;
			last = select( blockEditorStore ).getBlocks();
			collectIds( last, known );
		}
	}, blockEditorStore );
}
