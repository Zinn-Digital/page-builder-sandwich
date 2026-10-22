/**
 * pbs/nav-menu editor: pick a WordPress menu; the canvas shows its items with the front end's own
 * classes (links inert). The sidebar holds every free option: layout, how submenus open, and the
 * mobile menu (when it appears, its shape, the Menu button, how it closes). "Preview the mobile
 * menu" draws the folded bar, and the open menu, right in the canvas.
 *
 * Pro adds its panels through `editor.BlockEdit` (mega menu panels, item icons, badges,
 * descriptions and visibility) and its item extras through the `pbsw.navMenu.itemLabel` filter;
 * the mega menu panels themselves (pbs/mega-panel) are inner blocks.
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
	Button,
	ExternalLink,
	Notice,
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { getBlockType } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';

import { cls } from '../shared/cls';
import Glyph from './glyph';
import { useMenu } from './data';

// Re-exported for the Pro mega panel's editor, which imported it from here before 6.36.22.
export { useMenu };

/** The Menu button's icons, in the order the sidebar offers them. */
export const TOGGLE_ICONS = [ 'menu', 'menu-alt', 'grid', 'none' ];

/**
 * The item list, as the page prints it (submenus closed; the buttons are inert).
 *
 * @param {Object}        props
 * @param {Array<Object>} props.items      Items.
 * @param {Set<number>}   props.mega       Top-level items with a mega panel.
 * @param {number}        props.depth      Depth.
 * @param {Object}        props.attributes Block attributes (for the Pro item extras).
 * @return {Element} List items.
 */
function Items( { items, mega, depth, attributes } ) {
	return items.map( ( item ) => {
		const has =
			item.children.length > 0 || ( depth === 0 && mega.has( item.id ) );
		return (
			<li
				key={ item.id }
				className={ cls(
					'nav-menu__item',
					has && 'nav-menu__item--has-sub'
				) }
			>
				<span className={ cls( 'nav-menu__link' ) }>
					{ applyFilters(
						'pbsw.navMenu.itemLabel',
						item.title,
						item,
						attributes,
						depth
					) }
				</span>
				{ has && (
					<span className={ cls( 'nav-menu__expand' ) }>
						<Glyph name="down" />
					</span>
				) }
				{ item.children.length > 0 && ! mega.has( item.id ) && (
					<ul className={ cls( 'nav-menu__sub' ) }>
						<Items
							items={ item.children }
							mega={ mega }
							depth={ depth + 1 }
							attributes={ attributes }
						/>
					</ul>
				) }
			</li>
		);
	} );
}

/**
 * The root classes, as Site\Nav::markup() prints them (the collapsed preview uses the
 * stylesheet's `--collapse-always` layout, whatever width the menu folds at).
 *
 * @param {Object}  a       Attributes.
 * @param {boolean} preview Draw the folded (mobile) menu.
 * @return {string} Classes.
 */
export function rootClasses( a, preview ) {
	return cls(
		'nav-menu',
		`nav-menu--${ a.orientation === 'vertical' ? 'vertical' : 'horizontal' }`,
		preview ? 'nav-menu--collapse-always' : 'nav-menu--collapse-never',
		`nav-menu--mobile-${ a.mobileStyle || 'drawer' }`,
		a.drawerSide === 'start' && 'nav-menu--side-start',
		`nav-menu--toggle-${ a.toggleStyle || 'outline' }`,
		a.mobileSubmenus === 'expanded' && 'nav-menu--subs-open'
	);
}

/**
 * Labelled choices for a SelectControl.
 *
 * @param {Array<Array<string>>} pairs [ value, label ] pairs.
 * @return {Array<Object>} Options.
 */
const options = ( pairs ) =>
	pairs.map( ( [ value, label ] ) => ( { value, label } ) );

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		menuId,
		orientation,
		collapse,
		breakpoint,
		openOn,
		label,
		mobileStyle,
		drawerSide,
		toggleIcon,
		toggleLabel,
		toggleShowLabel,
		toggleStyle,
		closeOnLink,
		mobileSubmenus,
		closeDelay,
		animation,
		skipLink,
		skipTarget,
	} = attributes;
	const [ preview, setPreview ] = useState( false );
	const [ previewOpen, setPreviewOpen ] = useState( false );
	const { menus, items, loading, allowed } = useMenu( menuId );
	const panels = useSelect(
		( select ) =>
			select( 'core/block-editor' )
				.getBlocks( clientId )
				.map( ( b ) => b.attributes.itemId ),
		[ clientId ]
	);
	// Mega panels are Pro: offer the appender only when that block exists here.
	const megaAvailable = !! getBlockType( 'pbs/mega-panel' );
	const blockProps = useBlockProps();
	const innerProps = useInnerBlocksProps(
		{ className: 'pbsw-nav-menu__panels' },
		{
			allowedBlocks: [ 'pbs/mega-panel' ],
			renderAppender:
				menuId && megaAvailable
					? InnerBlocks.ButtonBlockAppender
					: false,
		}
	);
	const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );
	// Every editor that shows this block runs inside wp-admin, so a relative link reaches the
	// Menus screen (a site in a sub-directory included) without touching admin-ajax.
	const menusUrl = 'nav-menus.php';
	const picker = (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ __( 'Menu', 'page-builder-sandwich' ) }
			value={ String( menuId || '' ) }
			options={ [
				{
					value: '',
					label: __( 'Choose a menu…', 'page-builder-sandwich' ),
				},
				...menus.map( ( m ) => ( {
					value: String( m.id ),
					label: m.name,
				} ) ),
			] }
			onChange={ ( value ) =>
				setAttributes( { menuId: Number( value ) || 0 } )
			}
		/>
	);
	const folds = collapse !== 'never';
	const buttonText = toggleLabel || __( 'Menu', 'page-builder-sandwich' );
	const showText = toggleIcon === 'none' || toggleShowLabel !== false;
	const iconNames = {
		menu: __( 'Three lines', 'page-builder-sandwich' ),
		'menu-alt': __( 'Three lines, short middle', 'page-builder-sandwich' ),
		grid: __( 'Grid', 'page-builder-sandwich' ),
		none: __( 'No icon, words only', 'page-builder-sandwich' ),
	};

	let canvas;
	if ( ! allowed ) {
		canvas = (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Only people who can edit the site’s menus can choose one here.',
					'page-builder-sandwich'
				) }
			</Notice>
		);
	} else if ( loading ) {
		canvas = <Spinner />;
	} else if ( ! menuId || ! items.length ) {
		canvas = (
			<Placeholder
				icon="menu"
				label={ __( 'Navigation menu', 'page-builder-sandwich' ) }
				instructions={
					menus.length
						? __(
								'Choose which WordPress menu to show.',
								'page-builder-sandwich'
							)
						: __(
								'This site has no menus yet. Create one, then choose it here.',
								'page-builder-sandwich'
							)
				}
			>
				{ menus.length > 0 && picker }
				<ExternalLink href={ menusUrl }>
					{ __( 'Edit menus', 'page-builder-sandwich' ) }
				</ExternalLink>
			</Placeholder>
		);
	} else {
		const folded = preview && folds;
		canvas = (
			<nav
				className={ rootClasses( attributes, folded ) }
				aria-label={ label || __( 'Main', 'page-builder-sandwich' ) }
				data-wp-interactive={ folded ? '' : undefined }
			>
				{ folded &&
					applyFilters(
						'pbsw.navMenu.toggle',
						<span
							className={ cls( 'nav-menu__toggle' ) }
							role="button"
							tabIndex={ 0 }
							aria-expanded={ previewOpen }
							onClick={ () => setPreviewOpen( ! previewOpen ) }
							onKeyDown={ ( e ) =>
								( e.key === 'Enter' || e.key === ' ' ) &&
								setPreviewOpen( ! previewOpen )
							}
						>
							{ toggleIcon !== 'none' && (
								<Glyph name={ toggleIcon || 'menu' } />
							) }
							<span
								className={ cls(
									'nav-menu__toggle-label',
									! showText && 'nav-menu__sr'
								) }
							>
								{ buttonText }
							</span>
						</span>,
						attributes
					) }
				<div
					className={ cls( 'nav-menu__drawer' ) }
					data-open={ folded && previewOpen ? 'true' : undefined }
				>
					{ folded && previewOpen && (
						<span
							className={ cls( 'nav-menu__close' ) }
							role="button"
							tabIndex={ 0 }
							onClick={ () => setPreviewOpen( false ) }
							onKeyDown={ ( e ) =>
								( e.key === 'Enter' || e.key === ' ' ) &&
								setPreviewOpen( false )
							}
						>
							<Glyph name="close" />
							<span className={ cls( 'nav-menu__sr' ) }>
								{ __( 'Close menu', 'page-builder-sandwich' ) }
							</span>
						</span>
					) }
					<ul className={ cls( 'nav-menu__list' ) }>
						<Items
							items={ items }
							mega={ new Set( megaAvailable ? panels : [] ) }
							depth={ 0 }
							attributes={ attributes }
						/>
					</ul>
				</div>
			</nav>
		);
	}

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Menu', 'page-builder-sandwich' ) }>
					{ picker }
					<p>
						<ExternalLink href={ menusUrl }>
							{ __( 'Edit menus', 'page-builder-sandwich' ) }
						</ExternalLink>
					</p>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Name for screen readers',
							'page-builder-sandwich'
						) }
						help={ __(
							'Tells screen reader users which menu this is, for example “Main” or “Footer”.',
							'page-builder-sandwich'
						) }
						placeholder={ __( 'Main', 'page-builder-sandwich' ) }
						value={ label }
						onChange={ set( 'label' ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Layout', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Direction', 'page-builder-sandwich' ) }
						value={ orientation }
						options={ options( [
							[
								'horizontal',
								__( 'In a row', 'page-builder-sandwich' ),
							],
							[
								'vertical',
								__( 'In a column', 'page-builder-sandwich' ),
							],
						] ) }
						onChange={ set( 'orientation' ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Open submenus', 'page-builder-sandwich' ) }
						value={ openOn }
						options={ options( [
							[
								'click',
								__(
									'When the arrow is clicked',
									'page-builder-sandwich'
								),
							],
							[
								'hover',
								__(
									'On hover, or when the arrow is clicked',
									'page-builder-sandwich'
								),
							],
						] ) }
						onChange={ set( 'openOn' ) }
					/>
					{ openOn === 'hover' && (
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Close after the pointer leaves',
								'page-builder-sandwich'
							) }
							help={ __(
								'A short wait keeps a submenu open while the pointer crosses a gap.',
								'page-builder-sandwich'
							) }
							value={ String( closeDelay ?? 150 ) }
							options={ options( [
								[
									'0',
									__( 'At once', 'page-builder-sandwich' ),
								],
								[
									'150',
									__(
										'A moment (0.15 s)',
										'page-builder-sandwich'
									),
								],
								[
									'300',
									__(
										'A little later (0.3 s)',
										'page-builder-sandwich'
									),
								],
								[
									'500',
									__(
										'Half a second',
										'page-builder-sandwich'
									),
								],
							] ) }
							onChange={ ( v ) =>
								setAttributes( { closeDelay: Number( v ) } )
							}
						/>
					) }
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Submenu animation',
							'page-builder-sandwich'
						) }
						help={ __(
							'Visitors who ask their device for less motion never see it.',
							'page-builder-sandwich'
						) }
						value={ animation }
						options={ options( [
							[ 'fade', __( 'Fade', 'page-builder-sandwich' ) ],
							[
								'slide',
								__(
									'Fade and slide down',
									'page-builder-sandwich'
								),
							],
							[ 'none', __( 'None', 'page-builder-sandwich' ) ],
						] ) }
						onChange={ set( 'animation' ) }
					/>
					<p>
						{ __(
							'Colours, spacing, corners and shadows of the links, submenus, mobile menu and Menu button are in the Styles tab, under “Menu”.',
							'page-builder-sandwich'
						) }
					</p>
				</PanelBody>
				<PanelBody
					title={ __( 'Mobile menu', 'page-builder-sandwich' ) }
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Fold into a Menu button',
							'page-builder-sandwich'
						) }
						help={ __(
							'Phones and tablets follow the widths set under Design › Breakpoints.',
							'page-builder-sandwich'
						) }
						value={ collapse }
						options={ options( [
							[
								'mobile',
								__( 'On phones', 'page-builder-sandwich' ),
							],
							[
								'tablet',
								__(
									'On phones and tablets',
									'page-builder-sandwich'
								),
							],
							[
								'custom',
								__(
									'Below a width I choose',
									'page-builder-sandwich'
								),
							],
							[
								'always',
								__( 'Always', 'page-builder-sandwich' ),
							],
							[ 'never', __( 'Never', 'page-builder-sandwich' ) ],
						] ) }
						onChange={ set( 'collapse' ) }
					/>
					{ collapse === 'custom' && (
						<RangeControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Fold at this width or narrower (pixels)',
								'page-builder-sandwich'
							) }
							min={ 320 }
							max={ 2560 }
							step={ 10 }
							value={ breakpoint ?? 900 }
							onChange={ ( v ) =>
								setAttributes( {
									breakpoint: Number( v ) || 900,
								} )
							}
						/>
					) }
					{ folds && (
						<>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Shape', 'page-builder-sandwich' ) }
								value={ mobileStyle }
								options={ options( [
									[
										'drawer',
										__(
											'A panel that slides in from the side',
											'page-builder-sandwich'
										),
									],
									[
										'fullscreen',
										__(
											'Full screen',
											'page-builder-sandwich'
										),
									],
									[
										'dropdown',
										__(
											'A dropdown under the button',
											'page-builder-sandwich'
										),
									],
								] ) }
								onChange={ set( 'mobileStyle' ) }
							/>
							{ mobileStyle !== 'fullscreen' && (
								<SelectControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Side',
										'page-builder-sandwich'
									) }
									help={ __(
										'Start is the left in English and the right in Arabic or Hebrew.',
										'page-builder-sandwich'
									) }
									value={ drawerSide }
									options={ options( [
										[
											'end',
											__(
												'End',
												'page-builder-sandwich'
											),
										],
										[
											'start',
											__(
												'Start',
												'page-builder-sandwich'
											),
										],
									] ) }
									onChange={ set( 'drawerSide' ) }
								/>
							) }
							<fieldset className="pbsw-nav-menu__icons">
								<legend>
									{ __(
										'Menu button icon',
										'page-builder-sandwich'
									) }
								</legend>
								{ TOGGLE_ICONS.map( ( name ) => (
									<Button
										key={ name }
										size="compact"
										variant="secondary"
										isPressed={
											( toggleIcon || 'menu' ) === name
										}
										label={ iconNames[ name ] }
										showTooltip
										icon={
											name === 'none' ? (
												'editor-textcolor'
											) : (
												<Glyph name={ name } />
											)
										}
										onClick={ () =>
											setAttributes( {
												toggleIcon: name,
											} )
										}
									/>
								) ) }
							</fieldset>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Menu button words',
									'page-builder-sandwich'
								) }
								placeholder={ __(
									'Menu',
									'page-builder-sandwich'
								) }
								value={ toggleLabel }
								onChange={ set( 'toggleLabel' ) }
							/>
							{ toggleIcon !== 'none' && (
								<ToggleControl
									__nextHasNoMarginBottom
									label={ __(
										'Show the words beside the icon',
										'page-builder-sandwich'
									) }
									help={ __(
										'Hidden words are still read out by screen readers.',
										'page-builder-sandwich'
									) }
									checked={ toggleShowLabel !== false }
									onChange={ set( 'toggleShowLabel' ) }
								/>
							) }
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Menu button look',
									'page-builder-sandwich'
								) }
								value={ toggleStyle }
								options={ options( [
									[
										'outline',
										__(
											'Outlined',
											'page-builder-sandwich'
										),
									],
									[
										'solid',
										__( 'Filled', 'page-builder-sandwich' ),
									],
									[
										'plain',
										__( 'Plain', 'page-builder-sandwich' ),
									],
								] ) }
								onChange={ set( 'toggleStyle' ) }
							/>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Submenus in the mobile menu',
									'page-builder-sandwich'
								) }
								value={ mobileSubmenus }
								options={ options( [
									[
										'collapsed',
										__(
											'Closed, each opens with its arrow',
											'page-builder-sandwich'
										),
									],
									[
										'expanded',
										__(
											'All open',
											'page-builder-sandwich'
										),
									],
								] ) }
								onChange={ set( 'mobileSubmenus' ) }
							/>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Close the mobile menu when a link is tapped',
									'page-builder-sandwich'
								) }
								help={ __(
									'Useful when links jump to sections of the same page. Escape, the close button and a tap outside always close it.',
									'page-builder-sandwich'
								) }
								checked={ closeOnLink !== false }
								onChange={ set( 'closeOnLink' ) }
							/>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Preview the mobile menu here',
									'page-builder-sandwich'
								) }
								help={ __(
									'Only in the editor: shows the Menu button; click it to open the menu. Nothing is saved.',
									'page-builder-sandwich'
								) }
								checked={ preview }
								onChange={ ( v ) => {
									setPreview( v );
									setPreviewOpen( false );
								} }
							/>
						</>
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Accessibility', 'page-builder-sandwich' ) }
					initialOpen={ false }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Add a “Skip to content” link',
							'page-builder-sandwich'
						) }
						help={ __(
							'Keyboard users can jump past the menu. It appears only when it has keyboard focus. Block themes already add one; turn this on for a classic theme.',
							'page-builder-sandwich'
						) }
						checked={ !! skipLink }
						onChange={ set( 'skipLink' ) }
					/>
					{ skipLink && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Id of the main content',
								'page-builder-sandwich'
							) }
							help={ __(
								'The id of the element the link jumps to, without the #, for example main or content.',
								'page-builder-sandwich'
							) }
							value={ skipTarget }
							onChange={ ( v ) =>
								setAttributes( {
									skipTarget: v.replace(
										/[^A-Za-z0-9_-]/g,
										''
									),
								} )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			{ canvas }
			{ menuId > 0 && <div { ...innerProps } /> }
		</div>
	);
}
