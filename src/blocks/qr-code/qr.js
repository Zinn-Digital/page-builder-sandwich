/**
 * QR codes drawn locally (qrcode-generator, MIT, bundled — no QR service): error correction M,
 * UTF-8 text, a quiet zone of four modules, one path of black squares on a white square.
 */
import qrcode from 'qrcode-generator';

/**
 * The SVG for a text, or '' when it does not fit a QR code.
 *
 * @param {string} text Text (a web address, a phone link, anything).
 * @return {string} SVG markup.
 */
export function qrSvg( text ) {
	if ( ! text ) {
		return '';
	}
	try {
		// UTF-8 bytes (the package's own table covers Latin-1 only).
		qrcode.stringToBytes = ( s ) =>
			Array.from( new TextEncoder().encode( s ) );
		const qr = qrcode( 0, 'M' );
		qr.addData( text, 'Byte' );
		qr.make();
		const n = qr.getModuleCount();
		const size = n + 8;
		let d = '';
		// One rectangle per horizontal run of dark modules.
		for ( let r = 0; r < n; r++ ) {
			for ( let c = 0; c < n; c++ ) {
				if ( ! qr.isDark( r, c ) ) {
					continue;
				}
				let len = 1;
				while ( c + len < n && qr.isDark( r, c + len ) ) {
					len++;
				}
				d += `M${ c + 4 } ${ r + 4 }h${ len }v1h-${ len }z`;
				c += len - 1;
			}
		}
		return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${ size } ${ size }" shape-rendering="crispEdges"><rect width="${ size }" height="${ size }" fill="#fff"/><path d="${ d }" fill="#000"/></svg>`;
	} catch {
		return ''; // Too long for the largest QR code.
	}
}
