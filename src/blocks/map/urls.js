/**
 * The map's URLs (twin of Blocks\Media::map_urls()) and a forgiving coordinate reader.
 */
/**
 * A number with at most six decimals and no trailing zeros (Media::num()).
 *
 * @param {number} n Number.
 * @return {string} Text.
 */
export function num( n ) {
	const s = Number( n )
		.toFixed( 6 )
		.replace( /0+$/, '' )
		.replace( /\.$/, '' );
	return s === '-0' ? '0' : s;
}

/**
 * Embed and link URLs for a point.
 *
 * @param {number} lat  Latitude.
 * @param {number} lng  Longitude.
 * @param {number} zoom Zoom 1–19.
 * @return {{embed:string, link:string}} URLs.
 */
export function mapUrls( lat, lng, zoom ) {
	const span = ( 360 / 2 ** zoom ) * 1.2;
	const bbox = [
		num( Math.max( -180, lng - span ) ),
		num( Math.max( -85, lat - span / 2 ) ),
		num( Math.min( 180, lng + span ) ),
		num( Math.min( 85, lat + span / 2 ) ),
	].join( ',' );
	const pt = `${ num( lat ) },${ num( lng ) }`;
	return {
		embed: `https://www.openstreetmap.org/export/embed.html?bbox=${ encodeURIComponent(
			bbox
		) }&layer=mapnik&marker=${ encodeURIComponent( pt ) }`,
		link: `https://www.openstreetmap.org/?mlat=${ num( lat ) }&mlon=${ num(
			lng
		) }#map=${ zoom }/${ num( lat ) }/${ num( lng ) }`,
	};
}

/**
 * Coordinates from "51.5, -0.12", an OpenStreetMap URL (#map=z/lat/lng or mlat/mlon) or a
 * Google Maps URL (@lat,lng,z).
 *
 * @param {string} text Pasted text.
 * @return {{lat:number,lng:number,zoom?:number}|null} Point.
 */
export function parseCoordinates( text ) {
	const s = String( text || '' ).trim();
	let m = /#map=(\d{1,2})\/(-?\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)/.exec( s );
	if ( m ) {
		return check( m[ 2 ], m[ 3 ], m[ 1 ] );
	}
	m = /mlat=(-?\d+(?:\.\d+)?)&mlon=(-?\d+(?:\.\d+)?)/.exec( s );
	if ( m ) {
		return check( m[ 1 ], m[ 2 ] );
	}
	m = /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?),(\d{1,2})(?:\.\d+)?z/.exec( s );
	if ( m ) {
		return check( m[ 1 ], m[ 2 ], m[ 3 ] );
	}
	m = /^(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)$/.exec( s );
	return m ? check( m[ 1 ], m[ 2 ] ) : null;
}

function check( lat, lng, zoom ) {
	const a = Number( lat );
	const b = Number( lng );
	if ( a < -85 || a > 85 || b < -180 || b > 180 ) {
		return null;
	}
	const out = { lat: a, lng: b };
	if ( zoom !== undefined ) {
		out.zoom = Math.min( 19, Math.max( 1, Number( zoom ) ) );
	}
	return out;
}
