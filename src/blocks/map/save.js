/**
 * pbs/map saved markup: with the plugin off, a plain link to the place on OpenStreetMap (no
 * classes, no data-wp-*, nothing loaded from a third party). No translated string: saved markup
 * must be the same whatever language the editor runs in, or the block would read as invalid. The live card is rebuilt by
 * Media::render_map().
 */
import { mapUrls } from './urls';

export default function save( { attributes } ) {
	const { lat, lng, zoom, label } = attributes;
	if ( ! Number.isFinite( lat ) || ! Number.isFinite( lng ) ) {
		return null;
	}
	return (
		<p>
			<a href={ mapUrls( lat, lng, zoom ?? 14 ).link }>
				{ label || `${ lat }, ${ lng }` }
			</a>
		</p>
	);
}
