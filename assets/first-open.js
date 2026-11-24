/**
 * Block editor: offer to convert a legacy page. Nothing changes until the button is pressed.
 *
 * @package ZinnDigital\PBS
 */
( function ( wp, config ) {
	if ( ! config ) {
		return;
	}
	wp.domReady( function () {
		var notices = wp.data.dispatch( 'core/notices' );
		function failed() {
			notices.createErrorNotice( config.failed, { id: 'pbsw-first-open-failed' } );
		}
		notices.createWarningNotice( config.message, {
			id: 'pbsw-first-open',
			isDismissible: true,
			actions: [
				{
					label: config.button,
					onClick: function () {
						notices.removeNotice( 'pbsw-first-open' );
						wp.apiFetch( { path: config.path, method: 'POST' } )
							.then( function ( result ) {
								if ( result && 'converted' === result.outcome ) {
									window.location.reload();
								} else {
									failed();
								}
							} )
							.catch( failed );
					},
				},
			],
		} );
	} );
}( window.wp, window.pbswFirstOpen ) );
