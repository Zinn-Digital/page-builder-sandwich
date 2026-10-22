/**
 * The editor half of the Pro-block fallback (lane R35, owner decision 2026-10-18).
 *
 * When Pro is not running (a card-free trial ended unpaid), a placed Pro block reaches the
 * editor as WordPress's "missing block". Instead of WordPress's generic notice, each one says
 * "Upgrade to keep it, or convert to the free version", with one button converting every Pro
 * block on the page. Nothing changes until the user presses a button (and an undo restores it),
 * so paying restores every Pro block as it was.
 *
 * The free version here is the one visitors already see (includes/blocks/class-pro-fallback.php):
 * the saved markup turned into core blocks, the first variant or pane, a core equivalent, or
 * nothing when there is no free version.
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Warning } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { createBlock, pasteHandler } from '@wordpress/blocks';
import { useDispatch, select, dispatch } from '@wordpress/data';
import { parse } from '@wordpress/block-serialization-default-parser';

const data = () => window.pbswProFallback || null;

/**
 * The strategy for a block name, or null when it is not a locked Pro block.
 *
 * @param {string} name Block name.
 * @return {string|null} Strategy.
 */
export function strategyOf( name ) {
	const d = data();
	if ( ! d || typeof name !== 'string' || ! name.startsWith( 'pbs/' ) ) {
		return null;
	}
	return d.blocks[ name.slice( 4 ) ] || null;
}

/**
 * A raw (parsed) block's free HTML, with every nested Pro block reduced the same way.
 *
 * @param {Object} raw Block from the default parser.
 * @return {string} HTML.
 */
export function freeHtml( raw ) {
	const strategy = strategyOf( raw.blockName );
	if (
		strategy === 'hide' ||
		( strategy && strategy !== 'saved' && strategy !== 'first' )
	) {
		return '';
	}
	if ( strategy === 'first' ) {
		return raw.innerBlocks.length ? freeHtml( raw.innerBlocks[ 0 ] ) : '';
	}
	let child = 0;
	return raw.innerContent
		.map( ( part ) =>
			part === null ? freeHtml( raw.innerBlocks[ child++ ] ) : part
		)
		.join( '' );
}

/**
 * The free blocks that replace a locked Pro block (an empty list removes it).
 *
 * @param {Object} attributes The missing block's attributes.
 * @return {Array} Blocks.
 */
export function freeBlocks( attributes ) {
	const strategy = strategyOf( attributes.originalName );
	const raw = parse( attributes.originalContent || '' )[ 0 ] || {
		blockName: attributes.originalName,
		attrs: {},
		innerBlocks: [],
		innerContent: [ attributes.originalUndelimitedContent || '' ],
	};
	const attrs = raw.attrs || {};
	switch ( strategy ) {
		case 'hide':
			return [];
		case 'search':
			return [
				createBlock( 'core/search', {
					...( attrs.label ? { label: attrs.label } : {} ),
					...( attrs.buttonText
						? { buttonText: attrs.buttonText }
						: {} ),
				} ),
			];
		case 'login':
			return [
				createBlock( 'core/loginout', { displayLoginAsForm: true } ),
			];
		case 'posts':
			return [
				createBlock( 'core/latest-posts', {
					postsToShow: Number( attrs.perPage ) || 6,
				} ),
			];
	}
	const html = freeHtml( raw ).trim();
	return html ? pasteHandler( { HTML: html, mode: 'BLOCKS' } ) : [];
}

/**
 * Convert every locked Pro block in the post (top level and nested).
 */
export function convertAll() {
	const editor = select( 'core/block-editor' );
	const { replaceBlocks, removeBlock } = dispatch( 'core/block-editor' );
	editor.getClientIdsWithDescendants().forEach( ( clientId ) => {
		const block = editor.getBlock( clientId );
		if (
			! block ||
			block.name !== 'core/missing' ||
			! strategyOf( block.attributes.originalName )
		) {
			return;
		}
		const blocks = freeBlocks( block.attributes );
		if ( blocks.length ) {
			replaceBlocks( clientId, blocks );
		} else {
			removeBlock( clientId, false );
		}
	} );
}

function LockedProBlock( { clientId, attributes } ) {
	const { replaceBlocks, removeBlock } = useDispatch( 'core/block-editor' );
	const strategy = strategyOf( attributes.originalName );
	const url = data()?.upgradeUrl || '';
	const convert = () => {
		const blocks = freeBlocks( attributes );
		if ( blocks.length ) {
			replaceBlocks( clientId, blocks );
		} else {
			removeBlock( clientId );
		}
	};
	const actions = [];
	if ( url ) {
		actions.push(
			<Button
				key="upgrade"
				variant="primary"
				href={ url }
				target="_blank"
			>
				{ __( 'Upgrade to keep it', 'page-builder-sandwich' ) }
			</Button>
		);
	}
	actions.push(
		<Button key="convert" variant="secondary" onClick={ convert }>
			{ strategy === 'hide'
				? __( 'Remove it (no free version)', 'page-builder-sandwich' )
				: __( 'Convert to the free version', 'page-builder-sandwich' ) }
		</Button>,
		<Button key="all" variant="tertiary" onClick={ convertAll }>
			{ __( 'Convert all Pro blocks', 'page-builder-sandwich' ) }
		</Button>
	);
	return (
		<Warning actions={ actions }>
			{ __(
				'This is a Pro block and Pro is not active on this site, so visitors see its free version, or nothing when it has none. Upgrade to keep it, or convert it to the free version.',
				'page-builder-sandwich'
			) }
		</Warning>
	);
}

addFilter(
	'editor.BlockEdit',
	'pbsw/pro-fallback',
	createHigherOrderComponent(
		( BlockEdit ) => ( props ) =>
			props.name === 'core/missing' &&
			strategyOf( props.attributes.originalName ) ? (
				<LockedProBlock { ...props } />
			) : (
				<BlockEdit { ...props } />
			),
		'withPbswProFallback'
	)
);
