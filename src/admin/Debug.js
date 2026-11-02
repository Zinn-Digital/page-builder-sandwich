/**
 * Debug mode on the settings screen (row 71a): a switch, and while it is on every block of the
 * plugin and its add-ons with its version and whether something overrides it (and what), read
 * from `pbs/v1/debug`. The list can be copied as plain text for a support ticket.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	PanelBody,
	Spinner,
	ToggleControl,
} from '@wordpress/components';

/**
 * The edition a module comes with, in words.
 *
 * @param {Object} m Module.
 * @return {string} Label.
 */
function edition( m ) {
	if ( m.source === 'addon' ) {
		return m.plugin
			? sprintf(
					/* translators: %s: the add-on plugin's name. */
					__( 'Add-on: %s', 'page-builder-sandwich' ),
					m.plugin
				)
			: __( 'Add-on', 'page-builder-sandwich' );
	}
	return m.source === 'pro'
		? __( 'Pro', 'page-builder-sandwich' )
		: __( 'Free', 'page-builder-sandwich' );
}

/**
 * What overrides a module, in words.
 *
 * @param {Object} m Module.
 * @return {string} Label.
 */
function overriddenBy( m ) {
	if ( ! m.overridden ) {
		return __( 'No', 'page-builder-sandwich' );
	}
	return sprintf(
		/* translators: %s: where the override comes from, e.g. "theme: twentytwentyfive". */
		__( 'Yes, by %s', 'page-builder-sandwich' ),
		[ ...new Set( m.overrides.map( ( o ) => o.by ) ) ].join( ', ' )
	);
}

/**
 * @param {Object} props
 * @param {string} props.path    REST path.
 * @param {string} props.version The plugin's version.
 * @return {Element} Panel.
 */
export default function Debug( { path, version } ) {
	const [ report, setReport ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path } )
			.then( setReport )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			);
	}, [ path ] );

	const toggle = ( enabled ) => {
		setBusy( true );
		setNotice( null );
		apiFetch( { path, method: 'POST', data: { enabled } } )
			.then( setReport )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			)
			.finally( () => setBusy( false ) );
	};

	const copy = () => {
		const lines = report.modules.map(
			( m ) =>
				`${ m.name } | ${ m.version } | ${ edition( m ) } | ${ overriddenBy(
					m
				) }`
		);
		const text = [ `Page Builder Sandwich ${ version }`, ...lines ].join(
			'\n'
		);
		window.navigator.clipboard?.writeText( text ).then( () =>
			setNotice( {
				status: 'success',
				message: __( 'Copied.', 'page-builder-sandwich' ),
			} )
		);
	};

	const overridden = report
		? report.modules.filter( ( m ) => m.overridden ).length
		: 0;

	return (
		<PanelBody
			className="pbs-admin-debug"
			title={ __( 'Debug mode', 'page-builder-sandwich' ) }
			initialOpen={ false }
		>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			{ ! report && ! notice && <Spinner /> }
			{ report && (
				<>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Debug mode', 'page-builder-sandwich' ) }
						help={ __(
							'Lists every block of the plugin and its add-ons with its version, and shows whether a theme or another plugin overrides it. Only administrators see it.',
							'page-builder-sandwich'
						) }
						checked={ report.enabled }
						disabled={ busy }
						onChange={ toggle }
					/>
					{ report.enabled && (
						<>
							<p>
								{ sprintf(
									/* translators: 1: number of blocks, 2: number of them overridden. */
									__(
										'Blocks: %1$d. Overridden: %2$d.',
										'page-builder-sandwich'
									),
									report.modules.length,
									overridden
								) }{ ' ' }
								<Button variant="secondary" onClick={ copy }>
									{ __(
										'Copy as text',
										'page-builder-sandwich'
									) }
								</Button>
							</p>
							<table className="widefat striped">
								<thead>
									<tr>
										<th scope="col">
											{ __(
												'Block',
												'page-builder-sandwich'
											) }
										</th>
										<th scope="col">
											{ __(
												'Version',
												'page-builder-sandwich'
											) }
										</th>
										<th scope="col">
											{ __(
												'Edition',
												'page-builder-sandwich'
											) }
										</th>
										<th scope="col">
											{ __(
												'Overridden',
												'page-builder-sandwich'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ report.modules.map( ( m ) => (
										<tr key={ m.name }>
											<td>
												{ m.title }
												<br />
												<code>{ m.name }</code>
											</td>
											<td>
												<code>
													{ m.version || '–' }
												</code>
											</td>
											<td>{ edition( m ) }</td>
											<td>
												{ m.overridden ? (
													<strong>
														{ overriddenBy( m ) }
													</strong>
												) : (
													overriddenBy( m )
												) }
												{ m.overridden && (
													<>
														<br />
														<code>
															{ m.overrides
																.map(
																	( o ) =>
																		o.hook
																)
																.join( ', ' ) }
														</code>
													</>
												) }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</>
					) }
				</>
			) }
		</PanelBody>
	);
}
