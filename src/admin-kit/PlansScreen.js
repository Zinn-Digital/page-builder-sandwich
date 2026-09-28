/* Generated from wp/packages/zinn-admin-kit/src/js/PlansScreen.js by wp/bin/build-admin-kit.php. Edit the package, never this copy. */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Notice,
} from '@wordpress/components';

import { kitFetch, errorMessage } from './api';
import { badgeText, includes, planName, planSites } from './plans';
import DataList from './DataList';

/**
 * Plans and licence (features adm-6, adm-7, adm-8, sh-r1, sh-r7): what this site runs, the
 * no-card trial, the upgrade and renewal offers, and the plan comparison — generated from the one
 * plan -> feature matrix (`class-matrix.php`), so a screen can never promise a tier the code does
 * not grant.
 *
 * @param {Object} props
 * @param {Object} props.kit Boot data.
 * @return {Element} The screen.
 */
export default function Plans( { kit } ) {
	const licence = kit.licence || {};
	const [ notice, setNotice ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const paid = ( kit.plans || [] ).filter( ( p ) => 'free' !== p.id );

	const startTrial = () => {
		setBusy( true );
		setNotice( null );
		kitFetch( kit, 'trial', { method: 'POST' } )
			.then( ( result ) => {
				if ( result.url ) {
					window.location.assign( result.url );
					return;
				}
				setNotice( {
					status: result.started ? 'success' : 'warning',
					message: result.started
						? __(
								'Your trial has started. Reload the page to see every Pro feature.',
								'page-builder-sandwich'
							)
						: result.message,
				} );
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					message: errorMessage(
						error,
						__(
							'The trial could not be started.',
							'page-builder-sandwich'
						)
					),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	const fields = [
		{
			id: 'title',
			label: __( 'Feature', 'page-builder-sandwich' ),
			render: ( row ) => row.title,
		},
		...( kit.plans || [] ).map( ( plan ) => ( {
			id: plan.id,
			label: planName( plan.id ),
			render: ( row ) =>
				includes( plan.id, row.min ) ? (
					<span
						className="zak-yes"
						aria-label={ __( 'Included', 'page-builder-sandwich' ) }
					>
						✓
					</span>
				) : (
					<span
						className="zak-no"
						aria-label={ __( 'Not included', 'page-builder-sandwich' ) }
					>
						–
					</span>
				),
		} ) ),
	];
	const areas = [
		...new Set( ( kit.matrix || [] ).map( ( row ) => row.area ) ),
	];

	return (
		<div className="zak-stack">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<Card className="zak-card" data-zak-tour="licence">
				<CardHeader>
					<h2 className="zak-card__title">
						{ __( 'Your licence', 'page-builder-sandwich' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<dl className="zak-facts">
						<dt>{ __( 'Edition', 'page-builder-sandwich' ) }</dt>
						<dd>{ badgeText( licence ) }</dd>
						{ licence.premium && (
							<>
								<dt>{ __( 'Sites', 'page-builder-sandwich' ) }</dt>
								<dd>
									{ licence.unlimited
										? __(
												'Unlimited sites',
												'page-builder-sandwich'
											)
										: sprintf(
												/* translators: 1: sites in use, 2: sites allowed. */
												__(
													'%1$d of %2$d in use',
													'page-builder-sandwich'
												),
												licence.activated || 0,
												licence.sites || 0
											) }
								</dd>
							</>
						) }
						{ licence.renewal && (
							<>
								<dt>
									{ licence.renewal.expired
										? __( 'Expired', 'page-builder-sandwich' )
										: __( 'Renews', 'page-builder-sandwich' ) }
								</dt>
								<dd>
									{ new Date(
										licence.renewal.expires
									).toLocaleDateString() }
								</dd>
							</>
						) }
					</dl>
					{ licence.freeLocalhost && (
						<p className="zak-muted">
							{ __(
								'Staging, test and localhost copies of a site are free and never count toward your site limit.',
								'page-builder-sandwich'
							) }
						</p>
					) }
					{ licence.priority && (
						<p className="zak-muted">
							{ __(
								'Your plan includes priority support.',
								'page-builder-sandwich'
							) }
						</p>
					) }
					{ licence.accountUrl && (
						<p>
							<Button
								variant="secondary"
								href={ licence.accountUrl }
							>
								{ __(
									'Manage your licence and billing',
									'page-builder-sandwich'
								) }
							</Button>
						</p>
					) }
				</CardBody>
			</Card>

			{ ! licence.premium && (
				<Card className="zak-card" data-zak-tour="offers">
					<CardHeader>
						<h2 className="zak-card__title">
							{ __( 'Get more with Pro', 'page-builder-sandwich' ) }
						</h2>
					</CardHeader>
					<CardBody>
						<ul className="zak-plans">
							{ paid.map( ( plan ) => (
								<li key={ plan.id }>
									<strong>{ planName( plan.id ) }</strong>{ ' ' }
									<span>{ planSites( plan ) }</span>
								</li>
							) ) }
						</ul>
						<p className="zak-actions">
							{ licence.trial && licence.trial.available && (
								<Button
									variant="primary"
									isBusy={ busy }
									disabled={ busy }
									onClick={ startTrial }
								>
									{ sprintf(
										/* translators: %d: number of days. */
										_n(
											'Start a free %d-day trial, no card needed',
											'Start a free %d-day trial, no card needed',
											licence.trial.days,
											'page-builder-sandwich'
										),
										licence.trial.days
									) }
								</Button>
							) }
							{ licence.upgradeUrl && (
								<Button
									variant="secondary"
									href={ licence.upgradeUrl }
								>
									{ __(
										'See plans and prices',
										'page-builder-sandwich'
									) }
								</Button>
							) }
						</p>
						{ licence.bundle && (
							<p className="zak-muted">
								{ __(
									'Use Tranzly and Page Builder Sandwich together? The bundle includes both Pro plugins for less.',
									'page-builder-sandwich'
								) }
							</p>
						) }
					</CardBody>
				</Card>
			) }

			{ licence.premium && licence.renewal && licence.renewal.expired && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Your licence has expired. Renew it to keep getting updates and Pro features.',
						'page-builder-sandwich'
					) }{ ' ' }
					<ExternalLink href={ licence.renewal.url }>
						{ __( 'Renew now', 'page-builder-sandwich' ) }
					</ExternalLink>
				</Notice>
			) }

			<Card className="zak-card" data-zak-tour="compare">
				<CardHeader>
					<h2 className="zak-card__title">
						{ __( 'Compare the plans', 'page-builder-sandwich' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<DataList
						items={ ( kit.matrix || [] ).filter(
							( row ) => row.compare
						) }
						fields={ fields }
						getItemId={ ( row ) => row.id }
						getText={ ( row ) => row.title }
						searchLabel={ __(
							'Search features',
							'page-builder-sandwich'
						) }
						filters={ [
							{
								id: 'area',
								label: __( 'Area', 'page-builder-sandwich' ),
								options: areas.map( ( a ) => ( {
									value: a,
									label: a,
								} ) ),
								test: ( row, value ) => row.area === value,
							},
							{
								id: 'plan',
								label: __( 'Included in', 'page-builder-sandwich' ),
								options: ( kit.plans || [] ).map( ( p ) => ( {
									value: p.id,
									label: planName( p.id ),
								} ) ),
								test: ( row, value ) =>
									includes( value, row.min ),
							},
						] }
						perPage={ 25 }
						emptyText={ __(
							'No feature matches.',
							'page-builder-sandwich'
						) }
						label={ __( 'Features by plan', 'page-builder-sandwich' ) }
					/>
				</CardBody>
			</Card>
		</div>
	);
}
