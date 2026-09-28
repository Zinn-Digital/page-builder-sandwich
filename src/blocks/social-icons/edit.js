/**
 * pbs/social-icons editor: the front end's own list of icon links. Choosing a network stores
 * that network's brand icon (Font Awesome Free brands, from the bundled icon set) with the link,
 * so the page draws it as inline SVG with no request. Any icon from the library can replace it.
 */
import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

import { cls } from '../shared/cls';
import { Icon, IconButton, ItemTools, itemIcon, listOps } from '../shared/kit';
import { justifyOptions } from '../badge/edit';
import { loadSet, loadStyle } from '../../design/icons/sets';
import { iconSvg } from '../../design/icons/sanitize';

/** Network slug → [label, Font Awesome brand icon name | '', built-in fallback]. */
export const NETWORKS = {
	facebook: [ 'Facebook', 'facebook', 'link' ],
	'x-twitter': [ 'X', 'x-twitter', 'link' ],
	instagram: [ 'Instagram', 'instagram', 'link' ],
	linkedin: [ 'LinkedIn', 'linkedin', 'link' ],
	youtube: [ 'YouTube', 'youtube', 'link' ],
	tiktok: [ 'TikTok', 'tiktok', 'link' ],
	pinterest: [ 'Pinterest', 'pinterest', 'link' ],
	github: [ 'GitHub', 'github', 'link' ],
	whatsapp: [ 'WhatsApp', 'whatsapp', 'phone' ],
	telegram: [ 'Telegram', 'telegram', 'link' ],
	reddit: [ 'Reddit', 'reddit', 'link' ],
	mastodon: [ 'Mastodon', 'mastodon', 'link' ],
	threads: [ 'Threads', 'threads', 'link' ],
	bluesky: [ 'Bluesky', 'bluesky', 'link' ],
	discord: [ 'Discord', 'discord', 'link' ],
	twitch: [ 'Twitch', 'twitch', 'link' ],
	snapchat: [ 'Snapchat', 'snapchat', 'link' ],
	tumblr: [ 'Tumblr', 'tumblr', 'link' ],
	vimeo: [ 'Vimeo', 'vimeo', 'link' ],
	dribbble: [ 'Dribbble', 'dribbble', 'link' ],
	behance: [ 'Behance', 'behance', 'link' ],
	medium: [ 'Medium', 'medium', 'link' ],
	spotify: [ 'Spotify', 'spotify', 'link' ],
	email: [ '', '', 'mail' ],
	phone: [ '', '', 'phone' ],
	rss: [ '', '', 'rss' ],
	website: [ '', '', 'link' ],
};

/**
 * The label of a network (the generic ones are translated).
 *
 * @param {string} network Slug.
 * @return {string} Label.
 */
export function networkLabel( network ) {
	const generic = {
		email: __( 'Email', 'page-builder-sandwich' ),
		phone: __( 'Phone', 'page-builder-sandwich' ),
		rss: __( 'RSS feed', 'page-builder-sandwich' ),
		website: __( 'Website', 'page-builder-sandwich' ),
	};
	return generic[ network ] || ( NETWORKS[ network ] || [] )[ 0 ] || '';
}

/**
 * The brand icon of a network, from the bundled Font Awesome Free brands set.
 *
 * @param {string} network Slug.
 * @return {Promise<{svg:string,iconRef:string}>} Icon ('' when the network has none).
 */
export async function brandIcon( network ) {
	const name = ( NETWORKS[ network ] || [] )[ 1 ];
	if ( ! name ) {
		return { svg: '', iconRef: '' };
	}
	const set = await loadSet( 'fa' );
	await loadStyle( set, 'brands' );
	const entry = set.byName[ name ];
	const body = entry && entry.bodies.brands;
	return body
		? { svg: iconSvg( set.root, body ), iconRef: `fa:brands:${ name }` }
		: { svg: '', iconRef: '' };
}

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const { items, look, shape, size, justify, newTab } = attributes;
	const set = ( next ) => setAttributes( { items: next } );
	const blockProps = useBlockProps( {
		className: cls(
			'social-icons',
			`social-icons--${ look }`,
			`social-icons--${ shape }`,
			`social-icons--${ size }`,
			`social-icons--${ justify }`
		),
	} );
	const choose = async ( i, network ) => {
		const icon = await brandIcon( network ).catch( () => ( {
			svg: '',
			iconRef: '',
		} ) );
		setAttributes( {
			items: listOps.update( items, i, {
				networkSlug: network,
				...itemIcon( icon ),
			} ),
		} );
	};
	const add = async () => {
		const icon = await brandIcon( 'facebook' ).catch( () => ( {
			svg: '',
			iconRef: '',
		} ) );
		set(
			listOps.add( items, {
				networkSlug: 'facebook',
				url: '',
				label: '',
				...itemIcon( icon ),
			} )
		);
	};
	const networkOptions = Object.keys( NETWORKS ).map( ( k ) => ( {
		value: k,
		label: networkLabel( k ),
	} ) );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Links', 'page-builder-sandwich' ) }>
					{ items.map( ( item, i ) => {
						const name =
							item.label ||
							networkLabel( item.networkSlug ) ||
							sprintf(
								/* translators: %d: link number. */
								__( 'Link %d', 'page-builder-sandwich' ),
								i + 1
							);
						return (
							<fieldset key={ i } className="pbsw-kit-fieldset">
								<legend>{ name }</legend>
								<SelectControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Network',
										'page-builder-sandwich'
									) }
									value={ item.networkSlug || 'website' }
									options={ networkOptions }
									onChange={ ( v ) => choose( i, v ) }
								/>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									type="url"
									label={ __(
										'Address',
										'page-builder-sandwich'
									) }
									help={ __(
										'A profile address, mailto:you@example.com or tel:+441234567890.',
										'page-builder-sandwich'
									) }
									value={ item.url || '' }
									onChange={ ( v ) =>
										set(
											listOps.update( items, i, {
												url: v,
											} )
										)
									}
								/>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __(
										'Name read to screen readers',
										'page-builder-sandwich'
									) }
									placeholder={ networkLabel(
										item.networkSlug
									) }
									value={ item.label || '' }
									onChange={ ( v ) =>
										set(
											listOps.update( items, i, {
												label: v,
											} )
										)
									}
								/>
								<IconButton
									svg={ item.icon }
									iconRef={ item.iconId }
									label={ __(
										'Other icon',
										'page-builder-sandwich'
									) }
									onChange={ ( patch ) =>
										set(
											listOps.update(
												items,
												i,
												itemIcon( patch )
											)
										)
									}
								/>
								<ItemTools
									index={ i }
									count={ items.length }
									name={ name }
									onMove={ ( by ) =>
										set( listOps.move( items, i, by ) )
									}
									onRemove={ () =>
										set( listOps.remove( items, i ) )
									}
								/>
							</fieldset>
						);
					} ) }
					<Button variant="secondary" onClick={ add }>
						{ __( 'Add link', 'page-builder-sandwich' ) }
					</Button>
				</PanelBody>
				<PanelBody
					title={ __( 'Look', 'page-builder-sandwich' ) }
					initialOpen={ false }
				>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Style', 'page-builder-sandwich' ) }
						value={ look }
						options={ [
							{
								value: 'filled',
								label: __( 'Filled', 'page-builder-sandwich' ),
							},
							{
								value: 'outline',
								label: __( 'Outline', 'page-builder-sandwich' ),
							},
							{
								value: 'plain',
								label: __(
									'Icon only',
									'page-builder-sandwich'
								),
							},
						] }
						onChange={ ( v ) => setAttributes( { look: v } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Shape', 'page-builder-sandwich' ) }
						value={ shape }
						options={ [
							{
								value: 'circle',
								label: __( 'Circle', 'page-builder-sandwich' ),
							},
							{
								value: 'rounded',
								label: __(
									'Rounded square',
									'page-builder-sandwich'
								),
							},
							{
								value: 'square',
								label: __( 'Square', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( v ) => setAttributes( { shape: v } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Size', 'page-builder-sandwich' ) }
						value={ size }
						options={ [
							{
								value: 'small',
								label: __( 'Small', 'page-builder-sandwich' ),
							},
							{
								value: 'medium',
								label: __( 'Medium', 'page-builder-sandwich' ),
							},
							{
								value: 'large',
								label: __( 'Large', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( v ) => setAttributes( { size: v } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Alignment', 'page-builder-sandwich' ) }
						value={ justify }
						options={ justifyOptions() }
						onChange={ ( v ) => setAttributes( { justify: v } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Open profiles in a new tab',
							'page-builder-sandwich'
						) }
						checked={ newTab }
						onChange={ ( v ) => setAttributes( { newTab: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ul { ...blockProps }>
				{ items.map( ( item, i ) => (
					<li key={ i } className={ cls( 'social-icons__item' ) }>
						<span
							className={ cls(
								'social-icons__link',
								`social-icons__link--${ item.networkSlug || 'website' }`
							) }
							role="img"
							aria-label={
								item.label || networkLabel( item.networkSlug )
							}
						>
							<Icon
								svg={ item.icon }
								builtin={
									( NETWORKS[ item.networkSlug ] ||
										NETWORKS.website )[ 2 ]
								}
							/>
						</span>
					</li>
				) ) }
				{ ( isSelected || ! items.length ) && (
					<li className="pbsw-kit-add">
						<Button
							variant="secondary"
							size="compact"
							onClick={ add }
						>
							{ __( 'Add link', 'page-builder-sandwich' ) }
						</Button>
					</li>
				) }
			</ul>
		</>
	);
}
