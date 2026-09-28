/**
 * The Accessibility checker panel (pbs-a1): runs the rules on the live editor canvas as the page
 * is built, lists what it finds, selects the block on click and applies a fix where one exists.
 * The same component is the block editor sidebar and the Studio panel.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { getBlockType } from '@wordpress/blocks';
import { store as blockEditorStore } from '@wordpress/block-editor';
import {
	Button,
	CheckboxControl,
	Notice,
	TextControl,
} from '@wordpress/components';
import { runChecks } from './checks';
import { fixAttributes } from './fixes';

/**
 * The document the blocks are drawn in: the canvas iframe when the editor uses one.
 *
 * @return {Document} Canvas document.
 */
export function canvasDocument() {
	const frame = document.querySelector( 'iframe[name="editor-canvas"]' );
	return frame?.contentDocument || document;
}

/**
 * One finding.
 *
 * @param {Object}   props
 * @param {Object}   props.item     Finding.
 * @param {Function} props.onApply  `( attributes ) => void`.
 * @param {Function} props.onSelect Select the block.
 * @param {Object}   props.block    The block.
 * @param {Array}    props.palette  Palette.
 */
function Finding( { item, onApply, onSelect, block, palette } ) {
	const [ alt, setAlt ] = useState( '' );
	const type = block ? getBlockType( block.name ) : null;
	const auto =
		item.fix &&
		[ 'heading-level', 'contrast' ].includes( item.fix.type ) &&
		block
			? fixAttributes( item.fix, block, type, { palette } )
			: null;
	return (
		<li className={ `pbsw-a11y__item is-${ item.severity }` }>
			<p className="pbsw-a11y__msg">
				<span className="pbsw-a11y__sev">
					{ item.severity === 'error'
						? __( 'Problem', 'page-builder-sandwich' )
						: __( 'Check', 'page-builder-sandwich' ) }
				</span>{ ' ' }
				{ item.message }
			</p>
			<div className="pbsw-a11y__actions">
				<Button variant="secondary" size="compact" onClick={ onSelect }>
					{ __( 'Show block', 'page-builder-sandwich' ) }
				</Button>
				{ auto && (
					<Button
						variant="primary"
						size="compact"
						onClick={ () => onApply( auto ) }
					>
						{ item.fix.type === 'contrast'
							? __(
									'Use a readable theme colour',
									'page-builder-sandwich'
								)
							: sprintf(
									/* translators: %d: heading level. */
									__(
										'Make it H%d',
										'page-builder-sandwich'
									),
									item.fix.level
								) }
					</Button>
				) }
			</div>
			{ item.fix?.type === 'image-alt' && block && (
				<div className="pbsw-a11y__alt">
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Alt text', 'page-builder-sandwich' ) }
						value={ alt }
						onChange={ setAlt }
					/>
					<Button
						variant="primary"
						size="compact"
						disabled={ ! alt.trim() }
						onClick={ () =>
							onApply(
								fixAttributes( item.fix, block, type, { alt } )
							)
						}
					>
						{ __( 'Save alt text', 'page-builder-sandwich' ) }
					</Button>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __(
							'This image is decorative',
							'page-builder-sandwich'
						) }
						checked={ false }
						onChange={ () =>
							onApply(
								fixAttributes( item.fix, block, type, {
									decorative: true,
								} )
							)
						}
					/>
				</div>
			) }
		</li>
	);
}

export default function Checker() {
	const { blocks, palette, getBlock } = useSelect( ( select ) => {
		const s = select( blockEditorStore );
		return {
			blocks: s.getBlocks(),
			palette: s.getSettings()?.colors || [],
			getBlock: s.getBlock,
		};
	}, [] );
	const { selectBlock, updateBlockAttributes } =
		useDispatch( blockEditorStore );
	const [ findings, setFindings ] = useState( null );
	const timer = useRef( null );

	const run = useCallback( () => {
		const doc = canvasDocument();
		const root = doc.querySelector( '.is-root-container' ) || doc.body;
		const view = doc.defaultView || window;
		setFindings(
			runChecks( {
				blocks,
				root,
				getStyle: ( el ) => view.getComputedStyle( el ),
			} )
		);
	}, [ blocks ] );

	// Re-check as the page changes, after the canvas has painted it.
	useEffect( () => {
		window.clearTimeout( timer.current );
		timer.current = window.setTimeout( run, 600 );
		return () => window.clearTimeout( timer.current );
	}, [ run ] );

	if ( findings === null ) {
		return (
			<p className="pbsw-a11y__status">
				{ __( 'Checking…', 'page-builder-sandwich' ) }
			</p>
		);
	}
	const errors = findings.filter( ( f ) => f.severity === 'error' ).length;

	return (
		<div className="pbsw-a11y">
			<p className="pbsw-a11y__status" role="status">
				{ findings.length === 0
					? __(
							'No accessibility problems found on this page.',
							'page-builder-sandwich'
						)
					: sprintf(
							/* translators: 1: number of problems, 2: number of things to check. */
							__( '%1$s, %2$s.', 'page-builder-sandwich' ),
							sprintf(
								/* translators: %d: number of problems. */
								_n(
									'%d problem',
									'%d problems',
									errors,
									'page-builder-sandwich'
								),
								errors
							),
							sprintf(
								/* translators: %d: number of things to check. */
								_n(
									'%d thing to check',
									'%d things to check',
									findings.length - errors,
									'page-builder-sandwich'
								),
								findings.length - errors
							)
						) }
			</p>
			<Button variant="tertiary" size="compact" onClick={ run }>
				{ __( 'Check again', 'page-builder-sandwich' ) }
			</Button>
			<ul className="pbsw-a11y__list">
				{ findings.map( ( item ) => (
					<Finding
						key={ item.id }
						item={ item }
						block={ getBlock( item.clientId ) }
						palette={ palette }
						onSelect={ () => selectBlock( item.clientId ) }
						onApply={ ( attrs ) => {
							if ( attrs ) {
								updateBlockAttributes( item.clientId, attrs );
							}
						} }
					/>
				) ) }
			</ul>
			<Notice
				status="info"
				isDismissible={ false }
				className="pbsw-a11y__note"
			>
				{ __(
					'An automatic check finds many problems but not all of them. Also try the page with the keyboard alone and with a screen reader.',
					'page-builder-sandwich'
				) }
			</Notice>
		</div>
	);
}
