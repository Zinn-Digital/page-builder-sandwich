/**
 * pbs/qr-code saved markup: with the plugin off, the QR code itself and its caption (no classes,
 * no data-wp-*). The live figure is rebuilt by Media::render_qr_code() from the sanitised SVG.
 */
import { RawHTML } from '@wordpress/element';
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { text, svg, caption } = attributes;
	if ( ! text || ! svg ) {
		return null;
	}
	return (
		<figure>
			<RawHTML>{ svg }</RawHTML>
			{ caption && (
				<RichText.Content tagName="figcaption" value={ caption } />
			) }
		</figure>
	);
}
