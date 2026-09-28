/**
 * The saved (fallback) HTML of every core block.
 *
 * ⭐ This is what a visitor reads if the plugin is ever turned off, so it carries no class
 * attribute and nothing that names the plugin — inline styles only (docs/adr/0033). While the
 * plugin is active the server rebuilds the live HTML from the attributes instead
 * (includes/core/class-render.php), so these functions are the fallback, not the product.
 *
 * ⛔ includes/core/class-fallback.php writes the same bytes in PHP for the converter. The two are
 * held together by wp/tests/footprint/page-builder-sandwich/blocks/*.html: this suite and the
 * PHPUnit suite each assert their own side produces those files from samples.json.
 *
 * `style` is passed as a STRING: the `@wordpress/element` serializer writes a non-object style as
 * given, which keeps the author's declarations byte-for-byte.
 */
import { InnerBlocks, RichText } from '@wordpress/block-editor';
import { RawHTML } from '@wordpress/element';

export const ROW_STYLE = 'display:flex;flex-wrap:wrap';
export const COLUMN_STYLE = 'flex:1 1 0;min-width:0';

/**
 * Base style plus the author's declarations.
 *
 * @param {string} base  Base declarations.
 * @param {string} extra Author declarations.
 * @return {string} Joined declarations.
 */
export function joinStyle( base, extra ) {
	const more = ( extra || '' ).trim();
	return more ? `${ base };${ more }` : base;
}

/**
 * An attribute value, or undefined so the serializer leaves the attribute out.
 *
 * @param {string} value Value.
 * @return {string|undefined} The value when non-empty.
 */
const opt = ( value ) => ( value ? value : undefined );

export function rowSave( { attributes } ) {
	return (
		<div style={ joinStyle( ROW_STYLE, attributes.style ) }>
			<InnerBlocks.Content />
		</div>
	);
}

export function columnSave( { attributes } ) {
	return (
		<div style={ joinStyle( COLUMN_STYLE, attributes.style ) }>
			<InnerBlocks.Content />
		</div>
	);
}

export function buttonSave( { attributes } ) {
	const { url, text, style, target, rel, align, wrapStyle, wrapped } =
		attributes;
	const link = (
		<RichText.Content
			tagName="a"
			href={ opt( url ) }
			style={ opt( style ) }
			target={ opt( target ) }
			rel={ opt( rel ) }
			value={ text }
		/>
	);
	// wrapStyle: the rest of a converted legacy button paragraph's style (its margins).
	// wrapped: the legacy button sat in a paragraph, which is part of its layout (a bare link in
	// a flex column stretches to the column's width; in a paragraph it does not).
	const wrap = [ align ? `text-align:${ align }` : '', wrapStyle || '' ]
		.filter( Boolean )
		.join( ';' );
	if ( ! wrap && ! wrapped ) {
		return link;
	}
	return <p style={ wrap || undefined }>{ link }</p>;
}

export function iconSave( { attributes } ) {
	const { svg, style, label, display, justify } = attributes;
	const a11y = label
		? { role: 'img', 'aria-label': label }
		: { 'aria-hidden': 'true' };
	const icon = (
		<span style={ opt( style ) } { ...a11y }>
			<RawHTML>{ svg }</RawHTML>
		</span>
	);
	// 6.2: an icon on its own line is wrapped in a block element (aligned with an inline style
	// in this fallback only). With no `display` — every 6.1 icon — the markup is unchanged, so
	// 6.1 content stays valid without a deprecation (Core\Fallback::icon() writes the same).
	if ( display !== 'block' ) {
		return icon;
	}
	const align = justify === 'center' || justify === 'end' ? justify : '';
	return (
		<div style={ opt( align && `text-align:${ align }` ) }>{ icon }</div>
	);
}

/** Dynamic blocks store attributes only. */
/*
 * Widget / sidebar: rendered live by PHP. The saved HTML is only the FALLBACK a visitor reads if
 * the plugin is ever switched off — a sanitized snapshot of the widget's output, captured on the
 * server (Core\\Fallback::widget_html()) and held in the raw `fallbackHtml` attribute. None → null.
 */
export const dynamicSave = ( { attributes } ) =>
	attributes && attributes.fallbackHtml ? (
		<RawHTML>{ attributes.fallbackHtml }</RawHTML>
	) : null;
