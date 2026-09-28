/**
 * Breakpoints on the settings screen (pbs-d2): the tablet and mobile widths, saved through
 * `pbs/v1/design/breakpoints`. The premium layer adds its custom-breakpoint editor through the
 * `pbsw.admin.breakpointsExtra` filter (pbs-d3), so the free build carries no premium UI.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	PanelBody,
	Spinner,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- no stable export exists (WordPress 6.8-7.1); core's own panels use it.
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

/**
 * @param {Object} props
 * @param {string} props.path REST path.
 * @return {Element} Panel.
 */
export default function Breakpoints( { path } ) {
	const [ data, setData ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		apiFetch( { path } )
			.then( ( r ) => {
				setData( r );
				setDraft( r );
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			);
	}, [ path ] );

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path,
			method: 'POST',
			data: {
				tablet: Number( draft.tablet ),
				mobile: Number( draft.mobile ),
				custom: draft.custom || [],
			},
		} )
			.then( ( r ) => {
				setData( r );
				setDraft( r );
				setNotice( {
					status: 'success',
					message: __(
						'Breakpoints saved. Pages pick up the new widths on their next view.',
						'page-builder-sandwich'
					),
				} );
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			)
			.finally( () => setSaving( false ) );
	};

	/**
	 * Premium: the custom-breakpoint editor, or nothing.
	 *
	 * @type {Function|null}
	 */
	const Extra = applyFilters( 'pbsw.admin.breakpointsExtra', null );

	return (
		<PanelBody
			title={ __( 'Breakpoints', 'page-builder-sandwich' ) }
			initialOpen
		>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			{ ! draft && ! notice && <Spinner /> }
			{ draft && (
				<>
					<p>
						{ __(
							'Styles set for Tablet apply up to the tablet width, and styles set for Mobile up to the mobile width. Desktop styles apply everywhere else.',
							'page-builder-sandwich'
						) }
					</p>
					<div className="pbs-admin-breakpoints">
						<NumberControl
							__next40pxDefaultSize
							label={ __(
								'Tablet: up to (px)',
								'page-builder-sandwich'
							) }
							min={ 480 }
							max={ 2560 }
							value={ draft.tablet }
							onChange={ ( tablet ) =>
								setDraft( { ...draft, tablet } )
							}
						/>
						<NumberControl
							__next40pxDefaultSize
							label={ __(
								'Mobile: up to (px)',
								'page-builder-sandwich'
							) }
							min={ 240 }
							max={ 2559 }
							value={ draft.mobile }
							onChange={ ( mobile ) =>
								setDraft( { ...draft, mobile } )
							}
						/>
					</div>
					{ Extra && (
						<Extra
							value={ draft.custom || [] }
							onChange={ ( custom ) =>
								setDraft( { ...draft, custom } )
							}
						/>
					) }
					<p>
						<Button
							variant="primary"
							isBusy={ saving }
							disabled={
								saving ||
								JSON.stringify( draft ) ===
									JSON.stringify( data )
							}
							onClick={ save }
						>
							{ __(
								'Save breakpoints',
								'page-builder-sandwich'
							) }
						</Button>{ ' ' }
						<Button
							variant="tertiary"
							disabled={ saving }
							onClick={ () =>
								setDraft( {
									...draft,
									tablet: data.defaults.tablet,
									mobile: data.defaults.mobile,
								} )
							}
						>
							{ __(
								'Use the default widths',
								'page-builder-sandwich'
							) }
						</Button>
					</p>
				</>
			) }
		</PanelBody>
	);
}
