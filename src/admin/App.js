import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ExternalLink,
	Notice,
	Panel,
	PanelBody,
	Spinner,
	TextControl,
} from '@wordpress/components';

import About from './About';
import LegacyContent from './LegacyContent';
import { isValidPrefix } from './prefix';

/**
 * The admin screen.
 *
 * @param {Object} props
 * @param {Object} props.data Boot data printed by Admin::data().
 * @return {Element} The screen.
 */
export default function App( { data } ) {
	const [ settings, setSettings ] = useState( null );
	const [ prefix, setPrefix ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: data.restPath } )
			.then( ( result ) => {
				setSettings( result );
				setPrefix( result.prefix );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: error.message } )
			);
	}, [ data.restPath ] );

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( { path: data.restPath, method: 'POST', data: { prefix } } )
			.then( ( result ) => {
				setSettings( result );
				setNotice( {
					status: 'success',
					message: __( 'Settings saved.', 'page-builder-sandwich' ),
				} );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', message: error.message } )
			)
			.finally( () => setSaving( false ) );
	};

	if ( null === settings && null === notice ) {
		return <Spinner />;
	}

	const prefixValid = isValidPrefix( prefix );

	return (
		<div className="pbs-admin">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<Panel>
				{ data.upgradeUrl && (
					<PanelBody
						title={ __( 'Upgrade', 'page-builder-sandwich' ) }
						initialOpen
					>
						<p>
							<ExternalLink href={ data.upgradeUrl }>
								{ __(
									'See the Pro plans and pricing',
									'page-builder-sandwich'
								) }
							</ExternalLink>
						</p>
					</PanelBody>
				) }
				{ settings && (
					<PanelBody
						title={ __(
							'Front-end output',
							'page-builder-sandwich'
						) }
						initialOpen
					>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Class name prefix',
								'page-builder-sandwich'
							) }
							help={ __(
								'Every class name the plugin prints on your pages starts with this prefix, so the pages do not reveal which plugin built them. 1 to 8 lowercase letters or digits, starting with a letter.',
								'page-builder-sandwich'
							) }
							value={ prefix }
							onChange={ setPrefix }
						/>
						<Button
							variant="primary"
							isBusy={ saving }
							disabled={ saving || ! prefixValid }
							onClick={ save }
						>
							{ __( 'Save', 'page-builder-sandwich' ) }
						</Button>
					</PanelBody>
				) }
				{ settings && (
					<PanelBody
						title={ __( 'Beta updates', 'page-builder-sandwich' ) }
						initialOpen
					>
						{ settings.beta.available ? (
							<>
								<p>
									{ settings.beta.enabled
										? __(
												'This site receives beta versions as updates.',
												'page-builder-sandwich'
											)
										: __(
												'This site receives stable versions only.',
												'page-builder-sandwich'
											) }
								</p>
								<p>
									<ExternalLink
										href={ settings.beta.accountUrl }
									>
										{ __(
											'Change this on the Account page',
											'page-builder-sandwich'
										) }
									</ExternalLink>
								</p>
							</>
						) : (
							<p>
								{ __(
									'Beta versions are offered to licensed Pro installations that are connected to their account. Once connected, you can join from the Account page.',
									'page-builder-sandwich'
								) }
							</p>
						) }
					</PanelBody>
				) }
				{ data.migrationPath && (
					<LegacyContent path={ data.migrationPath } />
				) }
				<About
					version={ data.version }
					productUrl={ data.productUrl }
					companyUrl={ data.companyUrl }
				/>
			</Panel>
		</div>
	);
}
