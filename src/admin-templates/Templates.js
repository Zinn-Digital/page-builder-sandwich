/**
 * Templates & kits (features pbs-c1, pbs-c2, pbs-r1, pbs-r2, pbs-c3): the website-kit library,
 * plus the tabs the premium code adds (cloud library, team, storage) through the
 * `pbsw.admin.templatesTabs` filter.
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { TabPanel } from '@wordpress/components';

import Kits from './Kits';

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
			render: () => <Kits />,
		},
	];
	const extra = applyFilters( 'pbsw.admin.templatesTabs', [], data ) || [];
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
