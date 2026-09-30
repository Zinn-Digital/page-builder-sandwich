/**
 * Templates & kits (features pbs-c1, pbs-c2, pbs-r1, pbs-r2, pbs-c3): the website-kit library,
 * plus the tabs the premium code adds (cloud library, team, storage) through the
 * `pbsw.admin.templatesTabs` filter. On a free site the cloud-library tab explains the feature
 * and links to the plans; no Pro code ships in the free package.
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { ExternalLink, TabPanel } from '@wordpress/components';

import Kits from './Kits';

/**
 * The upsell tab a free site sees in place of the cloud library.
 *
 * @param {Object} props
 * @param {Object} props.data Boot data.
 * @return {Element} Tab body.
 */
function CloudUpsell( { data } ) {
	return (
		<div className="pbs-templates__upsell">
			<p>
				{ __(
					'Save sections, pages and styles to your cloud library and reuse them on all your sites. Agencies can share a library with their team and clients. You can keep the library in Zinn Digital® storage or in your own Cloudflare R2 or S3-compatible bucket.',
					'page-builder-sandwich'
				) }
			</p>
			{ data.upgradeUrl && (
				<ExternalLink href={ data.upgradeUrl }>
					{ __(
						'Get Page Builder Sandwich Pro',
						'page-builder-sandwich'
					) }
				</ExternalLink>
			) }
		</div>
	);
}

/**
 * The screen.
 *
 * @param {Object} props
 * @param {Object} props.data Boot data printed by Admin::data().
 * @return {Element} The screen.
 */
export default function Templates( { data } ) {
	const base = [
		{
			name: 'kits',
			title: __( 'Website kits', 'page-builder-sandwich' ),
			render: () => <Kits data={ data } />,
		},
	];
	const extra = applyFilters( 'pbsw.admin.templatesTabs', null, data ) || [
		{
			name: 'cloud',
			title: __( 'Cloud library', 'page-builder-sandwich' ),
			render: () => <CloudUpsell data={ data } />,
		},
	];
	const tabs = [ ...base, ...extra ];

	return (
		<div className="pbs-admin pbs-templates">
			<TabPanel
				className="pbs-templates__tabs"
				tabs={ tabs.map( ( { name, title } ) => ( { name, title } ) ) }
			>
				{ ( tab ) =>
					tabs.find( ( t ) => t.name === tab.name ).render()
				}
			</TabPanel>
		</div>
	);
}
