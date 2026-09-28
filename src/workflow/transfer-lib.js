import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Offer a JSON value as a file download.
 *
 * @param {Object} value    The value.
 * @param {string} filename The file name.
 */
export function downloadJson( value, filename ) {
	const blob = new window.Blob( [ JSON.stringify( value, null, 2 ) ], {
		type: 'application/json',
	} );
	const url = window.URL.createObjectURL( blob );
	const a = document.createElement( 'a' );
	a.href = url;
	a.download = filename;
	document.body.appendChild( a );
	a.click();
	a.remove();
	window.URL.revokeObjectURL( url );
}

/**
 * Read a chosen file as JSON.
 *
 * @param {File} file The file.
 * @return {Promise<Object>} The parsed value.
 */
export function readJsonFile( file ) {
	if ( ! file ) {
		return Promise.reject( new Error( 'no file' ) );
	}
	return file.text().then( ( text ) => {
		const value = JSON.parse( text );
		if ( ! value || 'object' !== typeof value ) {
			throw new Error( 'not an object' );
		}
		return value;
	} );
}

/**
 * One sentence describing an import report.
 *
 * @param {Object}  report The server's report.
 * @param {boolean} dryRun Whether this is a preview.
 * @return {string} The sentence.
 */
export function summarise( report, dryRun ) {
	const parts = [
		sprintf(
			/* translators: %d: number of items. */
			_n(
				'%d new',
				'%d new',
				report.created.length,
				'page-builder-sandwich'
			),
			report.created.length
		),
		sprintf(
			/* translators: %d: number of items. */
			_n(
				'%d replaced',
				'%d replaced',
				report.replaced.length,
				'page-builder-sandwich'
			),
			report.replaced.length
		),
		sprintf(
			/* translators: %d: number of items. */
			_n(
				'%d kept as they were',
				'%d kept as they were',
				report.skipped.length,
				'page-builder-sandwich'
			),
			report.skipped.length
		),
		sprintf(
			/* translators: %d: number of settings. */
			_n(
				'%d setting',
				'%d settings',
				report.settings.length,
				'page-builder-sandwich'
			),
			report.settings.length
		),
	];
	const list = parts.join( ', ' );
	return dryRun
		? sprintf(
				/* translators: %s: a list such as "2 new, 1 replaced". */
				__( 'Importing will give: %s.', 'page-builder-sandwich' ),
				list
			)
		: sprintf(
				/* translators: %s: a list such as "2 new, 1 replaced". */
				__( 'Imported: %s.', 'page-builder-sandwich' ),
				list
			);
}
