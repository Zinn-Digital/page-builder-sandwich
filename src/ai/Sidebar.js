/**
 * The AI sidebar (block editor and Sandwich Studio): a section from a sentence (pbs-ai2), SEO title
 * and description and image alt text (pbs-ai8); the Pro panels join through `pbs.ai.panels`.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useMemo } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { useSelect, useDispatch } from '@wordpress/data';
import { parse } from '@wordpress/blocks';
import { store as blockEditorStore } from '@wordpress/block-editor';
import {
	PanelBody,
	TextareaControl,
	TextControl,
	Button,
	Spinner,
	Notice,
	Flex,
} from '@wordpress/components';

import { ai, status, AiError, NotReady } from './api';

/**
 * Busy/error state for one panel.
 *
 * @return {Object} { busy, error, setError, run }.
 */
export function useAiCall() {
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const run = ( promise, then ) => {
		setBusy( true );
		setError( null );
		return promise
			.then( then )
			.catch( setError )
			.finally( () => setBusy( false ) );
	};
	return { busy, error, setError, run };
}

/**
 * Insert blocks after the selected block's top-level section, or at the end.
 *
 * @return {Function} ( blocks ) => void.
 */
export function useInsert() {
	const { insertBlocks } = useDispatch( blockEditorStore );
	const { selected, rootOf, indexOf, count } = useSelect( ( select ) => {
		const s = select( blockEditorStore );
		return {
			selected: s.getSelectedBlockClientId(),
			rootOf: s.getBlockHierarchyRootClientId,
			indexOf: s.getBlockIndex,
			count: s.getBlockCount(),
		};
	}, [] );
	return ( blocks ) => {
		const top = selected ? rootOf( selected ) : null;
		insertBlocks( blocks, top ? indexOf( top ) + 1 : count );
	};
}

/**
 * pbs-ai2.
 *
 * @return {Element} Panel.
 */
function SectionPanel() {
	const [ description, setDescription ] = useState( '' );
	const { busy, error, setError, run } = useAiCall();
	const insert = useInsert();
	return (
		<PanelBody title={ __( 'Add a section', 'page-builder-sandwich' ) }>
			<AiError error={ error } onDismiss={ () => setError( null ) } />
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Describe the section', 'page-builder-sandwich' ) }
				help={ __(
					'For example: "pricing with three plans for a dog-walking service". It is built from the section library in your brand style.',
					'page-builder-sandwich'
				) }
				value={ description }
				onChange={ setDescription }
				rows={ 3 }
			/>
			<Flex justify="flex-end">
				{ busy && <Spinner /> }
				<Button
					variant="primary"
					disabled={ busy || description.trim().length < 5 }
					onClick={ () =>
						run( ai( 'section', { description } ), ( res ) => {
							insert( parse( res.content ) );
							setDescription( '' );
						} )
					}
				>
					{ __( 'Generate section', 'page-builder-sandwich' ) }
				</Button>
			</Flex>
		</PanelBody>
	);
}

/**
 * pbs-ai8: SEO title and description, and alt text.
 *
 * @param {Object} props
 * @param {number} props.postId Post.
 * @return {Element} Panel.
 */
function SeoPanel( { postId } ) {
	const [ keyword, setKeyword ] = useState( '' );
	const [ meta, setMeta ] = useState( null );
	const [ done, setDone ] = useState( '' );
	const { busy, error, setError, run } = useAiCall();
	const { updateBlockAttributes } = useDispatch( blockEditorStore );
	// A string, so the selector returns an equal value while nothing changed.
	const imageList = useSelect( ( select ) => {
		const out = [];
		const walk = ( blocks ) =>
			blocks.forEach( ( b ) => {
				if ( b.name === 'core/image' && b.attributes.id ) {
					out.push( [
						b.clientId,
						b.attributes.id,
						b.attributes.alt || '',
					] );
				}
				walk( b.innerBlocks || [] );
			} );
		walk( select( blockEditorStore ).getBlocks() );
		return JSON.stringify( out );
	}, [] );
	const images = useMemo(
		() =>
			JSON.parse( imageList ).map( ( [ clientId, id, alt ] ) => ( {
				clientId,
				attributes: { id, alt },
			} ) ),
		[ imageList ]
	);
	const plugins = status().seo_plugins || [];
	if ( ! postId ) {
		return null;
	}
	return (
		<PanelBody
			title={ __( 'SEO and alt text', 'page-builder-sandwich' ) }
			initialOpen={ false }
		>
			<AiError error={ error } onDismiss={ () => setError( null ) } />
			{ done && (
				<Notice status="success" onRemove={ () => setDone( '' ) }>
					{ done }
				</Notice>
			) }
			{ plugins.length === 0 ? (
				<p>
					{ __(
						'Install Yoast SEO, Rank Math, SEOPress or All in One SEO to save a meta title and description.',
						'page-builder-sandwich'
					) }
				</p>
			) : (
				<>
					<TextControl
						__nextHasNoMarginBottom
						label={ __(
							'Focus keyword (optional)',
							'page-builder-sandwich'
						) }
						value={ keyword }
						onChange={ setKeyword }
					/>
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ () =>
							run(
								ai(
									'seo-meta',
									{ post_id: postId, keyword },
									'GET'
								),
								setMeta
							)
						}
					>
						{ __(
							'Suggest title and description',
							'page-builder-sandwich'
						) }
					</Button>
					{ meta && (
						<>
							<TextControl
								__nextHasNoMarginBottom
								label={ __(
									'Meta title',
									'page-builder-sandwich'
								) }
								value={ meta.title }
								onChange={ ( title ) =>
									setMeta( { ...meta, title } )
								}
							/>
							<TextareaControl
								__nextHasNoMarginBottom
								label={ __(
									'Meta description',
									'page-builder-sandwich'
								) }
								value={ meta.description }
								onChange={ ( description ) =>
									setMeta( { ...meta, description } )
								}
							/>
							<Button
								variant="primary"
								disabled={ busy }
								onClick={ () =>
									run(
										ai(
											'seo-meta',
											{
												post_id: postId,
												title: meta.title,
												description: meta.description,
											},
											'PUT'
										),
										( res ) =>
											setDone(
												sprintf(
													/* translators: %s: SEO plugin keys, comma separated. */
													__(
														'Saved to: %s',
														'page-builder-sandwich'
													),
													res.written_to.join( ', ' )
												)
											)
									)
								}
							>
								{ __(
									'Save to my SEO plugin',
									'page-builder-sandwich'
								) }
							</Button>
						</>
					) }
				</>
			) }
			<hr />
			<Button
				variant="secondary"
				disabled={ busy || images.length === 0 }
				onClick={ () =>
					run(
						ai( 'alt-text', {
							post_id: postId,
							only_missing: true,
						} ).then( ( res ) => {
							// Keep the editor's copy in step, or the next save would write the
							// old (empty) alt back into the page.
							res.written.forEach( ( w ) =>
								images
									.filter(
										( b ) =>
											b.attributes.id ===
												w.attachment_id &&
											! b.attributes.alt
									)
									.forEach( ( b ) =>
										updateBlockAttributes( b.clientId, {
											alt: w.alt,
										} )
									)
							);
							return res;
						} ),
						( res ) =>
							setDone(
								sprintf(
									/* translators: %d: number of images. */
									__(
										'Alt text written for %d image(s).',
										'page-builder-sandwich'
									),
									res.written.length
								)
							)
					)
				}
			>
				{ __( 'Write missing alt text', 'page-builder-sandwich' ) }
			</Button>
			{ images.length === 0 && (
				<p>
					{ __(
						'Images saved on this page appear here once the page is saved.',
						'page-builder-sandwich'
					) }
				</p>
			) }
			{ busy && <Spinner /> }
		</PanelBody>
	);
}

/**
 * The sidebar body.
 *
 * @param {Object} props
 * @param {number} props.postId Post being edited.
 * @return {Element} Sidebar.
 */
export default function Sidebar( { postId } ) {
	if ( ! status().ready ) {
		return <NotReady />;
	}
	const panels = applyFilters( 'pbs.ai.panels', [] );
	return (
		<>
			<SectionPanel />
			<SeoPanel postId={ postId } />
			{ panels.map( ( { name, Component } ) => (
				<Component key={ name } postId={ postId } />
			) ) }
		</>
	);
}
